<?php

// ajax functions


// ajax for the location typeahead on the meeting edit page
add_action('wp_ajax_tsml_locations', function () {
    tsml_require_meetings_permission();
    $locations = tsml_get_locations();
    $results = [];
    foreach ($locations as $location) {
        $results[] = [
            'value' => html_entity_decode($location['location']),
            'formatted_address' => $location['formatted_address'],
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'region' => $location['region_id'],
            'timezone' => $location['timezone'],
            'notes' => html_entity_decode($location['location_notes']),
            'tokens' => tsml_string_tokens($location['location']),
        ];
    }
    wp_send_json($results);
});

// ajax for the meeting edit group typeahead
add_action('wp_ajax_tsml_groups', function () {
    global $tsml_contact_fields;

    tsml_require_meetings_permission();

    $groups = get_posts(['post_type' => 'tsml_group', 'numberposts' => -1]);
    $results = [];
    foreach ($groups as $group) {
        $group_custom = get_post_meta($group->ID);

        // basic group info
        $result = [
            'value' => $group->post_title,
            'notes' => $group->post_content,
            'tokens' => tsml_string_tokens($group->post_title),
        ];

        foreach ($tsml_contact_fields as $field => $type) {
            $result[$field] = !empty($group_custom[$field][0]) ? $group_custom[$field][0] : null;
        }

        // district
        if ($district = get_the_terms($group, 'tsml_district')) {
            $result += [
                'district' => $district[0]->term_id,
            ];
        }

        $results[] = $result;
    }
    wp_send_json($results);
});


// ajax for the search typeahead on the public meeting directory
add_action('wp_ajax_tsml_typeahead', 'tsml_ajax_typeahead');
add_action('wp_ajax_nopriv_tsml_typeahead', 'tsml_ajax_typeahead');
function tsml_ajax_typeahead()
{
    // regions
    // phpcs:ignore
    $regions = get_terms('tsml_region');
    $results = [];
    foreach ($regions as $region) {
        $results[] = [
            'value' => html_entity_decode($region->name),
            'type' => 'region',
            'tokens' => tsml_string_tokens($region->name),
            'id' => $region->slug // needed by legacy search typeahead menu
        ];
    }

    // locations
    $locations = get_posts(['post_type' => 'tsml_location', 'numberposts' => -1]);
    foreach ($locations as $location) {
        $results[] = [
            'value' => html_entity_decode($location->post_title),
            'type' => 'location',
            'tokens' => tsml_string_tokens($location->post_title),
            'url' => get_permalink($location->ID),
        ];
    }

    // groups
    $groups = get_posts(['post_type' => 'tsml_group', 'numberposts' => -1]);
    foreach ($groups as $group) {
        $results[] = [
            'value' => html_entity_decode($group->post_title),
            'type' => 'group',
            'tokens' => tsml_string_tokens($group->post_title),
        ];
    }

    wp_send_json($results);
}

// ajax for address checking
add_action('wp_ajax_tsml_address', function () {
    tsml_require_meetings_permission();

    if (
        !$posts = get_posts([
            'post_type' => 'tsml_location',
            'numberposts' => 1,
            'meta_key' => 'formatted_address',
            'meta_value' => sanitize_text_field($_GET['formatted_address']),
        ])
    ) {
        wp_send_json(false);
    }

    $region = get_the_terms($posts[0]->ID, 'tsml_region');

    // return info to user
    wp_send_json([
        'location' => $posts[0]->post_title,
        'location_notes' => $posts[0]->post_content,
        'region' => $region[0]->term_id,
    ]);
});


// get all contact email addresses (for europe)
// linked from admin_import.php
add_action('wp_ajax_contacts', function () {
    global $wpdb;
    tsml_require_meetings_permission();
    $post_ids = $wpdb->get_col('SELECT id FROM ' . $wpdb->posts . ' WHERE post_type IN ("tsml_group", "tsml_meeting")');
    $emails = $wpdb->get_col('SELECT meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_key IN ("email", "contact_1_email", "contact_2_email", "contact_3_email") AND post_id IN (' . implode(',', $post_ids) . ')');
    $emails = array_unique(array_filter($emails));
    sort($emails);
    die(wp_kses_post(implode(',<br>', $emails)));
});


