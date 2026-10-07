<?php

namespace Payment;

use Booking\Member;
use Booking\Order;
use Exception;
use Mypos\IPC\Cart;
use Mypos\IPC\Config;
use Mypos\IPC\Customer;
use Mypos\IPC\Purchase;
use mypos\Page;
use ParkingManagement\API\MyPosAPI;
use ParkingManagement\interfaces\IPayment;
use ParkingManagement\Logger;
use ParkingManagement\ParkingManagement;

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_WARNING & ~E_NOTICE);

require_once PKMGMT_PLUGIN_MODULES_DIR . DS . 'payment' . DS . 'includes' . DS . 'mypos' . DS . 'page.php';
require_once PKMGMT_PLUGIN_MODULES_DIR . DS . 'payment' . DS . 'includes' . DS . 'mypos' . DS . 'api.php';

class MyPos implements IPayment
{

	private array $config;
	private array $properties;
	private int $order_id;

	public const ipcTestURL = "https://www.mypos.com/vmp/checkout-test";
	public const configTest = 'eyJzaWQiOiIwMDAwMDAwMDAwMDAwMTAiLCJjbiI6IjYxOTM4MTY2NjEwIiwicGsiOiItLS0tLUJFR0lOIFJTQSBQUklWQVRFIEtFWS0tLS0tXHJcbk1JSUNYQUlCQUFLQmdRQ2YwVGRjVHVwaGI3WCtad2VrdDFYS0VXWkRjelNHZWNmbzZ2UWZxdnJhZjVWUHpjbkpcclxuMk1jNUo3MkhCbTB1OThFSkhhbitubGUyV09aTVZHSXRUYVwvMmsxRlJXd2J0N2lRNWR6RGg1UEVlWkFTZzJVV2VcclxuaG9SOEw4TXBOQnFINmg3WklUd1ZUZlJTNExzQnZsRWZUN1B6aG01WUpLZk0rQ2R6RE0rTDlXVkVHd0lEQVFBQlxyXG5Bb0dBWWZLeHdVdEVicTh1bFZyRDNubldoRitoazFrNktlamRVcTBkTFlOMjl3OFdqYkNNS2I5SWFva21xV2lRXHJcbjVpWkdFcll4aDdHNEJEUDhBV1wvK005SFhNNG9xbTVTRWtheGhiVGxna3MrRTFzOWRUcGRGUXZMNzZUdm9kcVN5XHJcbmwyRTJCZ2hWZ0xMZ2tkaFJuOWJ1YUZ6WXRhOTVKS2ZneUtHb25OeHNRQTM5UHdFQ1FRREtiRzBLcDZLRWtOZ0Jcclxuc3JDcTNDeDJvZDVPZmlQREc4ZzNSWVpLeFwvTzlkTXk1Q00xNjBEd3VzVkpwdXl3YnBSaGNXcjNna3owUWdSTWRcclxuSVJWd3l4TmJBa0VBeWgzc2lwbWNnTjdTRDh4QkdcL010QllQcVdQMXZ4aFNWWVBmSnp1UFUzZ1M1TVJKelFIQnpcclxuc1ZDTGhUQlk3aEhTb3FpcWxxV1lhc2k4MUp6QkV3RXVRUUpCQUt3OXFHY1pqeU1IOEpVNVREU0dsbHIzanlieFxyXG5GRk1QajhUZ0pzMzQ2QUI4b3pxTExcL1RodldQcHhIdHRKYkg4UUFkTnV5V2RnNmRJZlZBYTk1aDdZK01DUUVaZ1xyXG5qUkRsMUJ6N2VXR08yYzBGcTlPVHozSVZMV3BubUd3ZlcrSHlheGl6eEZoVitGT2oxR1VWaXI5aHlsVjdWMERVXHJcblFqSWFqeXZcL29lRFdoRlE5d1FFQ1FDeWRoSjZOYU5RT0NaaCs2UVRySDNUQzVNZUJBMVllaXBvZTcrQmhzTE5yXHJcbmNGRzhzOXNUeFJubHRjWmwxZFhhQlNlbXZwTnZCaXpuMEt6aThHM1pBZ2M9XHJcbi0tLS0tRU5EIFJTQSBQUklWQVRFIEtFWS0tLS0tIiwicGMiOiItLS0tLUJFR0lOIENFUlRJRklDQVRFLS0tLS1cclxuTUlJQnNUQ0NBUm9DQ1FDQ1BqTnR0R05RV0RBTkJna3Foa2lHOXcwQkFRc0ZBREFkTVFzd0NRWURWUVFHRXdKQ1xyXG5SekVPTUF3R0ExVUVDZ3dGYlhsUVQxTXdIaGNOTVRneE1ERXlNRGN3T1RFeldoY05Namd4TURBNU1EY3dPVEV6XHJcbldqQWRNUXN3Q1FZRFZRUUdFd0pDUnpFT01Bd0dBMVVFQ2d3RmJYbFFUMU13Z1o4d0RRWUpLb1pJaHZjTkFRRUJcclxuQlFBRGdZMEFNSUdKQW9HQkFNTCtWVG1pWTR5Q2hvT1RNWlRYQUlHXC9tayt4ZlwvOW1qd0h4V3p4dEJKYk5uY05LXHJcbjBPTEkwVlhZS1cyR2dWa2xHSEhRanZldzFoVEZrRUdqbkNKN2Y1Q0RuYmd4ZXZ0eUFTREdzdDkyYTZ4Y0FlZEVcclxuYWRQMG5GWGhVeitjWVlJZ0ljZ2ZEY1gzWldlTkVGNWtzY3F5NTJrcEQyTzduRk5DVis4NXZTNGR1SkJOQWdNQlxyXG5BQUV3RFFZSktvWklodmNOQVFFTEJRQURnWUVBQ2oweGIrdE5ZRVJKa0wrcCt6RGNCc0JLNFJ2a25QbHBrK1lQXHJcbmVwaHVuRzJkQkdPbWdcL1dLZ29EMVBMV0QyYkVmR2dKeFlCSWc5cjF3TFlwREMxdHhoeFYrMk9CUVM4NktVTGgwXHJcbk5FY3IwcUVZMDVtSTRGbEUrRFwvQnBUXC8rV0Z5S2tadWc5MnJLMEZsejcxWHlcLzltQlhiUWZtK1lLNmw5cm9SWWRcclxuSjRzSGVRYz1cclxuLS0tLS1FTkQgQ0VSVElGSUNBVEUtLS0tLSIsImlkeCI6MX0=';
	public const ipcURL = 'https://mypos.com/vmp/checkout';
	private float $amount;

