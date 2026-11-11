<?php

namespace Booking;

use Exception;
use ParkingManagement\database\database;
use ParkingManagement\Logger;
use Throwable;

/**
 * Mirrors a just-created reservation into the shared booking-api Supabase project,
 * in addition to (never instead of) the legacy MySQL write, which stays the source
 * of truth. Best-effort: any failure here (missing config, unprovisioned site,
 * network error, Supabase-side validation error) is logged and swallowed - it must
 * never affect the customer's booking. Call this only after Order::create() and
 * Vehicle::create() have both already succeeded.
 */
class SupabaseSync
{
	public static function pushOrder(int $order_id, array $site): void
	{
		try {
			self::push($order_id, $site);
		} catch (Throwable $e) {
			Logger::error('supabase_sync.push', ['order_id' => $order_id, 'error' => $e->getMessage()]);
		}
	}

	private static function push(int $order_id, array $site): void
	{
		$supabase_site_id = trim((string)($site['supabase_site_id'] ?? ''));
		if ($supabase_site_id === '') {
			Logger::info('supabase_sync.push', ['order_id' => $order_id, 'message' => 'site has no supabase_site_id configured, skipping']);
			return;
		}

		$config = getParkingManagementInstance()->prop('supabase');
		$url = trim((string)($config['url'] ?? ''));
		$key = trim((string)($config['key'] ?? ''));
		if ($url === '' || $key === '') {
			Logger::info('supabase_sync.push', ['order_id' => $order_id, 'message' => 'Supabase is not configured, skipping']);
			return;
		}

		$order = (new Order())->read($order_id);
		$vehicle = self::readVehicle($order_id);
		if (empty($vehicle)) {
			Logger::error('supabase_sync.push', ['order_id' => $order_id, 'message' => 'no tbl_vehicule row for this order']);
			return;
		}
		$member = (new Member())->read((int)($order['membre_id'] ?? 0));

		$client = new SupabaseClient($url, $key);

		$reference_code = (string)($order['facture_id'] ?? ('PKMGMT-' . $order_id));

		$customer = $client->rpc('get_or_create_customer', [
			'p_first_name' => ucwords((string)($member['prenom'] ?? '')),
			'p_last_name' => ucwords((string)($member['nom'] ?? '')),
			'p_email' => strtolower((string)($member['email'] ?? '')),
			'p_phone' => (string)($member['tel_port'] ?? ''),
			'p_postal_code' => (string)($member['code_postal'] ?? ''),
		]);
		$customer_id = $customer['id'] ?? null;
		if (empty($customer_id)) {
			Logger::error('supabase_sync.push', ['order_id' => $order_id, 'message' => 'get_or_create_customer returned no id', 'response' => $customer]);
			return;
		}

		$parking_type = (int)($vehicle['parking_type'] ?? 0);
		// ext(0) -> outside, int(1) -> valet(2) -> inside. Valet has no dedicated
		// slot_category on the Supabase side (inside/outside only) - treated as
		// inside since it's attended/secure parking; the "Valet" booking_option line
		// item below is what actually records that it was a valet booking.
		$category = $parking_type === 0 ? 'outside' : 'inside';

		$slot_types = $client->select('slot_types', [
			'site_id' => 'eq.' . $supabase_site_id,
			'category' => 'eq.' . $category,
			'select' => 'id',
			'limit' => '1',
		]);
		$slot_type_id = $slot_types[0]['id'] ?? null;
		if (empty($slot_type_id)) {
			Logger::error('supabase_sync.push', ['order_id' => $order_id, 'message' => "no slot_type provisioned for site {$supabase_site_id} / category {$category}"]);
			return;
		}

		$type_id = VehicleType::fromInt((int)($vehicle['type_id'] ?? 1));
		$vehicle_category = match ($type_id) {
			VehicleType::CAR => 'car',
			VehicleType::MOTORCYCLE => 'moto',
			// booking-api's vehicle_category enum has no truck - van is the nearest fit.
			VehicleType::TRUCK => 'van',
		};

		$airport = Terminal::fromKey($site['airport'] ?? 'orly');
		$destination_airport = IATA::fromAirPort($airport)->name;

		$terminal = self::safeUnserialize((string)($order['terminal'] ?? ''));
		$terminal_options = Terminal::forAirport($airport);
		$departure_terminal = self::terminalLabel($terminal_options, $terminal['depart'] ?? null);
		$return_terminal = self::terminalLabel($terminal_options, $terminal['arrivee'] ?? null);

		$passengers = self::safeUnserialize((string)($order['nb_personne'] ?? ''));
		$passenger_count = max(1, (int)($passengers['aller'] ?? 1));

		$tz = new \DateTimeZone('Europe/Paris');
		// depart/arrivee are DATE columns (Y-m-d) and depart_heure/arrivee_heure are TIME
		// columns, which MySQL always round-trips as H:i:s regardless of what was inserted.
		$departure_at = \DateTime::createFromFormat('Y-m-d H:i:s', trim(($order['depart'] ?? '') . ' ' . ($order['depart_heure'] ?? '00:00:00')), $tz);
		$return_at = \DateTime::createFromFormat('Y-m-d H:i:s', trim(($order['arrivee'] ?? '') . ' ' . ($order['arrivee_heure'] ?? '00:00:00')), $tz);
		if (!$departure_at || !$return_at) {
			Logger::error('supabase_sync.push', ['order_id' => $order_id, 'message' => 'could not parse departure/return dates', 'order' => $order]);
			return;
		}

		$status = match ((int)($order['status'] ?? 0)) {
			OrderStatus::PENDING->value => 'pending',
			OrderStatus::PAID->value, OrderStatus::CONFIRMED->value => 'confirmed',
			OrderStatus::COMPLETED->value => 'checked_out',
			default => 'pending',
		};

		// create_booking is SECURITY DEFINER: the anon/publishable key cannot insert into
		// bookings or booking_options directly (RLS requires a company member). It also
		// prices the booking from Supabase's own slot_type_prices grid, so no price is sent.
		$payload = [
			'reference_code' => $reference_code,
			'site_id' => $supabase_site_id,
			'slot_type_id' => $slot_type_id,
			'customer_id' => $customer_id,
			'vehicle_category' => $vehicle_category,
			'license_plate' => (string)($vehicle['immatriculation'] ?? ''),
			'destination_airport' => $destination_airport,
			'passenger_count' => $passenger_count,
			'departure_at' => $departure_at->format(DATE_ATOM),
			'return_at' => $return_at->format(DATE_ATOM),
			'departure_terminal' => $departure_terminal,
			'return_terminal' => $return_terminal,
			'currency' => 'EUR',
			'status' => $status,
		];

		if ($parking_type === 2) {
			$valet_option_id = self::valetOptionId($client, $supabase_site_id, $order_id);
			if ($valet_option_id !== null) {
				$payload['options'] = [['site_option_id' => $valet_option_id, 'quantity' => 1]];
			}
		}

		try {
			$booking_id = $client->rpc('create_booking', ['payload' => $payload]);
		} catch (Exception $e) {
			// bookings.reference_code is unique: a retry of an already-mirrored order lands here.
			if ($e->getCode() === 409) {
				Logger::info('supabase_sync.push', ['order_id' => $order_id, 'reference_code' => $reference_code, 'message' => 'already mirrored, skipping']);
				return;
			}
			throw $e;
		}

		if (!is_string($booking_id) || $booking_id === '') {
			Logger::error('supabase_sync.push', ['order_id' => $order_id, 'message' => 'create_booking returned no id', 'response' => $booking_id]);
			return;
		}

		Logger::info('supabase_sync.push', ['order_id' => $order_id, 'supabase_booking_id' => $booking_id, 'message' => 'mirrored to Supabase']);
	}

