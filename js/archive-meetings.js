jQuery(function($){

	var userMarker;
	if (navigator.geolocation) {
		$('#map_options').removeClass('hidden');
	}

	//run search (triggered by dropdown toggle or form submit)
	function doSearch() {

		//prepare data for ajax
		var data = { 
			action: 'meetings',
			search: $('#search input[name=query]').val().trim(),
			day: 	$('#day li.active a').attr('data-id'),
			time:   $('#time li.active a').attr('data-id'),
			region: $('#region li.active a').attr('data-id'),
			type:   $('#type li.active a').attr('data-id'),
		}
		
		//get current query string for history and appending to links
		var querystring = {};
		if (data.search) querystring.sq = data.search;
		querystring.d = data.day ? data.day : 'any';
		if (data.time) querystring.i = data.time;
		if (data.region) querystring.r = data.region;
		if (data.type) querystring.t = data.type;
		querystring.v = $('#meetings .toggle-view.active').attr('data-id');
		querystring = jQuery.param(querystring);
		//console.log('querystring is ' + querystring)
		
		//save the query in the query string, if the browser is up to it
		if (history.pushState) {
			var url = window.location.protocol + '//' + window.location.host + window.location.pathname;
			if (querystring.length) url = url + '?' + querystring;
			if (location.search.indexOf('post_type=meetings') > -1) {
				url = url + ((url.indexOf('?') > -1) ? '&' : '?') + 'post_type=meetings';
			}
			window.history.pushState({path:url}, '', url);
		}
		
		//prepare search terms for highlighter
		var search = [];
		if (data.search) {
			search = data.search.split(' ');
			for (var i = 0; i < search.length; i++) {
				search[i] = new RegExp( '(' + search[i] + ')', 'gi');
			}
		}
		
		//debugging
		//console.log(myAjax.ajaxurl)
		//console.log(data);

		//request new meetings result
		jQuery.post(myAjax.ajaxurl, data, function(response){
			if (!response.length) {

				//if keyword and no results, clear other parameters and search again
				if (data.search && (typeof data.day !== 'undefined' || typeof data.region !== 'undefined' || typeof data.time !== 'undefined' || typeof data.type !== 'undefined')) {
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

				$('#meetings table').addClass('hidden');
				$('#meetings #map').addClass('hidden');
				$('#alert').html('No results matched those criteria.').removeClass('hidden');
			} else {
				$('#meetings table').removeClass('hidden');
				if ($('#meetings #map').hasClass('hidden')) {
					$('#meetings #map').removeClass('hidden');
					google.maps.event.trigger(map, 'resize');
				}
				
				$('#alert').addClass('hidden');

				var locations = [];

				var tbody = $('#meetings_tbody').html('');

				//loop through JSON meetings
				jQuery.each(response, function(index, obj){

					//add gender designation
					if (jQuery.inArray('M', obj.types) != -1) {
						obj.name += ' <small>Men</small>';
					} else if (jQuery.inArray('W', obj.types) != -1) {
						obj.name += ' <small>Women</small>';
					}

					//save location info
					if (!locations[obj.location_id]) {
						locations[obj.location_id] = {
							name: obj.location,
							address: obj.address,
							latitude: obj.latitude,
							longitude: obj.longitude,
							city: obj.city,
							state: obj.state,
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

					var sort_time = obj.day + '-' + (obj.time == '00:00' ? '11:59' : obj.time);
					
					//add new table row
					tbody.append('<tr>' + 
						'<td class="time" data-sort="' + sort_time + '">' + (data.day || !obj.day ? obj.time_formatted : days[obj.day] + ', ' + obj.time_formatted) + '</td>' + 
						'<td class="name" data-sort="' + obj.name + '-' + sort_time + '">' + formatLink(obj.url, highlight(obj.name, search), 'post_type') + '<div class="visible-print-block">' + (obj.sub_region || obj.region || '') + '</div></td>' + 
						'<td class="location" data-sort="' + obj.location + '-' + sort_time + '">' + highlight(obj.location, search) + '<div class="visible-print-block">' + highlight(obj.address, search) + '</div></td>' + 
						'<td class="address hidden-print" data-sort="' + obj.address + '-' + sort_time + '">' + highlight(obj.address, search) + '</td>' + 
						'<td class="region hidden-print" data-sort="' + (obj.sub_region || obj.region || '') + '-' + sort_time + '">' + (obj.sub_region || obj.region || '') + '</td>' + 
					'</tr>')
				});
				
				sortMeetings();

				//remove old markers and reset bounds
				for (var i = 0; i < markers.length; i++) markers[i].setMap(null);
				markers = [];
				bounds = new google.maps.LatLngBounds;

				loadMap(locations);
			}
		}, 'json');	
	}
	
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
	
	function sortMeetings() {
		$sorted = $('#meetings table thead th[data-sort]').first();
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
	
	//highlight search string
	function highlight(str, terms) {
		if (!terms.length) return str;
		for (var i = 0; i < terms.length; i++) {
			str = str.replace(terms[i], '<mark>$1</mark>')
		}
		return str;
	}

	//capture submit event
	$('#meetings #search').submit(function(e){
		doSearch();
		return false;
	});

	//capture dropdown change
	$('#meetings .controls').on('click', '.dropdown-menu a', function(e){
		e.preventDefault();

		//day only one selected
		if ($(this).closest('.dropdown').attr('id') == 'day') {
			$('#day li').removeClass('active');
			$('#day span.selected').html($(this).html());
		}

		//times only one
		if ($(this).closest('.dropdown').attr('id') == 'time') {
			$('#time li').removeClass('active');
			$('#time span.selected').html($(this).html());
		}

		//location only one
		if ($(this).closest('.dropdown').attr('id') == 'region') {
			$('#region li').removeClass('active');
			$('#region span.selected').html($(this).html());
		}

		//type only one
		if ($(this).closest('.dropdown').attr('id') == 'type') {
			$('#type li').removeClass('active');
			$('#type span.selected').html($(this).html());
		}

		$(this).parent().toggleClass('active');

		updateTitle();
		doSearch();
	});

	//toggle between list and map
	$('#meetings #action .toggle-view').click(function(e){
		e.preventDefault();
		
		//what's going on
		var action = $(this).attr('data-id');
		var previous = $('#meetings').attr('data-type');

		//save the query in the query string, if the browser is up to it
		if (history.pushState) {
			var url = updateQueryString('v', action);
			window.history.pushState({path:url}, '', url);
		}
		
		//toggle control, meetings div
		if (action == 'list') {
			closeFullscreen();
			$('#meetings #action .toggle-view[data-id=list]').addClass('active');
			$('#meetings #action .toggle-view[data-id=map]').removeClass('active');			
		} else if (action == 'map') {
			$('#meetings #action .toggle-view[data-id=map]').addClass('active');
			$('#meetings #action .toggle-view[data-id=list]').removeClass('active');			
		}

		//set meetings div
		$('#meetings').attr('data-type', action);
		
		//wake up the map if needed
		if (action == 'map' && action != previous) {
			google.maps.event.trigger(map, 'resize');
			map.fitBounds(bounds);
	   		if ((markers.length == 1) && $('#map').is(':visible')) {
	   			map.setZoom(14);
	   			google.maps.event.trigger(markers[0],'click');
	   		}
		}
	});

	$('a[href="#geolocator"]').click(function(e){
		e.preventDefault();
		$(this).toggleClass('active');

		if ($(this).hasClass('active')) {
			navigator.geolocation.getCurrentPosition(function(position) {
				var pos = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);

				userMarker = new google.maps.Marker({
					icon: 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png',
					position: pos,
					map: map,
					title: 'You'
				});

				map.setCenter(pos);
				map.setZoom(13);
			}, function(err) {
  				console.log('ERROR(' + err.code + '): ' + err.message);
  				$(this).removeClass('active')
  			});
		} else if (userMarker !== undefined) {
			userMarker.setMap(null);
		}
	});

	$('a[href="#fullscreen"]').click(function(e){
		e.preventDefault();
		var center = map.getCenter();
		$(this).toggleClass('active');
		if ($(this).hasClass('active')) {
			$('#meetings').addClass('fullscreen');
			var height = $(window).height() - 79;
			if ($('body').hasClass('admin-bar')) height -= 32;
			$('#map').css('height', height);
		} else {
			closeFullscreen();
		}
		google.maps.event.trigger(map, 'resize');
		map.setCenter(center);
	});

	//remove fullscreen with an escape key press
	$(document).keyup(function(e) {
		if (e.keyCode == 27) closeFullscreen();
	});
	
	function closeFullscreen() {
		if ($('#meetings').hasClass('fullscreen')) {
			$('#meetings').removeClass('fullscreen');
			$('a[href=#fullscreen]').removeClass('active');
			$('a[href=#fullscreen]').parent().removeClass('active');
			$('#map').css('height', 550);
		}
	}
	
	//save a string of the current state to the title bar, so that it prints nicely
	function updateTitle() {
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
		string += ' Meetings';
		if ($('#meetings #region li.active').index()) {
			string += ' in ' + $('#meetings #region span.selected').text();
		}
		document.title = string;
	}
	if ($('body').hasClass('post-type-archive-meetings')) updateTitle();
	
	//resize fullscreen on resize
	$(window).resize(function(e){
		if ($('#meetings').hasClass('fullscreen')) {
			var center = map.getCenter();
			var height = $(window).height() - 79;
			if ($('body').hasClass('admin-bar')) height -= 32;
			$('#map').css('height', height);
			google.maps.event.trigger(map, 'resize');
			map.setCenter(center);
		}
	});
	
});

//globals
markers = [];
map = new google.maps.Map(document.getElementById('map'), {
	panControl: false,
	mapTypeControl: false,
	mapTypeControlOptions: {
		mapTypeIds: [google.maps.MapTypeId.ROADMAP, 'map_style']
	}
});
bounds = new google.maps.LatLngBounds();

days = [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

infowindow = new google.maps.InfoWindow();


//load map, called from archive-meetings.php
function loadMap(locations) {

	for (var location_id in locations) {
		if (locations.hasOwnProperty(location_id)) {
			//console.log(locations[location_id]);
			
			var location = locations[location_id];
			
			//set new marker
			var marker = new google.maps.Marker({
				position: {lat: location.latitude - 0, lng: location.longitude - 0},
				map: map,
				title: location.name,
			});

			//create infowindow content
			marker.content = '<div class="infowindow"><h3>' + formatLink(location.url, location.name, 'post_type') + '</h3>' +
				'<address>' + location.address + '<br>' + location.city + (location.state ? ', ' + location.state : '') + '</address>';
				
			var current_day = null;
			for (var i = 0; i < location.meetings.length; i++) {
				var meeting = location.meetings[i];
				if (current_day != meeting.day) {
					if (current_day) marker.content += '</dl>';
					current_day = meeting.day;
					if (typeof days[current_day] !== 'undefined') marker.content += '<h5>' + days[current_day] + '</h5>';
					marker.content += '<dl>';
				}
				marker.content += '<dt>' + meeting.time + '</dt><dd>' + formatLink(meeting.url, meeting.name, 'post_type') + '</dd>';
			}
			marker.content += '</dl></div>';
			
			//add infowindow event
			google.maps.event.addListener(marker, 'click', (function(marker) {
				return function() {
					infowindow.setContent(marker.content);
					infowindow.open(map, marker);
				}
			})(marker));
	
			//add to map bounds
			bounds.extend(marker.position);
			
			markers[markers.length] = marker;
		}
	}

	if (markers.length > 1) {
		map.fitBounds(bounds);
	} else if (markers.length == 1) {
		map.setCenter(bounds.getCenter());
		if (jQuery('#map').is(':visible')) google.maps.event.trigger(markers[0],'click');
		map.setZoom(14);
	} else if (markers.length == 0) {
		//currently holds last position, not sure if that's good
	}
	
}

//format a link to a meeting result page, preserving all but the excluded query string keys
function formatLink(url, text, exclude) {
	var query_pairs = location.search.substr(1).split('&');
	var new_query_pairs = [];
	for (var i = 0; i < query_pairs.length; i++) {
		var query_parts = query_pairs[i].split('=');
		if (query_parts[0] != exclude) new_query_pairs[new_query_pairs.length] = query_parts[0] + '=' + query_parts[1];
	}
	if (new_query_pairs.length) {
		url += ((url.indexOf('?') == -1) ? '?' : '&') + new_query_pairs.join('&');
	}
	return '<a href="' + url + '">' + text + '</a>';
}

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
