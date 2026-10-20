<?php

namespace Booking;

class Terminal
{
	/**
	 * Returns the terminal list (value/label pairs) available for the given airport.
	 */
	public static function forAirport(Airport $airport): array
	{
		return match ($airport) {
			Airport::ROISSY => array(
				array('value' => "3", 'label' => 'Terminal 1'),
				array('value' => "4", 'label' => 'Terminal 3'),
				array('value' => "5", 'label' => 'Terminal 2A'),
				array('value' => "6", 'label' => 'Terminal 2B'),
				array('value' => "7", 'label' => 'Terminal 2C'),
				array('value' => "8", 'label' => 'Terminal 2D'),
				array('value' => "9", 'label' => 'Terminal 2E'),
				array('value' => "10", 'label' => 'Terminal 2F'),
				array('value' => "11", 'label' => 'Terminal 2G'),
			),
			default => array(
				array('value' => "1", 'label' => 'Terminal 4'),
				array('value' => "2", 'label' => 'Terminal 1,2,3'),
			),
		};
	}

	/**
	 * Returns a human-readable label for the given airport, used to group terminals.
	 */
	public static function label(Airport $airport): string
	{
		return $airport === Airport::ROISSY ? 'Roissy' : 'Orly';
	}

	/**
	 * Resolves an Airport case from its lowercase name (e.g. 'roissy', 'orly2').
	 * Falls back to Airport::ORLY when the key is unknown.
	 */
	public static function fromKey(string $key): Airport
	{
		foreach (Airport::cases() as $airport) {
			if (strtolower($airport->name) === strtolower($key)) {
				return $airport;
			}
		}
		return Airport::ORLY;
	}

	/**
	 * Returns the airports selectable for a site: just Orly and Roissy. Airport::ORLY2/
	 * ORLY3 still exist (external capacity grid / legacy bill-id lookups may use them)
	 * but aren't offered as a site's airport - they'd show the exact same Orly terminal
	 * list as Airport::ORLY anyway (see forAirport()).
	 */
	public static function airportOptions(): array
	{
		return array(
			array('value' => 'orly', 'label' => 'Orly'),
			array('value' => 'roissy', 'label' => 'Roissy'),
		);
	}

	/**
	 * Returns every known legacy parking "site" identity - i.e. which row of the legacy
	 * tbl_remplissage/tbl_commande grid (Airport::value, aka parking_id there) this plugin's
	 * site actually corresponds to. Deliberately separate from airportOptions(): a single
	 * physical airport can host more than one legally distinct parking business (e.g. Orly
	 * also hosts "CoverPark", Airport::ORLY2) - picking the airport alone isn't enough to
	 * tell those apart, see Order::resolveLegacySiteID().
	 */
	public static function legacySiteOptions(): array
	{
		return array(
			array('value' => (string)Airport::ORLY->value, 'label' => 'Orly'),
			array('value' => (string)Airport::ROISSY->value, 'label' => 'Roissy'),
			array('value' => (string)Airport::ORLY2->value, 'label' => 'Orly - CoverPark'),
			array('value' => (string)Airport::ORLY3->value, 'label' => 'Orly - Site 4'),
		);
	}
}
