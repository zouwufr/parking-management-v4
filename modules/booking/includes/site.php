<?php

namespace Booking;

class Site
{
	/**
	 * Returns every configured site, keyed by its numeric id.
	 */
	public static function all(): array
	{
		$pm = getParkingManagementInstance();
		if (!$pm) {
			return array();
		}
		$sites = $pm->prop('sites');
		return is_array($sites) ? $sites : array();
	}

	/**
	 * Returns only the sites marked as active.
	 */
	public static function activeOnly(): array
	{
		return array_filter(
			self::all(),
			static function ($site) {
				return is_array($site) && ($site['active'] ?? '0') === '1';
			}
		);
	}

	/**
	 * Finds a single site by id. Returns null if it does not exist.
	 */
	public static function find(string|int $id): ?array
	{
		$sites = self::all();
		$id = (string)$id;
		if ($id === '' || !array_key_exists($id, $sites) || !is_array($sites[$id])) {
			return null;
		}
		$site = $sites[$id];
		$site['id'] = $id;
		return $site;
	}

	/**
	 * Returns the first active site, used as the default when nothing else applies.
	 */
	public static function defaultSite(): ?array
	{
		$active = self::activeOnly();
		if (empty($active)) {
			return null;
		}
		return self::find(array_key_first($active));
	}

	/**
	 * Resolves the site for the current request: whatever `site_id` was submitted
	 * (GET, POST, or REST param already merged into $_REQUEST), falling back to the
	 * first active site when none was submitted or it no longer exists.
	 */
	public static function current(): ?array
	{
		$id = $_REQUEST['site_id'] ?? null;
		if (!empty($id)) {
			$site = self::find($id);
			if ($site !== null) {
				return $site;
			}
		}
		return self::defaultSite();
	}
}
