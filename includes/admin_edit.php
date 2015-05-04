<?php

//ajax for the typeahead
add_action('wp_ajax_location', function(){
	$locations = get_posts('post_type=locations&numberposts=-1');
	$results = array();
    foreach ($locations as $location) {
        $title  = get_the_title($location->ID);
        $tsml_custom = get_post_meta($location->ID);
        $results[] = array(
            'value'				=> html_entity_decode($title),
            'formatted_address'	=> $tsml_custom['formatted_address'][0],
            'latitude'			=> $tsml_custom['latitude'][0],
            'longitude'			=> $tsml_custom['longitude'][0],
            'address'			=> $tsml_custom['address'][0],
            'city'				=> $tsml_custom['city'][0],
            'state'				=> $tsml_custom['state'][0],
            'region'			=> $tsml_custom['region'][0],
            'tokens'			=> array_values(array_unique(explode(' ', str_replace(',', '', $title . ' ' . $tsml_custom['address'][0])))),
        );
	}
	wp_send_json($results);
});

//edit page
add_action('admin_init', function(){

	tsml_assets('admin');
	
	remove_meta_box('regiondiv', 'meetings', 'side');

	add_meta_box('info', 'General Info', function(){
		global $post, $tsml_days, $tsml_types, $tsml_custom, $tsml_nonce;

		//get post metadata
		$tsml_custom 	= get_post_custom($post->ID);
		$tsml_custom['types'] = unserialize($tsml_custom['types'][0]);
		if (!is_array($tsml_custom['types'])) $tsml_custom['types'] = array();

		//nonce field
		wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
		?>
		<div class="meta_form_row">
			<label for="day">Day</label>
			<select name="day" id="day">
				<?php foreach ($tsml_days as $key=>$day) {?>
				<option value="<?php echo $key?>"<?php selected($tsml_custom['day'][0], $key)?>><?php echo $day?></option>
				<?php }?>
			</select>
		</div>
		<div class="meta_form_row">
			<label for="time">Time</label>
			<input type="time" name="time" id="time" value="<?php echo $tsml_custom['time'][0]?>" step="900">
		</div>
		<div class="meta_form_row">
			<label for="tags">Types</label>
			<div class="checkboxes">
				<?php foreach ($tsml_types as $key=>$type) {?>
					<label>
						<input type="checkbox" name="types[]" value="<?php echo $key?>" <?php if (in_array($key, $tsml_custom['types'])) {?> checked="checked"<?php }?>>
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
		global $post;
		$parent = get_post($post->post_parent);
		$tsml_custom = get_post_meta($post->post_parent);
		?>
		<div class="meta_form_row typeahead">
			<label for="location">Location</label>
			<input type="text" name="location" id="location" value="<?php echo $parent->post_title?>">
		</div>
		<div class="meta_form_row">
			<label for="formatted_address">Address</label>
			<input type="text" name="formatted_address" id="formatted_address" value="<?php echo $tsml_custom['formatted_address'][0]?>">
			<input type="hidden" name="address" id="address" value="<?php echo $tsml_custom['address'][0]?>">
			<input type="hidden" name="city" id="city" value="<?php echo $tsml_custom['city'][0]?>">
			<input type="hidden" name="state" id="state" value="<?php echo $tsml_custom['state'][0]?>">
			<input type="hidden" name="country" id="country" value="<?php echo $tsml_custom['country'][0]?>">
			<input type="hidden" name="latitude" id="latitude" value="<?php echo $tsml_custom['latitude'][0]?>">
			<input type="hidden" name="longitude" id="longitude" value="<?php echo $tsml_custom['longitude'][0]?>">
		</div>
		<div class="meta_form_row">
			<label for="region">Region</label>
			<?php wp_dropdown_categories(array(
				'name' => 'region',
				'taxonomy' => 'region',
				'hierarchical' => true,
				'hide_empty' => false,
				'orderby' => 'name',
				'selected' => $tsml_custom['region'][0],
			)); ?>
		</div>
		<div class="meta_form_row">
			<label for="map">Map</label>
			<div id="map"></div>
		</div>
		<?php
	}, 'meetings', 'normal', 'low');
});
