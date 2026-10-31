<?php

namespace ParkingManagement\Admin;

use Booking\Terminal;
use ParkingManagement\Html;
use ParkingManagement\Logger;
use ParkingManagement\ParkingManagement;
use ParkingManagement\Template;

class Pages
{
	public static function management(): void
	{
		$pm = ParkingManagement::get_current();
		echo '<div class="wrap pkmgmt-parking-management-config">';
		echo '<h2>' . esc_html__('Parking Management', 'parking-management') . '</h2>';
		echo '<br class="clear"/>';
		if ($pm === null) {
			$_REQUEST['message'] = 'Failed to get config';
			do_action('pkmgmt_admin_notices');
		} else {
			do_action('pkmgmt_admin_notices');
			self::config_form($pm);
		}
		echo '</div>';
	}

	public static function notices_message(): void
	{
		$page = $_REQUEST['page'] ?? '';
		if ($page != 'pkmgmt') {
			return;
		}
		if (empty($_REQUEST['message']))
			return;
		$message = $_REQUEST['message'];
		if ('saved' == $message)
			$message = __('Configuration saved.', 'parking-management');
		if (empty($message))
			return;

		echo sprintf('<div id="message" class="updated"><p>%s</p></div>', esc_html($message));

	}

	private static function config_form(ParkingManagement $pm): void
	{
		global $plugin_page;
		echo '<form id="pkmgmt-admin-config" method="post">';
		settings_fields(ParkingManagement::post_type);
		do_settings_sections(ParkingManagement::post_type);
		self::config_form_hidden($plugin_page, $pm->id);
		echo '<div id="poststuff" class="metabox-holder">';
		self::config_form_header($pm);

		foreach (ParkingManagement::properties_available as $property => $config) {
			do_meta_boxes(null, $property, $pm->prop($property));
		}
		wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
		wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );

