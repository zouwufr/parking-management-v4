<?php

namespace ParkingManagement;

use Booking\Form;
use Booking\HomeForm;
use Booking\Member;
use Booking\Order;
use Booking\Site;
use Booking\Vehicle;
use Exception;
use ParkingManagement\interfaces\IParkingmanagement;
use ParkingManagement\interfaces\IShortcode;

require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "booking" . DS . "types" . DS . "parking_type.php";
require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "booking" . DS . "types" . DS . "vehicle_type.php";
require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "booking" . DS . "types" . DS . "iata.php";
require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "booking" . DS . "types" . DS . "airport.php";
require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "booking" . DS . "types" . DS . "terminal.php";
require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "booking" . DS . "types" . DS . "order_status.php";
require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "booking" . DS . "includes" . DS . "site.php";
require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "booking" . DS . "includes" . DS . "form.php";
require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "booking" . DS . "includes" . DS . "home_form.php";
require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "booking" . DS . "includes" . DS . "member.php";
require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "booking" . DS . "includes" . DS . "order.php";
require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "booking" . DS . "includes" . DS . "vehicle.php";

class Booking implements IShortcode, IParkingManagement
{

	private ParkingManagement $pm;

	public function __construct(ParkingManagement $pm)
	{
		$this->pm = $pm;
	}

	public function shortcode($type): string
	{
		return match ($type) {
			'form' => $this->shortcode_form(),
			'home-form' => $this->shortcode_home_form(),
			default => '',
		};
	}

	private function shortcode_form(): string
	{
		$form = new Form($this->pm);
		return $this->message() .
			Html::_div(array('class' => 'form container-md col mt-5'),
				Html::_div(array('class' => 'row'),
					Html::_div(array('class' => 'col-12'),
						Html::_form('reservation', 'reservation', 'post', '', array(),
							$form->progress_indicator(),
							Html::_div(array('class' => 'row'),
								Html::_div(array('class' => 'col-12'),
									$form->step_location_and_dates(),
									$form->step_vehicle_and_parking(),
									$form->step_your_details(),
									$form->total(),
									$form->cgv(),
									$form->submit(),
								),
							)
						)
					)
				),
				$form->dialog_booking_confirmation(),
			)
			. $form->spinner();
	}

	private function message(): string
	{
		$post = array_merge($_GET, $_POST);
		if (array_key_exists("message", $post)) {
			foreach ($post['message'] as $key => $value) {
				return match ($key) {
					'error' => Html::_alert('danger', $value),
					'info' => Html::_alert('info', $value),
					default => ''
				};
			}
		}
		return '';
	}


	private function shortcode_home_form(): string
	{
		$home_form = new HomeForm($this->pm);
		return Html::_div(array('class' => 'home-form container-md col-xl-6 my-4'),
			Html::_div(array('class' => 'row'),
				Html::_div(array('class' => 'col-12'),
					Html::_form('quote', 'quote', 'post', '', array(),
						$home_form->hidden($this->pm),
						Html::_div(array('class' => 'row row-cols-3'),
							$home_form->quote($this->pm),
						)
					)
				)
			)
		);
	}

	public function record(): void
	{
		$post = array_merge($_GET, $_POST);
		$page = home_url();

		try {
			$member = new Member();
			$member_id = $member->isMemberExists($post['email']);
			if (!$member_id)
				$member_id = $member->create();
			else
				$member->patch($member_id, $post);
			$order = new Order();
			$order_id = $order->create($member_id);
			if ($order_id === 0)
				exit(0);
			$vehicle = new Vehicle();
			$vehicle->create($order_id);

			$site = Site::current();

			$payment = new Payment($this->pm);
			$payment->setSite($site ?? []);
			$payment->setOrderId($order_id);
			if ($payment->isEnabled() && $payment->doRedirect()) {
				$_GET['order_id'] = $order_id;
				Logger::debug('booking.record', ["order_id" => $order_id]);
				$data = $this->getData($order_id, $member_id, $site ?? []);
				$not = new Notification($this->pm);
				$orderInstance = new Order();
				$not->confirmation($data, $orderInstance, $site ?? []);
				$payment->redirect();
			}
			if (!empty($site['form']['validation_page']['value']))
				$page = $site['form']['validation_page']['value'];

			wp_redirect($page . '?order_id=' . $order_id);
			exit(0);

		} catch (Exception $e) {
			Logger::error("booking.record", $e->getMessage());
			// $current_shortcode_page is only ever assigned while the [parking-management]
			// shortcode itself renders (includes/shortcode.php) - this code runs on the
			// `init` hook instead, which always fires *before* that happens on this same
			// request, so the global is unconditionally still "" here. wp_redirect("" . '?'
			// . ...) resolves as a query-only relative reference, which browsers happen to
			// resolve against the current path - fragile, and it stacked a duplicate
			// message[error] param on every retry since the referer's own query string
			// (including any previous message[error]) was never stripped. wp_get_referer()
			// (the actual page the form was submitted from) is reliable here instead.
			$page = strtok(wp_get_referer() ?: home_url(), '?');
			unset($post['pkmgmt_action'], $post['submit2'], $post['message']);
			wp_redirect($page . '?' . http_build_query($post) . '&message[error]=' . $e->getMessage());
			exit(0);
		}
	}

	/**
	 * @throws Exception
	 */
	private function getData($order_id, $member_id, array $site): array
	{
		$orderInstance = new Order();
		$order = $orderInstance->read($order_id);
		$memberInstance = new Member();
		$member = $memberInstance->read($member_id);
		$vehicleInstance = new Vehicle();
		$vehicle = $vehicleInstance->read($order_id);

		$info = $site['info'] ?? [];
		$form = $site['form'] ?? [];
		$data = replacementData('member', $member);
		$data = array_merge($data, replacementData('order', $order));
		$data = array_merge($data, replacementData('vehicle', $vehicle));
		$data['order_depart_formated'] = DatesRange::convertDate($data['order_depart'] . ' ' . $data['order_depart_heure'], 'Y-m-d H:i:s', 'd MMMM y H:mm');
		$data['order_arrivee_formated'] = DatesRange::convertDate($data['order_arrivee'] . ' ' . $data['order_arrivee_heure'], 'Y-m-d H:i:s', 'd MMMM y H:mm');
		$data['order_id'] = $_GET['order_id'];
		$data['member_id'] = $order['membre_id'];
		$data['info_name'] = $site['name'] ?? $this->pm->title;
		$data['info_address'] = $info['address'] ?? '';
		$data['info_mobile'] = $info['mobile'] ?? '';
		$data['info_rcs'] = $info['RCS'] ?? '';
		$data['info_email'] = $info['email'] ?? '';
		$data['form_options_late_price'] = $form['options']['late']['price'] ?? 0;
		$data['url_access'] = home_url();
		$data['home_url'] = home_url();
		return $data;
	}
}
