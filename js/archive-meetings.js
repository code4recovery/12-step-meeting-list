jQuery(function(){

	var userMarker;
	if (navigator.geolocation) {
		jQuery("#map_options").removeClass("hidden");
	}

	//run search (triggered by dropdown toggle or form submit)
	function doSearch() {
		//see what's selected
		var search = jQuery('#search input[name=query]').val().trim();

		//define search
		var days = [ "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

		var region = jQuery('#region li.active a').attr('data-id') ? jQuery('#region li.active a').attr('data-id') : '';
		
		console.log('searching with region ' + region);
		
		//prepare data for ajax
		var data = { 
			action: 'meetings',
			search: search,
			day: 	jQuery('#day li.active a').attr('data-id'),
			region: region,
			types: 	[]
		}

		//prepare search terms for highlighter
		if (search) {
			search = search.split(" ");
			for (var i = 0; i < search.length; i++) {
				search[i] = new RegExp( '(' + search[i] + ')', 'gi');
			}
		}

		//load types with selected menu items
		jQuery('#types li.active').each(function(){
			data['types'][data['types'].length] = jQuery(this).find('a').attr('data-id');
		});

		//request new meetings result
		jQuery.post(myAjax.ajaxurl, data, function(response){
			var tbody = jQuery("#meetings tbody").html("");
			if (response.length) {
				jQuery("#meetings table").removeClass("hidden");
				jQuery("#alert").addClass("hidden");
				var locations = [];

				//console.log('data.day was ' + data.day);

				//loop through JSON meetings
				jQuery.each(response, function(index, obj){

					//add gender designation
					if (jQuery.inArray('M', obj.types) != -1) {
						obj.name += ' <small>Men</small>';
					} else if (jQuery.inArray('M', obj.types) != -1) {
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
					tbody.append("<tr><td class='time'>" + (data.day ? obj.time_formatted : days[obj.day] + ", " + obj.time_formatted) + "</td><td class='name'><a href='" + obj.url + "'>" + highlight(obj.name, search) + "</a></td><td class='location'>" + highlight(obj.location, search) + "</td><td class='address'>" + obj.address + "</td><td class='region'>" + obj.region + "</td></tr>")
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
   					if (jQuery("#map").is(":visible")) google.maps.event.trigger(markers[0],'click');
   					map.setZoom(14);
				} else if (markers.length == 0) {
					//currently holds last position, not sure if that's good
				}

			} else {
				jQuery("#meetings table").addClass("hidden");
				jQuery("#alert").html("No results matched those criteria.").removeClass("hidden");
			}

		}, "json");		
	}

	//highlight search string
	function highlight(str, terms) {
		if (!terms.length) return str;
		for (var i = 0; i < terms.length; i++) {
			str = str.replace(terms[i], '<mark>$1</mark>')
		}
		//console.log('searhing ' + str + ' for ' + terms.join(' '));
		return str;
	}

	jQuery("#meetings #search").submit(function(e){

		//when submitting from input, clear dropdown values
		jQuery('#day li').removeClass('active').first().addClass('active');
		jQuery('#day span.selected').html('Any Day');
		jQuery('#region li').removeClass('active').first().addClass('active');
		jQuery('#region span.selected').html('Everywhere');
		jQuery('#types li').removeClass('active');
		jQuery('#types span.selected').html('Meeting Type');

		doSearch();
		return false;
	});

	jQuery('#meetings .controls .dropdown-menu a').click(function(e){
		e.preventDefault();

		//day only one selected
		if (jQuery(this).closest('.dropdown').attr('id') == 'day') {
			jQuery('#day li').removeClass('active');
			jQuery('#day span.selected').html(jQuery(this).html());
		}

		//location only one
		if (jQuery(this).closest('.dropdown').attr('id') == 'region') {
			jQuery('#region li').removeClass('active');
			jQuery('#region span.selected').html(jQuery(this).html());
		}

		jQuery(this).parent().toggleClass('active');

		//adjust type name
		if (jQuery(this).closest('.dropdown').attr('id') == 'types') {
			var count = jQuery('#types li.active').size();
			if (count == 0) {
				jQuery('#types span.selected').html('Meeting Type');
			} else if (count == 1) {
				jQuery('#types span.selected').html(jQuery('#types li.active a').first().html());
			} else {
				jQuery('#types span.selected').html('Meeting Types [' + count + ']');				
			}
		}

		doSearch();
	});

	//toggle between list and map
	jQuery('#meetings #action a').click(function(e){
		e.preventDefault();
		jQuery('#meetings #action a').toggleClass('active');
		jQuery('#meetings').attr('data-type', jQuery(this).attr('data-id'));

		//wake up the map
		google.maps.event.trigger(map, 'resize');
		map.fitBounds(bounds);
   		if ((markers.length == 1) && jQuery("#map").is(":visible")) {
   			map.setZoom(14);
   			google.maps.event.trigger(markers[0],'click');
   		}
	});

	jQuery("#geolocator").click(function(e){
		e.preventDefault();
		jQuery(this).toggleClass("active");

		if (jQuery(this).hasClass("active")) {
			navigator.geolocation.getCurrentPosition(function(position) {
				var pos = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);

				userMarker = new google.maps.Marker({
					icon: 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png',
				    position: pos,
				    map: map,
				    title: "You"
				});

				map.setCenter(pos);
				map.setZoom(13);
			}, function(err) {
  				console.log('ERROR(' + err.code + '): ' + err.message);
  				jQuery(this).removeClass("active")
  			});
		} else if (userMarker !== undefined) {
			userMarker.setMap(null);
		}
	});

	jQuery("#fullscreen").click(function(){
		var center = map.getCenter();
		jQuery(this).toggleClass("active");
		jQuery("#meetings").toggleClass("fullscreen");
		if (jQuery(this).hasClass("active")) {
			var height = jQuery(window).height() - 79;
			if (jQuery("body").hasClass("admin-bar")) height -= 32;
			jQuery("#map").css({height: height + 'px'});
		} else {
			jQuery("#map").css({height:false});
		}
		google.maps.event.trigger(map, 'resize');
		map.setCenter(center);
	});

});