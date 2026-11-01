<?php

namespace Booking;

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
		// Idempotent: a booking already mirrored for this order (live retry, or a
		// migration script re-run after a partial failure) is left alone rather than
		// duplicated.
		$existing = $client->select('bookings', [
			'reference_code' => 'eq.' . $reference_code,
			'select' => 'id',
			'limit' => '1',
		]);
		if (!empty($existing[0]['id'])) {
			Logger::info('supabase_sync.push', ['order_id' => $order_id, 'supabase_booking_id' => $existing[0]['id'], 'message' => 'already mirrored, skipping']);
			return;
		}

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

		$total_days = max(1, (int)($order['nb_jour'] ?? 1));
		$total_price = (float)($order['total'] ?? 0);
		$status = match ((int)($order['status'] ?? 0)) {
			OrderStatus::PENDING->value => 'pending',
			OrderStatus::PAID->value, OrderStatus::CONFIRMED->value => 'confirmed',
			OrderStatus::COMPLETED->value => 'checked_out',
			default => 'pending',
		};

		$booking = $client->insert('bookings', [
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
			'base_price_per_day' => round($total_price / $total_days, 2),
			'total_days' => $total_days,
			'subtotal' => $total_price,
			'total_price' => $total_price,
			'currency' => 'EUR',
			'status' => $status,
		]);

		$booking_id = is_array($booking) ? ($booking[0]['id'] ?? $booking['id'] ?? null) : null;
		if (empty($booking_id)) {
			Logger::error('supabase_sync.push', ['order_id' => $order_id, 'message' => 'bookings insert returned no id', 'response' => $booking]);
			return;
		}

		if ($parking_type === 2) {
			self::attachValetOption($client, $supabase_site_id, $booking_id, $order_id);
		}

		Logger::info('supabase_sync.push', ['order_id' => $order_id, 'supabase_booking_id' => $booking_id, 'message' => 'mirrored to Supabase']);
	}

	/**
	 * Valet is a booking_options line item on the Supabase side, not a slot_category -
	 * looked up by this project's own "valet" site_options.code convention per site
	 * (see admin Supabase settings description).
	 */
	private static function attachValetOption(SupabaseClient $client, string $supabase_site_id, string $booking_id, int $order_id): void
	{
		try {
			$options = $client->select('site_options', [
				'site_id' => 'eq.' . $supabase_site_id,
				'code' => 'eq.valet',
				'is_active' => 'eq.true',
				'select' => 'id,name,price',
				'limit' => '1',
			]);
			$option = $options[0] ?? null;
			if (empty($option['id'])) {
				Logger::info('supabase_sync.push', ['order_id' => $order_id, 'message' => "no 'valet' site_option provisioned for site {$supabase_site_id}, skipping option line"]);
				return;
			}
			$client->insert('booking_options', [
				'booking_id' => $booking_id,
				'site_option_id' => $option['id'],
				'option_name' => $option['name'] ?? 'Valet',
				'price' => (float)($option['price'] ?? 0),
				'quantity' => 1,
			]);
		} catch (Throwable $e) {
			Logger::error('supabase_sync.attachValetOption', ['order_id' => $order_id, 'error' => $e->getMessage()]);
		}
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
