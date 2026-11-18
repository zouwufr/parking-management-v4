<?php

namespace Booking;

use InvalidArgumentException;

enum VehicleType: int
{
	case CAR = 1;
	case MOTORCYCLE = 2;
	case TRUCK = 3;

	public static function fromInt(int $value): ?self
	{
		foreach (self::cases() as $case) {
			if ($case->value === $value) {
				return $case;
			}
		}
		throw new InvalidArgumentException("Invalid value for VehicleType enum: $value");
	}

	/**
	 * A submitted vehicle type, defaulting to a car when it is missing, 0 or unknown
	 * (same type for pricing and for the tbl_vehicule row the legacy admin reprices from).
	 */
	public static function fromSubmitted(mixed $value): self
	{
		if (is_numeric($value)) {
			try {
				return self::fromInt((int)$value);
			} catch (InvalidArgumentException) {
				// fall through to CAR
			}
		}
		return self::CAR;
	}
}
