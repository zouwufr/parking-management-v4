<?php

namespace ParkingManagement\API;

use Booking\Order;
use Booking\Site;
use Exception;
use ParkingManagement\Logger;
use ParkingManagement\PaymentID;
use Payplug\Exception\PayplugException;
use Payplug\Notification;
use Payplug\Payplug;
use Payplug\Resource\APIResource;
use WP_Error;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class PayplugAPI extends API
{
	private const rest_base = '/payplug/ipn';

	public function __construct()
	{
		add_action('rest_api_init', [$this, 'register_routes']);
	}

	public function register_routes(): void
	{
		register_rest_route($this->namespace, self::rest_base, [
			[
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => [$this, 'create_item'],
				'permission_callback' => '__return_true',
				'args' => [
					'site_id' => ['required' => false],
					'any_param' => ['required' => false]
				]
			]
		]);
	}

	/**
	 * Creates a single user.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item($request): WP_Error|WP_REST_Response
	{
		$site_id = $request->get_param('site_id');
		$site = $site_id !== null ? Site::find($site_id) : null;
		if ($site === null) {
			Logger::error("payplug.api.create_item", ["message" => "missing or invalid site_id"]);
			return new WP_Error(
				'payplug-notification-error',
				'Missing parameter',
				array('status' => WP_Http::BAD_REQUEST)
			);
		}
		$payment = $site['payment'];
		$provider = $payment['properties']['payplug'];
		$test_enabled = ($payment['active-test'] == '1');
		$secretKey = $test_enabled ? $provider['secret_key_test']['value'] : $provider['secret_key']['value'];
		if (empty($secretKey)) {
			Logger::warning("payplug.api.create_item", ["message" => "missing secret key"]);
			return new WP_REST_Response(null, WP_Http::NO_CONTENT);
		}
		try {
			Payplug::init(array(
				'secretKey' => $secretKey
			));
			Logger::debug("payplug.api.create_item", $request->get_body());

			$resource = Notification::treat($request->get_body());
			Logger::info("payplug.api.create_item", ['resource' => $resource]);
			if ($resource instanceof APIResource && $resource->is_paid) {
				$order_id = $resource->metadata['id_commande'];
				$payment_date = date('Y-m-d H:i:s', $resource->hosted_payment->paid_at);

				$amount = ($resource->amount / 100);
				$payment_id = PaymentID::PAYPLUG;
				$order = new Order();
				$order->update_payment((int)$order_id, $payment_date, $amount, $payment_id);
				Logger::info("payplug.api.create_item", "record payment status recorded");
				return new WP_REST_Response(null, WP_Http::NO_CONTENT);
			}
			if ($resource instanceof APIResource && $resource->failure) {
				Logger::error("payplug.api.create_item", ["failure" => ["code" => $resource->failure->code, "message" => $resource->failure->message]]);
				return new WP_REST_Response(null, WP_Http::NO_CONTENT);
			}
		} catch (PayplugException|Exception $e) {
			Logger::warning("payplug.api.create_item", $e->getMessage());
			if ( $e->getMessage() == "The resource you requested could not be found.")
				new WP_REST_Response(null, WP_Http::NO_CONTENT);
			return new WP_Error(
				'payplug-notification-error',
				$e->getMessage(),
				array('status' => WP_Http::BAD_REQUEST)
			);
		}
		Logger::error("payplug.api.create_item", "record payment status not recorded");

		return new WP_Error(
			"record-payment-status",
			__('payment status not recorded', 'parking-management'),
			array('status' => WP_Http::BAD_REQUEST)
		);
	}
}
