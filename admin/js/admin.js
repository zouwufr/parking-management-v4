document.addEventListener('DOMContentLoaded', function () {
	window.addEventListener('beforeunload', function () {
		localStorage.setItem('scrollPosition', window.scrollY.toString());
	});
	window.addEventListener('keydown', function (event) {
		if ((event.ctrlKey && event.key === 's') || (event.metaKey && event.key === 's')) {
			event.preventDefault();
			document.getElementById('pkmgmt-save').click();
		}
	});

	const scrollPosition = localStorage.getItem('scrollPosition');
	if (scrollPosition) {
		window.scrollTo(0, parseInt(scrollPosition, 10));
	}


	// Password toggle
	$('.togglePassword').on('click', function () {
		const passwordInput = $(this).siblings('.password-input');
		const eyeOpen = $(this).find('.fa-eye');
		const eyeClosed = $(this).find('.fa-eye-slash');

		if (passwordInput.attr('type') === 'password') {
			passwordInput.attr('type', 'text');
			eyeOpen.hide();
			eyeClosed.show();
		} else {
			passwordInput.attr('type', 'password');
			eyeOpen.show();
			eyeClosed.hide();
		}
	});


	// Date picker
	function generateRandomId() {
		return Math.floor(Math.random() * 1000000);
	}

	function getToday() {
		const today = new Date();
		return today.toISOString().slice(0, 10);
	}

	// Generic "add a date range" row, used for every per-site booked-dates / high-season block.
	$(document).on('click', '.site-dates-add', function (event) {
		event.preventDefault();
		const id = generateRandomId();
		const field = $(this).data('field');
		const newElement = `
            <div class="dates-element">
            	<label for="${field}-start-${id}">start</label>
                <input type="date" id="${field}-start-${id}" name="${field}[${id}][start]" class="start-date" value="${getToday()}">
            	<label for="${field}-end-${id}">end</label>
                <input type="date" id="${field}-end-${id}" name="${field}[${id}][end]" class="end-date" value="${getToday()}">
            	<label for="${field}-message-${id}">message</label>
                <input type="text" id="${field}-message-${id}" name="${field}[${id}][message]" class="message" placeholder="Message">
                <i class="fas fa-trash delete"></i>
            </div>
        `;
		$(this).closest('.dates-header').next('.dates-body').append(newElement);

		initializeDateTimePickers();
	});

	// Sites
	const sitesConfig = (typeof pkmgmt_sites_config !== 'undefined') ? pkmgmt_sites_config : {airports: [], terminals: {}, vehicle_types: {}};

	function checkboxField(name, key, label, checked, extraClass) {
		return `
			<div class="form-check form-check-inline ${extraClass || ''}">
				<input type="hidden" name="${name}[${key}]" value="0">
				<input type="checkbox" id="${name}-${key}" class="form-check-input" name="${name}[${key}]" value="1" ${checked ? 'checked' : ''}>
				<label class="form-check-label" for="${name}-${key}">${label}</label>
			</div>
		`;
	}

	function terminalOptionsMarkup(airportKey) {
		const terminals = sitesConfig.terminals[airportKey] || [];
		return terminals.map(function (terminal) {
			return `<option value="${terminal.value}">${terminal.label}</option>`;
		}).join('');
	}

	function vehicleTypesMarkup(baseName) {
		return Object.keys(sitesConfig.vehicle_types).map(function (key) {
			return checkboxField(baseName + '[vehicle_types]', key, sitesConfig.vehicle_types[key], false);
		}).join('');
	}

	function airportOptionsMarkup(selected) {
		return sitesConfig.airports.map(function (airport) {
			return `<option value="${airport.value}" ${airport.value === selected ? 'selected' : ''}>${airport.label}</option>`;
		}).join('');
	}

	function legacySiteOptionsMarkup(selected) {
		return (sitesConfig.legacy_sites || []).map(function (site) {
			return `<option value="${site.value}" ${site.value === selected ? 'selected' : ''}>${site.label}</option>`;
		}).join('');
	}

	// Matches Order::getSiteID()'s airport-based default (orly -> 1, roissy -> 2), used only
	// to preselect a sensible value for a brand-new site - distinct businesses sharing one
	// airport (e.g. Orly's "CoverPark") are picked explicitly afterwards, see legacy-site-select.
	function defaultLegacySiteFor(airportKey) {
		return airportKey === 'roissy' ? '2' : '1';
	}

	// Valet is only ever offered for cars.
	function parkingTypesFor(vehicle) {
		return vehicle === 'car' ? ['ext', 'int', 'valet'] : ['ext', 'int'];
	}

	function tariffsMarkup(baseName) {
		return Object.keys(sitesConfig.vehicle_types).map(function (vehicle) {
			return parkingTypesFor(vehicle).map(function (parkingType) {
				let days = '';
				for (let day = 1; day <= 10; day++) {
					days += `
						<div class="col-auto">
							<label class="form-label small mb-0">${day} day(s)</label>
							<input type="number" step="0.01" class="form-control form-control-sm" data-day="${day}" name="${baseName}[tariffs][${vehicle}][${parkingType}][${day}]">
						</div>
					`;
				}
				return `
					<div class="tariff-vehicle mb-2" data-vehicle="${vehicle}" data-parking-type="${parkingType}">
						<strong>${sitesConfig.vehicle_types[vehicle]} - ${parkingType.toUpperCase()}</strong>
						<div class="row g-2 align-items-end">
							${days}
							<div class="col-auto">
								<label class="form-label small mb-0">Extra day</label>
								<input type="number" step="0.01" class="form-control form-control-sm" data-day="jour_supplementaire" name="${baseName}[tariffs][${vehicle}][${parkingType}][jour_supplementaire]">
							</div>
						</div>
					</div>
				`;
			}).join('');
		}).join('');
	}

	// Price grid CSV export/import - one row per (vehicle, parking type), columns
	// day_1..day_10 + extra_day. Pure client-side: export reads the current inputs,
	// import fills them back in: nothing is sent to the server until Save is clicked.
	function exportPriceCsv($siteItem) {
		const rows = [['vehicle', 'parking_type', 'day_1', 'day_2', 'day_3', 'day_4', 'day_5', 'day_6', 'day_7', 'day_8', 'day_9', 'day_10', 'extra_day']];
		$siteItem.find('.tariff-vehicle').each(function () {
			const $block = $(this);
			const vehicle = $block.data('vehicle');
			const parkingType = $block.data('parking-type');
			const days = [];
			for (let day = 1; day <= 10; day++) {
				days.push($block.find(`input[data-day="${day}"]`).val() || '');
			}
			const extraDay = $block.find('input[data-day="jour_supplementaire"]').val() || '';
			rows.push([vehicle, parkingType, ...days, extraDay]);
		});
		const csv = rows.map(row => row.join(',')).join('\n');
		const blob = new Blob([csv], {type: 'text/csv'});
		const url = URL.createObjectURL(blob);
		const siteName = ($siteItem.find('input[name$="[name]"]').val() || 'site').replace(/[^a-z0-9]+/gi, '-');
		const a = document.createElement('a');
		a.href = url;
		a.download = `price-${siteName}.csv`;
		document.body.appendChild(a);
		a.click();
		a.remove();
		URL.revokeObjectURL(url);
	}

	// Fills a site's tariff grid inputs from a {vehicle: {parking_type: {day: price, jour_supplementaire: price}}}
	// object - the shared target shape for both CSV import and the "Load from DB" action below. Only present
	// values are written, so a partial grid (or a valet row this vehicle doesn't offer) leaves the rest untouched.
	function applyTariffGrid($siteItem, tariffs) {
		Object.keys(tariffs || {}).forEach(function (vehicle) {
			Object.keys(tariffs[vehicle] || {}).forEach(function (parkingType) {
				const $block = $siteItem.find(`.tariff-vehicle[data-vehicle="${vehicle}"][data-parking-type="${parkingType}"]`);
				if (!$block.length) {
					return; // e.g. a valet row for a non-car vehicle - not offered, skip it
				}
				const entry = tariffs[vehicle][parkingType] || {};
				Object.keys(entry).forEach(function (day) {
					const value = entry[day];
					if (value !== undefined && value !== null && value !== '') {
						$block.find(`input[data-day="${day}"]`).val(value);
					}
				});
			});
		});
	}

	function importPriceCsv($siteItem, text) {
		const lines = text.split(/\r?\n/).filter(line => line.trim().length);
		if (!lines.length) return;
		const header = lines[0].split(',').map(h => h.trim());
		const dayColumns = header.slice(2, 12);
		const tariffs = {};
		lines.slice(1).forEach(function (line) {
			const cells = line.split(',').map(c => c.trim());
			const vehicle = cells[0];
			const parkingType = cells[1];
			const entry = {};
			dayColumns.forEach(function (col, index) {
				const day = col.replace('day_', '');
				const value = cells[2 + index];
				if (value !== undefined && value !== '') {
					entry[day] = value;
				}
			});
			const extraDay = cells[cells.length - 1];
			if (extraDay !== undefined && extraDay !== '') {
				entry.jour_supplementaire = extraDay;
			}
			tariffs[vehicle] = tariffs[vehicle] || {};
			tariffs[vehicle][parkingType] = entry;
		});
		applyTariffGrid($siteItem, tariffs);
	}

	$(document).on('click', '.price-export', function (event) {
		event.preventDefault();
		exportPriceCsv($(this).closest('.site-item'));
	});

	$(document).on('click', '.price-import-trigger', function (event) {
		event.preventDefault();
		$(this).siblings('.price-import-file').trigger('click');
	});

	$(document).on('change', '.price-import-file', function (event) {
		const file = event.target.files[0];
		if (!file) return;
		const $siteItem = $(this).closest('.site-item');
		const reader = new FileReader();
		reader.onload = function (e) {
			importPriceCsv($siteItem, e.target.result);
		};
		reader.readAsText(file);
		$(this).val('');
	});

	// Pulls the legacy system's own tariff grid (tbl_remplissage.grille_tarifaire) for this
	// site's legacy site id and prefills the grid below - nothing is saved until "Save" is
	// clicked. Keyed by legacy-site-select, not airport-select: more than one legally distinct
	// parking business can share an airport (e.g. Orly also hosts "CoverPark"), so the airport
	// alone can't tell the legacy system which grid to return.
	$(document).on('click', '.price-load-db', function (event) {
		event.preventDefault();
		const $button = $(this);
		const $siteItem = $button.closest('.site-item');
		const legacySiteId = $siteItem.find('.legacy-site-select').val();
		if (!legacySiteId || typeof pkmgmt_sites_config.rest_url === 'undefined') {
			return;
		}
		$button.prop('disabled', true);
		$.ajax({
			url: pkmgmt_sites_config.rest_url + 'prices/db-grid',
			method: 'GET',
			data: {legacy_site_id: legacySiteId},
			beforeSend: function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', pkmgmt_sites_config.nonce);
			}
		}).done(function (tariffs) {
			if (tariffs && Object.keys(tariffs).length) {
				applyTariffGrid($siteItem, tariffs);
			} else {
				window.alert('No tariff data found in the database for this site.');
			}
		}).fail(function () {
			window.alert('Failed to load tariffs from the database.');
		}).always(function () {
			$button.prop('disabled', false);
		});
	});

	function templateHidden(baseName, key, title) {
		return `
			<input type="hidden" name="${baseName}[${key}][title]" value="${title}">
			<input type="hidden" name="${baseName}[${key}][type]" value="textarea">
		`;
	}

	function newSiteMarkup(id) {
		const name = `pkmgmt-sites[${id}]`;
		const defaultAirport = (sitesConfig.airports[0] && sitesConfig.airports[0].value) || 'orly';
		return `
			<div class="site-item border rounded p-3 mb-3" data-site-id="${id}">
				<div class="d-flex justify-content-between align-items-start mb-2">
					<div class="location_field flex-grow-1 me-3">
						<label for="site-${id}-name">Name</label><br/>
						<input type="text" id="site-${id}-name" name="${name}[name]" class="wide" size="80">
					</div>
					<div class="options-switch form-check">
						<input type="hidden" name="${name}[active]" value="0">
						<input type="checkbox" id="site-${id}-active" class="options-checkbox form-check-input" name="${name}[active]" value="1" checked>
						<label class="form-check-label black" for="site-${id}-active">Active</label>
					</div>
					<i class="fas fa-trash delete-site ms-3" title="Remove"></i>
				</div>
				<div class="row">
					<div class="col-sm-3">
						<label for="site-${id}-airport">Airport</label><br/>
						<select id="site-${id}-airport" name="${name}[airport]" class="airport-select">
							${airportOptionsMarkup(defaultAirport)}
						</select>
					</div>
					<div class="col-sm-3 terminal-container">
						<label for="site-${id}-terminal">Terminal</label><br/>
						<select id="site-${id}-terminal" name="${name}[terminal]">
							${terminalOptionsMarkup(defaultAirport)}
						</select>
					</div>
					<div class="col-sm-3 location_field vehicle-types-container">
						<label>Vehicle types</label><br/>
						${vehicleTypesMarkup(name)}
					</div>
					<div class="col-sm-3 location_field">
						<label for="site-${id}-legacy-site">Legacy site (DB)</label><br/>
						<select id="site-${id}-legacy-site" name="${name}[legacy_site_id]" class="legacy-site-select">
							${legacySiteOptionsMarkup(defaultLegacySiteFor(defaultAirport))}
						</select>
					</div>
				</div>
				<div class="row">
					<div class="col-sm-4 location_field">
						<label>Storage type (valet is an optional extra type)</label><br/>
						${checkboxField(name + '[type]', 'ext', 'ext', false)}
						${checkboxField(name + '[type]', 'int', 'int', false)}
						${checkboxField(name + '[type]', 'valet', 'valet', false)}
					</div>
				</div>
				<fieldset class="border border-dark p-3 position-relative my-3">
					<legend class="w-auto mx-2 bg-white position-absolute top-0 start-0 translate-middle-y fs-6">Company (this site's legal identity)</legend>
					<div class="row">
						<div class="col-sm-6 location_field">
							<label for="site-${id}-address">Address</label><br/>
							<input type="text" id="site-${id}-address" name="${name}[info][address]" class="wide" size="80">
						</div>
						<div class="col-sm-3 location_field">
							<label for="site-${id}-mobile">Mobile</label><br/>
							<input type="text" id="site-${id}-mobile" name="${name}[info][mobile]" class="wide" size="80">
						</div>
						<div class="col-sm-3 location_field">
							<label for="site-${id}-email">Email</label><br/>
							<input type="text" id="site-${id}-email" name="${name}[info][email]" class="wide" size="80">
						</div>
					</div>
					<div class="row">
						<div class="col-sm-4 location_field">
							<label for="site-${id}-rcs">RCS</label><br/>
							<input type="text" id="site-${id}-rcs" name="${name}[info][RCS]" class="wide" size="80">
						</div>
						<div class="col-sm-4 location_field">
							<label for="site-${id}-tva">TVA (%)</label><br/>
							<input type="text" id="site-${id}-tva" name="${name}[tva]" class="wide" size="80" value="20">
						</div>
						<div class="col-sm-4 location_field">
							<label for="site-${id}-tva-transport">TVA transport (%)</label><br/>
							<input type="text" id="site-${id}-tva-transport" name="${name}[tva_transport]" class="wide" size="80" value="10">
						</div>
					</div>
				</fieldset>
				<fieldset class="border border-dark p-3 position-relative my-3">
					<legend class="w-auto mx-2 bg-white position-absolute top-0 start-0 translate-middle-y fs-6">Price</legend>
					<p class="description">This is the price grid served by this site's own GET /wp-json/pkmgmt/v1/prices response - the booking form calls that endpoint to compute the total shown to the customer. Valet is only ever offered for cars.</p>
					<div class="price-grid-io mb-3">
						<button type="button" class="button price-export">Export CSV</button>
						<button type="button" class="button price-import-trigger">Import CSV</button>
						<button type="button" class="button price-load-db">Load from DB</button>
						<input type="file" accept=".csv,text/csv" class="price-import-file" style="display:none">
					</div>
					${tariffsMarkup(name)}
				</fieldset>
				<fieldset class="border border-dark p-3 position-relative my-3">
					<legend class="w-auto mx-2 bg-white position-absolute top-0 start-0 translate-middle-y fs-6">Booked dates</legend>
					<div class="dates-header">
						<span>Add a date</span>
						<button class="site-dates-add" type="button" data-field="${name}[booked_dates]"><i class="fas fa-plus"></i></button>
					</div>
					<div class="dates-body"></div>
				</fieldset>
				<fieldset class="border border-dark p-3 position-relative my-3">
					<legend class="w-auto mx-2 bg-white position-absolute top-0 start-0 translate-middle-y fs-6">High season</legend>
					<div class="dates-header justify-content-between">
						<div class="col-sm-2"><div class="input-group"><span class="input-group-text m-0">€</span>
						<input type="number" class="form-control" name="${name}[high_season][price]" value="0"></div></div>
						<div>
							<span>Add a date</span>
							<button class="site-dates-add" type="button" data-field="${name}[high_season][dates]"><i class="fas fa-plus"></i></button>
						</div>
					</div>
					<div class="dates-body"></div>
				</fieldset>
				<fieldset class="border border-dark p-3 position-relative my-3">
					<legend class="w-auto mx-2 bg-white position-absolute top-0 start-0 translate-middle-y fs-6">Mail</legend>
					${templateHidden(name + '[mail_templates]', 'confirmation', 'Confirmation')}
					<label for="site-${id}-mail-confirmation">Confirmation</label>
					<textarea id="site-${id}-mail-confirmation" name="${name}[mail_templates][confirmation][value]" cols="100" rows="8"></textarea>
					${templateHidden(name + '[mail_templates]', 'cancellation', 'Cancellation')}
					<label for="site-${id}-mail-cancellation">Cancellation</label>
					<textarea id="site-${id}-mail-cancellation" name="${name}[mail_templates][cancellation][value]" cols="100" rows="8"></textarea>
				</fieldset>
				<fieldset class="border border-dark p-3 position-relative my-3">
					<legend class="w-auto mx-2 bg-white position-absolute top-0 start-0 translate-middle-y fs-6">SMS template</legend>
					<textarea name="${name}[sms_template]" cols="100" rows="4"></textarea>
				</fieldset>
				<p class="description">Form options, notification (mail/SMS provider) and payment settings can be filled in after the first save, when the full tabbed editor is available for this site.</p>
			</div>
		`;
	}

	$('#sites-add-element').on('click', function (event) {
		event.preventDefault();
		const id = generateRandomId();
		$('#sites_body').append(newSiteMarkup(id));
	});

	$('#sites_body').on('click', '.delete-site', function () {
		$(this).closest('.site-item').remove();
	});

	$(document).on('change', 'select[name$="[airport]"]', function () {
		const $select = $(this);
		const $terminalSelect = $select.closest('.row').find('.terminal-container select');
		if ($terminalSelect.length) {
			$terminalSelect.html(terminalOptionsMarkup($select.val()));
		}
	});

	$('.shortcode-copy').on('click', function (event) {
		event.preventDefault();
		const short_div = $(this).closest('.shortcode-div');
		const copyText = short_div.find('.shortcode').val();
		const copyMessage = short_div.find('.shortcode-copy-message');
		// Get the text from the input field

		// Use the Clipboard API to copy the text
		navigator.clipboard.writeText(copyText).then(function () {
			// Show the copy message
			// const copyMessage = $('#shortcodeCopyMessage');
			copyMessage.show();

			// Hide the message after 2 seconds
			setTimeout(function () {
				copyMessage.hide();
			}, 2000);
		}).catch(function (error) {
			console.error(error);
		});
	});

	function initializeDateTimePickers() {
		$('.start-date').each(function () {
			const endDateInput = $(this).closest('.dates-element').find('.end-date').get(0);
			new easepick.create({
				element: this,
				plugins: ['RangePlugin', 'LockPlugin'],
				css: ['https://cdn.jsdelivr.net/npm/@easepick/bundle@1.2.1/dist/index.css'],
				format: 'YYYY-MM-DD',
				zIndex: 50,
				RangePlugin: {
					elementEnd: endDateInput,
					minDate: new Date(),
				},
				LockPlugin: {
					minDate: new Date(),
				}
			});
		});

	}

	initializeDateTimePickers();
	$('.dates-body').on('click', '.delete', function () {
		$(this).parent('.dates-element').remove();
	});

	// tabs
	// payment provider radios, one group per site
	$(document).on('change', 'input[name$="[payment][name]"]', function (e) {
		e.preventDefault();
		const toShow = $(this).attr('data-bs-target');
		const content = $(this).attr('data-bs-content') + ' div';

		$(content).each(function () {
			$(this).removeClass('active');
			$(this).removeClass('show');
		})
		$(toShow).addClass('active');
		$(toShow).addClass('show');
	})

	// Initialize the first tab
	$('.tab-links li:first-child a').trigger('click');

	// Page List dialog
	let currentInput;
	$('#dialog-pages-list').on('shown.bs.modal', function (e) {
		currentInput = $(e.relatedTarget).closest('.input-group').find('input');
		console.log("currentInput", currentInput);

	})
	$('li.page').on('click', function (e) {
		if (currentInput)
			currentInput.val(e.target.getAttribute('data-url'));
		$('.btn-close').trigger('click');
	})

});
