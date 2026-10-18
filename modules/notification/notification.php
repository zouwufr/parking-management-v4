<?php

namespace ParkingManagement;

use Booking\Member;
use Booking\Order;
use Booking\Site;
use Booking\Vehicle;
use Exception;
use Notification\Mail;
use ParkingManagement\interfaces\IShortcode;
use Notification\SMS;

require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "notification" . DS . "includes" . DS . "mail.php";
require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "notification" . DS . "includes" . DS . "sms.php";

class Notification implements IShortcode
{
	private ParkingManagement $pm;

	public function __construct(ParkingManagement $pm)
	{
		$this->pm = $pm;

	}

	public function shortcode(string $type): string
	{
		if (!array_key_exists('order_id', $_GET) || !is_numeric($_GET['order_id']))
			return '';
		try {
			$order = new Order();
			$row = $order->read((int)$_GET['order_id']);
			$site = Site::findByLegacySiteId($row['parking_id']) ?? Site::defaultSite();
			if ($site === null) {
				Logger::error("notification.shortcode", "no site could be resolved for this order");
				return '';
			}
			if (Payment::validateOnPayment($site) && (!array_key_exists('from', $_GET) || $_GET['from'] != 'provider'))
				return '';
			$data = $this->getData($row, $site);
			return match ($type) {
				'confirmation' => $this->confirmation($data, $order, $site),
				'cancellation' => $this->cancellation($data, $order, $site),
				default => ''
			};
		} catch (Exception $e) {
			Logger::error("notification.getData", $e->getMessage());
			return '';
		}
	}

	public function confirmation(array $data, Order $order, array $site): string
	{
		try {
			$payment = new Payment($this->pm);
			$payment->setSite($site);
			$payment->setOrderId((int)$_GET['order_id']);
			if (!$payment->updatePaymentStatus())
				$order->confirmed((int)$_GET['order_id']);
			$this->mail($site, $data, esc_html__('Summary of your order', 'parking-management'), $site['mail_templates']['confirmation']['value']);
			$this->sms($site, $data, $site['sms_template']);
		} catch (Exception $e) {
			Logger::error("notification.confirmation", $e->getMessage());
		}
		return '';
	}

	private function cancellation(array $data, Order $order, array $site): string
	{
		try {
			$order->cancel((int)$_GET['order_id']);
			$this->mail($site, $data, esc_html__('Pending payment', 'parking-management'), $site['mail_templates']['cancellation']['value']);
		} catch (Exception $e) {
			Logger::error("notification.cancellation", $e->getMessage());
		}
		return '';
	}

	private function mail(array $site, array $data, string $subject, $template): void
	{
		if (empty($template)) {
			Logger::warning("notification.mail", "mail do not sent to {$data['member_email']} : missing template");
			return;
		}
		$message = replacePlaceholders($template, $data);
		if (Mail::send(
			$site['notification']['mail'] ?? [],
			$data['member_email'],
			$subject,
			nl2br($message)
		))
			Logger::info("notification.mail", "mail sent to {$data['member_email']}");
		else
			Logger::warning("notification.mail", "mail do not sent to {$data['member_email']}");
	}

	private function sms(array $site, array $data, $template): void
	{
		if (empty($template)) {
			return;
		}
		$message = replacePlaceholders($template, $data);
		if (SMS::send($site['notification']['sms'] ?? [], $data['order_telephone'], $message))
			Logger::info("notification.sms", "message sent to {$data['order_telephone']}");
		else
			Logger::warning("notification.sms", "message do not sent to {$data['order_telephone']}");
	}

	/**
	 * @throws Exception
	 */
	private function getData(array $order, array $site): array
	{
		$memberInstance = new Member();
		$member = $memberInstance->read($order['membre_id']);
		$vehicleInstance = new Vehicle();
		$vehicle = $vehicleInstance->read((int)$_GET['order_id']);

		$info = $site['info'] ?? [];
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
		$data['form_options_late_price'] = $site['form']['options']['late']['price'] ?? 0;
		$data['url_access'] = home_url();
		$data['home_url'] = home_url();
		return $data;
	}
}
