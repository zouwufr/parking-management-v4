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
					<i class="fas fa-grip-vertical site-drag-handle me-3" title="Drag to reorder - the first active site is selected by default on the booking form"></i>
					<div class="location_field flex-grow-1 me-3">
						<label for="site-${id}-name">Name</label><br/>
						<input type="text" id="site-${id}-name" name="${name}[name]" class="wide" size="80">
					</div>
					<div class="options-switch form-check">
						<input type="hidden" name="${name}[active]" value="0">
						<input type="checkbox" id="site-${id}-active" class="options-checkbox form-check-input" name="${name}[active]" value="1" checked>
						<label class="form-check-label black" for="site-${id}-active">Active</label>
					</div>
					<input type="color" id="site-${id}-color" name="${name}[color]" class="form-control form-control-color ms-3" title="Color used to tell this site apart on the price table">
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

	// Site order here is submitted as-is in `pkmgmt-sites[]` and saved in that order,
	// which is also the order Site::activeOnly() returns - so dragging a site to the
	// top also makes it the default one selected on the booking form (Site::defaultSite()
	// picks the first active site).
	$('#sites_body').sortable({
		handle: '.site-drag-handle',
		axis: 'y',
		placeholder: 'site-item-placeholder',
		forcePlaceholderSize: true,
		tolerance: 'pointer',
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

	// The config form's info/database/sites/supabase fields are bundled into
	// one `pkmgmt-json` field on submit instead of posting thousands of
	// individual bracketed inputs - that used to blow past PHP's
	// max_input_vars (1000 by default) as soon as there were a couple of
	// sites, silently dropping whatever came after the limit (see
	// Admin::post_truncated() in admin/admin.php, kept there as a fallback
	// for the rare non-JS submission).
	//
	// Keep this in sync with ParkingManagement::properties_available.
	const PKMGMT_JSON_PROPERTIES = ['pkmgmt-info', 'pkmgmt-database', 'pkmgmt-sites', 'pkmgmt-supabase'];

	// Splits a bracketed form field name ("pkmgmt-sites[792698][tariffs]")
	// into ["pkmgmt-sites", "792698", "tariffs"].
	function pkmgmtNameToPath(name) {
		const firstBracket = name.indexOf('[');
		if (firstBracket === -1) {
			return [name];
		}
		const path = [name.slice(0, firstBracket)];
		const re = /\[([^\]]*)]/g;
		re.lastIndex = firstBracket;
		let match;
		while ((match = re.exec(name)) !== null) {
			path.push(match[1]);
		}
		return path;
	}

	// Writes `value` at `path` into a tree of Maps. A plain object would do,
	// except PHP preserves the exact order array keys were encountered in -
	// including numeric-looking ones (site ids) - while a JS object always
	// reorders integer-like keys ascending. That would silently undo the
	// site drag-reorder feature, so Maps (which keep insertion order for
	// every key type) are used instead and turned into JSON text by hand.
	function pkmgmtSetPath(root, path, value) {
		let node = root;
		for (let i = 0; i < path.length - 1; i++) {
			const key = path[i];
			if (!(node.get(key) instanceof Map)) {
				node.set(key, new Map());
			}
			node = node.get(key);
		}
		// Later entries for the same name win, matching how PHP keeps only
		// the last value for duplicate POST keys (relied on by the
		// hidden-input-plus-checkbox pairs used for the option toggles).
		node.set(path[path.length - 1], value);
	}

	function pkmgmtStringify(node) {
		if (node instanceof Map) {
			const parts = [];
			for (const [key, value] of node) {
				parts.push(JSON.stringify(String(key)) + ':' + pkmgmtStringify(value));
			}
			return '{' + parts.join(',') + '}';
		}
		return JSON.stringify(node);
	}

	const pkmgmtConfigForm = document.getElementById('pkmgmt-admin-config');
	if (pkmgmtConfigForm) {
		pkmgmtConfigForm.addEventListener('submit', function () {
			const root = new Map();
			const matchedNames = new Set();

			for (const [name, value] of new FormData(pkmgmtConfigForm).entries()) {
				if (typeof value !== 'string') {
					continue; // file inputs - none of these fields have one
				}
				const base = name.indexOf('[') === -1 ? name : name.slice(0, name.indexOf('['));
				if (!PKMGMT_JSON_PROPERTIES.includes(base)) {
					continue;
				}
				matchedNames.add(name);
				pkmgmtSetPath(root, pkmgmtNameToPath(name), value);
			}

			let jsonInput = pkmgmtConfigForm.querySelector('input[name="pkmgmt-json"]');
			if (!jsonInput) {
				jsonInput = document.createElement('input');
				jsonInput.type = 'hidden';
				jsonInput.name = 'pkmgmt-json';
				pkmgmtConfigForm.appendChild(jsonInput);
			}
			jsonInput.value = pkmgmtStringify(root);

			// Disabled fields are left out of the submission entirely, so
			// only pkmgmt-json (plus the handful of unrelated top-level
			// fields such as title/name/locale/nonces) is actually posted.
			matchedNames.forEach(function (name) {
				pkmgmtConfigForm.querySelectorAll('[name="' + name + '"]').forEach(function (el) {
					el.disabled = true;
				});
			});
		});
	}

});
