<?php

namespace Price;

use ParkingManagement\Html;
use ParkingManagement\interfaces\IParkingmanagement;
use ParkingManagement\ParkingManagement;
use ParkingManagement\TariffGrid;

class Page implements IParkingmanagement
{

	private array $sites = [];

	private ParkingManagement $pm;

	public function __construct(ParkingManagement $pm)
	{
		$this->pm = $pm;
	}

	public function setSites(array $sites): void
	{
		$this->sites = $sites;
	}

	public function table(): string
	{
		return Html::_div(
			array('class' => 'row'),
			$this->elem($this->siteColumns()),
			$this->allOptions(),
		);
	}

	/**
	 * Collects, across every active site and every parking type it offers, the
	 * columns of the single comparison table - a site with several parking types
	 * enabled gets one column per type, but today every site only ever enables one.
	 */
	private function siteColumns(): array
	{
		$parkingTypes = [
			'ext' => esc_html__('Outside lots', 'parking-management'),
			'int' => esc_html__('Inside lots', 'parking-management'),
			'valet' => esc_html__('Valet', 'parking-management'),
		];

		$columns = [];
		$today = current_time('Y-m-d');
		foreach ($this->sites as $site) {
			$tariffs = TariffGrid::onDate($site, $today);
			foreach ($parkingTypes as $parkingKey => $typeLabel) {
				if (($site['type'][$parkingKey] ?? '0') !== '1') {
					continue;
				}
				$tariff = $tariffs['car'][$parkingKey] ?? [];
				if (empty($tariff)) {
					continue;
				}
				$columns[] = [
					'name' => $site['name'] ?? '',
					'type' => $typeLabel,
					'tariff' => $tariff,
					'high_season' => $site['high_season']['price'] ?? 0,
					'color' => self::siteColor($site['name'] ?? ''),
				];
			}
		}
		return $columns;
	}

	private function elem(array $columns): string
	{
		if (empty($columns)) {
			return '';
		}

		$days = [];
		foreach ($columns as $column) {
			foreach ($column['tariff'] as $day => $price) {
				if (is_numeric($day) && !empty($price)) {
					$days[(int)$day] = true;
				}
			}
		}
		ksort($days);
		$days = array_keys($days);

		$headerCells = ['<th class="align-middle text-center bg-primary col-1">' . esc_html__('Number of days', 'parking-management') . '</th>'];
		foreach ($columns as $column) {
			$style = 'background-color: ' . esc_attr($column['color']) . '; color: ' . esc_attr(self::readableTextColor($column['color'])) . ';';
			$headerCells[] = '<th class="align-middle text-center" style="' . $style . '">' . esc_html($column['name'])
				. '<br><small class="fw-normal">' . esc_html($column['type']) . '</small>'
				. ($column['high_season'] ? '<br><small class="fw-normal">' . sprintf(esc_html__('+%s€ high season', 'parking-management'), $column['high_season']) . '</small>' : '')
				. '</th>';
		}

		$rows = [];
		foreach ($days as $day) {
			$cells = ['<th class="align-middle text-center" scope="row">' . $day . '</th>'];
			foreach ($columns as $column) {
				$price = $column['tariff'][$day] ?? null;
				$cells[] = '<td class="align-middle text-center">' . (is_numeric($price) ? esc_html($price) . '€' : '—') . '</td>';
			}
			$rows[] = '<tr>' . implode(PHP_EOL, $cells) . '</tr>';
		}

		return Html::_div(array('class' => 'col'),
			Html::_div(
				array('class' => 'table-responsive'),
				'<table class="table table-light table-hover table-striped align-middle text-center caption-top">',
				'<caption>' . esc_html__('Prices', 'parking-management') . '</caption>',
				'<thead class="table-dark">',
				'<tr>',
				implode(PHP_EOL, $headerCells),
				'</tr>',
				'</thead>',
				'<tbody>',
				implode(PHP_EOL, $rows),
				'</tbody>',
				'</table>',
			),
		);
	}

	private function allOptions(): string
	{
		$perSite = [];
		foreach ($this->sites as $site) {
			$list = $this->optionsList($site['form']['options'] ?? []);
			if (empty($list)) {
				continue;
			}
			$perSite[] = ['name' => $site['name'] ?? '', 'list' => $list];
		}
		if (empty($perSite)) {
			return '';
		}

		// When every site offers the exact same options at the same prices, repeating
		// the identical list once per site is just noise - show it a single time.
		$distinct = array_unique(array_map(static fn($s) => serialize($s['list']), $perSite));
		if (count($distinct) === 1 && count($perSite) > 1) {
			return Html::_div(
				array('class' => 'col-12 mt-5'),
				'<h3 class="option">' . esc_html__('Options price', 'parking-management') . '</h3>',
				Html::_ul(array('class' => 'list-group list-group-flush'), ...$perSite[0]['list'])
			);
		}

		$blocks = [];
		foreach ($perSite as $site) {
			$blocks[] = Html::_div(
				array('class' => 'col-12 mt-4'),
				'<h4>' . esc_html($site['name']) . '</h4>',
				Html::_ul(array('class' => 'list-group list-group-flush'), ...$site['list'])
			);
		}
		return Html::_div(
			array('class' => 'col-12 mt-5'),
			'<h3 class="option">' . esc_html__('Options price', 'parking-management') . '</h3>',
			...$blocks
		);
	}

