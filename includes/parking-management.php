<?php

namespace ParkingManagement;

use Exception;
use WP_Error;

class ParkingManagement
{

	const post_type = 'parking_management';

	const properties_available = [
		'info' => ['title' => 'Information'],
		'database' => ['title' => 'Database'],
		'sites' => ['title' => 'Sites'],
		'supabase' => ['title' => 'Supabase'],
	];
	private static ParkingManagement|null $current = null;

	public int $id;
	public string $name;
	public string $title;
	public string $locale = 'en-US';

	private array $properties = [];

	public function __construct($post = null)
	{
		$post = get_post($post);
		if ($post and self::post_type === get_post_type($post)) {
			$this->id = $post->ID;
			$this->name = $post->post_name;
			$this->title = $post->post_title;
			$this->locale = get_post_meta($post->ID, 'pkmgmt_locale', true);
		}
		$this->construct_properties();

		do_action('pkmgmt_parking_management', $this);

	}

	/**
	 * Returns true if this parking management is not yet saved to the database.
	 */
	public function initial(): bool
	{
		return empty($this->id);
	}


	private function construct_properties(): void
	{
		$builtin_properties = [];
		foreach (self::properties_available as $property => $config) {
			$builtin_properties[$property] = Template::get_default($property);
		}
		$properties = apply_filters(
			'pkmgmt_pre_construct_parking_management_properties',
			$builtin_properties, $this
		);

		// Filtering out properties with invalid name
		$properties = array_filter(
			$properties,
			static function ($key) {
				$sanitized_key = sanitize_key($key);
				return $key === $sanitized_key;
			},
			ARRAY_FILTER_USE_KEY
		);

		foreach ($properties as $name => $val) {
			$prop = $this->retrieve_property($name);

			if (isset($prop)) {
				$properties[$name] = $prop;
			}
		}

		$this->properties = $properties;
		foreach ($properties as $name => $val) {
			$properties[$name] = apply_filters(
				"pkmgmt_parking_management_property_$name",
				$val, $this
			);
		}
		$this->properties = $properties;
		$properties = (array)apply_filters(
			'pkmgmt_parking_management_properties',
			$properties, $this
		);
		$this->properties = $properties;
	}

	/**
	 * Registers the post type for parking management.
	 */
	public static function register_post_type(): void
	{
		register_post_type(self::post_type, array(
			'labels' => array(
				'name' => __('Parking Management', 'parking-management'),
				'singular_name' => __('Parking Management', 'parking-management')),
			'rewrite' => false,
			'query_var' => false));
	}

	/**
	 * Returns the parking management that is currently processed.
	 *
	 * @return ParkingManagement|null
	 */
	public static function get_current(): ?ParkingManagement
	{
		return self::$current;
	}

	public static function get_instance($post = null): ParkingManagement
	{
		$pm = null;

		if ($post instanceof self) {
			$pm = $post;
		} else if (!empty($post)) {
			$post = get_post($post);
			if (isset($post) and self::post_type === get_post_type($post)) {
				$pm = new self($post);
			}
		}
		return $pm::$current = $pm;
	}

	/**
	 * Returns a ParkingManagement data filled by default contents.
	 *
	 * @param array|string $args Optional.
	 * @return ParkingManagement A new ParkingManagement object
	 */
	public static function get_template(array|string $args = ''): ParkingManagement
	{
		$args = wp_parse_args($args, array(
			'locale' => 'en-US',
			'title' => '',
			'name' => ''
		));

		if (!isset($args['locale'])) {
			$args['locale'] = determine_locale();
		}
		if (!isset($args['title'])) {
			$args['title'] = __('Untitled', 'parking-management');
		}
		if (!isset($args['name'])) {
			$args['name'] = __('Untitled', 'parking-management');
		}

		$callback = static function ($args) {
			$pm = new self;
			$pm->locale = $args['locale'];
			$pm->title = $args['title'];
			$pm->name = $args['name'];

			$properties = $pm->get_properties();

			foreach ($properties as $key => $value) {
				$default_template = Template::get_default($key);

				if (isset($default_template)) {
					$properties[$key] = $default_template;
				}
			}

			$pm->set_properties($properties);
			return $pm;
		};

		$pm = pkmgmt_switch_locale($args['locale'],
			$callback,
			$args
		);

		self::$current = apply_filters('pkmgmt_parking_management_default_pack',
			$pm, $args
		);
		return self::$current;
	}


