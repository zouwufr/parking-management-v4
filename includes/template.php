<?php

namespace ParkingManagement;

class Template
{
	public static function get_default($prop = 'info')
	{
		match ($prop) {
			'info' => $template = self::info(),
			'database' => $template = self::database(),
			'sites' => $template = self::sites(),
			'supabase' => $template = self::supabase(),
			default => $template = null
		};
		return apply_filters('pkmgmt_default_template', $template, $prop);
	}

	private static function info(): array
	{
		return array(
			'logs' => [
				'database' => '0',
				'file' => '1',
				'retention' => '30',
			]
		);
	}

	private static function database(): array
	{
		return array(
			'name' => "",
			'host' => "",
			'port' => "",
			'user' => "",
			'password' => ""
		);
	}

	/**
	 * Global Supabase project config - reservations are mirrored here (best-effort,
	 * never blocking) alongside the legacy MySQL write. See Booking\SupabaseSync.
	 */
	private static function supabase(): array
	{
		return array(
			'url' => "",
			'key' => "",
		);
	}

	public static function payment_properties(): array
	{
		return [
			'payplug' => [
				'secret_key' => [
					'title' => 'Secret Key',
					'type' => 'password',
					'value' => ''
				],
				'public_key' => [
					'title' => 'Public Key',
					'type' => 'text',
					'value' => ''
				],
				'secret_key_test' => [
					'title' => 'Secret Test Key',
					'type' => 'password',
					'value' => ''
				],
				'public_key_test' => [
					'title' => 'Public Test Key',
					'type' => 'text',
					'value' => ''
				],
				'success_page' => [
					'title' => 'Success Page',
					'type' => 'page',
					'value' => ''
				],
				'cancel_page' => [
					'title' => 'Cancel Page',
					'type' => 'page',
					'value' => ''
				],
				'notification_url' => [
					'title' => 'Notification URL',
					'type' => 'url',
					'value' => ''
				],
			],
			'mypos' => [
				'configuration_package' => [
					'title' => 'Configuration Package',
					'type' => 'password',
					'value' => ''
				],
				'success_page' => [
					'title' => 'Success Page',
					'type' => 'page',
					'value' => ''
				],
				'cancel_page' => [
					'title' => 'Cancel Page',
					'type' => 'page',
					'value' => ''
				],
				'notification_url' => [
					'title' => 'Notification URL',
					'type' => 'url',
					'value' => ''
				],
			],
			'stripe' => [
				'secret_key' => [
					'title' => 'Secret Key',
					'type' => 'password',
					'value' => ''
				],
				'webhook_secret_key' => [
					'title' => 'Webhook Secret Key',
					'type' => 'password',
					'value' => ''
				],
				'secret_key_test' => [
					'title' => 'Secret Test Key',
					'type' => 'password',
					'value' => ''
				],
				'public_key_test' => [
					'title' => 'Public Test Key',
					'type' => 'text',
					'value' => ''
				],
				'success_page' => [
					'title' => 'Success Page',
					'type' => 'page',
					'value' => ''
				],
				'cancel_page' => [
					'title' => 'Cancel Page',
					'type' => 'page',
					'value' => ''
				],
			],
		];
	}

	public static function form_options(): array
	{
		return array(
			'night_extra_charge' => array(
				'enabled' => "0",
				'checked' => "0",
				'title' => 'Night extra charge',
				'price' => 0,
				'start' => '21:00',
				'end' => '07:00',
			),
			'shuttle' => array(
				'enabled' => "0",
				'checked' => "0",
				'title' => 'Shuttle',
				'price' => 0
			),
			'keep_keys' => array(
				'enabled' => "0",
				'checked' => "0",
				'title' => 'Keep keys',
				'price' => 0,
				'label' => esc_html__('I will keep my keys for %s €', 'parking-management')
			),
			'ev_charging' => array(
				'enabled' => "0",
				'checked' => "0",
				'title' => 'EV Charging',
				'price' => 0,
				'label' => esc_html__('Electric Vehicle charging for %s €', 'parking-management')
			),
			'late' => array(
				'enabled' => "0",
				'checked' => "0",
				'title' => 'Late',
				'price' => 0
			),
			'holiday' => array(
				'enabled' => "0",
				'checked' => "0",
				'title' => 'Holiday',
				'price' => 0
			),
			'forgetting' => array(
				'enabled' => "0",
				'checked' => "0",
				'title' => 'Forgetting',
				'price' => 0
			),
			'extra_baggage' => [
				'enabled' => "0",
				'checked' => "0",
				'title' => 'Extra baggage',
				'price' => 0
			],
			'oversize_baggage' => [
				'enabled' => "0",
				'checked' => "0",
				'title' => 'Oversize baggage',
				'price' => 0
			],
			'cancellation_insurance' => [
				'enabled' => "0",
				'checked' => "0",
				'title' => 'Cancellation insurance',
				'price' => 0,
				'label' => esc_html__('I hereby subscribe to the cancellation insurance for %s €', 'parking-management')
			],
		);
	}

