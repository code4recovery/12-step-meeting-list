jQuery(function ($) {
	//recursively run import
	function runImport() {
		$.getJSON(tsml.ajaxurl + '?action=tsml_import', function (data) {
			//update progress bar
			var $progress = $('body.tsml_meeting_page_import div#tsml_import_progress');
			var total = $progress.attr('data-total');
			var percentage = Math.floor(((total - data.remaining) / total) * 95) + 5 + '%';
			$progress.find('.progress-bar').css({width: percentage}).text(percentage);

			//update the counts on the right
			var $counts = $('#tsml_counts');
			var types = ['meetings', 'locations', 'groups', 'regions'];
			for (var i = 0; i < types.length; i++) {
				var type = types[i];
				if (data.counts[type] > 0) {
					if ($counts.hasClass('hidden')) $counts.removeClass('hidden');
					$li = $counts.find('li.' + type);
					if ($li.hasClass('hidden')) $li.removeClass('hidden');
					if ($li.text(data.descriptions[type]));
				}
			}

			//update the counts in the data sources
			if (data.data_sources) {
				$.each(data.data_sources, function (url, props) {
					$('tr[data-source="' + url + '"] td.count_meetings').text(props.count_meetings);
				});
			}

			//if there are errors, display message and append them to it
			if (data.errors.length) {
				$errors = $('#tsml_import_errors');
				if ($errors.hasClass('hidden')) $errors.removeClass('hidden');
				for (var i = 0; i < data.errors.length; i++) $errors.append(data.errors[i]);
			}

			//if there are more to import, go again
			if (data.remaining) runImport();
		}).fail(function (jqxhr, textStatus, error) {
			console.warn(textStatus, error);
		});
	}

	//import & settings page
	if ($('div#tsml_import_progress').length) {
		$('div#tsml_import_progress div.progress-bar').css({width: '5%'});
		$('#tsml_import_errors').addClass('hidden');
		runImport();
	}

	//delete data source or email contact
	$('table form span').click(function () {
		$(this).parent().submit();
	});

	//meeting add / edit page
	var $post_type = $('input#post_type');
	if ($post_type.length && $post_type.val() == 'tsml_meeting') {
		//make sure geocoding is finished (basic form validation)
		var form_valid = true;

		function formIsValid() {
			form_valid = true;
			$('#publish').removeClass('disabled');
		}

		function formIsNotValid() {
			form_valid = false;
			$('#publish').addClass('disabled');
		}

		// Hide all errors/warnings
		function resetClasses() {
			$('div.form_not_valid').addClass('hidden');
			$('div.need_approximate_address').addClass('hidden');
			$('input#formatted_address').removeClass('error');
			$('input#location').removeClass('warning');
			$('input#formatted_address').removeClass('warning');
		}

		$('form#post').submit(function () {
			return form_valid;
		});

		//show more types
		$('.toggle_more').on('click', 'a', function (e) {
			e.preventDefault();
			$(this).closest('.checkboxes').toggleClass('showing_more');
		});

		//day picker
		$('select#day').change(function () {
			var val = $(this).val();
			var $time = $('input#time');
			var $end_time = $('input#end_time');
			// If a day is selected, not Appointment
			if (val) {
				$time.removeAttr('disabled');
				$end_time.removeAttr('disabled');
				// Put a value in time and end_time if they don't already have a value
				if (!$time.val()) {
					$time.val('00:00').timepicker();
					$end_time.val('01:00').timepicker();
				}
			} else {
				// Appointment is sellected
				$time.attr('data-value', $time.val()).val('').attr('disabled', 'disabled');
				$end_time.attr('data-value', $end_time.val()).val('').attr('disabled', 'disabled');
			}
		});

		//time picker
		$('input.time').timepicker();

		//auto-suggest end time (todo maybe think about using moment for this)
		$('input#time').change(function () {
			//get time parts
			var parts = $(this).val().split(':');
			if (parts.length !== 2) return;
			var hours = parts[0] - 0;
			var parts = parts[1].split(' ');
			if (parts.length !== 2) return;
			var minutes = parts[0];
			var ampm = parts[1];

			//increment hour
			if (hours == 12) {
				hours = 1;
			} else {
				hours++;
				if (hours == 12) {
					ampm = ampm == 'am' ? 'pm' : 'am';
				}
			}
			hours += '';

			//set field value
			$('input#end_time').val(hours + ':' + minutes + ' ' + ampm);
		});

		//types checkboxes: ensure not both open and closed
		$('body.post-type-meetings form#post').on('change', 'input[name="types[]"]', function () {
			if (
				$('body.post-type-meetings form#post input[name="types[]"][value="C"]').prop('checked') &&
				$('body.post-type-meetings form#post input[name="types[]"][value="O"]').prop('checked')
			) {
				if ($(this).val() == 'C') {
					$('body.post-type-meetings form#post input[name="types[]"][value="O"]').prop('checked', false);
				} else {
					$('body.post-type-meetings form#post input[name="types[]"][value="C"]').prop('checked', false);
				}
			}
		});

		// location typeahead
		$.getJSON(tsml.ajaxurl + '?action=tsml_locations', function (data) {
			$('input#location').autocomplete({
				source: data,
				minLength: 1,
				select: function ($e, selected) {
					var location = selected.item;
					if (tsml.debug) console.log('Location: ', location);
					$('input[name=formatted_address]').val(location.formatted_address).trigger('change');
					$('input[name=latitude]').val(location.latitude);
					$('input[name=longitude]').val(location.longitude);
					$('select[name=region] option[value=' + location.region + ']').prop('selected', true);
					$('textarea[name=location_notes]').val(location.notes);
				}
			});
		});

		// group typeahead
		$.getJSON(tsml.ajaxurl + '?action=tsml_groups', function (data) {
			$('input#group').autocomplete({
				source: data,
				minLength: 1,
				select: function ($e, selected) {
					var group = selected.item;
					if (tsml.debug) console.log('Selected: ', selected);
					$('select[name=district]').val(group.district);
					$('input[name=website]').val(group.website);
					$('input[name=email]').val(group.email);
					$('input[name=phone]').val(group.phone);
					$('input[name=contact_1_name]').val(group.contact_1_name);
					$('input[name=contact_1_email]').val(group.contact_1_email);
					$('input[name=contact_1_phone]').val(group.contact_1_phone);
					$('input[name=contact_2_name]').val(group.contact_2_name);
					$('input[name=contact_2_email]').val(group.contact_2_email);
					$('input[name=contact_2_phone]').val(group.contact_2_phone);
					$('input[name=contact_3_name]').val(group.contact_3_name);
					$('input[name=contact_3_email]').val(group.contact_3_email);
					$('input[name=contact_3_phone]').val(group.contact_3_phone);
					$('input[name=mailing_address]').val(group.mailing_address);
					$('input[name=venmo]').val(group.venmo);
					$('input[name=last_contact]').val(group.last_contact);
					$('textarea[name=group_notes]').val(group.notes);
				}
			});
		});

		$('input[name="group_status"]').change(function () {
			$('#contact-type').attr('data-type', $(this).val());
			if ($(this).val() == 'meeting') {
				$('input#group').val('');
				$('textarea#group_notes').val('');
				$('select#district').val('');
				$('.apply_group_to_location').addClass('hidden');
			}
		});

		$('input#group').change(function () {
			$('div#group .apply_group_to_location').removeClass('hidden');
		});

		//address / map
		$('input#formatted_address')
			.change(function () {
				//disable submit until geocoding completes
				formIsNotValid();

				//setting new form
				$('input#latitude').val('');
				$('input#longitude').val('');

				var val = $(this).val().trim();

				if (!val.length) {
					createMap(false);
					$('input#formatted_address').val(''); //clear any spaces
					formIsValid();
					return;
				}

				$.getJSON(
					tsml.ajaxurl,
					{
						action: 'tsml_geocode',
						address: val,
						nonce: tsml.nonce
					},
					function (geocoded) {
						console.log('Geocoded: ', geocoded);
						//check status first, eg REQUEST_DENIED, ZERO_RESULTS
						if (geocoded.status == 'error') return;

						//set lat + lng
						$('input#latitude').val(geocoded.latitude);
						$('input#longitude').val(geocoded.longitude);
						createMap(false, {0: geocoded});

						//guess region if not set
						var region_id = false;
						if (!$('select#region option[selected]').length) {
							$('select#region option').each(function () {
								var region_name = $(this).text().replace('&nbsp;', '').trim();
								if (geocoded.city && region_name == geocoded.city) {
									region_id = $(this).attr('value');
								} else if (geocoded.formatted_address.indexOf(region_name) != -1) {
									region_id = $(this).attr('value');
								}
							});
						}

						//save address and check apply change box status
						$('input#formatted_address').val(geocoded.formatted_address).trigger('keyup');

						$('input#approximate').val(geocoded.approximate);

						//check if location with same address is already in the system, populate form
						$.getJSON(
							tsml.ajaxurl,
							{
								action: 'tsml_address',
								formatted_address: geocoded.formatted_address
							},
							function (data) {
								if (data) {
									$('input[name=location]').val(data.location);
									if (data.region != $('select[name=region]').val()) {
										$('select[name=region] option').prop('selected', false);
										$('select[name=region] option[value=' + data.region + ']').prop('selected', true);
									}
									$('textarea[name=location_notes]').val(data.location_notes);
								}

								if ((!data || !data.region) && !$('select#region option[selected]').length && region_id) {
									//set to guessed region earlier
									$('select[name=region] option[value=' + region_id + ']').prop('selected', true);
								}

								// hide error/warning messages
								resetClasses();

								meeting_is_online = $('input#conference_url').val() != '' || $('input#conference_phone').val() != '';
								// In-person meetings can't have approximate addresses
								if ($('input[name=in_person]:checked').val() == 'yes' && $('input#approximate').val() == 'yes') {
									$('div.form_not_valid').removeClass('hidden');
									$('input#formatted_address').addClass('error');
									formIsNotValid();
								} else if ($('input[name=in_person]:checked').val() == 'no' && $('input#approximate').val() == 'no' && meeting_is_online) {
									$('div.need_approximate_address').removeClass('hidden');
									$('input#location').addClass('warning');
									$('input#formatted_address').addClass('warning');
									formIsValid();
								} else {
									//form is ok to submit again
									formIsValid();
								}
							}
						);
					}
				);
			})
			.keyup(function () {
				//disable submit, will need to do geocoding on change
				var original_address = $(this).attr('data-original-value');
				if (original_address != $(this).val()) {
					formIsNotValid();
				}

				//unhide apply address to location?
				if ($('div.apply_address_to_location').length) {
					if (original_address.length && original_address != $(this).val()) {
						$('div.apply_address_to_location').removeClass('hidden');
					} else {
						$('div.apply_address_to_location').addClass('hidden');
					}
				}
			});

		// Verify address when a change to in_person question
		$('input[name=in_person]').change(function () {
			$('input#formatted_address').change();
		});
		$('input#conference_url').change(function () {
			$('input#formatted_address').change();
		});
		$('input#conference_phone').change(function () {
			$('input#formatted_address').change();
		});

		//when page loads, run lookup
		if ($('input#formatted_address').val()) $('input#formatted_address').trigger('change');
	}
});
