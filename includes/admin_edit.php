<?php

//ajax for the location typeahead
add_action('wp_ajax_location_autocomplete', 'tsml_admin_ajax_locations');

function tsml_admin_ajax_locations() {
	$locations = tsml_get_locations();
	$results = array();
    foreach ($locations as $location) {
        $results[] = array(
            'value'				=> html_entity_decode($location['location']),
            'formatted_address'	=> $location['formatted_address'],
            'latitude'			=> $location['latitude'],
            'longitude'			=> $location['longitude'],
            'region'			=> $location['region_id'],
            'notes'				=> html_entity_decode($location['location_notes']),
            'tokens'			=> tsml_string_tokens($location['location']),
        );
	}
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
	$groups = get_posts('post_type=tsml_group&numberposts=-1');
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
		'post_type'		=> 'tsml_location',
		'numberposts'	=> 1,
		'meta_key'		=> 'formatted_address',
		'meta_value'	=> sanitize_text_field($_GET['formatted_address']),
	))) return array();

	$region = get_the_terms($posts[0]->ID, 'tsml_region');

	//return info to user
	wp_send_json(array(
		'location' => $posts[0]->post_title,
		'location_notes' => $posts[0]->post_content,
		'region' => $region[0]->term_id,
	));
}

//custom title
function tsml_change_default_title($title){
	$screen = get_current_screen();
	if ($screen->post_type == 'tsml_meeting') {
		$title = 'Enter meeting name';
    }
    return $title;
}
add_filter('enter_title_here', 'tsml_change_default_title');

//edit page
add_action('admin_init', 'tsml_admin_init');

function tsml_admin_init() {

	tsml_assets();
	
	remove_meta_box('wii_post-box1', 'tsml_meeting', 'normal'); //removes weaver ii from east bay site

	add_meta_box('info', __('Meeting Information'), 'tsml_meeting_box', 'tsml_meeting', 'normal', 'low');

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
			<label for="day"><?php _e('Day')?></label>
			<select name="day" id="day">
				<?php foreach ($tsml_days as $key=>$day) {?>
				<option value="<?php echo $key?>"<?php if (strcmp(@$meeting_custom['day'][0], $key) == 0) {?> selected<?php }?>><?php echo $day?></option>
				<?php }?>
				<option disabled>──────</option>
				<option value=""<?php if (!strlen(@$meeting_custom['day'][0])) {?> selected<?php }?>><?php _e('Appointment')?></option>
			</select>
		</div>
		<div class="meta_form_row">
			<label for="time"><?php _e('Time')?></label>
			<input type="text" class="time" name="time" id="time" value="<?php echo @$meeting_custom['time'][0]?>"<?php if (!strlen(@$meeting_custom['day'][0])) {?> disabled<?php }?> data-time-format="<?php echo get_option('time_format')?>">
			<input type="text" class="time" name="end_time" id="end_time" value="<?php echo @$meeting_custom['end_time'][0]?>"<?php if (!strlen(@$meeting_custom['day'][0])) {?> disabled<?php }?> data-time-format="<?php echo get_option('time_format')?>">
		</div>
		<div class="meta_form_row">
			<label for="tags"><?php _e('Types')?></label>
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
			<label for="content"><?php _e('Notes')?></label>
			<textarea name="content" id="content" placeholder="eg. Birthday speaker meeting last Saturday of the month"><?php echo $post->post_content?></textarea>
		</div>
		<?php
	}		

	add_meta_box('location', __('Location Information'), 'tsml_location_box', 'tsml_meeting', 'normal', 'low');
	
	function tsml_location_box() {
		global $post, $tsml_days;
		$meetings = array();
		if ($post->post_parent) {
			$location = tsml_get_location($post->post_parent);
			$meetings = tsml_get_meetings(array('location_id'=>$location->ID));
		}
		?>
		<div class="meta_form_row typeahead">
			<label for="location"><?php _e('Location')?></label>
			<input type="text" name="location" id="location" value="<?php echo @$location->post_title?>">
		</div>
		<div class="meta_form_row">
			<label for="formatted_address"><?php _e('Address')?></label>
			<input type="text" name="formatted_address" id="formatted_address" value="<?php echo @$location->formatted_address?>">
			<input type="hidden" name="latitude" id="latitude" value="<?php echo @$location->latitude?>">
			<input type="hidden" name="longitude" id="longitude" value="<?php echo @$location->longitude?>">
		</div>
		<div class="meta_form_row">
			<label for="region"><?php _e('Region')?></label>
			<?php wp_dropdown_categories(array(
				'name' => 'region',
				'taxonomy' => 'tsml_region',
				'hierarchical' => true,
				'hide_empty' => false,
				'orderby' => 'name',
				'selected' => @$location->region_id,
			))?>
		</div>
		<div class="meta_form_row">
			<label><?php _e('Map')?></label>
			<div id="map"></div>
		</div>
		<?php if (count($meetings) > 1) {?>
		<div class="meta_form_row">
			<label><?php _e('Meetings')?></label>
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
			<label><?php _e('Notes')?></label>
			<textarea name="location_notes" placeholder="<?php _e('eg. Around back, basement, ring buzzer')?>"><?php echo @$location->post_content?></textarea>
		</div>
		<?php
	}
	
	add_meta_box('group', __('Group Information') . ' <span>(' . __('Optional') . ')</span>', 'tsml_group_box', 'tsml_meeting', 'normal', 'low');
	
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
			<label for="group"><?php _e('Group')?></label>
			<input type="text" name="group" id="group" value="<?php echo @$group->post_title?>">
		</div>
		<div class="meta_form_row checkbox apply_group_to_location hidden">
			<label><input type="checkbox" name="apply_group_to_location"> <?php _e('Apply this group to all meetings at this location')?></label>
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
			<label><?php _e('Notes')?></label>
			<textarea name="group_notes" placeholder="<?php _e('eg. Group history, when the business meeting is, etc.')?>"><?php echo @$group->post_content?></textarea>
		</div>
		<div class="meta_form_row" style="clear:left;">
			<label><?php _e('Contacts')?></label>
			<div class="container">
				<?php for ($i = 1; $i <= GROUP_CONTACT_COUNT; $i++) {?>
				<div class="row">
					<div><input type="text" name="contact_<?php echo $i?>_name" placeholder="<?php _e('Name')?>" value="<?php echo @$group_custom['contact_' . $i . '_name'][0]?>"></div>
					<div><input type="text" name="contact_<?php echo $i?>_email" placeholder="<?php _e('Email')?>" value="<?php echo @$group_custom['contact_' . $i . '_email'][0]?>"></div>
					<div><input type="text" name="contact_<?php echo $i?>_phone" placeholder="<?php _e('Phone')?>" value="<?php echo @$group_custom['contact_' . $i . '_phone'][0]?>"></div>
				</div>
				<?php }?>
			</div>
		</div>
		<div class="meta_form_row" style="clear:left;">
			<label><?php _e('Last Contact')?></label>
			<input type="date" name="last_contact" value="<?php echo @$group_custom['last_contact'][0]?>">
		</div>
		<?php
	}	
}