<?php

namespace Booking;

use DateTime;
use Exception;
use InvalidArgumentException;
use ParkingManagement\Booked;
use ParkingManagement\database\database;
use ParkingManagement\DatesRange;
use ParkingManagement\Logger;
use ParkingManagement\ParkingManagement;
use ParkingManagement\Payment;
use ParkingManagement\PaymentID;
use ParkingManagement\Price;
use wpdb;

class Order
{
	private string $airport;
	private int $site_id;
	private array $data;
	private ?array $site;

	private ParkingManagement $pm;

	private bool|wpdb $conn;
	public const PhoneCountry = array(
		1 => array('id' => 1, 'initial' => 'FR', 'pays' => 'France', 'prefix' => '0033', 'size' => 13, 'min' => '0', 'before' => '0'),
		2 => array('id' => 2, 'initial' => 'BE', 'pays' => 'Belgium', 'prefix' => '0032', 'size' => 13, 'min' => '0', 'before' => ''),
		3 => array('id' => 3, 'initial' => 'CH', 'pays' => 'Switzerland', 'prefix' => '0041', 'size' => 13, 'min' => '0', 'before' => ''),
		4 => array('id' => 4, 'initial' => 'LU', 'pays' => 'Luxembourg', 'prefix' => '00352', 'size' => 11, 'min' => 0, 'before' => ''),
		5 => array('id' => 5, 'initial' => 'MC', 'pays' => 'Monaco', 'prefix' => '00377', 'size' => 13, 'min' => 0, 'before' => ''),
		6 => array('id' => 6, 'initial' => 'DE', 'pays' => 'Germany', 'prefix' => '0049', 'size' => 14, 'min' => 0, 'before' => ''),
		7 => array('id' => 7, 'initial' => 'NL', 'pays' => 'Netherlands', 'prefix' => '0031', 'size' => 13, 'min' => 0, 'before' => '')
	);

	/**
	 * @throws Exception
	 */
	public function __construct()
	{
		$pm = getParkingManagementInstance();
		$this->pm = $pm;
		$this->conn = database::connect();
		$this->data = $_POST;
		$site_id = $this->getData('site_id');
		$this->site = !empty($site_id) ? Site::find($site_id) : Site::defaultSite();
		if (empty($this->site)) {
			throw new Exception('No site is configured.');
		}
		$this->airport = $this->site['airport'];
		$this->site_id = self::getSiteID($this->airport)->value;
		date_default_timezone_set('Europe/Paris');
	}

