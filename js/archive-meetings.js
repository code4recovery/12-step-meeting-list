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
			time: $('#time li.active a').attr('data-id'),
			region: $('#region li.active a').attr('data-id'),
			type: $('#type li.active a').attr('data-id'),
		}
		
		//get current query string for history and appending to links
		var querystring = {};
		if (data.search) querystring.sq = data.search;
		querystring.d = data.day ? data.day : 'any';
		if (data.time) querystring.i = data.time;
		if (data.region) querystring.r = data.region;
		if (data.type) querystring.t = data.type;
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

				var tbody = $('#meetings tbody').html('');

				//loop through JSON meetings
				jQuery.each(response, function(index, obj){

					//console.log(obj);

					//append query string to url
					if (querystring.length) {
						obj.url = obj.url + ((obj.url.indexOf('?') > -1) ? '&' : '?');
						obj.url = obj.url + querystring;
					}

					//add gender designation
					if (jQuery.inArray('M', obj.types) != -1) {
						obj.name += ' <small>Men</small>';
					} else if (jQuery.inArray('W', obj.types) != -1) {
						obj.name += ' <small>Women</small>';
					}

					//save location info
					if (!locations[obj.location_id]) {
						locations[obj.location_id] = {
							name : obj.location,
							address : obj.address,
							latitude : obj.latitude,
							longitude : obj.longitude,
							city : obj.city,
							state : obj.state,
							meetings : []
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

					//add new table row
					tbody.append('<tr>' + 
						'<td class="time">' + (data.day || !obj.day ? obj.time_formatted : days[obj.day] + ', ' + obj.time_formatted) + '</td>' + 
						'<td class="name"><a href="' + obj.url + '">' + highlight(obj.name, search) + '</a><div class="visible-print-block">' + (obj.sub_region || obj.region || '') + '</div></td>' + 
						'<td class="location">' + highlight(obj.location, search) + '<div class="visible-print-block">' + highlight(obj.address, search) + '</div></td>' + 
						'<td class="address hidden-print">' + highlight(obj.address, search) + '</td>' + 
						'<td class="region hidden-print">' + (obj.sub_region || obj.region || '') + '</td>' + 
					'</tr>')
				});

				//remove old markers and reset bounds
				for (var i = 0; i < markers.length; i++) markers[i].setMap(null);
				markers = [];
				bounds = new google.maps.LatLngBounds;

				//loop through new markers and add them (sparse array)
				for (location_id in locations) {
				    if (locations.hasOwnProperty(location_id) && /^0$|^[1-9]\d*$/.test(location_id) && location_id <= 4294967294) {
				        var obj = locations[location_id];
						var marker = new google.maps.Marker({
						    position: new google.maps.LatLng(obj.latitude, obj.longitude),
						    map: map,
						    title: obj.name
						});

						markers[markers.length] = marker;
						bounds.extend(marker.position);

						//add infowindow event
						google.maps.event.addListener(marker, 'click', (function(marker, obj) {
							return function() {
								var meetings = {};
								for (var i = 0; i < obj.meetings.length; i++) {
									if (!meetings[days[obj.meetings[i].day]]) meetings[days[obj.meetings[i].day]] = '';
									meetings[days[obj.meetings[i].day]] += '<dt>' + obj.meetings[i].time + '</dt>' + 
										'<dd><a href="' + obj.meetings[i].url + '">' + obj.meetings[i].name + '</a></dd>';
								}
								var meetings_list = '';
								for (var day in meetings) {
									meetings_list += '<h5>' + day + '</h5><dl>' + meetings[day] + '</dl>';
								}

								infowindow.setContent('<div class="infowindow"><h3>' + obj.name + '</h3><address>' + obj.address + '<br>' + obj.city + ', ' + obj.state + '</address>' + meetings_list + '</div>');
								infowindow.open(map, marker);
							}
						})(marker, obj));					
				    }
				}

				//handle zooming
				if (markers.length > 1) {
					map.fitBounds(bounds);
				} else if (markers.length == 1) {
					map.setCenter(bounds.getCenter());
   					if ($('#map').is(':visible')) google.maps.event.trigger(markers[0],'click');
   					map.setZoom(14);
				} else if (markers.length == 0) {
					//currently holds last position, not sure if that's good
				}

			}

		}, 'json');		
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
	updateTitle();
	
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
			    position: {lat: location.latitude, lng: location.longitude},
			    map: map,
			    title: location.name,
			});

			//create infowindow content
			marker.content = '<div class="infowindow"><h3><a href="' + location.url + '">' + location.name + '</h3>' +
				'<address>' + location.address + '<br>' + location.city_state + '</address>';
				
			var current_day = null;
			for (var i = 0; i < location.meetings.length; i++) {
				var meeting = location.meetings[i];
				if (current_day != meeting.day) {
					if (current_day) marker.content += '</dl>';
					current_day = meeting.day;
					marker.content += '<h5>' + days[current_day] + '</h5><dl>';
				}
				marker.content += '<dt>' + meeting.time + '</dt><dd>' + meeting.link + '</dd>';
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

	map.fitBounds(bounds);
	
}