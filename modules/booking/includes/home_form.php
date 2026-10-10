<?php

namespace Booking;

use ParkingManagement\Html;
use ParkingManagement\ParkingManagement;

class HomeForm
{

	private function enqueue(ParkingManagement $pm): void
	{
		wp_enqueue_style('parking-management-home-form', pkmgmt_plugin_url('modules/booking/css/home_form.css'), array(), PKMGMT_VERSION);
		wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), '6.0.0-beta3');
		wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', array(), '5.3.3');
		wp_enqueue_style('parking-management-easepick', 'https://cdn.jsdelivr.net/npm/@easepick/bundle@1.2.1/dist/index.css');
		wp_enqueue_style('parking-management-jquery-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.14.1/themes/base/jquery-ui.min.css');

		wp_enqueue_script('parking-management-jquery', 'https://code.jquery.com/jquery-3.7.1.min.js');
		wp_enqueue_script('parking-management-jquery-ui', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.14.1/jquery-ui.min.js', array('parking-management-jquery'));
		wp_enqueue_script('parking-management-easepick', 'https://cdn.jsdelivr.net/npm/@easepick/bundle@1.2.1/dist/index.umd.min.js');
		wp_enqueue_script(
			'parking-management-home-form',
			pkmgmt_plugin_url('modules/booking/js/home_form.js'),
			array(
				'parking-management-jquery',
				'parking-management-jquery-ui',
				'parking-management-easepick',
			),
			PKMGMT_VERSION
		);
		wp_localize_script('parking-management-home-form',
			'external_object',
			array(
				'locale' => $pm->locale,
				'i18n_path' => pkmgmt_plugin_url('languages'),
				'home_url' => home_url(),
				'home_form_css' => pkmgmt_plugin_url('modules/booking/css/home_form.css'),
			)
		);
	}

	public function __construct(ParkingManagement $pm)
	{
		$this->enqueue($pm);
	}

	public function hidden(ParkingManagement $pm): string
	{
		$site = Site::current();
		$contents = array();
		$contents[] = Html::_index('hidden', 'pkmgmt_action', 'pkmgmt_action', array('value' => 'home-form'));
		$contents[] = Html::_index('hidden', 'type_id', 'type_id', array('value' => '1'));
		$contents[] = Html::_index('hidden', 'aeroport', 'aeroport', array('value' => Order::getSiteID($site['airport'] ?? 'orly')->value));
		return implode("", $contents);

	}
	public function quote(ParkingManagement $pm): string
	{
		$post = array_merge($_GET, $_POST);
		return
			Html::_div(
				array('class' => 'col-6',),
				Html::_label_with_attr(
					array('class' => 'form-label'),
					'depart',
					esc_html__('Start date', 'parking-management')
				),
				'<div class="input-group">',
				'<span class="input-group-text"><i class="fa-solid fa-calendar-days"></i></span>',
				Html::_index('text', 'depart', 'depart', array(
					'class' => 'departure regular required border rounded-end form-control py-2',
					'autocomplete' => 'off',
					'tabindex' => "12",
					'value' => $post['depart'] ?? '',
				)),
				'</div>',

			).
			Html::_div(
				array('class' => 'col-6',),
				Html::_label_with_attr(
					array(
						'class' => 'form-label'
					),
					'retour',
					esc_html__('End date', 'parking-management')
				),
				'<div class="input-group">',
				'<span class="input-group-text"><i class="fa-solid fa-calendar-days"></i></span>',
				Html::_index('text', 'retour', 'retour', array(
					'class' => 'return regular required border rounded-end form-control py-2',
					'autocomplete' => 'off',
					'tabindex' => "-1",
					'value' => $post['retour'] ?? '',
				)),
				'</div>',
			).
			Html::_div(
				array('class' => 'col-12 d-flex justify-content-around pt-3'),
				'<button id="submit" type="submit" class="btn btn-success btn-lg"><i class="fa-solid fa-hand-point-up"></i>', esc_html__('Your quote in 2 clicks', 'parking-management') ,'</button>',
			);
	}
}
