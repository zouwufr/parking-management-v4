<?php

namespace ParkingManagement;

use Booking\Order;
use Booking\ParkingType;
use Booking\Site;
use Booking\VehicleType;
use Exception;
use InvalidArgumentException;
use ParkingManagement\API\PriceAPI;
use ParkingManagement\database\database;
use ParkingManagement\interfaces\IParkingmanagement;
use ParkingManagement\interfaces\IShortcode;
use Price\Page;
use WP_REST_Request;

require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "price" . DS . "includes" . DS . "page.php";
require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "price" . DS . "includes" . DS . "api.php";


class Price implements IShortcode, IParkingmanagement
{
	private ParkingManagement $pm;

	public function __construct(ParkingManagement $pm)
	{
		$this->pm = $pm;
	}

	public function shortcode(string $type): string
	{
		try {
			$this->enqueue();
			$site = Site::current();
			if ($site === null) {
				return '';
			}
			$page = new Page($this->pm);
			$page->setSite($site);
			return Html::_div(array('class' => 'container-md mt-5'),
				$page->table(),
			);
		} catch (Exception $e) {
			Logger::error("price.shortcode", $e->getMessage());
			return '';
		}
	}

	/**
	 * Gets the price calculation for a parking booking
	 * @param array|WP_REST_Request $data Request data containing booking details
	 * @return array Price calculation results or empty array on error
	 */
	public function getPrice(array|WP_REST_Request $data): array
	{
		try {
			$site_id = $this->getData($data, 'site_id');
			$site = !empty($site_id) ? Site::find($site_id) : Site::defaultSite();
			if ($site === null) {
				Logger::error("price.getPrice", ['message' => 'no site configured']);
				return array();
			}

			$form = $site['form'];
			$high_season = $site['high_season'];

			// Extract and validate dates
			$start = substr($this->getData($data, 'depart'), 0, 10);
			$start_hour = substr($this->getData($data, 'depart'), 11, 5);
			$end = substr($this->getData($data, 'retour'), 0, 10);
			$end_hour = substr($this->getData($data, 'retour'), 11, 5);

			if (!$start || !$end || "$start" == " 00:00" || "$end" == " 00:00") {
				Logger::error("price.getPrice.dates", [
					'start' => $start,
					'end' => $end,
					'error' => 'Invalid dates provided'
				]);
				return array();
			}

			Logger::info("price.getPrice.dates", [
				'start' => $start,
				'start_hour' => $start_hour,
				'end' => $end,
				'end_hour' => $end_hour,
			]);


			$realNumberOfDay = $numberOfDay = Order::nbRealDay($start, $end);
			// Get lot availability
			$siteId = Order::getSiteID($site['airport']);
			$maxLot = Booked::getMaxLot($start, $end, $siteId);
			$usedLot = Booked::usedLot($start, $end, $siteId);
			$maxUsedLot = max($usedLot);

			Logger::info("price.getPrice.lots", [
				'max_lot' => $maxLot,
				'used_lot' => $usedLot,
				'max_used_lot' => $maxUsedLot
			]);

			$percentage = array();
			foreach ($maxLot as $k => $v) {
				$percentage[] = !empty($usedLot[$k]) ? $v / $usedLot[$k] : 0;
			}

			$price = array(
				'du' => !empty($start) ? $start : NULL,
				'au' => !empty($end) ? $end : NULL,
				'complet' => 0,
				'toolong' => 0,
				'holiday' => 0,
				'high_season' => 0,
				'total' => 0,
				'total_reel' => 0,
				'timing' => 0,
				'max' => (int)max($maxLot),
				'utilise' => $maxUsedLot,
				'nb_jour_reel' => $realNumberOfDay,
				'nb_jour' => $numberOfDay,
				'pourcentage' => max($percentage),
				'nb_vehicule' => 1,
				'promo' => 0,
				'options' => array(),
			);

			if (Booked::is($start, $site) || Booked::is($end, $site)) {
				Logger::info("price.getPrice.booked", [
					'start_booked' => Booked::is($start),
					'end_booked' => Booked::is($end)
				]);
				$price['complet'] = 1;
				return $price;
			}

			// Process vehicle type
			$type_id = $this->getData($data, 'type_id');
			$type_id = !empty($type_id) && is_numeric($type_id) ? $type_id : '1';
			if (!is_numeric($type_id)) {
				$type_id = '1';
			}
			$type_id = VehicleType::fromInt((int)$type_id);

			$parking_type = $this->resolveParkingType($data, $site, $type_id);
			$vehicleKey = strtolower($type_id->name);
			$parkingKey = self::parkingTypeKey($parking_type);
			$tariff = $site['tariffs'][$vehicleKey][$parkingKey] ?? [];

			Logger::info("price.getPrice.types", [
				'site_id' => $site['id'],
				'vehicle_type' => $type_id->name,
				'parking_type' => $parking_type->name,
			]);

			if (empty($tariff)) {
				Logger::error("price.getPrice.tariff", [
					'site_id' => $site['id'],
					'vehicle_type' => $vehicleKey,
					'parking_type' => $parkingKey,
					'message' => 'no tariff configured for this combination',
				]);
				return [];
			}

			// Calculate base price
			if (!empty($tariff[$numberOfDay])) {
				$total = $tariff[$numberOfDay];
			} else {
				$latest = self::latestPrice($tariff);
				$total = $tariff[$latest] ?? 0;
				$total += ($numberOfDay - $latest) * ($tariff['jour_supplementaire'] ?? 0);
			}

			$price['total'] = $price['total_reel'] = (int)$total;

			// Apply holiday surcharge
			try {
				if ($this->isHoliday($start)) {
					$price['holiday'] = 1;
					$price['total'] += $this->get_extra_price($form['options']['holiday']);
					Logger::info("price.getPrice.holiday", ['start_date' => $start]);
				}
				if ($this->isHoliday($end)) {
					$price['holiday'] = 1;
					$price['total'] += $this->get_extra_price($form['options']['holiday']);
					Logger::info("price.getPrice.holiday", ['end_date' => $end]);
				}
			} catch (Exception $e) {
				Logger::error("price.getPrice.holiday", [
					'error' => $e->getMessage()
				]);
				// Continue without holiday pricing if check fails
			}

			if (HighSeason::is($start, $site) || HighSeason::is($end, $site)) {
				$price['high_season'] = 1;
				$price['total'] += $high_season['price'];
				Logger::info("price.getPrice.highSeason", [
					'start_high_season' => HighSeason::is($start),
					'end_high_season' => HighSeason::is($end)
				]);
			}

			$nb_pax = $this->getData($data, 'nb_pax');
			if (!empty($nb_pax) && $nb_pax > 4) {
				$extra = ($nb_pax - 4) * $this->get_extra_price($form['options']['shuttle']);
				$price['total'] += $extra;
				Logger::info("price.getPrice.passengers", [
					'passengers' => $nb_pax,
					'extra_charge' => $extra
				]);
			}

			$night_start = $this->night_extra($start_hour, $form['options']['night_extra_charge']);
			$night_end = $this->night_extra($end_hour, $form['options']['night_extra_charge']);
			$price['total'] += $night_start + $night_end;

			if ($night_start || $night_end) {
				Logger::info("price.getPrice.nightCharge", [
					'start_hour' => $start_hour,
					'end_hour' => $end_hour,
					'start_charge' => $night_start,
					'end_charge' => $night_end
				]);
			}

			$cancellation_insurance = $this->getData($data, 'cancellation_insurance');
			if (!empty($cancellation_insurance) && ($cancellation_insurance == '1')) {
				$insurance_price = (int)$form['options']['cancellation_insurance']['price'];
				$price['total'] += $insurance_price;
				Logger::info("price.getPrice.insurance", ['price' => $insurance_price]);
			}

			$keep_keys = $this->getData($data, 'keep_keys');
			if (!empty($keep_keys) && ($keep_keys == '1')) {
				$keep_keys = (int)$form['options']['keep_keys']['price'];
				$price['total'] += $keep_keys;
				Logger::info("price.getPrice.keep_keys", ['price' => $keep_keys]);
			}

			$ev_charging = $this->getData($data, 'ev_charging');
			if (!empty($ev_charging) && ($ev_charging == '1')) {
				$ev_charging = (int)$form['options']['ev_charging']['price'];
				$price['total'] += $ev_charging;
				Logger::info("price.getPrice.ev_charging", ['price' => $ev_charging]);
			}

			$price['timing'] = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];

			Logger::info("price.getPrice.complete", [
				'final_price' => $price['total'],
				'calculation_time' => $price['timing']
			]);

			return $price;

		} catch (Exception $e) {
			Logger::error("price.getPrice.error", [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'request_data' => $data instanceof WP_REST_Request ? $data->get_params() : $data
			]);
			return [];
		}
	}

	/**
	 * Picks the parking type (ext/int/valet) to price against: the submitted value
	 * when it is one the site actually enables, otherwise the site's first enabled
	 * type.
	 */
	/**
	 * Picks ext/int/valet to price against. Valet is only ever offered for cars,
	 * regardless of what the site enables, so it's excluded from the candidate
	 * list entirely for any other vehicle type.
	 */
	private function resolveParkingType(array|WP_REST_Request $data, array $site, VehicleType $type_id): ParkingType
	{
		$isCar = $type_id === VehicleType::CAR;
		$submitted = $this->getData($data, 'parking_type');
		if ($submitted !== '' && is_numeric($submitted)) {
			try {
				$candidate = ParkingType::fromInt((int)$submitted);
				$key = self::parkingTypeKey($candidate);
				if (($key !== 'valet' || $isCar) && ($site['type'][$key] ?? '0') === '1') {
					return $candidate;
				}
			} catch (InvalidArgumentException $e) {
				// fall through to the site's default below
			}
		}
		foreach (['ext' => ParkingType::OUTSIDE, 'int' => ParkingType::INSIDE, 'valet' => ParkingType::VALET] as $key => $case) {
			if ($key === 'valet' && !$isCar) {
				continue;
			}
			if (($site['type'][$key] ?? '0') === '1') {
				return $case;
			}
		}
		return ParkingType::OUTSIDE;
	}

	private static function parkingTypeKey(ParkingType $type): string
	{
		return match ($type) {
			ParkingType::INSIDE => 'int',
			ParkingType::VALET => 'valet',
			default => 'ext',
		};
	}

	/**
	 * @throws Exception
	 */
	public function isHoliday(string $date): bool
	{
		$conn = database::connect();
		if ($conn === false)
			throw new Exception("Database connection failed");
		if ($row = $conn->get_row(
			$conn->prepare("SELECT DATE_FORMAT(`date`, '%d/%m/%Y') as `date`, `titre` FROM `tbl_ferie` WHERE `date` = %s", [$date]),
			ARRAY_A
		)) {
			return !empty($row);
		}
		return false;
	}

	/**
	 * Pulls the most recently written tariff grid out of the legacy
	 * `tbl_remplissage.grille_tarifaire` column - the same row/table
	 * `Booked::getMaxLot()`/`usedLot()` already read `client`/`employe`/`utilisee`
	 * from - and reshapes it into this plugin's own
	 * `tariffs[vehicle][parking_type][day]` grid for the given airport. Used by
	 * the admin "Load from DB" action to prefill a site's Price tab from data the
	 * legacy system already has, instead of hand-typing or CSV-importing it.
	 * @throws Exception
	 */
	public function loadFromDatabase(string $airport): array
	{
		$conn = database::connect();
		if (!$conn) {
			throw new Exception("Database connection failed");
		}
		$row = $conn->get_row("SELECT `grille_tarifaire` FROM `tbl_remplissage` ORDER BY `date` DESC LIMIT 1", ARRAY_A);
		if (!empty($conn->last_error)) {
			Logger::error("price.loadFromDatabase", ['errorInfo' => $conn->last_error]);
			throw new Exception("Error executing query");
		}
		if (empty($row['grille_tarifaire'])) {
			return [];
		}
		$grid = @unserialize($row['grille_tarifaire'], ['allowed_classes' => false]);
		if (!is_array($grid)) {
			return [];
		}
		$parkingId = Order::getSiteID($airport)->value;
		$siteGrid = $grid[$parkingId] ?? [];
		if (!is_array($siteGrid)) {
			return [];
		}

		$tariffs = [];
		foreach (VehicleType::cases() as $vehicleType) {
			foreach (ParkingType::cases() as $parkingType) {
				if ($parkingType === ParkingType::VALET) {
					continue; // the legacy grid has no valet slot - valet is this plugin's own addition
				}
				$days = $siteGrid[$vehicleType->value][$parkingType->value] ?? [];
				if (empty($days) || !is_array($days)) {
					continue;
				}
				$entry = [];
				for ($day = 1; $day <= 10; $day++) {
					if (isset($days[$day]) && is_numeric($days[$day])) {
						$entry[$day] = $days[$day];
					}
				}
				if (isset($days['jour_supplementaire']) && is_numeric($days['jour_supplementaire'])) {
					$entry['jour_supplementaire'] = $days['jour_supplementaire'];
				}
				if (!empty($entry)) {
					$tariffs[strtolower($vehicleType->name)][self::parkingTypeKey($parkingType)] = $entry;
				}
			}
		}
		return $tariffs;
	}

	/**
	 * Returns every active site's own tariff table, keyed by site id, used by the
	 * price REST endpoint when no specific dates are requested.
	 */
	public function allSitesTariffs(): array
	{
		$price = [];
		foreach (Site::activeOnly() as $id => $site) {
			foreach ($site['vehicle_types'] ?? [] as $vehicleKey => $enabled) {
				if ($enabled !== '1') {
					continue;
				}
				foreach (['ext', 'int', 'valet'] as $parkingKey) {
					if (($site['type'][$parkingKey] ?? '0') !== '1') {
						continue;
					}
					$tariff = $site['tariffs'][$vehicleKey][$parkingKey] ?? [];
					$days = [];
					foreach ($tariff as $day => $value) {
						if (is_numeric($day) && !empty($value)) {
							$days[$day] = (int)$value;
						}
					}
					if (empty($days)) {
						continue;
					}
					$price[$id][$parkingKey][$vehicleKey] = [
						'prices' => $days,
						'extra_day' => (int)($tariff['jour_supplementaire'] ?? 0),
					];
				}
			}
		}
		return $price;
	}

	public static function latestPrice($grid): int
	{
		$day = 0;
		foreach ($grid as $k => $v) {
			if (!is_numeric($k)) {
				continue;
			}
			if (empty($v)) {
				break;
			}
			$day = (int)$k;
		}
		return $day;
	}

	private function night_extra($hour, $night_extra_charge): int
	{
		if ($night_extra_charge['enabled'] === '1') {
			$hour = (int)str_replace(":", "", $hour);
			$start = (int)str_replace(":", "", $night_extra_charge['start'] ?? '21:00');
			$end = (int)str_replace(":", "", $night_extra_charge['end'] ?? '07:00');
			if (($hour < $end) || ($hour > $start))
				return $night_extra_charge['price'];
		}
		return 0;
	}

	private function get_extra_price($option): int
	{
		if ($option['enabled'] === '1') {
			return $option['price'];
		}
		return 0;
	}

	private function getData(WP_REST_Request|array $data, $field = null)
	{
		if (($data instanceof WP_REST_Request)) {
			if ($data->has_param($field))
				return $data->get_param($field);
			return '';
		}
		if (is_null($field) || !array_key_exists($field, $data))
			return '';
		return $data[$field];
	}

	private function enqueue(): void
	{
		wp_enqueue_style('parking-management-booking', pkmgmt_plugin_url('modules/price/css/price.css'));
		wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', array(), '5.3.3');
	}
}

new PriceAPI();
