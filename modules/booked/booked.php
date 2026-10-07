<?php

namespace ParkingManagement;

use Booking\Airport;
use Booking\Order;
use Booking\Site;
use DateTime;
use DateTimeZone;
use Exception;
use ParkingManagement\API\BookedAPI;
use ParkingManagement\database\database;
use ParkingManagement\interfaces\IParkingmanagement;
use ParkingManagement\interfaces\IShortcode;

include_once PKMGMT_PLUGIN_MODULES_DIR . DS . 'booked' . DS . 'api.php';

class Booked implements IShortcode, IParkingManagement
{

	private ParkingManagement $pm;

	public function __construct(ParkingManagement $pm)
	{
		$this->pm = $pm;
	}

	public function shortcode(string $type): string
	{
		$this->enqueue();
		$site = Site::current();
		if ($site === null) {
			return '';
		}
		return self::HTMLMessage($site, $this->pm->locale);
	}

	private function enqueue(): void
	{
		wp_enqueue_style('parking-management-booked', pkmgmt_plugin_url('modules/booked/css/style.css'));
	}

	/**
	 * @throws Exception
	 */
	public static function getMaxLot($start, $end, $parking_id = Airport::ORLY, $field = NULL): array
	{
		$conn = database::connect();
		if (!$conn)
			throw new Exception("Database connection failed");
		$field = !empty($field) ? 'employe' : 'client';
		$maxLot = array();
		$nbDays = Order::nbRealDay($start, $end);
		$start = DateTime::createFromFormat('Y-m-d', $start);
		$end = DateTime::createFromFormat('Y-m-d', $end);

		// wpdb::get_results(..., ARRAY_A) always returns an array - an empty one both on
		// a real query failure AND on a legitimate "no rows for this range" result, so
		// the return value alone can't tell the two apart. Checking `!$results` (or
		// `=== null`) treated a simple lack of capacity data for a date range as a hard
		// error and aborted the whole price calc. last_error is the actual error signal.
		$results = $conn->get_results(
			$conn->prepare(
				"SELECT $field FROM `tbl_remplissage` WHERE `date` >= %s AND `date` < DATE_ADD(%s, INTERVAL 1 DAY) ORDER BY `date`",
				[$start->format('Y-m-d'), $end->format('Y-m-d')]
			), ARRAY_A);
		if (!empty($conn->last_error)) {
			Logger::error("booked.getMaxLot", ['errorInfo' => $conn->last_error]);
			throw new Exception("Error executing query");
		}
		foreach ($results as $row) {
			$deserialized = unserialize($row[$field]);
			$maxLot[] = $deserialized[$parking_id->value];
		}
		return (count($maxLot) > 0) ? $maxLot : range(0, $nbDays);
	}

	/**
	 * @throws Exception
	 */
	public static function usedLot($start, $end = NULL, $parking_id = Airport::ORLY): array
	{
		$conn = database::connect();
		if (!$conn)
			throw new Exception("Database connection failed");
		$start = DateTime::createFromFormat('Y-m-d', $start);
		$end = !empty($end) ? DateTime::createFromFormat('Y-m-d', $end) : DateTime::createFromFormat('Y-m-d', $start);

		$used = array();
		$results = $conn->get_results(
			$conn->prepare(
				"SELECT `date`, `utilisee` FROM `tbl_remplissage` WHERE `date` >= %s AND `date` <= %s",
				[$start->format('Y-m-d'), $end->format('Y-m-d')]
			), ARRAY_A);
		if (!empty($conn->last_error)) {
			Logger::error("booked.usedLot", ['errorInfo' => $conn->last_error]);
			throw new Exception("Error executing query");
		}
		foreach ($results as $row) {
			$deserialized = unserialize($row['utilisee']);
			$used[$row['date']] = abs($deserialized[$parking_id->value]);
		}
		ksort($used);
		$used = array_values($used);
		return !empty($used) ? $used : array(0);
	}


	private static function HTMLMessage(array $site, string $locale): string
	{
		try {
			$zone = new DateTimeZone("Europe/Paris");
			$date = new DateTime('now', $zone);
			$booked = DatesRange::getDateRange($date->format('Y-m-d'), $site['booked_dates'] ?? []);
			$message = DatesRange::getMessage($booked, $locale);
			if (empty($message)) {
				return '';
			}
			return Html::_div(
				['class' => 'booked'],
				Html::_alert('danger', $message)
			);

		} catch (Exception $e) {
			Logger::error("booked.HTMLMessage", $e->getMessage());
		}
		return '';
	}

	public static function is($date, ?array $site = null): bool
	{
		$site = $site ?? Site::current();
		if ($site === null) {
			return false;
		}
		$booked_dates = $site['booked_dates'] ?? [];
		if (empty($booked_dates)) {
			return false;
		}
		return DatesRange::isContain($date, $booked_dates);
	}
}

new BookedAPI();