	/**
	 * @throws Exception
	 */
	public function create(string $member_id): int
	{
		if (!$member_id)
			throw new Exception("order creation failed");
		if ($order_id = $this->isExists($member_id))
			return $order_id;
		$billing = implode(" ", array(ucwords($this->getData('nom')), ucwords($this->getData('prenom')))) . "\n";
		$billing .=
			implode(
				" ",
				array(
					ucwords(
						$this->getData('code_postal')
					),
					ucwords(
						$this->getData('ville')
					)
				)
			);
		$billing .= ',  ' . mb_strtoupper(self::PhoneCountry[$this->getData('pays')]['pays'], 'UTF-8');
		$start_date = substr($this->getData('depart'), 0, 10);
		$end_date = substr($this->getData('retour'), 0, 10);
		if (Booked::is($start_date, $this->site)) {
			Logger::info("order.create", "car park is full on " . DatesRange::convertDate($start_date));
			throw new Exception(__("Car park is full on ", 'parking-management') . DatesRange::convertDate($start_date));
		}
		$start = DateTime::createFromFormat('Y-m-d', $start_date);
		$end = DateTime::createFromFormat('Y-m-d', $end_date);
		$start_hour = substr($this->getData('depart'), 11, 5);
		$end_hour = substr($this->getData('retour'), 11, 5);
		if (Booked::is($start_date, $this->site) || Booked::is($end_date, $this->site)) {
			Logger::info("order.create", "car park is full");
			return 0;
		}

		$search = implode(" ",
			array(ucwords($this->getData('prenom')), ucwords($this->getData('nom')), ucwords($this->getData('email')),
				str_replace('+', '00', $this->getData('tel_port'))
			)
		);
		$referer = parse_url($_SERVER['HTTP_REFERER']);
		$referer_host = array_key_exists('host', $referer) ? $referer['host'] : NULL;
		$priceInstance = new Price($this->pm);
		$price = $priceInstance->getPrice($this->data);

		$payment = new Payment($this->pm);
		$payment->setSite($this->site);
		$order = array(
			'resauuid' => uniqid(),
			'site_id' => $this->site_id,
			'parking_id' => $this->site['id'],
			'date' => date('Y-m-d H:i:s'),
			'membre_id' => $member_id,
			'telephone' => $this->formatPhone($this->getData('tel_port')),
			'facturation' => $billing,
			'depart' => $start->format('Y-m-d'),
			'depart_heure' => $start_hour,
			'arrivee' => $end->format('Y-m-d'),
			'arrivee_heure' => $end_hour,
			'nb_jour' => $price['nb_jour'],
			'nb_jour_offert' => '0',
			'nb_personne' => serialize(array(
					'aller' => $this->getData('nb_pax'),
					'retour' => $this->getData('nb_pax')
				)
			),
			'destination_id' => !empty($this->getData('destination_id')) ? $this->getData('destination_id') : 0,
			'terminal' => serialize($this->getData('terminal')),
			'remarque' => '',
			'total' => $price['total'],
			'grille_tarifaire' => serialize($price),
			'tva' => $this->site['tva'] ?? 20,
			'tva_transport' => $this->site['tva_transport'] ?? 10,
			'coupon_id' => 0,
			'recherche' => sansAccent($search),
			'status' => OrderStatus::CONFIRMED->value,
			'nb_retard' => 0,
			'ip' => $_SERVER['REMOTE_ADDR'],
			'host' => $_SERVER['HTTP_USER_AGENT'],
			'referer' => $referer_host
		);
		Logger::info("order.create", ['params' => $order]);

		if ($this->conn->insert(
			'tbl_commande',
			$order) === false) {
			Logger::error("order.create", ['message' => $this->conn->last_error, 'params' => $order]);
			if ( str_contains($this->conn->last_error, 'Duplicate entry')) {
				throw new Exception(__("Order already exist", 'parking-management'));
			}
			throw new Exception(__("order creation failed in database insertion", 'parking-management'));
		}

		$id = $this->conn->insert_id;
		if (!$id) {
			Logger::error("order.create", ['message' => 'Order id is null']);
			throw new Exception(__("fail to create order", 'parking-management'));
		}
		Logger::info("order.create", ['id' => $id]);
		// may be useless
		$payment->setOrderId($id);
		$order['remarque'] = sansAccent("Commande Parking " . $this->airport . " / Destination : " . $this->getData('destination') . " / Reference : " . $id);
		$order['facture_id'] = $this->getBillID($id);
		if ($this->conn->update(
			'tbl_commande',
			[
				'remarque' => $order['remarque'],
				'facture_id' => $order['facture_id']
			],
			['id_commande' => $id]
		) === false)
			Logger::error("order.create", ["message" => "error during order update with remarque and facture_id", "facture_id" => $order['facture_id'], "remarque" => $order['remarque']]);

		return $id;
	}

	/**
	 * @throws Exception
	 */
	public function read(int $order_id): array
	{
		$query = 'SELECT `resauuid`, `site_id`, `parking_id`,  `date`, `membre_id`
		    , `telephone`, `facture_id`, `facturation`, `depart`, `depart_heure`, `arrivee`
		    , `arrivee_heure`, `nb_jour`, `nb_jour_offert`, `nb_personne`
		    , `destination_id`, `terminal`, `remarque`, `total`, `grille_tarifaire`, `tva`
		    , `tva_transport`, `coupon_id`, `recherche`, `status`
		    , `nb_retard`
		    , `ip`, `host`, `referer` FROM `tbl_commande` WHERE `id_commande` = %d';
		$result = $this->conn->get_row($this->conn->prepare($query, [$order_id]), ARRAY_A);
		if ($result === null) {
			throw new Exception("Order not found with id: " . $order_id);
		}
		return $result;
	}

	/**
	 * @throws Exception
	 */
	public function update_payment(int $order_id, string $payment_date, float $amount, PaymentID $payment_id): void
	{
		$bill_id = $this->getBillID($order_id);
		$date = date('Y-m-d H:i:s');
		if ($this->conn->update(
			'tbl_commande',
			[
				'facture_id' => $bill_id,
				'paye' => $amount,
				'date_paiement' => $payment_date,
				'paiement_id' => $payment_id->value,
				'status' => OrderStatus::PAID->value,
				'date' => $date,
			],
			[
				'id_commande' => $order_id
			]
		) === false) {
			Logger::error("order.update_payment", ['message' => $this->conn->last_error, 'params' => $order_id]);
			throw new Exception("payment order update failed");
		}

	}

