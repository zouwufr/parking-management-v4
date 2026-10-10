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
			async: false,
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
		if (
			parseInt($('#total_amount').val()) > 0
			&& (external_object.form_options.terms_and_conditions == '0' || $('#cgv_reservation').is(':checked'))
		)
			$("#submit").removeAttr("disabled");
		else
			$("#submit").attr("disabled", "disabled");
	}

	$('#cgv_reservation').on('change', function () {
		switchSubmitBtn($(this).is(':checked'))
	});
	$('#depart, #return, #nb_pax, .options-checkbox, input.type-id, input.parking-type').on('change', function () {
		getPrice();
	})

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
		// Note: the radio inputs themselves are always display:none by design (form.css
		// hides them so only the pill-styled <label> shows) - jQuery's :visible filter on
		// the <input> is therefore always false. Check the wrapping .radio div instead,
		// which is what setRadioHidden() above actually shows/hides. :visible also
		// correctly reflects the pkmgmt-hidden class since it's a real CSS display:none.
		const $visibleParkingType = $parkingTypeRadios.filter(function () {
			return $(this).closest('.radio').is(':visible');
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

	function applySite(siteId) {
		const sites = external_object.sites || {};
		const site = sites[siteId];
		const $typeIdRadios = $('input.type-id');

		if (!site) {
			setRadioHidden($typeIdRadios.closest('.radio'), false);
			refreshParkingTypeOptions(null);
			refreshOptions(null);
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
		// Same :visible caveat as refreshParkingTypeOptions() above - check the wrapping
		// .radio div, not the permanently display:none <input>.
		const $visibleTypeId = $typeIdRadios.filter(function () {
			return $(this).closest('.radio').is(':visible');
		});
		if ($visibleTypeId.length && !$visibleTypeId.is(':checked')) {
			$visibleTypeId.first().prop('checked', true);
		}

		refreshParkingTypeOptions(site);
		refreshOptions(site);

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
			async: false,
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
			async: false,
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
					if (external_object.form_options.dialog_confirmation === '1') {
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
	// Confirmation dialog
	let dialogConfirm;
	if (external_object.form_options.dialog_confirmation === '1') {

		$('#email').on('change', function () {
			$('#email2').val(this.value);
		})
		$('#email2').on('change', function () {
			$('#email').val(this.value);
		})
		dialogConfirm = $('#dialog_booking_confirmation').dialog({
			autoOpen: false,
			height: 530,
			width: 380,
			modal: true
		});
		$('#confirmation').validate({
			submitHandler: function (form) {
				$('#spinner-container').css('display', 'flex');
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
	}

	// Validation
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
			np_pax: {
				required: true,
			}
		},

		submitHandler: function (form) {
			if (external_object.form_options.dialog_confirmation === '1') {
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
				$('#spinner-container').css('display', 'flex');
				form.submit();
			}
		}
	})
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


