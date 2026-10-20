<?php
//defined('_PKMGMT') or die('Restricted access');

require_once PKMGMT_PLUGIN_DIR . DS . "includes" . DS . "logs.php";
require_once PKMGMT_PLUGIN_DIR . DS . "includes" . DS . "interfaces.php";
require_once PKMGMT_PLUGIN_DIR . DS . "includes" . DS . "capabilities.php";
require_once PKMGMT_PLUGIN_DIR . DS . "includes" . DS . "dates_range.php";
require_once PKMGMT_PLUGIN_DIR . DS . "includes" . DS . "functions.php";
require_once PKMGMT_PLUGIN_DIR . DS . "includes" . DS . "html.php";
require_once PKMGMT_PLUGIN_DIR . DS . "includes" . DS . "l10n.php";
require_once PKMGMT_PLUGIN_DIR . DS . "includes" . DS . "parking-management.php";
require_once PKMGMT_PLUGIN_DIR . DS . "includes" . DS . "shortcode.php";
require_once PKMGMT_PLUGIN_DIR . DS . "includes" . DS . "template.php";
require_once PKMGMT_PLUGIN_DIR . DS . "vendor" . DS . "autoload.php";


if (is_admin()) {
	require_once PKMGMT_PLUGIN_DIR . DS . "admin" . DS . "admin.php";
} else {
	require_once PKMGMT_PLUGIN_DIR . DS . "includes" . DS . "controller.php";
}

use ParkingManagement\Logger;
use ParkingManagement\ParkingManagement;
use ParkingManagement\Template;

class PKMGMT
{

	/**
	 * Loads modules from the modules directory.
	 */
	public static function load_modules(): void
	{
		self::load_module('api');
		self::load_module('payment');
		self::load_module('booked');
		self::load_module('booking');
		self::load_module('database');
		self::load_module('price');
		self::load_module('high_season');
		self::load_module('notification');
	}

	/**
	 * Loads the specified module.
	 *
	 * @param string $mod Name of module.
	 * @return bool True on success, false on failure.
	 */
	protected static function load_module(string $mod): bool
	{
		return pkmgmt_include_module_file($mod . '/' . $mod . '.php')
			|| pkmgmt_include_module_file($mod . '.php');
	}

	/**
	 * Retrieves a named entry from the option array of Parking Management.
	 *
	 * @param string $name Array item key.
	 * @param bool $default_value Optional. Default value to return if the entry
	 *                             does not exist. Default false.
	 * @return mixed Array value tied to the $name key. If nothing found,
	 *               the $default_value value will be returned.
	 */
	public static function get_option(string $name, bool $default_value = false): mixed
	{
		$option = get_option('pkmgmt');

		if (false === $option) {
			return $default_value;
		}

		return $option[$name] ?? $default_value;
	}


	/**
	 * Update an entry value on the option array of Parking Management.
	 *
	 * @param string $name Array item key.
	 * @param mixed $value Option value.
	 */
	public static function update_option(string $name, mixed $value): void
	{
		$old_option = get_option('pkmgmt');
		$old_option = (false === $old_option) ? array() : (array)$old_option;

		update_option('pkmgmt',
			array_merge($old_option, array($name => $value))
		);

		do_action('pkmgmt_update_option', $name, $value, $old_option);
	}
}

add_action('plugins_loaded', 'pkmgmt', 10, 0);

/**
 * Loads modules and registers WordPress shortcodes.
 */
function pkmgmt(): void
{
	PKMGMT::load_modules();

	add_shortcode('parking-management', 'pkmgmt_parking_management_shortcode_func');
}


add_action('init', 'pkmgmt_init', 2);
/**
 * Registers post types for parking management.
 */
function pkmgmt_init(): void
{
	pkmgmt_get_request_uri();
	pkmgmt_register_post_type();

	do_action('pkmgmt_init');
}

