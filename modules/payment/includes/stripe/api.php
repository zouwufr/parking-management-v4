<?php

namespace ParkingManagement\API;

use Booking\Order;
use Booking\Site;
use Exception;
use ParkingManagement\Logger;
use ParkingManagement\PaymentID;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;
use UnexpectedValueException;
use WP_Error;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class StripeAPI extends API
{
	private const rest_base = '/stripe/webhook';

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
			Logger::error("stripe.api.create_item", ["message" => "missing or invalid site_id"]);
			return new WP_Error(
				'stripe-notification-error',
				'Invalid request',
				array('status' => WP_Http::BAD_REQUEST)
			);
		}
		$payment = $site['payment'];
		$provider = $payment['properties']['stripe'];
		$test_enabled = ($payment['active-test'] == '1');
		$secretKey = $test_enabled ? $provider['secret_key_test']['value'] : $provider['secret_key']['value'];
		$webhook_secret = $provider['webhook_secret_key']['value'];
		if (empty($secretKey)) {
			Logger::warning("stripe.api.create_item", ["message" => "missing secret key"]);
			return new WP_REST_Response(null, WP_Http::NO_CONTENT);
		}
		if (empty($webhook_secret)) {
			Logger::warning("stripe.api.create_item", ["message" => "missing webhook secret key"]);
			return new WP_REST_Response(null, WP_Http::NO_CONTENT);
		}
		try {
			Stripe::setApiKey($secretKey);
			$stripe_signature = $request->get_header('stripe_signature');
			$event = Webhook::constructEvent(
				$request->get_body(),
				$stripe_signature,
				$webhook_secret
			);
			Logger::info('stripe.create_item', [
				'event_type' => $event->type,
				'event_id' => $event->id,
				'site_id' => $site_id,
			]);
			if ( $event->type === 'checkout.session.completed')
			{
				$session = $event->data->object;

				$session = Session::constructFrom($session->toArray());
				if (isset($session->metadata['id_commande'])) {
					$order_id = (int)$session->metadata['id_commande'];
					$order = new Order();
					if ($session->payment_status == 'paid') {
						$payment_date = $date = date('Y-m-d H:i:s');
						$amount = $session->amount_total / 100;
						$payment_id = PaymentID::STRIPE;
						$order->update_payment((int)$order_id, $payment_date, $amount, $payment_id);
						Logger::info("stripe.api.create_item", "record payment status recorded");
					}
				}
			}
			return new WP_REST_Response(null, WP_Http::NO_CONTENT);
		} catch (UnexpectedValueException|SignatureVerificationException|Exception $e) {
			Logger::warning("stripe.api.create_item", $e->getMessage());
			if ($e->getMessage() == "The resource you requested could not be found.")
				return new WP_REST_Response(null, WP_Http::NO_CONTENT);
			return new WP_Error(
				'stripe-notification-error',
				'Webhook processing failed',
				array('status' => WP_Http::BAD_REQUEST)
			);
		}
	}
}