	/**
	 * A stable color per site name, matching the palette already used site-wide in
	 * parkineo's own admin (src/include/css/admin.css) so the same site reads the
	 * same color everywhere. Falls back to a deterministic hash-based color for any
	 * site name outside that known palette.
	 */
	private static function siteColor(string $name): string
	{
		$known = [
			'orly' => '#62a3c3',
			'roissy' => '#e97445',
			'zaventem' => '#D000FF',
			'coverpark' => 'green',
			'dupont' => '#d4a017',
		];
		$key = strtolower(remove_accents($name));
		$key = preg_replace('/[^a-z0-9]/', '', $key);
		if (isset($known[$key])) {
			return $known[$key];
		}
		$hue = hexdec(substr(md5($key), 0, 2)) / 255 * 360;
		return "hsl({$hue}, 55%, 55%)";
	}

	/**
	 * Picks black or white text for readable contrast against a given CSS color,
	 * using the actual RGB for hex colors and a safe default for named/hsl colors.
	 */
	private static function readableTextColor(string $color): string
	{
		if (preg_match('/^#([0-9a-f]{6})$/i', $color, $m)) {
			$r = hexdec(substr($m[1], 0, 2));
			$g = hexdec(substr($m[1], 2, 2));
			$b = hexdec(substr($m[1], 4, 2));
			$luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
			return $luminance > 0.6 ? '#000' : '#fff';
		}
		return '#fff';
	}

	private function optionsList(array $options): array
	{
		$ul_contents = [];
		if (($options['holiday']['enabled'] ?? '0') === '1') {
			$ul_contents[] = Html::_li(array('class' => 'list-group-item'),
				esc_html__("Holidays (on leave and/or return)", 'parking-management') . " <strong>{$options['holiday']['price']} €</strong>"
			);
		}

		if (($options['ev_charging']['enabled'] ?? '0') === '1') {
			$ul_contents[] = Html::_li(array('class' => 'list-group-item'),
				esc_html__("EV Charge", 'parking-management') . " <strong>{$options['ev_charging']['price']} €</strong>"
			);
		}

		if (($options['night_extra_charge']['enabled'] ?? '0') === '1') {
			$ul_contents[] = Html::_li(array('class' => 'list-group-item'),
				esc_html__("Night extra charge arrival", 'parking-management') . " ({$options['night_extra_charge']['start']}-{$options['night_extra_charge']['end']}) <strong>{$options['night_extra_charge']['price']} €</strong>"
			);
		}

		if (($options['keep_keys']['enabled'] ?? '0') === '1') {
			$ul_contents[] = Html::_li(array('class' => 'list-group-item'),
				esc_html__("Keep your keys", 'parking-management') . " <strong>{$options['keep_keys']['price']} €</strong>"
			);
		}

		if (($options['late']['enabled'] ?? '0') === '1') {
			$ul_contents[] = Html::_li(array('class' => 'list-group-item'),
				esc_html__("Days late", 'parking-management') . " <strong>{$options['late']['price']} €</strong>"
			);
		}

		if (($options['extra_baggage']['enabled'] ?? '0') === '1') {
			$ul_contents[] = Html::_li(array('class' => 'list-group-item'),
				esc_html__("Fees for extra baggage more than 2 per person", 'parking-management') .
				" <strong>{$options['extra_baggage']['price']} €</strong> " . esc_html__("per extra baggage", "parking-management")
			);
		}

		if (($options['oversize_baggage']['enabled'] ?? '0') === '1') {
			$ul_contents[] = Html::_li(array('class' => 'list-group-item'),
				esc_html__("Oversize baggage", 'parking-management') . " <strong>{$options['oversize_baggage']['price']} €</strong>"
			);
		}

		if (($options['cancellation_insurance']['enabled'] ?? '0') === '1') {
			$ul_contents[] = Html::_li(array('class' => 'list-group-item'),
				esc_html__("Cancellation insurance", 'parking-management') . " <strong>{$options['cancellation_insurance']['price']} €</strong>"
			);
		}

		if (($options['shuttle']['enabled'] ?? '0') === '1') {
			$ul_contents[] = Html::_li(array('class' => 'list-group-item'),
				esc_html__("Shuffle with more than 4 persons", 'parking-management') . " <strong>{$options['shuttle']['price']} € / "
				. esc_html__("person", 'parking-management') . "</strong>"
			);
		}
		if (($options['forgetting']['enabled'] ?? '0') === '1') {
			$ul_contents[] = Html::_li(array('class' => 'list-group-item'),
				esc_html__("Things forgotten in the vehicle", 'parking-management') . " <strong>{$options['forgetting']['price']} €</strong>"
			);
		}

		return $ul_contents;
	}
}