/**
 * The migrate_2_to_3 .. migrate_to_4_11_0 chain below was written for, and
 * relies on being run in lockstep with, a true historical upgrade: each step
 * unconditionally overwrites properties assuming the exact old shape it
 * expects is still there. On a normal release-to-release update that holds
 * (the build workflow correctly bumps PKMGMT_VERSION to match each release
 * tag - see .github/workflows/push-tag.yml + build.yml - it's only the
 * git-committed source on `main` that keeps showing an old constant, since
 * that bump is applied to the build artifact and never committed back).
 * But anything that runs this plugin from a raw checkout instead of a
 * packaged release (or any other scenario where the stored `version` option
 * ends up behind the data's actual shape) would hit every one of these old
 * migrations unconditionally, destructively nulling out real config (payment
 * keys, mail/SMS templates, database credentials, ...) since they assume
 * shapes that no longer exist. This checks for the one shape marker
 * (`booking.mail_templates`, the target of migrate_3_7_to_4_0) that's present
 * on any already-modern install, and skips the legacy chain entirely when
 * it's there - a no-op when version tracking is already correct, a guard
 * when it isn't.
 */
function pkmgmt_is_modern_schema(): bool
{
	$pm = getParkingManagementInstance();
	if (!$pm || $pm->initial()) {
		return true; // nothing to migrate from
	}
	$post_id = $pm->id();
	if (!empty(get_post_meta($post_id, 'pkmgmt_sites', true))) {
		return true; // already on 5.0+
	}
	$booking = get_post_meta($post_id, 'pkmgmt_booking', true);
	return is_array($booking) && !empty($booking['mail_templates']);
}

add_action('admin_init', 'pkmgmt_upgrade', 10, 0);
function pkmgmt_upgrade(): void
{
	$old_version = PKMGMT::get_option('version', '0');
	$new_version = PKMGMT_VERSION;

	if (version_compare($old_version, $new_version, '==')) {
		return;
	}

	if (!pkmgmt_is_modern_schema()) {
		if (version_compare($old_version, '3.0.0', '<')) {
			pkmgmt_migrate_2_to_3();
		}
		if (version_compare($old_version, '3.1.0', '<')) {
			pkmgmt_migrate_3_0_to_3_1();
		}
		if (version_compare($old_version, '3.6.0', '<')) {
			pkmgmt_migrate_3_1_to_3_6();
		}
		if (version_compare($old_version, '3.7.0', '<')) {
			pkmgmt_migrate_3_6_to_3_7();
		}
		if (version_compare($old_version, '4.0.0', '<')) {
			pkmgmt_migrate_3_7_to_4_0();
		}
		if (version_compare($old_version, '4.2.1', '<')) {
			pkmgmt_migrate_to_4_2_1();
		}
		if (version_compare($old_version, '4.3.0', '<')) {
			pkmgmt_migrate_to_4_3_0();
		}
		if (version_compare($old_version, '4.3.5', '<')) {
			pkmgmt_migrate_to_4_3_5();
		}
		if (version_compare($old_version, '4.5.0', '<')) {
			pkmgmt_migrate_to_4_5_0();
		}
		if (version_compare($old_version, '4.9.0', '<')) {
			pkmgmt_migrate_to_4_9_0();
		}
		if (version_compare($old_version, '4.11.0', '<')) {
			pkmgmt_migrate_to_4_11_0();
		}
	}
	if (version_compare($old_version, '5.0.0', '<')) {
		pkmgmt_migrate_to_5_0_0();
	}

	do_action('pkmgmt_upgrade', $old_version, $new_version);
	PKMGMT::update_option('version', $new_version);
}

