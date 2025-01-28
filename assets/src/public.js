/*
	Javascript for Meetings Archive, Single Meeting, and Single Location pages
	a) procedural logic
	b) event handlers
	c) functions
*/

jQuery(function ($) {
	//a) procedural logic
	var $body = $('body');
	var typeaheadEnabled = false;

	if (typeof tsml_map !== 'object') {
		//main meetings page

		//show/hide upcoming menu option
		toggleUpcoming();

		//if already searching, mark results
		var $search_field = $('#meetings #search input[name=query]');
		if ($search_field.length && $search_field.val().length) {
			$('#tsml td').not('.time').mark($search_field.val());
		}

		var mode = $('#search li.active a').attr('data-id');
		if (mode == 'search') {
			typeaheadEnable();
			//list/map page (only create if we're on the map view)
			if ($('a.toggle-view[data-id="map"]').hasClass('active')) {
				createMap(true, locations);
			}
		} else if (mode == 'location' && $search_field.val().length) {
			doSearch();
		} else if (mode == 'me') {
			doSearch();
		}
	} else {
		//meeting or location detail page
		if (tsml.debug) console.log('public tsml_map', tsml_map);

		var location_link =
			typeof tsml_map.location_url === 'undefined'
				? tsml_map.location
				: formatLink(tsml_map.location_url, tsml_map.location, 'tsml_meeting');
		locations = {};
		locations[tsml_map.location_id] = {
			latitude: tsml_map.latitude,
			longitude: tsml_map.longitude,
			formatted_address: tsml_map.formatted_address,
			approximate: tsml_map.approximate,
			name: tsml_map.location,
			meetings: [],
			directions: tsml_map.directions,
			directions_url: tsml_map.directions_url,
			url: tsml_map.location_url
		};
		createMap(false, locations);
	}

	//b) jQuery event handlers

	//handle directions links; send to Apple Maps (iOS), or Google Maps (everything else)
	$body.on('click', 'a.tsml-directions', function (e) {
		e.preventDefault();

		//latitude,longitude
		var coordinates = [$(this).attr('data-latitude'), $(this).attr('data-longitude')].join();

		//detect if user is on iOS
		var iOS =
			['iPad Simulator', 'iPhone Simulator', 'iPod Simulator', 'iPad', 'iPhone', 'iPod'].includes(navigator.platform) ||
			(navigator.userAgent.includes('Mac') && 'ontouchend' in document);

		if (iOS) {
			//https://developer.apple.com/library/archive/featuredarticles/iPhoneURLScheme_Reference/MapLinks/MapLinks.html
			window.open('maps://?daddr=' + coordinates);
		} else {
			//https://developers.google.com/maps/documentation/urls/get-started#directions-action
			window.open('https://www.google.com/maps/dir/?api=1&destination=' + coordinates);
		}
	});

	//expand region select
	$('.panel-expandable').on('click', '.panel-heading', function (e) {
		$(this).closest('.panel-expandable').toggleClass('expanded');
		if (tsml.debug) console.log('.panel-expandable toggling');
	});

	//single meeting page feedback form
	$('#meeting #feedback').validate({
		onfocusout: false,
		onkeyup: function (element) {},
		highlight: function (element, errorClass, validClass) {
			$(element).parent().addClass('has-error');
		},
		unhighlight: function (element, errorClass, validClass) {
			$(element).parent().removeClass('has-error');
		},
		errorPlacement: function (error, element) {
			return; //don't show message on page, simply highlight
		},
		submitHandler: function (form) {
			var $form = $(form),
				$feedback = $form.closest('#feedback');
			if (!$form.hasClass('running'));
			$.post(tsml.ajaxurl, $form.serialize(), function (data) {
				$form.removeClass('running');
				$feedback.find('.list-group').html('<li class="list-group-item has-info">' + data + '</li>');
			}).fail(function (response) {
				$form.removeClass('running');
				$feedback.find('.list-group').html('<li class="list-group-item has-error">' + tsml.strings.email_not_sent + '</li>');
			});
			$form.addClass('running');
			return false;
		}
	});

	//table sorting
	$('#meetings table thead').on('click', 'th', function () {
		var sort = $(this).attr('class');
		var order;

		//update header
		if ($(this).attr('data-sort')) {
			order = $(this).attr('data-sort') == 'asc' ? 'desc' : 'asc';
		} else {
			order = 'asc';
		}
		$('#meetings table thead th').removeAttr('data-sort');
		$('#meetings table thead th.' + sort).attr('data-sort', order);
		sortMeetings();
	});

	//controls changes
	$('#meetings .controls')
		.on('submit', '#search', function () {
			//capture submit event
			trackAnalytics('search', $search_field.val());
			doSearch();
			return false;
		})
		.on('click', 'div.expand', function (e) {
			//expand or contract regions submenu
			e.preventDefault();
			e.stopPropagation();
			$(this).next('ul.children').toggleClass('expanded');
			$(this).toggleClass('expanded');
		})
		.on('click', '.dropdown-menu a', function (e) {
			//these are live hrefs now
			e.preventDefault();

			//dropdown menu click
			var param = $(this).closest('div').attr('id');

			if (param == 'mode') {
				if (tsml.debug) console.log('dropdown click search mode');

				//only one search mode
				$('#mode li').removeClass('active');

				//remove meeting results
				$('#meetings').addClass('empty');

				//change icon & enable or disable
				if ($(this).attr('data-id') == 'search') {
					$search_field.prop('disabled', false);
					$('#search button i').removeClass().addClass('glyphicon glyphicon-search');
				} else if ($(this).attr('data-id') == 'location') {
					$search_field.prop('disabled', false);
					$('#search button i').removeClass().addClass('glyphicon glyphicon-map-marker');
					setAlert('loc_thinking');
				} else if ($(this).attr('data-id') == 'me') {
					$search_field.prop('disabled', true);
					$('#search button i').removeClass().addClass('glyphicon glyphicon-user');
					setAlert('geo_thinking');
				}

				//change placeholder text
				$search_field.attr('placeholder', $(this).text());
			} else if (param == 'distance') {
				//distance only one
				if (tsml.debug) console.log('dropdown click distance');
				$('#distance li').removeClass('active');
				$('#distance span.selected').html($(this).html());
				trackAnalytics('distance', $(this).text());
			} else if (param == 'region') {
				//switch between region and district mode
				if ($(this).hasClass('switch')) {
					if (tsml.debug) console.log('dropdown click switching between region and district');
					var mode = $(this).parent().hasClass('region') ? 'district' : 'region';
					$(this).closest('#meetings').attr('tax-mode', mode);
					e.stopPropagation();
					return;
				}

				//region only one
				if (tsml.debug) console.log('dropdown click region or district');
				$('#region li').removeClass('active');
				$('#region span.selected').html($(this).html());
				trackAnalytics('region', $(this).text());
			} else if (param == 'day') {
				//day only one selected
				if (tsml.debug) console.log('dropdown click day');
				$('#day li').removeClass('active');
				$('#day span.selected').html($(this).html());
				trackAnalytics('day', $(this).text());
			} else if (param == 'time') {
				//time only one
				if (tsml.debug) console.log('dropdown click time');
				$('#time li').removeClass('active');
				$('#time span.selected').html($(this).html());
				trackAnalytics('time', $(this).text());
			} else if (param == 'type') {
				//type can be multiple
				if (tsml.debug) console.log('dropdown click type');
				if (!e.metaKey) $('#type li').removeClass('active');
				trackAnalytics('type', $(this).text());
			}

			$(this).parent().toggleClass('active');

			//wait to set label on type until we have a complete count
			if (param == 'type') {
				if ($('#type li.active a[data-id]').length) {
					if (tsml.debug) console.log('dropdown click ' + $('#type li.active a[data-id]').length + ' types selected');
					var types = [];
					$('#type li.active a[data-id]').each(function () {
						types.push($(this).text());
					});
					$('#type span.selected').html(types.join(' + '));
				} else {
					if (tsml.debug) console.log('dropdown click no types selected');
					$('#type span.selected').html($(this).text());
				}
			}

			//set page title
			var string = '';
			if ($('#meetings #day li.active').index()) {
				string += $('#meetings #day span.selected').text();
			}
			if ($('#meetings #time li.active').index()) {
				string += ' ' + $('#meetings #time span.selected').text();
			}
			if ($('#meetings #type li.active').index()) {
				string += ' ' + $('#meetings #type span.selected').text();
			}
			string += ' ' + tsml.program + ' Meetings';
			if ($('#meetings #region li.active').index()) {
				string += ' in ' + $('#meetings #region span.selected').text();
			}
			document.title = string;
			$('#tsml #meetings .title h1').text(string);

			if (tsml.debug) console.log('Page title: ', document.title);

			//show/hide upcoming menu option
			toggleUpcoming();

			doSearch();
		});

	//toggle between list and map
	$('#meetings #action .toggle-view').click(function (e) {
		//these are live hrefs now
		e.preventDefault();

		//what's going on
		var action = $(this).attr('data-id');
		var previous = $('#meetings').attr('data-view');

		//don't do anything
		if (action == previous) return;

		//toggle control, set meetings div
		$('#meetings #action .toggle-view').toggleClass('active');
		$('#meetings').attr('data-view', action);

		//init map if it doesn't exist yet
		if (action == 'map') {
			createMap(true, locations, searchLocation);
		}

		//save the query in the query string, if the browser is up to it
		if (history.pushState) {
			if (action == tsml.defaults.view) {
				var url = updateQueryString('tsml-view');
			} else {
				var url = updateQueryString('tsml-view', action);
			}
			window.history.pushState({path: url}, '', url);
		}
	});

	//resize fullscreen on resize
	$(window).resize(function (e) {
		if ($('#meetings').hasClass('tsml_fullscreen')) {
			var center = map.getCenter();
			var height = $(window).height() - 79;
			if ($body.hasClass('admin-bar')) height -= 32;
			$('#map').css('height', height);
			google.maps.event.trigger(map, 'resize');
			map.setCenter(center);
		}
	});

	//c) functions

	//run search (triggered by dropdown toggle or form submit)
	function doSearch() {
		//types and attendanceOptions can be multiple
		var types = [];
		const attendanceOptions = [];
		$('#type li.active a').each(function () {
			let userChoice = $(this).attr('data-id');
			if (userChoice) {
				if (['active', 'in_person', 'hybrid', 'online', 'inactive'].indexOf(userChoice) !== -1) {
					attendanceOptions.push(userChoice);
				} else types.push(userChoice);
			}
		});

		//prepare query for ajax
		var controls = {
			action: 'meetings',
			query: $('#meetings #search input[name=query]').val().trim(),
			mode: $('#search li.active a').attr('data-id'),
			region: $('#region li.region.active a').attr('data-id'),
			district: $('#region li.district.active a').attr('data-id'),
			day: $('#day li.active a').attr('data-id'),
			time: $('#time li.active a').attr('data-id'),
			type: types.length ? types.join(',') : undefined,
			distance: $('#distance li.active a').attr('data-id'),
			view: $('#meetings .toggle-view.active').attr('data-id'),
			attendance_option: attendanceOptions.length ? attendanceOptions.join(',') : undefined
		};
		if (tsml.debug) console.log('doSearch() controls', controls);

		//reset search location
		searchLocation = null;

		//get current query string for history and appending to links
		var query_string = {};
		query_string['tsml-day'] = controls.day ? controls.day : 'any';
		if (controls.mode != 'search' && controls.distance != tsml.defaults.distance) {
			query_string['tsml-distance'] = controls.distance;
		}
		if (controls.mode && controls.mode != tsml.defaults.mode) query_string['tsml-mode'] = controls.mode;
		if (controls.query && controls.query != tsml.defaults.query) query_string['tsml-query'] = controls.query;
		if (controls.mode == 'search') {
			if (controls.region != tsml.defaults.region) {
				query_string['tsml-region'] = controls.region;
			} else if (controls.district != tsml.defaults.district) {
				query_string['tsml-district'] = controls.district;
			}
		}
		if (controls.time && controls.time != tsml.defaults.time) query_string['tsml-time'] = controls.time;
		if (controls.type && controls.type != tsml.defaults.type) query_string['tsml-type'] = controls.type;
		if (controls.view && controls.view != tsml.defaults.view) query_string['tsml-view'] = controls.view;
		if (controls.attendance_option != null) query_string['tsml-attendance_option'] = controls.attendance_option;
		query_string = $.param(query_string);

		//save the query in the query string, if the browser is up to it
		if (history.pushState) {
			var url = window.location.protocol + '//' + window.location.host + window.location.pathname;
			if (query_string.length) url = url + '?' + query_string;
			if (location.search.indexOf('post_type=tsml_meeting') > -1) {
				url = url + (url.indexOf('?') > -1 ? '&' : '?') + 'post_type=tsml_meeting';
			}
			window.history.pushState({path: url}, '', url);
		}

		//set the mode on the parent object
		$('#meetings').attr('data-mode', controls.mode);

		if (controls.mode == 'search') {
			typeaheadEnable();
			removeSearchMarker(); //clear search marker if it exists
			getMeetings(controls);
		} else if (controls.mode == 'location') {
			typeaheadDisable();

			if (controls.query) {
				//start spinner
				$('#search button i').removeClass().addClass('glyphicon glyphicon-refresh spinning');

				//geocode the address
				$.getJSON(
					tsml.ajaxurl,
					{
						action: 'tsml_geocode',
						address: controls.query,
						nonce: tsml.nonce
					},
					function (geocoded) {
						if (tsml.debug) console.log('doSearch() location geocoded', geocoded);
						$('#search button i').removeClass().addClass('glyphicon glyphicon-map-marker');
						if (geocoded.status == 'error') {
							//show error message
							removeSearchMarker(); //clear marker if it exists
							setAlert('loc_error');
						} else {
							$search_field.val(geocoded.formatted_address);
							controls.latitude = geocoded.latitude;
							controls.longitude = geocoded.longitude;
							controls.query = ''; //don't actually keyword search this
							searchLocation = {
								latitude: controls.latitude,
								longitude: controls.longitude
							};
							getMeetings(controls);
						}
					}
				);
			} else {
				setAlert('loc_empty');
			}
		} else if (controls.mode == 'me') {
			if (controls.query) {
				$('#meetings #search input[name=query]').val('');
				controls.query = '';
			}

			//start spinner
			$('#search button i').removeClass().addClass('glyphicon glyphicon-refresh spinning');

			if (navigator.geolocation) {
				navigator.geolocation.getCurrentPosition(
					function (pos) {
						$('#search button i').removeClass().addClass('glyphicon glyphicon-user');
						controls.latitude = pos.coords.latitude;
						controls.longitude = pos.coords.longitude;
						searchLocation = {
							latitude: controls.latitude,
							longitude: controls.longitude
						};
						getMeetings(controls);
					},
					function () {
						//browser supports but can't get geolocation
						if (tsml.debug) console.log('doSearch() didnt get location');
						$('#search button i').removeClass().addClass('glyphicon glyphicon-user'); //todo switch to location
						removeSearchMarker();
						setAlert('geo_error');
					},
					{
						enableHighAccuracy: true,
						timeout: 10000, //10 seconds
						maximumAge: 600000 //10 minutes
					}
				);
			} else {
				//browser doesn't support geolocation
				if (tsml.debug) console.log('doSearch() no browser support for geo');
				$('#search button i').removeClass().addClass('glyphicon glyphicon-user'); //todo switch to location
				removeSearchMarker();
				setAlert('geo_error_browser');
			}
		}
	}

	//actually get the meetings from the JSON resource and output them
	function getMeetings(controls) {
		//request new meetings result
		controls.distance_units = tsml.distance_units;
		controls.nonce = tsml.nonce;

		//make url more readable
		for (var property in controls) {
			if (controls[property] === null || controls[property] === undefined || !controls[property].toString().length) {
				delete controls[property];
			}
		}

		if (tsml.debug) console.log('getMeetings()', tsml.ajaxurl + '?' + $.param(controls));

		$.post(
			tsml.ajaxurl,
			controls,
			function (response) {
				if (tsml.debug) console.log('getMeetings() received', response);

				if (typeof response != 'object' || response == null) {
					//there was a problem with the data source
					$('#meetings').addClass('empty');
					setAlert('data_error');
				} else if (!response.length) {
					//if keyword and no results, clear other parameters and search again
					if (
						(controls.query &&
							(typeof controls.day !== 'undefined' ||
								typeof controls.region !== 'undefined' ||
								typeof controls.time !== 'undefined' ||
								typeof controls.type !== 'undefined')) ||
						typeof controls.attendance_option !== 'undefined'
					) {
						$('#day li').removeClass('active').first().addClass('active');
						$('#time li').removeClass('active').first().addClass('active');
						$('#region li').removeClass('active').first().addClass('active');
						$('#type li').removeClass('active').first().addClass('active');
						$('#attendance_option li').removeClass('active').first().addClass('active');

						//set selected text
						$('#day span.selected').html($('#day li:first-child a').html());
						$('#time span.selected').html($('#time li:first-child a').html());
						$('#region span.selected').html($('#region li:first-child a').html());
						$('#type span.selected').html($('#type li:first-child a').html());
						$('#attendance_option span.selected').html($('#attendance_option li:first-child a').html());

						return doSearch();
					}

					$('#meetings').addClass('empty');
					setAlert('no_meetings');
				} else {
					$('#meetings').removeClass('empty');

					setAlert();

					locations = [];

					var tbody = $('#meetings_tbody').html('');

					//loop through JSON meetings
					$.each(response, function (index, obj) {
						//types could be undefined
						if (!obj.types) obj.types = [];

						var typeList = [];

						//add type 'flags'
						if (typeof tsml.flags == 'object') {
							// True if the meeting is temporarily closed, but online option available
							var meetingIsOnlineAndTC = obj.types.indexOf('TC') !== -1 && obj.types.indexOf('ONL') !== -1;
							for (var i = 0; i < tsml.flags.length; i++) {
								var flagIsTempClosed = tsml.flags[i] === 'TC';
								// True if the type for the meeting obj matches one of the predetermined flags being looped
								var typeIsFlagged = obj.types.indexOf(tsml.flags[i]) !== -1;
								//  Add flag, except TC when meeting is also online
								//if (typeIsFlagged && !(meetingIsOnlineAndTC && flagIsTempClosed)) {
								//obj.name += ' <small>' + tsml.types[tsml.flags[i]] + '</small>';
								//}
								if (typeIsFlagged && tsml.flags[i] != 'TC' && tsml.flags[i] != 'ONL') {
									typeList.push(tsml.types[tsml.flags[i]]);
								}
							}
						}

						//decode types (for hidden type column)
						var types = [];
						for (var i = 0; i < obj.types.length; i++) {
							types.push(tsml.types[obj.types[i]]);
						}
						types.sort();
						types = types.join(', ');

						//save location info for map view
						if (!locations[obj.location_id]) {
							locations[obj.location_id] = {
								name: obj.location,
								formatted_address: obj.formatted_address,
								latitude: obj.latitude,
								longitude: obj.longitude,
								url: obj.location_url,
								meetings: []
							};
						}

						//push meeting on to location
						locations[obj.location_id].meetings[locations[obj.location_id].meetings.length] = {
							name: obj.name,
							time: obj.time_formatted,
							day: obj.day,
							notes: obj.notes,
							url: obj.url
						};

						//classes for table row
						var classes = [];
						if (obj.notes && obj.notes.length) {
							classes.push('notes');
						}
						for (var i = 0; i < obj.types.length; i++) {
							classes.push('type-' + sanitizeTitle(obj.types[i]));
						}
						classes.push('attendance-' + obj.attendance_option);

						//add new table row
						var row = '<tr class="' + classes.join(' ') + '">';
						for (var i = 0; i < tsml.columns.length; i++) {
							switch (tsml.columns[i]) {
								case 'time':
									var sort_time = (typeof obj.day === 'undefined' ? 7 : obj.day) + '-' + (obj.time == '00:00' ? '23:59' : obj.time);
									row +=
										'<td class="time" data-sort="' +
										sort_time +
										'-' +
										sanitizeDataSort(obj.location) +
										'">' +
										formatDayAndTime(obj, controls) +
										'</td>';
									break;

								case 'distance':
									row += '<td class="distance" data-sort="' + obj.distance + '">' + obj.distance + ' ' + tsml.distance_units + '</td>';
									break;

								case 'name':
									row +=
										'<td class="name" data-sort="' +
										sanitizeDataSort(obj.name) +
										'-' +
										sort_time +
										'">' +
										formatLink(obj.url, obj.name, 'post_type');
									if (typeList.length > 0) {
										row += ' <small>' + typeList.join(', ') + '</small>';
									}
									row += '</td>';
									break;

								case 'location':
									row += '<td class="location" data-sort="' + sanitizeDataSort(obj.location) + '-' + sort_time + '">';
									row += '<div class="location-name notranslate">' + obj.location + '</div>';
									row += '<div class="attendance-' + obj.attendance_option + '"><small>';
									switch (obj.attendance_option) {
										case 'online':
											row += 'Online';
											break;
										case 'inactive':
											row += 'Temporarily Inactive';
											break;
										case 'hybrid':
											row += 'In-person and Online';
											break;
										default:
											break;
									}
									row += '</small></div>';
									row += '</td>';
									break;

								case 'location_group':
									meeting_location = obj.location;
									if (obj.attendance_option == 'online' || obj.attendance_option == 'inactive') {
										if (obj.group !== undefined) {
											meeting_location = obj.group;
										} else {
											meeting_location = '';
										}
									}

									row += '<td class="location" data-sort="' + sanitizeDataSort(obj.location) + '-' + sort_time + '">';
									row += '<div class="location-name notranslate">' + meeting_location + '</div>';
									row += '<div class="attendance-' + obj.attendance_option + '"><small>';
									switch (obj.attendance_option) {
										case 'online':
											row += 'Online';
											break;
										case 'inactive':
											row += 'Temporarily Inactive';
											break;
										case 'hybrid':
											row += 'In-person and Online';
											break;
										default:
											break;
									}
									row += '</small></div>';
									row += '</td>';
									break;

								case 'address':
									row +=
										'<td class="address notranslate" data-sort="' +
										sanitizeDataSort(obj.formatted_address) +
										'-' +
										sort_time +
										'">' +
										formatAddress(obj.formatted_address, tsml.street_only) +
										'</td>';
									break;

								case 'region':
									row +=
										'<td class="region notranslate" data-sort="' +
										sanitizeDataSort(obj.sub_region || obj.region || '') +
										'-' +
										sort_time +
										'">' +
										(obj.sub_region || obj.region || '') +
										'</td>';
									break;

								case 'district':
									row +=
										'<td class="district notranslate" data-sort="' +
										sanitizeDataSort(obj.sub_district || obj.district || '') +
										'-' +
										sort_time +
										'">' +
										(obj.sub_district || obj.district || '') +
										'</td>';
									break;

								case 'types':
									row += '<td class="types" data-sort="' + sanitizeDataSort(types) + '-' + sort_time + '">' + types + '</td>';
									break;
							}
						}
						tbody.append(row + '</tr>');
					});

					sortMeetings();

					//highlight search results
					if (controls.query && controls.mode == 'search') {
						$('#tsml td').not('.time').mark(controls.query);
					}

					//build map
					if (controls.view == 'map') {
						createMap(true, locations, searchLocation);
					}

					tbody.trigger('tsml_meetings_updated', {
						meetings: response,
						tbody: tbody
					});
				}
			},
			'json'
		);
	}

	//return Sunday, 4:20pm or 4:20pm, or Appointment
	function formatDayAndTime(obj, controls) {
		if (typeof obj.time_formatted === 'undefined' || typeof obj.day === 'undefined' || typeof tsml.days[obj.day] === 'undefined') {
			//appointment meeting
			return '<span>' + tsml.strings.appointment + '</span>';
		} else if (typeof controls.day !== 'undefined') {
			//day is set, return only the time
			return '<span>' + obj.time_formatted + '</span>';
		}
		return '<span>' + tsml.days[obj.day] + '</span><span>' + obj.time_formatted + '</span>';
	}

	//slugify a string, like WordPress's sanitize_title()
	function sanitizeTitle(str) {
		if (str == null) return '';

		// Convert "str" to a string (sometimes it can be an int; possibly something else)
		str = str.toString();

		str = str.replace(/^\s+|\s+$/g, ''); // trim
		str = str.toLowerCase();

		// remove accents, swap ñ for n, etc
		var from = 'àáäâèéëêìíïîòóöôùúüûñç·/_,:;';
		var to = 'aaaaeeeeiiiioooouuuunc------';

		for (var i = 0, l = from.length; i < l; i++) {
			str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
		}

		str = str
			.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
			.replace(/\s+/g, '-') // collapse whitespace and replace by -
			.replace(/-+/g, '-'); // collapse dashes

		return str;
	}

	function sanitizeDataSort(str) {
		if (str == null) return '';

		// Convert "str" to a string (sometimes it can be an int; possibly something else)
		str = str.toString();

		// For efficiency, create all of the regular expressions only once
		if (typeof tsml_sanitize_data == 'undefined') {
			tsml_sanitize_data = [
				// Strip HTML tags
				[/<[^>]+>/gi, ''],
				// Strip other unwanted chars
				[/[&'"<>]+/g, ''],
				// Replace any slashes, periods, spaces or dashes with a dash (Unicode aware)
				[
					new RegExp(
						'\u005b\u005c\u002f\u005c\u002e\u0020\u00a0\u1680\u2000\u002d\u200a\u202f\u205f\u3000\u005c\u002d\u058a\u05be\u1400\u1806\u2010\u002d\u2015\u2e17\u2e1a\u2e3a\u2e3b\u2e40\u301c\u3030\u30a0\ufe31\ufe32\ufe58\ufe63\uff0d\u005d',
						'g'
					),
					'-'
				],
				// Strip any non-alphanumeric chars (Unicode aware)
				[
					new RegExp(
						'\u005b\u005e\u0041\u002d\u005a\u0061\u002d\u007a\u00aa\u00b5\u00ba\u00c0\u002d\u00d6\u00d8\u002d\u00f6\u00f8\u002d\u02c1\u02c6\u002d\u02d1\u02e0\u002d\u02e4\u02ec\u02ee\u0370\u002d\u0374\u0376\u0377\u037a\u002d\u037d\u037f\u0386\u0388\u002d\u038a\u038c\u038e\u002d\u03a1\u03a3\u002d\u03f5\u03f7\u002d\u0481\u048a\u002d\u052f\u0531\u002d\u0556\u0559\u0560\u002d\u0588\u05d0\u002d\u05ea\u05ef\u002d\u05f2\u0620\u002d\u064a\u066e\u066f\u0671\u002d\u06d3\u06d5\u06e5\u06e6\u06ee\u06ef\u06fa\u002d\u06fc\u06ff\u0710\u0712\u002d\u072f\u074d\u002d\u07a5\u07b1\u07ca\u002d\u07ea\u07f4\u07f5\u07fa\u0800\u002d\u0815\u081a\u0824\u0828\u0840\u002d\u0858\u0860\u002d\u086a\u08a0\u002d\u08b4\u08b6\u002d\u08bd\u0904\u002d\u0939\u093d\u0950\u0958\u002d\u0961\u0971\u002d\u0980\u0985\u002d\u098c\u098f\u0990\u0993\u002d\u09a8\u09aa\u002d\u09b0\u09b2\u09b6\u002d\u09b9\u09bd\u09ce\u09dc\u09dd\u09df\u002d\u09e1\u09f0\u09f1\u09fc\u0a05\u002d\u0a0a\u0a0f\u0a10\u0a13\u002d\u0a28\u0a2a\u002d\u0a30\u0a32\u0a33\u0a35\u0a36\u0a38\u0a39\u0a59\u002d\u0a5c\u0a5e\u0a72\u002d\u0a74\u0a85\u002d\u0a8d\u0a8f\u002d\u0a91\u0a93\u002d\u0aa8\u0aaa\u002d\u0ab0\u0ab2\u0ab3\u0ab5\u002d\u0ab9\u0abd\u0ad0\u0ae0\u0ae1\u0af9\u0b05\u002d\u0b0c\u0b0f\u0b10\u0b13\u002d\u0b28\u0b2a\u002d\u0b30\u0b32\u0b33\u0b35\u002d\u0b39\u0b3d\u0b5c\u0b5d\u0b5f\u002d\u0b61\u0b71\u0b83\u0b85\u002d\u0b8a\u0b8e\u002d\u0b90\u0b92\u002d\u0b95\u0b99\u0b9a\u0b9c\u0b9e\u0b9f\u0ba3\u0ba4\u0ba8\u002d\u0baa\u0bae\u002d\u0bb9\u0bd0\u0c05\u002d\u0c0c\u0c0e\u002d\u0c10\u0c12\u002d\u0c28\u0c2a\u002d\u0c39\u0c3d\u0c58\u002d\u0c5a\u0c60\u0c61\u0c80\u0c85\u002d\u0c8c\u0c8e\u002d\u0c90\u0c92\u002d\u0ca8\u0caa\u002d\u0cb3\u0cb5\u002d\u0cb9\u0cbd\u0cde\u0ce0\u0ce1\u0cf1\u0cf2\u0d05\u002d\u0d0c\u0d0e\u002d\u0d10\u0d12\u002d\u0d3a\u0d3d\u0d4e\u0d54\u002d\u0d56\u0d5f\u002d\u0d61\u0d7a\u002d\u0d7f\u0d85\u002d\u0d96\u0d9a\u002d\u0db1\u0db3\u002d\u0dbb\u0dbd\u0dc0\u002d\u0dc6\u0e01\u002d\u0e30\u0e32\u0e33\u0e40\u002d\u0e46\u0e81\u0e82\u0e84\u0e86\u002d\u0e8a\u0e8c\u002d\u0ea3\u0ea5\u0ea7\u002d\u0eb0\u0eb2\u0eb3\u0ebd\u0ec0\u002d\u0ec4\u0ec6\u0edc\u002d\u0edf\u0f00\u0f40\u002d\u0f47\u0f49\u002d\u0f6c\u0f88\u002d\u0f8c\u1000\u002d\u102a\u103f\u1050\u002d\u1055\u105a\u002d\u105d\u1061\u1065\u1066\u106e\u002d\u1070\u1075\u002d\u1081\u108e\u10a0\u002d\u10c5\u10c7\u10cd\u10d0\u002d\u10fa\u10fc\u002d\u1248\u124a\u002d\u124d\u1250\u002d\u1256\u1258\u125a\u002d\u125d\u1260\u002d\u1288\u128a\u002d\u128d\u1290\u002d\u12b0\u12b2\u002d\u12b5\u12b8\u002d\u12be\u12c0\u12c2\u002d\u12c5\u12c8\u002d\u12d6\u12d8\u002d\u1310\u1312\u002d\u1315\u1318\u002d\u135a\u1380\u002d\u138f\u13a0\u002d\u13f5\u13f8\u002d\u13fd\u1401\u002d\u166c\u166f\u002d\u167f\u1681\u002d\u169a\u16a0\u002d\u16ea\u16f1\u002d\u16f8\u1700\u002d\u170c\u170e\u002d\u1711\u1720\u002d\u1731\u1740\u002d\u1751\u1760\u002d\u176c\u176e\u002d\u1770\u1780\u002d\u17b3\u17d7\u17dc\u1820\u002d\u1878\u1880\u002d\u1884\u1887\u002d\u18a8\u18aa\u18b0\u002d\u18f5\u1900\u002d\u191e\u1950\u002d\u196d\u1970\u002d\u1974\u1980\u002d\u19ab\u19b0\u002d\u19c9\u1a00\u002d\u1a16\u1a20\u002d\u1a54\u1aa7\u1b05\u002d\u1b33\u1b45\u002d\u1b4b\u1b83\u002d\u1ba0\u1bae\u1baf\u1bba\u002d\u1be5\u1c00\u002d\u1c23\u1c4d\u002d\u1c4f\u1c5a\u002d\u1c7d\u1c80\u002d\u1c88\u1c90\u002d\u1cba\u1cbd\u002d\u1cbf\u1ce9\u002d\u1cec\u1cee\u002d\u1cf3\u1cf5\u1cf6\u1cfa\u1d00\u002d\u1dbf\u1e00\u002d\u1f15\u1f18\u002d\u1f1d\u1f20\u002d\u1f45\u1f48\u002d\u1f4d\u1f50\u002d\u1f57\u1f59\u1f5b\u1f5d\u1f5f\u002d\u1f7d\u1f80\u002d\u1fb4\u1fb6\u002d\u1fbc\u1fbe\u1fc2\u002d\u1fc4\u1fc6\u002d\u1fcc\u1fd0\u002d\u1fd3\u1fd6\u002d\u1fdb\u1fe0\u002d\u1fec\u1ff2\u002d\u1ff4\u1ff6\u002d\u1ffc\u2071\u207f\u2090\u002d\u209c\u2102\u2107\u210a\u002d\u2113\u2115\u2119\u002d\u211d\u2124\u2126\u2128\u212a\u002d\u212d\u212f\u002d\u2139\u213c\u002d\u213f\u2145\u002d\u2149\u214e\u2183\u2184\u2c00\u002d\u2c2e\u2c30\u002d\u2c5e\u2c60\u002d\u2ce4\u2ceb\u002d\u2cee\u2cf2\u2cf3\u2d00\u002d\u2d25\u2d27\u2d2d\u2d30\u002d\u2d67\u2d6f\u2d80\u002d\u2d96\u2da0\u002d\u2da6\u2da8\u002d\u2dae\u2db0\u002d\u2db6\u2db8\u002d\u2dbe\u2dc0\u002d\u2dc6\u2dc8\u002d\u2dce\u2dd0\u002d\u2dd6\u2dd8\u002d\u2dde\u2e2f\u3005\u3006\u3031\u002d\u3035\u303b\u303c\u3041\u002d\u3096\u309d\u002d\u309f\u30a1\u002d\u30fa\u30fc\u002d\u30ff\u3105\u002d\u312f\u3131\u002d\u318e\u31a0\u002d\u31ba\u31f0\u002d\u31ff\u3400\u002d\u4db5\u4e00\u002d\u9fef\ua000\u002d\ua48c\ua4d0\u002d\ua4fd\ua500\u002d\ua60c\ua610\u002d\ua61f\ua62a\ua62b\ua640\u002d\ua66e\ua67f\u002d\ua69d\ua6a0\u002d\ua6e5\ua717\u002d\ua71f\ua722\u002d\ua788\ua78b\u002d\ua7bf\ua7c2\u002d\ua7c6\ua7f7\u002d\ua801\ua803\u002d\ua805\ua807\u002d\ua80a\ua80c\u002d\ua822\ua840\u002d\ua873\ua882\u002d\ua8b3\ua8f2\u002d\ua8f7\ua8fb\ua8fd\ua8fe\ua90a\u002d\ua925\ua930\u002d\ua946\ua960\u002d\ua97c\ua984\u002d\ua9b2\ua9cf\ua9e0\u002d\ua9e4\ua9e6\u002d\ua9ef\ua9fa\u002d\ua9fe\uaa00\u002d\uaa28\uaa40\u002d\uaa42\uaa44\u002d\uaa4b\uaa60\u002d\uaa76\uaa7a\uaa7e\u002d\uaaaf\uaab1\uaab5\uaab6\uaab9\u002d\uaabd\uaac0\uaac2\uaadb\u002d\uaadd\uaae0\u002d\uaaea\uaaf2\u002d\uaaf4\uab01\u002d\uab06\uab09\u002d\uab0e\uab11\u002d\uab16\uab20\u002d\uab26\uab28\u002d\uab2e\uab30\u002d\uab5a\uab5c\u002d\uab67\uab70\u002d\uabe2\uac00\u002d\ud7a3\ud7b0\u002d\ud7c6\ud7cb\u002d\ud7fb\uf900\u002d\ufa6d\ufa70\u002d\ufad9\ufb00\u002d\ufb06\ufb13\u002d\ufb17\ufb1d\ufb1f\u002d\ufb28\ufb2a\u002d\ufb36\ufb38\u002d\ufb3c\ufb3e\ufb40\ufb41\ufb43\ufb44\ufb46\u002d\ufbb1\ufbd3\u002d\ufd3d\ufd50\u002d\ufd8f\ufd92\u002d\ufdc7\ufdf0\u002d\ufdfb\ufe70\u002d\ufe74\ufe76\u002d\ufefc\uff21\u002d\uff3a\uff41\u002d\uff5a\uff66\u002d\uffbe\uffc2\u002d\uffc7\uffca\u002d\uffcf\uffd2\u002d\uffd7\uffda\u002d\uffdc\u0030\u002d\u0039\u00b2\u00b3\u00b9\u00bc\u002d\u00be\u0660\u002d\u0669\u06f0\u002d\u06f9\u07c0\u002d\u07c9\u0966\u002d\u096f\u09e6\u002d\u09ef\u09f4\u002d\u09f9\u0a66\u002d\u0a6f\u0ae6\u002d\u0aef\u0b66\u002d\u0b6f\u0b72\u002d\u0b77\u0be6\u002d\u0bf2\u0c66\u002d\u0c6f\u0c78\u002d\u0c7e\u0ce6\u002d\u0cef\u0d58\u002d\u0d5e\u0d66\u002d\u0d78\u0de6\u002d\u0def\u0e50\u002d\u0e59\u0ed0\u002d\u0ed9\u0f20\u002d\u0f33\u1040\u002d\u1049\u1090\u002d\u1099\u1369\u002d\u137c\u16ee\u002d\u16f0\u17e0\u002d\u17e9\u17f0\u002d\u17f9\u1810\u002d\u1819\u1946\u002d\u194f\u19d0\u002d\u19da\u1a80\u002d\u1a89\u1a90\u002d\u1a99\u1b50\u002d\u1b59\u1bb0\u002d\u1bb9\u1c40\u002d\u1c49\u1c50\u002d\u1c59\u2070\u2074\u002d\u2079\u2080\u002d\u2089\u2150\u002d\u2182\u2185\u002d\u2189\u2460\u002d\u249b\u24ea\u002d\u24ff\u2776\u002d\u2793\u2cfd\u3007\u3021\u002d\u3029\u3038\u002d\u303a\u3192\u002d\u3195\u3220\u002d\u3229\u3248\u002d\u324f\u3251\u002d\u325f\u3280\u002d\u3289\u32b1\u002d\u32bf\ua620\u002d\ua629\ua6e6\u002d\ua6ef\ua830\u002d\ua835\ua8d0\u002d\ua8d9\ua900\u002d\ua909\ua9d0\u002d\ua9d9\ua9f0\u002d\ua9f9\uaa50\u002d\uaa59\uabf0\u002d\uabf9\uff10\u002d\uff19\u0300\u002d\u036f\u0483\u002d\u0489\u0591\u002d\u05bd\u05bf\u05c1\u05c2\u05c4\u05c5\u05c7\u0610\u002d\u061a\u064b\u002d\u065f\u0670\u06d6\u002d\u06dc\u06df\u002d\u06e4\u06e7\u06e8\u06ea\u002d\u06ed\u0711\u0730\u002d\u074a\u07a6\u002d\u07b0\u07eb\u002d\u07f3\u07fd\u0816\u002d\u0819\u081b\u002d\u0823\u0825\u002d\u0827\u0829\u002d\u082d\u0859\u002d\u085b\u08d3\u002d\u08e1\u08e3\u002d\u0903\u093a\u002d\u093c\u093e\u002d\u094f\u0951\u002d\u0957\u0962\u0963\u0981\u002d\u0983\u09bc\u09be\u002d\u09c4\u09c7\u09c8\u09cb\u002d\u09cd\u09d7\u09e2\u09e3\u09fe\u0a01\u002d\u0a03\u0a3c\u0a3e\u002d\u0a42\u0a47\u0a48\u0a4b\u002d\u0a4d\u0a51\u0a70\u0a71\u0a75\u0a81\u002d\u0a83\u0abc\u0abe\u002d\u0ac5\u0ac7\u002d\u0ac9\u0acb\u002d\u0acd\u0ae2\u0ae3\u0afa\u002d\u0aff\u0b01\u002d\u0b03\u0b3c\u0b3e\u002d\u0b44\u0b47\u0b48\u0b4b\u002d\u0b4d\u0b56\u0b57\u0b62\u0b63\u0b82\u0bbe\u002d\u0bc2\u0bc6\u002d\u0bc8\u0bca\u002d\u0bcd\u0bd7\u0c00\u002d\u0c04\u0c3e\u002d\u0c44\u0c46\u002d\u0c48\u0c4a\u002d\u0c4d\u0c55\u0c56\u0c62\u0c63\u0c81\u002d\u0c83\u0cbc\u0cbe\u002d\u0cc4\u0cc6\u002d\u0cc8\u0cca\u002d\u0ccd\u0cd5\u0cd6\u0ce2\u0ce3\u0d00\u002d\u0d03\u0d3b\u0d3c\u0d3e\u002d\u0d44\u0d46\u002d\u0d48\u0d4a\u002d\u0d4d\u0d57\u0d62\u0d63\u0d82\u0d83\u0dca\u0dcf\u002d\u0dd4\u0dd6\u0dd8\u002d\u0ddf\u0df2\u0df3\u0e31\u0e34\u002d\u0e3a\u0e47\u002d\u0e4e\u0eb1\u0eb4\u002d\u0ebc\u0ec8\u002d\u0ecd\u0f18\u0f19\u0f35\u0f37\u0f39\u0f3e\u0f3f\u0f71\u002d\u0f84\u0f86\u0f87\u0f8d\u002d\u0f97\u0f99\u002d\u0fbc\u0fc6\u102b\u002d\u103e\u1056\u002d\u1059\u105e\u002d\u1060\u1062\u002d\u1064\u1067\u002d\u106d\u1071\u002d\u1074\u1082\u002d\u108d\u108f\u109a\u002d\u109d\u135d\u002d\u135f\u1712\u002d\u1714\u1732\u002d\u1734\u1752\u1753\u1772\u1773\u17b4\u002d\u17d3\u17dd\u180b\u002d\u180d\u1885\u1886\u18a9\u1920\u002d\u192b\u1930\u002d\u193b\u1a17\u002d\u1a1b\u1a55\u002d\u1a5e\u1a60\u002d\u1a7c\u1a7f\u1ab0\u002d\u1abe\u1b00\u002d\u1b04\u1b34\u002d\u1b44\u1b6b\u002d\u1b73\u1b80\u002d\u1b82\u1ba1\u002d\u1bad\u1be6\u002d\u1bf3\u1c24\u002d\u1c37\u1cd0\u002d\u1cd2\u1cd4\u002d\u1ce8\u1ced\u1cf4\u1cf7\u002d\u1cf9\u1dc0\u002d\u1df9\u1dfb\u002d\u1dff\u20d0\u002d\u20f0\u2cef\u002d\u2cf1\u2d7f\u2de0\u002d\u2dff\u302a\u002d\u302f\u3099\u309a\ua66f\u002d\ua672\ua674\u002d\ua67d\ua69e\ua69f\ua6f0\ua6f1\ua802\ua806\ua80b\ua823\u002d\ua827\ua880\ua881\ua8b4\u002d\ua8c5\ua8e0\u002d\ua8f1\ua8ff\ua926\u002d\ua92d\ua947\u002d\ua953\ua980\u002d\ua983\ua9b3\u002d\ua9c0\ua9e5\uaa29\u002d\uaa36\uaa43\uaa4c\uaa4d\uaa7b\u002d\uaa7d\uaab0\uaab2\u002d\uaab4\uaab7\uaab8\uaabe\uaabf\uaac1\uaaeb\u002d\uaaef\uaaf5\uaaf6\uabe3\u002d\uabea\uabec\uabed\ufb1e\ufe00\u002d\ufe0f\ufe20\u002d\ufe2f\u002d\u005d\u002b',
						'g'
					),
					''
				],
				// Replace any runs of dashes with a single dash
				[/\-+/g, '-'],
				// Strip leading/trailing dash if they exist
				[/^\-|\-$/g, '']
			];
		}

		// Use the textarea element to decode HTML entities.  This is important for languages that use them.  For example, Polish uses &oacute;.
		var e = document.createElement('textarea');
		e.innerHTML = str;
		str = e.value;

		// Apply all regular expressions
		a = window.tsml_sanitize_data;
		for (var i = 0; i < a.length; i++) {
			str = str.replace(a[i][0], a[i][1]);
		}

		// Lower case the string
		return str.toLowerCase();
	}

	//set or clear the alert message
	function setAlert(message_key) {
		if (typeof message_key == 'undefined') {
			$('#alert').html('').addClass('hidden');
		} else {
			$('#alert').html(tsml.strings[message_key]).removeClass('hidden');
		}
	}

	//sort the meetings by the current sort criteria after getting ajax
	function sortMeetings() {
		var $sorted = $('#meetings table thead th[data-sort]').first();
		var sort = $sorted.attr('class');
		var order = $sorted.attr('data-sort');
		var tbody = document.getElementById('meetings_tbody');
		var store = [];
		var sort_index = $('#meetings table thead th').index($sorted);

		//execute sort
		for (var i = 0, len = tbody.rows.length; i < len; i++) {
			var row = tbody.rows[i];
			var value = row.cells[sort_index].getAttribute('data-sort');
			if (sort == 'distance') value = parseFloat(value);
			store.push([value, row]);
		}

		var compareFunction;

		if (sort === 'distance') {
			compareFunction = function ([valueA], [valueB]) {
				return order === 'asc' ? valueA - valueB : valueB - valueA;
			};
		} else if (window.Intl) {
			// Do locale-aware sort, falling back on English if needed.
			// NOTE: The locale follows whatever language Wordpress is configured to use
			var locales = [document.documentElement.lang];
			if (!(locales[0].startsWith != 'en')) locales.push('en');
			var collator = new Intl.Collator(locales, {sensitivity: 'variant'});
			compareFunction = function (x, y) {
				return collator.compare(x[0], y[0]) * (order == 'asc' ? 1 : -1);
			};
		} else {
			// No Intl object. Must be a very old browser. Do the best we can
			compareFunction = function (x, y) {
				if (x[0] > y[0]) return order == 'asc' ? 1 : -1;
				if (x[0] < y[0]) return order == 'asc' ? -1 : 1;
				return 0;
			};
		}
		store.sort(compareFunction);

		for (var i = 0, len = store.length; i < len; i++) {
			tbody.appendChild(store[i][1]);
		}
	}

	//if day is today, show 'upcoming' time option, otherwise hide it
	function toggleUpcoming() {
		var current_day = new Date().getDay();
		var selected_day = $('#day li.active a').first().attr('data-id');
		var selected_time = $('#time li.active a').first().attr('data-id');
		if (current_day != selected_day) {
			$('#time li.upcoming').addClass('hidden');
			if (selected_time == 'upcoming') {
				$('#time li.active').removeClass('active');
				$('#time li').first().addClass('active');
				$('#time span.selected').html($('#time li a').first().text());
			}
		} else {
			$('#time li.upcoming').removeClass('hidden');
		}
	}

	//send event to google analytics, if loaded
	function trackAnalytics(action, label) {
		if (typeof ga === 'function') {
			if (tsml.debug) console.log('trackAnalytics() sending ' + action + ': ' + label + ' to google analytics');
			ga('send', 'event', '12 Step Meeting List', action, label);
		}
	}

	//Create the custom widget for search autocomplete
	$.widget('custom.autocomplete', $.ui.autocomplete, {
		_create: function () {
			this._super();
			this.widget().menu('option', 'items', '> :not(.ui-autocomplete-category)');
		},
		_renderItem: function (ul, item) {
			var matcher = new RegExp($.ui.autocomplete.escapeRegex(this.term), 'ig');
			var output = item.label.replace(matcher, '<strong>' + this.term + '</strong>');
			return $('<li>').attr('data-value', item.value).append(output).appendTo(ul);
		},
		_renderMenu: function (ul, items) {
			var that = this,
				currentType = '';
			$.each(items, function (index, item) {
				var li;
				if (item.type != currentType) {
					const key = `${item.type}s`;
					ul.append("<li class='ui-autocomplete-category'>" + tsml.strings[key] + '</li>');
					currentType = item.type;
				}
				li = that._renderItemData(ul, item);
				if (item.type) {
					li.attr('aria-label', item.type + ' : ' + item.value);
				}
			});
		}
	});

	//disable the typeahead (if you switched to a different search mode)
	function typeaheadDisable() {
		if (!typeaheadEnabled) return;
		typeaheadEnabled = false;
		$('#meetings #search input[name="query"]').autocomplete('destroy');
	}

	//enable the typeahead (if you switch back to search)
	function typeaheadEnable() {
		if (typeaheadEnabled) return;
		if (tsml.debug) console.log('typeaheadEnable()');
		$.getJSON(tsml.ajaxurl + '?action=tsml_typeahead').done(function (search_data) {
			typeaheadEnabled = true;
			$('#meetings #search input[name="query"]').autocomplete({
				autoFocus: false,
				source: search_data,
				minLength: 1,
				select: function (event, ui) {
					const {item} = ui;
					if (tsml.debug) console.log('item: ', item);
					if (item.type == 'region') {
						$('#region li').removeClass('active');
						var active = $('#region li a[data-id="' + item.id + '"]');
						active.parent().addClass('active');
						if (tsml.debug) console.log('Active: ', active);
						$('#region span.selected').html(active.html());
						$('#search input[name="query"]').val('');
						event.preventDefault();
						trackAnalytics('region', active.text());
						doSearch();
					} else if (item.type == 'location') {
						trackAnalytics('location', item.value);
						location.href = item.url;
					} else if (item.type == 'group') {
						trackAnalytics('group', item.value);
						doSearch();
					}
				}
			});
		});
	}

	function showResult(event, ui) {
		$('#search input').text(ui.item.label);
	}

	//set a param on the query string
	function updateQueryString(key, value, url) {
		if (!url) url = window.location.href;
		var re = new RegExp('([?&])' + key + '=.*?(&|#|$)(.*)', 'gi'),
			hash;

		if (re.test(url)) {
			if (typeof value !== 'undefined' && value !== null) {
				return url.replace(re, '$1' + key + '=' + value + '$2$3');
			} else {
				hash = url.split('#');
				url = hash[0].replace(re, '$1$3').replace(/(&|\?)$/, '');
				if (typeof hash[1] !== 'undefined' && hash[1] !== null) url += '#' + hash[1];
				return url;
			}
		} else {
			if (typeof value !== 'undefined' && value !== null) {
				var separator = url.indexOf('?') !== -1 ? '&' : '?';
				hash = url.split('#');
				url = hash[0] + separator + key + '=' + value;
				if (typeof hash[1] !== 'undefined' && hash[1] !== null) url += '#' + hash[1];
				return url;
			} else {
				return url;
			}
		}
	}
});
