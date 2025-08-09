<?php

// catch meetings without locations and save them as a draft, also format text
add_filter('wp_insert_post_data', function ($post) {
    global $tsml_days;

    // sanitize text (remove html, trim)
    if ($post['post_type'] == 'tsml_meeting') {
        $post['post_content'] = tsml_sanitize_text_area($post['post_content']);

        // check for blank title. if empty, build one from entered values
        if (empty($post['post_title'])) {
            $title = empty($_POST['location']) ? __('New Meeting', '12-step-meeting-list') : $_POST['location'];
            $title .= in_array($_POST['day'], ['0', '1', '2', '3', '4', '5', '6']) ? ' ' . $tsml_days[$_POST['day']] : '';
            $title .= empty($_POST['time']) ? '' : ' ' . $_POST['time'];
            $post['post_title'] = $title;
        }
    }

    return $post;
}, '99', 2);


// handle all the metadata, location
add_action('post_updated', function ($post_id, $post, $post_before) {
    global $tsml_nonce, $wpdb, $tsml_notification_addresses, $tsml_days, $tsml_contact_fields;

    // security
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }
    if (!isset($_POST['tsml_nonce']) || !wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
        return;
    }
    if (!isset($_POST['post_type']) || ($_POST['post_type'] != 'tsml_meeting')) {
        return;
    }
    tsml_require_meetings_permission();

    // sanitize strings (website, website_2, paypal are not included)
    $strings = ['post_title', 'location', 'formatted_address', 'timezone', 'mailing_address', 'venmo', 'square', 'post_status', 'group', 'last_contact', 'conference_url_notes', 'conference_phone', 'conference_phone_notes'];
    foreach ($strings as $string) {
        if (isset($_POST[$string])) {
            $_POST[$string] = stripslashes(sanitize_text_field($_POST[$string]));
        }
    }

    // sanitize textareas
    $textareas = ['post_content', 'location_notes', 'group_notes'];
    foreach ($textareas as $textarea) {
        if (isset($_POST[$textarea])) {
            $_POST[$textarea] = stripslashes(tsml_sanitize_text_area($_POST[$textarea]));
        }
    }

    // get current meeting state to compare against m
    $old_meeting = tsml_get_meeting($post_id);
    $decode_keys = array('post_title', 'post_content', 'location', 'timezone', 'location_notes', 'group', 'group_notes');
    foreach ($decode_keys as $key) {
        if (property_exists($post_before, $key)) {
            $old_meeting->$key = $post_before->$key;
        }
        if (!empty($old_meeting->{$key})) {
            $old_meeting->{$key} = html_entity_decode($old_meeting->{$key});
        }
    }
    $old_meeting->name = html_entity_decode($post_before->post_title);
    $old_meeting->notes = html_entity_decode($post_before->post_content);

    // check types for not-array-ness (happens if program doesn't have types)
    if (empty($_POST['types']) || !is_array($_POST['types'])) {
        $_POST['types'] = [];
    }

    // don't allow it to be both open and closed
    if (in_array('C', $_POST['types']) && in_array('O', $_POST['types'])) {
        $_POST['types'] = array_values(array_diff($_POST['types'], ['C']));
    }

    // don't allow it to be both men and women
    if (in_array('M', $_POST['types']) && in_array('W', $_POST['types'])) {
        $_POST['types'] = array_values(array_diff($_POST['types'], ['W']));
    }

    // video conference information (doing this here because it affects types)
    // If either Conference URL or phone have values, we allow/set type ONL
    $valid_conference_url = null;
    $_POST['types'] = array_values(array_diff($_POST['types'], ['ONL']));
    if (!empty($_POST['conference_url'])) {
        $url = tsml_sanitize('url', $_POST['conference_url']);
        if (tsml_conference_provider($url)) {
            $valid_conference_url = $url;
            $_POST['types'][] = 'ONL';
        }
    }
    $_POST['conference_phone'] = tsml_sanitize('phone', $_POST['conference_phone']);
    if (!empty($_POST['conference_phone']) && empty($valid_conference_url)) {
        $_POST['types'][] = 'ONL';
    }

    // re-geocode to determine whether location is approximate
    $approximate = (empty($_POST['formatted_address']) || tsml_geocode($_POST['formatted_address'])['approximate'] === 'yes');

    // add TC if location is specific and and can't attend in person
    if ($_POST['in_person'] === 'no' && !$approximate) {
        $_POST['types'][] = 'TC';
    }

    // video conferencing info
    if (@strcmp($old_meeting->conference_url, $valid_conference_url) !== 0) {
        if (empty($valid_conference_url)) {
            delete_post_meta($post->ID, 'conference_url');
            delete_post_meta($post->ID, 'conference_url_notes');
        } else {
            update_post_meta($post->ID, 'conference_url', $valid_conference_url);
        }
    }

    if (strcmp($old_meeting->conference_url_notes, $_POST['conference_url_notes']) !== 0) {
        if (empty($_POST['conference_url_notes'])) {
            delete_post_meta($post->ID, 'conference_url_notes');
        } else {
            update_post_meta($post->ID, 'conference_url_notes', $_POST['conference_url_notes']);
        }
    }

    if (strcmp($old_meeting->conference_phone, $_POST['conference_phone']) !== 0) {
        if (empty($_POST['conference_phone'])) {
            delete_post_meta($post->ID, 'conference_phone');
            delete_post_meta($post->ID, 'conference_phone_notes');
        } else {
            update_post_meta($post->ID, 'conference_phone', $_POST['conference_phone']);
        }
    }

    if (strcmp($old_meeting->conference_phone_notes, $_POST['conference_phone_notes']) !== 0) {
        if (empty($_POST['conference_phone_notes'])) {
            delete_post_meta($post->ID, 'conference_phone_notes');
        } else {
            update_post_meta($post->ID, 'conference_phone_notes', $_POST['conference_phone_notes']);
        }
    }

    // compare types
    if (tsml_program_has_types() && (implode(', ', $old_meeting->types) != tsml_meeting_types($_POST['types']))) {
        if (empty($_POST['types'])) {
            delete_post_meta($post->ID, 'types');
        } else {
            update_post_meta($post->ID, 'types', array_map('esc_attr', $_POST['types']));
        }
    }

    // day could be null for appointment meeting
    if (in_array($_POST['day'], ['0', '1', '2', '3', '4', '5', '6'])) {
        if (!isset($old_meeting->day) || $old_meeting->day != intval($_POST['day'])) {
            update_post_meta($post->ID, 'day', intval($_POST['day']));
        }

        $_POST['time'] = empty($_POST['time']) ? null : tsml_sanitize('time', $_POST['time']);
        if (strcmp($old_meeting->time, $_POST['time']) !== 0) {
            if (empty($_POST['time'])) {
                delete_post_meta($post->ID, 'time');
            } else {
                update_post_meta($post->ID, 'time', $_POST['time']);
            }
        }

        $_POST['end_time'] = empty($_POST['end_time']) ? null : tsml_sanitize('time', $_POST['end_time']);
        if ($old_meeting->end_time != $_POST['end_time']) {
            if (empty($_POST['end_time'])) {
                delete_post_meta($post->ID, 'end_time');
            } else {
                update_post_meta($post->ID, 'end_time', $_POST['end_time']);
            }
        }
    } else {
        // appointment meeting
        if (!empty($old_meeting->day) || $old_meeting->day == '0') {
            delete_post_meta($post->ID, 'day');
        }
        if (!empty($old_meeting->time)) {
            delete_post_meta($post->ID, 'time');
        }
        if (!empty($old_meeting->end_time)) {
            delete_post_meta($post->ID, 'end_time');
        }
    }

    // exit here if the location is not ready
    if (empty($_POST['formatted_address'])) {
        $location_id = null;
    } else {

        // save location information (set this value or get caught in a loop)
        $_POST['post_type'] = 'tsml_location';

        // see if address is already in the database
        if (
            $locations = get_posts([
                'post_type' => 'tsml_location',
                'numberposts' => 1,
                'orderby' => 'id',
                'order' => 'ASC',
                'meta_key' => 'formatted_address',
                'meta_value' => $_POST['formatted_address'],
                'post_status' => 'any',
            ])
        ) {
            $location_id = $locations[0]->ID;
            if ($locations[0]->post_title != $_POST['location'] || $locations[0]->post_content != $_POST['location_notes']) {
                wp_update_post([
                    'ID' => $location_id,
                    'post_title' => $_POST['location'],
                    'post_content' => $_POST['location_notes'],
                ]);
            }

            // If the meeting post is published, and the location isn't, then publish the location
            if ($_POST['post_status'] == 'publish' && $locations[0]->post_status != 'publish') {
                wp_update_post(['ID' => $location_id, 'post_status' => 'publish']);
            }

            // latitude longitude only if updated
            foreach (['latitude', 'longitude'] as $field) {
                if ($old_meeting->{$field} != $_POST[$field]) {
                    update_post_meta($location_id, $field, floatval($_POST[$field]));
                }
            }
            update_post_meta($location_id, 'approximate', $approximate ? 'yes' : 'no');

            // update region
            if (@$old_meeting->region_id != @$_POST['region']) {
                if (!empty($_POST['region'])) {
                    wp_set_object_terms($location_id, intval($_POST['region']), 'tsml_region');
                } else {
                    // phpcs:ignore
                    wp_remove_object_terms($location_id, get_terms(['taxonomy' => 'tsml_region']), 'tsml_region');
                }
            }
        } elseif (!empty($_POST['formatted_address'])) {
            $location_id = wp_insert_post([
                'post_title' => $_POST['location'],
                'post_type' => 'tsml_location',
                'post_status' => 'publish',
                'post_content' => $_POST['location_notes'],
            ]);

            // set latitude, longitude, approximate and region
            update_post_meta($location_id, 'latitude', floatval($_POST['latitude']));
            update_post_meta($location_id, 'longitude', floatval($_POST['longitude']));
            update_post_meta($location_id, 'approximate', $approximate ? 'yes' : 'no');
            if (!empty($_POST['region'])) {
                wp_set_object_terms($location_id, intval($_POST['region']), 'tsml_region');
            }
        }

        // update address & info on location
        if ($location_id && (html_entity_decode($old_meeting->formatted_address) != $_POST['formatted_address'])) {
            update_post_meta($location_id, 'formatted_address', $_POST['formatted_address']);
        }
    }

    // save timezone
    if (strcmp($old_meeting->timezone, $_POST['timezone']) !== 0) {
        if (!tsml_timezone_is_valid($_POST['timezone'])) {
            delete_post_meta($location_id, 'timezone');
        } else {
            update_post_meta($location_id, 'timezone', $_POST['timezone']);
        }
    }

    // set parent on this post (or all meetings at location) without re-triggering the save_posts hook (update 7/25/17: removing post_status from this)
    if (($old_meeting->post_parent != $location_id)) {
        if (empty($_POST['apply_address_to_location'])) {
            $wpdb->query($wpdb->prepare('UPDATE ' . $wpdb->posts . ' SET post_parent = %d WHERE ID = %d', $location_id, $post->ID));
        } else {
            foreach ($old_meeting->location_meetings as $meeting) {
                $wpdb->query($wpdb->prepare('UPDATE ' . $wpdb->posts . ' SET post_parent = %d WHERE ID = %d', $location_id, $meeting['id']));
            }
        }
    }

    // location-less meetings should all be drafts
    $wpdb->query('UPDATE ' . $wpdb->posts . ' SET post_status = "draft" WHERE post_type = "tsml_meeting" AND post_status = "publish" AND post_parent = 0');

    // save group information (set this value or get caught in a loop)
    $_POST['post_type'] = 'tsml_group';

    // group name is required for groups, not used for individual meetings
    if ($_POST['group_status'] == 'meeting') {
        $_POST['group'] = null;
    }

    if (empty($_POST['group'])) {
        // individual meeting
        $contact_entity_id = $post->ID;
        delete_post_meta($post->ID, 'group_id');

    } else {
        // group
        if ($groups = $wpdb->get_results($wpdb->prepare('SELECT ID, post_title, post_content FROM ' . $wpdb->posts . ' WHERE post_type = "tsml_group" AND post_title = "%s" ORDER BY id', stripslashes($_POST['group'])))) {
            $contact_entity_id = $groups[0]->ID;
            if ($groups[0]->post_title != $_POST['group'] || $groups[0]->post_content != $_POST['group_notes']) {
                wp_update_post([
                    'ID' => $contact_entity_id,
                    'post_title' => $_POST['group'],
                    'post_content' => $_POST['group_notes'],
                ]);
            }
            // update region
            if (!empty($_POST['district']) && $old_meeting->district_id != $_POST['district']) {
                wp_set_object_terms($contact_entity_id, intval($_POST['district']), 'tsml_district');
            }
        } else {
            $contact_entity_id = wp_insert_post([
                'post_type' => 'tsml_group',
                'post_status' => 'publish',
                'post_title' => $_POST['group'],
                'post_content' => $_POST['group_notes'],
            ]);
            if (!empty($_POST['district'])) {
                wp_set_object_terms($contact_entity_id, intval($_POST['district']), 'tsml_district');
            }
        }

        // save to meetings(s)
        if ($old_meeting->group_id != $contact_entity_id) {
            if (empty($_POST['apply_group_to_location'])) {
                update_post_meta($post->ID, 'group_id', $contact_entity_id);
            } else {
                foreach ($old_meeting->location_meetings as $meeting) {
                    update_post_meta($meeting['id'], 'group_id', $contact_entity_id);
                }
            }
        }

        // switching from individual meeting
        if (empty($old_meeting->group)) {
            foreach ($tsml_contact_fields as $field => $type) {
                // clear out contact information associated with meeting
                delete_post_meta($post->ID, $field);
            }
        }
    }

    // special validation, todo warn user on fail
    if (!empty($_POST['venmo']) && substr($_POST['venmo'], 0, 1) != '@') {
        $_POST['venmo'] = null;
    }
    if (!empty($_POST['square']) && substr($_POST['square'], 0, 1) != '$') {
        $_POST['square'] = null;
    }
    if (!empty($_POST['paypal']) && strpos($_POST['paypal'], '/') !== false) {
        $_POST['paypal'] = strtok($_POST['paypal'], '/');
    }
    if (!empty($_POST['homegroup_online']) && strpos($_POST['homegroup_online'], '/') !== false) {
        $_POST['homegroup_online'] = strtok($_POST['homegroup_online'], '/');
    }

    // loop through and validate each field
    foreach ($tsml_contact_fields as $field => $type) {
        if (empty($_POST[$field])) {
            delete_post_meta($contact_entity_id, $field);
        } else {
            update_post_meta($contact_entity_id, $field, tsml_sanitize($type, $_POST[$field]));
        }
    }

    // deleted orphaned locations and groups
    tsml_delete_orphans();

    // update types in use
    tsml_update_types_in_use();

    // update bounds for geocoding
    tsml_bounds();

    // rebuild cache
    tsml_cache_rebuild();

    // remove self
    $user = wp_get_current_user();

    $new_meeting = tsml_get_meeting($post->ID);
    $new_meeting->name = $new_meeting->post_title;

    $tsml_notification_addresses = array_diff($tsml_notification_addresses, [$user->user_email]);

    if (count($tsml_notification_addresses)) {
        tsml_email_meeting_change($tsml_notification_addresses, $new_meeting, $old_meeting);
    }
}, 10, 3);
