jQuery(function(){

	//typeahead
	var locations = new Bloodhound({
		datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value'),
		queryTokenizer: Bloodhound.tokenizers.whitespace,
		prefetch: {
			url: myAjax.ajaxurl + '?action=location&q=%QUERY',
			ttl: 10
		}
	});
	locations.initialize();
	jQuery('input#location').typeahead(null, {
		displayKey: 'value',
		source: locations.ttAdapter()
	}).on('typeahead:autocompleted', function($e, datum){
		jQuery("input[name=formatted_address]").val(datum.formatted_address);
		jQuery("input[name=latitude]").val(datum.latitude);
		jQuery("input[name=longitude]").val(datum.longitude);
		jQuery("input[name=address]").val(datum.address);
		jQuery("input[name=city]").val(datum.city);
		jQuery("input[name=state]").val(datum.state);
		jQuery("input[name=postal_code]").val(datum.postal_code);
		jQuery("input[name=country]").val(datum.country);
		jQuery("select[name=region] option[value='" + datum.region + "']").prop("selected", true);
		setMap(datum.latitude, datum.longitude)
	});

	/*timepicker
	jQuery('input[type=time]').timepicker({
		timeFormat: "hh:mm tt",
		stepMinute: 15
	});*/

	jQuery("form#post").submit(function(){
		var timeVal = jQuery("input[name=time]").val();
		var errors = false;
		if (timeVal.length != 5) errors = true;
		if (timeVal.indexOf(':') != 2) errors = true;
		var hours = timeVal.substr(0, 2);
		var minutes = timeVal.substr(3, 2);
		if (isNaN(hours) || hours < 0 || hours > 23) errors = true;
		if (isNaN(minutes) || minutes < 0 || minutes > 59) errors = true;
		if (errors) {
			alert('Time should be 24-hour format HH:MM.');
			return false;
		}
		return true;
	});

	//address / map
	jQuery("input#formatted_address").blur(function(){
		var val = jQuery(this).val();
		if (!val.length) {
			setMap();
			jQuery("input#address").val("");
			jQuery("input#city").val("");
			jQuery("input#state").val("");
			jQuery("input#zip").val("");
			jQuery("input#country").val("");
			jQuery("input#latitude").val("");
			jQuery("input#longitude").val("");
			return;
		}

		jQuery.getJSON("https://maps.googleapis.com/maps/api/geocode/json", { address: val, sensor : false }, function(data){

			//set lat + lng
			var latitude = data.results[0].geometry.location.lat;
			var longitude = data.results[0].geometry.location.lng;
			jQuery("input#latitude").val(latitude);
			jQuery("input#longitude").val(longitude);
			setMap(latitude, longitude);

			//guess region
			val = data.results[0].formatted_address;
			jQuery("select#region option").each(function(){
				if (val.indexOf(jQuery(this).text()) != -1) {
					jQuery(this).attr("selected", "selected");
					return false;
				}
			});
			
			console.log(data.results[0].address_components);
			
			//get address, city and state
			for (var i = 0; i < data.results[0].address_components.length; i++) {
				var component = data.results[0].address_components[i];
				if (component.types[0] == 'street_number') {
					//set address as street number
					jQuery("input#address").val(component.long_name);
				} else if (component.types[0] == 'route') {
					//append street name
					jQuery("input#address").val(jQuery("input#address").val() + " " + component.long_name);
				} else if (component.types[0] == 'locality') {
					//set city
					jQuery("input#city").val(component.long_name);
				} else if (component.types[0] == 'administrative_area_level_1') {
					//set state
					jQuery("input#state").val(component.short_name);
				} else if (component.types[0] == 'postal_code') {
					//set ZIP
					jQuery("input#postal_code").val(component.short_name);
				} else if (component.types[0] == 'country') {
					//set country
					jQuery("input#country").val(component.short_name);
				}
				
				jQuery("input#formatted_address").val(
					jQuery("input#address").val() + ', ' +
					jQuery("input#city").val() + ', ' +
					jQuery("input#state").val() + ' ' +
					jQuery("input#postal_code").val() + ', ' +
					jQuery("input#country").val()
				);
				
			}
		});
	});

	setMap(jQuery("input#latitude").val(), jQuery("input#longitude").val());

	function setMap(latitude, longitude) {
		if (!latitude || !longitude) {
			jQuery("div#map").html("");
			return;
		}
		var myLatlng = new google.maps.LatLng(latitude, longitude);
		var map = new google.maps.Map(document.getElementById("map"), { 
			zoom: 16, 
			zoomControl: false,
			scrollwheel: false,
			streetViewControl: false,
			mapTypeControl: false,
			center: myLatlng
		});
		var marker = new google.maps.Marker({ position: myLatlng, map: map });
	}
});