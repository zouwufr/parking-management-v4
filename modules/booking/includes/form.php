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
				Html::_radio($id . '_' . $element['id'], $name, $element['value'], array('class' => 'parking-type', 'tabindex' => "9"), $value == $element['value']),
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
				Html::_radio($id . '_' . $element['value'], $name, $element['value'], array('class' => 'type-id', 'tabindex' => "6",), $value == $element['value']),
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

	private function get_parking_type(): array
	{
		$types = array();
		$type = $this->site['type'] ?? [];
		if (($type['ext'] ?? '0') === '1')
			$types[] = array(
				'id' => '1',
				'label' => esc_html__('Outside', 'parking-management'),
				'value' => '0'
			);
		if (($type['int'] ?? '0') === '1')
			$types[] = array(
				'id' => '2',
				'label' => esc_html__('Inside', 'parking-management'),
				'value' => '1'
			);
		if (($type['valet'] ?? '0') === '1')
			$types[] = array(
				'id' => '3',
				'label' => esc_html__('Valet', 'parking-management'),
				'value' => '2'
			);
		return $types;
	}

	private function get_vehicle_type(): array
	{
		$types = array();
		$vehicle_type = $this->site['vehicle_types'] ?? [];
		if (($vehicle_type['car'] ?? '0') === '1')
			$types[] = array(
				'id' => '1',
				'label' => '<i class="fa fa-car fa-lg"></i>',
				'value' => '1'
			);
		if (($vehicle_type['motorcycle'] ?? '0') === '1')
			$types[] = array(
				'id' => '2',
				'label' => '<i class="fa fa-motorcycle fa-lg"></i>',
				'value' => '2'
			);
		if (($vehicle_type['truck'] ?? '0') === '1')
			$types[] = array(
				'id' => '3',
				'label' => '<i class="fa fa-truck fa-lg"></i>',
				'value' => '3'
			);
		return $types;
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
		wp_enqueue_style('parking-management-booking', pkmgmt_plugin_url('modules/booking/css/form.css'));
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
				'form_options' => $this->site['form']['options'] ?? [],
				'sites' => $sites,
				'terminals' => $terminals,
			)
		);

	}

	public function common_personal_information(ParkingManagement $pm): array
	{
		$post = array_merge($_GET, $_POST);
		$vehicle_type = $this->get_vehicle_type();

		$content = [
			self::_row_field('name',
				self::_label('nom', esc_html__('Name', 'parking-management')),
				Html::_index('text', 'nom', 'nom',
					array(
						'class' => 'name regular required col-5 border rounded py-2 px-3 form-control',
						'value' => $post['nom'] ?? '',
						'tabindex' => "1",
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
						'tabindex' => "2",
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
						'tabindex' => "3",
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
						'tabindex' => "4",
					)
				),
			),
			self::_row_field('email',
				self::_label('email', esc_html__('Email', 'parking-management')),
				Html::_index('email', 'email', 'email',
					array(
						'class' => 'email regular required col-5 border rounded py-2 px-3 form-control',
						'value' => $post['email'] ?? '',
						'tabindex' => "5",
					)
				),
			),
		];
		if (count($vehicle_type) == 1)
			$content[] = Html::_index('hidden', 'type_id', 'type_id', ['value' => $vehicle_type[0]['value']]);
		$content[] = self::_row_field('type-id input-group align-items-center',
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
					($post['type_id'] ?? ($vehicle_type[0]['value'] ?? '')))
			)
		);
		$form = $this->site['form'] ?? [];
		if (($form['options']['keep_keys']['enabled'] ?? '0') === '1' && $form['options']['keep_keys']['price'] !== '0' && !empty($form['options']['keep_keys']['label'])) {
			$msg = sprintf(esc_html__($form['options']['keep_keys']['label'], 'parking-management'), $form['options']['keep_keys']['price']);
			$content[] = Html::_switch(
				'keep_keys',
				'keep_keys', [],
				$msg,
				array_key_exists('keep_keys', $post) ? $post['keep_keys']: $form['options']['keep_keys']['checked']);
		}
		return array_merge($content,
			[
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
							'tabindex' => "7",
						)
					)
				),
				self::_row_field('immatriculation',
					self::_label('immatriculation', esc_html__('Immatriculation', 'parking-management')),
					Html::_index('text', 'immatriculation', 'immatriculation',
						array(
							'class' => 'immatriculation regular required col-5 border rounded py-2 px-3 form-control',
							'value' => $post['immatriculation'] ?? '',
							'tabindex' => "8",
						)
					)
				)
			]);
	}

	public function personal_information(): string
	{
		$post = array_merge($_GET, $_POST);
		$parking_type = $this->get_parking_type();
		$content = $this->common_personal_information($this->pm);
		$content[] = self::_row_field('parking_type input-group align-items-center',
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
					($post['parking_type'] ?? ($parking_type[0]['value'] ?? '')))
			)
		);
		return Html::_div(
			array('class' => 'personal-information col-12'),
			Html::_fieldset(
				'<legend>' . __('Personal Information', 'parking-management') . '</legend>',
				...$content,
			)
		);
	}

	private function pax(): string
	{
		$post = array_merge($_GET, $_POST);
		return self::_row_field('nb_pax',
			self::_label('nb_pax', esc_html__('Number of pax', 'parking-management')),
			Html::_select('nb_pax', 'nb_pax', array(
				'class' => 'required border col-5 rounded py-2 px-3 form-select',
				'tabindex' => "14",
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
				self::_label('site_id', esc_html__('Parking site', 'parking-management')),
				Html::_select('site_id', 'site_id',
					array(
						'class' => 'site-select required border rounded py-2 px-3 form-select',
						'tabindex' => "9",
					),
					$sites,
					$post['site_id'] ?? $sites[0]['value']
				)
			),
		];
	}

	private function common_trip(ParkingManagement $pm): array
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
						'tabindex' => "10",
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
					array('class' => 'row border mx-2 pb-3'),
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
						array('class' => 'row'),
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
									'tabindex' => "11",
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
									'tabindex' => "13",
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
										'tabindex' => "12",
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

	public function trip_information(): string
	{
		$content = $this->common_trip($this->pm);
		$content[] = $this->pax();
		return Html::_div(
			array('class' => 'trip_information'),
			Html::_fieldset(
				'<legend>' . __('Trip Information', 'parking-management') . '</legend>',

				...$content,
			),
		);
	}

	public function cgv(): string
	{
		$form = $this->site['form'] ?? [];
		if (($form['terms_and_conditions'] ?? '0') !== '1')
			return '';
		$post = array_merge($_GET, $_POST);
		$warning_msg = "Merci de valider les conditions générales de vente";
		$msg = <<<EOT
En cochant cette case (obligatoire), j'accèpte les <a href="/cgv" target="_blank">conditions générales de vente</a>, atteste la validation de commande et mon obligation de paiement, <strong>en vertu de l'article L.121-19-3</strong>
EOT;

		return Html::_div(
			array('class' => 'cgv form-check'),
			Html::_index('hidden', '', 'cgv_reservation', array('value' => '0')),
			Html::_index(
				'checkbox',
				'cgv_reservation',
				'cgv_reservation',
				array(
					'class' => 'cgv_reservation required form-check-input',
					'value' => "1",
					'tabindex' => "16",
					'data-msg-required' => $warning_msg,
				),
				false,
				false,
				(array_key_exists('cgv_reservation', $post) ? $post['cgv_reservation'] : '0') == '1'
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
				'<button type="submit" tabindex="17" id="submit" name="submit" class="form-control btn btn-primary text-center" disabled>'
				. esc_html__('Pay and validate', 'parking-management')
				. ' <i class="fa-regular fa-circle-right"></i>'
				. '</button>'

			),

		);
	}

	public function dialog_booking_confirmation(): string
	{
		$post = array_merge($_GET, $_POST);
		$form = $this->site['form'] ?? [];
		if (($form['dialog_confirmation'] ?? '0') != '1') {
			return '';
		}
		return '<div id="dialog_booking_confirmation" title="' . esc_html__('Confirmation', 'parking-management') . '">
	<form id="confirmation" name="confirmation" method="post" action="">
		<div class="row">
			<div>
				<label class="form-label" for="depart2">' . esc_html__('Dropping off at', 'parking-management') . '</label>
				<input tabindex="19" type="text" id="depart2" name="depart" class="departure regular required border rounded form-control py-2" autocomplete="off" value="' . ($post['depart'] ?? '') . '">
			</div>
			<div>
					<label class="form-label" for="retour2">' . esc_html__('Landing at the airport', 'parking-management') . '</label>
					<input tabindex="-1" type="text" id="retour2" name="retour" class="return regular required border rounded form-control py-2" autocomplete="off" value="' . ($post['retour'] ?? '') . '">
			</div>
			<div class="mb-3">
				<div class="col email">
					<label class="label form-label" for="email2">' . esc_html__('Email', 'parking-management') . '</label>
					<input tabindex="18" type="email" id="email2" name="email" class="email regular required col-5 border rounded py-2 px-3 form-control" value="' . ($post['email'] ?? '') . '">
				</div>
			</div>
		</div>
        <div class="row my-3 position-absolute bottom-0 start-50 translate-middle-x">
			<div class="col">
                <button tabindex="20" id="submit2" class="form-control btn btn-primary text-center gradient-e97445" name="submit2" type="submit">
                    ' . esc_html__('Confirm your order', 'parking-management') . '
                    <i class="fa-regular fa-circle-right"></i>
				</button>
			</div>
		</div>
	</form>
</div>';
	}

	public function options(): string
	{
		$form = $this->site['form'] ?? [];

		$post = array_merge($_GET, $_POST);
		$contents = [];
		foreach ($form['options'] ?? [] as $key => $value) {
			if ($value['enabled'] !== '1' || $value['price'] === '0' || empty($value['label']) || $key == 'keep_keys') {
				continue;
			}
			$msg = sprintf(esc_html__($value['label'], 'parking-management'), $value['price']);
			$contents[] = Html::_div(
				array('class' => 'options-container form-check'),
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
					(array_key_exists($key, $post) ? $post[$key] : $value['checked']) == '1'
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
