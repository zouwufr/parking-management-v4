<?php

namespace Notification;

use Aws\Exception\AwsException;
use Aws\Sns\SnsClient;
use ParkingManagement\Logger;

class SMS {

	public static function send(array $sms, string $phone, string $message): bool
	{
		if (empty($message)) {
			Logger::error("Message cannot be empty");
			return false;
		}
		if (empty($sms)) {
			Logger::error("sms.send", "missing SMS configuration");
			return false;
		}

		return match ($sms['type'] ?? '') {
			'AWS' => self::aws($sms, $phone, $message),
			default => false
		};
	}

	private static function aws(array $sms, string $phone, string $message): bool
	{
		if ( empty($sms['user']) || empty($sms['password'])) {
			Logger::warning("sms.aws", "sms is not configuration");
			return false;
		}
		$awsConfig = [
			'credentials' => [
				'key' => $sms['user'],
				'secret' => $sms['password'],
			],
			'region' => $sms['region'] ?? 'eu-west-3',
			'version' => 'latest'
		];

		try {
			$snsClient = new SnsClient($awsConfig);
			$result = $snsClient->publish([
				'Message' => $message,
				'PhoneNumber' => $phone,
				'MessageAttributes' => [
					'AWS.SNS.SMS.SenderID' => [
						'DataType' => 'String',
						'StringValue' => $sms['sender'] ?? 'PARKING'
					],
					'AWS.SNS.SMS.SMSType' => [
						'DataType' => 'String',
						'StringValue' => 'Transactional'
					],
				]
			]);
			Logger::info('sms.aws.sent', ['messageId' => $result['MessageId'] ?? null]);
			return true;
		} catch (AwsException $e) {
			Logger::error("sms.aws",  $e->getMessage());
			return false;
		}
	}

}
