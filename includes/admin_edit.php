<?php

//ajax for the location typeahead
add_action('wp_ajax_location', 'tsml_admin_ajax_locations');

function tsml_admin_ajax_locations() {
	$locations = get_posts('post_type=' . TSML_TYPE_LOCATIONS . '&numberposts=-1');
	$results = array();
    foreach ($locations as $location) {
        $title  = get_the_title($location->ID);
        $location_custom = get_post_meta($location->ID);
        $results[] = array(
            'value'				=> html_entity_decode($title),
            'formatted_address'	=> @$location_custom['formatted_address'][0],
            'latitude'			=> @$location_custom['latitude'][0],
            'longitude'			=> @$location_custom['longitude'][0],
            'address'			=> @$location_custom['address'][0],
            'city'				=> @$location_custom['city'][0],
            'state'				=> @$location_custom['state'][0],
            'postal_code'		=> @$location_custom['postal_code'][0],
            'country'			=> @$location_custom['country'][0],
            'region'			=> @$location_custom['region'][0],
            'notes'				=> html_entity_decode($location->post_content),
            'tokens'			=> tsml_string_tokens($title . ' ' . @$location_custom['address'][0]),
        );
	}
	//dd($results);
	wp_send_json($results);
}

//tokenize string for the typeaheads
function tsml_string_tokens($string) {

	//shorten words that have quotes in them instead of splitting them
	$string = html_entity_decode($string);
	$string = str_replace("'", '', $string);
	$string = str_replace('’', '', $string);
	
	//remove everything that's not a letter or a number
	$string = preg_replace("/[^a-zA-Z 0-9]+/", ' ', $string);
	
	//return array
	return array_values(array_unique(array_filter(explode(' ', $string))));

}

//ajax for the group typeahead
add_action('wp_ajax_tsml_group', 'tsml_admin_ajax_groups');

function tsml_admin_ajax_groups() {
	$groups = get_posts('post_type=' . TSML_TYPE_GROUPS . '&numberposts=-1');
	$results = array();
    foreach ($groups as $group) {
        $title  = get_the_title($group->ID);
        $group_custom = get_post_meta($group->ID);
        $results[] = array(
            'value'				=> html_entity_decode($title),
            'contact_1_name'	=> @$group_custom['contact_1_name'][0],
            'contact_1_email'	=> @$group_custom['contact_1_email'][0],
            'contact_1_phone'	=> @$group_custom['contact_1_phone'][0],
            'contact_2_name'	=> @$group_custom['contact_2_name'][0],
            'contact_2_email'	=> @$group_custom['contact_2_email'][0],
            'contact_2_phone'	=> @$group_custom['contact_2_phone'][0],
            'contact_3_name'	=> @$group_custom['contact_3_name'][0],
            'contact_3_email'	=> @$group_custom['contact_3_email'][0],
            'contact_3_phone'	=> @$group_custom['contact_3_phone'][0],
            'notes'				=> html_entity_decode($group->post_content),
            'tokens'			=> tsml_string_tokens($title),
        );
	}
	wp_send_json($results);
}

//ajax for address checking
add_action('wp_ajax_address', 'tsml_admin_ajax_address');

function tsml_admin_ajax_address() {
	if (!$posts = get_posts(array(
		'post_type'		=> TSML_TYPE_LOCATIONS,
		'numberposts'	=> 1,
		'meta_key'		=> 'formatted_address',
		'meta_value'	=> sanitize_text_field($_GET['formatted_address']),
	))) return array();

	$custom = get_post_custom($posts[0]->ID);

	//return info to user
	wp_send_json(array(
		'location' => $posts[0]->post_title,
		'location_notes' => $posts[0]->post_content,
		'region' => $custom['region'][0],
	));
}

//custom title
function tsml_change_default_title($title){
	$screen = get_current_screen();
	if ($screen->post_type == TSML_TYPE_MEETINGS) {
		$title = 'Enter meeting name';
    }
    return $title;
}
add_filter('enter_title_here', 'tsml_change_default_title');

//edit page
add_action('admin_init', 'tsml_admin_init');

