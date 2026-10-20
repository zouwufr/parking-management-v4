<?php

namespace ParkingManagement\API;

use Exception;
use ParkingManagement\Logger;
use ParkingManagement\Price;
use WP_Error;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

class PriceAPI extends API
{

	private const rest_base = '/prices';

	public function __construct()
	{
		add_action('rest_api_init', array($this, 'register_routes'));
	}

	public function register_routes(): void
	{
		register_rest_route($this->namespace, self::rest_base, array(
				array(
					'methods' => WP_REST_Server::READABLE,
					'callback' => array($this, 'get_item'),
					'permission_callback' => '__return_true',
					'args' => array(
						'depart' => array(
							'description' => "Starting date for the getting price",
							'type' => "string",
							'required' => false,
						),
						'retour' => array(
							'description' => "Ending date for the getting price",
							'type' => "string",
							'required' => false,
						),
						'nb_pax' => array(
							'description' => "Number of passenger",
							'type' => "int",
							'required' => false,
						),
						'type_id' => array(
							'description' => "Vehicle type",
							'type' => "int",
							'required' => false,
						),
						'site_id' => array(
							'description' => "Parking site",
							'type' => "string",
							'required' => false,
						),
						'any_param' => array(
							'required' => false,
						),
					),

				)
			)
		);

		register_rest_route($this->namespace, self::rest_base . '/db-grid', array(
				array(
					'methods' => WP_REST_Server::READABLE,
					'callback' => array($this, 'get_db_grid'),
					'permission_callback' => function () {
						return current_user_can('manage_options');
					},
					'args' => array(
						'legacy_site_id' => array(
							'description' => "Legacy parking_id (tbl_remplissage/tbl_commande) to load the tariff grid for",
							'type' => "string",
							'required' => true,
						),
					),
				)
			)
		);
	}

	/**
	 * Admin-only: pulls the legacy system's own tariff grid (tbl_remplissage.grille_tarifaire)
	 * for the given legacy site id, used to prefill a site's Price tab from the database.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function get_db_grid($request): WP_Error|WP_REST_Response
	{
		try {
			$pm = getParkingManagementInstance();
			$price = new Price($pm);
			$data = $price->loadFromDatabase($request->get_param('legacy_site_id'));
		} catch (Exception $e) {
			Logger::error("price.api.get_db_grid", $e->getMessage());
			return new WP_Error('error', $e->getMessage());
		}
		return rest_ensure_response($data);
	}

	/**
	 * Retrieves one item from the collection.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 * @since 4.7.0
	 *
	 */
	public function get_item($request): WP_Error|WP_REST_Response
	{
		Logger::info("price.api.get_item", ['request' =>
			[
				'params' => $request->get_params(),
				'method' => $request->get_method(),
			]
		]);
		try {
			$pm = getParkingManagementInstance();
			$price = new Price($pm);
			if ($request->has_param('depart') && $request->has_param('retour')) {
				$data = $price->getPrice($request);
			} else{
				$data = $price->allSitesTariffs();
			}
		} catch (Exception $e) {
			Logger::error("price.api.get_item", $e->getMessage());
			return new WP_Error('error', $e->getMessage());
		}
		return rest_ensure_response($data);
	}
}
