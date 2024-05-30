<?php
//customizations for the add/edit meeting administration screens

//custom title
add_filter('enter_title_here', function ($title) {
    $screen = get_current_screen();
    if ($screen->post_type == 'tsml_meeting') {
        $title = __('Enter meeting name', '12-step-meeting-list');
    }
    return $title;
});


//move author meta box to right side
add_action('do_meta_boxes', function () {
    remove_meta_box('authordiv', 'tsml_meeting', 'normal');
    add_meta_box('authordiv', __('Editor', '12-step-meeting-list'), 'post_author_meta_box', 'tsml_meeting', 'side', 'default');
});

// add admin assets to tsml screens only
add_action('admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if (is_object($screen) && $screen->post_type === 'tsml_meeting') {
        tsml_assets();
    }
});

//edit page
add_action('admin_init', function () {

    // Compares versions and updates databases as needed for upgrades
    $tsml_version = get_option('tsml_version');
    if (version_compare($tsml_version, TSML_VERSION, '<')) {


        //do this every time
        update_option('tsml_version', TSML_VERSION);
        flush_rewrite_rules();
    };

    add_meta_box('info', __('Meeting Information', '12-step-meeting-list'), function () {
        global $tsml_days, $tsml_programs, $tsml_program, $tsml_nonce, $tsml_types_in_use;

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
            <label for="day">
                <?php _e('Day', '12-step-meeting-list') ?>
            </label>
            <select name="day" id="day">
                <?php foreach ($tsml_days as $key => $day) { ?>
                    <option value="<?php echo $key ?>" <?php selected(strcmp(@$meeting->day, $key) == 0) ?>>
                        <?php echo $day ?>
                    </option>
                <?php } ?>
                <option disabled>──────</option>
                <option value="" <?php selected(!strlen(@$meeting->day)) ?>>
                    <?php _e('Appointment', '12-step-meeting-list') ?>
                </option>
            </select>
        </div>
        <div class="meta_form_row">
            <label for="time">
                <?php _e('Time', '12-step-meeting-list') ?>
            </label>
            <input type="text" class="time" name="time" id="time" value="<?php echo $meeting->time ?>" <?php disabled(!strlen(@$meeting->day)) ?> data-time-format="<?php echo get_option('time_format') ?>">
            <input type="text" class="time" name="end_time" id="end_time" value="<?php echo $meeting->end_time ?>" <?php disabled(!strlen(@$meeting->day)) ?> data-time-format="<?php echo get_option('time_format') ?>">
        </div>
        <?php if (tsml_program_has_types()) { ?>
            <div class="meta_form_row">
                <label for="types">
                    <?php _e('Types', '12-step-meeting-list') ?>
                </label>
                <div
                    class="checkboxes<?php if (!empty($tsml_types_in_use) && count($tsml_types_in_use) !== count($tsml_programs[$tsml_program]['types'])) { ?> has_more<?php } ?>">
                    <?php
                    foreach ($tsml_programs[$tsml_program]['types'] as $key => $type) {
                        if ($key == 'ONL' || $key == 'TC') continue; //hide "Online Meeting" since it's not manually settable, neither is location Temporarily Closed
                        ?>
                        <label <?php if (!empty($tsml_types_in_use) && !in_array($key, $tsml_types_in_use)) {
                            echo ' class="not_in_use"';
                        } ?>>
                            <input type="checkbox" name="types[]" value="<?php echo $key ?>" <?php checked(in_array($key, @$meeting->types)) ?>>
                            <?php echo $type ?>
                        </label>
                    <?php } ?>
                    <div class="toggle_more">
                        <div class="more">
                            <span class="dashicons dashicons-arrow-down-alt2"></span> <a href="#more-types">
                                <?php _e('View all', '12-step-meeting-list') ?>
                            </a>
                        </div>
                        <div class="less">
                            <span class="dashicons dashicons-arrow-up-alt2"></span> <a href="#more-types">
                                <?php _e('Hide types not in use', '12-step-meeting-list') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
        <div class="meta_form_row">
            <label for="content">
                <?php _e('Notes', '12-step-meeting-list') ?>
            </label>
            <textarea name="content" id="content"
                placeholder="<?php _e('eg. Birthday speaker meeting last Saturday of the month', '12-step-meeting-list') ?>"><?php echo $meeting->post_content ?></textarea>
        </div>
        <div class="meta_form_separator">
            <h4>
                <?php _e('Online Meeting Details', '12-step-meeting-list') ?>
            </h4>
            <p>
                <?php echo sprintf(__('If this meeting has videoconference information, please enter the full valid URL here. Currently supported providers: %s. If other details are required, such as a password, they can be included in the Notes field above, but a ‘one tap’ experience is ideal. Passwords can be appended to phone numbers using this format <code>+12125551212,,123456789#,,#,,444444#</code>', '12-step-meeting-list'), implode(', ', tsml_conference_providers())) ?>
            </p>
        </div>
        <div class="meta_form_row">
            <label for="conference_url">
                <?php _e('URL', '12-step-meeting-list') ?>
            </label>
            <input type="url" name="conference_url" id="conference_url" placeholder="https://"
                value="<?php echo $meeting->conference_url ?>">
        </div>
        <div class="meta_form_row">
            <label for="conference_url_notes">
                <?php _e('URL Notes', '12-step-meeting-list') ?>
            </label>
            <input type="text" name="conference_url_notes" id="conference_url_notes"
                value="<?php echo $meeting->conference_url_notes ?>">
        </div>
        <div class="meta_form_row">
            <label for="conference_phone">
                <?php _e('Phone', '12-step-meeting-list') ?>
            </label>
            <input type="text" name="conference_phone" id="conference_phone" placeholder="+12125551212,,123456789#,,#,,444444#"
                value="<?php echo $meeting->conference_phone ?>">
        </div>
        <div class="meta_form_row">
            <label for="conference_phone_notes">
                <?php _e('Phone Notes', '12-step-meeting-list') ?>
            </label>
            <input type="text" name="conference_phone_notes" id="conference_phone_notes"
                value="<?php echo $meeting->conference_phone_notes ?>">
        </div>
        <?php
    }, 'tsml_meeting', 'normal', 'low');

    add_meta_box(
        'location',
        __('Location Information', '12-step-meeting-list'),
        function () {
            global $tsml_mapbox_key, $tsml_google_maps_key, $tsml_timezone, $tsml_user_interface;
            $meeting = tsml_get_meeting();
            $location = $meetings = [];
            if ($meeting->post_parent) {
                $location = tsml_get_location($meeting->post_parent);
                $meetings = tsml_get_meetings(['location_id' => $location->ID]);
            }
            ?>
        <div class="meta_form_row radio">
            <div class="in_person">
                <div>
                    <?php _e('Can I currently attend this meeting in person?', '12-step-meeting-list') ?>
                </div>
                <label>
                    <input type="radio" name="in_person" value="yes" <?php checked(empty($meeting->attendance_option) || $meeting->attendance_option == 'in_person' || $meeting->attendance_option == 'hybrid') ?> />
                    <?php _e('Yes', '12-step-meeting-list') ?>
                </label>
                <label>
                    <input type="radio" name="in_person" value="no" <?php checked($meeting->attendance_option == 'online' || $meeting->attendance_option == 'inactive') ?> />
                    <?php _e('No', '12-step-meeting-list') ?>
                </label>
            </div>
            <div class="location_note">
                <?php _e('Select Yes for in-person or hybrid meetings.', '12-step-meeting-list') ?><br />
                <?php _e('Select No for online-only meetings, or meetings that are temporarily inactive.', '12-step-meeting-list') ?><br /><br />

                <?php _e('For meetings I can attend in person:', '12-step-meeting-list') ?>
                <ul>
                    <li>
                        <?php _e('A specific address is required', '12-step-meeting-list') ?>
                    </li>
                </ul>

                <?php _e('For online or hybrid meetings:', '12-step-meeting-list') ?>
                <ul>
                    <li>
                        <?php _e('Fill in the "Online Meeting Details" above', '12-step-meeting-list') ?>
                    </li>
                </ul>

                <?php _e('For online-only meetings:', '12-step-meeting-list') ?>
                <ul>
                    <li>
                        <?php _e('Use an approximate address, example: Philadelphia, PA, USA. It may help to think of it as the meeting\'s origin. The Meeting Guide app uses this information to infer the meeting\'s time zone.', '12-step-meeting-list') ?>
                    </li>
                </ul>
            </div>
            <div class="location_error form_not_valid hidden">
                <?php _e('Error: In person meetings must have a specific address.', '12-step-meeting-list') ?>
            </div>
            <div class="location_warning need_approximate_address hidden">
                <?php _e('Warning: Online meetings with a specific address will appear that the location temporarily closed. Meetings that are Online only should use appoximate addresses.', '12-step-meeting-list') ?><br /><br />
                <?php _e('Example:', '12-step-meeting-list') ?><br />
                <?php _e('Location: Online-Philadelphia', '12-step-meeting-list') ?><br />
                <?php _e('Address: Philadelphia, PA, USA', '12-step-meeting-list') ?>
            </div>
        </div>

        <div class="meta_form_row">
            <label for="location">
                <?php _e('Location', '12-step-meeting-list') ?>
            </label>
            <input value="<?php tsml_echo($location, 'post_title') ?>" type="text" name="location" id="location">
        </div>
        <div class="meta_form_row">
            <label for="formatted_address">
                <?php _e('Address', '12-step-meeting-list') ?>
            </label>
            <input value="<?php tsml_echo($location, 'formatted_address') ?>"
                data-original-value="<?php tsml_echo($location, 'formatted_address') ?>" type="text" name="formatted_address"
                id="formatted_address">
            <input value="<?php tsml_echo($location, 'approximate') ?>" type="hidden" name="approximate" id="approximate">
            <input value="<?php tsml_echo($location, 'latitude') ?>" type="hidden" name="latitude" id="latitude">
            <input value="<?php tsml_echo($location, 'latitude') ?>" type="hidden" name="longitude" id="longitude">
        </div>
        <?php if (count($meetings) > 1) { ?>
            <div class="meta_form_row checkbox apply_address_to_location hidden">
                <label>
                    <input type="checkbox" name="apply_address_to_location">
                    <?php _e('Apply this updated address to all meetings at this location', '12-step-meeting-list') ?>
                </label>
            </div>
        <?php }
            if (wp_count_terms('tsml_region')) { ?>
            <div class="meta_form_row">
                <label for="region">
                    <?php _e('Region', '12-step-meeting-list') ?>
                </label>
                <?php wp_dropdown_categories([
                        'name' => 'region',
                        'taxonomy' => 'tsml_region',
                        'hierarchical' => true,
                        'hide_empty' => false,
                        'orderby' => 'name',
                        'selected' => empty($location->region_id) ? null : $location->region_id,
                        'show_option_none' => __('Region', '12-step-meeting-list'),
                    ]) ?>
            </div>
        <?php } ?>

        <div class="meta_form_row">
            <label>
                <?php _e('Map', '12-step-meeting-list') ?>
            </label>
            <div id="map">
                <?php if (empty($tsml_mapbox_key) && empty($tsml_google_maps_key)) { ?>
                    <p>Enable maps on the <a href="<?php echo admin_url('edit.php?post_type=tsml_meeting&page=import') ?>">Import &
                            Settings</a> page.</p>
                <?php } ?>
            </div>
        </div>

        <div class="meta_form_row">
            <label for="timezone">
                <?php _e('Timezone', '12-step-meeting-list') ?>
            </label>

            <?php echo tsml_timezone_select(@$location->timezone) ?>
        </div>

        <?php if (empty($location->timezone) && empty($tsml_timezone) && $tsml_user_interface === 'tsml_ui') {?>
            <div class="meta_form_separator">
                <p>
                    <?php _e('Because your site does not have a default timezone set, a timezone must be selected here for the meeting to appear on the meeting finder page.', '12-step-meeting-list') ?>
                </p>
            </div>
        <?php } ?>

        <?php if (count($meetings) > 1) { ?>
            <div class="meta_form_row">
                <label>
                    <?php _e('Meetings', '12-step-meeting-list') ?>
                </label>
                <ol>
                    <?php foreach ($meetings as $m) {
                            if ($m['id'] != $meeting->ID) {
                                $m['name'] = '<a href="' . get_edit_post_link($m['id']) . '">' . $m['name'] . '</a>';
                            }
                            ?>
                        <li>
                            <span>
                                <?php echo tsml_format_day_and_time(@$m['day'], @$m['time'], ' ', true) ?>
                            </span>
                            <?php echo $m['name'] ?>
                        </li>
                    <?php } ?>
                </ol>
            </div>
        <?php } ?>
        <div class="meta_form_row">
            <label>
                <?php _e('Location Notes', '12-step-meeting-list') ?>
            </label>
            <textarea name="location_notes"
                placeholder="<?php _e('eg. Around back, basement, ring buzzer', '12-step-meeting-list') ?>"><?php tsml_echo($location, 'post_content') ?></textarea>
        </div>
        <?php
        },
        'tsml_meeting',
        'normal',
        'low'
    );

    add_meta_box('group', __('Contact Information <small>Optional</small>', '12-step-meeting-list'), function () {
        global $tsml_contact_display;
        $meeting = tsml_get_meeting();
        $meetings = [];
        $district = 0;
        if (!empty($meeting->group_id)) {
            $meetings = tsml_get_meetings(['group_id' => $meeting->group_id]);
            $district = wp_get_post_terms($meeting->group_id, 'tsml_district', ['fields' => 'ids']);
            if (is_array($district)) {
                $district = empty($district) ? 0 : $district[0];
            }
        }
        ?>
        <div id="contact-type" data-type="<?php echo empty($meeting->group) ? 'meeting' : 'group' ?>">
            <div class="meta_form_row radio">
                <label>
                    <input type="radio" name="group_status" value="meeting" <?php checked(empty($meeting->group)) ?>>
                    <?php _e('Individual meeting', '12-step-meeting-list') ?>
                </label>
                <label>
                    <input type="radio" name="group_status" value="group" <?php checked(!empty($meeting->group)) ?>>
                    <?php _e('Part of a group', '12-step-meeting-list') ?>
                </label>
            </div>
            <div class="meta_form_row group-visible">
                <label for="group">
                    <?php _e('Group', '12-step-meeting-list') ?>
                </label>
                <input type="text" name="group" id="group" value="<?php tsml_echo($meeting, 'group') ?>">
            </div>
            <div class="meta_form_row checkbox apply_group_to_location hidden">
                <label>
                    <input type="checkbox" name="apply_group_to_location">
                    <?php _e('Apply this group to all meetings at this location', '12-step-meeting-list') ?>
                </label>
            </div>
            <?php if (count($meetings) > 1) { ?>
                <div class="meta_form_row">
                    <label>Meetings</label>
                    <ol>
                        <?php foreach ($meetings as $m) {
                            if ($m['id'] != @$meeting->ID) {
                                $m['name'] = '<a href="' . get_edit_post_link($m['id']) . '">' . $m['name'] . '</a>';
                            }

                            ?>
                            <li><span>
                                    <?php echo tsml_format_day_and_time($m['day'], $m['time'], ' ', true) ?>
                                </span>
                                <?php echo $m['name'] ?>
                            </li>
                        <?php } ?>
                    </ol>
                </div>
            <?php }
            if (wp_count_terms('tsml_district')) { ?>
                <div class="meta_form_row group-visible">
                    <label for="district">
                        <?php _e('District', '12-step-meeting-list') ?>
                    </label>
                    <?php wp_dropdown_categories([
                        'name' => 'district',
                        'taxonomy' => 'tsml_district',
                        'hierarchical' => true,
                        'hide_empty' => false,
                        'orderby' => 'name',
                        'selected' => $district,
                        'show_option_none' => __('None', '12-step-meeting-list'),
                    ]) ?>
                </div>
            <?php } ?>
            <div class="meta_form_row group-visible">
                <label for="group_notes">
                    <?php _e('Group Notes', '12-step-meeting-list') ?>
                </label>
                <textarea name="group_notes" id="group_notes"
                    placeholder="<?php _e('eg. Group history, when the business meeting is, etc.', '12-step-meeting-list') ?>"><?php echo @$meeting->group_notes ?></textarea>
            </div>
            <div class="meta_form_row">
                <label for="website">
                    <?php _e('Website', '12-step-meeting-list') ?>
                </label>
                <input type="text" name="website" id="website" value="<?php tsml_echo($meeting, 'website') ?>"
                    placeholder="https://">
            </div>
            <div class="meta_form_row">
                <label for="website_2">
                    <?php _e('Website 2', '12-step-meeting-list') ?>
                </label>
                <input type="text" name="website_2" id="website_2" value="<?php tsml_echo($meeting, 'website_2') ?>"
                    placeholder="https://">
            </div>
            <div class="meta_form_row">
                <label for="email">
                    <?php _e('Email', '12-step-meeting-list') ?>
                </label>
                <input type="text" name="email" id="email" value="<?php tsml_echo($meeting, 'email') ?>"
                    placeholder="group@website.org">
            </div>
            <div class="meta_form_row">
                <label for="phone">
                    <?php _e('Phone', '12-step-meeting-list') ?>
                </label>
                <input type="text" name="phone" id="phone" value="<?php tsml_echo($meeting, 'phone') ?>"
                    placeholder="+18005551212">
            </div>
            <div class="meta_form_row">
                <label for="mailing_address">
                    <?php _e('Mailing Address', '12-step-meeting-list') ?>
                </label>
                <input type="text" name="mailing_address" id="mailing_address"
                    value="<?php tsml_echo($meeting, 'mailing_address') ?>" placeholder="123 Main St, Anytown OK">
            </div>
            <div class="meta_form_row">
                <label>
                    <?php _e('Venmo', '12-step-meeting-list') ?>
                </label>
                <input type="text" name="venmo" placeholder="@VenmoHandle" value="<?php tsml_echo($meeting, 'venmo') ?>">
            </div>
            <div class="meta_form_row">
                <label>
                    <?php _e('Square Cash', '12-step-meeting-list') ?>
                </label>
                <input type="text" name="square" placeholder="$Cashtag" value="<?php tsml_echo($meeting, 'square') ?>">
            </div>
            <div class="meta_form_row">
                <label>
                    <?php _e('PayPal', '12-step-meeting-list') ?>
                </label>
                <input type="text" name="paypal" placeholder="PayPalUsername" value="<?php tsml_echo($meeting, 'paypal') ?>">
            </div>
            <div class="meta_form_row">
                <label>
                    <?php _e('Contacts', '12-step-meeting-list') ?>
                    <span style="display: block;font-size:90%;color:#999;">
                        (
                        <?php if ($tsml_contact_display == 'public') {
                            _e('Public', '12-step-meeting-list');
                        } else {
                            _e('Private', '12-step-meeting-list');
                        } ?>)
                    </span>
                </label>
                <div class="container">
                    <?php
                    for ($i = 1; $i <= TSML_GROUP_CONTACT_COUNT; $i++) { ?>
                        <div class="row">
                            <?php
                            foreach (['name' => __('Name', '12-step-meeting-list'), 'email' => __('Email', '12-step-meeting-list'), 'phone' => __('Phone', '12-step-meeting-list')] as $key => $label) {
                                $field = implode('_', ['contact', $i, $key]);
                                ?>
                                <div>
                                    <input type="text" name="<?php echo $field ?>" placeholder="<?php echo $label ?>"
                                        value="<?php tsml_echo($meeting, $field) ?>">
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <div class="meta_form_row">
                <label for="last_contact">
                    <?php _e('Last Contact', '12-step-meeting-list') ?>
                </label>
                <input type="date" name="last_contact" value="<?php tsml_echo($meeting, 'last_contact') ?>">
            </div>
        </div>
        <?php
    }, 'tsml_meeting', 'normal', 'low');
});
