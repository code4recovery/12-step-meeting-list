//map functions -- methods must all support both google maps and mapbox

//declare some global variables
var infowindow,
	searchLocation,
	searchMarker,
	tsmlmap,
	markers = [],
	bounds,
	mapMode = 'none',
	locationIcon,
	searchIcon;

//create an empty map
function createMap(scrollwheel, locations, searchLocation) {
	if (tsml.debug) console.log('createMap() locations', locations);
	if (tsml.mapbox_key) {
		mapMode = 'mapbox';

		mapboxgl.accessToken = tsml.mapbox_key;

		//init map
		if (!tsmlmap) {
			tsmlmap = new mapboxgl.Map({
				container: 'map',
				style: tsml.mapbox_theme || 'mapbox://styles/mapbox/streets-v9'
			});

			//add zoom control
			tsmlmap.addControl(
				new mapboxgl.NavigationControl({
					showCompass: false
				})
			);
		}

		//init bounds
		bounds = {
			north: false,
			south: false,
			east: false,
			west: false
		};

		//custom marker icons
		locationIcon = window.btoa(
			'<?xml version="1.0" encoding="utf-8"?><svg viewBox="-1.1 -1.086 43.182 63.273" xmlns="http://www.w3.org/2000/svg"><path fill="#f76458" stroke="#b3382c" stroke-width="3" d="M20.5,0.5 c11.046,0,20,8.656,20,19.333c0,10.677-12.059,21.939-20,38.667c-5.619-14.433-20-27.989-20-38.667C0.5,9.156,9.454,0.5,20.5,0.5z"/></svg>'
		);
		searchIcon = window.btoa(
			'<?xml version="1.0" encoding="utf-8"?><svg viewBox="-1.1 -1.086 43.182 63.273" xmlns="http://www.w3.org/2000/svg"><path fill="#2c78b3" stroke="#2c52b3" stroke-width="3" d="M20.5,0.5 c11.046,0,20,8.656,20,19.333c0,10.677-12.059,21.939-20,38.667c-5.619-14.433-20-27.989-20-38.667C0.5,9.156,9.454,0.5,20.5,0.5z"/></svg>'
		);
	} else if (tsml.google_maps_key) {
		//check to see if google ready (wp google maps was removing other map scripts for a while)
		if (typeof google !== 'object') {
			console.warn('google key present but google script not ready');
			return;
		}

		mapMode = 'google';

		//init map
		if (!tsmlmap)
			tsmlmap = new google.maps.Map(document.getElementById('map'), {
				disableDefaultUI: true,
				scrollwheel: scrollwheel,
				zoomControl: true
			});

		//init popup
		infowindow = new google.maps.InfoWindow();

		//init bounds
		bounds = new google.maps.LatLngBounds();
	}

	setMapMarkers(locations, searchLocation);
}

//format an address: replace commas with breaks
function formatAddress(address, street_only) {
	if (!address) return '';
	address = address.split(', ');
	if (street_only) return address[0];
	if (address[address.length - 1] == 'USA') {
		address.pop(); //don't show USA
		var state_and_zip = address.pop();
		address[address.length - 1] += ', ' + state_and_zip;
	}
	return address.join('<br>');
}

//format a link to a meeting result page, preserving all but the excluded query string keys
function formatLink(url, text, exclude) {
	if (!url) return text;
	if (location.search) {
		var query_pairs = location.search.substr(1).split('&');
		var new_query_pairs = [];
		for (var i = 0; i < query_pairs.length; i++) {
			var query_parts = query_pairs[i].split('=');
			if (query_parts[0] != exclude) new_query_pairs[new_query_pairs.length] = query_parts[0] + '=' + query_parts[1];
		}
		if (new_query_pairs.length) {
			url += (url.indexOf('?') == -1 ? '?' : '&') + new_query_pairs.join('&');
		}
	}
	return '<a href="' + url + '">' + text + '</a>';
}

//remove search marker
function removeSearchMarker() {
	searchLocation = null;
	if (typeof searchMarker == 'object' && searchMarker) {
		searchMarker.setMap(null);
		searchMarker = null;
	}
}

