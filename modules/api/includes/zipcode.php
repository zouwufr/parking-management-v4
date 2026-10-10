<?php

namespace ParkingManagement\API;

use Booking\Order;
use Exception;
use ParkingManagement\database\database;
use ParkingManagement\Logger;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

class Zipcode extends API
{
	protected $rest_base = '/zipcode';

	public function __construct()
	{
		add_action('rest_api_init', array($this, 'register_routes'));
	}

	public function register_routes(): void
	{
		register_rest_route($this->namespace, $this->rest_base, array(
				array(
					'methods' => WP_REST_Server::READABLE,
					'callback' => array($this, 'get_item'),
					'permission_callback' => '__return_true',
					'args' => array(
						'term' => array(
							'description' => "Term to search",
							'type' => "string",
							'required' => true,
						),
						'any_params' => array(
							'required' => false,
						),
					),
				)
			)
		);
	}

	public function get_item($request): WP_Error|WP_REST_Response
	{
		try {
			$conn = database::connect();
			if (!$conn)
				return new WP_Error("database_connection", __("Database connection failed.", 'parking-management'));
			$data = array();
			if ($results = $conn->get_results(
				$conn->prepare(
					"SELECT `id_code_postal`, `pays_id`, `code_postal`, `ville` FROM `tbl_code_postal` WHERE `code_postal` LIKE %s ORDER BY `pays_id` , `code_postal` ",
					[addslashes($request['term']) . "%"]
				), ARRAY_A)) {
				foreach ($results as $row) {
					$data[] = array(
						'id' => (int)$row['id_code_postal'],
						'pays_id' => (int)$row['pays_id'],
						'value' => $row['code_postal'],
						'label' => $row['code_postal'] . ' (' . stripslashes($row['ville']) . ')',
						'ville' => stripslashes($row['ville']),
						'pays' => stripslashes(Order::PhoneCountry[$row['pays_id']]['pays']),
						'category' => stripslashes(Order::PhoneCountry[$row['pays_id']]['pays'])
					);
				}
			}
			return rest_ensure_response($data);
		} catch (Exception $e) {
			Logger::error("zipcode.get_item", ['request' => $request, 'error' => $e->getMessage()]);
			return new WP_Error('error', $e->getMessage());
		}
	}
}
