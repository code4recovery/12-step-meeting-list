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
		const feedName = $(this).parents('[data-source]').find('[data-source-name]').text().trim();
        if (confirm(`Remove the ${feedName} feed?`)) {
            $(this).parent().submit();
        }
	});

	//meeting add / edit page
	var $post_type = $('input#post_type');
	if ($post_type.length && $post_type.val() == 'tsml_meeting') {
		//make sure geocoding is finished (basic form validation)
		var form_valid = true;

        var $form = $('form#post');
        var $fields = {
            day:               $form.find('select#day'),
            time:              $form.find('input#time'),
            end_time:          $form.find('input#end_time'),
            types:             $form.find('input[name="types[]"]'),
            conference_url:    $form.find('input#conference_url'),
            conference_phone:  $form.find('input#conference_phone'),
            in_person:         $form.find('input[name=in_person]'),
            location:          $form.find('input#location'),
            location_notes:    $form.find('textarea[name=location_notes]'),
            formatted_address: $form.find('input#formatted_address'),
            approximate:       $form.find('input#approximate'),
            latitude:          $form.find('input[name=latitude]'),
            longitude:         $form.find('input[name=longitude]'),
			group:             $form.find('input#group'),
			group_notes:       $form.find('textarea[name=group_notes]'),
			group_status:      $form.find('input[name="group_status"]'),
            region:            $form.find('select[name=region]'),
            district:          $form.find('select[name=district]'),
            website:           $form.find('input[name=website]'),
            email:             $form.find('input[name=email]'),
            phone:             $form.find('input[name=phone]'),
            contact_1_name:    $form.find('input[name=contact_1_name]'),
            contact_1_email:   $form.find('input[name=contact_1_email]'),
            contact_1_phone:   $form.find('input[name=contact_1_phone]'),
            contact_2_name:    $form.find('input[name=contact_2_name]'),
            contact_2_email:   $form.find('input[name=contact_2_email]'),
            contact_2_phone:   $form.find('input[name=contact_2_phone]'),
            contact_3_name:    $form.find('input[name=contact_3_name]'),
            contact_3_email:   $form.find('input[name=contact_3_email]'),
            contact_3_phone:   $form.find('input[name=contact_3_phone]'),
            mailing_address:   $form.find('input[name=mailing_address]'),
            venmo:             $form.find('input[name=venmo]'),
            paypal:            $form.find('input[name=paypal]'),
            last_contact:      $form.find('input[name=last_contact]'),
        };

        // set a state for $fields 
        //    state: loading, error, warning
        //    code:  a code that corresponds with a field <small data-message> attribute
        $.fn.setState = function(state, code) {
            var $field = $(this);
            $field.siblings('[data-message]').removeClass('show');
            $field.removeClass('error warning');
            if (-1 === ['warning','loading','error'].indexOf(state)) {
                state = '';
            }
            $field.data('state', state);
            if ('error' === state || 'warning' === state) {
                $field.addClass(state);
                if (code) {
                    $field.siblings('[data-message=' + code + ']').addClass('show');
                }
            }
            updateFormState();
            return this;
        };
        // clear state, meaning the field is good
        $.fn.clearState = function() {
            return $(this).setState();
        }

        // after field states update, toggle publish button
        function updateFormState() {
            var pendingFields = [];
            Object.keys($fields).forEach(function(field) {
                var state = $fields[field].data('state');
                if (-1 < ['error','loading'].indexOf(state)) {
                    pendingFields.push($fields[field][0])
                }
            });
            form_valid = !pendingFields.length;
            $('#publish').toggleClass('disabled', !form_valid);
        }

		// Hide all errors/warnings
		function resetClasses() {
			$('div.need_approximate_address').addClass('hidden');
			$fields.formatted_address.removeClass('error');
			$fields.location.removeClass('warning');
			$fields.formatted_address.removeClass('warning');
		}

		$form.submit(function () {
			return form_valid;
		});

		//show more types
		$('.toggle_more').on('click', 'a', function (e) {
			e.preventDefault();
			$(this).closest('.checkboxes').toggleClass('showing_more');
		});

		//day picker
		$fields.day.on('change', function () {
			var val = $(this).val();
			// If a day is selected, not Appointment
			if (val) {
				$fields.time.removeAttr('disabled');
				$fields.end_time.removeAttr('disabled');
				// Put a value in time and end_time if they don't already have a value
				if (!$fields.time.val()) {
					$fields.time.val('00:00').timepicker();
					$fields.end_time.val('01:00').timepicker();
				}
			} else {
				// Appointment is sellected
				$fields.time.attr('data-value', $fields.time.val()).val('').attr('disabled', 'disabled');
				$fields.end_time.attr('data-value', $fields.end_time.val()).val('').attr('disabled', 'disabled');
			}
		});

		//time pickeres
		$('input.time').timepicker();

		//auto-suggest end time (todo maybe think about using moment for this)
		$fields.time.on('change', function () {
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
			$fields.end_time.val(hours + ':' + minutes + ' ' + ampm);
		});

		//types checkboxes: ensure not both open and closed
        $fields.types.on('change', function () {
			if (
				$fields.types.filter('[value="C"]').prop('checked') &&
				$fields.types.filter('[value="O"]').prop('checked')
			) {
				if ($(this).val() == 'C') {
					$fields.types.filter('[value="O"]').prop('checked', false);
				} else {
					$fields.types.filter('[value="C"]').prop('checked', false);
				}
			}
		});

		// location typeahead
		$.getJSON(tsml.ajaxurl + '?action=tsml_locations', function (data) {
			$fields.location.autocomplete({
				source: data,
				minLength: 1,
				select: function ($e, selected) {
					var location = selected.item;
					if (tsml.debug) console.log('Location: ', location);
					$fields.formatted_address.val(location.formatted_address).trigger('change');
					$fields.latitude.val(location.latitude);
					$fields.longitude.val(location.longitude);
					$('select[name=region] option[value=' + location.region + ']').prop('selected', true);
					$fields.location_notes.val(location.notes);
				}
			});
		});

		// group typeahead
		$.getJSON(tsml.ajaxurl + '?action=tsml_groups', function (data) {
			$fields.group.autocomplete({
				source: data,
				minLength: 1,
				select: function ($e, selected) {
					var group = selected.item;
					if (tsml.debug) console.log('Selected: ', selected);
					$fields.district.val(group.district);
					$fields.website.val(group.website);
					$fields.email.val(group.email);
					$fields.phone.val(group.phone);
					$fields.contact_1_name.val(group.contact_1_name);
					$fields.contact_1_email.val(group.contact_1_email);
					$fields.contact_1_phone.val(group.contact_1_phone);
					$fields.contact_2_name.val(group.contact_2_name);
					$fields.contact_2_email.val(group.contact_2_email);
					$fields.contact_2_phone.val(group.contact_2_phone);
					$fields.contact_3_name.val(group.contact_3_name);
					$fields.contact_3_email.val(group.contact_3_email);
					$fields.contact_3_phone.val(group.contact_3_phone);
					$fields.mailing_address.val(group.mailing_address);
					$fields.venmo.val(group.venmo);
					$fields.last_contact.val(group.last_contact);
					$fields.group_notes.val(group.notes);
				}
			});
		});

		$fields.group_status.on('change', function () {
			$('#contact-type').attr('data-type', $(this).val());
			if ($(this).val() == 'meeting') {
				$fields.group.val('');
				$fields.group_notes.val('');
				$fields.district.val('');
				$('.apply_group_to_location').addClass('hidden');
			}
		});

		$fields.group.on('change', function () {
			$('div#group .apply_group_to_location').removeClass('hidden');
		});

		//address / map
		$fields.formatted_address
			.on('change', function () {
				//disable submit until geocoding completes
				$fields.formatted_address.setState('loading');

				//setting new form
				$fields.latitude.val('');
				$fields.longitude.val('');

				var val = $(this).val().trim();

				if (!val.length) {
					createMap(false);
					$fields.formatted_address.val(''); //clear any spaces
					$fields.formatted_address.clearState();
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
						if (geocoded.status == 'error') {
                            $fields.formatted_address.setState('error', 2);
                            return;
                        }

						//set lat + lng
						$fields.latitude.val(geocoded.latitude);
						$fields.longitude.val(geocoded.longitude);
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
						$fields.formatted_address.val(geocoded.formatted_address).trigger('keyup');

						$fields.approximate.val(geocoded.approximate);

						//check if location with same address is already in the system, populate form
						$.getJSON(
							tsml.ajaxurl,
							{
								action: 'tsml_address',
								formatted_address: geocoded.formatted_address
							},
							function (data) {
								if (data) {
									$fields.location.val(data.location);
									if (data.region != $fields.region.val()) {
										$('select[name=region] option').prop('selected', false);
										$('select[name=region] option[value=' + data.region + ']').prop('selected', true);
									}
									$fields.location_notes.val(data.location_notes);
								}

								if ((!data || !data.region) && !$('select#region option[selected]').length && region_id) {
									//set to guessed region earlier
									$('select[name=region] option[value=' + region_id + ']').prop('selected', true);
								}

								// hide error/warning messages
								resetClasses();

								meeting_is_online = $fields.conference_url.val() != '' || $fields.conference_phone.val() != '';
								// In-person meetings can't have approximate addresses
								if ($('input[name=in_person]:checked').val() == 'yes' && $fields.approximate.val() == 'yes') {
									$fields.formatted_address.setState('error', 1);
								} else if ($('input[name=in_person]:checked').val() == 'no' && $fields.approximate.val() == 'no' && meeting_is_online) {
                                    $('div.need_approximate_address').removeClass('hidden');
									$fields.location.addClass('warning');
                                    $fields.formatted_address.setState('warning');
								} else {
									//field is good
									$fields.formatted_address.clearState();
								}
							}
						);
					}
				);
			})
			.on('keyup', function () {
				//disable submit, will need to do geocoding on change
				var original_address = $(this).attr('data-original-value');
				if (original_address != $(this).val()) {
					$fields.formatted_address.setState('loading');
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
		$fields.in_person.on('change', function () {
			$fields.formatted_address.trigger('change');
		});

        // Conference URL validation
        $fields.conference_url.validate = function() {
            $fields.conference_url.clearState();
            var conferenceUrl = decodeURI($fields.conference_url.val());
            // if is zoom...
            if (conferenceUrl.match(/\bzoom\.us\b/i)) {
                // but doesn't include meeting number, error
                var zoomUrlParts = conferenceUrl.match(/^(https?:\/\/)*([a-z0-9]+\.)*zoom\.us\/j\/(\d{8,20})(.*)$/i);
                if (! zoomUrlParts ) {
                    $fields.conference_url.setState('warning', 1);
                    return;
                }
                // else cleanup zoom url
                var newZoomUrl = 'https://zoom.us/j/' + zoomUrlParts[3];
                var zoomPwd = conferenceUrl.match(/[\?\&](pwd=[a-z0-9]{28,36})/i);
                if (zoomPwd) {
                    newZoomUrl += '?' + zoomPwd[1];
                }
                if (conferenceUrl !== newZoomUrl) {
                    $fields.conference_url.val(newZoomUrl);
                    $fields.conference_url.setState('warning', 2);
                }
            }
        };

        // validate conference url on change and once on initial load
		$fields.conference_url.on('change', $fields.conference_url.validate);
        $fields.conference_url.validate();

		$fields.conference_phone.on('change', function () {
			$fields.formatted_address.trigger('change');
		});

        // validate paypal name
        $fields.paypal.validate = function() {
            var value = $fields.paypal.val().trim();
            // must be letters and numbers, under 20 characters
            // reference: https://www.paypal.com/us/cshelp/article/what-is-paypalme-help432
            if (value && !value.match(/^[a-z0-9]{1,20}$/i)) {
                $fields.paypal.setState('error', 1);
            } else {
                $fields.paypal.clearState();
            }
        }
        $fields.paypal.on('change', $fields.paypal.validate);
        $fields.paypal.validate();

		//when page loads, run lookup
		if ($fields.formatted_address.val()) $fields.formatted_address.trigger('change');
	}
});
