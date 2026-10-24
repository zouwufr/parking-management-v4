<?php

namespace Booking;

use ParkingManagement\Html;
use ParkingManagement\Logger;
use ParkingManagement\ParkingManagement;

class Form
{
	private ParkingManagement $pm;

	private array $site;

	public function __construct(ParkingManagement $pm)
	{
		$this->pm = $pm;
		$this->site = Site::current() ?? [];
		$this->enqueue();
	}

	private static function _radio_parking_type_field($div_class, $id, $name, array $elements, $value): string
	{
		$contents = array();
		foreach ($elements as $element) {
			$contents[] = Html::_div(
				array(
					'class' => 'radio ' . $div_class,
				),
				Html::_radio($id . '_' . $element['id'], $name, $element['value'], array('class' => 'parking-type'), $value == $element['value']),
				Html::_label_with_attr(
					array(
						'class' => 'px-md-5 px-3 label form-label',
					),
					$id . '_' . $element['id'], $element['label']),
			);
		}

		return implode(PHP_EOL, $contents);
	}

	private static function _radio_type_field($div_class, $id, $name, array $elements, $value): string
	{
		$contents = array();
		foreach ($elements as $element) {
			$contents[] = Html::_div(
				array(
					'class' => 'radio ' . $div_class,
				),
				Html::_radio($id . '_' . $element['value'], $name, $element['value'], array('class' => 'type-id'), $value == $element['value']),
				Html::_label_with_attr(array(
					'class' => 'label px-3 px-md-5'
				), $id . '_' . $element['value'],

					$element['label']

				),
			);
		}

		return implode(PHP_EOL, $contents);
	}

	private static function _row_field($class, ...$contents): string
	{
		return Html::_div(
			array('class' => 'row mb-3'),
			Html::_div(array('class' => 'col ' . $class), ...$contents),
		);
	}

	/**
	 * Always renders all three - site restrictions are enforced client-side (JS
	 * toggles visibility per the currently selected site), since switching sites
	 * must be able to reveal options the page's initial/default site doesn't offer.
	 * Previously this filtered by $this->site['type'] at render time, which meant a
	 * site needing an option the default site didn't have (e.g. the default site has
	 * no valet) could never show it - the radio simply didn't exist in the DOM to
	 * toggle, so switching to that site left the selection stuck with nothing valid
	 * to fall back to.
	 */
	private function get_parking_type(): array
	{
		return array(
			array('id' => '1', 'label' => esc_html__('Outside', 'parking-management'), 'value' => '0'),
			array('id' => '2', 'label' => esc_html__('Inside', 'parking-management'), 'value' => '1'),
			array('id' => '3', 'label' => esc_html__('Valet', 'parking-management'), 'value' => '2'),
		);
	}

	/** Same reasoning as get_parking_type() above. */
	private function get_vehicle_type(): array
	{
		return array(
			array('id' => '1', 'label' => '<i class="fa fa-car fa-lg"></i>', 'value' => '1'),
			array('id' => '2', 'label' => '<i class="fa fa-motorcycle fa-lg"></i>', 'value' => '2'),
			array('id' => '3', 'label' => '<i class="fa fa-truck fa-lg"></i>', 'value' => '3'),
		);
	}

	/** First parking_type value Site::current() actually enables, for the initial checked radio. */
	private function defaultParkingTypeValue(): string
	{
		$type = $this->site['type'] ?? [];
		foreach (array('ext' => '0', 'int' => '1', 'valet' => '2') as $key => $value) {
			if (($type[$key] ?? '0') === '1') {
				return $value;
			}
		}
		return '0';
	}

	/** First vehicle type value Site::current() actually enables, for the initial checked radio. */
	private function defaultVehicleTypeValue(): string
	{
		$vehicle_type = $this->site['vehicle_types'] ?? [];
		foreach (array('car' => '1', 'motorcycle' => '2', 'truck' => '3') as $key => $value) {
			if (($vehicle_type[$key] ?? '0') === '1') {
				return $value;
			}
		}
		return '1';
	}


	private function get_terminal(): array
	{
		$airport = Terminal::fromKey($this->site['airport'] ?? 'orly');

		return array(
			'group' => array(
				Terminal::label($airport) => Terminal::forAirport($airport)
			)
		);
	}

