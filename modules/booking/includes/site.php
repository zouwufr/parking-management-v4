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
	 * Finds the site whose legacy parking identity (Order::resolveLegacySiteID(),
	 * aka parking_id/site_id in the legacy tbl_commande grid) matches the given
	 * value. Used to recover the plugin site from an order row's `parking_id` (or
	 * `site_id`) column - both now always hold the same legacy value, not this
	 * plugin's own site config key - in contexts with no `site_id` request param
	 * (payment provider redirect, webhook, notification page).
	 */
	public static function findByLegacySiteId(string|int $legacySiteId): ?array
	{
		$legacySiteId = (string)$legacySiteId;
		foreach (self::all() as $id => $site) {
			if (!is_array($site)) {
				continue;
			}
			$site['id'] = (string)$id;
			if ((string)Order::resolveLegacySiteID($site)->value === $legacySiteId) {
				return $site;
			}
		}
		return null;
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