//set / initialize map
function setMapBounds() {
	if (mapMode == 'google') {
		if (markers.length > 1) {
			//multiple markers
			tsmlmap.fitBounds(bounds);
		} else if (markers.length == 1) {
			//if only one marker, zoom in and click the infowindow
			var center = bounds.getCenter();
			if (markers[0].getClickable()) {
				tsmlmap.setCenter({lat: center.lat() + 0.0025, lng: center.lng()});
				google.maps.event.trigger(markers[0], 'click');
			} else {
				tsmlmap.setCenter({lat: center.lat(), lng: center.lng()});
			}
			tsmlmap.setZoom(15);
		}
	} else if (mapMode == 'mapbox') {
		if (markers.length > 1) {
			//multiple markers
			tsmlmap.fitBounds(
				[
					[bounds.west, bounds.south],
					[bounds.east, bounds.north]
				],
				{
					duration: 0,
					padding: 100
				}
			);
		} else if (markers.length == 1) {
			//if only one marker, zoom in and open the popup if it exists
			if (markers[0].getPopup()) {
				tsmlmap.setZoom(14).setCenter([bounds.east, bounds.north + 0.0025]);
				markers[0].togglePopup();
			} else {
				tsmlmap.setZoom(14).setCenter([bounds.east, bounds.north]);
			}
		}
	}
}

//set single marker, called by all public pages
function setMapMarker(title, position, content) {
	//stop if coordinates are empty
	if (!position.lat && !position.lng) return;

	var marker;

	if (mapMode == 'google') {
		//set new marker
		marker = new google.maps.Marker({
			position: position,
			map: tsmlmap,
			title: title,
			icon: {
				path:
					'M20.5,0.5 c11.046,0,20,8.656,20,19.333c0,10.677-12.059,21.939-20,38.667c-5.619-14.433-20-27.989-20-38.667C0.5,9.156,9.454,0.5,20.5,0.5z',
				fillColor: '#f76458',
				fillOpacity: 1,
				anchor: new google.maps.Point(40, 50),
				strokeWeight: 2,
				strokeColor: '#b3382c',
				scale: 0.6
			}
		});

		//add infowindow event
		if (content) {
			google.maps.event.addListener(
				marker,
				'click',
				(function(marker) {
					return function() {
						infowindow.setContent('<div class="tsml_infowindow">' + content + '</div>');
						infowindow.open(tsmlmap, marker);
					};
				})(marker)
			);
		} else {
			marker.setClickable(false); //we'll check this when setting center
		}
	} else if (mapMode == 'mapbox') {
		var el = document.createElement('div');
		el.className = 'marker';
		el.style.backgroundImage = 'url(data:image/svg+xml;base64,' + locationIcon + ')';
		el.style.width = '26px';
		el.style.height = '38.4px';

		marker = new mapboxgl.Marker(el).setLngLat(position);

		if (content) {
			var popup = new mapboxgl.Popup({offset: 25});
			popup.setHTML(content);
			marker.setPopup(popup);
		}

		marker.addTo(tsmlmap);
	}

	return marker;
}

