<?php

namespace ParkingManagement;

use Booking\Order;
use Booking\Site;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use ParkingManagement\interfaces\IParkingmanagement;
use ParkingManagement\interfaces\IPayment;
use ParkingManagement\interfaces\IShortcode;
use Payment\MyPos;
use Payment\Payplug;
use Payment\Stripe;

require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "payment" . DS . "payment_id.php";
require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "payment" . DS . "includes" . DS . "payplug.php";
require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "payment" . DS . "includes" . DS . "mypos.php";
require_once PKMGMT_PLUGIN_MODULES_DIR . DS . "payment" . DS . "includes" . DS . "stripe.php";

class Payment implements IShortcode, IParkingManagement
{

	private ParkingManagement $pm;
	private string $provider = '';

	private array $config = [];

	private int $order_id = 0;

	public function __construct(ParkingManagement $pm)
	{
		$this->pm = $pm;
	}


	private function getInstanceProvider(): IPayment
	{
		Logger::debug('payment.getInstanceProvider', ["provider" => $this->provider]);
		return match ($this->provider) {
			'mypos' => new MyPos($this->config, $this->order_id),
			'stripe' => new Stripe($this->config, $this->order_id),
			default => new Payplug($this->config, $this->order_id),
		};
	}

	public function shortcode(string $type): string
	{
		if (!array_key_exists('order_id', $_GET) || !is_numeric($_GET['order_id'])
			|| (array_key_exists('from', $_GET) && $_GET['from'] === 'provider')
		)
			return '';
		$this->order_id = (int)$_GET['order_id'];
		$this->resolveSiteFromOrder();
		if (!$this->isEnabled())
			return '';
		return match ($this->provider) {
			'payplug', 'mypos', 'stripe' => $this->run_provider(),
			default => sprintf('[parking-management type="payment" payment_provider="%s"]', $this->provider)
		};

	}
	public function run_provider(): string
	{
		$instance = $this->getInstanceProvider();
		return $instance->pay();
	}

	#[NoReturn] public function redirect(): void
	{
		$instance = $this->getInstanceProvider();
		$instance->redirect();
	}

	public function isEnabled(): bool
	{
		return ($this->config['enabled'] ?? '0') == '1';
	}

	public function doRedirect(): bool
	{
		return ($this->config['redirect-to-provider'] ?? '0') == '1';
	}

	public static function validateOnPayment(array $site): bool
	{
		return ($site['payment']['valid-on-payment'] ?? '0') === '1';
	}

	/**
	 * Configures this instance from an already-resolved site (used right after
	 * order creation, in the same request, where the site is already known).
	 */
	public function setSite(array $site): void
	{
		$this->config = $site['payment'] ?? [];
		$this->provider = $this->config['name'] ?? 'payplug';
	}

	public function setOrderId(int $order_id): void
	{
		$this->order_id = $order_id;
	}

	/**
	 * Resolves the site from the order's own `parking_id` - used whenever Payment
	 * is reached outside the original booking request (payment page, provider
	 * redirect back, webhook), where there is no `site_id` in the current request.
	 */
	private function resolveSiteFromOrder(): void
	{
		$site = null;
		try {
			$order = new Order();
			$row = $order->read($this->order_id);
			$site = Site::findByLegacySiteId($row['parking_id']);
		} catch (Exception $e) {
			Logger::error('payment.resolveSiteFromOrder', $e->getMessage());
		}
		$this->setSite($site ?? Site::defaultSite() ?? []);
	}

	public function updatePaymentStatus(): bool
	{
		if ( ($this->provider !== 'stripe') || (empty($_GET['session_id']))) return false;
		return Stripe::updatePaymentStatus($this->config, $this->order_id);
	}
}
