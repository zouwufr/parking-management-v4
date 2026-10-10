<?php

use ParkingManagement\Booking;
use ParkingManagement\Booked;
use ParkingManagement\HighSeason;
use ParkingManagement\Notification;
use ParkingManagement\Payment;
use ParkingManagement\Price;
use ParkingManagement\interfaces\IShortcode;

$current_shortcode_page = "";
function pkmgmt_parking_management_shortcode_func(array $atts, $content = null, $code = ''): string
{
	global $wp, $current_shortcode_page;
	if (is_feed()) {
		return '[parking-management]';
	}
	if ('parking-management' !== $code) {
		return esc_html__('Error: not a Parking Management shortcode', 'parking-management');
	}

	$atts = shortcode_atts(
		array(
			'type' => 'form', // type supported form, home-form, payment, price, booked, high-season, notification
			'payment_provider' => '', // payment supported payplug, mypos, stripe
			'action' => '', // action supported confirmation, cancellation
		), $atts, 'parking-management'
	);
	$current_shortcode_page = home_url(add_query_arg(array(), $wp->request));
	$type = trim(array_key_exists('type', $atts) ? $atts['type'] : '');
	$payment_provider = trim(array_key_exists('payment_provider', $atts) ? $atts['payment_provider'] : '');
	$action = trim(array_key_exists('action', $atts) ? $atts['action'] : '');

	return match ($type) {
		'form',
		'home-form', 'price',
		'booked', 'high-season', 'payment',
		'notification' => pkmgmt_parking_management_shortcode_router($type, ['payment_provider' => $payment_provider, 'action' => $action]),
		default => '[parking-management "not found"]',
	};
}

function pkmgmt_parking_management_shortcode_router(string $type, $atts): string
{
	$pm = getParkingManagementInstance();
	if (!$pm)
		return '[parking-management "not found"]';

	/** @var IShortcode $instance */
	$instance = match ($type) {
		'form', 'home-form' => new Booking($pm),
		'price' => new Price($pm),
		'booked' => new Booked($pm),
		'high-season' => new HighSeason($pm),
		'payment' => new Payment($pm),
		'notification' => new Notification($pm),
	};

	if ($type === 'payment') {
		$type = $atts['payment_provider'];
	}

	if ($type === 'notification') {
		$type = $atts['action'];
	}

	return $instance->shortcode($type);
}