// function:	export csv
// used:		linked from admin-import.php
add_action('wp_ajax_csv', function () {

    // going to need this later
    global $tsml_days, $tsml_programs, $tsml_program, $tsml_sharing, $tsml_export_columns, $tsml_custom_meeting_fields;

    // security
    tsml_require_meetings_permission();

    // get data source
    $meetings = tsml_get_meetings([], false, true);

    // helper vars
    $delimiter = ',';
    $escape = '"';

    // allow user-defined fields to be exported
    if (!empty($tsml_custom_meeting_fields)) {
        $tsml_export_columns = array_merge($tsml_export_columns, $tsml_custom_meeting_fields);
    }

    // do header
    $return = implode($delimiter, array_values($tsml_export_columns)) . PHP_EOL;

    // get the preferred time format setting
    $time_format = get_option('time_format');

    // append meetings
    foreach ($meetings as $meeting) {
        $line = [];
        foreach ($tsml_export_columns as $column => $value) {
            if (in_array($column, ['time', 'end_time'])) {
                $line[] = empty($meeting[$column]) ? null : date($time_format, strtotime($meeting[$column]));
            } elseif ($column == 'day') {
                $line[] = $tsml_days[$meeting[$column]];
            } elseif ($column == 'types') {
                $types = !empty($meeting[$column]) ? $meeting[$column] : [];
                if (!is_array($types)) {
                    $types = [];
                }
                foreach ($types as &$type) {
                    $type = $tsml_programs[$tsml_program]['types'][trim($type)];
                }
                sort($types);
                $line[] = $escape . implode(', ', $types) . $escape;
            } elseif (strstr($column, 'notes')) {
                $line[] = $escape . strip_tags(str_replace($escape, str_repeat($escape, 2), !empty($meeting[$column]) ? $meeting[$column] : '')) . $escape;
            } elseif (array_key_exists($column, $meeting)) {
                $line[] = $escape . str_replace($escape, '', $meeting[$column]) . $escape;
            } else {
                $line[] = '';
            }
        }
        $return .= implode($delimiter, $line) . PHP_EOL;
    }

    // headers to trigger file download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="meetings.csv"');

    // output
    wp_die(wp_kses($return, []));
});

// function: receives user feedback, sends email to admin
// used:		single-meetings.php
add_action('wp_ajax_tsml_feedback', 'tsml_ajax_feedback');
add_action('wp_ajax_nopriv_tsml_feedback', 'tsml_ajax_feedback');
function tsml_ajax_feedback()
{
    global $tsml_feedback_addresses, $tsml_nonce;

    $meeting = tsml_get_meeting(intval($_POST['meeting_id']));
    $name = sanitize_text_field($_POST['tsml_name']);
    $email = sanitize_email($_POST['tsml_email']);

    $message = '<p style="padding-bottom: 20px; border-bottom: 2px dashed #ccc; margin-bottom: 20px;">' . nl2br(tsml_sanitize_text_area(stripslashes($_POST['tsml_message']))) . '</p>';

    $message_lines = [
        __('Requested By', '12-step-meeting-list') => $name . ' &lt;<a href="mailto:' . $email . '">' . $email . '</a>&gt;',
        __('Meeting', '12-step-meeting-list') => '<a href="' . get_permalink($meeting->ID) . '">' . $meeting->post_title . '</a>',
        __('When', '12-step-meeting-list') => tsml_format_day_and_time($meeting->day, $meeting->time),
    ];

    if (!empty($meeting->types)) {
        $message_lines[__('Types', '12-step-meeting-list')] = implode(', ', $meeting->types);
    }

    if (!empty($meeting->notes)) {
        $message_lines[__('Notes', '12-step-meeting-list')] = $meeting->notes;
    }

    if (!empty($meeting->location)) {
        $message_lines[__('Location', '12-step-meeting-list')] = $meeting->location;
    }

    if (!empty($meeting->formatted_address)) {
        $message_lines[__('Address', '12-step-meeting-list')] = $meeting->formatted_address;
    }

    if (!empty($meeting->region)) {
        $message_lines[__('Region', '12-step-meeting-list')] = $meeting->region;
    }

    if (!empty($meeting->location_notes)) {
        $message_lines[__('Location Notes', '12-step-meeting-list')] = $meeting->location_notes;
    }

    foreach ($message_lines as $key => $value) {
        $message .= '<p>' . $key . ': ' . $value . '</p>';
    }

    // if meeting was imported and has feedback_emails set, email them instead
    $to_email_addresses = $tsml_feedback_addresses;
    if (!empty($meeting->data_source) && !empty($meeting->feedback_emails)) {
        $to_email_addresses = $meeting->feedback_emails;
    }

    // email vars
    if (!isset($_POST['tsml_nonce']) || !wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
        esc_html_e('Error: nonce value not set correctly. Email was not sent.', '12-step-meeting-list');
    } elseif (empty($to_email_addresses) || empty($name) || !is_email($email) || empty($message)) {
        esc_html_e('Error: required form value missing. Email was not sent.', '12-step-meeting-list');
    } else {
        // send HTML email
        $subject = __('Meeting Feedback Form', '12-step-meeting-list') . ': ' . $meeting->post_title;
        if (tsml_email($to_email_addresses, $subject, $message, $name . ' <' . $email . '>')) {
            esc_html_e('Thank you for your feedback.', '12-step-meeting-list');
        } else {
            global $phpmailer;
            if (!empty($phpmailer->ErrorInfo)) {
                // translators: %s is the error message
                echo esc_html(sprintf(__('Error: %s', '12-step-meeting-list'), $phpmailer->ErrorInfo));
            } else {
                esc_html_e('An error occurred while sending email!', '12-step-meeting-list');
            }
        }
    }

    exit;
}