function tsml_admin_init() {

	tsml_assets();
	
	remove_meta_box('regiondiv', TSML_TYPE_MEETINGS, 'side');
	remove_meta_box('wii_post-box1', TSML_TYPE_MEETINGS, 'normal'); //removes weaver ii from east bay site

	add_meta_box('info', __('Meeting Information', '12-step-meeting-list'), 'tsml_meeting_box', TSML_TYPE_MEETINGS, 'normal', 'low');

	function tsml_meeting_box() {
		global $post, $tsml_days, $tsml_types, $tsml_program, $tsml_nonce;

		//get post metadata
		$meeting_custom 	= get_post_custom($post->ID);
		$meeting_custom['types'] = empty($meeting_custom['types']) ? array() : unserialize($meeting_custom['types'][0]);
		if (!is_array($meeting_custom['types'])) $meeting_custom['types'] = array();
		
		//nonce field
		wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
		?>
		<div class="meta_form_row">
			<label for="day"><?php _e('Day', '12-step-meeting-list')?></label>
			<select name="day" id="day">
				<?php foreach ($tsml_days as $key=>$day) {?>
				<option value="<?php echo $key?>"<?php if (strcmp(@$meeting_custom['day'][0], $key) == 0) {?> selected<?php }?>><?php echo $day?></option>
				<?php }?>
				<option disabled>──────</option>
				<option value=""<?php if (!strlen(@$meeting_custom['day'][0])) {?> selected<?php }?>><?php _e('Appointment', '12-step-meeting-list')?></option>
			</select>
		</div>
		<div class="meta_form_row">
			<label for="time"><?php _e('Time', '12-step-meeting-list')?></label>
			<input type="time" name="time" id="time" value="<?php echo @$meeting_custom['time'][0]?>"<?php if (!strlen(@$meeting_custom['day'][0])) {?> disabled<?php }?>>
		</div>
		<div class="meta_form_row">
			<label for="tags"><?php _e('Types', '12-step-meeting-list')?></label>
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
			<label for="content"><?php _e('Notes', '12-step-meeting-list')?></label>
			<textarea name="content" id="content" placeholder="eg. Birthday speaker meeting last Saturday of the month"><?php echo $post->post_content?></textarea>
		</div>
		<?php
	}		

	add_meta_box('location', __('Location Information', '12-step-meeting-list'), 'tsml_location_box', TSML_TYPE_MEETINGS, 'normal', 'low');
	
	function tsml_location_box() {
		global $post, $tsml_days;
		$meetings = array();
		if ($post->post_parent) {
			$location = get_post($post->post_parent);
			$location_custom = get_post_meta($post->post_parent);
			$meetings = tsml_get_meetings(array('location_id'=>$location->ID));
		}
		?>
		<div class="meta_form_row typeahead">
			<label for="location"><?php _e('Location', '12-step-meeting-list')?></label>
			<input type="text" name="location" id="location" value="<?php echo @$location->post_title?>">
		</div>
		<div class="meta_form_row">
			<label for="formatted_address"><?php _e('Address', '12-step-meeting-list')?></label>
			<input type="text" name="formatted_address" id="formatted_address" value="<?php echo @$location_custom['formatted_address'][0]?>">
			<input type="hidden" name="address" id="address" value="<?php echo @$location_custom['address'][0]?>">
			<input type="hidden" name="city" id="city" value="<?php echo @$location_custom['city'][0]?>">
			<input type="hidden" name="state" id="state" value="<?php echo @$location_custom['state'][0]?>">
			<input type="hidden" name="postal_code" id="postal_code" value="<?php echo @$location_custom['postal_code'][0]?>">
			<input type="hidden" name="country" id="country" value="<?php echo @$location_custom['country'][0]?>">
			<input type="hidden" name="latitude" id="latitude" value="<?php echo @$location_custom['latitude'][0]?>">
			<input type="hidden" name="longitude" id="longitude" value="<?php echo @$location_custom['longitude'][0]?>">
		</div>
		<div class="meta_form_row">
			<label for="region"><?php _e('Region', '12-step-meeting-list')?></label>
			<?php wp_dropdown_categories(array(
				'name' => 'region',
				'taxonomy' => 'region',
				'hierarchical' => true,
				'hide_empty' => false,
				'orderby' => 'name',
				'selected' => @$location_custom['region'][0],
			))?>
		</div>
		<div class="meta_form_row">
			<label><?php _e('Map', '12-step-meeting-list')?></label>
			<div id="map"></div>
		</div>
		<?php if (count($meetings) > 1) {?>
		<div class="meta_form_row">
			<label><?php _e('Meetings', '12-step-meeting-list')?></label>
			<ol>
				<?php foreach ($meetings as $meeting) {
					if ($meeting['id'] != $post->ID) $meeting['name'] = '<a href="' . get_edit_post_link($meeting['id']) . '">' . $meeting['name'] . '</a>';
				?>
				<li><span><?php echo tsml_format_day_and_time($meeting['day'], $meeting['time'], ' ', true)?></span> <?php echo $meeting['name']?></li>
				<?php }?>
			</ol>
		</div>
		<?php }?>
		<div class="meta_form_row">
			<label><?php _e('Notes', '12-step-meeting-list')?></label>
			<textarea name="location_notes" placeholder="<?php _e('eg. Around back, basement, ring buzzer', '12-step-meeting-list')?>"><?php echo @$location->post_content?></textarea>
		</div>
		<?php
	}
	
	add_meta_box('group', __('Group Information', '12-step-meeting-list') . ' <span>(' . __('Optional', '12-step-meeting-list') . ')</span>', 'tsml_group_box', TSML_TYPE_MEETINGS, 'normal', 'low');
	
	function tsml_group_box() {
		global $post;
		$meeting_custom = get_post_custom($post->ID);
		$meetings = array();
		if (!empty($meeting_custom['group_id'][0])) {
			$group = get_post($meeting_custom['group_id'][0]);
			$group_custom = get_post_meta($group->ID);
			$meetings = tsml_get_meetings(array('group_id'=>$group->ID));
		}
		?>
		<div class="meta_form_row typeahead">
			<label for="group"><?php _e('Group', '12-step-meeting-list')?></label>
			<input type="text" name="group" id="group" value="<?php echo @$group->post_title?>">
		</div>
		<div class="meta_form_row checkbox apply_group_to_location hidden">
			<label><input type="checkbox" name="apply_group_to_location"> <?php _e('Apply this group to all meetings at this location', '12-step-meeting-list')?></label>
		</div>
		<?php if (count($meetings) > 1) {?>
		<div class="meta_form_row">
			<label>Meetings</label>
			<ol>
				<?php foreach ($meetings as $meeting) {
					if ($meeting['id'] != $post->ID) $meeting['name'] = '<a href="' . get_edit_post_link($meeting['id']) . '">' . $meeting['name'] . '</a>';
				?>
				<li><span><?php echo tsml_format_day_and_time($meeting['day'], $meeting['time'], ' ', true)?></span> <?php echo $meeting['name']?></li>
				<?php }?>
			</ol>
		</div>
		<?php }?>
		<div class="meta_form_row">
			<label><?php _e('Notes', '12-step-meeting-list')?></label>
			<textarea name="group_notes" placeholder="<?php _e('eg. Group history, when the business meeting is, etc.', '12-step-meeting-list')?>"><?php echo @$group->post_content?></textarea>
		</div>
		<div class="meta_form_row" style="clear:left;">
			<label><?php _e('Contacts', '12-step-meeting-list')?></label>
			<div class="container">
				<?php for ($i = 1; $i <= GROUP_CONTACT_COUNT; $i++) {?>
				<div class="row">
					<div><input type="text" name="contact_<?php echo $i?>_name" placeholder="<?php _e('Name', '12-step-meeting-list')?>" value="<?php echo @$group_custom['contact_' . $i . '_name'][0]?>"></div>
					<div><input type="text" name="contact_<?php echo $i?>_email" placeholder="<?php _e('Email', '12-step-meeting-list')?>" value="<?php echo @$group_custom['contact_' . $i . '_email'][0]?>"></div>
					<div><input type="text" name="contact_<?php echo $i?>_phone" placeholder="<?php _e('Phone', '12-step-meeting-list')?>" value="<?php echo @$group_custom['contact_' . $i . '_phone'][0]?>"></div>
				</div>
				<?php }?>
			</div>
		</div>
		<?php
	}
	
}
