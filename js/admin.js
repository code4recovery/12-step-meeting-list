jQuery(function(){
	jQuery("select[name=location_id]").change(function(){
		if (location_id = jQuery(this).val()) {
			jQuery.post(myAjax.ajaxurl, {action: "get_location", location_id: location_id, dataType: "json"}, function(data) {
				jQuery("input[name=location]").val(data.location);
				jQuery("input[name=address1]").val(data.address1);
				jQuery("input[name=address2]").val(data.address2);
				jQuery("input[name=city]").val(data.city);
				jQuery("select[name=state] option[value='" + data.state + "']").attr("selected", "selected");
				jQuery("select[name=region] option[value='" + data.region + "']").attr("selected", "selected");
			});
		} else {
			jQuery("input[name=location]").val("");
			jQuery("input[name=address1]").val("");
			jQuery("input[name=address2]").val("");
			jQuery("input[name=city]").val("");
			jQuery("select[name=state] option[value='CA']").attr("selected", "selected");
			jQuery("select[name=region] option:first-child").attr("selected", "selected");
		}
	});
});