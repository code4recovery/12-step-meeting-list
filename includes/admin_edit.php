<?php

//ajax for the typeahead
add_action('wp_ajax_location', function(){
	$locations = get_posts('post_type=locations&numberposts=-1');
	$results = array();
    foreach ($locations as $location) {
        $title  = get_the_title($location->ID);
        $location_custom = get_post_meta($location->ID);
        $results[] = array(
            'value'				=> html_entity_decode($title),
            'formatted_address'	=> $location_custom['formatted_address'][0],
            'latitude'			=> $location_custom['latitude'][0],
            'longitude'			=> $location_custom['longitude'][0],
            'address'			=> $location_custom['address'][0],
            'city'				=> $location_custom['city'][0],
            'state'				=> $location_custom['state'][0],
            'postal_code'		=> $location_custom['postal_code'][0],
            'country'			=> $location_custom['country'][0],
            'region'			=> $location_custom['region'][0],
            'tokens'			=> array_values(array_unique(explode(' ', str_replace(',', '', $title . ' ' . $location_custom['address'][0])))),
        );
	}
	wp_send_json($results);
});

//edit page
add_action('admin_init', function(){

	tsml_assets('admin');
	
	remove_meta_box('regiondiv', 'meetings', 'side');
	remove_meta_box('wii_post-box1', 'meetings', 'normal'); //removes weaver ii from east bay site

	add_meta_box('info', 'General Info', function(){
		global $post, $tsml_days, $tsml_types, $tsml_program, $tsml_nonce;

		//get post metadata
		$meeting_custom 	= get_post_custom($post->ID);
		$meeting_custom['types'] = unserialize($meeting_custom['types'][0]);
		if (!is_array($meeting_custom['types'])) $meeting_custom['types'] = array();

		//nonce field
		wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
		?>
		<div class="meta_form_row">
			<label for="day">Day</label>
			<select name="day" id="day">
				<?php foreach ($tsml_days as $key=>$day) {?>
				<option value="<?php echo $key?>"<?php selected($meeting_custom['day'][0], $key)?>><?php echo $day?></option>
				<?php }?>
			</select>
		</div>
		<div class="meta_form_row">
			<label for="time">Time</label>
			<input type="time" name="time" id="time" value="<?php echo $meeting_custom['time'][0]?>">
		</div>
		<div class="meta_form_row">
			<label for="tags">Types</label>
			<div class="checkboxes">
				<?php foreach ($tsml_types[$tsml_program] as $key=>$type) {?>
					<label>
						<input type="checkbox" name="types[]" value="<?php echo $key?>" <?php if (in_array($key, $meeting_custom['types'])) {?> checked="checked"<?php }?>>
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
		global $post, $tsml_days;
		$location = get_post($post->post_parent);
		$location_custom = get_post_meta($post->post_parent);
		$meetings = tsml_get_meetings(array('location_id'=>$location->ID));
		?>
		<div class="meta_form_row typeahead">
			<label for="location">Location</label>
			<input type="text" name="location" id="location" value="<?php echo $location->post_title?>">
		</div>
		<div class="meta_form_row">
			<label for="formatted_address">Address</label>
			<input type="text" name="formatted_address" id="formatted_address" value="<?php echo $location_custom['formatted_address'][0]?>">
			<input type="hidden" name="address" id="address" value="<?php echo $location_custom['address'][0]?>">
			<input type="hidden" name="city" id="city" value="<?php echo $location_custom['city'][0]?>">
			<input type="hidden" name="state" id="state" value="<?php echo $location_custom['state'][0]?>">
			<input type="hidden" name="postal_code" id="postal_code" value="<?php echo $location_custom['postal_code'][0]?>">
			<input type="hidden" name="country" id="country" value="<?php echo $location_custom['country'][0]?>">
			<input type="hidden" name="latitude" id="latitude" value="<?php echo $location_custom['latitude'][0]?>">
			<input type="hidden" name="longitude" id="longitude" value="<?php echo $location_custom['longitude'][0]?>">
		</div>
		<div class="meta_form_row">
			<label for="region">Region</label>
			<?php wp_dropdown_categories(array(
				'name' => 'region',
				'taxonomy' => 'region',
				'hierarchical' => true,
				'hide_empty' => false,
				'orderby' => 'name',
				'selected' => $location_custom['region'][0],
			))?>
		</div>
		<div class="meta_form_row">
			<label>Map</label>
			<div id="map"></div>
		</div>
		<?php if (count($meetings) > 1) {?>
		<div class="meta_form_row">
			<label>Meetings</label>
			<ol>
				<?php foreach ($meetings as $meeting) {
					if ($meeting['id'] != $post->ID) $meeting['name'] = '<a href="' . get_edit_post_link($meeting['id']) . '">' . $meeting['name'] . '</a>';
				?>
				<li><span><?php echo $tsml_days[$meeting['day']]?>, <?php echo tsml_format_time($meeting['time'])?></span> <?php echo $meeting['name']?></li>
				<?php }?>
			</ol>
		</div>
		<?php }?>
		<div class="meta_form_row">
			<label>Notes</label>
			<textarea name="location_notes" placeholder="eg. Around back, basement, ring buzzer"><?php echo $location->post_content?></textarea>
		</div>
		<div class="meta_form_row">
			<label>Contacts</label>
			<div class="container">
				<?php for ($i = 1; $i < 4; $i++) {?>
				<div class="row">
					<div><input type="text" name="contact_<?php echo $i?>_name" placeholder="Name" value="<?php echo $location_custom['contact_' . $i . '_name'][0]?>"></div>
					<div><input type="text" name="contact_<?php echo $i?>_email" placeholder="Email" value="<?php echo $location_custom['contact_' . $i . '_email'][0]?>"></div>
					<div><input type="text" name="contact_<?php echo $i?>_phone" placeholder="Phone" value="<?php echo $location_custom['contact_' . $i . '_phone'][0]?>"></div>
				</div>
				<?php }?>
			</div>
		</div>
		<?php
	}, 'meetings', 'normal', 'low');
});
