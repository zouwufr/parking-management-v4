<?php

namespace Notification;

use Exception;
use ParkingManagement\Logger;
use PHPMailer\PHPMailer\PHPMailer;

class Mail
{
	public static function send(array $mailConfig, $to, $subject, $message, $additional_headers = '', $charset = 'UTF-8', $attachmentPaths = [], $isHTML = true): bool
	{
		if (
			empty($mailConfig['host']['value'])
			|| empty($mailConfig['login']['value'])
			|| empty($mailConfig['password']['value'])
			|| empty($mailConfig['sender']['value'])
		) {
			Logger::error("mail.send", "mail is not configured");
			return false;
		}

		$mail = new PHPMailer(true); // Passing `true` enables exceptions
		try {
			// Server settings
			$mail->isSMTP(); // Set mailer to use SMTP
			$mail->Host = $mailConfig['host']['value']; // Specify main and backup SMTP servers
			$mail->SMTPAuth = true; // Enable SMTP authentication
			$mail->Username = $mailConfig['login']['value']; // SMTP username
			$mail->Password = $mailConfig['password']['value']; // SMTP password
			$mail->SMTPSecure = 'tls'; // Enable TLS encryption, `ssl` also accepted
			$mail->Port = 587; // TCP port to connect to

			// Set CharSet
			$mail->CharSet = $charset;

			// Default sender information - change as needed
			$defaultFromEmail = $mailConfig['sender']['value'];
			$defaultFromName = 'Contact Parking';

			// Parse additional headers for 'From' and set it
			$fromEmail = $defaultFromEmail;
			$fromName = $defaultFromName;
			if (!empty($additional_headers)) {
				$headers = preg_split("/\r?\n/", $additional_headers);
				foreach ($headers as $header) {
					if (stripos($header, 'From:') === 0) {
						$fromLine = trim(substr($header, 5)); // Remove "From:" part
						$fromParts = explode('<', $fromLine);
						if (count($fromParts) == 2) {
							$fromName = trim($fromParts[0]);
							$fromEmail = trim($fromParts[1], ' >');
						} else {
							$fromEmail = trim($fromParts[0]);
						}
						break; // Stop once 'From' is found
					} else if (strpos($header, ':')) {
						list($key, $value) = explode(':', $header, 2);
						$mail->addCustomHeader($key, trim($value));
					}
				}
			}

			$mail->setFrom($fromEmail, $fromName);

			// Recipients
			$mail->addAddress($to); // Add a recipient

			// Content
			$mail->isHTML($isHTML); // Set email format to plain text
			$mail->Subject = $subject;
			$mail->Body    = $message;

			foreach ($attachmentPaths as $attachmentPath) {
				if (!empty($attachmentPath) && file_exists($attachmentPath)) {
					$mail->addAttachment($attachmentPath);
				}
			}

			// Send the email
			$mail->send();
			Logger::info("mail.send", "mail was sent to $to");
			return true;
		} catch (Exception $e) {
			Logger::error("mail.send",  $e->getMessage());
			Logger::error("mail.send", "mail is failed: " . $mail->ErrorInfo);
			return false;
		}
	}
}
