<?php

namespace Booking;

use Exception;
use ParkingManagement\database\database;
use ParkingManagement\Logger;
use wpdb;

enum MemberStatus: int
{
	case ADMIN = 1;
	case SECRETARY = 2;
	case EMPLOYEE = 3;
	case AGENCY_PARTNER = 4;
	case AGENCY = 5;
	case CUSTOMER = 6;
}

class Member
{
	private wpdb $conn;

	private array $member;

	public function getMember(): array
	{
		return $this->member;
	}

	public function __construct()
	{
		if (!$conn = database::connect()) {
			Logger::error("member.database.connect", database::getError());
			return;
		}
		$this->conn = $conn;
	}

	public function isMemberExists($email): bool|string
	{
		try {
			$sql = "SELECT `id_membre` FROM `tbl_membre` WHERE `email` = %s";
			if (!$row = $this->conn->get_row($this->conn->prepare($sql, [$email]), ARRAY_A))
				return false;
			return $row["id_membre"];
		} catch (Exception $e) {
			$post = array_merge($_GET, $_POST);
			Logger::error("member.isMemberExists", ['data' => $post, 'error' => $e->getMessage()]);
			return false;
		}
	}

	/**
	 * Creates a new member record
	 * @return string
	 * @throws Exception
	 */
	public function create(): string
	{
		try {
			$post = array_merge($_GET, $_POST);

			// Log the incoming request data
			Logger::info("member.create.request", [
				'data' => array_diff_key($post, array_flip(['password'])) // Log request data excluding sensitive fields
			]);

			$password = generatePassword(8);

			$this->member = [
				'status' => MemberStatus::CUSTOMER->value,
				'date' => date('Y-m-d'),
				'email' => strtolower($post['email']),
				'password' => strrev(md5($password)),
				'nom' => ucwords($post['nom']),
				'prenom' => ucwords($post['prenom']),
				'code_postal' => $post['code_postal'],
				'ville' => ucwords($post['ville']),
				'pays' => !empty($post['pays']) ? $post['pays'] : 'n/c',
				'tel_fixe' => NULL,
				'tel_port' => $post['tel_port'],
				'tva' => '',
				'url' => slug($post['prenom'] . ' ' . $post['nom'] . ' ' . $post['ville']),
				'afficher' => 1
			];

			Logger::info("member.create.request", ["member" => $this->member]);
			// Validate required fields
			$requiredFields = ['email', 'nom', 'prenom', 'code_postal'];
			foreach ($requiredFields as $field) {
				if (empty($this->member[$field])) {
					Logger::error("member.create.validation", [
						'missing_field' => $field,
						'data' => array_diff_key($this->member, array_flip(['password']))
					]);
					throw new Exception("Required field missing: $field");
				}
			}
			if ($this->conn->insert('tbl_membre', $this->member) === false ) {
				Logger::error("member.create.execute", [
					'errorInfo' => $this->conn->last_error,
					'data' => array_diff_key($this->member, array_flip(['password']))
				]);
				throw new Exception(__("Failed to create member", 'parking-management'));
			}

			$memberId = $this->conn->insert_id;

			// Log successful creation
			Logger::info("member.create.success", [
				'member_id' => $memberId,
				'email' => $this->member['email']
			]);

			return $memberId;

		} catch (Exception $e) {
			Logger::error("member.create.general", [
				'error' => $e->getMessage(),
				'data' => array_diff_key($this->member ?? [], array_flip(['password']))
			]);
			throw $e;
		}
	}

	/**
	 * @throws Exception
	 */
	public function read(int $member_id): array
	{
		$query = 'SELECT `id_membre`
     , `status`, `date`, `email`
     , `nom`, `prenom`
     , `code_postal`, `ville`, `pays`
     , `tel_fixe`, `tel_port`
		FROM `tbl_membre` WHERE id_membre = %d';
		if (!$row = $this->conn->get_row(
			$this->conn->prepare($query, [$member_id]),
			ARRAY_A
		)) {
			throw new Exception("failed to read member");
		}
		return $row;
	}

	/**
	 * Updates specific fields of a member record
	 * @param int $member_id The ID of the member to update
	 * @param array $fields Associative array of fields to update and their new values
	 * @return bool
	 */
	public function patch(int $member_id, array $fields): bool
	{
		// Define allowable fields for updating
		$allowedFields = [
			'code_postal', 'ville', 'pays',
			'tel_fixe', 'tel_port'
		];

		// Filter out any fields that aren't in the allowed list
		$updateFields = array_intersect_key($fields, array_flip($allowedFields));

		if (empty($updateFields)) {
			Logger::error("member.patch", [
				"message" => "No valid fields provided for update",
				'member_id' => $member_id,
				'fields' => $fields,
			]);
//			throw new Exception("No valid fields provided for update");
			return false;
		}

		try {
			// Format specific fields if needed
			if (isset($updateFields['email'])) {
				$updateFields['email'] = strtolower($updateFields['email']);
			}
			if (isset($updateFields['nom'])) {
				$updateFields['nom'] = ucwords($updateFields['nom']);
			}
			if (isset($updateFields['prenom'])) {
				$updateFields['prenom'] = ucwords($updateFields['prenom']);
			}
			if (isset($updateFields['ville'])) {
				$updateFields['ville'] = ucwords($updateFields['ville']);
			}

			$where = ['id_membre' => $member_id];
			$result = $this->conn->update(
				'tbl_membre',
				$updateFields,
				$where
			);


			if ($result === false) {
				Logger::error("member.patch", [
					'member_id' => $member_id,
					'fields' => $fields,
					'errorInfo' => $this->conn->last_error
				]);
				return false;
			}
			Logger::info("member.patch", "Member updated");
			return true;
		} catch (Exception $e) {
			Logger::error("member.patch", [
				'member_id' => $member_id,
				'fields' => $fields,
				'error' => $e->getMessage()
			]);
			return false;
		}
	}

}
