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
		jQuery("select[name=region] option[value='" + datum.region + "']").prop("selected", true);
		setMap(datum.latitude, datum.longitude)
	});

	//address / map
	jQuery("input#formatted_address").blur(function(){
		var val = jQuery(this).val();
		if (!val.length) {
			setMap();
			jQuery("input#address").val("");
			jQuery("input#city").val("");
			jQuery("input#state").val("");
			jQuery("input#latitude").val("");
			jQuery("input#longitude").val("");
			return;
		}

		jQuery.getJSON("https://maps.googleapis.com/maps/api/geocode/json", { address: val, sensor : false }, function(data){
			jQuery("input#formatted_address").val(data.results[0].formatted_address);
			var latitude = data.results[0].geometry.location.lat;
			var longitude = data.results[0].geometry.location.lng;
			jQuery("input#latitude").val(latitude);
			jQuery("input#longitude").val(longitude);
			setMap(latitude, longitude);

			//guess region
			if (val != data.results[0].formatted_address) {
				val = data.results[0].formatted_address;
				jQuery("select#region option").each(function(){
					if (val.indexOf(jQuery(this).text()) != -1) {
						jQuery(this).attr("selected", "selected");
						return false;
					}
				});
			}

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
				} else if (component.types[0] == 'point_of_interest') {
					//remove point of intrest from front of formatted_address
					var current_value = jQuery("input#formatted_address").val();
					if (current_value.substr(0, component.long_name.length + 2) == component.long_name + ', ') {
						jQuery("input#formatted_address").val(current_value.substr(component.long_name.length + 2));
					}
				}
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