<?php

//ajax for the typeahead
add_action('wp_ajax_location', function(){
	$locations = get_posts('post_type=locations&numberposts=-1');
	$results = array();
    foreach ($locations as $location) {
        $title  = get_the_title($location->ID);
        $custom = get_post_meta($location->ID);
        $results[] = array(
            'value'				=> html_entity_decode($title),
            'formatted_address'	=> $custom['formatted_address'][0],
            'latitude'			=> $custom['latitude'][0],
            'longitude'			=> $custom['longitude'][0],
            'address'			=> $custom['address'][0],
            'city'				=> $custom['city'][0],
            'state'				=> $custom['state'][0],
            'region'			=> $custom['region'][0],
            'tokens'			=> array_values(array_unique(explode(' ', str_replace(',', '', $title . ' ' . $custom['address'][0])))),
        );
	}
	wp_send_json($results);
});

//edit page
add_action('admin_init', function(){

	wp_enqueue_script('google_maps_api', 'http://maps.google.com/maps/api/js?sensor=false');
	wp_enqueue_style('meetings_meta_style', plugin_dir_url(__FILE__) . '../css/admin.css');
	wp_enqueue_script('meetings_admin_js', plugin_dir_url(__FILE__) . '../js/admin_edit.js', array('jquery'), '', true);
	wp_enqueue_script('typeahead_js', plugin_dir_url(__FILE__) . '../js/typeahead.bundle.js', array('jquery'), '', true);
	wp_localize_script('meetings_admin_js', 'myAjax', array('ajaxurl'=>admin_url('admin-ajax.php')));        

	remove_meta_box('tagsdiv-region', 'meetings', 'side' );

	add_meta_box('info', 'General Info', function(){
		global $post, $days, $types, $custom;

		//get post metadata
		$custom 	= get_post_custom($post->ID);
		$custom['types'] = unserialize($custom['types'][0]);
		if (!is_array($custom['types'])) $custom['types'] = array();
		?>
		<div class="meta_form_row">
			<label for="day">Day</label>
			<select name="day" id="day">
				<?php foreach ($days as $key=>$day) {?>
				<option value="<?php echo $key?>"<?php selected($custom['day'][0], $key)?>><?php echo $day?></option>
				<?php }?>
			</select>
		</div>
		<div class="meta_form_row">
			<label for="time">Time</label>
			<input type="time" name="time" id="time" value="<?php echo $custom['time'][0]?>" step="900">
		</div>
		<div class="meta_form_row">
			<label for="tags">Types</label>
			<div class="checkboxes">
				<?php foreach ($types as $key=>$type) {?>
					<label>
						<input type="checkbox" name="types[]" value="<?php echo $key?>" <?php if (in_array($key, $custom['types'])) {?> checked="checked"<?php }?>>
						<?php echo $type?>
					</label>
				<?php }?>
			</div>
		</div>
		<div class="meta_form_row">
			<label for="content">Notes</label>
			<textarea name="content" id="content" placeholder="eg. Birthday speaker meeting last Saturday of the month"><?php echo $post->post_content?></textarea>
		</div>
		<?php
	}, 'meetings', 'normal', 'low');

	add_meta_box('location', 'Location', function(){
		global $post, $regions;
		$parent = get_post($post->post_parent);
		$custom = get_post_meta($post->post_parent);
		?>
		<div class="meta_form_row typeahead">
			<label for="location">Location</label>
			<input type="text" name="location" id="location" value="<?php echo $parent->post_title?>">
		</div>
		<div class="meta_form_row">
			<label for="formatted_address">Address</label>
			<input type="text" name="formatted_address" id="formatted_address" value="<?php echo $custom['formatted_address'][0]?>">
			<input type="hidden" name="address" id="address" value="<?php echo $custom['address'][0]?>">
			<input type="hidden" name="city" id="city" value="<?php echo $custom['city'][0]?>">
			<input type="hidden" name="state" id="state" value="<?php echo $custom['state'][0]?>">
			<input type="hidden" name="latitude" id="latitude" value="<?php echo $custom['latitude'][0]?>">
			<input type="hidden" name="longitude" id="longitude" value="<?php echo $custom['longitude'][0]?>">
		</div>
		<div class="meta_form_row">
			<label for="region">Region</label>
			<select name="region" id="region">
				<?php foreach ($regions as $key=>$region) {?>
					<option value="<?php echo $key?>" <?php selected($custom['region'][0], $key)?>><?php echo $region?></option>
				<?php }?>
			</select>
		</div>
		<div class="meta_form_row">
			<label for="map">Map</label>
			<div id="map"></div>
		</div>
		<?php
	}, 'meetings', 'normal', 'low');
});