		echo '</div>';
		echo '</form>';
		self::dialog_page_list();

	}

	private static function config_form_hidden(string $page, int $id): void
	{
		echo sprintf('
		<input type="hidden" id="page" name="page" value="%s" />
		<input type="hidden" id="post_ID" name="post_ID" value="%d" />
		<input type="hidden" id="hidden-action" name="action" value="save" />
		<input type="hidden" id="pkmgmt-locale" name="pkmgmt-locale" value="fr_FR">
		',
			$page,
			$id
		);
	}

	private static function config_form_header(ParkingManagement $pm): void
	{
		if (current_user_can('pkmgmt_edit', $pm->id)) {
			wp_nonce_field('pkmgmt-save_' . $pm->id);
		}
		echo '<div id="titlediv">';

		echo Html::_index("text", "pkmgmt-title", "pkmgmt-title", array(
			'class' => "wide",
			'placeholder' => esc_html__("Title", 'parking-management'),
			'size' => 80,
			'value' => esc_attr($pm->title)
		),
			!(current_user_can('pkmgmt_edit', $pm->id))
		);

		echo Html::_p(array(),
			esc_html__("Name", 'parking-management') . '<br/>',
			Html::_index("text", "pkmgmt-name", "pkmgmt-name", array(
				'class' => "wide",
				'size' => 80,
				'value' => esc_attr($pm->name)
			),
				!(current_user_can('pkmgmt_edit', $pm->id))
			)
		);
		echo self::_shortcode_field(
			'shortcode-form',
			'shortcode-form',
			esc_html__("Copy and paste this code into your page to include the booking form. A site selector is shown automatically when more than one site is active.", 'parking-management'),
			"[parking-management type='form']"
		);
		echo self::_shortcode_field(
			'shortcode-home-form',
			'shortcode-home-form',
			esc_html__("Copy and paste this code into your page to include the home (quick-quote) form.", 'parking-management'),
			"[parking-management type='home-form']"
		);
		echo self::_shortcode_field(
			'shortcode-price',
			'shortcode-price',
			esc_html__("Copy and paste this code into your page to include the price table.", 'parking-management'),
			"[parking-management type='price']"
		);
		echo self::_shortcode_field(
			'shortcode-booked',
			'shortcode-booked',
			esc_html__("Copy and paste this code into your page to include the booked message.", 'parking-management'),
			"[parking-management type='booked']"
		);
		echo self::_shortcode_field(
			'shortcode-high-season',
			'shortcode-high-season',
			esc_html__("Copy and paste this code into your page to include the high-season message.", 'parking-management'),
			"[parking-management type='high-season']"
		);
		echo self::_shortcode_field(
			'shortcode-payment-payplug',
			'shortcode-payment-payplug',
			esc_html__("Copy and paste this code into your page to include the payplug payment form.", 'parking-management'),
			"[parking-management type='payment' payment_provider='payplug']"
		);
		echo self::_shortcode_field(
			'shortcode-payment-mypos',
			'shortcode-payment-mypos',
			esc_html__("Copy and paste this code into your page to include the mypos payment form.", 'parking-management'),
			"[parking-management type='payment' payment_provider='mypos']"
		);
		echo self::_shortcode_field(
			'shortcode-payment-stripe',
			'shortcode-payment-stripe',
			esc_html__("Copy and paste this code into your page to include the stripe payment form.", 'parking-management'),
			"[parking-management type='payment' payment_provider='stripe']"
		);
		echo self::_shortcode_field(
			'shortcode-notification-confirmation',
			'shortcode-notification-confirmation',
			esc_html__("Copy and paste this code into your page where you want a notification confirmation after an order.", 'parking-management'),
			"[parking-management type='notification' action='confirmation']"
		);
		echo self::_shortcode_field(
			'shortcode-notification-cancellation',
			'shortcode-notification-cancellation',
			esc_html__("Copy and paste this code into your page where you want a notification cancellation after an order.", 'parking-management'),
			"[parking-management type='notification' action='cancellation']"
		);
		echo '<div class="save-pkmgmt">';
		echo Html::_index("submit", "pkmgmt-save", "pkmgmt-save", array(
			'class' => 'button-primary',
			'value' => esc_html__("Save", 'parking-management'),
		));
		echo '</div>';
		echo '</div>';
	}

	private static function _fieldset(string $legend, ...$contents): string
	{
		return Html::_fieldset_with_attr(
			['class' => "border border-dark p-3 position-relative my-4"],
			'<legend class="w-auto mx-2 bg-white position-absolute top-0 start-0 translate-middle-y fs-6">' . $legend . '</legend>',
			...$contents);
	}

	private static function _shortcode_field($id, $name, $title, $shortcode): string
	{
		return Html::_p(array('class' => 'mb-0'),
			$title,
			'<br/>',
			'<div class="input-container wide shortcode-div">',
			Html::_index(
				"text",
				$id,
				$name,
				array(
					'class' => "wide shortcode",
					'size' => 80,
					'value' => $shortcode
				),
				false,
				true,
			),
			'<div class="tooltip">',
			'<button class="shortcode-copy" type="button">',
			'<span class="tooltiptext">Copy to clipboard</span>',
			'<i class="fas fa-copy darkgray"></i>',
			'</button>',
			'</div>',
			'<span class="shortcode-copy-message">copied</span>',
			'</div>'
		);
	}

	private static function _field($id, $div_class, $name, $label_content, $value): string
	{
		return Html::_div(array('class' => $div_class),
			Html::_label($id, $label_content),
			'<br/>',
			Html::_index("text", $id, $name, array(
				'class' => 'wide',
				'size' => 80,
				'value' => $value
			))
		);
	}

	private static function _field_url($id, $div_class, $name, $label_content, $value): string
	{
		return Html::_div(array('class' => $div_class),
			Html::_label($id, $label_content),
			'<br/>',
			Html::_index("text", $id, $name, array(
				'class' => 'wide',
				'size' => 80,
				'value' => $value
			))
		);
	}

	private static function _field_password($id, $div_class, $name, $label_content, $value): string
	{
		return Html::_div(array('class' => $div_class),
			Html::_label($id, $label_content),
			'<br/>',
			Html::_password($id, $name, array('class' => 'password-container'), array(
				'class' => 'wide password-input',
				'size' => 80,
				'value' => $value
			))
		);
	}

	private static function _field_checkbox($id, $div_class, $name, $span_content, $values): string
	{
		if (!is_array($values)) {
			return Html::_div(array('class' => $div_class), "bad values type");
		}
		$contents = array();
		$contents[] = '<div class="pb-2">' . $span_content . '</div>';
		foreach ($values as $key => $value) {
			$contents[] = Html::_div(
				array('class' => 'form-check form-switch form-check-inline'),
				Html::_checkbox($id, $name, array('class' => $id . ' form-check-input'), $key, $value),
			);
		}
		return Html::_div(array('class' => $div_class),
			...$contents
		);
	}

	private static function _field_select($id, $div_class, $name, $label_content, $options, $value, string $select_class = ''): string
	{
		if (!is_array($options)) {
			return Html::_div(array('class' => $div_class), "bad values type");
		}

		return Html::_div(array('class' => $div_class),
			Html::_label($id, $label_content),
			'<br/>',
			Html::_select($id, $name, $select_class !== '' ? array('class' => $select_class) : array(), $options, $value)
		);
	}

	private static function _field_textarea($id, $div_class, $name, $label_content, $value, $args = array()): string
	{
		$cols = array_key_exists('cols', $args) ? $args['cols'] : "100";
		$rows = array_key_exists('rows', $args) ? $args['rows'] : "12";
		return Html::_div(array('class' => $div_class),
			Html::_label($id, $label_content),
			'<br/>',
			Html::_textarea($id, $name, $value, $cols, $rows),
		);
	}

	private static function _field_page($id, $div_class, $name, $label_content, $value): string
	{
		return Html::_div(array('class' => $div_class),
			Html::_label($id, $label_content),
			Html::_div(array('class' => 'input-group mb-3'),
				Html::_div(array('class' => 'input-group-prepend'),
					'<button type="button" class="btn btn-outline-primary pages-list " data-bs-toggle="modal" data-bs-target="#dialog-pages-list">' . esc_html__('Browse page', 'parking-management') . '</button>'
				),
				Html::_index("text", $id, $name, array(
					'class' => 'form-control pages-list',
					'size' => 80,
					'value' => $value
				))
			)
		);
	}

	private static function _field_payment($id, $div_class, $name, $properties): string
	{
		$contents = array();
		foreach ($properties as $key => $params) {
			$contents[] = Html::_index('hidden', $id . '-' . $key . '-title', $name . '[' . $key . '][title]', array('value' => $params['title']));
			$contents[] = Html::_index('hidden', $id . '-' . $key . '-type', $name . '[' . $key . '][type]', array('value' => $params['type']));
			$contents[] = match ($params['type']) {
				'password' => self::_field_password(
					$id . '-' . $key,
					$div_class . " form-control",
					$name . '[' . $key . '][value]',
					$params['title'],
					$params['value']),
				'text' => self::_field(
					$id . '-' . $key,
					$div_class . " form-control",
					$name . '[' . $key . '][value]',
					$params['title'],
					$params['value']),
				'url' => self::_field_url(
					$id . '-' . $key,
					$div_class . " form-control",
					$name . '[' . $key . '][value]',
					$params['title'],
					$params['value']),
				'page' => self::_field_page(
					$id . '-' . $key,
					$div_class . " form-control",
					$name . '[' . $key . '][value]',
					$params['title'],
					$params['value']),
				default => ''
			};
		}
		return self::_fieldset(
			'Properties',
			...$contents
		);
	}

	private static function _option_card(string $namePrefix, string $id, array $option): string
	{

		return '<div class="col-sm-3">'
			. '<div class="card">'
			. '<div class="card-body">'
			. '<h5 class="card-title">' . $option['title'] . '</h5>'
			. '<input type="hidden" value="' . $option['title'] . '" name="' . esc_attr("{$namePrefix}[{$id}][title]") . '"/>'
			. '<input type="hidden" value="' . $option['label'] . '" name="' . esc_attr("{$namePrefix}[{$id}][label]") . '"/>'
			. '<div class="input-group mb-3">'
			. '<div class="input-group-text">'
			. '<input type="hidden" value="0" name="' . esc_attr("{$namePrefix}[{$id}][enabled]") . '"/>'
			. '<input type="hidden" value="0" name="' . esc_attr("{$namePrefix}[{$id}][checked]") . '"/>'
			. Html::_index('checkbox', 'pkmgmt-form-options-' . $id . '-enabled', "{$namePrefix}[{$id}][enabled]",
				array(
					'aria-label' => 'Enable the ' . $option['title'],
					"value" => '1',
					'class' => 'form-check-input mt-0'
				),
				false,
				false,
				$option['enabled'] === '1'

			)
			. '</div>'
			. '<div class="input-group-text">'
			. Html::_index('checkbox', 'pkmgmt-form-options-' . $id . '-checked', "{$namePrefix}[{$id}][checked]",
				array(
					'aria-label' => 'the ' . $option['title'] . 'is checked in form',
					"value" => '1',
					'class' => 'form-check-input mt-0'
				),
				false,
				false,
				$option['checked'] === '1'

			)
			. '</div>'
			. '<span class="input-group-text">€</span>'
			. '<input type="number" class="form-control" name="' . esc_attr("{$namePrefix}[{$id}][price]") . '" aria-label="Price for ' . $option['title'] . '" value="' . $option['price'] . '">'
			. '</div>'
			. (isset($option['start'], $option['end']) ?
				'<div class="input-group mb-3">'
				. '<span class="input-group-text">Start</span>'
				. '<input type="time" class="form-control" name="' . esc_attr("{$namePrefix}[{$id}][start]") . '" value="' . $option['start'] . '">'
				. '<span class="input-group-text">End</span>'
				. '<input type="time" class="form-control" name="' . esc_attr("{$namePrefix}[{$id}][end]") . '" value="' . $option['end'] . '">'
				. '</div>'
				: '')
			. '</div>'
			. '</div>'
			. '</div>';
	}

	private static function notification_template($id, $name, array|string $templates): void
	{
		echo '<div class="' . $id . '-fields">';
		if (is_array($templates)) {
			if (empty($templates)) {
				$templates = [
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
				];
			}
			echo Html::_label("nav-$id-tab", 'Templates');
			echo '<nav>';
			echo '<div class="nav nav-tabs" id="nav-' . $id . '-tab" role="tablist">';
			foreach ($templates as $key => $template) {
				echo Html::_button(array(
					'class' => 'nav-link',
					'id' => "nav-$id-" . $key . '-tab',
					'data-bs-toggle' => 'tab',
					'data-bs-target' => "#nav-${id}-" . $key,
					'type' => 'button',
					'role' => 'tab',
					'aria-controls' => "nav-${id}-" . $key,
				),
					$template['title']
				);
			}
			echo '</div>';
			echo '</nav>';
			echo '<div class="tab-content" id="nav-' . $id . '-tab-content">';
			foreach ($templates as $key => $template) {
				echo '<div class="tab-pane fade" id="nav-' . $id . '-' . $key . '" role="tabpanel" aria-labelledby="nav-' . $id . '-' . $key . '-tab" tabindex="0">';
				echo Html::_index('hidden', $id . '-templates-' . $key . '-title', $name . "[" . $key . '][title]', array('value' => $template['title']));
				echo Html::_index('hidden', $id . '-templates-' . $key . '-type', $name . "[" . $key . '][type]', array('value' => $template['type']));
				wp_editor($template['value'], $id . '-' . $key . '-value', [
					'textarea_name' => $name . "[" . $key . '][value]',
					'textarea_rows' => 30,
					'tabindex' => "0",
					'teeny' => true,
					'media_buttons' => false,
				]);
				echo '</div>';
			}
			echo '</div>';
		} else {
			echo self::_field_textarea($id . '-template', $id . '_field', $name, esc_html__('Template', 'parking-management'), $templates, array('cols' => "0"));
		}
		echo '</div>';
	}

	public static function info_box($info, $box): void
	{
		echo '<p class="description">' . esc_html__('Company address/RCS/email live on each site now (a site can be a different legal company) - see the Info tab on each site below.', 'parking-management') . '</p>';
		echo '<div class="' . $box['id'] . '-fields">';
		echo self::_field_checkbox('info-logs', 'info_field', 'pkmgmt-info[logs]', 'Logs', $info['logs']);
		echo '</div>';
	}

	public static function database_box($database, $box): void
	{
		self::database($database, $box, 'pkmgmt-database');
	}

	private static function database($database, $box, string $fieldPrefix): void
	{
		$contents = [];
		$contents[] = '<div class="' . $box['id'] . '-fields">';
		$contents[] = self::_field($fieldPrefix . '-name', 'database_field', "{$fieldPrefix}[name]", esc_html__('Name', 'parking-management'), $database['name'] ?? '');
		$contents[] = self::_field($fieldPrefix . '-host', 'database_field', "{$fieldPrefix}[host]", esc_html__('Host', 'parking-management'), $database['host'] ?? '');
		$contents[] = self::_field($fieldPrefix . '-port', 'database_field', "{$fieldPrefix}[port]", esc_html__('Port', 'parking-management'), $database['port'] ?? '');
		$contents[] = self::_field($fieldPrefix . '-user', 'database_field', "{$fieldPrefix}[user]", esc_html__('Username', 'parking-management'), $database['user'] ?? '');
		$contents[] = self::_field_password($fieldPrefix . '-password', 'database_field', "{$fieldPrefix}[password]", esc_html__('Password', 'parking-management'), $database['password'] ?? '');
		$contents[] = '</div>';
		echo self::_fieldset('Database', ...$contents);
	}

	private static function payment($id, $payment, $name): void
	{
		$payment_name = $payment['name'] ?? 'payplug';
		if (!array_key_exists('properties', $payment) || empty($payment['properties'])) {
			$payment['properties'] = Template::payment_properties();
		}
		$contents = [];
		$contents[] = Html::_div(
			array('class' => 'form-check form-switch form-check-inline'),
			Html::_checkbox($id, $name, array('class' => 'form-check-input'), 'enabled', $payment['enabled'])
		);
		$contents[] = Html::_div(
			array('class' => 'form-check form-switch form-check-inline'),
			Html::_checkbox($id, $name, array('class' => 'valid-on-payment form-check-input'), 'valid-on-payment', $payment['valid-on-payment']),
		);
		$contents[] = Html::_div(
			array('class' => 'form-check form-switch form-check-inline'),
			Html::_checkbox($id, $name, array('class' => 'redirect-to-provider form-check-input'), 'redirect-to-provider', $payment['redirect-to-provider']),
		);
		$contents[] = Html::_div(
			array('class' => 'form-check form-switch form-check-inline'),
			Html::_checkbox($id, $name, array('class' => 'form-check-input'), 'active-test', $payment['active-test'])
		);

		$contents_radio[] = '<div class="mt-1" id="' . $id . '-providers-select' . '">';
		foreach ($payment['properties'] as $provider => $property) {
			$contents_radio[] = Html::_div(
				['class' => 'form-check form-check-inline'],

				Html::_radio(
					$id . "-{$provider}-radio",
					$name . '[name]',
					"$provider",
					[
						'class' => 'form-check-input',
						'data-bs-target' => "#nav-{$id}-{$provider}",
						'data-bs-content' => "#nav-{$id}-tab-content",
					],
					$payment_name == $provider
				),
				Html::_label_with_attr(
					[
						'class' => 'form-check-label',
					],
					$id . "-{$provider}-radio",
					ucfirst($provider))
			);
		}
		$contents_radio[] = '</div>';
		$contents[] = self::_fieldset('Providers', ...$contents_radio);

		$contents[] = '<div class="tab-content" id="nav-' . $id . '-tab-content">';
		foreach ($payment['properties'] as $provider => $property) {

			$contents[] = Html::_div(
				[
					'class' => 'tab-pane fade' . (($payment_name == $provider) ? ' show active' : ''),
					'id' => "nav-{$id}-{$provider}",
					'role' => "tabpanel",
				],
				self::_field_payment(
					"{$id}-{$provider}",
					"{$id}_field",
					"{$name}[properties][${provider}]",
					$payment['properties'][$provider]
				)
			);
		}
		$contents[] = '</div>';

		echo self::_fieldset('Payment', ...$contents);

	}

	private static function mail($mail, $box, string $fieldPrefix): void
	{
		$mail = wp_parse_args($mail, Template::mail_properties());
		echo Html::_div(array('class' => $box['id'] . '-fields'),
			Html::_index('hidden', $fieldPrefix . '-host-title', "{$fieldPrefix}[host][title]", array('value' => $mail['host']['title'])),
			Html::_index('hidden', $fieldPrefix . '-host-type', "{$fieldPrefix}[host][type]", array('value' => $mail['host']['type'])),
			self::_field($fieldPrefix . '-host', 'mail-field', "{$fieldPrefix}[host][value]", $mail['host']['title'], $mail['host']['value'])
		);
		echo Html::_div(array('class' => $box['id'] . '-fields'),
			Html::_index('hidden', $fieldPrefix . '-login-title', "{$fieldPrefix}[login][title]", array('value' => $mail['login']['title'])),
			Html::_index('hidden', $fieldPrefix . '-login-type', "{$fieldPrefix}[login][type]", array('value' => $mail['login']['type'])),
			self::_field($fieldPrefix . '-login', 'mail-field', "{$fieldPrefix}[login][value]", $mail['login']['title'], $mail['login']['value'])
		);
		echo Html::_div(array('class' => $box['id'] . '-fields'),
			Html::_index('hidden', $fieldPrefix . '-password-title', "{$fieldPrefix}[password][title]", array('value' => $mail['password']['title'])),
			Html::_index('hidden', $fieldPrefix . '-password-type', "{$fieldPrefix}[password][type]", array('value' => $mail['password']['type'])),
			self::_field_password($fieldPrefix . '-password', 'mail-field', "{$fieldPrefix}[password][value]", $mail['password']['title'], $mail['password']['value']),
		);
		echo Html::_div(array('class' => $box['id'] . '-fields'),
			Html::_index('hidden', $fieldPrefix . '-sender-title', "{$fieldPrefix}[sender][title]", array('value' => $mail['sender']['title'])),
			Html::_index('hidden', $fieldPrefix . '-sender-type', "{$fieldPrefix}[sender][type]", array('value' => $mail['sender']['type'])),
			Html::_div(array('class' => 'mail-field'),
				Html::_label($fieldPrefix . '-sender', $mail['sender']['title']),
				'<br/>',
				Html::_index("email", $fieldPrefix . '-sender', "{$fieldPrefix}[sender][value]", array(
					'class' => 'wide',
					'size' => 80,
					'value' => $mail['sender']['value']
				))
			),
		);
	}

	private static function sms($sms, $box, string $fieldPrefix): void
	{
		$sms = wp_parse_args($sms, ['type' => 'AWS', 'user' => '', 'password' => '', 'sender' => '']);
		echo '<div class="' . $box['id'] . '-fields">';
		echo self::_field_select($fieldPrefix . '-type', 'sms_field', "{$fieldPrefix}[type]", esc_html__('Type', 'parking-management'),
			array(
				array(
					'value' => 'AWS',
					'label' => 'AWS',
				),
				array(
					'value' => 'OVH',
					'label' => 'OVH',
				)
			),
			$sms['type']);
		echo '</div>';
		echo '<div class="' . $box['id'] . '-fields">';
		echo self::_field($fieldPrefix . '-user', 'sms_field', "{$fieldPrefix}[user]", esc_html__('Username', 'parking-management'), $sms['user']);
		echo '</div>';
		echo '<div class="' . $box['id'] . '-fields">';
		echo self::_field_password($fieldPrefix . '-password', 'sms_field', "{$fieldPrefix}[password]", esc_html__('Password', 'parking-management'), $sms['password']);
		echo '</div>';
		echo '<div class="' . $box['id'] . '-fields">';
		echo self::_field($fieldPrefix . '-sender', 'sms_field', "{$fieldPrefix}[sender]", esc_html__('Sender', 'parking-management'), $sms['sender']);
		echo '</div>';
	}

	private static function _date_range_row(string $fieldPrefix, $id, array $date): string
	{
		return '<div class="dates-element">'
			. '<label for="' . esc_attr("{$fieldPrefix}-start-{$id}") . '">start</label>'
			. '<input type="date" id="' . esc_attr("{$fieldPrefix}-start-{$id}") . '" name="' . esc_attr("{$fieldPrefix}[{$id}][start]") . '" class="start-date" value="' . esc_attr($date['start'] ?? '') . '">'
			. '<label for="' . esc_attr("{$fieldPrefix}-end-{$id}") . '">end</label>'
			. '<input type="date" id="' . esc_attr("{$fieldPrefix}-end-{$id}") . '" name="' . esc_attr("{$fieldPrefix}[{$id}][end]") . '" class="end-date" value="' . esc_attr($date['end'] ?? '') . '">'
			. '<label for="' . esc_attr("{$fieldPrefix}-message-{$id}") . '">message</label>'
			. '<input type="text" id="' . esc_attr("{$fieldPrefix}-message-{$id}") . '" name="' . esc_attr("{$fieldPrefix}[{$id}][message]") . '" class="message" placeholder="Message" value="' . esc_attr($date['message'] ?? '') . '">'
			. '<i class="fas fa-trash delete"></i>'
			. '</div>';
	}

	public static function sites_box($sites, $box): void
	{
		echo '<div id="' . esc_attr($box['id']) . '" class="sites-global">';
		echo '<div class="dates-header">';
		echo '<span>' . esc_html__('Add a site', 'parking-management') . '</span>';
		echo '<button id="sites-add-element" type="button"><i class="fas fa-plus"></i></button>';
		echo '</div>';
		echo '<div id="sites_body" class="sites-body">';
		if (is_array($sites)) {
			foreach ($sites as $id => $site) {
				if (!is_array($site))
					continue;
				echo self::_site_item($id, $site);
			}
		}
		echo '</div>';
		echo '</div>';
	}

	private static function _site_item($id, array $site): string
	{
		$name = "pkmgmt-sites[$id]";
		$box = ['id' => "site-$id"];

		$header = '<div class="d-flex justify-content-between align-items-start mb-2">'
			. self::_field("site-$id-name", 'location_field flex-grow-1 me-3', "{$name}[name]", esc_html__('Name', 'parking-management'), $site['name'] ?? '')
			. '<span class="site-id-badge text-muted small me-3" title="'
			. esc_attr__('Use this id in the booking page URL, e.g. ?site_id=' . $id, 'parking-management')
			. '">' . esc_html__('ID', 'parking-management') . ': <code>' . esc_html($id) . '</code></span>'
			. Html::_switch("site-$id-active", "{$name}[active]", [], esc_html__('Active', 'parking-management'), $site['active'] ?? '1')
			. '<i class="fas fa-trash delete-site ms-3" title="' . esc_attr__('Remove', 'parking-management') . '"></i>'
			. '</div>';

		$sections = [
			'info' => ['label' => esc_html__('Info', 'parking-management'), 'content' => self::_site_info_tab($id, $name, $site)],
			'tariffs' => ['label' => esc_html__('Price', 'parking-management'), 'content' => self::_site_tariffs_tab($name, $site)],
			'form' => ['label' => esc_html__('Form options', 'parking-management'), 'content' => self::_site_form_tab($box, $name, $site)],
			'booked' => ['label' => esc_html__('Booked dates', 'parking-management'), 'content' => self::_site_booked_dates_tab($name, $site)],
			'high_season' => ['label' => esc_html__('High season', 'parking-management'), 'content' => self::_site_high_season_tab($name, $site)],
			'notification' => ['label' => esc_html__('Notification', 'parking-management'), 'content' => self::_site_notification_tab($box, $name, $site)],
			'payment' => ['label' => esc_html__('Payment', 'parking-management'), 'content' => self::_site_payment_tab($box, $name, $site)],
			'templates' => ['label' => esc_html__('Templates', 'parking-management'), 'content' => self::_site_templates_tab($id, $name, $site)],
		];

		$navItems = [];
		$panes = [];
		$first = true;
		foreach ($sections as $key => $section) {
			$tabId = "site-{$id}-{$key}";
			$navItems[] = Html::_button([
				'class' => 'nav-link' . ($first ? ' active' : ''),
				'id' => "nav-{$tabId}-tab",
				'data-bs-toggle' => 'tab',
				'data-bs-target' => "#nav-{$tabId}",
				'type' => 'button',
				'role' => 'tab',
				'aria-controls' => "nav-{$tabId}",
			], $section['label']);
			$panes[] = '<div class="tab-pane fade' . ($first ? ' show active' : '') . '" id="nav-' . $tabId . '" role="tabpanel">' . $section['content'] . '</div>';
			$first = false;
		}

		return '<div class="site-item border rounded p-3 mb-3" data-site-id="' . esc_attr($id) . '">'
			. $header
			. '<nav><div class="nav nav-tabs" id="nav-site-' . $id . '-tab" role="tablist">' . implode('', $navItems) . '</div></nav>'
			. '<div class="tab-content" id="nav-site-' . $id . '-tab-content">' . implode('', $panes) . '</div>'
			. '</div>';
	}

	private static function _site_info_tab($id, string $name, array $site): string
	{
		$airportKey = $site['airport'] ?? 'orly';
		$airport = Terminal::fromKey($airportKey);
		$terminalOptions = Terminal::forAirport($airport);
		$type = $site['type'] ?? [];
		$vehicle_types = $site['vehicle_types'] ?? [];

		$contents = [];
		$contents[] = '<div class="row">';
		$contents[] = '<div class="col-sm-3">';
		// admin.js's "Load from DB" handler reads this site's airport via `.find('.airport-select').val()`,
		// which only works if the <select> itself carries the class - _field_select() otherwise puts
		// $div_class on the wrapping <div>, leaving the <select> classless (unlike newSiteMarkup()'s
		// JS-generated template, which already puts `class="airport-select"` directly on its <select>).
		// That mismatch made .val() resolve to undefined for every existing (server-rendered) site,
		// silently no-op'ing the click handler's `if (!airport) return;` guard.
		$contents[] = self::_field_select("site-$id-airport", 'location_field', "{$name}[airport]", esc_html__('Airport', 'parking-management'), Terminal::airportOptions(), $airportKey, 'airport-select');
		$contents[] = '</div>';
		$contents[] = '<div class="col-sm-3 terminal-container">';
		$contents[] = self::_field_select("site-$id-terminal", 'location_field', "{$name}[terminal]", esc_html__('Terminal', 'parking-management'), $terminalOptions, $site['terminal'] ?? ($terminalOptions[0]['value'] ?? ''));
		$contents[] = '</div>';
		$contents[] = '<div class="col-sm-3">';
		$contents[] = self::_field_checkbox("site-$id-vehicle-type", 'location_field', "{$name}[vehicle_types]", esc_html__('Vehicle types', 'parking-management'), [
			'car' => $vehicle_types['car'] ?? '0',
			'motorcycle' => $vehicle_types['motorcycle'] ?? '0',
			'truck' => $vehicle_types['truck'] ?? '0',
		]);
		$contents[] = '</div>';
		$contents[] = '<div class="col-sm-3">';
		// Separate from "Airport" above: more than one legally distinct parking business can
		// share a physical airport (e.g. Orly also hosts "CoverPark"), so the airport alone
		// isn't enough to resolve which legacy tbl_remplissage/tbl_commande row is this site's
		// own - see Order::resolveLegacySiteID(). Defaults to the airport's own legacy id so
		// existing single-business-per-airport sites behave exactly as before.
		$contents[] = self::_field_select("site-$id-legacy-site", 'location_field', "{$name}[legacy_site_id]", esc_html__('Legacy site (DB)', 'parking-management'), Terminal::legacySiteOptions(), $site['legacy_site_id'] ?? (string)$airport->value, 'legacy-site-select');
		$contents[] = '</div>';
		$contents[] = '</div>';
		$contents[] = '<div class="row">';
		$contents[] = '<div class="col-sm-4">';
		$contents[] = self::_field_checkbox("site-$id-type", 'location_field', "{$name}[type]", esc_html__('Storage type (valet is an optional extra type)', 'parking-management'), [
			'ext' => $type['ext'] ?? '0',
			'int' => $type['int'] ?? '0',
			'valet' => $type['valet'] ?? '0',
		]);
		$contents[] = '</div>';
		$contents[] = '</div>';

		$info = $site['info'] ?? [];
		$companyFields = [];
		$companyFields[] = '<div class="row">';
		$companyFields[] = '<div class="col-sm-6">';
		$companyFields[] = self::_field("site-$id-address", 'location_field', "{$name}[info][address]", esc_html__('Address', 'parking-management'), $info['address'] ?? '');
		$companyFields[] = '</div>';
		$companyFields[] = '<div class="col-sm-3">';
		$companyFields[] = self::_field("site-$id-mobile", 'location_field', "{$name}[info][mobile]", esc_html__('Mobile', 'parking-management'), $info['mobile'] ?? '');
		$companyFields[] = '</div>';
		$companyFields[] = '<div class="col-sm-3">';
		$companyFields[] = self::_field("site-$id-email", 'location_field', "{$name}[info][email]", esc_html__('Email', 'parking-management'), $info['email'] ?? '');
		$companyFields[] = '</div>';
		$companyFields[] = '</div>';
		$companyFields[] = '<div class="row">';
		$companyFields[] = '<div class="col-sm-4">';
		$companyFields[] = self::_field("site-$id-rcs", 'location_field', "{$name}[info][RCS]", esc_html__('RCS', 'parking-management'), $info['RCS'] ?? '');
		$companyFields[] = '</div>';
		$companyFields[] = '<div class="col-sm-4">';
		$companyFields[] = self::_field("site-$id-tva", 'location_field', "{$name}[tva]", esc_html__('TVA (%)', 'parking-management'), $site['tva'] ?? 20);
		$companyFields[] = '</div>';
		$companyFields[] = '<div class="col-sm-4">';
		$companyFields[] = self::_field("site-$id-tva-transport", 'location_field', "{$name}[tva_transport]", esc_html__('TVA transport (%)', 'parking-management'), $site['tva_transport'] ?? 10);
		$companyFields[] = '</div>';
		$companyFields[] = '</div>';
		$contents[] = self::_fieldset(esc_html__('Company (this site\'s legal identity)', 'parking-management'), ...$companyFields);

		return implode(PHP_EOL, $contents);
	}

	private static function _site_tariffs_tab(string $name, array $site): string
	{
		$tariffs = $site['tariffs'] ?? [];
		$contents = [];
		$contents[] = '<p class="description">' . esc_html__('This is the price grid served by this site\'s own GET /wp-json/pkmgmt/v1/prices response - the booking form calls that endpoint to compute the total shown to the customer. Valet is only ever offered for cars.', 'parking-management') . '</p>';
		$contents[] = '<div class="price-grid-io mb-3">';
		$contents[] = '<button type="button" class="button price-export">' . esc_html__('Export CSV', 'parking-management') . '</button> ';
		$contents[] = '<button type="button" class="button price-import-trigger">' . esc_html__('Import CSV', 'parking-management') . '</button> ';
		$contents[] = '<button type="button" class="button price-load-db">' . esc_html__('Load from DB', 'parking-management') . '</button>';
		$contents[] = '<input type="file" accept=".csv,text/csv" class="price-import-file" style="display:none">';
		$contents[] = '</div>';
		foreach (['car', 'motorcycle', 'truck'] as $vehicle) {
			$parkingKeys = $vehicle === 'car' ? ['ext', 'int', 'valet'] : ['ext', 'int'];
			foreach ($parkingKeys as $parkingKey) {
				$vehicleTariff = $tariffs[$vehicle][$parkingKey] ?? [];
				$contents[] = '<div class="tariff-vehicle mb-2" data-vehicle="' . esc_attr($vehicle) . '" data-parking-type="' . esc_attr($parkingKey) . '">';
				$contents[] = '<strong>' . esc_html(ucfirst($vehicle)) . ' - ' . esc_html(strtoupper($parkingKey)) . '</strong>';
				$contents[] = '<div class="row g-2 align-items-end">';
				for ($day = 1; $day <= 10; $day++) {
					$contents[] = '<div class="col-auto">';
					$contents[] = '<label class="form-label small mb-0">' . sprintf(esc_html__('%d day(s)', 'parking-management'), $day) . '</label>';
					$contents[] = '<input type="number" step="0.01" class="form-control form-control-sm" data-day="' . esc_attr($day) . '" name="' . esc_attr("{$name}[tariffs][{$vehicle}][{$parkingKey}][{$day}]") . '" value="' . esc_attr($vehicleTariff[$day] ?? '') . '">';
					$contents[] = '</div>';
				}
				$contents[] = '<div class="col-auto">';
				$contents[] = '<label class="form-label small mb-0">' . esc_html__('Extra day', 'parking-management') . '</label>';
				$contents[] = '<input type="number" step="0.01" class="form-control form-control-sm" data-day="jour_supplementaire" name="' . esc_attr("{$name}[tariffs][{$vehicle}][{$parkingKey}][jour_supplementaire]") . '" value="' . esc_attr($vehicleTariff['jour_supplementaire'] ?? '') . '">';
				$contents[] = '</div>';
				$contents[] = '</div>';
				$contents[] = '</div>';
			}
		}
		return implode(PHP_EOL, $contents);
	}

	private static function _site_form_tab(array $box, string $name, array $site): string
	{
		$form = $site['form'] ?? [];
		$options = $form['options'] ?? Template::form_options();
		if (isset($options['keep_keys'])) {
			$options['keep_keys']['label'] = 'I will keep my keys for %s €';
		}

		$contents = [];
		$contents[] = '<div class="row">';
		$contents[] = '<div class="col">';
		$contents[] = Html::_index('hidden', $box['id'] . '-booking-page-title', "{$name}[form][booking_page][title]", ['value' => $form['booking_page']['title'] ?? 'Booking Page']);
		$contents[] = self::_field_page($box['id'] . '-booking-page', 'form-control', "{$name}[form][booking_page][value]", esc_html__('Booking Page', 'parking-management'), $form['booking_page']['value'] ?? '');
		$contents[] = '</div>';
		$contents[] = '<div class="col">';
		$contents[] = Html::_index('hidden', $box['id'] . '-validation-page-title', "{$name}[form][validation_page][title]", ['value' => $form['validation_page']['title'] ?? 'Validation Page']);
		$contents[] = self::_field_page($box['id'] . '-validation-page', 'form-control', "{$name}[form][validation_page][value]", esc_html__('Validation Page', 'parking-management'), $form['validation_page']['value'] ?? '');
		$contents[] = '</div>';
		$contents[] = '</div>';

		$contents[] = self::_field($box['id'] . '-indicative', 'location_field', "{$name}[form][indicative]", esc_html__('Indicative', 'parking-management'), $form['indicative'] ?? '');

		$contents[] = self::_field_checkbox($box['id'] . '-form-toggles', 'location_field', "{$name}[form]", esc_html__('Options', 'parking-management'), [
			'terms_and_conditions' => $form['terms_and_conditions'] ?? '0',
			'dialog_confirmation' => $form['dialog_confirmation'] ?? '0',
		]);

		$contents[] = '<div class="row mt-3">';
		foreach ($options as $optId => $option) {
			$contents[] = self::_option_card("{$name}[form][options]", $optId, $option);
		}
		$contents[] = '</div>';
		return implode(PHP_EOL, $contents);
	}

	private static function _site_booked_dates_tab(string $name, array $site): string
	{
		$booked_dates = $site['booked_dates'] ?? [];
		$fieldPrefix = "{$name}[booked_dates]";
		$contents = [];
		$contents[] = '<div class="dates-header">';
		$contents[] = '<span>' . esc_html__('Add a date', 'parking-management') . '</span>';
		$contents[] = '<button class="site-dates-add" type="button" data-field="' . esc_attr($fieldPrefix) . '"><i class="fas fa-plus"></i></button>';
		$contents[] = '</div>';
		$contents[] = '<div class="dates-body site-dates-body">';
		foreach ($booked_dates as $dateId => $date) {
			if (is_array($date)) {
				$contents[] = self::_date_range_row($fieldPrefix, $dateId, $date);
			}
		}
		$contents[] = '</div>';
		return implode(PHP_EOL, $contents);
	}

	private static function _site_high_season_tab(string $name, array $site): string
	{
		$high_season = $site['high_season'] ?? ['price' => 0, 'dates' => []];
		$fieldPrefix = "{$name}[high_season][dates]";
		$contents = [];
		$contents[] = '<div class="dates-header justify-content-between">';
		$contents[] = '<div class="col-sm-2"><div class="input-group"><span class="input-group-text m-0">€</span>';
		$contents[] = '<input type="number" class="form-control" name="' . esc_attr("{$name}[high_season][price]") . '" aria-label="Price for High season" value="' . esc_attr($high_season['price'] ?? 0) . '"></div></div>';
		$contents[] = '<div>';
		$contents[] = '<span>' . esc_html__('Add a date', 'parking-management') . '</span>';
		$contents[] = '<button class="site-dates-add" type="button" data-field="' . esc_attr($fieldPrefix) . '"><i class="fas fa-plus"></i></button>';
		$contents[] = '</div>';
		$contents[] = '</div>';
		$contents[] = '<div class="dates-body site-dates-body">';
		foreach ($high_season['dates'] ?? [] as $dateId => $date) {
			if (is_array($date)) {
				$contents[] = self::_date_range_row($fieldPrefix, $dateId, $date);
			}
		}
		$contents[] = '</div>';
		return implode(PHP_EOL, $contents);
	}

	private static function _site_notification_tab(array $box, string $name, array $site): string
	{
		$notification = $site['notification'] ?? [];
		ob_start();
		self::mail($notification['mail'] ?? [], $box, "{$name}[notification][mail]");
		$mailHtml = ob_get_clean();
		ob_start();
		self::sms($notification['sms'] ?? [], $box, "{$name}[notification][sms]");
		$smsHtml = ob_get_clean();
		return '<h5>' . esc_html__('Mail server', 'parking-management') . '</h5>' . $mailHtml
			. '<h5 class="mt-3">' . esc_html__('SMS provider', 'parking-management') . '</h5>' . $smsHtml;
	}

	private static function _site_payment_tab(array $box, string $name, array $site): string
	{
		ob_start();
		self::payment($box['id'] . '-payment', $site['payment'] ?? [], "{$name}[payment]");
		return ob_get_clean();
	}

	private static function _site_templates_tab($id, string $name, array $site): string
	{
		ob_start();
		echo '<fieldset class="border border-dark p-3 position-relative my-3">';
		echo '<legend class="w-auto mx-2 bg-white position-absolute top-0 start-0 translate-middle-y fs-6">' . esc_html__('Mail', 'parking-management') . '</legend>';
		self::notification_template("site-$id-mail", "{$name}[mail_templates]", $site['mail_templates'] ?? []);
		echo '</fieldset>';
		echo '<fieldset class="border border-dark p-3 position-relative my-3">';
		echo '<legend class="w-auto mx-2 bg-white position-absolute top-0 start-0 translate-middle-y fs-6">' . esc_html__('SMS', 'parking-management') . '</legend>';
		self::notification_template("site-$id-sms", "{$name}[sms_template]", $site['sms_template'] ?? '');
		echo '</fieldset>';
		return ob_get_clean();
	}

	public static function dialog_page_list(): void
	{
		$contents = array();
		$contents[] = '<div id="dialog-pages-list" class="modal fade" tabindex="-1" aria-labelledby="pageList" aria-hidden="true">';
		$contents[] = '<div class="modal-dialog modal-lg modal-dialog-centered">';
		$contents[] = '<div class="modal-content">';
		$contents[] = '<div class="modal-header">';
		$contents[] = '<h1 class="modal-title fs-5" id="pageList">' . esc_html__("Select a page", 'parking-management') . '</h1>';
		$contents[] = '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
		$contents[] = '</div>';
		$contents[] = '<div class="modal-body">';
		$contents[] = '<ul id="pageList" class="list-group overflow-auto">';
		$pages = get_pages();
		foreach ($pages as $page) {
			$contents[] = '<li class="page list-group-item list-group-item-action" data-url="' . get_page_link($page->ID) . '">' . $page->post_title . '</li>';
		}

		$contents[] = '</ul>';
		$contents[] = '</div>';
		$contents[] = '</div>';
		$contents[] = '</div>';
		$contents[] = '</div>';
		echo implode(PHP_EOL, $contents);
	}

}
