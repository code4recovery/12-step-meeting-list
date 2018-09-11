/*
	Javascript for Meetings Archive, Single Meeting, and Single Location pages
	a) procedural logic
	b) event handlers
	c) functions
*/

jQuery(function($){

	//a) procedural logic
	var $body = $('body');
	var typeaheadEnabled = false;
	
	if (typeof tsml_map !== 'object')  { //main meetings page

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
		if ($search_field.size() && $search_field.val().length) {
			$('#tsml td').not('.time').mark($search_field.val());
		}
		
		var mode = $('#search li.active a').attr('data-id');
		if (mode == 'search') {
			typeaheadEnable();
			//list/map page (only create if we're on the map view)
			if ($('a.toggle-view[data-id="map"]').hasClass('active')) {
				createMap(true, locations);
			}
		} else if ((mode == 'location') && $search_field.val().length) {
			doSearch();
		} else if (mode == 'me') {
			doSearch();
		}

	} else { //meeting or location detail page
		
		var location_link = (typeof tsml_map.location_url === 'undefined') ? tsml_map.location : formatLink(tsml_map.location_url, tsml_map.location, 'tsml_meeting');
		locations = {};
		locations[tsml_map.location_id] = {
			latitude: tsml_map.latitude,
			longitude: tsml_map.longitude,
			formatted_address: tsml_map.formatted_address,
			name: tsml_map.location,
			meetings: [],
			directions: tsml_map.directions,
			directions_url: tsml_map.directions_url,
			url: tsml_map.location_url,
		};
		createMap(false, locations);
	}
		
	//b) jQuery event handlers

	//handle directions links; send to Apple Maps (iOS), or Google Maps (everything else)
	var iOS = !!navigator.platform && /iPad|iPhone|iPod/.test(navigator.platform);
	$body.on('click', 'a.tsml-directions', function(e){
		e.preventDefault();
		var directions = (iOS ? 'maps://?' : 'https://maps.google.com/?') + $.param({
			daddr: $(this).attr('data-latitude') + ',' + $(this).attr('data-longitude'),
			saddr: 'Current Location',
			q: $(this).attr('data-location')
		});
		window.open(directions);
	});

	//expand region select
	$('.panel-expandable').on('click', '.panel-heading', function(e){
		$(this).closest('.panel-expandable').toggleClass('expanded');
		if (tsml.debug) console.log('expanding region');
	})
	
	//single meeting page feedback form
	$('#meeting #feedback').validate({
		onfocusout: false,
		onkeyup: function(element) { },
		highlight: function(element, errorClass, validClass) {
			$(element).parent().addClass('has-error');
		},
		unhighlight: function(element, errorClass, validClass) {
			$(element).parent().removeClass('has-error');
		},
		errorPlacement: function(error, element) {
			return; //don't show message on page, simply highlight
		}, 
		submitHandler: function(form){
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
	$('#meetings table thead').on('click', 'th', function(){
		var sort = $(this).attr('class');
		var order;

		//update header
		if ($(this).attr('data-sort')) {
			order = ($(this).attr('data-sort') == 'asc') ? 'desc' : 'asc';
		} else {
			order = 'asc';
		}
		$('#meetings table thead th').removeAttr('data-sort');
		$('#meetings table thead th.' + sort).attr('data-sort', order);
		sortMeetings();
	});
	
	//controls changes
	$('#meetings .controls').on('submit', '#search', function(){
		//capture submit event
		trackAnalytics('search', $search_field.val())
		doSearch();
		return false;
	}).on('click', 'div.expand', function(e){
		//expand or contract regions submenu
		e.preventDefault();
		e.stopPropagation();
		$(this).next('ul.children').toggleClass('expanded');
		$(this).toggleClass('expanded');
	}).on('click', '.dropdown-menu a', function(e){
		
		//these are live hrefs now
		e.preventDefault();

		if (tsml.debug) console.log('dropdown click');

		//dropdown menu click
		var param = $(this).closest('div').attr('id');
		
		if (param == 'mode') {
			
			if (tsml.debug) console.log('search mode');

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
			if (tsml.debug) console.log('distance');
			$('#distance li').removeClass('active');
			$('#distance span.selected').html($(this).html());
			trackAnalytics('distance', $(this).text());
		} else if (param == 'region') {
			//switch between region and district mode
			if ($(this).hasClass('switch')) {
				if (tsml.debug) console.log('switching between region and district');
				var mode = $(this).parent().hasClass('region') ? 'district' : 'region';
				$(this).closest('#meetings').attr('tax-mode', mode);
				e.stopPropagation();
				return;
			}
			
			//region only one
			if (tsml.debug) console.log('region or district');
			$('#region li').removeClass('active');
			$('#region span.selected').html($(this).html());
			trackAnalytics('region', $(this).text());
			
		} else if (param == 'day') {
			//day only one selected
			if (tsml.debug) console.log('day');
			$('#day li').removeClass('active');
			$('#day span.selected').html($(this).html());
			trackAnalytics('day', $(this).text());
		} else if (param == 'time') {
			//time only one
			if (tsml.debug) console.log('time');
			$('#time li').removeClass('active');
			$('#time span.selected').html($(this).html());
			trackAnalytics('time', $(this).text());
		} else if (param == 'type') {
			//type can be multiple
			if (tsml.debug) console.log('type');
			if (!e.metaKey) $('#type li').removeClass('active');
			trackAnalytics('type', $(this).text());
		}

		$(this).parent().toggleClass('active');

		//wait to set label on type until we have a complete count
		if (param == 'type') {
			if ($('#type li.active a[data-id]').size()) {
				if (tsml.debug) console.log($('#type li.active a[data-id]').size() + ' types selected');
				var types = [];
				$('#type li.active a[data-id]').each(function(){
					types.push($(this).text());
				});
				$('#type span.selected').html(types.join(' + '));
			} else {
				if (tsml.debug) console.log('no types selected');
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
	$('#meetings #action .toggle-view').click(function(e){
		
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
			window.history.pushState({path:url}, '', url);
		}
		
	});

	//resize fullscreen on resize
	$(window).resize(function(e){
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
		$('#type li.active a').each(function(){
			if ($(this).attr('data-id')) {
				types.push($(this).attr('data-id'));
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
		}

		//reset search location
		searchLocation = null;

		if (tsml.debug) console.log('doing search');
		if (tsml.debug) console.log(controls);
		
		//get current query string for history and appending to links
		var query_string = {};
		query_string['tsml-day'] = controls.day ? controls.day : 'any';
		if ((controls.mode != 'search') && (controls.distance != tsml.defaults.distance)) {
			query_string['tsml-distance'] = controls.distance;
		}
		if (controls.mode && (controls.mode != tsml.defaults.mode)) query_string['tsml-mode'] = controls.mode;
		if (controls.query && (controls.query != tsml.defaults.query)) query_string['tsml-query'] = controls.query;
		if (controls.mode == 'search') {
			if (controls.region != tsml.defaults.region) {
				query_string['tsml-region'] = controls.region;
			} else if (controls.district != tsml.defaults.district) {
				query_string['tsml-district'] = controls.district;
			}
		}
		if (controls.time && (controls.time != tsml.defaults.time)) query_string['tsml-time'] = controls.time;
		if (controls.type && (controls.type != tsml.defaults.type)) query_string['tsml-type'] = controls.type;
		if (controls.view != tsml.defaults.view) query_string['tsml-view'] = controls.view;
		query_string = $.param(query_string);
		
		//save the query in the query string, if the browser is up to it
		if (history.pushState) {
			var url = window.location.protocol + '//' + window.location.host + window.location.pathname;
			if (query_string.length) url = url + '?' + query_string;
			if (location.search.indexOf('post_type=tsml_meeting') > -1) {
				url = url + ((url.indexOf('?') > -1) ? '&' : '?') + 'post_type=tsml_meeting';
			}
			window.history.pushState({path:url}, '', url);
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
				$.getJSON(tsml.ajaxurl, { 
					action: 'tsml_geocode',
					address: controls.query, 
					nonce: tsml.nonce,
				}, function(geocoded) {
					if (tsml.debug) console.log('geocoded', geocoded);
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
							longitude: controls.longitude,
						};
						getMeetings(controls);
					}
				});
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
				navigator.geolocation.getCurrentPosition(function(pos) {
					$('#search button i').removeClass().addClass('glyphicon glyphicon-user');
					controls.latitude = pos.coords.latitude;
					controls.longitude = pos.coords.longitude;
					searchLocation = {
						latitude: controls.latitude,
						longitude: controls.longitude,
					};
					getMeetings(controls);
				}, function() {
					//browser supports but can't get geolocation
					$('#search button i').removeClass().addClass('glyphicon glyphicon-user'); //todo switch to location
					removeSearchMarker();
					setAlert('geo_error');
				}, {
					enableHighAccuracy: true,
					timeout: 10000, //10 seconds
					maximumAge: 600000, //10 minutes
				});
			} else {
				//browser doesn't support geolocation
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
				
		$.post(tsml.ajaxurl, controls, function(response){

			if (typeof response != 'object') {
								
				//there was a problem with the data source
				$('#meetings').addClass('empty');
				setAlert('data_error');				
				
			} else if (!response.length) {
	
				//if keyword and no results, clear other parameters and search again
				if (controls.query && (typeof controls.day !== 'undefined' || typeof controls.region !== 'undefined' || typeof controls.time !== 'undefined' || typeof controls.type !== 'undefined')) {
					$('#day li').removeClass('active').first().addClass('active');
					$('#time li').removeClass('active').first().addClass('active');
					$('#region li').removeClass('active').first().addClass('active');
					$('#type li').removeClass('active').first().addClass('active');
	
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
				$.each(response, function(index, obj){
	
					//add gender designation
					if ($.inArray('M', obj.types) != -1) {
						obj.name += ' <small>' + tsml.strings.men + '</small>';
					} else if ($.inArray('W', obj.types) != -1) {
						obj.name += ' <small>' + tsml.strings.women + '</small>';
					}
	
					//save location info
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
						name : obj.name,
						time : obj.time_formatted,
						day : obj.day,
						notes : obj.notes,
						url : obj.url
					};
	
					var sort_time = obj.day + '-' + (obj.time == '00:00' ? '23:59' : obj.time);
	
					//decode types (for hidden type column)
					for (var i = 0; i < obj.types.length; i++) {
						obj.types[i] = tsml.types[obj.types[i]];
					}
					obj.types.sort();
					obj.types = obj.types.join(', ');
					
					//add new table row
					var string = '<tr';
					if (obj.notes.length) string += ' class="notes"'
					string += '>';
					for (var i = 0; i < tsml.columns.length; i++) {
						switch (tsml.columns[i])	 {
							case 'time':
							string += '<td class="time" data-sort="' + sort_time + '-' + sanitizeTitle(obj.location) + '"><span>' + (controls.day || !obj.day ? obj.time_formatted : tsml.days[obj.day] + '</span><span>' + obj.time_formatted) + '</span></td>';
							break;
							
							case 'distance':
							string += '<td class="distance" data-sort="' + obj.distance + '">' + obj.distance + ' ' + tsml.distance_units + '</td>';
							break;
							
							case 'name':
							string += '<td class="name" data-sort="' + sanitizeTitle(obj.name) + '-' + sort_time + '">' + formatLink(obj.url, obj.name, 'post_type') + '</td>';
							break;
							
							case 'location':
							string += '<td class="location" data-sort="' + sanitizeTitle(obj.location) + '-' + sort_time + '">' + obj.location + '</td>';
							break;
							
							case 'address':
							string += '<td class="address" data-sort="' + sanitizeTitle(obj.formatted_address) + '-' + sort_time + '">' + formatAddress(obj.formatted_address, tsml.street_only) + '</td>';
							break;
							
							case 'region':
							string += '<td class="region" data-sort="' + sanitizeTitle((obj.sub_region || obj.region || '')) + '-' + sort_time + '">' + (obj.sub_region || obj.region || '') + '</td>';
							break;
							
							case 'district':
							string += '<td class="district" data-sort="' + sanitizeTitle((obj.sub_district || obj.district || '')) + '-' + sort_time + '">' + (obj.sub_district || obj.district || '') + '</td>';
							break;
							
							case 'types':
							string += '<td class="types" data-sort="' + sanitizeTitle(obj.types) + '-' + sort_time + '">' + obj.types + '</td>';
							break;
						}
					}
					tbody.append(string + '</tr>');
				});
				
				sortMeetings();
				
				if ((controls.mode == 'search') && controls.query) $('#tsml td').not('.time').mark(controls.query);
	
				if (controls.view == 'map') {
					createMap(true, locations, searchLocation);
				}

			}
		}, 'json');	
	}
	
	//slugify a string, like WordPress's sanitize_title()
	function sanitizeTitle(str) {
		if (str == null) return '';

		str = str.replace(/^\s+|\s+$/g, ''); // trim
		str = str.toLowerCase();
		
		// remove accents, swap ñ for n, etc
		var from = "àáäâèéëêìíïîòóöôùúüûñç·/_,:;";
		var to = "aaaaeeeeiiiioooouuuunc------";
		
		for (var i=0, l=from.length ; i<l ; i++) {
			str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
		}
		
		str = str.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
			.replace(/\s+/g, '-') // collapse whitespace and replace by -
			.replace(/-+/g, '-'); // collapse dashes
		
		return str;
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
			store.push([row.cells[sort_index].getAttribute('data-sort'), row]);
		}
		store.sort(function(x,y){
			if (x[0] > y[0]) return (order == 'asc') ? 1 : -1;
			if (x[0] < y[0]) return (order == 'asc') ? -1 : 1;
			return 0;
		});
		for (var i = 0, len = store.length; i < len; i++){
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
			if (tsml.debug) console.log('sending ' + action + ': ' + label + ' to google analytics');
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
		if (tsml.debug) console.log('enabling typeahead');
		typeaheadEnabled = true;
		$('#meetings #search input[name="query"]').typeahead({
			highlight: true
		}, {
			name: 'tsml_regions',
			display: 'value',
			source: tsml_regions,
			templates: {
				header: '<h3>' + tsml.strings.regions + '</h3>',
			}
		}, {
			name: 'tsml_groups',
			display: 'value',
			source: tsml_groups,
			templates: {
				header: '<h3>' + tsml.strings.groups + '</h3>',
			}
		}, {
			name: 'tsml_locations',
			display: 'value',
			source: tsml_locations,
			templates: {
				header: '<h3>' + tsml.strings.locations + '</h3>',
			}
		}).on('typeahead:selected', function($e, item){
			if (item.type == 'region') {
				$('#region li').removeClass('active');
				var active = $('#region li a[data-id="' + item.id + '"]');
				active.parent().addClass('active');
				$('#region span.selected').html(active.html());
				$('#search input[name="query"]').val('').typeahead('val', '');
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
		var re = new RegExp("([?&])" + key + "=.*?(&|#|$)(.*)", "gi"), hash;
	
		if (re.test(url)) {
			if (typeof value !== 'undefined' && value !== null) {
				return url.replace(re, '$1' + key + "=" + value + '$2$3');
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