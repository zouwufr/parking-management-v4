<?php

namespace ParkingManagement\interfaces;

interface IPayment
{
	public function __construct(array $config, int $order_id);
	public function pay(): string;
	public function redirect(): void;
}
