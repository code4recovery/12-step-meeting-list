function closestmeetings() {
navigator.geolocation.getCurrentPosition(show)
}

jQuery( document ).ready(function() { 
    //console.log( "ready!" );
    closestmeetings();
});

function codeIt(arLoc) {
    return '<td class="time">'+arLoc['time_formatted']+'</td><td class="name"><a href="'+arLoc['url'].replace(/\\/g)+'">'+arLoc['name']+'</a></td><td class="distance">'+arLoc['distance']+' mi</td></tr>';
}

function show(position) { 
    
    var geo = jQuery("#geolocate").val();
    //var geo = "any"; 
	jQuery.ajax({
		url : displayclosestmeetings.ajax_url,
		type : "GET",
		data: {
			action : 'display_closest_meetings',
			lat : position.coords.latitude,
			long:position.coords.longitude,
			today: geo
		},
		success : function( response ) {
			
			response.sort(function (a, b) {
                 return a.time.localeCompare(b.time);
            });
			
			var txt = "";
			
			for (i=0; i<response.length; i++){
			    txt += codeIt(response[i]);
			}
			
 			    var head = '<thead><tr><th class="time">Time</th><th class="name">Meeting</th><th class="distance">Distance</th></tr></thead><tbody>'; 
 			    var tail = '</tbody>';

			jQuery('#dataxhr').html( head + txt + tail );
			//closestadj();
		    
		}
	});

	return true;
}