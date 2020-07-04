<?php
//customizations for the add/edit meeting administration screens

//custom title
add_filter('enter_title_here', 'tsml_change_default_title');
function tsml_change_default_title($title) {
    $screen = get_current_screen();
    if ($screen->post_type == 'tsml_meeting') {
        $title = __('Enter meeting name', '12-step-meeting-list');
    }
    return $title;
}

//move author meta box to right side
add_action('do_meta_boxes', 'tsml_move_author_meta_box');
function tsml_move_author_meta_box() {
    remove_meta_box('authordiv', 'tsml_meeting', 'normal');
    add_meta_box('authordiv', __('Editor', '12-step-meeting-list'), 'post_author_meta_box', 'tsml_meeting', 'side', 'default');
}

// Hook tsml_assets where we can check $post_type
add_action( 'admin_print_scripts-post.php', 'tsml_assets' );
add_action( 'admin_print_scripts-post-new.php', 'tsml_assets' );
add_action( 'admin_print_scripts-tsml_meeting_page_import', 'tsml_assets' );

//edit page
add_action('admin_init', 'tsml_admin_init');
function tsml_admin_init() {

//    tsml_assets();

    add_meta_box('info', __('Meeting Information', '12-step-meeting-list'), 'tsml_meeting_box', 'tsml_meeting', 'normal', 'low');

    function tsml_meeting_box() {
        global $post, $tsml_days, $tsml_programs, $tsml_program, $tsml_nonce, $tsml_types_in_use;

		$meeting = tsml_get_meeting();

		//time is before the end of april and not currently using temporary closure
		if (!in_array('TC', $tsml_types_in_use) && time() < strtotime('2020-04-30')) {
			tsml_alert('Please note: a new “Temporary Closure” meeting type has recently been added. Use this to indicate meetings that are temporarily not meeting. Find it under “View all” below.', 'warning');
		}

        if (!empty($meeting->data_source)) {
            tsml_alert(__('This meeting was imported from an external data source. Any changes you make here will be overwritten when you refresh the data.', '12-step-meeting-list'), 'warning');
        }

        //nonce field
        wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
        ?>
		<div class="meta_form_row">
			<label for="day"><?php _e('Day', '12-step-meeting-list')?></label>
			<select name="day" id="day">
				<?php foreach ($tsml_days as $key => $day) {?>
				<option value="<?php echo $key ?>"<?php selected(strcmp(@$meeting->day, $key) == 0)?>><?php echo $day ?></option>
				<?php }?>
				<option disabled>──────</option>
				<option value=""<?php selected(!strlen(@$meeting->day))?>><?php _e('Appointment', '12-step-meeting-list')?></option>
			</select>
		</div>
		<div class="meta_form_row">
			<label for="time"><?php _e('Time', '12-step-meeting-list')?></label>
			<input type="text" class="time" name="time" id="time" value="<?php echo $meeting->time ?>"<?php disabled(!strlen(@$meeting->day))?> data-time-format="<?php echo get_option('time_format') ?>">
			<input type="text" class="time" name="end_time" id="end_time" value="<?php echo $meeting->end_time ?>"<?php disabled(!strlen(@$meeting->day))?> data-time-format="<?php echo get_option('time_format') ?>">
		</div>
		<?php if (tsml_program_has_types()) {?>
		<div class="meta_form_row">
			<label for="types"><?php _e('Types', '12-step-meeting-list')?></label>
			<div class="checkboxes<?php if (!empty($tsml_types_in_use) && count($tsml_types_in_use) !== count($tsml_programs[$tsml_program]['types'])) {?> has_more<?php }?>">
			<?php
			foreach ($tsml_programs[$tsml_program]['types'] as $key => $type) {
				if ($key == 'ONL') continue; //hide "Online Meeting" since it's not manually settable
				?>
				<label <?php if (!empty($tsml_types_in_use) && !in_array($key, $tsml_types_in_use)) {echo ' class="not_in_use"';}?>>
					<input type="checkbox" name="types[]" value="<?php echo $key ?>" <?php checked(in_array($key, @$meeting->types))?>>
					<?php echo $type ?>
				</label>
			<?php }?>
				<div class="toggle_more">
					<div class="more">
						<span class="dashicons dashicons-arrow-down-alt2"></span> <a href="#more-types"><?php _e('View all', '12-step-meeting-list')?></a>
					</div>
					<div class="less">
						<span class="dashicons dashicons-arrow-up-alt2"></span> <a href="#more-types"><?php _e('Hide types not in use', '12-step-meeting-list')?></a>
					</div>
				</div>
			</div>
		</div>
		<?php }?>
		<div class="meta_form_row">
			<label for="content"><?php _e('Notes', '12-step-meeting-list')?></label>
			<textarea name="content" id="content" placeholder="<?php _e('eg. Birthday speaker meeting last Saturday of the month', '12-step-meeting-list')?>"><?php echo $meeting->post_content ?></textarea>
		</div>
		<div class="meta_form_separator">
			<h4><?php _e('Online Meeting Details', '12-step-meeting-list')?></h4>
			<p><?php echo sprintf(__('If this meeting has videoconference information, please enter the full valid URL here. Currently supported providers: %s. If other details are required, such as a password, they can be included in the Notes field above, but a ‘one tap’ experience is ideal. Passwords can be appended to phone numbers using this format <code>+12125551212,,123456789#,,#,,444444#</code>', '12-step-meeting-list'), implode(', ', tsml_conference_providers()))?></p>
		</div>
		<div class="meta_form_row">
			<label for="conference_url"><?php _e('URL', '12-step-meeting-list')?></label>
			<input type="url" name="conference_url" id="conference_url" placeholder="https://" value="<?php echo $meeting->conference_url ?>">
		</div>
		<div class="meta_form_row">
			<label for="content"><?php _e('Phone', '12-step-meeting-list')?></label>
			<input type="text" name="conference_phone" id="conference_phone" placeholder="+12125551212,,123456789#,,#,,444444#" value="<?php echo $meeting->conference_phone ?>">
		</div>
	<?php
	}

    add_meta_box('location', __('Location Information', '12-step-meeting-list'), 'tsml_location_box', 'tsml_meeting', 'normal', 'low');

    function tsml_location_box() {
        global $post, $tsml_days, $tsml_mapbox_key, $tsml_google_maps_key;
        $meetings = array();
        if ($post->post_parent) {
            $location = tsml_get_location($post->post_parent);
            $meetings = tsml_get_meetings(array('location_id' => $location->ID));
        }
        ?>
		<div class="meta_form_row typeahead">
			<label for="location"><?php _e('Location', '12-step-meeting-list')?></label>
			<input type="text" name="location" id="location" value="<?php if (!empty($location->post_title)) {
            echo $location->post_title;
        }
        ?>">
		</div>
		<div class="meta_form_row">
			<label for="formatted_address"><?php _e('Address', '12-step-meeting-list')?></label>
			<input type="text" name="formatted_address" id="formatted_address" value="<?php if (!empty($location->formatted_address)) {
            echo $location->formatted_address;
        }
        ?>" data-original-value="<?php if (!empty($location->formatted_address)) {
            echo $location->formatted_address;
        }
        ?>">
			<input type="hidden" name="latitude" id="latitude" value="<?php if (!empty($location->latitude)) {
            echo $location->latitude;
        }
        ?>">
			<input type="hidden" name="longitude" id="longitude" value="<?php if (!empty($location->longitude)) {
            echo $location->longitude;
        }
        ?>">
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
            'selected' => empty($location->region_id) ? null : $location->region_id,
            'show_option_none' => __('Region', '12-step-meeting-list'),
        ))?>
		</div>
		<?php }?>

		<div class="meta_form_row">
			<label><?php _e('Map', '12-step-meeting-list')?></label>
			<div id="map">
				<?php if (empty($tsml_mapbox_key) && empty($tsml_google_maps_key)) {?>
				<p>Enable maps on the <a href="<?php echo admin_url('edit.php?post_type=tsml_meeting&page=import') ?>">Import & Settings</a> page.</p>
				<?php }?>
			</div>
		</div>

		<?php if (count($meetings) > 1) {?>
		<div class="meta_form_row">
			<label><?php _e('Meetings', '12-step-meeting-list')?></label>
			<ol>
				<?php foreach ($meetings as $meeting) {
            if ($meeting['id'] != $post->ID) {
                $meeting['name'] = '<a href="' . get_edit_post_link($meeting['id']) . '">' . $meeting['name'] . '</a>';
            }

            ?>
				<li><span><?php echo tsml_format_day_and_time(@$meeting['day'], @$meeting['time'], ' ', true) ?></span> <?php echo $meeting['name'] ?></li>
				<?php }?>
			</ol>
		</div>
		<?php }?>
		<div class="meta_form_row">
			<label><?php _e('Location Notes', '12-step-meeting-list')?></label>
			<textarea name="location_notes" placeholder="<?php _e('eg. Around back, basement, ring buzzer', '12-step-meeting-list')?>"><?php if (!empty($location->post_content)) {
            echo $location->post_content;
        }
        ?></textarea>
		</div>
	<?php
	}

    add_meta_box('group', __('Contact Information <small>Optional</small>', '12-step-meeting-list'), 'tsml_group_box', 'tsml_meeting', 'normal', 'low');

    function tsml_group_box() {
        global $tsml_contact_display;
        $meeting = tsml_get_meeting();
        $meetings = array();
        $district = 0;
        if (!empty($meeting->group_id)) {
			$meetings = tsml_get_meetings(array('group_id' => $meeting->group_id));
            $district = wp_get_post_terms($meeting->group_id, 'tsml_district', array('fields' => 'ids'));
            if (is_array($district)) {
                $district = empty($district) ? 0 : $district[0];
            }
		}
        ?>
		<div id="contact-type" data-type="<?php echo empty($meeting->group) ? 'meeting' : 'group' ?>">
			<div class="meta_form_row radio">
				<label><input type="radio" name="group_status" value="meeting"<?php checked(empty($meeting->group))?>> <?php _e('Individual meeting', '12-step-meeting-list')?></label>
				<label><input type="radio" name="group_status" value="group"<?php checked(!empty($meeting->group))?>> <?php _e('Part of a group', '12-step-meeting-list')?></label>
			</div>
			<div class="meta_form_row typeahead group-visible">
				<label for="group"><?php _e('Group', '12-step-meeting-list')?></label>
				<input type="text" name="group" id="group" value="<?php echo @$meeting->group ?>">
			</div>
			<div class="meta_form_row checkbox apply_group_to_location hidden">
				<label><input type="checkbox" name="apply_group_to_location"> <?php _e('Apply this group to all meetings at this location', '12-step-meeting-list')?></label>
			</div>
			<?php if (count($meetings) > 1) {?>
			<div class="meta_form_row">
				<label>Meetings</label>
				<ol>
					<?php foreach ($meetings as $m) {
            if ($m['id'] != @$meeting->ID) {
                $m['name'] = '<a href="' . get_edit_post_link($m['id']) . '">' . $m['name'] . '</a>';
            }

            ?>
					<li><span><?php echo tsml_format_day_and_time($m['day'], $m['time'], ' ', true) ?></span> <?php echo $m['name'] ?></li>
					<?php }?>
				</ol>
			</div>
			<?php }
        if (wp_count_terms('tsml_district')) {?>
			<div class="meta_form_row group-visible">
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
			<div class="meta_form_row group-visible">
				<label for="group_notes"><?php _e('Group Notes', '12-step-meeting-list')?></label>
				<textarea name="group_notes" id="group_notes" placeholder="<?php _e('eg. Group history, when the business meeting is, etc.', '12-step-meeting-list')?>"><?php echo @$meeting->group_notes ?></textarea>
			</div>
			<div class="meta_form_row">
				<label for="website"><?php _e('Website', '12-step-meeting-list')?></label>
				<input type="text" name="website" id="website" value="<?php echo @$meeting->website ?>" placeholder="https://">
			</div>
			<div class="meta_form_row">
				<label for="website_2"><?php _e('Website 2', '12-step-meeting-list')?></label>
				<input type="text" name="website_2" id="website_2" value="<?php echo @$meeting->website_2 ?>" placeholder="https://">
			</div>
			<div class="meta_form_row">
				<label for="email"><?php _e('Email', '12-step-meeting-list')?></label>
				<input type="text" name="email" id="email" value="<?php echo @$meeting->email ?>" placeholder="group@website.org">
			</div>
			<div class="meta_form_row">
				<label for="phone"><?php _e('Phone', '12-step-meeting-list')?></label>
				<input type="text" name="phone" id="phone" value="<?php echo @$meeting->phone ?>" placeholder="+18005551212">
			</div>
			<div class="meta_form_row">
				<label for="mailing_address"><?php _e('Mailing Address', '12-step-meeting-list')?></label>
				<input type="text" name="mailing_address" id="mailing_address" value="<?php echo @$meeting->mailing_address ?>" placeholder="123 Main St, Anytown OK">
			</div>
			<div class="meta_form_row">
				<label><?php _e('Venmo', '12-step-meeting-list')?></label>
				<input type="text" name="venmo" placeholder="@VenmoHandle" value="<?php echo @$meeting->venmo ?>">
			</div>
			<div class="meta_form_row">
				<label><?php _e('Square Cash', '12-step-meeting-list')?></label>
				<input type="text" name="square" placeholder="$Cashtag" value="<?php echo @$meeting->square ?>">
			</div>
			<div class="meta_form_row">
				<label><?php _e('PayPal', '12-step-meeting-list')?></label>
				<input type="text" name="paypal" placeholder="PayPalUsername" value="<?php echo @$meeting->paypal ?>">
			</div>
			<div class="meta_form_row">
				<label>
					<?php _e('Contacts', '12-step-meeting-list')?>
					<span style="display: block;font-size:90%;color:#999;">(<?php if ($tsml_contact_display == 'public') {
            _e('Public', '12-step-meeting-list');
        } else {
            _e('Private', '12-step-meeting-list');
        }?>)</span>
				</label>
				<div class="container">
					<?php for ($i = 1; $i <= GROUP_CONTACT_COUNT; $i++) {?>
					<div class="row">
						<div><input type="text" name="contact_<?php echo $i ?>_name" placeholder="<?php _e('Name', '12-step-meeting-list')?>" value="<?php echo @$meeting->{'contact_' . $i . '_name'} ?>"></div>
						<div><input type="text" name="contact_<?php echo $i ?>_email" placeholder="<?php _e('Email', '12-step-meeting-list')?>" value="<?php echo @$meeting->{'contact_' . $i . '_email'} ?>"></div>
						<div><input type="text" name="contact_<?php echo $i ?>_phone" placeholder="<?php _e('Phone', '12-step-meeting-list')?>" value="<?php echo @$meeting->{'contact_' . $i . '_phone'} ?>"></div>
					</div>
					<?php }?>
				</div>
			</div>
			<div class="meta_form_row">
				<label for="last_contact"><?php _e('Last Contact', '12-step-meeting-list')?></label>
				<input type="date" name="last_contact" value="<?php echo @$meeting->last_contact ?>">
			</div>
		</div>
	<?php
	}
}