// function: get geocode for string
// used: public meeting directory, admin_meeting.php
add_action('wp_ajax_tsml_geocode', 'tsml_ajax_geocode');
add_action('wp_ajax_nopriv_tsml_geocode', 'tsml_ajax_geocode');
function tsml_ajax_geocode()
{
    global $tsml_nonce;
    if (!wp_verify_nonce(@$_GET['nonce'], $tsml_nonce)) {
        tsml_ajax_unauthorized();
    }
    wp_send_json(tsml_geocode(@$_GET['address']));
}

// function: get a list of all the geocodes in the database
// used: for debugging
add_action('wp_ajax_tsml_geocodes', function () {
    global $tsml_google_overrides;

    tsml_require_meetings_permission();

    $addresses = tsml_get_option_array('tsml_addresses');

    // handle get request to remove an address from the cache
    if (isset($_GET['remove'])) {
        $remove = stripslashes($_GET['remove']);
        if (!empty($addresses[$remove])) {
            unset($addresses[$remove]);
            update_option('tsml_addresses', $addresses);
        }
    }

    // include the google overrides
    if (!empty($tsml_google_overrides)) {
        $addresses = array_merge($addresses, $tsml_google_overrides);
    }

    // add useful links
    foreach ($addresses as $address => $geocode) {
        $addresses[$address]['map_address'] = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($geocode['formatted_address']);
        $addresses[$address]['map_coordinates'] = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($geocode['latitude'] . ',' . $geocode['longitude']);
        if ($geocode['status'] === 'geocode') {
            $addresses[$address]['remove'] = admin_url('admin-ajax.php?action=tsml_geocodes&remove=' . urlencode($address));
        }
    }

    ksort($addresses);

    wp_send_json($addresses);
});

// ajax function to import the meetings in the import buffer
// used by admin_import.php
add_action('wp_ajax_tsml_import', function () {
    tsml_require_meetings_permission();
    $response = tsml_import_buffer_next(25);
    wp_send_json($response);
});

// api ajax function
// used by theme, web app, mobile app
add_action('wp_ajax_meetings', 'tsml_ajax_meetings');
add_action('wp_ajax_nopriv_meetings', 'tsml_ajax_meetings');
function tsml_ajax_meetings()
{
    global $tsml_sharing, $tsml_sharing_keys, $tsml_nonce;

    // accepts GET or POST
    $input = empty($_POST) ? $_GET : $_POST;

    if ($tsml_sharing == 'open') {
        // sharing is open
    } elseif (!empty($input['nonce']) && wp_verify_nonce($input['nonce'], $tsml_nonce)) {
        // nonce checks out
    } elseif (!empty($input['key']) && array_key_exists($input['key'], $tsml_sharing_keys)) {
        // key checks out
    } else {
        tsml_ajax_unauthorized();
    }

    if (!headers_sent()) {
        header('Access-Control-Allow-Origin: *');
    }
    wp_send_json(tsml_get_meetings($input));
}

// create and email a sharing key to meeting guide
add_action('wp_ajax_meeting_guide', 'tsml_ajax_meeting_guide');
add_action('wp_ajax_nopriv_meeting_guide', 'tsml_ajax_meeting_guide');
function tsml_ajax_meeting_guide()
{
    global $tsml_sharing_keys;

    $mg_key = false;

    // check for existing keys
    foreach ($tsml_sharing_keys as $key => $value) {
        if ($value == 'Meeting Guide') {
            $mg_key = $key;
        }
    }

    // add new key
    if (empty($mg_key)) {
        $mg_key = md5(uniqid('Meeting Guide', true));
        $tsml_sharing_keys[$mg_key] = 'Meeting Guide';
        asort($tsml_sharing_keys);
        update_option('tsml_sharing_keys', $tsml_sharing_keys);
    }

    // build url
    $message = admin_url('admin-ajax.php?') . http_build_query(
        array(
            'action' => 'meetings',
            'key' => $mg_key,
        )
    );

    // send email
    if (tsml_email(TSML_MEETING_GUIDE_APP_NOTIFY, 'Sharing Key', $message)) {
        die('sent');
    }

    die('not sent!');
}

// send a 401 and exit
function tsml_ajax_unauthorized()
{
    if (!headers_sent()) {
        header('HTTP/1.1 401 Unauthorized', true, 401);
    }
    wp_send_json(['error' => 'HTTP/1.1 401 Unauthorized']);
}
