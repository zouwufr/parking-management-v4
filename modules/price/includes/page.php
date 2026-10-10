<?php

namespace Price;

use ParkingManagement\Html;
use ParkingManagement\interfaces\IParkingmanagement;
use ParkingManagement\ParkingManagement;

class Page implements IParkingmanagement
{

	private array $site = [];

	private ParkingManagement $pm;

	public function __construct(ParkingManagement $pm)
	{
		$this->pm = $pm;
	}

	public function setSite(array $site): void
	{
		$this->site = $site;
	}

	public function table(): string
	{
		$type = $this->site['type'] ?? [];
		$enabledCount = (($type['ext'] ?? '0') === '1' ? 1 : 0) + (($type['int'] ?? '0') === '1' ? 1 : 0) + (($type['valet'] ?? '0') === '1' ? 1 : 0);
		$div_class = $enabledCount > 1 ? 'col-md-6' : 'col';
		return Html::_div(
			array('class' => 'row'),
			$this->elem($div_class, esc_html__('Outside lots', 'parking-management'), 'ext', ($type['ext'] ?? '0') === '1'),
			$this->elem($div_class, esc_html__('Inside lots', 'parking-management'), 'int', ($type['int'] ?? '0') === '1'),
			$this->elem($div_class, esc_html__('Valet', 'parking-management'), 'valet', ($type['valet'] ?? '0') === '1'),
			$this->options($this->site['form']['options'] ?? [])
		);
	}

	private function elem(string $div_class, string $title, string $parkingKey, bool $enabled): string
	{
		if (!$enabled)
			return '';
		$tariff = $this->site['tariffs']['car'][$parkingKey] ?? [];
		if (empty($tariff))
			return '';
		$high_season = $this->site['high_season']['price'] ?? 0;

		$contents = array();
		foreach ($tariff as $day => $price) {
			if (!is_numeric($day) || empty($price)) {
				continue;
			}
			$contents[] = '<tr><th class="align-middle text-center" scope="row">' . $day . '</th>';
			$contents[] = '<td class="align-middle text-center">' . $price . '€' . '</td>';
			if ($high_season)
				$contents[] = '<td class="align-middle text-center">' . ($price + $high_season) . '€' . '</td>';
			$contents[] = '</tr>';
		}
		return Html::_div(array('class' => $div_class),
			Html::_div(
				array('class' => 'table-responsive'),
				'<table class="table table-light table-hover table-striped align-middle text-center caption-top">',
				'<caption>' . $title . '</caption>',
				'<thead class="table-dark">',
				'<tr>',
				'<th class="align-middle text-center bg-primary col-1">' . esc_html__("Number of days", 'parking-management') . '</th>',
				'<th class="align-middle text-center bg-primary">' . esc_html__("Price", "parking-management") . '</th>',
				($high_season) ? '<th class="align-middle text-center bg-warning col-4">' . esc_html__("High Season Price", 'parking-management') . '</th>' : '',
				'</tr>',
				'</thead>',
				'<tbody>',
				implode(PHP_EOL, $contents),
				'</tbody>',
				'</table>',
			),
		);
	}

	private function options(array $options): string
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

		return Html::_div(
			array('class' => 'col mt-5'),
			'<h3 class="option">' . esc_html__("Options price", 'parking-management') . '</h3>',
			Html::_ul(array('class' => 'list-group list-group-flush'),
				...$ul_contents
			)
		);
	}
}
