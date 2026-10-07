<?php

namespace ParkingManagement\API;

use Booking\Site;
use ParkingManagement\DatesRange;
use ParkingManagement\Logger;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class HighSeasonApi extends Api
{
	private const rest_base = '/high-season';

	public function __construct()
	{
		add_action('rest_api_init', [$this, 'register_routes']);
	}

	public function register_routes(): void
	{
		register_rest_route($this->namespace, self::rest_base, [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [$this, 'get_items'],
			'permission_callback' => '__return_true',
			'args' => [
				'raw' => [
					'description' => 'get raw values',
					'default' => "false",
					'required' => false
				],
				'site_id' => [
					'description' => 'The site to get the high season window for',
					'required' => false
				],
			]
		]);
	}

	/**
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items($request): WP_REST_Response|WP_Error
	{
		$site_id = $request->get_param('site_id');
		$site = !empty($site_id) ? Site::find($site_id) : Site::defaultSite();
		if ($site === null)
			return new WP_Error('error', __('failed to get config', 'parking-management'), ['status' => 500]);
		$highSeasons = $site['high_season'] ?? [];
		$raw = $request->has_param('raw') ? $request->get_param('raw') : false;
		if (empty($highSeasons['dates']) || !is_array($highSeasons['dates']))
			return rest_ensure_response([]);
		if (isEmptyOrTrue($raw))
			$hs = DatesRange::getDatesRange($highSeasons['dates']);
		else
			$hs = DatesRange::getDatesRangeAPI($highSeasons['dates']);

		return rest_ensure_response($hs);
	}
}
