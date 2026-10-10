<?php

use ParkingManagement\ParkingManagement;
use enshrined\svgSanitize\Sanitizer;

/**
 * Include a file under PKMGMT_PLUGIN_MODULES_DIR.
 *
 * @param string $path File path relative to the module dir.
 * @return bool True on success, false on failure.
 */
function pkmgmt_include_module_file(string $path): bool
{
	$dir = PKMGMT_PLUGIN_MODULES_DIR;

	if (empty($dir) or !is_dir($dir)) {
		return false;
	}

	$path = path_join($dir, ltrim($path, DS));

	if (file_exists($path)) {
		include_once $path;
		return true;
	}

	return false;
}

function pkmgmt_get_request_uri(): string
{
	static $request_uri = '';

	if (empty($request_uri))
		$request_uri = add_query_arg(array());
	return sanitize_url($request_uri);
}

function pkmgmt_register_post_type(): bool
{
	if (class_exists('ParkingManagement')) {
		ParkingManagement::register_post_type();
		return true;
	} else {
		return false;
	}
}


/**
 * Converts multi-dimensional array to a flat array.
 *
 * @param mixed $input Array or item of array.
 * @return array Flatten array.
 */
function pkmgmt_array_flatten(mixed $input): array
{
	if (!is_array($input)) {
		return array($input);
	}

	$output = array();

	foreach ($input as $value) {
		$output = array_merge($output, pkmgmt_array_flatten($value));
	}

	return $output;
}


/**
 * Navigates through an array, object, or scalar, and
 * normalizes newline characters in the value.
 *
 * @param mixed $input The array or string to be processed.
 * @param string $to Optional. The newline character that is used in the output.
 * @return string|array Processed value.
 */
function pkmgmt_normalize_newline_deep(mixed $input, string $to = "\n"): mixed
{
	if (is_array($input)) {
		$result = array();

		foreach ($input as $key => $text) {
			$result[$key] = pkmgmt_normalize_newline_deep($text, $to);
		}

		return $result;
	}

	// Non-string scalars (null, int, bool - e.g. a value missing from an
	// older/shape-mismatched config during a migration) pass through unchanged
	// instead of fataling against pkmgmt_normalize_newline()'s string type hint.
	return is_string($input) ? pkmgmt_normalize_newline($input, $to) : $input;
}

/**
 * Normalizes newline characters.
 *
 * @param string $text Input text.
 * @param string $to Optional. The newline character that is used in the output.
 * @return string Normalized text.
 */
function pkmgmt_normalize_newline(string $text, string $to = "\n"): string
{
	$nls = array("\r\n", "\r", "\n");

	if (!in_array($to, $nls)) {
		return $text;
	}

	return str_replace($nls, $to, $text);
}

/**
 * Print the object wrapped into <pre></pre> and call exit by default
 *
 * @param mixed $object
 * @param bool $out Default: true
 * @return void
 */
function print_log(mixed $object, bool $out = true): void
{
	print "<pre>";
	print_r($object);
	print "</pre>";
	if ($out)
		exit(1);
}

function get_post_id_by_post_type(string $post_type): int
{
	$post_ids = get_posts(array(
		'post_type' => $post_type,
		'numberposts' => 1,
		'fields' => 'ids',
	));
	if (empty($post_ids))
		return 0;
	return $post_ids[0];
}

function pkmgmt_plugin_url(string $path): string
{
	$url = plugins_url($path, PKMGMT_PLUGIN);
	if (is_ssl() and str_starts_with($url, 'http:')) {
		$url = 'https:' . substr($url, 5);
	}

	return $url;
}

// Generate a password
function generatePassword($length = 6): string
{
	$password = "";
	$possible = "0123456789abcdfghjkmnpqrstvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
	$i = 0;
	while ($i < $length) {
		$char = $possible[mt_rand(0, strlen($possible) - 1)];
		// we don't want this character if it's already in the password
		if (!strstr($password, $char)) {
			$password .= $char;
			$i++;
		}
	}
	return $password;
}

