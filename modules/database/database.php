<?php

namespace ParkingManagement\database;

use ParkingManagement\Logger;
use wpdb;

class database {

	private static array $error = [];

	// TODO: Add a test connection
	public static function connect(): bool|wpdb
	{
		$pm = getParkingManagementInstance();
		if (!$pm) {
			self::$error = array(
				'code' => '0001',
				'message' => 'fail to get Parking management instance'
			);
			Logger::error("database.connect", self::$error);
			return false;
		}
		$database = $pm->prop('database');
		if (empty($database)
			|| (!array_key_exists('host', $database)) || empty($database['host'])
			|| (!array_key_exists('port', $database)) || empty($database['port'])
			|| (!array_key_exists('name', $database)) || empty($database['name'])
			|| (!array_key_exists('user', $database)) || empty($database['user'])
			|| (!array_key_exists('password', $database)) || empty($database['password'])
		)
		{
			self::$error = array(
				'code' => '0002',
				'message' => 'Database configuration error',
			);
			Logger::error("database.connect", self::$error);
			return false;
		}

			$dsn = new wpdb($database['user'], $database['password'], $database['name'], "{$database['host']}:{$database['port']}" );
			$dsn->hide_errors();

		return $dsn;
	}
	/**
	 * Whether $table has $column, cached per request. Lets new columns be written
	 * only once the matching SQL migration has run on that booking database, so a
	 * plugin deployed ahead of the migration keeps taking bookings.
	 */
	public static function hasColumn(wpdb $conn, string $table, string $column): bool
	{
		static $cache = [];
		$key = $conn->dbname . '.' . $table . '.' . $column;
		if (!array_key_exists($key, $cache)) {
			$cache[$key] = (bool)$conn->get_var($conn->prepare("SHOW COLUMNS FROM `$table` LIKE %s", [$column]));
		}
		return $cache[$key];
	}

	public static function getError(): array
	{
		return self::$error;
	}


}
