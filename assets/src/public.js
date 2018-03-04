/*
	Javascript for Meetings Archive, Single Meeting, and Single Location pages
	a) procedural logic
	b) event handlers
	c) functions
*/

jQuery(function($){

	//a) procedural logic
	
	var infowindow = new google.maps.InfoWindow();
		
	var searchMarker;
	
	var markers = [];
	
	var bounds = new google.maps.LatLngBounds();
	
	var userIcon = {
		path: 'M20.5,0.5 c11.046,0,20,8.656,20,19.333c0,10.677-12.059,21.939-20,38.667c-5.619-14.433-20-27.989-20-38.667C0.5,9.156,9.454,0.5,20.5,0.5z',
		fillColor: '#2c78b3',
		fillOpacity: 1,
		anchor: new google.maps.Point(40,50),
		strokeWeight: 2,
		strokeColor: '#2c52b3',
		scale: .6
	}
	
	var locationIcon = {
		path: 'M20.5,0.5 c11.046,0,20,8.656,20,19.333c0,10.677-12.059,21.939-20,38.667c-5.619-14.433-20-27.989-20-38.667C0.5,9.156,9.454,0.5,20.5,0.5z',
		fillColor: '#f76458',
		fillOpacity: 1,
		anchor: new google.maps.Point(40,50),
		strokeWeight: 2,
		strokeColor: '#b3382c',
		scale: .6
	}
	
	var $body = $('body');
	
	//if ($body.hasClass('post-type-archive-tsml_meeting')) {
	if (typeof tsml_map !== 'object')  { //provided by template pages

		//list/map page
		var map = new google.maps.Map(document.getElementById('map'), {
			disableDefaultUI: true,
			scrollwheel: true,
			streetViewControl: true,
			zoomControl: true,
			fullscreenControl: true,
			mapTypeControlOptions: {
				mapTypeIds: [google.maps.MapTypeId.ROADMAP, 'map_style']
			}
		});

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
			setMapMarkers();
			setMapBounds();
		} else if ((mode == 'location') && $search_field.val().length) {
			doSearch(false);
		} else if (mode == 'me') {
			doSearch(false);
		}

	} else {
		
		//meeting or location detail page
		tsml_map.latitude -= 0;
		tsml_map.longitude -= 0;
		
		var location_link = (typeof tsml_map.location_url === 'undefined') ? tsml_map.location : formatLink(tsml_map.location_url, tsml_map.location, 'tsml_meeting');
		
		//build infowindow content
		var content = '<h3>' + location_link + '</h3>'+
			'<p>' + formatAddress(tsml_map.address) + '</p>'+
			'<p><a class="btn btn-default" href="' + tsml_map.directions_url + '" target="_blank">' + tsml_map.directions + '</a></p>';
											
		var map = new google.maps.Map(document.getElementById('map'), {
			zoom: 15,
			center: new google.maps.LatLng((tsml_map.latitude + .0025), tsml_map.longitude),
			disableDefaultUI: true,
			scrollwheel: false,
			streetViewControl: true,
			zoomControl: true,
			fullscreenControl: true,
			mapTypeControlOptions: {
				mapTypeIds: [google.maps.MapTypeId.ROADMAP, 'map_style']
			}
		});
		
		var marker = setMapMarker(tsml_map.location, tsml_map.latitude, tsml_map.longitude, content);
		
		google.maps.event.trigger(marker, 'click');
		
		//stripe payments
		if (tsml_map.contributions_api_key) {
			var stripe = Stripe(tsml_map.contributions_api_key);
			
			Stripe.applePay.checkAvailability(function(available) {
				if (available) {
					$('#payment').addClass('apple-pay');
				} else {
					var elements = stripe.elements();
					var card = elements.create('card', { 
						style: {
							base: {
								fontSize: '16px',
								lineHeight: '24px'
							}
						}
					});
					card.mount('#card-element');
				}
			});
		}

	}
		
	//b) jQuery event handlers
	
	$('.panel-expandable').on('click', '.panel-heading', function(e){
		$(this).closest('.panel-expandable').toggleClass('expanded');
		console.log('click');
	})
	
	//single meeting page feedback form
	$('#meeting #feedback').validate({
		onfocusout:false,
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
		doSearch(true);
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

		tsmlDebug('dropdown click');

		//dropdown menu click
		var param = $(this).closest('div').attr('id');
		
		if (param == 'mode') {
			
			tsmlDebug('search mode');

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
			tsmlDebug('distance');
			$('#distance li').removeClass('active');
			$('#distance span.selected').html($(this).html());
			trackAnalytics('distance', $(this).text());
		} else if (param == 'region') {
			//switch between region and district mode
			if ($(this).hasClass('switch')) {
				tsmlDebug('switching between region and district');
				var mode = $(this).parent().hasClass('region') ? 'district' : 'region';
				$(this).closest('#meetings').attr('tax-mode', mode);
				e.stopPropagation();
				return;
			}
			
			//region only one
			tsmlDebug('region or district');
			$('#region li').removeClass('active');
			$('#region span.selected').html($(this).html());
			trackAnalytics('region', $(this).text());
			
		} else if (param == 'day') {
			//day only one selected
			tsmlDebug('day');
			$('#day li').removeClass('active');
			$('#day span.selected').html($(this).html());
			trackAnalytics('day', $(this).text());
		} else if (param == 'time') {
			//time only one
			tsmlDebug('time');
			$('#time li').removeClass('active');
			$('#time span.selected').html($(this).html());
			trackAnalytics('time', $(this).text());
		} else if (param == 'type') {
			//type only one
			tsmlDebug('type');
			$('#type li').removeClass('active');
			$('#type span.selected').html($(this).html());
			trackAnalytics('type', $(this).text());
		}

		$(this).parent().toggleClass('active');

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
		
		doSearch(false);
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
		
		//save the query in the query string, if the browser is up to it
		if (history.pushState) {
			if (action == tsml.defaults.view) {
				var url = updateQueryString('tsml-view');
			} else {
				var url = updateQueryString('tsml-view', action);
			}
			window.history.pushState({path:url}, '', url);
		}
		
		//wake up the map if needed
		if (action == 'map' && previous == 'list') {
			google.maps.event.trigger(map, 'resize');
	 		setMapBounds();
	 		
	 		//this is a little crazy, but is needed on iOS, don't know why
	 		setTimeout(function(){
		 		$body.click();
		 	}, 100);
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
	
	//run search (triggered by dropdown toggle or form submit) ('keyword_searching' means the current intent is keyword search)
	function doSearch(keyword_searching) {
	
		//prepare data for ajax
		var data = { 
			action: 'meetings',
			query: $('#meetings #search input[name=query]').val().trim(),
			mode: $('#search li.active a').attr('data-id'),
			region: $('#region li.region.active a').attr('data-id'),
			district: $('#region li.district.active a').attr('data-id'),
			day: $('#day li.active a').attr('data-id'),
			time: $('#time li.active a').attr('data-id'),
			type: $('#type li.active a').attr('data-id'),
			distance: $('#distance li.active a').attr('data-id'),
			view: $('#meetings .toggle-view.active').attr('data-id'),
		}

		tsmlDebug('doing search');
		tsmlDebug(data);
		
		//get current query string for history and appending to links
		var query_string = {};
		query_string['tsml-day'] = data.day ? data.day : 'any';
		if ((data.mode != 'search') && (data.distance != tsml.defaults.distance)) {
			query_string['tsml-distance'] = data.distance;
		}
		if (data.mode && (data.mode != tsml.defaults.mode)) query_string['tsml-mode'] = data.mode;
		if (data.query && (data.query != tsml.defaults.query)) query_string['tsml-query'] = data.query;
		if (data.mode == 'search') {
			if (data.region != tsml.defaults.region) {
				query_string['tsml-region'] = data.region;
			} else if (data.district != tsml.defaults.district) {
				query_string['tsml-district'] = data.district;
			}
		}
		if (data.time && (data.time != tsml.defaults.time)) query_string['tsml-time'] = data.time;
		if (data.type && (data.type != tsml.defaults.type)) query_string['tsml-type'] = data.type;
		if (data.view != tsml.defaults.view) query_string['tsml-view'] = data.view;
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
		$('#meetings').attr('data-mode', data.mode);
		
		if (data.mode == 'search') {
			typeaheadEnable();
			setSearchMarker();
			getMeetings(data, keyword_searching);
		} else if (data.mode == 'location') {
			typeaheadDisable();
	
			if (data.query) {
				
				//start spinner
				$('#search button i').removeClass().addClass('glyphicon glyphicon-refresh spinning');
	
				//geocode the address
				$.getJSON('https://maps.googleapis.com/maps/api/geocode/json', { 
					address: data.query, 
					key: tsml.google_api_key,
					language: tsml.language,
				}, function(geocoded_data) {
					$('#search button i').removeClass().addClass('glyphicon glyphicon-map-marker');
					if (geocoded_data.status == 'OK') {
						$search_field.val(geocoded_data.results[0].formatted_address);
						data.latitude = geocoded_data.results[0].geometry.location.lat;
						data.longitude = geocoded_data.results[0].geometry.location.lng;
						data.query = ''; //don't actually keyword search this
						setSearchMarker(data);
						getMeetings(data, keyword_searching);
					} else {
						//show error message
						setSearchMarker();
						setAlert('loc_error');
					}
				});
			} else {
				setAlert('loc_empty');
			}
		} else if (data.mode == 'me') {
	
			//start spinner
			$('#search button i').removeClass().addClass('glyphicon glyphicon-refresh spinning');
			
			if (navigator.geolocation) {
				navigator.geolocation.getCurrentPosition(function(pos) {
					$('#search button i').removeClass().addClass('glyphicon glyphicon-user');
					data.latitude = pos.coords.latitude;
					data.longitude = pos.coords.longitude;
					setSearchMarker(data);
					getMeetings(data, keyword_searching);
				}, function() {
					//browser supports but can't get geolocation
					$('#search button i').removeClass().addClass('glyphicon glyphicon-user'); //todo switch to location
					setSearchMarker();
					setAlert('geo_error');
				}, {
					enableHighAccuracy: true,
					timeout: 10000, //10 seconds
					maximumAge: 600000, //10 minutes
				});
			} else {
				//browser doesn't support geolocation
				$('#search button i').removeClass().addClass('glyphicon glyphicon-user'); //todo switch to location
				setSearchMarker();
				setAlert('geo_error_browser');
			}
		}
		
	}
	
	//format an address: replace commas with breaks
	function formatAddress(address, street_only) {
		address = address.split(', ');
		if (street_only) return address[0];
		if (address[address.length-1] == 'USA') {
			address.pop(); //don't show USA
			var state_and_zip = address.pop();
			address[address.length-1] += ', ' + state_and_zip;
		}
		return address.join('<br>');
	}
	
	//format a link to a meeting result page, preserving all but the excluded query string keys
	function formatLink(url, text, exclude) {
		if (location.search) {
			var query_pairs = location.search.substr(1).split('&');
			var new_query_pairs = [];
			for (var i = 0; i < query_pairs.length; i++) {
				var query_parts = query_pairs[i].split('=');
				if (query_parts[0] != exclude) new_query_pairs[new_query_pairs.length] = query_parts[0] + '=' + query_parts[1];
			}
			if (new_query_pairs.length) {
				url += ((url.indexOf('?') == -1) ? '?' : '&') + new_query_pairs.join('&');
			}
		}
		return '<a href="' + url + '">' + text + '</a>';
	}
	
	//actually get the meetings from the JSON resource and output them
	function getMeetings(data, keyword_searching) {
		//request new meetings result
		data.distance_units = tsml.distance_units;
		data.nonce = tsml.nonce;
				
		$.post(tsml.ajaxurl, data, function(response){

			if (typeof response != 'object') {
								
				//there was a problem with the data source
				$('#meetings').addClass('empty');
				setAlert('data_error');				
				
			} else if (!response.length) {
	
				//if keyword and no results, clear other parameters and search again
				if (keyword_searching && (typeof data.day !== 'undefined' || typeof data.region !== 'undefined' || typeof data.time !== 'undefined' || typeof data.type !== 'undefined')) {
					$('#day li').removeClass('active').first().addClass('active');
					$('#time li').removeClass('active').first().addClass('active');
					$('#region li').removeClass('active').first().addClass('active');
					$('#type li').removeClass('active').first().addClass('active');
	
					//set selected text
					$('#day span.selected').html($('#day li:first-child a').html());
					$('#time span.selected').html($('#time li:first-child a').html());
					$('#region span.selected').html($('#region li:first-child a').html());
					$('#type span.selected').html($('#type li:first-child a').html());
					return doSearch(true);
				}
	
				$('#meetings').addClass('empty');
				setAlert('no_meetings');
			} else {
				$('#meetings').removeClass('empty');
	
				//refresh map if visible
				if ($('#meetings').attr('data-view') == 'map') {
					google.maps.event.trigger(map, 'resize');
				}
				
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
							string += '<td class="time" data-sort="' + sort_time + '-' + sanitizeTitle(obj.location) + '"><span>' + (data.day || !obj.day ? obj.time_formatted : tsml.days[obj.day] + '</span><span>' + obj.time_formatted) + '</span></td>';
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
				
				if ((data.mode == 'search') && data.query) $('#tsml td').not('.time').mark(data.query);
	
				//remove old markers and reset bounds
				for (var i = 0; i < markers.length; i++) {
					if ((typeof markers[i] == 'object') && markers[i]) {
						markers[i].setMap(null);
					}
				}
				markers = [];
				bounds = new google.maps.LatLngBounds;
				
				//add user marker if it exists
				if ((typeof searchMarker == 'object') && searchMarker) {
					bounds.extend(searchMarker.position);
				}
	
				setMapMarkers();
				setMapBounds();
			}
		}, 'json');	
	}
	
	//slugify a string, like WordPress's sanitize_title()
	function sanitizeTitle(str) {
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
	
	//set / initialize map
	function setMapBounds() {
		if (markers.length > 1) {
			map.fitBounds(bounds);
		} else if (markers.length == 1) {
			map.setCenter(bounds.getCenter());
			if (mode == 'map') google.maps.event.trigger(markers[0],'click');
			map.setZoom(14);
		} else if (markers.length == 0) {
			//currently holds last position, not sure if that's good
		}
	}
	
	//set single marker, called by all public pages
	function setMapMarker(title, lat, lng, content) {
		
		//cast as numeric;
		lat -= 0;
		lng -= 0;

		//stop if coordinates are empty
		if (!lat && !lng) return;
		
		//set new marker
		var marker = new google.maps.Marker({
			position: { lat: lat, lng: lng },
			map: map,
			title: title,
			icon: locationIcon,
		});
	
		//add infowindow event
		google.maps.event.addListener(marker, 'click', (function(marker) {
			return function() {
				infowindow.setContent('<div class="tsml_infowindow">' + content + '</div>');
				infowindow.open(map, marker);
			}
		})(marker));
		
		return marker;
	}
	
	//load map, called from archive-meetings.php
	function setMapMarkers() {
		for (var location_id in locations) {
			if (locations.hasOwnProperty(location_id)) {
				var location = locations[location_id];
	
				//create infowindow content
				var content = '<h3>' + formatLink(location.url, location.name, 'post_type') + '</h3>' +
					'<address>' + formatAddress(location.formatted_address) + '</address>';
					
				var current_day = null;
				for (var i = 0; i < location.meetings.length; i++) {
					var meeting = location.meetings[i];
					if (current_day != meeting.day) {
						if (current_day) content += '</dl>';
						current_day = meeting.day;
						if (typeof tsml.days[current_day] !== 'undefined') content += '<h5>' + tsml.days[current_day] + '</h5>';
						content += '<dl>';
					}
					content += '<dt>' + meeting.time + '</dt><dd>' + formatLink(meeting.url, meeting.name, 'post_type') + '</dd>';
				}
				content += '</dl>';
				
				var marker = setMapMarker(location.name, location.latitude, location.longitude, content);
					
				//add to map bounds
				if ((typeof marker == 'object') && marker) {
					bounds.extend(marker.position);
				}
				
				markers[markers.length] = marker;
			}
		}
	}
	
	//set or remove the search marker (user location or search center)
	function setSearchMarker(data) {
		if ((typeof searchMarker == 'object') && searchMarker) {
			searchMarker.setMap(null);
			searchMarker = null;
		}
		if (typeof data == 'object') {
			searchMarker = new google.maps.Marker({
				icon: userIcon,
				position: new google.maps.LatLng(data.latitude, data.longitude),
				map: map,
			});
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

	function tsmlDebug(string) {
		if (!tsml.debug) return;
		console.log(string);
	}

	//send event to google analytics, if loaded
	function trackAnalytics(action, label) {
		if (typeof ga === 'function') {
			//console.log('sending ' + action + ': ' + label + ' to google analytics');
			ga('send', 'event', '12 Step Meeting List', action, label);
		}
	}
	
		
	//disable the typeahead (if you switched to a different search mode)	
	function typeaheadDisable() {
		$('#meetings #search input[name="query"]').typeahead('destroy');
	}
	
	//enable the typeahead (if you switch back to search)
	function typeaheadEnable() {
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
				doSearch(false);
			} else if (item.type == 'location') {
				trackAnalytics('location', item.value);
				location.href = item.url;
			} else if (item.type == 'group') {
				trackAnalytics('group', item.value);
				doSearch(true);
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
