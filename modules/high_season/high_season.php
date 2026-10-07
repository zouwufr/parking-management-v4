<?php

namespace ParkingManagement;

use Booking\Site;
use DateTime;
use DateTimeZone;
use Exception;
use ParkingManagement\API\HighSeasonApi;
use ParkingManagement\interfaces\IParkingmanagement;
use ParkingManagement\interfaces\IShortcode;

include_once PKMGMT_PLUGIN_MODULES_DIR . DS . 'high_season' . DS . 'api.php';

class HighSeason implements IShortcode, IParkingmanagement
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
		wp_enqueue_style('parking-management-high-season', pkmgmt_plugin_url('modules/high_season/css/style.css'));
	}
	private static function HTMLMessage(array $site, string $locale): string
	{
		try {
			$high_season = $site['high_season'] ?? [];
			if (!array_key_exists('dates', $high_season))
				return '';
			$zone = new DateTimeZone("Europe/Paris");
			$date = new DateTime( 'now', $zone);
			$hs = DatesRange::getDateRange($date->format('Y-m-d'), $high_season['dates']);
			$message = DatesRange::getMessage($hs, $locale);
			if (empty($message)) {
				return '';
			}
			return Html::_div(
				['class' => 'high-season'],
				Html::_alert('warning', $message)
			);

		} catch (Exception $e) {
			Logger::error("database.connect", $e->getMessage());
		}
		return '';
	}

	public static function is($date, ?array $site = null): bool
	{
		$site = $site ?? Site::current();
		if ($site === null)
			return false;
		$high_season = $site['high_season'] ?? [];
		if (!array_key_exists('dates', $high_season))
			return false;
		return DatesRange::isContain($date, $high_season['dates']);
	}
}

new HighSeasonAPI();