	/**
	 * Valet is a booking_options line item on the Supabase side, not a slot_category -
	 * looked up by this project's own "valet" site_options.code convention per site.
	 */
	private static function valetOptionId(SupabaseClient $client, string $supabase_site_id, int $order_id): ?string
	{
		$options = $client->select('site_options', [
			'site_id' => 'eq.' . $supabase_site_id,
			'code' => 'eq.valet',
			'is_active' => 'eq.true',
			'select' => 'id',
			'limit' => '1',
		]);
		$id = $options[0]['id'] ?? null;
		if (empty($id)) {
			Logger::info('supabase_sync.push', ['order_id' => $order_id, 'message' => "no 'valet' site_option provisioned for site {$supabase_site_id}, skipping option line"]);
			return null;
		}
		return (string)$id;
	}

	private static function readVehicle(int $order_id): array
	{
		$conn = database::connect();
		if (!$conn) {
			return [];
		}
		$row = $conn->get_row(
			$conn->prepare(
				'SELECT `type_id`, `parking_type`, `modele`, `immatriculation` FROM `tbl_vehicule` WHERE `commande_id` = %d',
				[$order_id]
			),
			ARRAY_A
		);
		return $row ?: [];
	}

	private static function terminalLabel(array $terminal_options, mixed $value): ?string
	{
		if (empty($value)) {
			return null;
		}
		foreach ($terminal_options as $option) {
			if ((string)$option['value'] === (string)$value) {
				return $option['label'];
			}
		}
		return null;
	}

	private static function safeUnserialize(string $data): array
	{
		if ($data === '') {
			return [];
		}
		$result = @unserialize($data, ['allowed_classes' => false]);
		return is_array($result) ? $result : [];
	}
}
