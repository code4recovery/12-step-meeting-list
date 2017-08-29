<?php
//customizations for the add/edit meeting administration screens

//custom title
add_filter('enter_title_here', 'tsml_change_default_title');
function tsml_change_default_title($title){
	$screen = get_current_screen();
	if ($screen->post_type == 'tsml_meeting') {
		$title = 'Enter meeting name';
    }
    return $title;
}

//edit page
add_action('admin_init', 'tsml_admin_init');
function tsml_admin_init() {

	tsml_assets();
	
	add_meta_box('info', __('Meeting Information', '12-step-meeting-list'), 'tsml_meeting_box', 'tsml_meeting', 'normal', 'low');

	function tsml_meeting_box() {
		global $post, $tsml_days, $tsml_types, $tsml_program, $tsml_nonce, $tsml_types_in_use;

		//get post metadata
		$meeting_custom 	= get_post_custom($post->ID);
		$meeting_custom['types'] = empty($meeting_custom['types']) ? array() : unserialize($meeting_custom['types'][0]);
		if (!is_array($meeting_custom['types'])) $meeting_custom['types'] = array();
		
		if (!empty($meeting_custom['data_source'])) tsml_alert(__('This meeting was imported from an external data source. Any changes you make here will be overwritten when you refresh the data.', '12-step-meeting-list'), 'warning');
		
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
			<input type="text" class="time" name="time" id="time" value="<?php echo @$meeting_custom['time'][0]?>"<?php if (!strlen(@$meeting_custom['day'][0])) {?> disabled<?php }?> data-time-format="<?php echo get_option('time_format')?>">
			<input type="text" class="time" name="end_time" id="end_time" value="<?php echo @$meeting_custom['end_time'][0]?>"<?php if (!strlen(@$meeting_custom['day'][0])) {?> disabled<?php }?> data-time-format="<?php echo get_option('time_format')?>">
		</div>
		<div class="meta_form_row">
			<label for="tags"><?php _e('Types', '12-step-meeting-list')?></label>
			<div class="checkboxes<?php if (!empty($tsml_types_in_use) && count($tsml_types_in_use) !== count($tsml_types[$tsml_program])) {?> has_more<?php }?>">
			<?php 
			foreach ($tsml_types[$tsml_program] as $key => $type) {?>
				<label <?php if (!empty($tsml_types_in_use) && !in_array($key, $tsml_types_in_use)) { echo ' class="not_in_use"'; }?>>
					<input type="checkbox" name="types[]" value="<?php echo $key?>" <?php if (in_array($key, $meeting_custom['types'])) {?> checked="checked"<?php }?>>
					<?php echo $type?>
				</label>
			<?php }?>
				<div class="toggle_more">
					<div class="more">
						<span class="dashicons dashicons-arrow-down-alt2"></span> <a href="#more-types"><?php _e('More', '12-step-meeting-list')?></a>
					</div>
					<div class="less">
						<span class="dashicons dashicons-arrow-up-alt2"></span> <a href="#more-types"><?php _e('Fewer', '12-step-meeting-list')?></a>
					</div>
				</div>
			</div>
		</div>
		<div class="meta_form_row">
			<label for="content"><?php _e('Notes', '12-step-meeting-list')?></label>
			<textarea name="content" id="content" placeholder="eg. Birthday speaker meeting last Saturday of the month"><?php echo $post->post_content?></textarea>
		</div>
		<?php
	}		

	add_meta_box('location', __('Location Information', '12-step-meeting-list'), 'tsml_location_box', 'tsml_meeting', 'normal', 'low');
	
	function tsml_location_box() {
		global $post, $tsml_days;
		$meetings = array();
		if ($post->post_parent) {
			$location = tsml_get_location($post->post_parent);
			$meetings = tsml_get_meetings(array('location_id'=>$location->ID));
		}
		?>
		<div class="meta_form_row typeahead">
			<label for="location"><?php _e('Location', '12-step-meeting-list')?></label>
			<input type="text" name="location" id="location" value="<?php echo @$location->post_title?>">
		</div>
		<div class="meta_form_row">
			<label for="formatted_address"><?php _e('Address', '12-step-meeting-list')?></label>
			<input type="text" name="formatted_address" id="formatted_address" value="<?php echo @$location->formatted_address?>" data-original-value="<?php echo @$location->formatted_address?>">
			<input type="hidden" name="latitude" id="latitude" value="<?php echo @$location->latitude?>">
			<input type="hidden" name="longitude" id="longitude" value="<?php echo @$location->longitude?>">
		</div>
		<?php if (count($meetings) > 1) {?>
		<div class="meta_form_row checkbox apply_address_to_location hidden">
			<label><input type="checkbox" name="apply_address_to_location"> <?php _e('Apply this updated address to all meetings at this location', '12-step-meeting-list')?></label>
		</div>
		<?php }
		if (wp_count_terms('tsml_region')) {?>
		<div class="meta_form_row">
			<label for="region"><?php _e('Region', '12-step-meeting-list')?></label>
			<?php wp_dropdown_categories(array(
				'name' => 'region',
				'taxonomy' => 'tsml_region',
				'hierarchical' => true,
				'hide_empty' => false,
				'orderby' => 'name',
				'selected' => @$location->region_id,
				'show_option_none' => __('Region', '12-step-meeting-list'),
			))?>
		</div>
		<?php }?>
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
	
	add_meta_box('group', __('Group Information <small>Optional</small>', '12-step-meeting-list'), 'tsml_group_box', 'tsml_meeting', 'normal', 'low');
	
	function tsml_group_box() {
		global $post;
		$meeting_custom = get_post_custom($post->ID);
		$meetings = array();
		$district = 0;
		if (!empty($meeting_custom['group_id'][0])) {
			$group = get_post($meeting_custom['group_id'][0]);
			$group_custom = get_post_meta($group->ID);
			$meetings = tsml_get_meetings(array('group_id'=>$group->ID));
			$district = wp_get_post_terms($group->ID, 'tsml_district', array('fields'=>'ids'));
			if (is_array($district)) {
				$district = empty($district) ? 0 : $district[0];
			}
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
		<?php }
		if (wp_count_terms('tsml_district')) {?>
		<div class="meta_form_row">
			<label for="district"><?php _e('District', '12-step-meeting-list')?></label>
			<?php wp_dropdown_categories(array(
				'name' => 'district',
				'taxonomy' => 'tsml_district',
				'hierarchical' => true,
				'hide_empty' => false,
				'orderby' => 'name',
				'selected' => $district,
				'show_option_none' => __('District', '12-step-meeting-list'),
			))?>
		</div>
		<?php }?>
		<div class="meta_form_row">
			<label><?php _e('Notes', '12-step-meeting-list')?></label>
			<textarea name="group_notes" placeholder="<?php _e('eg. Group history, when the business meeting is, etc.', '12-step-meeting-list')?>"><?php echo @$group->post_content?></textarea>
		</div>
		<div class="meta_form_row">
			<label><?php _e('Website', '12-step-meeting-list')?></label>
			<input type="text" name="website" id="website" value="<?php echo @$group_custom['website'][0]?>" placeholder="https://">
		</div>
		<div class="meta_form_row">
			<label><?php _e('Email', '12-step-meeting-list')?></label>
			<input type="text" name="email" id="email" value="<?php echo @$group_custom['email'][0]?>" placeholder="group@website.com">
		</div>
		<div class="meta_form_row">
			<label><?php _e('Phone', '12-step-meeting-list')?></label>
			<input type="text" name="phone" id="phone" value="<?php echo @$group_custom['phone'][0]?>" placeholder="(800) 555-1212">
		</div>
		<div class="meta_form_row">
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
		<div class="meta_form_row">
			<label><?php _e('Last Contact', '12-step-meeting-list')?></label>
			<input type="date" name="last_contact" value="<?php echo @$group_custom['last_contact'][0]?>">
		</div>
		<?php if (tsml_accepts_payments()) {?>
		<div class="meta_form_row">
			<label><?php _e('Stripe API Key', '12-step-meeting-list')?></label>
			<input type="text" name="contributions_api_key" id="contributions_api_key" value="<?php echo @$group_custom['contributions_api_key'][0]?>" placeholder="<?php _e('Stripe API Key')?>">
		</div>
		<?php
		}
	}	
}