	/**
	 * Retrieves parking management property of the specified name from the database.
	 *
	 * @param string $prop Property name.
	 * @return array|string|null Property value. Null if property does not exist.
	 */
	public function retrieve_property(string $prop): array|string|null
	{
		$property = null;

		if (!$this->initial()) {
			$post_id = $this->id;

			if (metadata_exists('post', $post_id, 'pkmgmt_' . $prop)) {
				$property = get_post_meta($post_id, 'pkmgmt_' . $prop, true);
			} else if (metadata_exists('post', $post_id, $prop)) {
				$property = get_post_meta($post_id, $prop, true);
			}
		}
		return $property;
	}

	/**
	 * Returns the value for the given property name.
	 *
	 * @param string $name Property name.
	 * @return array|string Property value. Null if property does not exist.
	 */
	public function prop(string $name): array|string
	{
		$props = $this->get_properties();
		return $props[$name] ?? array();
	}


	/**
	 * Returns all the properties.
	 *
	 * @return array This parking management properties.
	 */
	public function get_properties(): array
	{
		return $this->properties;
	}


	/**
	 * Updates properties.
	 *
	 * @param array $properties New properties.
	 */
	public function set_properties(array $properties): void
	{
		$defaults = $this->get_properties();

		$properties = wp_parse_args($properties, $defaults);
//		$properties = array_intersect_key($properties, $defaults);

		$this->properties = $properties;
	}


	/**
	 * Returns ID of this parking management.
	 *
	 * @return int|string The ID.
	 */
	public function id(): int|string
	{
		return $this->id;
	}

	/**
	 * @throws Exception
	 */
	public function save(): WP_Error|int
	{
		$title = wp_slash($this->title);
		$props = wp_slash($this->get_properties());

		$post_content = implode("\n", pkmgmt_array_flatten($props));

		if ($this->initial()) {
			$post_id = wp_insert_post(array(
				'post_type' => self::post_type,
				'post_status' => 'publish',
				'post_title' => $title,
				'post_name' => $this->name,
				'post_content' => trim($post_content)));
		} else {
			$post_id = wp_update_post(array(
				'ID' => $this->id,
				'post_status' => 'publish',
				'post_title' => $this->title,
				'post_name' => $this->name,
				'post_content' => trim($post_content)));
		}
		if ($post_id) {
			foreach ($props as $prop => $value)
				update_post_meta($post_id, 'pkmgmt_' . $prop, pkmgmt_normalize_newline_deep($value));

			if (!empty($this->locale))
				update_post_meta($post_id, 'pkmgmt_locale', $this->locale);

			if ($this->initial()) {
				$this->id = $post_id;
				do_action('pkmgmt_after_create', $this);
			} else {
				do_action('pkmgmt_after_update', $this);
			}

			do_action('pkmgmt_after_save', $this);
		} else {
			error_log(var_export(["save", 'Could not save post'], true));
			throw new Exception("Could not save post");
		}
		return $post_id;
	}

	public function set_title($title): void
	{
		$title = strip_tags($title);
		$title = trim($title);

		if ('' === $title) {
			$title = __('Untitled', 'parking-management');
		}

		$this->title = $title;
	}

	public function set_name($name): void
	{
		$name = strip_tags($name);
		$name = trim($name);

		if ('' === $name) {
			$name = __('Untitled', 'parking-management');
		}

		$this->name = $name;
	}

	public function set_locale($locale): void
	{
		$locale = trim($locale);

		if (is_valid_locale($locale)) {
			$this->locale = $locale;
		} else {
			$this->locale = 'en_US';
		}
	}
}