function pkmgmt_migrate_2_to_3(): void
{
	global $wpdb;
	$post_id = get_post_id_by_post_type('pkmgmt');
	if (!$post_id)
		return;
	$post = get_post($post_id);
	wp_update_post(array(
		'ID' => $post_id,
		'post_type' => ParkingManagement::post_type,
		'post_status' => 'publish',
		'post_title' => $post->post_title,
		'post_name' => $post->post_name,
	));
	$pm = ParkingManagement::get_template([
		'title' => $post->post_title,
		'name' => $post->post_name,
	]);
	$pm->id = $post_id;
	$properties = $pm->get_properties();
	if (metadata_exists('post', $post_id, '_info')) {
		$prop = get_post_meta($post_id, '_info', true);
		$properties['info']['address'] = $prop['adresse'];
		$properties['info']['mobile'] = $prop['telephone'];
		$properties['info']['RCS'] = $prop['RCS'];
		$properties['info']['email'] = $prop['email'];
		$properties['info']['terminal'] = $prop['terminal'];
		$properties['info']['vehicle_type']['car'] = '1';
		$properties['info']['vehicle_type']['truck'] = '1';
		$properties['info']['type']['ext'] = $prop['type']['ext'];
		$properties['info']['type']['int'] = $prop['type']['int'];
		$properties['form']['booking']['dialog_confirmation'] = $prop['gestion']['autovalid'] === '1' ? '0' : '1';
		$properties['form']['booking']['terms_and_conditions'] = $prop['cg'];
		$properties['form']['booking']['options']['holiday']['enabled'] = $prop['frais']['ferie'];
		$properties['form']['booking']['options']['night_extra_charge']['enabled'] = $prop['frais']['nuit'];
		$properties['form']['indicative'] = $prop['indicatif'];
	}
	if (metadata_exists('post', $post_id, '_database')) {
		$prop = get_post_meta($post_id, '_database', true);
		$properties['database']['name'] = $prop['dbname'];
		$properties['database']['host'] = $prop['dbhost'];
		$properties['database']['port'] = $prop['dbport'];
		$properties['database']['user'] = $prop['dbuser'];
		$properties['database']['password'] = $prop['dbpassword'];

	}
	if (metadata_exists('post', $post_id, '_divers')) {
		$prop = get_post_meta($post_id, '_divers', true);
		if (strtoupper($prop['smstype']) === 'OVH') {
			$properties['notification']['sms']['type'] = 'OVH';
			$properties['notification']['sms']['user'] = $prop['smsuser'];
			$properties['notification']['sms']['password'] = $prop['smspasswd'];
			$properties['notification']['sms']['sender'] = $prop['smssender'];
			$properties['notification']['sms']['template'] = $prop['smsmessage'];
		}
	}
	if (metadata_exists('post', $post_id, '_response')) {
		$prop = get_post_meta($post_id, '_response', true);
		$properties['notification']['mail']['templates']['confirmation']['value'] = $prop;
	}

	$pm->set_properties($properties);
	try {
		$pm->save();
		foreach (['_info', '_database', '_divers', '_response', '_paypal', '_template', '_name', '_locale'] as $prop) {
			delete_post_meta($pm->id(), $prop);
		};
	} catch (Throwable $e) {
		error_log("migration.save: " . $e->getMessage());
	}
}

function pkmgmt_migrate_3_0_to_3_1(): void
{
	$pm = getParkingManagementInstance();
	if (!$pm)
		return;
	$props = $pm->get_properties();
	$props['notification'] = Template::get_default('notification');
	$sms = $pm->retrieve_property('sms');
	if (!empty($sms)) {
		$props['notification']['sms'] = $sms;
		delete_post_meta($pm->id, 'pkmgmt_sms');
	}
	$mail = $pm->retrieve_property('mail');
	if (!empty($mail)) {
		$props['notification']['mail']['host'] = $mail['host'];
		$props['notification']['mail']['login'] = $mail['login'];
		$props['notification']['mail']['password'] = $mail['password'];
		$props['notification']['mail']['sender'] = $mail['sender'];
		delete_post_meta($pm->id, 'pkmgmt_mail');
	}

	$pm->set_properties($props);
	try {
		$pm->save();
	} catch (Throwable $e) {
		Logger::error("migrate.3.0.to.3.1", $e->getMessage());
	}
}

function pkmgmt_migrate_3_1_to_3_6(): void
{
	$pm = getParkingManagementInstance();
	if (!$pm)
		return;
	$props = $pm->get_properties();
	$props['payment']['providers']['mypos']['redirect-to-provider'] = '0';
	$props['form']['payment'] = '';
	$props['form']['valet'] = [
		'validation_page' => [
			'title' => 'Validation Page',
			'value' => ''
		],
		'payment' => ''
	];
	$props['form']['booking_page'] = [
		'title' => 'Booking Page',
		'value' => ''
	];
	$pm->set_properties($props);
	try {
		$pm->save();
	} catch (Throwable $e) {
		Logger::error("migrate.3.1.to.3.6", $e->getMessage());
	}
}