	public function __construct(array $config, int $order_id)
	{
		$this->config = $config;
		$this->properties = $config['properties']['mypos'];
		$this->order_id = $order_id;
		$this->initPayment();
	}

	public function pay(): string
	{
		return Page::form($this->amount, $this->order_id, $this->config);
	}

	public function redirect(): void
	{
		$data = array();
		try {
			Logger::debug('mypos.redirect', ['config' => $this->config]);
			$test_enabled = $this->config['active-test'] === '1';

			$data['post'] = $_POST;
			$order = new Order();
			$data['order'] = $order->read($this->order_id);
			$data['order']['id'] = $this->order_id;
			if ($test_enabled)
				$data['order']['id'] = generatePassword();
			$member = new Member();
			$data['member'] = $member->read($data['order']['membre_id']);
			$this->amount = $data['order']['total'];
			$configPackage = $test_enabled ? self::configTest : $this->properties['configuration_package']['value'];
			$ipcURL = $test_enabled ? self::ipcTestURL : self::ipcURL;
			$cnf = new Config();
			$cnf->setIpcURL($ipcURL);
			$cnf->setLang('en');
			$cnf->setVersion('1.4');
			$cnf->loadConfigurationPackage($configPackage);
			$customer = new Customer();
			$customer->setFirstName($data['member']['prenom']);
			$customer->setLastName($data['member']['nom']);
			$customer->setEmail($data['member']['email']);
			$customer->setPhone(preg_replace('/^00/', '+', $data['member']['tel_port']));
			$customer->setCountry('FRA');
			$customer->setZip($data['member']['code_postal']);

			$cart = new Cart;
			$cart->add("Reservation du {$data['order']['depart']} au {$data['order']['arrivee']}", 1, $data['order']['total']);


			$success_url = $this->properties['success_page']['value'] . '?from=provider&order_id=' . $this->order_id;
			$cancel_url = $this->properties['cancel_page']['value'] . '?order_id=' . $this->order_id;
			$notify_url = home_url() . "/wp-json/pkmgmt/v1/mypos/ipn?order_id={$this->order_id}";
			if ($this->properties['notification_url']['value'] !== '')
				$notify_url = $this->properties['notification_url']['value'];

			$purchase = new Purchase($cnf);
			$purchase->setUrlOk($success_url);
			$purchase->setUrlCancel($cancel_url);
			$purchase->setUrlNotify($notify_url);
			$purchase->setOrderID($data['order']['id']);
			$purchase->setCurrency('EUR');
			$purchase->setCustomer($customer);
			$purchase->setCart($cart);


			$purchase->setCardTokenRequest(Purchase::CARD_TOKEN_REQUEST_PAY_AND_STORE);
			$purchase->setPaymentParametersRequired(Purchase::PURCHASE_TYPE_FULL);
			$purchase->setPaymentMethod(Purchase::PAYMENT_METHOD_BOTH);

			$purchase->process();
			exit(0);
		} catch (Exception $e) {
			Logger::error("payplug.initPayment", ['data' => $data, 'message' => $e->getMessage(), 'exception' => $e]);
		}
	}

	private function initPayment(): void
	{
		$data = [];
		try {
			$order = new Order();
			$data['order'] = $order->read($this->order_id);
			$this->amount = $data['order']['total'];

		} catch (Exception $e) {
			Logger::error("mypos.initPayment", ['data' => $data, 'payload' => $payload ?? 'n/c', 'exception' => $e->getMessage()]);
		}
	}
}


new MyPosAPI();
