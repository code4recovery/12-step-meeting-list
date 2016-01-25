<?php

//ajax for the typeahead
add_action('wp_ajax_location', 'tsml_admin_ajax_locations');

function tsml_admin_ajax_locations() {
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
            'contact_1_name'	=> $location_custom['contact_1_name'][0],
            'contact_1_email'	=> $location_custom['contact_1_email'][0],
            'contact_1_phone'	=> $location_custom['contact_1_phone'][0],
            'contact_2_name'	=> $location_custom['contact_2_name'][0],
            'contact_2_email'	=> $location_custom['contact_2_email'][0],
            'contact_2_phone'	=> $location_custom['contact_2_phone'][0],
            'contact_3_name'	=> $location_custom['contact_3_name'][0],
            'contact_3_email'	=> $location_custom['contact_3_email'][0],
            'contact_3_phone'	=> $location_custom['contact_3_phone'][0],
            'notes'				=> html_entity_decode($location->post_content),
            'tokens'			=> array_values(array_unique(explode(' ', str_replace(',', '', $title . ' ' . $location_custom['address'][0])))),
        );
	}
	wp_send_json($results);
}

//ajax for address checking
add_action('wp_ajax_address', 'tsml_admin_ajax_address');

function tsml_admin_ajax_address() {
	if (!$posts = get_posts(array(
		'post_type'		=> 'locations',
		'numberposts'	=> 1,
		'meta_key'		=> 'formatted_address',
		'meta_value'	=> sanitize_text_field($_GET['formatted_address']),
	))) return array();

	$custom = get_post_custom($posts[0]->ID);

	//return info to user
	wp_send_json(array(
		'location' => $posts[0]->post_title,
		'location_notes' => $posts[0]->post_content,
		'contact_1_name' => $custom['contact_1_name'][0],
		'contact_1_email' => $custom['contact_1_email'][0],
		'contact_1_phone' => $custom['contact_1_phone'][0],
		'contact_2_name' => $custom['contact_2_name'][0],
		'contact_2_email' => $custom['contact_2_email'][0],
		'contact_2_phone' => $custom['contact_2_phone'][0],
		'contact_3_name' => $custom['contact_3_name'][0],
		'contact_3_email' => $custom['contact_3_email'][0],
		'contact_3_phone' => $custom['contact_3_phone'][0],
	));
}

//edit page
add_action('admin_init', 'tsml_admin_init');

function tsml_admin_init() {

	tsml_assets('admin');
	
	remove_meta_box('regiondiv', 'meetings', 'side');
	remove_meta_box('wii_post-box1', 'meetings', 'normal'); //removes weaver ii from east bay site

	add_meta_box('info', 'Meeting Information', 'tsml_meeting_box', 'meetings', 'normal', 'low');

	function tsml_meeting_box() {
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
				<option value="<?php echo $key?>"<?php if (strcmp($meeting_custom['day'][0], $key) == 0) {?> selected<?php }?>><?php echo $day?></option>
				<?php }?>
				<option disabled>──────</option>
				<option value=""<?php if (!strlen($meeting_custom['day'][0])) {?> selected<?php }?>>Appointment</option>
			</select>
		</div>
		<div class="meta_form_row">
			<label for="time">Time</label>
			<input type="time" name="time" id="time" value="<?php echo $meeting_custom['time'][0]?>"<?php if (!strlen($meeting_custom['day'][0])) {?> disabled<?php }?>>
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
	}		

	add_meta_box('group', 'Group Information <span>(Optional)</span>', 'tsml_group_box', 'meetings', 'normal', 'low');
	
	function tsml_group_box() {
		?>
		<div class="meta_form_row typeahead">
			<label for="group">Group</label>
			<input type="text" name="group" id="group" value="">
		</div>
		<div class="meta_form_row" style="clear:left;">
			<label>Contacts</label>
			<div class="container">
				<?php for ($i = 1; $i < 4; $i++) {?>
				<div class="row">
					<div><input type="text" name="contact_<?php echo $i?>_name" placeholder="Name" value=""></div>
					<div><input type="text" name="contact_<?php echo $i?>_email" placeholder="Email" value=""></div>
					<div><input type="text" name="contact_<?php echo $i?>_phone" placeholder="Phone" value=""></div>
				</div>
				<?php }?>
			</div>
		</div>
		<?php
	}

	add_meta_box('location', 'Location Information', 'tsml_location_box', 'meetings', 'normal', 'low');
	
	function tsml_location_box() {
		global $post, $tsml_days;
		if ($post->post_parent) {
			$location = get_post($post->post_parent);
			$location_custom = get_post_meta($post->post_parent);
			$meetings = tsml_get_meetings(array('location_id'=>$location->ID));
		}
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
				<li><span><?php echo tsml_format_day_and_time($meeting['day'], $meeting['time'])?></span> <?php echo $meeting['name']?></li>
				<?php }?>
			</ol>
		</div>
		<?php }?>
		<div class="meta_form_row">
			<label>Notes</label>
			<textarea name="location_notes" placeholder="eg. Around back, basement, ring buzzer"><?php echo $location->post_content?></textarea>
		</div>
		<?php
		//deprecating these fields
		if (!empty($location_custom['contact_1_name'][0]) || 
			!empty($location_custom['contact_1_email'][0]) || 
			!empty($location_custom['contact_1_phone'][0]) || 
			!empty($location_custom['contact_2_name'][0]) || 
			!empty($location_custom['contact_2_email'][0]) || 
			!empty($location_custom['contact_2_phone'][0]) || 
			!empty($location_custom['contact_3_name'][0]) || 
			!empty($location_custom['contact_3_email'][0]) || 
			!empty($location_custom['contact_3_phone'][0])) {?>
		<div class="meta_form_row">
			<label>Contacts</label>
			<div class="container">
				<?php for ($i = 1; $i < 4; $i++) {
					if (!empty($location_custom['contact_' . $i . '_name'][0]) || !empty($location_custom['contact_' . $i . '_email'][0]) || !empty($location_custom['contact_' . $i . '_phone'][0])) {
					?>
				<div class="row">
					<div><input type="text" name="contact_<?php echo $i?>_name" placeholder="Name" value="<?php echo @$location_custom['contact_' . $i . '_name'][0]?>"></div>
					<div><input type="text" name="contact_<?php echo $i?>_email" placeholder="Email" value="<?php echo @$location_custom['contact_' . $i . '_email'][0]?>"></div>
					<div><input type="text" name="contact_<?php echo $i?>_phone" placeholder="Phone" value="<?php echo @$location_custom['contact_' . $i . '_phone'][0]?>"></div>
				</div>
					<?php }
				}?>
			</div>
		</div>
		<?php
		}
	}
}
