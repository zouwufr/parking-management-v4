<?php

namespace Price;

use ParkingManagement\Html;
use ParkingManagement\interfaces\IParkingmanagement;
use ParkingManagement\ParkingManagement;

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
		foreach ($this->sites as $site) {
			foreach ($parkingTypes as $parkingKey => $typeLabel) {
				if (($site['type'][$parkingKey] ?? '0') !== '1') {
					continue;
				}
				$tariff = $site['tariffs']['car'][$parkingKey] ?? [];
				if (empty($tariff)) {
					continue;
				}
				$columns[] = [
					'name' => $site['name'] ?? '',
					'type' => $typeLabel,
					'tariff' => $tariff,
					'high_season' => $site['high_season']['price'] ?? 0,
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
			$headerCells[] = '<th class="align-middle text-center bg-primary">' . esc_html($column['name'])
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
		$blocks = [];
		foreach ($this->sites as $site) {
			$list = $this->optionsList($site['form']['options'] ?? []);
			if (empty($list)) {
				continue;
			}
			$blocks[] = Html::_div(
				array('class' => 'col-12 mt-4'),
				'<h4>' . esc_html($site['name'] ?? '') . '</h4>',
				Html::_ul(array('class' => 'list-group list-group-flush'), ...$list)
			);
		}
		if (empty($blocks)) {
			return '';
		}
		return Html::_div(
			array('class' => 'col-12 mt-5'),
			'<h3 class="option">' . esc_html__('Options price', 'parking-management') . '</h3>',
			...$blocks
		);
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