function pkmgmt_migrate_3_6_to_3_7(): void
{
	$pm = getParkingManagementInstance();
	if (!$pm)
		return;
	$props = $pm->get_properties();
	unset($props['payment']['providers']['mypos']['redirect-to-provider']);
	unset($props['payment']['providers']['payplug']['redirect-to-provider']);
	unset($props['payment']['providers']['paypal']['redirect-to-provider']);
	$props['form']['redirect-to-provider'] = '0';
	$props['form']['valet']['database'] = [
		'name' => "",
		'host' => "",
		'port' => "",
		'user' => "",
		'password' => ""
	];
	$props['form']['valet']['redirect-to-provider'] = '0';
	$props['notification']['valet'] = [
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
		],
		'templates' => [
			'confirmation' => [
				'title' => 'Confirmation',
				'type' => 'textarea',
				'value' => ''
			],
			'cancellation' => [
				'title' => 'Cancellation',
				'type' => 'textarea',
				'value' => ''
			],
		]
	];
	$pm->set_properties($props);
	try {
		$pm->save();
	} catch (Throwable $e) {
		Logger::error("migrate.3.1.to.3.6", $e->getMessage());
	}
}

function pkmgmt_migrate_3_7_to_4_0(): void
{
	$pm = getParkingManagementInstance();
	if (!$pm)
		return;
	$props = $pm->get_properties();
	$props['booking'] = Template::get_default('booking');
	$props['booking']['validation_page'] = $props['form']['validation_page'];
	$props['booking']['options'] = $props['form']['booking'];
	$props['booking']['database'] = $props['database'];
	$props['booking']['payment'] = [
		'valid-on-payment' => $props['payment']['valid-booking-on-payment'],
		'redirect-to-provider' => $props['form']['redirect-to-provider'],
		'name' => 'payplug',
		'enabled' => $props['payment']['providers']['payplug']['enabled'],
		'active-test' => $props['payment']['providers']['payplug']['active-test'],
		'properties' => [
			'payplug' => $props['payment']['providers']['payplug']['properties'],
			'mypos' => $props['payment']['providers']['mypos']['properties'],
			'paypal' => $props['payment']['providers']['paypal']['properties'],
		]
	];
	$props['booking']['mail_templates'] = [
		'confirmation' => [
			'title' => 'Confirmation',
			'type' => 'textarea',
			'value' => $props['notification']['mail']['templates']['confirmation'],
		],
		'cancellation' => [
			'title' => 'Cancellation',
			'type' => 'textarea',
			'value' => $props['notification']['mail']['templates']['cancellation'],
		]
	];
	$props['booking']['sms_template'] = $props['notification']['sms']['template'];

	$props['valet'] = Template::get_default('valet');
	$props['valet']['validation_page'] = $props['form']['valet']['validation_page'];
	$props['valet']['options'] = $props['form']['booking'];
	$props['valet']['database'] = $props['database'];
	$props['valet']['payment'] = [
		'valid-on-payment' => $props['payment']['valid-valet-on-payment'],
		'redirect-to-provider' => $props['form']['valet']['redirect-to-provider'],
		'name' => 'payplug',
		'enabled' => $props['payment']['providers']['payplug']['enabled'],
		'active-test' => $props['payment']['providers']['payplug']['active-test'],
		'properties' => [
			'payplug' => $props['payment']['providers']['payplug']['properties'],
			'mypos' => $props['payment']['providers']['mypos']['properties'],
			'paypal' => $props['payment']['providers']['paypal']['properties'],
		]
	];
	$props['valet']['mail_templates'] = [
		'confirmation' => [
			'title' => 'Confirmation',
			'type' => 'textarea',
			'value' => $props['notification']['valet']['templates']['confirmation'],
		],
		'cancellation' => [
			'title' => 'Cancellation',
			'type' => 'textarea',
			'value' => $props['notification']['valet']['templates']['cancellation'],
		]
	];
	$props['valet']['sms_template'] = $props['notification']['sms']['template'];
	$props['form']['valet_page'] = [
		'title' => 'Valet Page',
		'value' => ''
	];
	unset($props['payment']);
	unset($props['database']);
	unset($props['form']['booking']);
	unset($props['form']['payment']);
	unset($props['form']['redirect-to-provider']);
	unset($props['form']['valet']);
	unset($props['form']['validation_page']);
	unset($props['notification']['mail']['templates']);
	unset($props['notification']['valet']['templates']);
	unset($props['notification']['sms']['template']);
	$pm->set_properties($props);
	try {
		$pm->save();
	} catch (Throwable $e) {
		Logger::error("migrate.3.7.to.4.0", $e->getMessage());
	}

}

