<?php
// customizations for the add/edit meeting administration screens

// custom title
add_filter('enter_title_here', function ($title) {
    $screen = get_current_screen();
    if ($screen->post_type == 'tsml_meeting') {
        $title = __('Enter meeting name', '12-step-meeting-list');
    }
    return $title;
});


// move author meta box to right side
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

// edit page
add_action('admin_init', function () {

    // compares versions and updates databases as needed for upgrades
    $tsml_version = get_option('tsml_version');
    if (version_compare($tsml_version, TSML_VERSION, '<')) {


        // do this every time
        update_option('tsml_version', TSML_VERSION);
        flush_rewrite_rules();
    };

    add_meta_box('info', __('Meeting Information', '12-step-meeting-list'), function () {
        global $tsml_days, $tsml_programs, $tsml_program, $tsml_nonce, $tsml_types_in_use;

        $meeting = tsml_get_meeting();

        // time is before the end of april and not currently using temporary closure
        if (!in_array('TC', $tsml_types_in_use) && time() < strtotime('2020-04-30')) {
            tsml_alert('Please note: a new “Temporary Closure” meeting type has recently been added. Use this to indicate meetings that are temporarily not meeting. Find it under “View all” below.', 'warning');
        }

        if (!empty($meeting->data_source)) {
            tsml_alert(__('This meeting was imported from an external data source. Any changes you make here will be overwritten when you refresh the data.', '12-step-meeting-list'), 'warning');
        }

        // nonce field
        wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
        ?>
        <div class="meta_form_row">
            <label for="day">
                <?php esc_html_e('Day', '12-step-meeting-list') ?>
            </label>
            <select name="day" id="day">
                <?php foreach ($tsml_days as $key => $day) { ?>
                    <option value="<?php echo esc_attr($key) ?>" <?php selected(strcmp(@$meeting->day, $key) == 0) ?>>
                        <?php echo esc_html($day) ?>
                    </option>
                <?php } ?>
                <option disabled>──────</option>
                <option value="" <?php selected(!strlen(@$meeting->day)) ?>>
                    <?php esc_html_e('Appointment', '12-step-meeting-list') ?>
                </option>
            </select>
        </div>
        <div class="meta_form_row">
            <label for="time">
                <?php esc_html_e('Time', '12-step-meeting-list') ?>
            </label>
            <?php
            tsml_input_text('time', @$meeting->time, ['class' => 'time', 'data-time-format' => get_option('time_format'), 'disabled' => !strlen(@$meeting->day)]);
            tsml_input_text('end_time', @$meeting->end_time, ['class' => 'time', 'data-time-format' => get_option('time_format'), 'disabled' => !strlen(@$meeting->day)]);
            ?>
        </div>
        <?php if (tsml_program_has_types()) { ?>
            <div class="meta_form_row">
                <label for="types">
                    <?php esc_html_e('Types', '12-step-meeting-list') ?>
                </label>
                <div
                    class="checkboxes<?php if (!empty($tsml_types_in_use) && count($tsml_types_in_use) !== count($tsml_programs[$tsml_program]['types'])) { ?> has_more<?php } ?>">
                    <?php
                    foreach ($tsml_programs[$tsml_program]['types'] as $key => $type) {
                        if ($key == 'ONL' || $key == 'TC') {
                            // hide "Online Meeting" since it's not manually settable, neither is location Temporarily Closed
                            continue;
                        }
                        ?>
                        <label <?php if (!empty($tsml_types_in_use) && !in_array($key, $tsml_types_in_use)) {
                            echo ' class="not_in_use"';
                        } ?>>
                            <input type="checkbox" name="types[]" value="<?php echo esc_attr($key) ?>" <?php checked(in_array($key, @$meeting->types)) ?>>
                            <?php echo esc_html($type) ?>
                        </label>
                    <?php } ?>
                    <div class="toggle_more">
                        <div class="more">
                            <span class="dashicons dashicons-arrow-down-alt2"></span> <a href="#more-types">
                                <?php esc_html_e('View all', '12-step-meeting-list') ?>
                            </a>
                        </div>
                        <div class="less">
                            <span class="dashicons dashicons-arrow-up-alt2"></span> <a href="#more-types">
                                <?php esc_html_e('Hide types not in use', '12-step-meeting-list') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
        <div class="meta_form_row">
            <label for="content">
                <?php esc_html_e('Notes', '12-step-meeting-list') ?>
            </label>
            <textarea name="content" id="content"
                placeholder="<?php esc_attr_e('eg. Birthday speaker meeting last Saturday of the month', '12-step-meeting-list') ?>"><?php echo esc_textarea($meeting->post_content) ?></textarea>
        </div>
        <div class="meta_form_separator">
            <h4>
                <?php esc_html_e('Online Meeting Details', '12-step-meeting-list') ?>
            </h4>
            <p>
                <?php echo wp_kses(
                    sprintf(
                        // translators: %s is the list of supported conference providers
                        __('If this meeting has videoconference information, please enter the full valid URL here. Currently supported providers: %s. If other details are required, such as a password, they can be included in the Notes field above, but a ‘one tap’ experience is ideal. Passwords can be appended to phone numbers using this format <code>+12125551212,,123456789#,,#,,444444#</code>', '12-step-meeting-list'),
                        implode(', ', tsml_conference_providers())
                    ),
                    TSML_ALLOWED_HTML
                ) ?>
            </p>
        </div>
        <div class="meta_form_row">
            <label for="conference_url">
                <?php esc_html_e('URL', '12-step-meeting-list') ?>
            </label>
            <?php tsml_input_url('conference_url', @$meeting->conference_url) ?>
            <small class="error_message" data-message="1">
                <?php esc_html_e('Zoom conference urls require a valid meeting number. Example: https://zoom.us/j/1234567890', '12-step-meeting-list') ?>
            </small>
            <small class="error_warning" data-message="2">
                <?php esc_html_e('Your conference url has been updated to follow the Zoom url standard.', '12-step-meeting-list') ?>
            </small>
        </div>
        <div class="meta_form_row">
            <label for="conference_url_notes">
                <?php esc_html_e('URL Notes', '12-step-meeting-list') ?>
            </label>
            <?php tsml_input_text('conference_url_notes', @$meeting->conference_url_notes) ?>
        </div>
        <div class="meta_form_row">
            <label for="conference_phone">
                <?php esc_html_e('Phone', '12-step-meeting-list') ?>
            </label>
            <?php tsml_input_text('conference_phone', @$meeting->conference_phone, ['placeholder' => "+12125551212,,123456789#,,#,,444444#"]) ?>
        </div>
        <div class="meta_form_row">
            <label for="conference_phone_notes">
                <?php esc_html_e('Phone Notes', '12-step-meeting-list') ?>
            </label>
            <?php tsml_input_text('conference_phone_notes', @$meeting->conference_phone_notes) ?>
        </div>
        <?php
    }, 'tsml_meeting', 'normal', 'low');

    add_meta_box(
        'location',
        __('Location Information', '12-step-meeting-list'),
        function () {
            global $tsml_mapbox_key, $tsml_google_maps_key, $tsml_timezone, $tsml_user_interface;
            // phpcs:ignore
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
                    <?php esc_html_e('Can I currently attend this meeting in person?', '12-step-meeting-list') ?>
                </div>
                <label>
                    <input type="radio" name="in_person" value="yes" <?php checked(empty($meeting->attendance_option) || $meeting->attendance_option == 'in_person' || $meeting->attendance_option == 'hybrid') ?> />
                    <?php esc_html_e('Yes', '12-step-meeting-list') ?>
                </label>
                <label>
                    <input type="radio" name="in_person" value="no" <?php checked($meeting->attendance_option == 'online' || $meeting->attendance_option == 'inactive') ?> />
                    <?php esc_html_e('No', '12-step-meeting-list') ?>
                </label>
            </div>
            <div class="location_note">
                <?php esc_html_e('Select Yes for in-person or hybrid meetings.', '12-step-meeting-list') ?><br />
                <?php esc_html_e('Select No for online-only meetings, or meetings that are temporarily inactive.', '12-step-meeting-list') ?><br /><br />

                <?php esc_html_e('For meetings I can attend in person:', '12-step-meeting-list') ?>
                <ul>
                    <li>
                        <?php esc_html_e('A specific address is required', '12-step-meeting-list') ?>
                    </li>
                </ul>

                <?php esc_html_e('For online or hybrid meetings:', '12-step-meeting-list') ?>
                <ul>
                    <li>
                        <?php esc_html_e('Fill in the "Online Meeting Details" above', '12-step-meeting-list') ?>
                    </li>
                </ul>

                <?php esc_html_e('For online-only meetings:', '12-step-meeting-list') ?>
                <ul>
                    <li>
                        <?php esc_html_e('Use an approximate address, example: Philadelphia, PA, USA. It may help to think of it as the meeting\'s origin. The Meeting Guide app uses this information to infer the meeting\'s time zone.', '12-step-meeting-list') ?>
                    </li>
                </ul>
            </div>
            <div class="location_warning need_approximate_address hidden">
                <?php esc_html_e('Warning: Online meetings with a specific address will appear that the location temporarily closed. Meetings that are Online only should use appoximate addresses.', '12-step-meeting-list') ?><br /><br />
                <?php esc_html_e('Example:', '12-step-meeting-list') ?><br />
                <?php esc_html_e('Location: Online-Philadelphia', '12-step-meeting-list') ?><br />
                <?php esc_html_e('Address: Philadelphia, PA, USA', '12-step-meeting-list') ?>
            </div>
        </div>

        <div class="meta_form_row">
            <label for="location">
                <?php esc_html_e('Location', '12-step-meeting-list') ?>
            </label>
            <?php tsml_input_text('location', @$location->post_title) ?>
        </div>
        <div class="meta_form_row">
            <label for="formatted_address">
                <?php esc_html_e('Address', '12-step-meeting-list') ?>
            </label>
            <?php
                tsml_input_text('formatted_address', @$location->formatted_address, ['data-original-value' => @$location->formatted_address]);
                tsml_input_hidden('approximate', @$location->approximate);
                tsml_input_hidden('latitude', @$location->latitude);
                tsml_input_hidden('longitude', @$location->longitude);
                ?>
            <small class="error_message" data-message="1">
                <?php esc_html_e('Error: In person meetings must have a specific address.', '12-step-meeting-list') ?>
            </small>
            <small class="error_message" data-message="2">
                <?php esc_html_e('Error: Unable to process this address for exact location.', '12-step-meeting-list') ?>
            </small>
        </div>
        <?php if (count($meetings) > 1) { ?>
            <div class="meta_form_row checkbox apply_address_to_location hidden">
                <label>
                    <input type="checkbox" name="apply_address_to_location">
                    <?php esc_html_e('Apply this updated address to all meetings at this location', '12-step-meeting-list') ?>
                </label>
            </div>
        <?php }
            // phpcs:ignore
            if (wp_count_terms('tsml_region')) { ?>
            <div class="meta_form_row">
                <label for="region">
                    <?php esc_html_e('Region', '12-step-meeting-list') ?>
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
                <?php esc_html_e('Map', '12-step-meeting-list') ?>
            </label>
            <div id="map">
                <?php if (empty($tsml_mapbox_key) && empty($tsml_google_maps_key)) { ?>
                    <p>
                        <?php echo wp_kses(sprintf(
                                // translators: %s is the link to the Import & Settings page
                                __('Enable maps on the <a href="%s">Import & Settings</a> page.', '12-step-meeting-list'),
                                admin_url('edit.php?post_type=tsml_meeting&page=import')
                            ), TSML_ALLOWED_HTML) ?>
                    </p>
                <?php } ?>
            </div>
        </div>

        <div class="meta_form_row">
            <label for="timezone">
                <?php esc_html_e('Timezone', '12-step-meeting-list') ?>
            </label>
            <?php tsml_timezone_select(@$location->timezone) ?>
        </div>

        <?php if (empty($location->timezone) && empty($tsml_timezone) && $tsml_user_interface === 'tsml_ui') { ?>
            <div class="meta_form_separator">
                <p>
                    <?php esc_html_e('Because your site does not have a default timezone set, a timezone must be selected here for the meeting to appear on the meeting finder page.', '12-step-meeting-list') ?>
                </p>
            </div>
        <?php } ?>

        <?php if (count($meetings) > 1) { ?>
            <div class="meta_form_row">
                <label>
                    <?php esc_html_e('Meetings', '12-step-meeting-list') ?>
                </label>
                <?php tsml_admin_meeting_list($meetings, $meeting->ID) ?>
            </div>
        <?php } ?>
        <div class="meta_form_row">
            <label>
                <?php esc_html_e('Location Notes', '12-step-meeting-list') ?>
            </label>
            <textarea name="location_notes"
                placeholder="<?php esc_html_e('eg. Around back, basement, ring buzzer', '12-step-meeting-list') ?>"><?php echo esc_attr(@$location->post_content) ?></textarea>
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
                    <?php esc_html_e('Individual meeting', '12-step-meeting-list') ?>
                </label>
                <label>
                    <input type="radio" name="group_status" value="group" <?php checked(!empty($meeting->group)) ?>>
                    <?php esc_html_e('Part of a group', '12-step-meeting-list') ?>
                </label>
            </div>
            <div class="meta_form_row group-visible">
                <label for="group">
                    <?php esc_html_e('Group', '12-step-meeting-list') ?>
                </label>
                <?php tsml_input_text('group', @$meeting->group) ?>
            </div>
            <div class="meta_form_row checkbox apply_group_to_location hidden">
                <label>
                    <input type="checkbox" name="apply_group_to_location">
                    <?php esc_html_e('Apply this group to all meetings at this location', '12-step-meeting-list') ?>
                </label>
            </div>
            <?php if (count($meetings) > 1) { ?>
                <div class="meta_form_row">
                    <label><?php esc_html_e('Meetings', '12-step-meeting-list') ?></label>
                    <?php tsml_admin_meeting_list($meetings, $meeting->ID) ?>
                </div>
            <?php }
            // phpcs:ignore
            if (wp_count_terms('tsml_district')) { ?>
                <div class="meta_form_row group-visible">
                    <label for="district">
                        <?php esc_html_e('District', '12-step-meeting-list') ?>
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
                    <?php esc_html_e('Group Notes', '12-step-meeting-list') ?>
                </label>
                <textarea name="group_notes" id="group_notes"
                    placeholder="<?php esc_html_e('eg. Group history, when the business meeting is, etc.', '12-step-meeting-list') ?>"><?php echo esc_textarea(@$meeting->group_notes) ?></textarea>
            </div>
            <div class="meta_form_row">
                <label for="website">
                    <?php esc_html_e('Website', '12-step-meeting-list') ?>
                </label>
                <?php tsml_input_url('website', @$meeting->website) ?>
            </div>
            <div class="meta_form_row">
                <label for="website_2">
                    <?php esc_html_e('Website 2', '12-step-meeting-list') ?>
                </label>
                <?php tsml_input_url('website_2', @$meeting->website_2) ?>
            </div>
            <div class="meta_form_row">
                <label for="email">
                    <?php esc_html_e('Email', '12-step-meeting-list') ?>
                </label>
                <?php tsml_input_email('email', @$meeting->email, ['placeholder' => 'group@website.org']) ?>
            </div>
            <div class="meta_form_row">
                <label for="phone">
                    <?php esc_html_e('Phone', '12-step-meeting-list') ?>
                </label>
                <?php tsml_input_text('phone', @$meeting->phone, ['placeholder' => '+18005551212']) ?>
            </div>
            <div class="meta_form_row">
                <label for="mailing_address">
                    <?php esc_html_e('Mailing Address', '12-step-meeting-list') ?>
                </label>
                <?php tsml_input_text('mailing_address', @$meeting->mailing_address, ['placeholder' => '123 Main St, Anytown OK']) ?>
            </div>
            <div class="meta_form_row">
                <label>
                    <?php esc_html_e('Venmo', '12-step-meeting-list') ?>
                </label>
                <?php tsml_input_text('venmo', @$meeting->venmo, ['placeholder' => '@VenmoHandle']) ?>
            </div>
            <div class="meta_form_row">
                <label>
                    <?php esc_html_e('Square Cash', '12-step-meeting-list') ?>
                </label>
                <?php tsml_input_text('square', @$meeting->square, ['placeholder' => '$Cashtag']) ?>
            </div>
            <div class="meta_form_row">
                <label>
                    <?php esc_html_e('PayPal', '12-step-meeting-list') ?>
                </label>
                <?php tsml_input_text('paypal', @$meeting->paypal, ['placeholder' => 'PayPalUsername']) ?>
                <small class="error_message" data-message="1">
                    <?php esc_html_e('A valid PayPal username can only contain letters and numbers, and be less than 20 characters.', '12-step-meeting-list') ?>
                </small>
            </div>
            <div class="meta_form_row">
                <label>
                    <?php esc_html_e('Contacts', '12-step-meeting-list') ?>
                    <span style="display: block;font-size:90%;color:#999;">
                        (
                        <?php if ($tsml_contact_display == 'public') {
                            esc_html_e('Public', '12-step-meeting-list');
                        } else {
                            esc_html_e('Private', '12-step-meeting-list');
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
                                    <?php tsml_input_text($field, @$meeting->{$field}, ['placeholder' => $label]) ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <div class="meta_form_row">
                <label for="last_contact">
                    <?php esc_html_e('Last Contact', '12-step-meeting-list') ?>
                </label>
                <?php tsml_input_date('last_contact', @$meeting->last_contact) ?>
            </div>
        </div>
        <?php
    }, 'tsml_meeting', 'normal', 'low');
});
