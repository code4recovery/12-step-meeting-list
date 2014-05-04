jQuery(function(){

	//typeahead
	var locations = new Bloodhound({
		datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value'),
		queryTokenizer: Bloodhound.tokenizers.whitespace,
		prefetch: myAjax.ajaxurl + '?action=location'
	});
	locations.initialize();
	jQuery('input#location').typeahead(null, {
		name: 'locations',
		displayKey: 'value',
		source: locations.ttAdapter()
	}).on('typeahead:autocompleted', function($e, datum){
		jQuery("input[name=address]").val(datum.address);
		jQuery("input[name=latitude]").val(datum.latitude);
		jQuery("input[name=longitude]").val(datum.longitude);
		jQuery("select[name=region] option[value='" + datum.region + "']").attr("selected", "selected");
		setMap(datum.latitude, datum.longitude)
	});

	//address / map
	jQuery("input#address").blur(function(){
		var val = jQuery(this).val();
		if (!val.length) {
			setMap();
			jQuery("input#latitude").val("");
			jQuery("input#longitude").val("");
			return;
		}

		jQuery.getJSON("https://maps.googleapis.com/maps/api/geocode/json", { address: val, sensor : false }, function(data){
			jQuery("input#address").val(data.results[0].formatted_address);
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