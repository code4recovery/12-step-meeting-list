jQuery(function($){

	//day picker
	$('select#day').change(function(){
		var val = $(this).val();
		var $time = $('input#time');
		if (val) {
			$('input#time').removeAttr('disabled');
			if (!$time.val() && $time.attr('data-value')) $time.val($time.attr('data-value'));
		} else {
			$time.attr('data-value', $time.val()).val('').attr('disabled', 'disabled');
		}
	});
	
	//delete email contact
	$('#get_feedback table span').click(function(){
		$(this).parent().submit();
	});

	//location typeahead
	var locations = new Bloodhound({
		datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value'),
		queryTokenizer: Bloodhound.tokenizers.whitespace,
		prefetch: {
			url: myAjax.ajaxurl + '?action=location',
		}
	});
	locations.initialize();
	$('input#location').typeahead(null, {
		displayKey: 'value',
		source: locations.ttAdapter()
	}).on('typeahead:autocompleted', function($e, datum){
		$('input[name=formatted_address]').val(datum.formatted_address);
		$('input[name=latitude]').val(datum.latitude);
		$('input[name=longitude]').val(datum.longitude);
		$('input[name=address]').val(datum.address);
		$('input[name=city]').val(datum.city);
		$('input[name=state]').val(datum.state);
		$('input[name=postal_code]').val(datum.postal_code);
		$('input[name=country]').val(datum.country);
		$('select[name=region] option[value=' + datum.region + ']').prop('selected', true);
		$('textarea[name=location_notes]').val(datum.notes);
		setMap(datum.latitude, datum.longitude);
	});

	//group typeahead
	var groups = new Bloodhound({
		datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value'),
		queryTokenizer: Bloodhound.tokenizers.whitespace,
		prefetch: {
			url: myAjax.ajaxurl + '?action=tsml_group',
			ttl: 10
		}
	});
	groups.initialize();
	$('input#group').typeahead(null, {
		displayKey: 'value',
		source: groups.ttAdapter()
	}).on('typeahead:autocompleted', function($e, datum){
		$('input[name=contact_1_name]').val(datum.contact_1_name);
		$('input[name=contact_1_email]').val(datum.contact_1_email);
		$('input[name=contact_1_phone]').val(datum.contact_1_phone);
		$('input[name=contact_2_name]').val(datum.contact_2_name);
		$('input[name=contact_2_email]').val(datum.contact_2_email);
		$('input[name=contact_2_phone]').val(datum.contact_2_phone);
		$('input[name=contact_3_name]').val(datum.contact_3_name);
		$('input[name=contact_3_email]').val(datum.contact_3_email);
		$('input[name=contact_3_phone]').val(datum.contact_3_phone);
		$('textarea[name=group_notes]').val(datum.notes);
	});

	/*timepicker
	$('input[type=time]').timepicker({
		timeFormat: 'hh:mm tt',
		stepMinute: 15
	});*/
	
	$('input#group').change(function(){
		$('div#group .apply_group_to_location').removeClass('hidden');
	});

	$('form#post').submit(function(){
		if (!$('select#day').val()) {
			$('input#time').val(''); //double check is empty
			return true; //by appointment, don't check time
		}
		var timeVal = $('input#time').val();
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
	$('input#formatted_address').blur(function(){

		//setting new form
		$('input#address').val('');
		$('input#city').val('');
		$('input#state').val('');
		$('input#zip').val('');
		$('input#country').val('');
		$('input#latitude').val('');
		$('input#longitude').val('');

		var val = $(this).val().trim();
		
		if (!val.length) {
			setMap();
			$('input#formatted_address').val(''); //clear any spaces
			return;
		}

		jQuery.getJSON('https://maps.googleapis.com/maps/api/geocode/json', { address: val, key: 'AIzaSyBRM9LTED2PgK91UL4qRmiWHVq0TI686tc', sensor: false }, function(data){

			//check status first, eg REQUEST_DENIED, ZERO_RESULTS
			if (data.status != 'OK') return;
			
			//set lat + lng
			var latitude = data.results[0].geometry.location.lat;
			var longitude = data.results[0].geometry.location.lng;
			$('input#latitude').val(latitude);
			$('input#longitude').val(longitude);
			setMap(latitude, longitude);

			//guess region if not set
			var region_id = false;
			if (!$('select#region option[selected]').size()) {
				val = data.results[0].formatted_address;
				$('select#region option').each(function(){
					var region_name = $(this).text().replace('&nbsp;', '').trim();
					if (val.indexOf(region_name) != -1) region_id = $(this).attr('value');
				});
			}
			
			var point_of_interest;
			var city = false;

			//get address, city and state
			for (var i = 0; i < data.results[0].address_components.length; i++) {
				var component = data.results[0].address_components[i];
				if (!component.types.length || component.types[0] == 'point_of_interest') {
					//record the point of interest in case address is empty
					point_of_interest = component.short_name;
				} else if (component.types.indexOf('street_number') !== -1) {
					//set address as street number
					$('input#address').val(component.long_name);
				} else if (component.types.indexOf('route') !== -1) {
					//append street name
					var address = $('input#address').val() + ' ' + component.long_name;
					$('input#address').val(address.trim());
				} else if (component.types.indexOf('locality') !== -1) {
					//set city
					city = component.long_name;
				} else if (component.types.indexOf('sublocality') !== -1) {
					//set city
					if (!city) city = component.long_name;
				} else if (component.types.indexOf('administrative_area_level_3') !== -1) {
					//set city
					if (!city) city = component.long_name;
				} else if (component.types.indexOf('administrative_area_level_1') !== -1) {
					//set state
					$('input#state').val(component.short_name);
				} else if (component.types.indexOf('postal_code') !== -1) {
					//set ZIP
					$('input#postal_code').val(component.short_name);
				} else if (component.types.indexOf('country') !== -1) {
					//set country
					$('input#country').val(component.short_name);
				}
			}

			//set city
			$('input#city').val(city);
			
			//set address to point of interest if empty
			if (!$('input#address').val().length) $('input#address').val(point_of_interest);
			
			//build formatted address from components
			var formatted_address = [];

			var address = $('input#address').val();
			if (address.length) formatted_address[formatted_address.length] = address;
			
			var city = $('input#city').val();
			if (city.length) formatted_address[formatted_address.length] = city;
			
			var state_code = $('input#state').val() + ' ' + $('input#postal_code').val();
			state_code = state_code.trim();
			if (state_code.length) formatted_address[formatted_address.length] = state_code;
			
			var country = $('input#country').val();
			if (country.length) formatted_address[formatted_address.length] = country;

			var formatted_address = formatted_address.join(', ');

			//update address field with corrected address
			$('input#formatted_address').val(formatted_address);
			
			//check if location with same address is already in the system, populate form
			jQuery.getJSON(myAjax.ajaxurl + '?action=address', { formatted_address: formatted_address }, function(data){
				if (data) {
					$('input[name=location]').val(data.location);
					$('select[name=region] option').prop('selected', false);
					$('select[name=region] option[value=' + data.region + ']').prop('selected', true);
					$('textarea[name=location_notes]').val(data.location_notes);
				} else if (region_id) {
					//set to guessed region earlier
					$('select[name=region] option').prop('selected', false);
					$('select[name=region] option[value=' + region_id + ']').prop('selected', true);
				}
			});

		});
	});

	if ($('input#formatted_address').val()) $('input#formatted_address').blur();

	function setMap(latitude, longitude) {
		if (!latitude || !longitude) {
			$('div#map').html('');
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