function pkmgmt_migrate_to_4_2_1(): void
{
	$pm = getParkingManagementInstance();
	if (!$pm)
		return;
	$props = $pm->get_properties();
	if (!array_key_exists('valet', $props['form']['type']))
		$props['form']['type']['valet'] = 0;
	$pm->set_properties($props);
	try {
		$pm->save();
	} catch (Throwable $e) {
		Logger::error("migrate.to.4.2.1", $e->getMessage());
	}
}

function pkmgmt_migrate_to_4_3_0(): void
{
	try {
		$pm = getParkingManagementInstance();
		if (!$pm)
			return;
		$payment = Template::payment_properties();
		$props = $pm->get_properties();
		$props['booking']['payment']['properties']['stripe'] = $payment['stripe'];
		$props['valet']['payment']['properties']['stripe'] = $payment['stripe'];
		unset($props['booking']['payment']['properties']['paypal']);
		unset($props['valet']['payment']['properties']['paypal']);

		$pm->set_properties($props);
		$pm->save();
	} catch (Throwable $e) {
		Logger::error("migrate.to.4.3.0", $e->getMessage());
	}
}

function pkmgmt_migrate_to_4_3_5(): void
{
	try {
		$pm = getParkingManagementInstance();
		if (!$pm)
			return;
		$payment = Template::payment_properties();
		$props = $pm->get_properties();
		$props['booking']['payment']['properties']['stripe']['webhook_secret_key'] = $payment['stripe']['webhook_secret_key'];
		$props['valet']['payment']['properties']['stripe']['webhook_secret_key'] = $payment['stripe']['webhook_secret_key'];

		$pm->set_properties($props);
		$pm->save();
	} catch (Throwable $e) {
		Logger::error("migrate.to.4.3.5", $e->getMessage());
	}
}

function pkmgmt_migrate_to_4_5_0(): void
{
	try {
		$pm = getParkingManagementInstance();
		if (!$pm)
			return;
		$payment = Template::payment_properties();
		$props = $pm->get_properties();
		$props['form']['options']['keep_keys'] =  array(
			'enabled' => "0",
			'title' => 'Keep keys',
			'price' => 0,
			'label' => 'I will keep my keys for %s €'
		);
		$props['form']['options']['ev_charging'] = [
			'enabled' => "0",
			'title' => 'EV Charging',
			'price' => 0,
			'label' => 'Electric Vehicle charging for %s €'
		];
		$props['form']['options']['cancellation_insurance']['label'] = esc_html__('I hereby subscribe to the cancellation insurance for %s €', 'parking-management');

		$pm->set_properties($props);
		$pm->save();
	} catch (Throwable $e) {
		Logger::error("migrate.to.4.5.0", $e->getMessage());
	}
}

function pkmgmt_migrate_to_4_9_0(): void
{
	try {
		$pm = getParkingManagementInstance();
		if (!$pm)
			return;
		$props = $pm->get_properties();
		$props['form']['options']['extra_baggage'] =  [
			'enabled' => "0",
			'title' => 'Extra baggage',
			'price' => 0
		];
		$props['form']['options']['oversize_baggage'] = [
			'enabled' => "0",
			'title' => 'Oversize baggage',
			'price' => 0
		];

		$pm->set_properties($props);
		$pm->save();
	} catch (Throwable $e) {
		Logger::error("migrate.to.4.9.0", $e->getMessage());
	}
}

function pkmgmt_migrate_to_4_11_0(): void
{
	try {
		$pm = getParkingManagementInstance();
		if (!$pm)
			return;
		$props = $pm->get_properties();
		$props['form']['options']['night_extra_charge']['start'] = $props['form']['options']['night_extra_charge']['start'] ?? '21:00';
		$props['form']['options']['night_extra_charge']['end'] = $props['form']['options']['night_extra_charge']['end'] ?? '07:00';

		$pm->set_properties($props);
		$pm->save();
	} catch (Throwable $e) {
		Logger::error("migrate.to.4.11.0", $e->getMessage());
	}
}

