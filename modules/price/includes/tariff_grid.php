<?php

namespace ParkingManagement;

use Booking\Order;
use Booking\ParkingType;
use Booking\VehicleType;
use ParkingManagement\database\database;

/**
 * Tariffs read live from parkineo's `tbl_remplissage.grille_tarifaire` - the grids
 * parkineo's own booking site and admin price from - so WordPress and parkineo always
 * charge the same. Prices are managed in parkineo only; the plugin keeps no copy.
 *
 * Shapes:
 * - legacy grid (one row):  [legacy site id][VehicleType value][ParkingType value][day|'jour_supplementaire']
 * - plugin tariffs:         [vehicle key][parking key][day|'jour_supplementaire']
 */
class TariffGrid
{
	/** Days per vehicle/parking type in the legacy grids. */
	private const LEGACY_GRID_DAYS = 60;

	/** @var array<string, array> distinct grids per date range, for this request */
	private static array $gridsCache = [];

	/**
	 * The site's tariffs for a stay from $from to $to (Y-m-d). When the stay spans
	 * several grids, parkineo's rule applies: the grid giving the highest price for
	 * this vehicle/parking type and length of stay wins.
	 */
	public static function forStay(array $site, string $from, string $to, string $vehicleKey, string $parkingKey, int $days): array
	{
		$legacyId = Order::resolveLegacySiteID($site)->value;
		$best = null;
		$bestPrice = -1;
		foreach (self::grids($from, $to) as $grid) {
			if (empty($grid[$legacyId]) || !is_array($grid[$legacyId])) {
				continue;
			}
			$tariffs = self::fromLegacySiteGrid($grid[$legacyId]);
			$price = self::price($tariffs[$vehicleKey][$parkingKey] ?? [], $days);
			if ($price > $bestPrice) {
				$best = $tariffs;
				$bestPrice = $price;
			}
		}
		if ($best === null) {
			Logger::error('tariff_grid.forStay', [
				'site_id' => $site['id'] ?? null,
				'legacy_site_id' => $legacyId,
				'from' => $from,
				'to' => $to,
				'message' => 'no parkineo grid for these dates/site',
			]);
			return [];
		}
		return $best;
	}

	/** The site's tariffs in force on $date (Y-m-d), e.g. for the public price table. */
	public static function onDate(array $site, string $date): array
	{
		return self::forStay($site, $date, $date, 'car', 'ext', 1);
	}

	/**
	 * Price of $days days on one vehicle/parking tariff: the day's price, or the
	 * latest priced day plus `jour_supplementaire` per extra day.
	 */
	public static function price(array $tariff, int $days): int
	{
		if (!empty($tariff[$days])) {
			return (int)$tariff[$days];
		}
		$latest = Price::latestPrice($tariff);
		if ($latest === 0) {
			return 0;
		}
		return (int)($tariff[$latest] ?? 0) + ($days - $latest) * (int)($tariff['jour_supplementaire'] ?? 0);
	}

	/**
	 * Plugin tariffs in the legacy single-site shape `[VehicleType][ParkingType][day]`,
	 * i.e. what the parkineo admin writes to `tbl_commande.grille_tarifaire` and
	 * reprices from when an order is edited. Days 1..60 are all filled in (with
	 * price()), then `jour_supplementaire`: the admin's own latestPrice() stops at the
	 * first empty day and would otherwise land on the `jour_supplementaire` key.
	 */
	public static function toLegacySiteGrid(array $tariffs): array
	{
		$grid = [];
		foreach (VehicleType::cases() as $vehicleType) {
			foreach (ParkingType::cases() as $parkingType) {
				$tariff = $tariffs[self::vehicleKey($vehicleType)][self::parkingKey($parkingType)] ?? [];
				if (empty($tariff) || !is_array($tariff) || Price::latestPrice($tariff) === 0) {
					continue;
				}
				$days = [];
				for ($day = 1; $day <= self::LEGACY_GRID_DAYS; $day++) {
					$days[$day] = self::price($tariff, $day);
				}
				$days['jour_supplementaire'] = $tariff['jour_supplementaire'] ?? 0;
				$grid[$vehicleType->value][$parkingType->value] = $days;
			}
		}
		return $grid;
	}

	private static function fromLegacySiteGrid(array $siteGrid): array
	{
		$tariffs = [];
		foreach (VehicleType::cases() as $vehicleType) {
			foreach (ParkingType::cases() as $parkingType) {
				$days = $siteGrid[$vehicleType->value][$parkingType->value] ?? null;
				if (is_array($days)) {
					$tariffs[self::vehicleKey($vehicleType)][self::parkingKey($parkingType)] = $days;
				}
			}
		}
		return $tariffs;
	}

	/** Distinct unserialized grids of tbl_remplissage between $from and $to inclusive. */
	private static function grids(string $from, string $to): array
	{
		$key = "$from|$to";
		if (array_key_exists($key, self::$gridsCache)) {
			return self::$gridsCache[$key];
		}
		$grids = [];
		$conn = database::connect();
		if ($conn) {
			$rows = $conn->get_col($conn->prepare(
				"SELECT DISTINCT `grille_tarifaire` FROM `tbl_remplissage` WHERE `date` BETWEEN %s AND %s",
				[$from, $to]
			));
			if (!empty($conn->last_error)) {
				Logger::error('tariff_grid.grids', ['errorInfo' => $conn->last_error]);
			}
			foreach ($rows ?: [] as $row) {
				$grid = @unserialize((string)$row, ['allowed_classes' => false]);
				if (is_array($grid)) {
					$grids[] = $grid;
				}
			}
		}
		return self::$gridsCache[$key] = $grids;
	}

	private static function vehicleKey(VehicleType $type): string
	{
		return strtolower($type->name);
	}

	private static function parkingKey(ParkingType $type): string
	{
		return match ($type) {
			ParkingType::INSIDE => 'int',
			ParkingType::VALET => 'valet',
			default => 'ext',
		};
	}
}