	public static function mail_properties(): array
	{
		return [
			'host' => [
				'title' => 'Host',
				'type' => 'text',
				'value' => ''
			],
			'login' => [
				'title' => 'Login',
				'type' => 'text',
				'value' => ''
			],
			'password' => [
				'title' => 'Password',
				'type' => 'password',
				'value' => ''
			],
			'sender' => [
				'title' => 'Sender',
				'type' => 'email',
				'value' => ''
			]
		];
	}

	private static function tariff_table(): array
	{
		return array(
			'ext' => array('jour_supplementaire' => 0),
			'int' => array('jour_supplementaire' => 0),
			'valet' => array('jour_supplementaire' => 0),
		);
	}

	private static function sites(): array
	{
		return array();
	}

	/**
	 * Returns a single blank site, in the exact shape stored under the `sites`
	 * property. Everything a site needs lives here - it used to be split across
	 * the global info/form/booked_dates/high_season/notification/booking/valet
	 * properties, which no longer exist.
	 */
	public static function site_item(): array
	{
		return array(
			'name' => '',
			'active' => '1',

			// Legal identity: sites can be different legal companies (confirmed against
			// the legacy parkineo app's per-site config), so this is per-site, not global.
			'info' => array(
				'address' => '',
				'mobile' => '',
				'RCS' => '',
				'email' => '',
			),

			// VAT rates, also confirmed per-site in the legacy app (parkineo's $aParking
			// config has tva/tvaTransport per site) rather than a single fixed rate.
			'tva' => 20,
			'tva_transport' => 10,

			// A site can enable ext/int/valet in any combination (same shape the old
			// global info.type used), which is how "valet" becomes a per-site option
			// instead of a separate booking kind.
			'type' => array(
				'ext' => '0',
				'int' => '0',
				'valet' => '0',
			),

			// A site is bound to one airport and one terminal of that airport.
			'airport' => 'orly',
			'terminal' => '1',

			'vehicle_types' => array(
				'car' => '0',
				'motorcycle' => '0',
				'truck' => '0',
			),

			// tariffs[vehicle][parking_type][day] = price, plus 'jour_supplementaire'.
			'tariffs' => array(
				'car' => self::tariff_table(),
				'motorcycle' => self::tariff_table(),
				'truck' => self::tariff_table(),
			),

			'form' => array(
				'indicative' => '',
				'booking_page' => [
					'title' => 'Booking Page',
					'value' => ''
				],
				'validation_page' => [
					'title' => 'Validation Page',
					'value' => ''
				],
				'terms_and_conditions' => '0',
				'dialog_confirmation' => '0',
				'options' => self::form_options(),
			),

			'booked_dates' => array(),

			'high_season' => array(
				'price' => 0,
				'dates' => array(),
			),

			'notification' => array(
				'mail' => self::mail_properties(),
				'sms' => [
					'type' => 'AWS',
					'user' => '',
					'password' => '',
					'sender' => '',
				],
			),

			'payment' => array(
				'enabled' => "0",
				'valid-on-payment' => '0',
				'redirect-to-provider' => '0',
				'name' => 'payplug',
				'active-test' => '0',
				'properties' => self::payment_properties(),
			),

			'mail_templates' => array(
				'confirmation' => array(
					'title' => 'Confirmation',
					'type' => 'textarea',
					'value' => ''
				),
				'cancellation' => array(
					'title' => 'Cancellation',
					'type' => 'textarea',
					'value' => ''
				),
			),
			'sms_template' => '',
		);
	}
}
