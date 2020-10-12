/*
	Javascript for Meetings Archive, Single Meeting, and Single Location pages
	a) procedural logic
	b) event handlers
	c) functions
*/

jQuery(function($) {
	//a) procedural logic
	var $body = $('body');
	var typeaheadEnabled = false;

	if (typeof tsml_map !== 'object') {
		//main meetings page

		//search typeahead
		var tsml_regions = new Bloodhound({
			datumTokenizer: Bloodhound.tokenizers.obj.nonword('value'),
			queryTokenizer: Bloodhound.tokenizers.whitespace,
			prefetch: {
				url: tsml.ajaxurl + '?action=tsml_regions',
				cache: false
			}
		});
		var tsml_groups = new Bloodhound({
			datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value'),
			queryTokenizer: Bloodhound.tokenizers.whitespace,
			prefetch: {
				url: tsml.ajaxurl + '?action=tsml_groups',
				cache: false
			}
		});
		var tsml_locations = new Bloodhound({
			datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value'),
			queryTokenizer: Bloodhound.tokenizers.whitespace,
			prefetch: {
				url: tsml.ajaxurl + '?action=tsml_locations',
				cache: false
			}
		});

		//show/hide upcoming menu option
		toggleUpcoming();

		//if already searching, mark results
		var $search_field = $('#meetings #search input[name=query]');
		if ($search_field.length && $search_field.val().length) {
			$('#tsml td')
				.not('.time')
				.mark($search_field.val());
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

		var location_link =
			typeof tsml_map.location_url === 'undefined'
				? tsml_map.location
				: formatLink(tsml_map.location_url, tsml_map.location, 'tsml_meeting');
		locations = {};
		locations[tsml_map.location_id] = {
			latitude: tsml_map.latitude,
			longitude: tsml_map.longitude,
			formatted_address: tsml_map.formatted_address,
			name: tsml_map.location,
			meetings: [],
			directions: tsml_map.directions,
			directions_url: tsml_map.directions_url,
			url: tsml_map.location_url
		};
		createMap(false, locations);

		// Grab meeting and phone if they exist.
		var meeting_el = document.getElementById('meeting-link'),
			phone_el = document.getElementById('phone-link');
		// If Meeting URL exists, grab it from meta and send user on their way
		if (meeting_el) {
			meeting_el.addEventListener('click', function(e) {
				e.preventDefault();
				$.get(tsml.ajaxurl, {
					action: 'meeting_link',
					meeting_id: tsml.meeting_id,
					nonce: tsml.nonce
				})
					.done(function(result) {
						if (result.success) {
							window.location.assign(result.data.meeting);
							// window.open( result.data.meeting );
						}
					})
					.fail(function(err) {
						// TODO: improve
						console.log('FAIL');
					});
			});
		}
		// If Phone Number exists grab it from meta and send user on their way
		if (phone_el) {
			phone_el.addEventListener('click', function(e) {
				e.preventDefault();
				$.get(tsml.ajaxurl, {
					action: 'phone_link',
					meeting_id: tsml.meeting_id,
					nonce: tsml.nonce
				})
					.done(function(result) {
						if (result.success) {
							window.location.assign(result.data.phone);
						}
					})
					.fail(function(err) {
						// TODO: improve
						console.log('FAIL');
					});
			});
		}
	}

	//b) jQuery event handlers

	//handle directions links; send to Apple Maps (iOS), or Google Maps (everything else)
	var iOS = !!navigator.platform && /iPad|iPhone|iPod/.test(navigator.platform);
	$body.on('click', 'a.tsml-directions', function(e) {
		e.preventDefault();
		var directions =
			(iOS ? 'maps://?' : 'https://maps.google.com/?') +
			$.param({
				daddr: $(this).attr('data-latitude') + ',' + $(this).attr('data-longitude'),
				saddr: 'Current Location',
				q: $(this).attr('data-location')
			});
		window.open(directions);
	});

	//expand region select
	$('.panel-expandable').on('click', '.panel-heading', function(e) {
		$(this)
			.closest('.panel-expandable')
			.toggleClass('expanded');
		if (tsml.debug) console.log('.panel-expandable toggling');
	});

	//single meeting page feedback form
	$('#meeting #feedback').validate({
		onfocusout: false,
		onkeyup: function(element) {},
		highlight: function(element, errorClass, validClass) {
			$(element)
				.parent()
				.addClass('has-error');
		},
		unhighlight: function(element, errorClass, validClass) {
			$(element)
				.parent()
				.removeClass('has-error');
		},
		errorPlacement: function(error, element) {
			return; //don't show message on page, simply highlight
		},
		submitHandler: function(form) {
			var $form = $(form),
				$feedback = $form.closest('#feedback');
			if (!$form.hasClass('running'));
			$.post(tsml.ajaxurl, $form.serialize(), function(data) {
				$form.removeClass('running');
				$feedback.find('.list-group').html('<li class="list-group-item has-info">' + data + '</li>');
			}).fail(function(response) {
				$form.removeClass('running');
				$feedback.find('.list-group').html('<li class="list-group-item has-error">' + tsml.strings.email_not_sent + '</li>');
			});
			$form.addClass('running');
			return false;
		}
	});

	//table sorting
	$('#meetings table thead').on('click', 'th', function() {
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
		.on('submit', '#search', function() {
			//capture submit event
			trackAnalytics('search', $search_field.val());
			doSearch();
			return false;
		})
		.on('click', 'div.expand', function(e) {
			//expand or contract regions submenu
			e.preventDefault();
			e.stopPropagation();
			$(this)
				.next('ul.children')
				.toggleClass('expanded');
			$(this).toggleClass('expanded');
		})
		.on('click', '.dropdown-menu a', function(e) {
			//these are live hrefs now
			e.preventDefault();

			//dropdown menu click
			var param = $(this)
				.closest('div')
				.attr('id');

			if (param == 'mode') {
				if (tsml.debug) console.log('dropdown click search mode');

				//only one search mode
				$('#mode li').removeClass('active');

				//remove meeting results
				$('#meetings').addClass('empty');

				//change icon & enable or disable
				if ($(this).attr('data-id') == 'search') {
					$search_field.prop('disabled', false);
					$('#search button i')
						.removeClass()
						.addClass('glyphicon glyphicon-search');
				} else if ($(this).attr('data-id') == 'location') {
					$search_field.prop('disabled', false);
					$('#search button i')
						.removeClass()
						.addClass('glyphicon glyphicon-map-marker');
					setAlert('loc_thinking');
				} else if ($(this).attr('data-id') == 'me') {
					$search_field.prop('disabled', true);
					$('#search button i')
						.removeClass()
						.addClass('glyphicon glyphicon-user');
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
					var mode = $(this)
						.parent()
						.hasClass('region')
						? 'district'
						: 'region';
					$(this)
						.closest('#meetings')
						.attr('tax-mode', mode);
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

			$(this)
				.parent()
				.toggleClass('active');

			//wait to set label on type until we have a complete count
			if (param == 'type') {
				if ($('#type li.active a[data-id]').length) {
					if (tsml.debug) console.log('dropdown click ' + $('#type li.active a[data-id]').length + ' types selected');
					var types = [];
					$('#type li.active a[data-id]').each(function() {
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

			//show/hide upcoming menu option
			toggleUpcoming();

			doSearch();
		});

	//toggle between list and map
	$('#meetings #action .toggle-view').click(function(e) {
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
	$(window).resize(function(e) {
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
		//types can be multiple
		var types = [];
		$('#type li.active a').each(function() {
			if ($(this).attr('data-id')) {
				types.push($(this).attr('data-id'));
			}
		});

		//prepare query for ajax
		var controls = {
			action: 'meetings',
			query: $('#meetings #search input[name=query]')
				.val()
				.trim(),
			mode: $('#search li.active a').attr('data-id'),
			region: $('#region li.region.active a').attr('data-id'),
			district: $('#region li.district.active a').attr('data-id'),
			day: $('#day li.active a').attr('data-id'),
			time: $('#time li.active a').attr('data-id'),
			type: types.length ? types.join(',') : undefined,
			distance: $('#distance li.active a').attr('data-id'),
			view: $('#meetings .toggle-view.active').attr('data-id')
		};

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
				$('#search button i')
					.removeClass()
					.addClass('glyphicon glyphicon-refresh spinning');

				//geocode the address
				$.getJSON(
					tsml.ajaxurl,
					{
						action: 'tsml_geocode',
						address: controls.query,
						nonce: tsml.nonce
					},
					function(geocoded) {
						if (tsml.debug) console.log('doSearch() location geocoded', geocoded);
						$('#search button i')
							.removeClass()
							.addClass('glyphicon glyphicon-map-marker');
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
			$('#search button i')
				.removeClass()
				.addClass('glyphicon glyphicon-refresh spinning');

			if (navigator.geolocation) {
				navigator.geolocation.getCurrentPosition(
					function(pos) {
						$('#search button i')
							.removeClass()
							.addClass('glyphicon glyphicon-user');
						controls.latitude = pos.coords.latitude;
						controls.longitude = pos.coords.longitude;
						searchLocation = {
							latitude: controls.latitude,
							longitude: controls.longitude
						};
						getMeetings(controls);
					},
					function() {
						//browser supports but can't get geolocation
						if (tsml.debug) console.log('doSearch() didnt get location');
						$('#search button i')
							.removeClass()
							.addClass('glyphicon glyphicon-user'); //todo switch to location
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
				$('#search button i')
					.removeClass()
					.addClass('glyphicon glyphicon-user'); //todo switch to location
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
			function(response) {
				if (tsml.debug) console.log('getMeetings() received', response);

				if (typeof response != 'object' || response == null) {
					//there was a problem with the data source
					$('#meetings').addClass('empty');
					setAlert('data_error');
				} else if (!response.length) {
					//if keyword and no results, clear other parameters and search again
					if (
						controls.query &&
						(typeof controls.day !== 'undefined' ||
							typeof controls.region !== 'undefined' ||
							typeof controls.time !== 'undefined' ||
							typeof controls.type !== 'undefined')
					) {
						$('#day li')
							.removeClass('active')
							.first()
							.addClass('active');
						$('#time li')
							.removeClass('active')
							.first()
							.addClass('active');
						$('#region li')
							.removeClass('active')
							.first()
							.addClass('active');
						$('#type li')
							.removeClass('active')
							.first()
							.addClass('active');

						//set selected text
						$('#day span.selected').html($('#day li:first-child a').html());
						$('#time span.selected').html($('#time li:first-child a').html());
						$('#region span.selected').html($('#region li:first-child a').html());
						$('#type span.selected').html($('#type li:first-child a').html());

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
					$.each(response, function(index, obj) {
						//types could be undefined
						if (!obj.types) obj.types = [];
						//add type 'flags'
						if (typeof tsml.flags == 'object') {
							// True if the meeting is temporarily closed, but online option available
							var meetingIsOnlineAndTC = obj.types.indexOf('TC') !== -1 && obj.types.indexOf('ONL') !== -1;
							for (var i = 0; i < tsml.flags.length; i++) {
								var flagIsTempClosed = tsml.flags[i] === 'TC';
								// True if the type for the meeting obj matches one of the predetermined flags being looped
								var typeIsFlagged = obj.types.indexOf(tsml.flags[i]) !== -1;
								//  Add flag, except TC when meeting is also online
								if (typeIsFlagged && !(meetingIsOnlineAndTC && flagIsTempClosed)) {
									obj.name += ' <small>' + tsml.types[tsml.flags[i]] + '</small>';
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
										'"><span>' +
										(typeof controls.day !== 'undefined' || typeof obj.day === 'undefined'
											? obj.time_formatted
											: tsml.days[obj.day] + '</span><span>' + obj.time_formatted) +
										'</span></td>';
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
										formatLink(obj.url, obj.name, 'post_type') +
										'</td>';
									break;

								case 'location':
									row += '<td class="location" data-sort="' + sanitizeDataSort(obj.location) + '-' + sort_time + '">' + obj.location + '</td>';
									break;

								case 'address':
									row +=
										'<td class="address" data-sort="' +
										sanitizeDataSort(obj.formatted_address) +
										'-' +
										sort_time +
										'">' +
										formatAddress(obj.formatted_address, tsml.street_only) +
										'</td>';
									break;

								case 'region':
									row +=
										'<td class="region" data-sort="' +
										sanitizeDataSort(obj.sub_region || obj.region || '') +
										'-' +
										sort_time +
										'">' +
										(obj.sub_region || obj.region || '') +
										'</td>';
									break;

								case 'district':
									row +=
										'<td class="district" data-sort="' +
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
						$('#tsml td')
							.not('.time')
							.mark(controls.query);
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

	//slugify a string, like WordPress's sanitize_title()
	function sanitizeTitle(str) {
		if (str == null) return '';

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
	
		// For efficiency, create all of the regular expressions only once
		if (typeof tsml_sanitize_data == 'undefined') {
			tsml_sanitize_data = [
				// Strip HTML tags
				[/<[^>]+>/ig, ''],
				// Strip other unwanted chars
				[/[&'"<>]+/g, ''],
				// Replace any slashes, periods, spaces or dashes with a dash (UNICODE aware)
				[new RegExp(b64DecodeUnicode('W1wvXC4gwqDhmoDigIAt4oCK4oCv4oGf44CAXC3Wita+4ZCA4aCG4oCQLeKAleK4l+K4muK4uuK4u+K5gOOAnOOAsOOCoO+4se+4su+5mO+5o++8jV0='), 'g'), '-'],
				// Strip any non-alphanumeric chars (UNICODE aware)
				[new RegExp(b64DecodeUnicode('W15BLVphLXrCqsK1wrrDgC3DlsOYLcO2w7gty4HLhi3LkcugLcuky6zLrs2wLc20zbbNt826Lc29zb/Ohs6ILc6KzozOji3Ooc6jLc+1z7ct0oHSii3Ur9SxLdWW1ZnVoC3WiNeQLdeq168t17LYoC3Zitmu2a/ZsS3bk9uV26Xbptuu26/bui3bvNu/3JDcki3cr92NLd6l3rHfii3fqt+037XfuuCggC3goJXgoJrgoKTgoKjgoYAt4KGY4KGgLeChquCioC3gorTgorYt4KK94KSELeCkueCkveClkOClmC3gpaHgpbEt4KaA4KaFLeCmjOCmj+CmkOCmky3gpqjgpqot4Kaw4Kay4Ka2LeCmueCmveCnjuCnnOCnneCnny3gp6Hgp7Dgp7Hgp7zgqIUt4KiK4KiP4KiQ4KiTLeCoqOCoqi3gqLDgqLLgqLPgqLXgqLbgqLjgqLngqZkt4Kmc4Kme4KmyLeCptOCqhS3gqo3gqo8t4KqR4KqTLeCqqOCqqi3gqrDgqrLgqrPgqrUt4Kq54Kq94KuQ4Kug4Kuh4Ku54KyFLeCsjOCsj+CskOCsky3grKjgrKot4Kyw4Kyy4Kyz4Ky1LeCsueCsveCtnOCtneCtny3graHgrbHgroPgroUt4K6K4K6OLeCukOCuki3grpXgrpngrprgrpzgrp7grp/grqPgrqTgrqgt4K6q4K6uLeCuueCvkOCwhS3gsIzgsI4t4LCQ4LCSLeCwqOCwqi3gsLngsL3gsZgt4LGa4LGg4LGh4LKA4LKFLeCyjOCyji3gspDgspIt4LKo4LKqLeCys+CytS3gsrngsr3gs57gs6Dgs6Hgs7Hgs7LgtIUt4LSM4LSOLeC0kOC0ki3gtLrgtL3gtY7gtZQt4LWW4LWfLeC1oeC1ui3gtb/gtoUt4LaW4LaaLeC2seC2sy3gtrvgtr3gt4At4LeG4LiBLeC4sOC4suC4s+C5gC3guYbguoHguoLguoTguoYt4LqK4LqMLeC6o+C6peC6py3gurDgurLgurPgur3gu4At4LuE4LuG4LucLeC7n+C8gOC9gC3gvYfgvYkt4L2s4L6ILeC+jOGAgC3hgKrhgL/hgZAt4YGV4YGaLeGBneGBoeGBpeGBpuGBri3hgbDhgbUt4YKB4YKO4YKgLeGDheGDh+GDjeGDkC3hg7rhg7wt4YmI4YmKLeGJjeGJkC3hiZbhiZjhiZot4Ymd4YmgLeGKiOGKii3hio3hipAt4Yqw4YqyLeGKteGKuC3hir7hi4Dhi4It4YuF4YuILeGLluGLmC3hjJDhjJIt4YyV4YyYLeGNmuGOgC3hjo/hjqAt4Y+14Y+4LeGPveGQgS3hmazhma8t4Zm/4ZqBLeGamuGaoC3hm6rhm7Et4Zu44ZyALeGcjOGcji3hnJHhnKAt4Zyx4Z2ALeGdkeGdoC3hnazhna4t4Z2w4Z6ALeGes+Gfl+GfnOGgoC3hobjhooAt4aKE4aKHLeGiqOGiquGisC3ho7XhpIAt4aSe4aWQLeGlreGlsC3hpbThpoAt4aar4aawLeGnieGogC3hqJbhqKAt4amU4aqn4ayFLeGss+GthS3hrYvhroMt4a6g4a6u4a6v4a66LeGvpeGwgC3hsKPhsY0t4bGP4bGaLeGxveGygC3hsojhspAt4bK64bK9LeGyv+GzqS3hs6zhs64t4bOz4bO14bO24bO64bSALeG2v+G4gC3hvJXhvJgt4byd4bygLeG9heG9iC3hvY3hvZAt4b2X4b2Z4b2b4b2d4b2fLeG9veG+gC3hvrThvrYt4b684b6+4b+CLeG/hOG/hi3hv4zhv5At4b+T4b+WLeG/m+G/oC3hv6zhv7It4b+04b+2LeG/vOKBseKBv+KCkC3igpzihILihIfihIot4oST4oSV4oSZLeKEneKEpOKEpuKEqOKEqi3ihK3ihK8t4oS54oS8LeKEv+KFhS3ihYnihY7ihoPihoTisIAt4rCu4rCwLeKxnuKxoC3is6Tis6st4rOu4rOy4rOz4rSALeK0peK0p+K0reK0sC3itafita/itoAt4raW4ragLeK2puK2qC3itq7itrAt4ra24ra4LeK2vuK3gC3it4bit4gt4reO4reQLeK3luK3mC3it57iuK/jgIXjgIbjgLEt44C144C744C844GBLeOCluOCnS3jgp/jgqEt44O644O8LeODv+OEhS3jhK/jhLEt44aO44agLeOGuuOHsC3jh7/jkIAt5La15LiALem/r+qAgC3qkozqk5At6pO96pSALeqYjOqYkC3qmJ/qmKrqmKvqmYAt6pmu6pm/LeqaneqaoC3qm6XqnJct6pyf6pyiLeqeiOqeiy3qnr/qn4It6p+G6p+3Leqggeqggy3qoIXqoIct6qCK6qCMLeqgouqhgC3qobPqooIt6qKz6qOyLeqjt+qju+qjveqjvuqkii3qpKXqpLAt6qWG6qWgLeqlvOqmhC3qprLqp4/qp6At6qek6qemLeqnr+qnui3qp77qqIAt6qio6qmALeqpguqphC3qqYvqqaAt6qm26qm66qm+Leqqr+qqseqqteqqtuqquS3qqr3qq4Dqq4Lqq5st6qud6qugLeqrquqrsi3qq7TqrIEt6qyG6qyJLeqsjuqskS3qrJbqrKAt6qym6qyoLeqsruqssC3qrZrqrZwt6q2n6q2wLeqvouqwgC3tnqPtnrAt7Z+G7Z+LLe2fu++kgC3vqa3vqbAt76uZ76yALe+shu+sky3vrJfvrJ3vrJ8t76yo76yqLe+stu+suC3vrLzvrL7vrYDvrYHvrYPvrYTvrYYt766x76+TLe+0ve+1kC3vto/vtpIt77eH77ewLe+3u++5sC3vubTvubYt77u877yhLe+8uu+9gS3vvZrvvaYt776+77+CLe+/h++/ii3vv4/vv5It77+X77+aLe+/nDAtOcKywrPCucK8LcK+2aAt2anbsC3bud+ALd+J4KWmLeClr+Cnpi3gp6/gp7Qt4Ke54KmmLeCpr+Crpi3gq6/graYt4K2v4K2yLeCtt+Cvpi3gr7LgsaYt4LGv4LG4LeCxvuCzpi3gs6/gtZgt4LWe4LWmLeC1uOC3pi3gt6/guZAt4LmZ4LuQLeC7meC8oC3gvLPhgYAt4YGJ4YKQLeGCmeGNqS3hjbzhm64t4Zuw4Z+gLeGfqeGfsC3hn7nhoJAt4aCZ4aWGLeGlj+GnkC3hp5rhqoAt4aqJ4aqQLeGqmeGtkC3hrZnhrrAt4a654bGALeGxieGxkC3hsZnigbDigbQt4oG54oKALeKCieKFkC3ihoLihoUt4oaJ4pGgLeKSm+KTqi3ik7/inbYt4p6T4rO944CH44ChLeOAqeOAuC3jgLrjhpIt44aV44igLeOIqeOJiC3jiY/jiZEt44mf44qALeOKieOKsS3jir/qmKAt6pip6pumLeqbr+qgsC3qoLXqo5At6qOZ6qSALeqkieqnkC3qp5nqp7At6qe56qmQLeqpmeqvsC3qr7nvvJAt77yZzIAtza/Sgy3SidaRLda91r/XgdeC14TXhdeH2JAt2JrZiy3Zn9mw25Yt25zbny3bpNun26jbqi3brdyR3LAt3Yrepi3esN+rLd+z373goJYt4KCZ4KCbLeCgo+CgpS3goKfgoKkt4KCt4KGZLeChm+Cjky3go6Hgo6Mt4KSD4KS6LeCkvOCkvi3gpY/gpZEt4KWX4KWi4KWj4KaBLeCmg+CmvOCmvi3gp4Tgp4fgp4jgp4st4KeN4KeX4Kei4Kej4Ke+4KiBLeCog+CovOCovi3gqYLgqYfgqYjgqYst4KmN4KmR4Kmw4Kmx4Km14KqBLeCqg+CqvOCqvi3gq4Xgq4ct4KuJ4KuLLeCrjeCrouCro+Crui3gq7/grIEt4KyD4Ky84Ky+LeCthOCth+CtiOCtiy3grY3grZbgrZfgraLgraPgroLgrr4t4K+C4K+GLeCviOCvii3gr43gr5fgsIAt4LCE4LC+LeCxhOCxhi3gsYjgsYot4LGN4LGV4LGW4LGi4LGj4LKBLeCyg+CyvOCyvi3gs4Tgs4Yt4LOI4LOKLeCzjeCzleCzluCzouCzo+C0gC3gtIPgtLvgtLzgtL4t4LWE4LWGLeC1iOC1ii3gtY3gtZfgtaLgtaPgtoLgtoPgt4rgt48t4LeU4LeW4LeYLeC3n+C3suC3s+C4seC4tC3guLrguYct4LmO4Lqx4Lq0LeC6vOC7iC3gu43gvJjgvJngvLXgvLfgvLngvL7gvL/gvbEt4L6E4L6G4L6H4L6NLeC+l+C+mS3gvrzgv4bhgKst4YC+4YGWLeGBmeGBni3hgaDhgaIt4YGk4YGnLeGBreGBsS3hgbThgoIt4YKN4YKP4YKaLeGCneGNnS3hjZ/hnJIt4ZyU4ZyyLeGctOGdkuGdk+GdsuGds+GetC3hn5Phn53hoIst4aCN4aKF4aKG4aKp4aSgLeGkq+GksC3hpLvhqJct4aib4amVLeGpnuGpoC3hqbzhqb/hqrAt4aq+4ayALeGshOGstC3hrYThrast4a2z4a6ALeGuguGuoS3hrq3hr6Yt4a+z4bCkLeGwt+GzkC3hs5Lhs5Qt4bOo4bOt4bO04bO3LeGzueG3gC3ht7nht7st4be/4oOQLeKDsOKzry3is7Hitb/it6At4re/44CqLeOAr+OCmeOCmuqZry3qmbLqmbQt6pm96pqe6pqf6puw6pux6qCC6qCG6qCL6qCjLeqgp+qigOqigeqitC3qo4Xqo6At6qOx6qO/6qSmLeqkreqlhy3qpZPqpoAt6qaD6qazLeqngOqnpeqoqS3qqLbqqYPqqYzqqY3qqbst6qm96qqw6qqyLeqqtOqqt+qquOqqvuqqv+qrgeqrqy3qq6/qq7Xqq7bqr6Mt6q+q6q+s6q+t76ye77iALe+4j++4oC3vuK8tXSs='), 'g'), ''],
				// Replace any runs of dashes with a single dash
				[/\-+/g, '-'],
				// Strip leading/trailing dash if they exist
				[/^\-|\-$/g, '']
			]
		}
	
		// Use the textarea element to decode HTML entities.  This is important for languages that use them.  For example, Polish uses &oacute;.
		var str = document.createElement('textarea'); a.innerHTML = str; str = a.value;
		
		// Apply all regular expressions
		a = window.tsml_sanitize_data;
		for (var i=0;i<a.length;i++) {
			str = str.replace(a[i][0], a[i][1]);
		}
		
		// Lower case the string
		return str.toLowerCase();
	}	

	//set or clear the alert message
	function setAlert(message_key) {
		if (typeof message_key == 'undefined') {
			$('#alert')
				.html('')
				.addClass('hidden');
		} else {
			$('#alert')
				.html(tsml.strings[message_key])
				.removeClass('hidden');
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

		// CC - BOM - Do locale-aware sort, falling back on English if needed
		/*
		store.sort(function(x, y) {
			if (x[0] > y[0]) return order == 'asc' ? 1 : -1;
			if (x[0] < y[0]) return order == 'asc' ? -1 : 1;
			return 0;
		});
		*/
		var locales = [document.documentElement.lang];
		if (!(locales[0].startsWith != 'en')) locales.push('en');
		var collator = new Intl.Collator(locales, {sensitivity: 'variant'});
		store.sort(function(x, y) {
			return collator.compare(x[0], y[0]) * (order == 'asc' ? 1 : -1);
		});
		// CC - EOM

		for (var i = 0, len = store.length; i < len; i++) {
			tbody.appendChild(store[i][1]);
		}
	}

	//if day is today, show 'upcoming' time option, otherwise hide it
	function toggleUpcoming() {
		var current_day = new Date().getDay();
		var selected_day = $('#day li.active a')
			.first()
			.attr('data-id');
		var selected_time = $('#time li.active a')
			.first()
			.attr('data-id');
		if (current_day != selected_day) {
			$('#time li.upcoming').addClass('hidden');
			if (selected_time == 'upcoming') {
				$('#time li.active').removeClass('active');
				$('#time li')
					.first()
					.addClass('active');
				$('#time span.selected').html(
					$('#time li a')
						.first()
						.text()
				);
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

	//disable the typeahead (if you switched to a different search mode)
	function typeaheadDisable() {
		if (!typeaheadEnabled) return;
		typeaheadEnabled = false;
		$('#meetings #search input[name="query"]').typeahead('destroy');
	}

	//enable the typeahead (if you switch back to search)
	function typeaheadEnable() {
		if (typeaheadEnabled) return;
		if (tsml.debug) console.log('typeaheadEnable()');
		typeaheadEnabled = true;
		$('#meetings #search input[name="query"]')
			.typeahead(
				{
					highlight: true
				},
				{
					name: 'tsml_regions',
					display: 'value',
					source: tsml_regions,
					templates: {
						header: '<h3>' + tsml.strings.regions + '</h3>'
					}
				},
				{
					name: 'tsml_groups',
					display: 'value',
					source: tsml_groups,
					templates: {
						header: '<h3>' + tsml.strings.groups + '</h3>'
					}
				},
				{
					name: 'tsml_locations',
					display: 'value',
					source: tsml_locations,
					templates: {
						header: '<h3>' + tsml.strings.locations + '</h3>'
					}
				}
			)
			.on('typeahead:selected', function($e, item) {
				if (item.type == 'region') {
					$('#region li').removeClass('active');
					var active = $('#region li a[data-id="' + item.id + '"]');
					active.parent().addClass('active');
					$('#region span.selected').html(active.html());
					$('#search input[name="query"]')
						.val('')
						.typeahead('val', '');
					trackAnalytics('region', active.text());
					doSearch();
				} else if (item.type == 'location') {
					trackAnalytics('location', item.value);
					location.href = item.url;
				} else if (item.type == 'group') {
					trackAnalytics('group', item.value);
					doSearch();
				}
			});
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
