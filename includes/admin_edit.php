<?php

//ajax for the typeahead
add_action('wp_ajax_location', function(){
	$locations = get_posts('post_type=locations&numberposts=-1');
	$results = array();
    foreach ($locations as $location) {
        $title  = get_the_title($location->ID);
        $md_custom = get_post_meta($location->ID);
        $results[] = array(
            'value'				=> html_entity_decode($title),
            'formatted_address'	=> $md_custom['formatted_address'][0],
            'latitude'			=> $md_custom['latitude'][0],
            'longitude'			=> $md_custom['longitude'][0],
            'address'			=> $md_custom['address'][0],
            'city'				=> $md_custom['city'][0],
            'state'				=> $md_custom['state'][0],
            'region'			=> $md_custom['region'][0],
            'tokens'			=> array_values(array_unique(explode(' ', str_replace(',', '', $title . ' ' . $md_custom['address'][0])))),
        );
	}
	wp_send_json($results);
});

//edit page
add_action('admin_init', function(){

	md_assets('admin');
	
	remove_meta_box('tagsdiv-region', 'meetings', 'side' );

	add_meta_box('info', 'General Info', function(){
		global $post, $md_days, $md_types, $md_custom, $md_nonce;

		//get post metadata
		$md_custom 	= get_post_custom($post->ID);
		$md_custom['types'] = unserialize($md_custom['types'][0]);
		if (!is_array($md_custom['types'])) $md_custom['types'] = array();

		//nonce field
		wp_nonce_field($md_nonce, 'md_nonce', false);
		?>
		<div class="meta_form_row">
			<label for="day">Day</label>
			<select name="day" id="day">
				<?php foreach ($md_days as $key=>$day) {?>
				<option value="<?php echo $key?>"<?php selected($md_custom['day'][0], $key)?>><?php echo $day?></option>
				<?php }?>
			</select>
		</div>
		<div class="meta_form_row">
			<label for="time">Time</label>
			<input type="time" name="time" id="time" value="<?php echo $md_custom['time'][0]?>" step="900">
		</div>
		<div class="meta_form_row">
			<label for="tags">Types</label>
			<div class="checkboxes">
				<?php foreach ($md_types as $key=>$type) {?>
					<label>
						<input type="checkbox" name="types[]" value="<?php echo $key?>" <?php if (in_array($key, $md_custom['types'])) {?> checked="checked"<?php }?>>
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
		global $post, $md_regions;
		$parent = get_post($post->post_parent);
		$md_custom = get_post_meta($post->post_parent);
		?>
		<div class="meta_form_row typeahead">
			<label for="location">Location</label>
			<input type="text" name="location" id="location" value="<?php echo $parent->post_title?>">
		</div>
		<div class="meta_form_row">
			<label for="formatted_address">Address</label>
			<input type="text" name="formatted_address" id="formatted_address" value="<?php echo $md_custom['formatted_address'][0]?>">
			<input type="hidden" name="address" id="address" value="<?php echo $md_custom['address'][0]?>">
			<input type="hidden" name="city" id="city" value="<?php echo $md_custom['city'][0]?>">
			<input type="hidden" name="state" id="state" value="<?php echo $md_custom['state'][0]?>">
			<input type="hidden" name="country" id="country" value="<?php echo $md_custom['country'][0]?>">
			<input type="hidden" name="latitude" id="latitude" value="<?php echo $md_custom['latitude'][0]?>">
			<input type="hidden" name="longitude" id="longitude" value="<?php echo $md_custom['longitude'][0]?>">
		</div>
		<div class="meta_form_row">
			<label for="region">Region</label>
			<select name="region" id="region">
				<?php foreach ($md_regions as $key=>$region) {?>
					<option value="<?php echo $key?>" <?php selected($md_custom['region'][0], $key)?>><?php echo $region?></option>
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
