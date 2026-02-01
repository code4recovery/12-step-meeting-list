// map functions

// declare some global variables
var infowindow,
	searchLocation,
	searchMarker,
	tsmlmap,
	markers = [],
	bounds,
	locationIcon,
	searchIcon,
	mapProvider = tsml.map_provider || 'leaflet';

// create an empty map
function createMap(scrollwheel, locations, searchLocation) {
	if (tsml.debug) console.log('createMap() locations', locations);

	// init map based on provider
	if (!tsmlmap) {
		if (mapProvider === 'yandex') {
			// Initialize Yandex Map
			ymaps.ready(function() {
				tsmlmap = new ymaps.Map('map', {
					center: [55.76, 37.64], // Default center (Moscow)
					zoom: 10,
					controls: ['zoomControl', 'typeSelector', 'fullscreenControl']
				});
			});
		} else {
			// Initialize Leaflet Map
			tsmlmap = L.map('map');
			L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
				maxZoom: 19,
				attribution:
					'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>'
			}).addTo(tsmlmap);
		}
	}

	// init bounds
	bounds = {
		north: false,
		south: false,
		east: false,
		west: false
	};

	// custom marker icons for Leaflet
	if (mapProvider === 'leaflet') {
		locationIcon = window.btoa(
			'<?xml version="1.0" encoding="utf-8"?><svg viewBox="-1.1 -1.086 43.182 63.273" xmlns="http://www.w3.org/2000/svg"><path fill="#f76458" stroke="#b3382c" stroke-width="3" d="M20.5,0.5 c11.046,0,20,8.656,20,19.333c0,10.677-12.059,21.939-20,38.667c-5.619-14.433-20-27.989-20-38.667C0.5,9.156,9.454,0.5,20.5,0.5z"/></svg>'
		);
		searchIcon = window.btoa(
			'<?xml version="1.0" encoding="utf-8"?><svg viewBox="-1.1 -1.086 43.182 63.273" xmlns="http://www.w3.org/2000/svg"><path fill="#2c78b3" stroke="#2c52b3" stroke-width="3" d="M20.5,0.5 c11.046,0,20,8.656,20,19.333c0,10.677-12.059,21.939-20,38.667c-5.619-14.433-20-27.989-20-38.667C0.5,9.156,9.454,0.5,20.5,0.5z"/></svg>'
		);
	}

	setMapMarkers(locations, searchLocation);
}

// format an address: replace commas with breaks
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

// format a link to a meeting result page, preserving all but the excluded query string keys
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

// remove search marker
function removeSearchMarker() {
	searchLocation = null;
	if (typeof searchMarker == 'object' && searchMarker) {
		if (mapProvider === 'yandex') {
			tsmlmap.geoObjects.remove(searchMarker);
		} else {
			searchMarker.setMap(null);
		}
		searchMarker = null;
	}
}

// set / initialize map
function setMapBounds() {
	if (markers.length > 1) {
		//multiple markers
		if (mapProvider === 'yandex') {
			tsmlmap.setBounds([
				[bounds.south, bounds.west],
				[bounds.north, bounds.east]
			], {
				checkZoomRange: true,
				zoomMargin: 10
			});
		} else {
			tsmlmap.fitBounds(
				[
					[bounds.south, bounds.west],
					[bounds.north, bounds.east]
				],
				{padding: [10, 10]}
			);
		}
	} else if (markers.length == 1) {
		//if only one marker, zoom in and open the popup if it exists
		if (mapProvider === 'yandex') {
			tsmlmap.setCenter([bounds.north, bounds.east], 16);
			if (markers[0].balloon) {
				markers[0].balloon.open();
			}
		} else {
			if (markers[0].getPopup()) {
				tsmlmap.setZoom(16);
				tsmlmap.panTo([bounds.north + 0.0025, bounds.east]);
				markers[0].togglePopup();
			} else {
				tsmlmap.setZoom(16);
				tsmlmap.panTo([bounds.north, bounds.east]);
			}
		}
	}
}

//set single marker, called by all public pages
function setMapMarker(title, position, content) {
	//stop if coordinates are empty
	if (!position.lat && !position.lng) return;

	var marker;

	if (mapProvider === 'yandex') {
		// Create Yandex marker
		marker = new ymaps.Placemark([position.lat, position.lng], {
			hintContent: title,
			balloonContent: content || title
		}, {
			preset: 'islands#redIcon'
		});
		
		tsmlmap.geoObjects.add(marker);
	} else {
		// Create Leaflet marker
		var html = document.createElement('div');
		html.className = 'marker';
		html.style.backgroundImage = 'url(data:image/svg+xml;base64,' + locationIcon + ')';
		html.style.width = '26px';
		html.style.height = '38.4px';

		var icon = L.divIcon({className: 'marker', html, iconAnchor: [13, 38.4], popupAnchor: [0, -22]});

		marker = new L.marker(position, {icon}).addTo(tsmlmap);

		if (content) {
			var popup = new L.popup();
			popup.setContent(content);
			marker.bindPopup(popup);
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
			if (mapProvider === 'yandex') {
				tsmlmap.geoObjects.remove(markers[i]);
			} else {
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
	var location_array =
		typeof locations === 'object'
			? Object.keys(locations)
					.map(function (e) {
						return locations[e];
					})
					.sort(function (a, b) {
						return b.latitude - a.latitude;
					})
			: [];

	//loop through and create new markers
	for (var i = 0; i < location_array.length; i++) {
		var location = location_array[i];
		if (tsml.debug) console.log('setMapMarkers() location', location);
		var content;

		if (location.url && location.formatted_address && !location.approximate) {
			//create infowindow content
			content =
				'<h3 class="notranslate">' +
				formatLink(location.url, location.name, 'post_type') +
				'</h3>' +
				'<address class="notranslate">' +
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
			if (!bounds.north || position.lat > bounds.north) bounds.north = position.lat;
			if (!bounds.south || position.lat < bounds.south) bounds.south = position.lat;
			if (!bounds.east || position.lng > bounds.east) bounds.east = position.lng;
			if (!bounds.west || position.lng < bounds.west) bounds.west = position.lng;
			if (location.approximate === 'yes') {
				if (mapProvider === 'yandex') {
					marker.options.set('visible', false);
				} else {
					marker.remove();
				}
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
	
	if (mapProvider === 'yandex') {
		searchMarker = new ymaps.Placemark([data.latitude, data.longitude], {
			hintContent: 'Search Location'
		}, {
			preset: 'islands#blueIcon'
		});
		tsmlmap.geoObjects.add(searchMarker);
	} else {
		var html = document.createElement('div');
		html.className = 'marker';
		html.style.backgroundImage = 'url(data:image/svg+xml;base64,' + searchIcon + ')';
		html.style.width = '26px';
		html.style.height = '38.4px';

		var icon = L.divIcon({className: 'marker', html, iconAnchor: [13, 38.4], popupAnchor: [0, -22]});

		searchMarker = new L.marker([data.latitude, data.longitude], {icon}).addTo(tsmlmap);
	}
}