/**
 * Pre-5.0 installs kept one global config split across the `info` (terminal/
 * vehicle_type/type), `form`, `booked_dates`, `high_season`, `notification` and
 * `booking`/`valet` properties - `booking` and `valet` were two independent
 * "kinds" (own validation page, payment config incl. provider success/cancel
 * pages, mail/SMS templates - see pkmgmt_migrate_3_7_to_4_0()), not a single
 * combined config. 5.0 replaces both kinds with one or more independent
 * "sites" (see Template::site_item()). This folds each kind that had real
 * config into its own site (so an install using only `booking`, only `valet`,
 * or both, keeps working immediately after the upgrade with zero manual
 * reconfiguration) rather than collapsing them into one, which would silently
 * discard whichever kind lost that merge - `pkmgmt_valet` is unconditionally
 * deleted at the end either way, so any config left out here is unrecoverable.
 *
 * The `properties_available` list no longer includes the legacy properties, so
 * $pm->prop(...) cannot see them any more - this reads the raw post meta directly.
 */
function pkmgmt_migrate_to_5_0_0(): void
{
	try {
		$pm = getParkingManagementInstance();
		if (!$pm || $pm->initial())
			return;
		$post_id = $pm->id();

		if (!empty(get_post_meta($post_id, 'pkmgmt_sites', true))) {
			return; // already migrated
		}

		$legacy_info = get_post_meta($post_id, 'pkmgmt_info', true);
		$legacy_info = is_array($legacy_info) ? $legacy_info : [];
		$legacy_form = get_post_meta($post_id, 'pkmgmt_form', true);
		$legacy_form = is_array($legacy_form) ? $legacy_form : [];
		$legacy_booking = get_post_meta($post_id, 'pkmgmt_booking', true);
		$legacy_booking = is_array($legacy_booking) ? $legacy_booking : [];
		$legacy_valet = get_post_meta($post_id, 'pkmgmt_valet', true);
		$legacy_valet = is_array($legacy_valet) ? $legacy_valet : [];
		$legacy_notification = get_post_meta($post_id, 'pkmgmt_notification', true);
		$legacy_notification = is_array($legacy_notification) ? $legacy_notification : [];
		$legacy_booked_dates = get_post_meta($post_id, 'pkmgmt_booked_dates', true);
		$legacy_booked_dates = is_array($legacy_booked_dates) ? $legacy_booked_dates : [];
		$legacy_high_season = get_post_meta($post_id, 'pkmgmt_high_season', true);
		$legacy_high_season = is_array($legacy_high_season) ? $legacy_high_season : [];

		if (empty($legacy_info) && empty($legacy_form) && empty($legacy_booking) && empty($legacy_valet)) {
			return; // fresh install, nothing to carry over
		}

		// Builds one site from the shared location config (info/form/booked_dates/
		// high_season, common to both kinds) plus one kind's own config
		// (validation page, terms/dialog, payment, mail/SMS templates).
		// $notification_mail overrides the shared pkmgmt_notification.mail block -
		// needed for valet, which historically kept its own mail login/sender under
		// pkmgmt_notification.valet instead (see pkmgmt_migrate_3_6_to_3_7()).
		$build_site = function (string $name, array $legacy_kind, ?array $notification_mail = null) use ($legacy_info, $legacy_form, $legacy_notification, $legacy_booked_dates, $legacy_high_season): array {
			$site = Template::site_item();
			$site['name'] = $name;
			$site['active'] = '1';
			$site['info']['address'] = $legacy_info['address'] ?? '';
			$site['info']['mobile'] = $legacy_info['mobile'] ?? '';
			$site['info']['RCS'] = $legacy_info['RCS'] ?? '';
			$site['info']['email'] = $legacy_info['email'] ?? '';

			if (!empty($legacy_info['type']) && is_array($legacy_info['type'])) {
				$site['type'] = wp_parse_args($legacy_info['type'], $site['type']);
			}
			if (!empty($legacy_info['terminal'])) {
				$airport = strtolower($legacy_info['terminal']);
				$site['airport'] = in_array($airport, ['orly', 'roissy', 'orly2', 'orly3'], true) ? $airport : 'orly';
			}
			if (!empty($legacy_info['vehicle_type']) && is_array($legacy_info['vehicle_type'])) {
				$site['vehicle_types'] = wp_parse_args($legacy_info['vehicle_type'], $site['vehicle_types']);
			}

			if (!empty($legacy_form)) {
				$site['form']['indicative'] = $legacy_form['indicative'] ?? $site['form']['indicative'];
				$site['form']['booking_page'] = $legacy_form['booking_page'] ?? $site['form']['booking_page'];
				if (!empty($legacy_form['options']) && is_array($legacy_form['options'])) {
					$site['form']['options'] = wp_parse_args($legacy_form['options'], $site['form']['options']);
				}
			}

			if (!empty($legacy_kind)) {
				$site['form']['validation_page'] = $legacy_kind['validation_page'] ?? $site['form']['validation_page'];
				$site['form']['terms_and_conditions'] = $legacy_kind['options']['terms_and_conditions'] ?? $site['form']['terms_and_conditions'];
				$site['form']['dialog_confirmation'] = $legacy_kind['options']['dialog_confirmation'] ?? $site['form']['dialog_confirmation'];
				if (!empty($legacy_kind['payment']) && is_array($legacy_kind['payment'])) {
					$site['payment'] = wp_parse_args($legacy_kind['payment'], $site['payment']);
				}
				$site['mail_templates'] = $legacy_kind['mail_templates'] ?? $site['mail_templates'];
				$site['sms_template'] = $legacy_kind['sms_template'] ?? '';
			}

			$site['booked_dates'] = $legacy_booked_dates;
			if (!empty($legacy_high_season)) {
				$site['high_season'] = wp_parse_args($legacy_high_season, $site['high_season']);
			}
			if (!empty($legacy_notification)) {
				$site['notification']['mail'] = $notification_mail ?? $legacy_notification['mail'] ?? $site['notification']['mail'];
				$site['notification']['sms'] = $legacy_notification['sms'] ?? $site['notification']['sms'];
			}

			return $site;
		};

		$title = $pm->title ?: 'Site';
		$sites = [];
		if (!empty($legacy_booking)) {
			$sites[] = $build_site($title, $legacy_booking);
		}
		if (!empty($legacy_valet)) {
			$valet_mail = (!empty($legacy_notification['valet']) && is_array($legacy_notification['valet']))
				? $legacy_notification['valet']
				: null;
			$sites[] = $build_site(empty($sites) ? $title : $title . ' - Valet', $legacy_valet, $valet_mail);
		}
		if (empty($sites)) {
			return; // neither kind had real config - nothing to carry over
		}

		$database = Template::get_default('database');
		foreach ([$legacy_booking, $legacy_valet] as $legacy_kind) {
			if (!empty($legacy_kind['database']) && array_filter($legacy_kind['database'])) {
				$database = $legacy_kind['database'];
				break;
			}
		}

		$props = $pm->get_properties();
		$props['sites'] = array_combine(range(1, count($sites)), $sites);
		$props['database'] = $database;
		$props['info'] = [
			'logs' => $legacy_info['logs'] ?? ['database' => '0', 'file' => '1', 'retention' => '30'],
		];

		$pm->set_properties($props);
		$pm->save();

		foreach (['pkmgmt_form', 'pkmgmt_booked_dates', 'pkmgmt_high_season', 'pkmgmt_notification', 'pkmgmt_booking', 'pkmgmt_valet', 'pkmgmt_locations'] as $legacy_key) {
			delete_post_meta($post_id, $legacy_key);
		}
	} catch (Throwable $e) {
		Logger::error("migrate.to.5.0.0", $e->getMessage());
	}
}

add_action('activate_' . PKMGMT_PLUGIN_BASENAME, 'pkmgmt_install', 9, 0);


/**
 * Callback tied to a plugin activation action hook. Attempts to create
 *  an initial user dataset.
 * @throws Exception
 */
function pkmgmt_install(): void
{
	if (get_option('pkmgmt')) {
		return;
	}
	ParkingManagement::register_post_type();
	pkmgmt_upgrade();

	if (get_posts(array('post_type' => ParkingManagement::post_type))) {
		return;
	}

	$pm = ParkingManagement::get_template(
		array(
			'title' => sprintf('Parking %d', 1),
			'name' => sprintf('Parking %d', 1)
		)
	);
	$pm->save();

	PKMGMT::update_option('bulk_validate',
		array(
			'timestamp' => time(),
			'version' => PKMGMT_VERSION,
			'count_valid' => 1,
			'count_invalid' => 0,
		)
	);
}

function enable_svg_upload($mimes)
{
	$mimes['svg'] = 'image/svg+xml';
	return $mimes;
}

add_filter('upload_mimes', 'enable_svg_upload');