//add one or more markers to a map
function setMapMarkers(locations, searchLocation) {
	//remove existing markers
	if (markers.length) {
		for (var i = 0; i < markers.length; i++) {
			if (mapMode == 'google') {
				markers[i].setMap(null);
			} else if (mapMode == 'mapbox') {
				markers[i].remove();
			}
		}
		markers = [];
	}

	//set search location?
	removeSearchMarker();
	if (searchLocation) {
		if (tsml.debug) console.log('setMapMarker() searchLocation', searchLocation);
		setSearchMarker(searchLocation);
	}

	//convert to array and sort it by latitude (for marker overlaps)
	var location_array = Object.keys(locations)
		.map(function(e) {
			return locations[e];
		})
		.sort(function(a, b) {
			return b.latitude - a.latitude;
		});

	//loop through and create new markers
	for (var i = 0; i < location_array.length; i++) {
		var location = location_array[i];
		if (tsml.debug) console.log('setMapMarkers() location', location);
		var content;

		if (location.url && location.formatted_address && !location.approximate) {
			//create infowindow content
			content =
				'<h3>' +
				formatLink(location.url, location.name, 'post_type') +
				'</h3>' +
				'<address>' +
				formatAddress(location.formatted_address) +
				'</address>';

			//make directions button
			if (location.directions && location.directions_url) {
				content +=
					'<a href="' +
					location.directions_url +
					'" class="btn btn-default btn-block">' +
					'<svg class="icon" width="1em" height="1em" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">' +
					'<path fill-rule="evenodd" d="M9.896 2.396a.5.5 0 0 0 0 .708l2.647 2.646-2.647 2.646a.5.5 0 1 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708 0z"/>' +
					'<path fill-rule="evenodd" d="M13.25 5.75a.5.5 0 0 0-.5-.5h-6.5a2.5 2.5 0 0 0-2.5 2.5v5.5a.5.5 0 0 0 1 0v-5.5a1.5 1.5 0 0 1 1.5-1.5h6.5a.5.5 0 0 0 .5-.5z"/>' +
					'</svg>' +
					location.directions +
					'</a>';
			}

			//make meeting list
			if (location.meetings && location.meetings.length) {
				var current_day = null;
				for (var j = 0; j < location.meetings.length; j++) {
					var meeting = location.meetings[j];
					if (current_day != meeting.day) {
						if (current_day) content += '</dl>';
						current_day = meeting.day;
						if (typeof tsml.days[current_day] !== 'undefined') content += '<h5>' + tsml.days[current_day] + '</h5>';
						content += '<dl>';
					}
					content += '<dt>' + meeting.time + '</dt><dd>' + formatLink(meeting.url, meeting.name, 'post_type') + '</dd>';
				}
				content += '</dl>';
			}
		}

		//make coordinates numeric
		var position = {
			lat: parseFloat(location.latitude),
			lng: parseFloat(location.longitude)
		};

		var marker = setMapMarker(location.name, position, content);

		//manage bounds and set "visibility" if not approximate location
		if (typeof marker == 'object' && marker) {
			if (mapMode == 'google') {
				bounds.extend(marker.position);
				if (location.approximate === 'yes') marker.setVisible(false);
			} else if (mapMode == 'mapbox') {
				if (!bounds.north || position.lat > bounds.north) bounds.north = position.lat;
				if (!bounds.south || position.lat < bounds.south) bounds.south = position.lat;
				if (!bounds.east || position.lng > bounds.east) bounds.east = position.lng;
				if (!bounds.west || position.lng < bounds.west) bounds.west = position.lng;
				if (location.approximate === 'yes') marker.remove();
			}
		}

		if (tsml.debug) console.log('setMapMarkers() marker', marker);

		markers.push(marker);
	}

	setMapBounds();
}

//set or remove the search marker (user location or search center)
function setSearchMarker(data) {
	removeSearchMarker();
	if (!data || !data.latitude) return;
	if (mapMode == 'google') {
		searchMarker = new google.maps.Marker({
			icon: {
				path:
					'M20.5,0.5 c11.046,0,20,8.656,20,19.333c0,10.677-12.059,21.939-20,38.667c-5.619-14.433-20-27.989-20-38.667C0.5,9.156,9.454,0.5,20.5,0.5z',
				fillColor: '#2c78b3',
				fillOpacity: 1,
				anchor: new google.maps.Point(40, 50),
				strokeWeight: 2,
				strokeColor: '#2c52b3',
				scale: 0.6
			},
			position: new google.maps.LatLng(data.latitude, data.longitude),
			map: tsmlmap
		});

		bounds.extend(searchMarker.position);
	} else if (mapMode == 'mapbox') {
		var el = document.createElement('div');
		el.className = 'marker';
		el.style.backgroundImage = 'url(data:image/svg+xml;base64,' + searchIcon + ')';
		el.style.width = '26px';
		el.style.height = '38.4px';

		marker = new mapboxgl.Marker(el).setLngLat([data.longitude, data.latitude]).addTo(tsmlmap);
	}
}