// Simplify a string
function slug($to_slug, $separator = '-'): array|string|null
{
	$to_slug = strip_tags(html_entity_decode($to_slug));
	$accented = array('&', 'À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ă', 'Ą', 'Ç', 'Ć', 'Č', 'Œ',
		'Ď', 'Đ', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ă', 'ą', 'ç', 'ć', 'č', 'œ', 'ď', 'đ',
		'È', 'É', 'Ê', 'Ë', 'Ę', 'Ě', 'Ğ', 'Ì', 'Í', 'Î', 'Ï', 'İ', 'Ĺ', 'Ľ', 'Ł', 'è', 'é',
		'ê', 'ë', 'ę', 'ě', 'ğ', 'ì', 'í', 'î', 'ï', 'ı', 'ĺ', 'ľ', 'ł', 'Ñ', 'Ń', 'Ň', 'Ò',
		'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ő', 'Ŕ', 'Ř', 'Ś', 'Ş', 'Š', 'ñ', 'ń', 'ň', 'ò', 'ó', 'ô',
		'ö', 'ø', 'ő', 'ŕ', 'ř', 'ś', 'ş', 'š', '$', 'Ţ', 'Ť', 'Ù', 'Ú', 'Û', 'Ų', 'Ü', 'Ů',
		'Ű', 'Ý', 'ß', 'Ź', 'Ż', 'Ž', 'ţ', 'ť', 'ù', 'ú', 'û', 'ų', 'ü', 'ů', 'ű', 'ý', 'ÿ',
		'ź', 'ż', 'ž', 'А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М',
		'Н', 'О', 'П', 'Р', 'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л',
		'м', 'н', 'о', 'р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э',
		'Ю', 'Я', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я');
	$replace = array('et', 'A', 'A', 'A', 'A', 'A', 'A', 'AE', 'A', 'A', 'C', 'C', 'C', 'OE',
		'D', 'D', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'a', 'a', 'c', 'c', 'c', 'oe', 'd', 'd',
		'E', 'E', 'E', 'E', 'E', 'E', 'G', 'I', 'I', 'I', 'I', 'I', 'L', 'L', 'L', 'e', 'e',
		'e', 'e', 'e', 'e', 'g', 'i', 'i', 'i', 'i', 'i', 'l', 'l', 'l', 'N', 'N', 'N', 'O',
		'O', 'O', 'O', 'O', 'O', 'O', 'R', 'R', 'S', 'S', 'S', 'n', 'n', 'n', 'o', 'o', 'o',
		'o', 'o', 'o', 'r', 'r', 's', 's', 's', 's', 'T', 'T', 'U', 'U', 'U', 'U', 'U', 'U',
		'U', 'Y', 'Y', 'Z', 'Z', 'Z', 't', 't', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'y', 'y',
		'z', 'z', 'z', 'A', 'B', 'B', 'r', 'A', 'E', 'E', 'X', '3', 'N', 'N', 'K', 'N', 'M',
		'H', 'O', 'N', 'P', 'a', 'b', 'b', 'r', 'a', 'e', 'e', 'x', '3', 'n', 'n', 'k', 'n',
		'm', 'h', 'o', 'p', 'C', 'T', 'Y', 'O', 'X', 'U', 'u', 'W', 'W', 'b', 'b', 'b', 'E',
		'O', 'R', 'c', 't', 'y', 'o', 'x', 'u', 'u', 'w', 'w', 'b', 'b', 'b', 'e', 'o', 'r ');
	$slug = str_replace($accented, $replace, $to_slug);

	$search = array('@[ ]@i', '@[^a-zA-Z0-9_-]@');
	$replace = array($separator, '');
	return preg_replace(
		'/(?:([' . $separator . '])\1)\1*/',
		'$1',
		trim(
			strtolower(preg_replace($search, $replace, $slug)),
			$separator
		)
	);
}

function sansAccent($subject): array|string
{
	$subject = strip_tags(html_entity_decode($subject));

	$search = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ă', 'Ą', 'Ç', 'Ć', 'Č',
		'Œ', 'Ď', 'Đ', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ă', 'ą', 'ç', 'ć', 'č',
		'œ', 'ď', 'đ', 'È', 'É', 'Ê', 'Ë', 'Ę', 'Ě', 'Ğ', 'Ì', 'Í', 'Î', 'Ï', 'İ',
		'Ĺ', 'Ľ', 'Ł', 'è', 'é', 'ê', 'ë', 'ę', 'ě', 'ğ', 'ì', 'í', 'î', 'ï', 'ı',
		'ĺ', 'ľ', 'ł', 'Ñ', 'Ń', 'Ň', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ő', 'Ŕ', 'Ř',
		'Ś', 'Ş', 'Š', 'ñ', 'ń', 'ň', 'ò', 'ó', 'ô', 'ö', 'ø', 'ő', 'ŕ', 'ř', 'ś',
		'ş', 'š', 'Ţ', 'Ť', 'Ù', 'Ú', 'Û', 'Ų', 'Ü', 'Ů', 'Ű', 'Ý', 'ß', 'Ź', 'Ż',
		'Ž', 'ţ', 'ť', 'ù', 'ú', 'û', 'ų', 'ü', 'ů', 'ű', 'ý', 'ÿ', 'ź', 'ż', 'ž',
		'А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н',
		'О', 'П', 'Р', 'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к',
		'л', 'м', 'н', 'о', 'р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ',
		'Ы', 'Ь', 'Э', 'Ю', 'Я', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ',
		'ы', 'ь', 'э', 'ю', 'я');
	$replace = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'A', 'A', 'C', 'C', 'C',
		'OE', 'D', 'D', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'a', 'a', 'c', 'c', 'c',
		'oe', 'd', 'd', 'E', 'E', 'E', 'E', 'E', 'E', 'G', 'I', 'I', 'I', 'I', 'I',
		'L', 'L', 'L', 'e', 'e', 'e', 'e', 'e', 'e', 'g', 'i', 'i', 'i', 'i', 'i',
		'l', 'l', 'l', 'N', 'N', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'R', 'R',
		'S', 'S', 'S', 'n', 'n', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'r', 'r', 's',
		's', 's', 'T', 'T', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'Y', 'Y', 'Z', 'Z',
		'Z', 't', 't', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'y', 'y', 'z', 'z', 'z',
		'A', 'B', 'B', 'r', 'A', 'E', 'E', 'X', '3', 'N', 'N', 'K', 'N', 'M', 'H',
		'O', 'N', 'P', 'a', 'b', 'b', 'r', 'a', 'e', 'e', 'x', '3', 'n', 'n', 'k',
		'n', 'm', 'h', 'o', 'p', 'C', 'T', 'Y', 'O', 'X', 'U', 'u', 'W', 'W', 'b',
		'b', 'b', 'E', 'O', 'R', 'c', 't', 'y', 'o', 'x', 'u', 'u', 'w', 'w', 'b',
		'b', 'b', 'e', 'o', 'r ');
	return str_replace($search, $replace, $subject);
}


function getParkingManagementInstance(): ParkingManagement|bool
{
	$id = get_post_id_by_post_type(ParkingManagement::post_type);
	if (!$id)
		return false;
	return ParkingManagement::get_instance($id);
}

function replacePlaceholders($string, $replacements): string
{
	foreach ($replacements as $key => $value) {
		$string = str_replace("{" . $key . "}", $value, $string);
	}
	return $string;
}

function replacementData(string $context, array $data): array
{
	$rep = [];
	foreach ($data as $key => $value) {
		$rep["{$context}_".$key] = $value;
	}
	return $rep;
}

function sanitize_svg($svg)
{
	$sanitizer = new Sanitizer();
	$sanitized_svg = $sanitizer->sanitize($svg);
	return $sanitized_svg ?: $svg;
}

function inline_svg($file_path)
{
	if (file_exists($file_path)) {
		$svg = file_get_contents($file_path);
		return sanitize_svg($svg);
	}
	return 'SVG not found';
}

function isEmptyOrTrue(mixed $value): bool
{
	return empty($value) || $value === "true";
}
