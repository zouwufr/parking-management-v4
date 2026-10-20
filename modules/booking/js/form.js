document.addEventListener('DOMContentLoaded', function () {

	function loadScript(url) {
		const script = document.createElement("script");
		script.type = "text/javascript";
		script.src = url;
		document.getElementsByTagName("head")[0].appendChild(script);
	}

	// Price
	function getPrice() {
		if (
			$('#depart').val().length === 0
			|| $('#retour').val().length === 0
		)
			return;
		// On fait la liste des options dans une variables javascript
		$.ajax({
			type: 'GET',
			url: '/wp-json/pkmgmt/v1/prices',
			data: $('#reservation').serialize(),
			processData: true,
			dataType: 'json',
			error: function (e, f, g) {
				console.error("get price failed", f);
			},
			success: function (data) {
				if (data.toolong === 0) {
					if (data.complet === 0) {
						const total = parseInt(!isNaN(parseFloat(data.total)) ? data.total : '0');
						$('#total_amount').val(total);
						$('div.total span').html(total + ' €');

					} else {
						console.error(data.complet);
						$('#total_amount').val('0');
						$('div.total span').html('0 €');
					}
				} else {
					console.error(data.toolong);
					$('#total_amount').val('0');
					$('div.total span').html('0 €');
				}
				switchSubmitBtn();
			}
		});
	}
	getPrice();
	function switchSubmitBtn() {
		// #cgv_reservation is always rendered (see refreshTermsAndConditions()) - only
		// gate on it when the current site actually requires it (the 'required' class,
		// kept in sync with the selected site), not on mere DOM presence.
		if (
			parseInt($('#total_amount').val()) > 0
			&& (!$('#cgv_reservation').hasClass('required') || $('#cgv_reservation').is(':checked'))
		)
			$("#submit").removeAttr("disabled");
		else
			$("#submit").attr("disabled", "disabled");
	}

	$('#cgv_reservation').on('change', function () {
		switchSubmitBtn($(this).is(':checked'))
	});
	$('#depart, #retour, #nb_pax, .options-checkbox, input.type-id, input.parking-type').on('change', function () {
		getPrice();
	})

	// Some block themes (e.g. the sandbox's Twenty Twenty-Five) wrap page content in
	// containers using CSS containment/container-query properties, which - like
	// `transform` - creates a new containing block for `position: fixed` descendants,
	// so the spinner only covered that wrapper instead of the full viewport. Moving it
	// to be a direct child of <body> sidesteps that regardless of theme markup.
	function showSpinner() {
		const $spinner = $('#spinner-container');
		if ($spinner.length && !$spinner.parent().is('body')) {
			$spinner.appendTo('body');
		}
		$spinner.css('display', 'flex');
	}

	// Parking site: filters vehicle types / storage type / terminals to what the
	// selected site actually supports, since each site has its own tariff. The
	// storage type (parking_type) must always stay aligned with both the selected
	// site AND the selected vehicle type, since valet is only ever offered for cars.
	const vehicleKeyByValue = {'1': 'car', '2': 'motorcycle', '3': 'truck'};
	const parkingKeyByValue = {'0': 'ext', '1': 'int', '2': 'valet'};

	function currentSiteId() {
		const $select = $('.site-select');
		return $select.length ? $select.val() : external_object.site_id;
	}

	function currentSite() {
		const sites = external_object.sites || {};
		return sites[currentSiteId()];
	}

	// dialog_confirmation is a per-site flag (see sites[].dialog_confirmation) - checking the
	// currently selected site here, rather than a page-load-time snapshot, matters for the
	// same reason as refreshTermsAndConditions(): the visitor can switch sites client-side.
	function dialogConfirmationEnabled() {
		const site = currentSite();
		return !!(site && site.dialog_confirmation === '1');
	}

	// parking_type options shown = site's enabled types, minus valet unless the
	// currently checked vehicle type is a car.
	// The .radio wrapper carries Bootstrap's `d-flex` utility class (`display: flex
	// !important`), which beats a plain inline `display:none` from jQuery's
	// show()/hide()/toggle() - so hiding must go through this !important-scoped class
	// (form.css: `form#reservation div.radio.pkmgmt-hidden`) instead.
	function setRadioHidden($radioWrapper, hidden) {
		$radioWrapper.toggleClass('pkmgmt-hidden', hidden);
	}

	function refreshParkingTypeOptions(site) {
		const $parkingTypeRadios = $('input.parking-type');
		if (!site) {
			setRadioHidden($parkingTypeRadios.closest('.radio'), false);
			return;
		}
		const isCar = vehicleKeyByValue[$('input.type-id:checked').val()] === 'car';
		$parkingTypeRadios.each(function () {
			const key = parkingKeyByValue[$(this).val()];
			let supported = !!(site.type && site.type[key] === '1');
			if (key === 'valet' && !isCar) {
				supported = false;
			}
			setRadioHidden($(this).closest('.radio'), !supported);
			if (!supported && $(this).is(':checked')) {
				$(this).prop('checked', false);
			}
		});
		// Check site-support (the pkmgmt-hidden class) directly rather than :visible -
		// once these radios can also be hidden because their wizard STEP isn't the
		// active one (unrelated to site support), :visible would be false for every
		// radio whenever the user isn't on that step, breaking this fallback whenever
		// the site changes from a different step (e.g. Step 1's site-select).
		const $visibleParkingType = $parkingTypeRadios.filter(function () {
			return !$(this).closest('.radio').hasClass('pkmgmt-hidden');
		});
		if ($visibleParkingType.length && !$visibleParkingType.is(':checked')) {
			$visibleParkingType.first().prop('checked', true);
		}
	}

	// keep_keys/ev_charging/cancellation_insurance: opt-in extras configured per site
	// (enabled/price/label), same alignment gap as the parking/vehicle type radios -
	// the checkbox is always rendered (see Form::options()) and JS shows/hides/relabels
	// it for whichever site is selected.
	const optionKeys = ['keep_keys', 'ev_charging', 'cancellation_insurance'];

	function refreshOptions(site) {
		optionKeys.forEach(function (key) {
			const $wrapper = $('[data-option="' + key + '"]');
			if (!$wrapper.length) {
				return;
			}
			if (!site) {
				$wrapper.show();
				return;
			}
			const option = (site.options && site.options[key]) || {};
			const supported = option.enabled === '1' && !!option.price && option.price !== '0' && !!option.label;
			$wrapper.toggle(supported);
			const $checkbox = $wrapper.find('.options-checkbox');
			if (!supported) {
				if ($checkbox.is(':checked')) {
					$checkbox.prop('checked', false);
				}
			} else {
				$wrapper.find('label.form-check-label').text(option.label.replace('%s', option.price));
			}
		});
	}

	// terms_and_conditions: a plain per-site on/off flag (unlike options(), it has no
	// price/label to vary) - always rendered server-side for the same reason as options()
	// above, since the visitor can switch sites client-side: a non-default site enabling it
	// had no visible effect when the checkbox only existed in the DOM for whichever site
	// happened to be current at page-load time. jQuery Validate reads the 'required' class
	// fresh on each check, so toggling it here keeps validation in sync with the checkbox.
	function refreshTermsAndConditions(site) {
		const $wrapper = $('#pkmgmt-cgv');
		if (!$wrapper.length) {
			return;
		}
		const $checkbox = $('#cgv_reservation');
		const required = !!(site && site.terms_and_conditions === '1');
		$wrapper.toggle(required);
		$checkbox.toggleClass('required', required);
		if (!required && $checkbox.is(':checked')) {
			$checkbox.prop('checked', false);
		}
		$('#reservation').validate().element('#cgv_reservation');
	}

	function applySite(siteId) {
		const sites = external_object.sites || {};
		const site = sites[siteId];
		const $typeIdRadios = $('input.type-id');

		if (!site) {
			setRadioHidden($typeIdRadios.closest('.radio'), false);
			refreshParkingTypeOptions(null);
			refreshOptions(null);
			refreshTermsAndConditions(null);
			return;
		}

		$typeIdRadios.each(function () {
			const key = vehicleKeyByValue[$(this).val()];
			const supported = !!(site.vehicle_types && site.vehicle_types[key] === '1');
			setRadioHidden($(this).closest('.radio'), !supported);
			if (!supported && $(this).is(':checked')) {
				$(this).prop('checked', false);
			}
		});
		// Same site-support-vs-step-visibility caveat as refreshParkingTypeOptions() above.
		const $visibleTypeId = $typeIdRadios.filter(function () {
			return !$(this).closest('.radio').hasClass('pkmgmt-hidden');
		});
		if ($visibleTypeId.length && !$visibleTypeId.is(':checked')) {
			$visibleTypeId.first().prop('checked', true);
		}

		refreshParkingTypeOptions(site);
		refreshOptions(site);
		refreshTermsAndConditions(site);

		const terminals = (external_object.terminals && external_object.terminals[site.airport]) || [];
		$('#terminal_depart, #terminal_arrivee').each(function () {
			const $select = $(this);
			const current = $select.val();
			$select.empty();
			terminals.forEach(function (terminal) {
				$select.append($('<option>', {value: terminal.value, text: terminal.label}));
			});
			if (terminals.some(function (terminal) { return terminal.value === current; })) {
				$select.val(current);
			}
		});
	}

	// Validation
	// Must be initialized before anything below can call the bare $('#reservation').validate()
	// (e.g. refreshTermsAndConditions(), reached synchronously via .trigger('change') right
	// after this block) - jQuery Validate only honors the FIRST .validate(...) call made on a
	// given form; every later call (with or without options) just returns that same instance
	// and silently ignores any options passed to it. Previously this real config (rules +
	// submitHandler) was declared near the bottom of the file, by which point the bare call
	// below had already created the validator with no rules and no submitHandler - so this
	// form was always falling through to a plain, unvalidated native submission.
	$.validator.addMethod(
		"customMobile",
		function () {
			return !!iti.isValidNumber();
		},
		"Vérifiez le numéro de téléphone"
	);

	if (external_object.locale === 'fr_FR')
		loadScript("https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.20.0/localization/messages_fr.min.js");
	if (external_object.locale === 'de_DE')
		loadScript("https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.20.0/localization/messages_de.min.js");
	if (external_object.locale === 'nl_NL')
		loadScript("https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.20.0/localization/messages_nl.min.js");
	$("#reservation").validate({
		// jQuery Validate defaults to ignoring :hidden fields entirely. Every step but the
		// current one is hidden via .pkmgmt-step-hidden (display:none !important), so without
		// this the final submit was only ever validating whichever step happened to be open,
		// silently skipping the other two - this is what let incomplete steps through.
		ignore: [],
		rules: {
			nom: {
				minlength: 2,
				required: true
			},
			prenom: {
				minlength: 2,
				required: true
			},
			code_postal: {
				digits: true,
				required: true
			},
			mobile: {
				customMobile: true,
				required: true
			},
			email: {
				required: true,
				email: true
			},
			modele: {
				required: true,
				minlength: 2
			},
			immatriculation: {
				required: true,
				minlength: 2
			},
			destination: {
				required: true,
				minlength: 2
			},
			depart: {
				required: true,
			},
			retour: {
				required: true,
			},
			nb_pax: {
				required: true,
			}
		},

		// Jumps back to whichever step holds the first invalid field, so form.js's error
		// label is never rendered behind a hidden .pkmgmt-step - fires before showErrors()
		// runs (jQuery Validate triggers invalid-form, then shows errors), so by the time
		// the error is actually displayed the right step is already visible.
		// jumpToFirstInvalidStep() is a function declaration defined further down in this
		// same scope - safe to reference here since it's hoisted and only ever called once
		// the whole script (and thus that declaration) has already run.
		invalidHandler: function (event, validator) {
			jumpToFirstInvalidStep(validator);
		},

		submitHandler: function (form) {
			if (dialogConfirmationEnabled()) {
				// Don't rely on the main picker's 'select' event (or #email's 'change') having
				// fired at some earlier point to have kept depart2/retour2/email2 in sync - if
				// the value was already correct before that listener was ever triggered (e.g.
				// a pre-filled reload, or the user never re-picked after an earlier edit), it
				// never would have. Sync explicitly from the real, just-validated fields right
				// before the dialog opens, which is the one moment these actually need to be
				// correct regardless of history.
				syncValues('#depart', '#depart2');
				syncValues('#retour', '#retour2');
				syncValues('#email', '#email2');
				easepickCreate(
					$('#depart2').get(0),
					$('#retour2').get(0),
					1,
					function () {
						syncValues('#depart2', '#depart');
						syncValues('#retour2', '#retour');
						getPrice();
					}
				);
				dialogConfirm.dialog("open");
			} else {
				showSpinner();
				form.submit();
			}
		}
	})

	$('.site-select').on('change', function () {
		applySite($(this).val());
		loadSiteDependentDates();
		getPrice();
	}).trigger('change');

	// Switching vehicle type can only ever narrow/widen the valet option; re-check
	// parking_type alignment whenever it changes (getPrice() already re-fires
	// separately via the shared change listener below).
	$(document).on('change', 'input.type-id', function () {
		refreshParkingTypeOptions(currentSite());
	});

	// Step wizard: purely a client-side progressive-disclosure layer over the single
	// #reservation form - every field from every step is still rendered server-side on
	// page load (see Form::step_fieldset() in form.php) and nothing is fetched/submitted
	// per step. Next/Prev never block navigation - the real "Pay and validate" submit
	// (see the invalidHandler on $("#reservation").validate({...}) above) is what
	// actually enforces required fields now, and jumps back to whichever step holds the
	// first invalid one, so gating Next on per-step completeness would just be a second,
	// redundant blocker.
	const TOTAL_STEPS = 3;
	let currentStep = 1;
	const stepFieldIds = {
		1: ['destination', 'depart', 'retour', 'nb_pax'],
		2: ['modele', 'immatriculation'],
		3: ['nom', 'prenom', 'code_postal', 'mobile', 'email'],
	};

	// Shared by the invalidHandler above (client-side submit attempt) and the
	// server-side-error recheck below - finds the first step holding a field the
	// validator considers invalid and shows it, so the error label form.js renders is
	// never left stranded behind a hidden step.
	function jumpToFirstInvalidStep(validator) {
		for (let n = 1; n <= TOTAL_STEPS; n++) {
			const hasInvalid = (stepFieldIds[n] || []).some(function (id) {
				const el = document.getElementById(id);
				return el && !validator.element('#' + id);
			});
			if (hasInvalid) {
				showStep(n);
				return;
			}
		}
		showStep(1);
	}

	function showStep(n) {
		$('.pkmgmt-step').each(function () {
			const $step = $(this);
			const isActive = parseInt($step.data('step'), 10) === n;
			$step.toggleClass('pkmgmt-step-hidden', !isActive);
			$step.attr('aria-hidden', isActive ? 'false' : 'true');
		});
		$('.pkmgmt-progress-step').each(function () {
			const $item = $(this);
			const stepNum = parseInt($item.data('step'), 10);
			$item.attr('aria-current', stepNum === n ? 'step' : 'false');
			$item.toggleClass('pkmgmt-progress-step-done', stepNum < n);
		});
		const $heading = $('#pkmgmt-step-' + n + '-heading');
		$('#pkmgmt-step-announcer').text('Step ' + n + ' of ' + TOTAL_STEPS + ': ' + $heading.text());
		$heading.trigger('focus');
		currentStep = n;
	}

	$(document).on('click', '.pkmgmt-step-next', function () {
		showStep(Math.min(currentStep + 1, TOTAL_STEPS));
	});
	$(document).on('click', '.pkmgmt-step-prev', function () {
		showStep(Math.max(currentStep - 1, 1));
	});

	// Date picker
	const DateTime = easepick.DateTime;
	let highSeason = [];
	let bookedDates = [];

	function loadSiteDependentDates() {
		const siteId = currentSiteId();
		$.ajax({
			type: 'GET',
			url: '/wp-json/pkmgmt/v1/high-season',
			data: {site_id: siteId},
			dataType: 'json',
			error: function (e, f, g) {
				console.error("get high season failed", f);
			},
			success: function (data) {
				if (data instanceof Array)
				{
					highSeason = data.map(d => {
						if (d instanceof Array) {
							const start = new DateTime(d[0], 'YYYY-MM-DD');
							const end = new DateTime(d[1], 'YYYY-MM-DD');
							return [start, end];
						}
						return new DateTime(d, 'YYYY-MM-DD');
					});
				}
			}
		});

		$.ajax({
			type: 'GET',
			url: '/wp-json/pkmgmt/v1/booked',
			data: {site_id: siteId},
			dataType: 'json',
			error: function (e, f, g) {
				console.error("get booked failed", f);
			},
			success: function (data) {
				if (data instanceof Array)
				{
					bookedDates = data.map(d => {
						if (d instanceof Array) {
							const start = new DateTime(d[0], 'YYYY-MM-DD');
							const end = new DateTime(d[1], 'YYYY-MM-DD');
							return [start, end];
						}
						return new DateTime(d, 'YYYY-MM-DD');
					});
				}
			}
		});
	}

	function syncValues(source, target) {
		$(target).val($(source).val());
	}

	function easepickCreate(startDateInput, endDateInput, calendars, callback) {
		// easepick's TimePlugin has no "default time" option of its own - left alone it
		// defaults to midnight for a freshly-picked date. Seed it to noon instead, but
		// only when the field doesn't already hold a value, so a date pre-filled from a
		// home-form redirect or a server-side error re-render keeps its real time.
		const hasStartValue = !!(startDateInput && startDateInput.value);
		const hasEndValue = !!(endDateInput && endDateInput.value);
		new easepick.create({
			element: startDateInput,
			lang: 'fr-FR',
			autoApply: false,
			locale: {
				apply: "OK"
			},
			calendars: calendars,
			grid: calendars,
			plugins: ['RangePlugin', 'LockPlugin', 'TimePlugin'],
			css: [
				'https://cdn.jsdelivr.net/npm/@easepick/bundle@1.2.1/dist/index.css',
				external_object.form_css,
			],
			format: 'YYYY-MM-DD HH:mm',
			zIndex: 50,
			setup(picker) {
				if (!hasStartValue) picker.setStartTime('12:00');
				if (!hasEndValue) picker.setEndTime('12:00');
				picker.on('select', () => {
					callback();
				});
				picker.on('view', (evt) => {

					const {view, date, target} = evt.detail;
					if ( view === 'CalendarDay' && date.inArray(highSeason, '[]') ) {
						target.classList.add('high-season');
					}
					// target.append();
				});

			},
			RangePlugin: {
				elementEnd: endDateInput,
				minDate: new Date(),

				locale: {
					one: "jour",
					other: "jours"
				}
			},
			LockPlugin: {
				minDate: new Date(),
				inseparable: false,
				filter(date) {
					return date.inArray(bookedDates, '[]');
				},
			},
			TimePlugin: {
				format: 'HH:mm',
			}
		});
	}

	function initializeDateTimePickers() {

		$('#depart').each(function () {
			easepickCreate(
				$('#depart').get(0),
				$('#retour').get(0),
				2,
				function () {
					getPrice();
					if (dialogConfirmationEnabled()) {
						syncValues('#depart', '#depart2');
						syncValues('#retour', '#retour2');
					}
				}
			);
		});
	}

	initializeDateTimePickers();


	// Zip Codes
	$("#code_postal").catcomplete({
		source: '/wp-json/pkmgmt/v1/zipcode',
		dataType: "json",
		minLength: 2,
		select: function (event, ui) {
			$('#ville').val(ui.item.ville);
			$('#pays').val(ui.item.pays_id);
		}
	});

	// Models vehicle
	$("#modele").catcomplete({
		source: '/wp-json/pkmgmt/v1/vehicle',
		dataType: "json",
		minLength: 2,
		select: function (event, ui) {
			$('#marque').val(ui.item.category);
		}
	});

	// Destination
	$('#destination').catcomplete({
		source: '/wp-json/pkmgmt/v1/destination',
		dataType: "json",
		minLength: 2,
		select: function (event, ui) {
			$('#destination_id').val(ui.item.id);
		}
	});

	// Phone lib
	const indicative = external_object.indicative ? external_object.indicative : "fr";
	const mobileInput = document.querySelector('#mobile');
	const iti = window.intlTelInput(mobileInput, {
		utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@23.1.0/build/js/utils.js",
		formatOnDisplay: false,
		separateDialCode: false,
		autoPlaceholder: "polite",
		autoHideDialCode: true,
		nationalMode: true,
		initialCountry: "auto",
		strictMode: true,
		geoIpLookup: callback => {
			fetch("https://ipapi.co/json")
				.then(res => res.json())
				.then(data => callback(data.country_code))
				.catch(() => callback("fr"));
		},
		onlyCountries: ["fr", "be", "ch", "de", "nl"],
		preferredCountries: [indicative]
	});

	$('#mobile').on('change', function () {
		$('#tel_port').val(iti.getNumber());
	})
	// Confirmation dialog - #dialog_booking_confirmation is always rendered now (see
	// Form::dialog_booking_confirmation()), so this setup always runs; dialogConfirmationEnabled()
	// (checked at the two places that decide whether to actually open it) is what makes this a
	// no-op for sites that don't use it. jQuery UI's dialog() starts with autoOpen:false, so
	// initializing it unconditionally is harmless either way.
	let dialogConfirm;

	$('#email').on('change', function () {
		$('#email2').val(this.value);
	})
	$('#email2').on('change', function () {
		$('#email').val(this.value);
	})
	dialogConfirm = $('#dialog_booking_confirmation').dialog({
		autoOpen: false,
		// Height is left to the content (fixed 530px previously clipped/left dead space
		// depending on content) - form.css caps width responsively on narrow viewports.
		width: 420,
		modal: true,
		classes: {
			'ui-dialog': 'pkmgmt-dialog',
			'ui-dialog-titlebar': 'pkmgmt-dialog-titlebar',
			'ui-dialog-titlebar-close': 'pkmgmt-dialog-close',
		}
	});
	$('#confirmation').validate({
		submitHandler: function (form) {
			showSpinner();
			$.each($('#reservation').serializeArray(), function (i, field) {
				$('<input>').attr({
					type: 'hidden',
					name: field.name,
					value: field.value
				}).appendTo(form);
			})
			form.submit();
		}
	})

	// After a server-side validation error (e.g. Member::create()'s required-field
	// check), the page re-renders fully populated from the submitted values with an
	// error banner on top - don't strand the visitor on Step 1 if the actual invalid
	// field lives on a later step.
	if ($('.alert-danger').length) {
		jumpToFirstInvalidStep($('#reservation').validate());
	}
});

// Widget auto completion
$.widget("custom.catcomplete", $.ui.autocomplete, {
	_create: function () {
		this._super();
		this.widget().menu("option", "items", "> :not(.ui-autocomplete-category)");
	},
	_renderMenu: function (ul, items) {
		let that = this,
			currentCategory = "";
		$.each(items, function (index, item) {
			let li;
			if (item.category !== currentCategory) {
				ul.append("<li class='ui-autocomplete-category'>" + item.category + "</li>");
				currentCategory = item.category;
			}
			li = that._renderItemData(ul, item);
			if (item.category) {
				li.attr("aria-label", item.category + " : " + item.label);
			}
		});
	}
});


