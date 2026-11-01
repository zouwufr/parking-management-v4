<?php

namespace Booking;

use Exception;

/**
 * Thin REST wrapper around a Supabase project (PostgREST + RPC), used to mirror
 * reservations into the shared booking-api schema. Auth is the project's API key
 * only - no Supabase Auth session, matching booking-api's own public-booking RLS
 * policies (see SupabaseSync).
 */
class SupabaseClient
{
	private string $url;
	private string $key;

	public function __construct(string $url, string $key)
	{
		$this->url = rtrim($url, '/');
		$this->key = $key;
	}

	/**
	 * Calls a Postgres RPC function. Returns its decoded JSON result.
	 *
	 * @throws Exception
	 */
	public function rpc(string $name, array $args): mixed
	{
		return $this->request('POST', "/rest/v1/rpc/{$name}", $args);
	}

	/**
	 * Inserts a row and returns the inserted row (Prefer: return=representation).
	 *
	 * @throws Exception
	 */
	public function insert(string $table, array $row): mixed
	{
		return $this->request('POST', "/rest/v1/{$table}", $row);
	}

	/**
	 * Selects rows from a table. $query is passed through as PostgREST query params,
	 * e.g. ['site_id' => 'eq.<uuid>', 'select' => 'id', 'limit' => '1'].
	 *
	 * @throws Exception
	 */
	public function select(string $table, array $query = []): array
	{
		$path = "/rest/v1/{$table}";
		if (!empty($query)) {
			$path .= '?' . http_build_query($query);
		}
		$result = $this->request('GET', $path, null);
		return is_array($result) ? $result : [];
	}

	/**
	 * @throws Exception
	 */
	private function request(string $method, string $path, ?array $body): mixed
	{
		$args = [
			'method' => $method,
			'timeout' => 5,
			'headers' => [
				'apikey' => $this->key,
				'Authorization' => 'Bearer ' . $this->key,
				'Content-Type' => 'application/json',
				'Prefer' => 'return=representation',
			],
		];
		if ($body !== null) {
			$args['body'] = wp_json_encode($body);
		}

		$response = wp_remote_request($this->url . $path, $args);
		if (is_wp_error($response)) {
			throw new Exception('Supabase request failed: ' . $response->get_error_message());
		}

		$code = wp_remote_retrieve_response_code($response);
		$raw = wp_remote_retrieve_body($response);
		if ($code < 200 || $code >= 300) {
			throw new Exception("Supabase request to {$path} returned HTTP {$code}: {$raw}");
		}

		if ($raw === '') {
			return null;
		}
		$decoded = json_decode($raw, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new Exception('Supabase response was not valid JSON: ' . json_last_error_msg());
		}
		return $decoded;
	}
}