	private static function _label($for, $contents): string
	{
		return Html::_label_with_attr(
			array(
				'class' => 'label form-label'
			),
			$for,
			$contents
		);
	}

	private function enqueue(): void
	{
		wp_enqueue_style('parking-management-booking', pkmgmt_plugin_url('modules/booking/css/form.css'), array(), PKMGMT_VERSION);
		wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), '6.0.0-beta3');
		wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', array(), '5.3.3');
		wp_enqueue_style('parking-management-easepick', 'https://cdn.jsdelivr.net/npm/@easepick/bundle@1.2.1/dist/index.css');
		wp_enqueue_style('parking-management-jquery-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.14.1/themes/base/jquery-ui.min.css');
		wp_enqueue_style('parking-management-intl-tel-input', 'https://cdn.jsdelivr.net/npm/intl-tel-input@23.1.0/build/css/intlTelInput.css');

		wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', array(), false, true);
		wp_enqueue_script('parking-management-jquery', 'https://code.jquery.com/jquery-3.7.1.min.js');
		wp_enqueue_script('parking-management-jquery-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.14.1/jquery-ui.min.js', array('parking-management-jquery'));
		wp_enqueue_script('parking-management-jquery-validate', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.min.js', array('parking-management-jquery'));
		wp_enqueue_script('parking-management-intl-tel-input', 'https://cdn.jsdelivr.net/npm/intl-tel-input@23.1.0/build/js/intlTelInput.min.js', array('parking-management-jquery'));
		wp_enqueue_script('parking-management-easepick', 'https://cdn.jsdelivr.net/npm/@easepick/bundle@1.2.1/dist/index.umd.min.js');
		wp_enqueue_script('parking-management-luxon', 'https://cdn.jsdelivr.net/npm/luxon/build/global/luxon.min.js');
		wp_enqueue_script(
			'parking-management-booking',
			pkmgmt_plugin_url('modules/booking/js/form.js'),
			array(
				'bootstrap',
				'parking-management-jquery',
				'parking-management-jquery-ui',
				'parking-management-jquery-validate',
				'parking-management-easepick',
				'parking-management-luxon',
				'parking-management-intl-tel-input',
			),
			PKMGMT_VERSION);

		$sites = array();
		foreach (Site::activeOnly() as $id => $site) {
			$sites[$id] = array(
				'name' => $site['name'] ?? '',
				'type' => $site['type'] ?? [],
				'airport' => $site['airport'] ?? 'orly',
				'terminal' => $site['terminal'] ?? '',
				'vehicle_types' => $site['vehicle_types'] ?? [],
				'terms_and_conditions' => $site['form']['terms_and_conditions'] ?? '0',
				'dialog_confirmation' => $site['form']['dialog_confirmation'] ?? '0',
				'options' => array_intersect_key(
					$site['form']['options'] ?? [],
					array_flip(array_merge(['keep_keys'], self::CHECKBOX_OPTION_KEYS))
				),
			);
		}
		$terminals = array();
		foreach (Airport::cases() as $airport) {
			$terminals[strtolower($airport->name)] = Terminal::forAirport($airport);
		}
		wp_localize_script('parking-management-booking',
			'external_object',
			array(
				'locale' => $this->pm->locale,
				'home_url' => home_url(),
				'site_id' => $this->site['id'] ?? '',
				'form_css' => pkmgmt_plugin_url('modules/booking/css/form.css'),
				'indicative' => $this->site['form']['indicative'] ?? '',
				'sites' => $sites,
				'terminals' => $terminals,
			)
		);

	}

	/** Identity fields - Step 3 ("Your Details"). */
	private function identity_fields(): array
	{
		$post = array_merge($_GET, $_POST);

		return [
			self::_row_field('name',
				self::_label('nom', esc_html__('Name', 'parking-management')),
				Html::_index('text', 'nom', 'nom',
					array(
						'class' => 'name regular required col-5 border rounded py-2 px-3 form-control',
						'value' => $post['nom'] ?? '',
						'autofocus' => 'autofocus',
					)
				),
			),
			self::_row_field('firstname',
				self::_label('prenom', esc_html__('Firstname', 'parking-management')),
				Html::_index('text', 'prenom', 'prenom',
					array(
						'class' => 'firstname regular required col-5 border rounded py-2 px-3 form-control',
						'value' => $post['prenom'] ?? '',
					)
				),
			),
			self::_row_field('zip-code',
				self::_label('code_postal', esc_html__('Zip code', 'parking-management')),
				Html::_index('text', 'code_postal', 'code_postal',
					array(
						'class' => 'zip-code regular col-5 border rounded py-2 px-3 form-control',
						'autocomplete' => 'off',
						'value' => $post['code_postal'] ?? '',
					)
				),
				Html::_index('text', 'ville', 'ville',
					array(
						'class' => 'ville regular',
						'value' => $post['ville'] ?? '',
						'tabindex' => "-1",
					)
				),
				Html::_index('text', 'pays', 'pays',
					array(
						'class' => 'pays regular',
						'value' => $post['pays'] ?? '',
						'tabindex' => "-1",
					)
				),
			),
			self::_row_field('mobile input-group align-items-center',
				Html::_index('hidden', 'tel_port', 'tel_port', [
					'value' => $post['tel_port'] ?? '',
				]),
				Html::_label_with_attr(
					array('class' => 'mobile col-sm-3 label form-label'),
					'mobile',
					esc_html__('Mobile phone', 'parking-management')
				),
				Html::_index('tel', 'mobile', 'mobile',
					array(
						'class' => 'mobile regular required col-5 border rounded py-2 form-control',
						'value' => $post['tel_port'] ?? '',
					)
				),
			),
			self::_row_field('email',
				self::_label('email', esc_html__('Email', 'parking-management')),
				Html::_index('email', 'email', 'email',
					array(
						'class' => 'email regular required col-5 border rounded py-2 px-3 form-control',
						'value' => $post['email'] ?? '',
					)
				),
			),
		];
	}

	/** Vehicle-type radios - Step 2 ("Vehicle & Parking"). */
	private function vehicle_type_field(): string
	{
		$post = array_merge($_GET, $_POST);
		$vehicle_type = $this->get_vehicle_type();

		return self::_row_field('type-id input-group align-items-center',
			Html::_label_with_attr(
				array('class' => 'type_id col-sm-3 form form-label'),
				'type_id',
				esc_html__('Type of vehicle', 'parking-management')
			),
			Html::_div(
				array(
					'class' => 'row col col-sm-5 col-md-8 gx-sm-4 gx-md-5 justify-content-around',
				),
				self::_radio_type_field(
					'col col-sm d-flex justify-content-around p-0',
					'type_id',
					'type_id',
					$vehicle_type,
					($post['type_id'] ?? $this->defaultVehicleTypeValue()))
			)
		);
	}

	/**
	 * Always rendered (not gated on this site's enabled/price/label) so switching to a
	 * site with a different keep_keys config has an element to show/hide/relabel - same
	 * reasoning as get_parking_type()/get_vehicle_type() above. Step 2 ("Vehicle & Parking").
	 */
	private function keep_keys_field(): string
	{
		$post = array_merge($_GET, $_POST);
		$form = $this->site['form'] ?? [];
		$keep_keys = $form['options']['keep_keys'] ?? [];
		$keep_keys_label = $keep_keys['label'] ?? esc_html__('I will keep my keys for %s €', 'parking-management');

		return Html::_div(
			array('class' => 'option-wrapper', 'data-option' => 'keep_keys'),
			Html::_switch(
				'keep_keys',
				'keep_keys', [],
				sprintf(esc_html__($keep_keys_label, 'parking-management'), $keep_keys['price'] ?? 0),
				array_key_exists('keep_keys', $post) ? $post['keep_keys'] : ($keep_keys['checked'] ?? '0'))
		);
	}

	/** Car model/plate fields - Step 2 ("Vehicle & Parking"). */
	private function car_details_fields(): array
	{
		$post = array_merge($_GET, $_POST);

		return [
			Html::_p([
				'class' => 'h5 black',
			], esc_html__('We only accommodate passenger vehicles such as compact cars, sedans, SUVs, and 4x4s.', 'parking-management')),
			self::_row_field('modele',
				self::_label('modele', esc_html__('Car model', 'parking-management')),
				Html::_index('text', 'marque', 'marque',
					array(
						'class' => 'marque regular',
						'value' => $post['marque'] ?? '',
						'tabindex' => "-1",
					)
				),
				Html::_index('text', 'modele', 'modele',
					array(
						'class' => 'modele regular required col-5 border rounded py-2 px-3 form-control',
						'value' => $post['modele'] ?? '',
					)
				)
			),
			self::_row_field('immatriculation',
				self::_label('immatriculation', esc_html__('Immatriculation', 'parking-management')),
				Html::_index('text', 'immatriculation', 'immatriculation',
					array(
						'class' => 'immatriculation regular required col-5 border rounded py-2 px-3 form-control',
						'value' => $post['immatriculation'] ?? '',
					)
				)
			),
		];
	}

	/** Car-park (ext/int/valet) radios - Step 2 ("Vehicle & Parking"). */
	private function parking_type_field(): string
	{
		$post = array_merge($_GET, $_POST);
		$parking_type = $this->get_parking_type();

		return self::_row_field('parking_type input-group align-items-center',
			Html::_label_with_attr(
				array('class' => 'parking_type col-sm-3 form form-label'),
				'parking_type',
				esc_html__('Car Park', 'parking-management')
			),
			Html::_div(
				array(
					'class' => 'col col-sm-5 col-md-8 row justify-content-around',
				),
				self::_radio_parking_type_field(
					'col-sm d-flex justify-content-around',
					'parking_type',
					'parking_type',
					$parking_type,
					($post['parking_type'] ?? $this->defaultParkingTypeValue()))
			)
		);
	}

	/** Wraps a step's fields in the shared step fieldset shell + Prev/Next nav. */
	private function step_fieldset(int $n, string $title, ...$fields): string
	{
		$fields[] = $this->step_nav_buttons($n);
		return Html::_fieldset_with_attr(
			array(
				'class' => 'pkmgmt-step' . ($n > 1 ? ' pkmgmt-step-hidden' : ''),
				'id' => 'pkmgmt-step-' . $n,
				'data-step' => (string)$n,
				'aria-hidden' => $n > 1 ? 'true' : 'false',
			),
			'<legend id="pkmgmt-step-' . $n . '-heading" tabindex="-1">' . $title . '</legend>',
			...$fields,
		);
	}

	private function step_nav_buttons(int $n): string
	{
		return Html::_div(
			array('class' => 'pkmgmt-step-actions'),
			$n > 1 ? '<button type="button" class="btn btn-outline-secondary pkmgmt-step-prev" data-step-prev>' . esc_html__('Back', 'parking-management') . '</button>' : '',
			// Never blocked on per-step field completeness - the final "Pay and validate"
			// submit is what actually enforces required fields (see the invalidHandler on
			// $("#reservation").validate({...}) in form.js), jumping back to whichever step
			// holds the first invalid field if any are missing.
			$n < 3 ? '<button type="button" class="btn btn-primary pkmgmt-step-next" data-step-next>' . esc_html__('Next', 'parking-management') . ' <i class="fa-regular fa-circle-right"></i></button>' : '',
		);
	}

	/** Step progress indicator + screen-reader announcer, rendered once before the steps. */
	public function progress_indicator(int $total = 3): string
	{
		$labels = [
			1 => esc_html__('Location & Dates', 'parking-management'),
			2 => esc_html__('Vehicle & Parking', 'parking-management'),
			3 => esc_html__('Your Details', 'parking-management'),
		];
		$items = [];
		for ($n = 1; $n <= $total; $n++) {
			$items[] = Html::_li(
				array(
					'class' => 'pkmgmt-progress-step',
					'data-step' => (string)$n,
					'aria-current' => $n === 1 ? 'step' : 'false',
				),
				'<span class="pkmgmt-progress-number">' . $n . '</span>',
				'<span class="pkmgmt-progress-label">' . ($labels[$n] ?? '') . '</span>'
			);
		}
		return Html::_nav(
			array('class' => 'pkmgmt-progress', 'aria-label' => esc_html__('Booking steps', 'parking-management')),
			Html::_ul(array(), ...$items),
			Html::_span(array('class' => 'visually-hidden', 'aria-live' => 'polite', 'id' => 'pkmgmt-step-announcer'), '')
		);
	}

	public function step_vehicle_and_parking(): string
	{
		$content = array_merge(
			[$this->vehicle_type_field(), $this->parking_type_field()],
			$this->car_details_fields(),
			[$this->keep_keys_field(), $this->options()],
		);
		return $this->step_fieldset(2, esc_html__('Vehicle & Parking', 'parking-management'), ...$content);
	}

	public function step_your_details(): string
	{
		return $this->step_fieldset(3, esc_html__('Your Details', 'parking-management'),
			...$this->identity_fields(),
		);
	}

	private function pax(): string
	{
		$post = array_merge($_GET, $_POST);
		return self::_row_field('nb_pax',
			self::_label('nb_pax', esc_html__('Number of pax', 'parking-management')),
			Html::_select('nb_pax', 'nb_pax', array(
				'class' => 'required border col-5 rounded py-2 px-3 form-select',
			),
				array(
					array(
						'value' => '',
						'label' => '-'
					),
					array(
						'value' => '1',
						'label' => '1'
					),
					array(
						'value' => '2',
						'label' => '2'
					),
					array(
						'value' => '3',
						'label' => '3'
					),
					array(
						'value' => '4',
						'label' => '4'
					),
					array(
						'value' => '5',
						'label' => '5  (+7€)'
					),
					array(
						'value' => '6',
						'label' => '6 (+14€)'
					),
					array(
						'value' => '7',
						'label' => '7 (+21€)'
					),
					array(
						'value' => '8',
						'label' => '8 (+28€)'
					),
					array(
						'value' => '9',
						'label' => '9 (+35€)'
					),
					array(
						'value' => '10',
						'label' => '10 (+42€)'
					),
				),
				array_key_exists('nb_pax', $post) ? $post['nb_pax'] : ''
			),
		);
	}

	private function get_sites(): array
	{
		$options = array();
		foreach (Site::activeOnly() as $id => $site) {
			$options[] = array(
				'value' => (string)$id,
				'label' => $site['name'] ?? '',
			);
		}
		return $options;
	}

	private function site_field(): array
	{
		$sites = $this->get_sites();
		if (empty($sites)) {
			return array();
		}
		$post = array_merge($_GET, $_POST);
		return [
			self::_row_field('site_id',
				self::_label('site_id', esc_html__('Location', 'parking-management')),
				Html::_select('site_id', 'site_id',
					array(
						'class' => 'site-select required border rounded py-2 px-3 form-select',
					),
					$sites,
					$post['site_id'] ?? $sites[0]['value']
				)
			),
		];
	}

	private function common_trip(): array
	{
		$post = array_merge($_GET, $_POST);
		return [
			...$this->site_field(),
			self::_row_field('destination',
				self::_label('destination', esc_html__('Destination', 'parking-management')),
				Html::_index('text', 'destination', 'destination',
					array(
						'class' => 'destination regular border rounded py-2 form-control',
						'value' => $post['destination'] ?? '',
					)
				),
				Html::_index('text', 'destination_id', 'destination_id',
					array(
						'class' => 'destination_id regular',
						'value' => $post['destination_id'] ?? '',
						'tabindex' => "-1",
					)
				),
			),
			self::_row_field('',
				Html::_div(
					array('class' => 'row border mx-2 pb-3 pkmgmt-trip-box'),
					Html::_div(
						array(
							'class' => 'row d-none d-md-flex',
						),
						Html::_div(
							array(
								'class' => 'col',
							),
							'<h1 class="title">' . esc_html__('Departure', 'parking-management') . '</h1>',
						),
						Html::_div(
							array(
								'class' => 'col',
							),
							'<h1 class="title">' . esc_html__('Return', 'parking-management') . '</h1>',
						),
					),
					Html::_div(
						array('class' => 'row pkmgmt-terminal-row'),
						Html::_div(
							array('class' => 'col',),
							Html::_label_with_attr(
								array('class' => 'form-label'),
								'terminal_depart',
								esc_html__('Terminal departure', 'parking-management')
							),
							Html::_select('terminal_depart', 'terminal[depart]',
								array(
									'class' => 'border form-select py-2',
								),
								$this->get_terminal(),
								(array_key_exists('terminal', $post) && $post['terminal']['depart'] ?? '')),

						),
						Html::_div(
							array('class' => 'col'),
							Html::_label_with_attr(
								array('class' => 'form-label'),
								'terminal_arrivee',
								esc_html__('Terminal return', 'parking-management')
							),
							Html::_select('terminal_arrivee', 'terminal[arrivee]',
								array(
									'class' => 'border form-select py-2',
								),
								$this->get_terminal(),
								(array_key_exists('terminal', $post) && $post['terminal']['arrivee'] ?? '')),
						),
					),
					Html::_div(
						array('class' => 'row',),
						Html::_div(
							array('class' => 'col ',),
							Html::_div(
								['class' => 'd-flex flex-column align-items-center mt-1'],

								Html::_label_with_attr(
									array('class' => 'form-label'),
									'depart',
									esc_html__('Date and hour', 'parking-management')
								),
								Html::_div(
									['class' => 'input-group journey'],

									Html::_index('text', 'depart', 'depart', array(
										'class' => 'departure regular required form-control border py-2',
										'autocomplete' => 'off',
										'value' => $post['depart'] ?? '',
									)),
									Html::_span(
										['class' => 'input-group-text hyphen border-right-0 border-left-0 border-top border-bottom'],
										'-'
									),
									Html::_index('text', 'retour', 'retour', array(
										'class' => 'return regular required form-control border py-2',
										'autocomplete' => 'off',
										'tabindex' => "-1",
										'value' => $post['retour'] ?? '',
									)),

								),
							),
						),
					),
				),
			),
		];
	}

	public function step_location_and_dates(): string
	{
		$content = $this->common_trip();
		$content[] = $this->pax();
		return $this->step_fieldset(1, esc_html__('Location & Dates', 'parking-management'),
			...$content,
		);
	}

	/**
	 * Always rendered (like options()'s opt-in checkboxes) regardless of whether the
	 * *default* site requires it - terms_and_conditions is a per-site flag, and the
	 * visitor can switch sites client-side via the Step 1 selector. Rendering it only
	 * for whichever site happened to be current at page-load time meant enabling it on
	 * a non-default site had no visible effect: the checkbox simply didn't exist in the
	 * DOM for form.js's refreshTermsAndConditions() (called from applySite()) to show.
	 * The `required` class/checked-state below still reflect the current site so the
	 * first paint (and no-JS fallback) is already correct.
	 */
	public function cgv(): string
	{
		$form = $this->site['form'] ?? [];
		$required = ($form['terms_and_conditions'] ?? '0') === '1';
		$post = array_merge($_GET, $_POST);
		$warning_msg = "Merci de valider les conditions générales de vente";
		$msg = <<<EOT
En cochant cette case (obligatoire), j'accèpte les <a href="/cgv" target="_blank">conditions générales de vente</a>, atteste la validation de commande et mon obligation de paiement, <strong>en vertu de l'article L.121-19-3</strong>
EOT;

		return Html::_div(
			array('class' => 'cgv form-check', 'id' => 'pkmgmt-cgv'),
			Html::_index('hidden', '', 'cgv_reservation', array('value' => '0')),
			Html::_index(
				'checkbox',
				'cgv_reservation',
				'cgv_reservation',
				array(
					'class' => 'cgv_reservation form-check-input' . ($required ? ' required' : ''),
					'value' => "1",
					'data-msg-required' => $warning_msg,
				),
				false,
				false,
				$required && (array_key_exists('cgv_reservation', $post) ? $post['cgv_reservation'] : '0') == '1'
			),
			Html::_label_with_attr(array('class' => 'form-check-label black'), 'cgv_reservation', $msg),
		);
	}

	public function total(): string
	{
		$post = array_merge($_GET, $_POST);
		return Html::_div(
			array(),
			Html::_index('hidden', 'total_amount', 'total_amount',
				array('value' => $post['total_amount'] ?? '0')
			),
			Html::_div(
				array(
					'class' => 'total form-control',
					'id' => 'total',
				),
				esc_html__('Order amount', 'parking-management'),
				'<span>0€</span>'
			)
		);
	}

	public function submit(): string
	{
		return Html::_div(
			array(
				'class' => 'mt-4 row justify-content-md-center',
			),
			Html::_div(array(
				'class' => 'col-sm-4',
			),
				Html::_index('hidden', 'aeroport', 'aeroport', array('value' => Order::getSiteID($this->site['airport'] ?? 'orly')->value)),
				Html::_index('hidden', 'pkmgmt_action', 'pkmgmt_action', array('value' => 'booking')),
				'<button type="submit" id="submit" name="submit" class="form-control btn btn-primary text-center" disabled>'
				. esc_html__('Pay and validate', 'parking-management')
				. ' <i class="fa-regular fa-circle-right"></i>'
				. '</button>'

			),

		);
	}

	/**
	 * Always rendered, same reasoning as cgv()/options() - dialog_confirmation is a
	 * per-site flag and the visitor can switch sites client-side via the Step 1 selector,
	 * so gating this on whichever site was current at page-load time meant enabling it on
	 * a non-default site had no visible effect (the modal simply didn't exist in the DOM
	 * for form.js to open). jQuery UI's dialog() is initialized with autoOpen:false
	 * regardless, so always rendering/initializing it is harmless either way.
	 */
	public function dialog_booking_confirmation(): string
	{
		$post = array_merge($_GET, $_POST);
		return '<div id="dialog_booking_confirmation" title="' . esc_html__('Confirmation', 'parking-management') . '">
	<p class="pkmgmt-dialog-intro">' . esc_html__('Please double-check your dates and email before paying.', 'parking-management') . '</p>
	<form id="confirmation" name="confirmation" method="post" action="">
		<div class="row g-3">
			<div class="col-12">
				<label class="form-label" for="depart2">' . esc_html__('Dropping off at', 'parking-management') . '</label>
				<input tabindex="19" type="text" id="depart2" name="depart" class="departure regular required border rounded form-control py-2" autocomplete="off" value="' . ($post['depart'] ?? '') . '">
			</div>
			<div class="col-12">
				<label class="form-label" for="retour2">' . esc_html__('Landing at the airport', 'parking-management') . '</label>
				<input tabindex="-1" type="text" id="retour2" name="retour" class="return regular required border rounded form-control py-2" autocomplete="off" value="' . ($post['retour'] ?? '') . '">
			</div>
			<div class="col-12">
				<label class="label form-label" for="email2">' . esc_html__('Email', 'parking-management') . '</label>
				<input tabindex="18" type="email" id="email2" name="email" class="email regular required border rounded py-2 px-3 form-control" value="' . ($post['email'] ?? '') . '">
			</div>
		</div>
		<div class="pkmgmt-dialog-actions">
			<button tabindex="20" id="submit2" class="btn btn-primary w-100" name="submit2" type="submit">
				' . esc_html__('Confirm your order', 'parking-management') . '
				<i class="fa-regular fa-circle-right"></i>
			</button>
		</div>
	</form>
</div>';
	}

	// The other Template::form_options() keys (night_extra_charge, shuttle, late,
	// holiday, forgetting, extra_baggage, oversize_baggage) apply automatically in
	// Price::getPrice() based on date/time/pax, not an opt-in checkbox here.
	private const CHECKBOX_OPTION_KEYS = ['ev_charging', 'cancellation_insurance'];

	public function options(): string
	{
		// Always rendered (not gated on this site's enabled/price) so switching to a site
		// with a different config has an element to show/hide/relabel client-side - same
		// reasoning as get_parking_type()/get_vehicle_type() and keep_keys above.
		$form = $this->site['form'] ?? [];

		$post = array_merge($_GET, $_POST);
		$contents = [];
		foreach (self::CHECKBOX_OPTION_KEYS as $key) {
			$value = $form['options'][$key] ?? [];
			$msg = sprintf(esc_html__($value['label'] ?? '%s €', 'parking-management'), $value['price'] ?? 0);
			$contents[] = Html::_div(
				array('class' => 'options-container form-check', 'data-option' => $key),
				Html::_index('hidden', '', $key, array('value' => '0')),
				Html::_index(
					'checkbox',
					$key,
					$key,
					[
						'class' => 'options-checkbox form-check-input',
						'value' => "1",
					],
					false,
					false,
					(array_key_exists($key, $post) ? $post[$key] : ($value['checked'] ?? '0')) == '1'
				),
				Html::_label_with_attr(array('class' => 'form-check-label black'), $key, $msg)
			);
		}
		return join('', $contents);
	}

	public function spinner(): string
	{
		return Html::_div(array('class' => 'justify-content-center spinner-container', 'id' => 'spinner-container'),
			Html::_div(array('class' => 'spinner-border text-primary', 'role' => 'status'),
				Html::_span(array('class' => 'sr-only'),
					__('Loading...', 'parking-management')
				),
			)
		);
	}

}
