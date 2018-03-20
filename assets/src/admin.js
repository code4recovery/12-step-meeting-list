jQuery(function($){

	//recursively run import
	function runImport() {

		$.getJSON(tsml.ajaxurl + '?action=tsml_import', function(data){

			//update progress bar
			var $progress = $('body.tsml_meeting_page_import div#tsml_import_progress');
			var total = $progress.attr('data-total');
			var percentage = (Math.floor(((total - data.remaining) / total) * 95) + 5) + '%';
			$progress.find('.progress-bar').css({width:percentage}).text(percentage);
			
			//update the counts on the right
			var $counts = $('#tsml_counts');
			var types = ['meetings', 'locations', 'groups', 'regions'];
			for (var i = 0; i < types.length; i++) {
				var type = types[i];
				if (data.counts[type] > 0) {
					if ($counts.hasClass('hidden')) $counts.removeClass('hidden');
					$li = $counts.find('li.' + type);
					if ($li.hasClass('hidden')) $li.removeClass('hidden');
					if ($li.text(data.descriptions[type]));
				}
			}
			
			//update the counts in the data sources
			if (data.data_sources) {
				$.each(data.data_sources, function(url, props) {
					$('tr[data-source="' + url + '"] td.count_meetings').html(props.count_meetings);
				});
			}
			
			//if there are errors, display message and append them to it
			if (data.errors.length) {
				$errors = $('#tsml_import_errors');
				if ($errors.hasClass('hidden')) $errors.removeClass('hidden');
				for (var i = 0; i < data.errors.length; i++) $errors.append(data.errors[i]);
			}
			
			//console.log('geocoded ' + data.geocoded)
			
			//if there are more to import, go again
			if (data.remaining) runImport();
		});
	}

	//import & settings page
	if ($('div#tsml_import_progress').length) {
		$('div#tsml_import_progress div.progress-bar').css({width:'5%'});
		$('#tsml_import_errors').addClass('hidden');
		runImport();
	}
	
	//delete data source or email contact
	$('table form span').click(function(){
		$(this).parent().submit();
	});

	//meeting add / edit page
	var $post_type = $('input#post_type');
	if ($post_type.size() && $post_type.val() == 'tsml_meeting') {

		//make sure geocoding is finished (basic form validation)
		var form_valid = true;

		function formIsValid() {
			form_valid = true;
			$('#publish').removeClass('disabled');
		}

		function formIsNotValid() {
			form_valid = false;
			$('#publish').addClass('disabled');
		}

		$('form#post').submit(function(){
			return form_valid;
		});

		//show more types
		$('.toggle_more').on('click', 'a', function(e){
			e.preventDefault();
			$(this).closest('.checkboxes').toggleClass('showing_more');
		});

		//day picker
		$('select#day').change(function(){
			var val = $(this).val();
			var $time = $('input#time');
			var $end_time = $('input#end_time');
			if (val) {
				$time.removeAttr('disabled');
				$end_time.removeAttr('disabled');
				var newTime = (!$time.val() && $time.attr('data-value')) ? $time.attr('data-value') : '00:00';
				var newEndTime = (!$end_time.val() && $end_time.attr('data-value')) ? $end_time.attr('data-value') : '01:00';
				$time.val(newTime).timepicker();
				$end_time.val(newEndTime).timepicker();
			} else {
				$time.attr('data-value', $time.val()).val('').attr('disabled', 'disabled');
				$end_time.attr('data-value', $end_time.val()).val('').attr('disabled', 'disabled');
			}
		});
		
		//time picker
		$('input.time').timepicker();
		
		//auto-suggest end time (todo maybe think about using moment for this)
		$('input#time').change(function(){

			//get time parts
			var parts = $(this).val().split(':');
			if (parts.length !== 2) return;
			var hours = parts[0] - 0;
			var parts = parts[1].split(' ');
			if (parts.length !== 2) return;
			var minutes = parts[0];
			var ampm = parts[1];

			//increment hour
			if (hours == 12) {
				hours = 1;
			} else {
				hours++;
				if (hours == 12) {
					ampm = (ampm == 'am') ? 'pm' : 'am';
				}
			}
			hours += '';

			//set field value
			$('input#end_time').val(hours + ':' + minutes + ' ' + ampm);
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
		
		//location typeahead
		var tsml_locations = new Bloodhound({
			datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value'),
			queryTokenizer: Bloodhound.tokenizers.whitespace,
			prefetch: {
				url: tsml.ajaxurl + '?action=tsml_locations',
				cache: false
			}
		});

		$('input#location').typeahead(null, {
			displayKey: 'value',
			source: tsml_locations
		}).on('typeahead:autocompleted typeahead:selected', function($e, location){
			$('input[name=formatted_address]').val(location.formatted_address).trigger('change');
			$('input[name=latitude]').val(location.latitude);
			$('input[name=longitude]').val(location.longitude);
			$('select[name=region] option[value=' + location.region + ']').prop('selected', true);
			$('textarea[name=location_notes]').val(location.notes);
		});

		//group typeahead
		var tsml_groups = new Bloodhound({
			datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value'),
			queryTokenizer: Bloodhound.tokenizers.whitespace,
			prefetch: {
				url: tsml.ajaxurl + '?action=tsml_groups',
				ttl: 10
			}
		});

		$('input#group').typeahead(null, {
			displayKey: 'value',
			source: tsml_groups
		}).on('typeahead:autocompleted typeahead:selected', function($e, group){
	        $('input[name=website]').val(group.website);
	        $('input[name=email]').val(group.email);
	        $('input[name=phone]').val(group.phone);        
			$('input[name=contact_1_name]').val(group.contact_1_name);
			$('input[name=contact_1_email]').val(group.contact_1_email);
			$('input[name=contact_1_phone]').val(group.contact_1_phone);
			$('input[name=contact_2_name]').val(group.contact_2_name);
			$('input[name=contact_2_email]').val(group.contact_2_email);
			$('input[name=contact_2_phone]').val(group.contact_2_phone);
			$('input[name=contact_3_name]').val(group.contact_3_name);
			$('input[name=contact_3_email]').val(group.contact_3_email);
			$('input[name=contact_3_phone]').val(group.contact_3_phone);
			$('input[name=last_contact]').val(group.last_contact);
			$('textarea[name=group_notes]').val(group.notes);
		});

		$('input[name="group_status"]').change(function(){
			if ($(this).val() == 'individual') {
				$('input#group').closest('.meta_form_row').addClass('hidden');
				$('input#group').val('');
				$('textarea#group_notes').closest('.meta_form_row').addClass('hidden');
				$('textarea#group_notes').val('');
				$('select#district').closest('.meta_form_row').addClass('hidden');
				$('select#district').val('');
				$('.apply_group_to_location').addClass('hidden');
			} else {
				$('input#group').closest('.meta_form_row').removeClass('hidden');
				$('textarea#group_notes').closest('.meta_form_row').removeClass('hidden');
				$('select#district').closest('.meta_form_row').removeClass('hidden');
			}
		});

		$('input#group').change(function(){
			$('div#group .apply_group_to_location').removeClass('hidden');
		});

		//address / map
		$('input#formatted_address').change(function(){

			//disable submit until geocoding completes
			formIsNotValid();

			//setting new form
			$('input#latitude').val('');
			$('input#longitude').val('');

			var val = $(this).val().trim();
			
			if (!val.length) {
				setMap();
				$('input#formatted_address').val(''); //clear any spaces
				formIsValid();
				return;
			}

			$.getJSON('https://maps.googleapis.com/maps/api/geocode/json', { 
					address: val, 
					key: tsml.google_api_key,
					language: tsml.language
				}, function(data){

				//check status first, eg REQUEST_DENIED, ZERO_RESULTS
				if (data.status != 'OK') return;
							
				var google_overrides = $.parseJSON(tsml.google_overrides);
				
				//check if there is an override, because the Google Geocoding API is not always right
				var address = (typeof google_overrides[data.results[0].formatted_address] == 'undefined') ? {
					formatted_address: data.results[0].formatted_address,
					latitude: data.results[0].geometry.location.lat,
					longitude: data.results[0].geometry.location.lng
				} : address = google_overrides[data.results[0].formatted_address];
				
				//set lat + lng
				$('input#latitude').val(address.latitude);
				$('input#longitude').val(address.longitude);
				setMap(address.latitude, address.longitude);

				//guess region if not set
				var region_id = false;
				if (!$('select#region option[selected]').length) {
					$('select#region option').each(function(){
						var region_name = $(this).text().replace('&nbsp;', '').trim();
						if (address.formatted_address.indexOf(region_name) != -1) region_id = $(this).attr('value');
					});
				}
				
				//save address and check apply change box status
				$('input#formatted_address').val(address.formatted_address).trigger('keyup');
				
				//check if location with same address is already in the system, populate form
				$.getJSON(tsml.ajaxurl + '?action=address', { formatted_address: address.formatted_address }, function(data){
					if (data) {
						$('input[name=location]').val(data.location);
						if (data.region != $('select[name=region]').val()) {
							$('select[name=region] option').prop('selected', false);
							$('select[name=region] option[value=' + data.region + ']').prop('selected', true);
						}
						$('textarea[name=location_notes]').val(data.location_notes);
					}
					
					if ((!data || !data.region) && !$('select#region option[selected]').length && region_id) {
						//set to guessed region earlier
						$('select[name=region] option[value=' + region_id + ']').prop('selected', true);
					}

					//form is ok to submit again
					formIsValid();
				});

			});
		}).keyup(function(){

			//disable submit, will need to do geocoding on change
			var original_address = $(this).attr('data-original-value');
			if (original_address != $(this).val()) {
				formIsNotValid(); 
			}

			//unhide apply address to location?
			if ($('div.apply_address_to_location').size()) {
				if (original_address.length && (original_address != $(this).val())) {
					$('div.apply_address_to_location').removeClass('hidden');
				} else {
					$('div.apply_address_to_location').addClass('hidden');
				}
			}
		});			

		//when page loads, run lookup
		if ($('input#formatted_address').val()) $('input#formatted_address').trigger('change');

		function setMap(latitude, longitude) {
			if (!latitude || !longitude) {
				$('div#map').html('');
				return;
			}
			var myLatlng = new google.maps.LatLng(latitude, longitude);
			var map = new google.maps.Map(document.getElementById('map'), { 
				zoom: 16, 
				zoomControl: true,
				scrollwheel: false,
				streetViewControl: false,
				mapTypeControl: false,
				fullscreenControl: false,
				center: myLatlng
			});
			var marker = new google.maps.Marker({ position: myLatlng, map: map });
		}
	}

});