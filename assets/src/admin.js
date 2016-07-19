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
	
	//types checkboxes: ensure not both open and closed
	$('body.post-type-meetings form#post').on('change', 'input[name="types[]"]', function() {
		if ($('body.post-type-meetings form#post input[name="types[]"][value="C"]').prop('checked') && 
			$('body.post-type-meetings form#post input[name="types[]"][value="O"]').prop('checked')) {
			if ($(this).val() == 'C') {
				$('body.post-type-meetings form#post input[name="types[]"][value="O"]').prop('checked', false);
			} else {
				$('body.post-type-meetings form#post input[name="types[]"][value="C"]').prop('checked', false);
			}
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

		jQuery.getJSON('https://maps.googleapis.com/maps/api/geocode/json', { address: val, key: myAjax.google_api_key }, function(data){

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
			
			//save address
			var address = parseAddressComponents(data.results[0].address_components);
			$('input#address').val(address.address);
			$('input#city').val(address.city);
			$('input#state').val(address.state);
			$('input#postal_code').val(address.postal_code);
			$('input#country').val(address.country);
			$('input#formatted_address').val(address.formatted);
			
			//check if location with same address is already in the system, populate form
			jQuery.getJSON(myAjax.ajaxurl + '?action=address', { formatted_address: address.formatted }, function(data){
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
	
	//parse a google address components response into an array of useful values
	function parseAddressComponents(components) {
		var point_of_interest, neighborhood, address, city, state, postal_code, country;
		var formatted = [];

		//get address, city and state
		for (var i = 0; i < components.length; i++) {
			var c = components[i];
			if (!c.types.length || c.types[0] == 'point_of_interest') {
				//in case address is empty
				point_of_interest = c.short_name;
			} else if (c.types.indexOf('neighborhood') !== -1) {
				neighborhood = c.short_name;
			} else if (c.types.indexOf('street_number') !== -1) {
				address = c.long_name;
			} else if (c.types.indexOf('route') !== -1) {
				//append street name
				address = (address) ? address + ' ' + c.long_name : c.long_name;
			} else if (c.types.indexOf('locality') !== -1) {
				city = c.long_name;
			} else if (c.types.indexOf('sublocality') !== -1) {
				if (!city) city = c.long_name;
			} else if (c.types.indexOf('administrative_area_level_3') !== -1) {
				if (!city) city = c.long_name;
			} else if (c.types.indexOf('administrative_area_level_1') !== -1) {
				state = c.short_name;
			} else if (c.types.indexOf('postal_code') !== -1) {
				postal_code = c.short_name;
			} else if (c.types.indexOf('country') !== -1) {
				country = c.short_name;
			}
		}

		if (!address && point_of_interest) address = point_of_interest;
		
		if (!address && neighborhood) address = neighborhood;
		
		if (address) formatted[formatted.length] = address;
		
		if (city) formatted[formatted.length] = city;
		
		//state and postal code should be part of the same unit if possible
		if (state) {
			if (address && postal_code) {
				formatted[formatted.length] = state + ' ' + postal_code;
			} else {
				formatted[formatted.length] = state;
			}
		} else if (postal_code) {
			formatted[formatted.length] = postal_code;
		}
		
		if (country) formatted[formatted.length] = country;

		return {
			address: address,
			city: city,
			state: state,
			postal_code: postal_code,
			country: country,
			formatted: formatted.join(', ')
		}
	
	}
});