	/**
	 * @throws Exception
	 */
	public function confirmed(int $order_id): void
	{
		$order = $this->read($order_id);
		if ($order['status'] === OrderStatus::PAID->value)
			return;
		if ( $this->conn->update(
			'tbl_commande',
			[
				'annulation' => 0,
				'status' => OrderStatus::CONFIRMED->value,
			],
			[
				'id_commande' => $order_id
			]
		) === false) {
			Logger::error("order.confirmed", ['message' => $this->conn->last_error, 'params' => $order_id]);
			throw new Exception("order confirmation failed");
		}
	}

	/**
	 * @throws Exception
	 */
	public function cancel(int $order_id): void
	{
		if ($this->conn->update(
			'tbl_commande',
			[
				'annulation' => 1,
				'status' => OrderStatus::CONFIRMED->value,
			],
			[
				'id_commande' => $order_id
			]
		) === false)
			throw new Exception("order cancellation failed: {$this->conn->last_error}");

	}

	/**
	 * @param int $member_id
	 * @return int
	 */
	public function isExists(int $member_id): int
	{
		try {
			$start = substr($this->getData('depart'), 0, 10);
			$start_hour = substr($this->getData('depart'), 11, 5);
			$end = substr($this->getData('retour'), 0, 10);
			$end_hour = substr($this->getData('retour'), 11, 5);

			$start_date = DateTime::createFromFormat('Y-m-d', $start);
			$end_date = DateTime::createFromFormat('Y-m-d', $end);

			if ($start_date === false || $end_date === false) {
				Logger::warning("order.isExists", ["message" => "Invalid date format ", "start" => $start, "end" => $end, "start_hour" => $start_hour, "end_hour" => $end_hour]);
				throw new InvalidArgumentException("Invalid date format provided");
			}

			if ($row = $this->conn->get_row(
				$this->conn->prepare(
					"SELECT `id_commande` FROM `tbl_commande` WHERE `membre_id` = %d AND `depart` = %s AND `depart_heure` = %s AND `arrivee` = %s AND `arrivee_heure` = %s",
					[
						$member_id,
						$start_date->format('Y-m-d'),
						$start_hour,
						$end_date->format('Y-m-d'),
						$end_hour
					]
				),
				ARRAY_A) === null) {
				return 0;
			}

			if (!empty($row['id_commande'])) {
				return (int)$row['id_commande'];
			}

			return 0;

		} catch (InvalidArgumentException|Exception $e) {
			Logger::error("order.isExists", ["message" => $e->getMessage(), "member_id" => $member_id]);
			return 0;
		}
	}

	private function getData($field = null)
	{
		if (is_null($field) || !array_key_exists($field, $this->data))
			return '';
		return $this->data[$field];
	}

	private function getBillID(int $id)
	{

		if ($row = $this->conn->get_row(
			$this->conn->prepare(
				"SELECT `facture_id` FROM `tbl_commande` WHERE `id_commande` = %d",
				[$id]
			), ARRAY_A)) {
			if ($row['facture_id'] != '' && $row['facture_id'] != 'NUL000000000')
				return $row['facture_id'];
		}

		$site = self::getIATAbyAirport(self::getSiteID(strtolower($this->airport)));
		$aita = $site->name;
		$query = "SELECT count(*) AS num FROM `tbl_commande` WHERE `facture_id` LIKE '$aita%'";

		$row = $this->conn->get_row($this->conn->prepare($query), ARRAY_A);

		return ($aita . str_pad((int)($row['num']) + 1, 9, 0, STR_PAD_LEFT));
	}

	public static function nbRealDay($start, $end): int
	{
		$start = DateTime::createFromFormat('Y-m-d', $start);
		$end = DateTime::createFromFormat('Y-m-d', $end);
		$interval = $start->diff($end);
		return ($interval->days + 1);

	}

	private function formatPhone(string $phone): string|null
	{
		return !empty($phone) ? str_replace('+', '00', str_replace('+330', '+33', $phone)) : NULL;
	}

	public static function getSiteID(string $terminal): Airport
	{
		return match (strtolower($terminal)) {
			"roissy" => Airport::ROISSY,
			"orly2" => Airport::ORLY2,
			"orly3" => Airport::ORLY3,
			default => Airport::ORLY,
		};
	}

	public static function getIATAbyAirport(Airport $airport): IATA
	{
		return match ($airport) {
			Airport::ROISSY => IATA::CDG,
			default => IATA::ORY
		};
	}
}
