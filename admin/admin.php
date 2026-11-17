<?php

namespace ParkingManagement\Admin;

use Booking\Airport;
use Booking\Terminal;
use Exception;
use ParkingManagement\ParkingManagement;
use ParkingManagement\Template;

require_once PKMGMT_PLUGIN_DIR . DS . "admin" . DS . "includes" . DS . "pages.php";


class Admin
{
	public function __construct()
	{
		add_action('admin_init',
			static function () {
				do_action('pkmgmt_admin_init');
			},
			10, 0
		);

		// CSS and Javascript
		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

		add_action('admin_menu', array($this, 'menu'), 9, 0);
		add_action('pkmgmt_admin_notices', array('ParkingManagement\Admin\Pages', 'notices_message'), 10, 3);
	}

	public static function enqueue_scripts($hook_suffix): void
	{
		if (!str_contains($hook_suffix, 'pkmgmt')) {
			return;
		}
		global $plugin_page;
		$screen_id = $plugin_page;
		// CSS
		wp_enqueue_style('parking-management-admin', pkmgmt_plugin_url('admin/css/admin.css'), array(), PKMGMT_VERSION);
		wp_enqueue_style('parking-management-admin-rtl', pkmgmt_plugin_url('admin/css/admin-rtl.css'), array(), PKMGMT_VERSION);
		wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), '6.0.0-beta3');
		wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', array('admin-menu', 'forms'), '5.3.3');
		wp_enqueue_style('parking-management-jquery-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.14.1/themes/base/jquery-ui.min.css');
		wp_enqueue_style('parking-management-easepick', 'https://cdn.jsdelivr.net/npm/@easepick/bundle@1.2.1/dist/index.css');

		wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', array(), false, true);
		wp_enqueue_script('parking-management-jquery', 'https://code.jquery.com/jquery-3.7.1.min.js');
		wp_enqueue_script('parking-management-jquery-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.14.1/jquery-ui.min.js', array('parking-management-jquery'));
		wp_enqueue_script('parking-management-jquery-validate', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.min.js', array('parking-management-jquery'));
		wp_enqueue_script('parking-management-additional-methods', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/additional-methods.min.js', array('parking-management-jquery'));
		wp_enqueue_script('parking-management-easepick', 'https://cdn.jsdelivr.net/npm/@easepick/bundle@1.2.1/dist/index.umd.min.js');
		wp_enqueue_script('parking-management-luxon', 'https://cdn.jsdelivr.net/npm/luxon/build/global/luxon.min.js');
		wp_enqueue_script(
			'parking-management-admin',
			pkmgmt_plugin_url('admin/js/admin.js'),
			array(
				'bootstrap',
				'parking-management-jquery',
				'parking-management-jquery-ui',
				'parking-management-jquery-validate',
				'parking-management-additional-methods',
				'parking-management-easepick',
				'parking-management-luxon',
			),
			PKMGMT_VERSION);
		wp_enqueue_script(
			'parking-management-admin-bottom',
			pkmgmt_plugin_url('admin/js/admin_bottom.js'),
			['parking-management-jquery', 'postbox'],
			PKMGMT_VERSION,
			true
		);
		wp_localize_script(
			'parking-management-admin-bottom',
			'config',
			['screen_id' => $screen_id]
		);

		$terminals = array();
		foreach (Airport::cases() as $airport) {
			$terminals[strtolower($airport->name)] = Terminal::forAirport($airport);
		}
		wp_localize_script(
			'parking-management-admin',
			'pkmgmt_sites_config',
			array(
				'airports' => Terminal::airportOptions(),
				'terminals' => $terminals,
				'legacy_sites' => Terminal::legacySiteOptions(),
				'vehicle_types' => array(
					'car' => __('Car', 'parking-management'),
					'motorcycle' => __('Motorcycle', 'parking-management'),
					'truck' => __('Truck', 'parking-management'),
				),
				'rest_url' => esc_url_raw(rest_url('pkmgmt/v1/')),
				'nonce' => wp_create_nonce('wp_rest'),
			)
		);
	}

	public function menu(): void
	{
		global $_wp_last_object_menu;

		$_wp_last_object_menu++;

		add_menu_page(
			__("Parking Management", 'parking-management'),
			__("Car park", 'parking-management'),
			"pkmgmt_read",
			'pkmgmt',
			array('ParkingManagement\Admin\Pages', 'management'),
			'dashicons-car',
			$_wp_last_object_menu
		);

		$management = add_submenu_page('pkmgmt',
			__('Configuration', 'parking-management'),
			__('Configuration', 'parking-management'),
			"pkmgmt_read",
			"pkmgmt",
			array('ParkingManagement\Admin\Pages', 'management')
		);

		add_action("load-" . $management,
			array(&$this, "load"),
			10,
			0);


	}

	/**
	 * @throws Exception
	 */
	public function load(): void
	{
		switch ($this->current_action()) {
			case "save":
				$query = array();
				if (isset($_POST['pkmgmt-json'])) {
					// The form bundles info/database/sites/supabase into one
					// JSON field precisely so this can't happen regardless of
					// how many sites exist - but if that field got mangled in
					// transit, fail loudly instead of saving with the
					// properties it covers silently reset to their defaults.
					if (!self::merge_json_fields()) {
						$query['message'] = __('Not saved: the configuration data was corrupted in transit. Please try again.', 'parking-management');
						wp_safe_redirect(add_query_arg($query, menu_page_url('pkmgmt', false)));
						exit();
					}
				} elseif (self::post_truncated()) {
					// Fallback for a non-JS submission: PHP silently dropped the
					// trailing fields (Supabase, last site...), and saving now
					// would overwrite them with defaults, so refuse.
					$query['message'] = sprintf(
						__('Not saved: the form has more fields than PHP max_input_vars (%d) allows, so some values were dropped. Raise max_input_vars and save again.', 'parking-management'),
						(int)ini_get('max_input_vars')
					);
					wp_safe_redirect(add_query_arg($query, menu_page_url('pkmgmt', false)));
					exit();
				}
				$pm = $this->load_save();
				if (!$pm) {
					$query['message'] = 'failed';
				} else {
					$query['message'] = 'saved';
				}
				$redirect_to = add_query_arg($query, menu_page_url('pkmgmt', false));
				wp_safe_redirect($redirect_to);
				exit();
			default:
				$this->load_default();
				break;
		}
	}

	/**
	 * True when PHP hit max_input_vars while parsing the POST body, i.e. the
	 * trailing fields of the config form never reached us.
	 */
	private static function post_truncated(): bool
	{
		$max = (int)ini_get('max_input_vars');
		if ($max <= 0) {
			return false;
		}
		$leaves = 0;
		array_walk_recursive($_POST, static function () use (&$leaves) {
			$leaves++;
		});
		return $leaves >= $max;
	}

	/**
	 * The config form's JS bundles the info/database/sites/supabase fields
	 * into one `pkmgmt-json` field instead of posting each of them
	 * individually, so the save can't be truncated by max_input_vars no
	 * matter how many sites exist. Decodes that field back into $_POST so
	 * load_save() can read it exactly like it always has. Returns false if
	 * the field isn't valid JSON, without touching $_POST.
	 */
	private static function merge_json_fields(): bool
	{
		$json = wp_unslash($_POST['pkmgmt-json']);
		$decoded = json_decode($json, true);
		if (!is_array($decoded)) {
			return false;
		}
		foreach (ParkingManagement::properties_available as $property => $config) {
			$key = 'pkmgmt-' . $property;
			if (array_key_exists($key, $decoded)) {
				// Re-slash: load_save() unslashes $_POST as a whole further
				// down, same as it always has for the non-JS fields.
				$_POST[$key] = wp_slash($decoded[$key]);
			}
		}
		return true;
	}

	/**
	 * @throws Exception
	 */
	private function load_save(): false|ParkingManagement
	{
		$id = get_post_id_by_post_type(ParkingManagement::post_type);
		if (!current_user_can('pkmgmt_edit', $id))
			wp_die(__("You are not allowed to edit this page.", 'parking-management'));

		$data = array();

		foreach (ParkingManagement::properties_available as $property => $config) {
			$data[$property] = $_POST['pkmgmt-' . $property] ?? Template::get_default($property);
		}

		$args = array_merge(
			$_REQUEST,
			array(
				'id' => $id,
				'title' => $_POST['pkmgmt-title'] ?? null,
				'name' => $_POST['pkmgmt-name'] ?? null,
				'locale' => $_POST['pkmgmt-locale'] ?? null,
			),
			$data
		);
		$args = wp_unslash($args);

		$args['id'] = (int)$args['id'];

		if (-1 == $args['id']) {
			$pm = ParkingManagement::get_template();
		} else {
			$pm = ParkingManagement::get_instance($args['id']);
		}


		if (null !== $args['title']) {
			$pm->set_title($args['title']);
		}
		if (null !== $args['name']) {
			$pm->set_name($args['name']);
		}
		if (null !== $args['locale']) {
			$pm->set_locale($args['locale']);
		}

		$properties = array();
		foreach (ParkingManagement::properties_available as $prop => $config) {
			if (null !== $args[$prop]) {
				$properties[$prop] = $args[$prop];
			}
		}
		$pm->set_properties($properties);
		$pm->save();
		return $pm;
	}

	private function load_default(): void
	{
		$id = get_post_id_by_post_type(ParkingManagement::post_type);
		$args = array_merge(
			array(
				'id' => $id,
			),
			$_REQUEST
		);

		$args = wp_unslash($args);

		$args['id'] = (int)$args['id'];

		if (0 == $args['id']) {
			ParkingManagement::get_template();
		} else {
			ParkingManagement::get_instance($args['id']);
		}
		self::meta_box();

	}

	private function current_action()
	{
		if (isset($_REQUEST['action']) && -1 != $_REQUEST['action'])
			return $_REQUEST['action'];

		if (isset($_REQUEST['action2']) && -1 != $_REQUEST['action2'])
			return $_REQUEST['action2'];

		return false;
	}

	private static function meta_box(): void
	{
		// MetaBox
		foreach (ParkingManagement::properties_available as $prop => $config) {
			add_meta_box(
				$prop,
				__($config['title'], 'parking-management'),
				['ParkingManagement\Admin\Pages', $prop . '_box'],
				null,
				$prop,
				'core'
			);
		}
	}
}

new Admin();
