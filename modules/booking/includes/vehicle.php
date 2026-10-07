<?php

namespace Booking;

use Exception;
use ParkingManagement\database\database;
use ParkingManagement\Logger;
use ParkingManagement\Price;
use wpdb;

class Vehicle
{

	private array $data;
	private wpdb $conn;

	public function __construct()
	{
		if (!$conn = database::connect()) {
			Logger::error("member.database.connect", database::getError());
			return;
		}
		$this->conn = $conn;
		$this->data = array_merge($_GET, $_POST);
	}

	/**
	 * @throws Exception
	 */
	public function create(int $order_id = 0): int
	{
		if (!$order_id)
			throw new Exception("vehicle creation failed: order_id required");
		if ($vehicule_id = $this->isExists($order_id))
			return $vehicule_id;

		$priceInstance = new Price(getParkingManagementInstance());
		$price = $priceInstance->getPrice($this->data);
		$params = array(
			'commande_id' => $order_id
		, 'type_id' => $this->getData('type_id')
		, 'parking_type' => $this->getData('parking_type')
		, 'options' => serialize(array())
		, 'marque' => $this->getData('marque') ?? 'Unknown'
		, 'modele' => $this->getData('modele')
		, 'immatriculation' => $this->getData('immatriculation')
		, 'nb_personne' => serialize(
				array(
					'aller' => $this->getData('nb_pax'),
					'retour' => $this->getData('nb_pax')
				)
			)
		, 'tarif' => $price['total']
		, 'commentaire' => ''
		, 'status' => serialize(array(
				0 => array('encours' => '00:00', 'fait' => '00:00')
			, 1 => array('encours' => '00:00', 'fait' => '00:00')
			))
		);
		Logger::info("vehicule.create", ['params' => $params]);
		if (!$this->conn->insert(
			'tbl_vehicule',
			$params
		)) {
			Logger::error("vehicule.create", ['error' => $this->conn->last_error]);
			throw new Exception(esc_html__("vehicle creation failed", 'parking-management'));
		}
		return $this->conn->insert_id;
	}

	public function isExists(int $order_id): int
	{
		if ($row = $this->conn->get_row(
			$this->conn->prepare("SELECT `id_vehicule` FROM `tbl_vehicule`
                     WHERE
                	`commande_id` = %d",
				[$order_id]
			), ARRAY_A)) {
			if ($row['id_vehicule'] != '')
				return (int)$row['id_vehicule'];
		}
		return 0;
	}

	public function read(int $order_id): array
	{
		$row = $this->conn->get_row($this->conn->prepare(
			"SELECT `id_vehicule` FROM `tbl_vehicule` WHERE `commande_id` = %d",
			[$order_id]),
			ARRAY_A
		);
		if (!$row) {
			Logger::error("vehicle.read", ['error' => $this->conn->last_error]);
			return [];
		}
		return $row;

	}

	private function getData(string|null $field = null)
	{
		if (is_null($field) || !array_key_exists($field, $this->data))
			return '';
		return $this->data[$field];
	}

}
