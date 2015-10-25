jQuery(function(){

	//day picker
	jQuery('select#day').change(function(){
		var val = jQuery(this).val();
		var $time = jQuery('input#time');
		if (val) {
			jQuery('input#time').removeAttr('disabled');
			if (!$time.val() && $time.attr('data-value')) $time.val($time.attr('data-value'));
		} else {
			$time.attr('data-value', $time.val()).val('').attr('disabled', 'disabled');
		}
	});

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
		jQuery('input[name=formatted_address]').val(datum.formatted_address);
		jQuery('input[name=latitude]').val(datum.latitude);
		jQuery('input[name=longitude]').val(datum.longitude);
		jQuery('input[name=address]').val(datum.address);
		jQuery('input[name=city]').val(datum.city);
		jQuery('input[name=state]').val(datum.state);
		jQuery('input[name=postal_code]').val(datum.postal_code);
		jQuery('input[name=country]').val(datum.country);
		jQuery('select[name=region] option[value=' + datum.region + ']').prop('selected', true);
		jQuery('input[name=contact_1_name]').val(datum.contact_1_name);
		jQuery('input[name=contact_1_email]').val(datum.contact_1_email);
		jQuery('input[name=contact_1_phone]').val(datum.contact_1_phone);
		jQuery('input[name=contact_2_name]').val(datum.contact_2_name);
		jQuery('input[name=contact_2_email]').val(datum.contact_2_email);
		jQuery('input[name=contact_2_phone]').val(datum.contact_2_phone);
		jQuery('input[name=contact_3_name]').val(datum.contact_3_name);
		jQuery('input[name=contact_3_email]').val(datum.contact_3_email);
		jQuery('input[name=contact_3_phone]').val(datum.contact_3_phone);
		jQuery('textarea[name=location_notes]').val(datum.notes);
		setMap(datum.latitude, datum.longitude);
	});

	/*timepicker
	jQuery('input[type=time]').timepicker({
		timeFormat: 'hh:mm tt',
		stepMinute: 15
	});*/

	jQuery('form#post').submit(function(){
		if (!jQuery('select#day').val()) {
			jQuery('input#time').val(''); //double check is empty
			return true; //by appointment, don't check time
		}
		var timeVal = jQuery('input#time').val();
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
	jQuery('input#formatted_address').blur(function(){

		//setting new form
		jQuery('input#address').val('');
		jQuery('input#city').val('');
		jQuery('input#state').val('');
		jQuery('input#zip').val('');
		jQuery('input#country').val('');
		jQuery('input#latitude').val('');
		jQuery('input#longitude').val('');

		var val = jQuery(this).val().trim();
		
		if (!val.length) {
			setMap();
			jQuery('input#formatted_address').val(''); //clear any spaces
			return;
		}

		jQuery.getJSON('https://maps.googleapis.com/maps/api/geocode/json', { address: val, key: 'AIzaSyBRM9LTED2PgK91UL4qRmiWHVq0TI686tc', sensor: false }, function(data){

			//set lat + lng
			var latitude = data.results[0].geometry.location.lat;
			var longitude = data.results[0].geometry.location.lng;
			jQuery('input#latitude').val(latitude);
			jQuery('input#longitude').val(longitude);
			setMap(latitude, longitude);

			//guess region if not set
			if (!jQuery('select#region option[selected]').size()) {
				val = data.results[0].formatted_address;
				jQuery('select#region option').each(function(){
					var region_name = jQuery(this).text().replace('&nbsp;', '').trim();
					if (val.indexOf(region_name) != -1) {
						jQuery('select#region option').attr('selected', false);
						jQuery(this).attr('selected', 'selected');
					}
				});
			}
						
			//get address, city and state
			for (var i = 0; i < data.results[0].address_components.length; i++) {
				var component = data.results[0].address_components[i];
				var point_of_interest;
				if (!component.types.length || component.types[0] == 'point_of_interest') {
					//record the point of interest in case address is empty
					point_of_interest = component.short_name;
				} else if (component.types.indexOf('street_number') !== -1) {
					//set address as street number
					jQuery('input#address').val(component.long_name);
				} else if (component.types.indexOf('route') !== -1) {
					//append street name
					var address = jQuery('input#address').val() + ' ' + component.long_name;
					jQuery('input#address').val(address.trim());
				} else if (component.types.indexOf('locality') !== -1) {
					//set city
					jQuery('input#city').val(component.long_name);
				} else if (component.types.indexOf('sublocality') !== -1) {
					//set city
					jQuery('input#city').val(component.long_name);
				} else if (component.types.indexOf('administrative_area_level_1') !== -1) {
					//set state
					jQuery('input#state').val(component.short_name);
				} else if (component.types.indexOf('postal_code') !== -1) {
					//set ZIP
					jQuery('input#postal_code').val(component.short_name);
				} else if (component.types.indexOf('country') !== -1) {
					//set country
					jQuery('input#country').val(component.short_name);
				}
				
				//set address to point of interest if empty
				if (!jQuery('input#address').val().length) jQuery('input#address').val(point_of_interest);
				
				//build formatted address from components
				var formatted_address = [];

				var address = jQuery('input#address').val();
				if (address.length) formatted_address[formatted_address.length] = address;
				
				var city = jQuery('input#city').val();
				if (city.length) formatted_address[formatted_address.length] = city;
				
				var state_code = jQuery('input#state').val() + ' ' + jQuery('input#postal_code').val();
				state_code = state_code.trim();
				if (state_code.length) formatted_address[formatted_address.length] = state_code;
				
				var country = jQuery('input#country').val();
				if (country.length) formatted_address[formatted_address.length] = country;
			}

			var formatted_address = formatted_address.join(', ');

			//update address field with corrected address
			jQuery('input#formatted_address').val(formatted_address);
			
			//check if location with same address is already in the system, populate form
			jQuery.getJSON(myAjax.ajaxurl + '?action=address', { formatted_address: formatted_address }, function(data){
				if (data) {
					//console.log(data);
					jQuery('input[name=location]').val(data.location);
					jQuery('textarea[name=location_notes]').val(data.location_notes);
					jQuery('input[name=contact_1_name]').val(data.contact_1_name);
					jQuery('input[name=contact_1_email]').val(data.contact_1_email);
					jQuery('input[name=contact_1_phone]').val(data.contact_1_phone);
					jQuery('input[name=contact_2_name]').val(data.contact_2_name);
					jQuery('input[name=contact_2_email]').val(data.contact_2_email);
					jQuery('input[name=contact_2_phone]').val(data.contact_2_phone);
					jQuery('input[name=contact_3_name]').val(data.contact_3_name);
					jQuery('input[name=contact_3_email]').val(data.contact_3_email);
					jQuery('input[name=contact_3_phone]').val(data.contact_3_phone);
				}
			});

		});
	});

	if (jQuery('input#formatted_address').val()) jQuery('input#formatted_address').blur();

	function setMap(latitude, longitude) {
		if (!latitude || !longitude) {
			jQuery('div#map').html('');
			return;
		}
		var myLatlng = new google.maps.LatLng(latitude, longitude);
		var map = new google.maps.Map(document.getElementById('map'), { 
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