<?php

/**
 * get all groups in the system
 * used: tsml_group_count(), tsml_import(), and admin_import.php
 * 
 * @param mixed $status
 * @return array
 */
function tsml_get_all_groups($status = 'any')
{
    return get_posts([
        'post_type' => 'tsml_group',
        'post_status' => $status,
        'numberposts' => -1,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);
}

/**
 * get all locations in the system
 * used: tsml_location_count(), tsml_import(), and admin_import.php
 * 
 * @param mixed $status
 * @return array
 */
function tsml_get_all_locations($status = 'any')
{
    return get_posts([
        'post_type' => 'tsml_location',
        'post_status' => $status,
        'numberposts' => -1,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);
}

/**
 * get all meetings in the system
 * used: tsml_meeting_count(), tsml_import() and admin_import.php
 * 
 * @param mixed $status
 * @return array
 */
function tsml_get_all_meetings($status = 'any')
{
    return get_posts([
        'post_type' => 'tsml_meeting',
        'post_status' => $status,
        'numberposts' => -1,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);
}

/**
 * get all regions in the system
 * used: tsml_region_count(), tsml_import() and admin_import.php
 * 
 * @return mixed
 */
function tsml_get_all_regions()
{
    // phpcs:ignore
    return get_terms('tsml_region', ['fields' => 'ids', 'hide_empty' => false]);
}

/**
 * get meeting ids for a data source
 * used: tsml_ajax_import, import/settings page
 * @param mixed $source
 * @return array
 */
function tsml_get_data_source_ids($source)
{
    return get_posts([
        'post_type' => 'tsml_meeting',
        'numberposts' => -1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => 'data_source',
                'value' => $source,
                'compare' => '=',
            ]
        ],
    ]);
}

/**
 * build and return the entity array of fields
 * used: tsml_get_meetings, to supply entity for locally managed meetings / non-imported meetings
 * 
 * @return array
 */
function tsml_get_entity()
{
    global $tsml_feedback_addresses;
    $saved_tsml_entity = tsml_get_option_array('tsml_entity');
    $tsml_entity = [
        'entity' => !empty($saved_tsml_entity['entity']) ? $saved_tsml_entity['entity'] : get_bloginfo('name'),
        'entity_email' => !empty($saved_tsml_entity['entity_email']) ? $saved_tsml_entity['entity_email'] : get_bloginfo('admin_email'),
        'entity_url' => !empty($saved_tsml_entity['entity_url']) ? $saved_tsml_entity['entity_url'] : get_bloginfo('url'),
    ];
    if (!empty($saved_tsml_entity['entity_phone'])) {
        $tsml_entity['entity_phone'] = $saved_tsml_entity['entity_phone'];
    }
    if (!empty($saved_tsml_entity['entity_location'])) {
        $tsml_entity['entity_location'] = $saved_tsml_entity['entity_location'];
    }
    if (!empty($tsml_feedback_addresses)) {
        $tsml_entity['feedback_emails'] = $tsml_feedback_addresses;
    }
    return $tsml_entity;
}

/**
 * get all locations with full location information
 * used: tsml_get_meetings()
 * 
 * @return array
 */
function tsml_get_groups()
{
    global $tsml_contact_fields;

    $groups = array();

    // get all districts with parents, need for sub_district below
    $districts = $districts_with_parents = [];
    $terms = get_categories(['taxonomy' => 'tsml_district']);
    foreach ($terms as $term) {
        $districts[$term->term_id] = $term->name;
        if ($term->parent) {
            $districts_with_parents[$term->term_id] = $term->parent;
        }
    }

    // get all locations
    $posts = tsml_get_all_groups('publish');

    // much faster than doing get_post_meta() over and over
    $group_meta = tsml_get_meta('tsml_group');

    // make an array of all groups
    foreach ($posts as $post) {

        $district_id = !empty($group_meta[$post->ID]['district_id']) ? $group_meta[$post->ID]['district_id'] : null;
        if (array_key_exists($district_id, $districts_with_parents)) {
            $district = $districts[$districts_with_parents[$district_id]];
            $sub_district = $districts[$district_id];
        } else {
            $district = !empty($districts[$district_id]) ? $districts[$district_id] : '';
            $sub_district = null;
        }

        $groups[$post->ID] = [
            'group_id' => $post->ID, // so as not to conflict with another id when combined
            'group' => $post->post_title,
            'district' => $district,
            'district_id' => $district_id,
            'sub_district' => $sub_district,
            'group_notes' => $post->post_content,
        ];

        foreach ($tsml_contact_fields as $field => $type) {
            $groups[$post->ID][$field] = empty($group_meta[$post->ID][$field]) ? null : $group_meta[$post->ID][$field];
        }
    }

    return $groups;
}

/**
 * template tag to get location, attach custom fields to it
 * $location_id can be false if there is a global $post object, eg on the single location template page
 * used: single-locations.php
 * 
 * @param mixed $location_id
 * @return array|WP_Post|null
 */
function tsml_get_location($location_id = false)
{
    if (!$location = get_post($location_id)) {
        return;
    }
    if ($custom = get_post_meta($location->ID)) {
        foreach ($custom as $key => $value) {
            $location->{$key} = htmlentities($value[0], ENT_QUOTES);
        }
    }
    $location->post_title = htmlentities($location->post_title, ENT_QUOTES);
    $location->notes = esc_html($location->post_content);
    if ($region = get_the_terms($location, 'tsml_region')) {
        $location->region_id = $region[0]->term_id;
        $location->region = $region[0]->name;
    }

    // directions link (obsolete 4/15/2018, keeping for compatibility)
    $location->directions = 'https://maps.google.com/maps/dir/?api=1&' . http_build_query([
        'destination' => $location->latitude . ',' . $location->longitude,
    ]);

    return $location;
}

/**
 * get all locations with full location information
 * used: tsml_import(), tsml_get_meetings(), admin_edit
 * 
 * @return array
 */
function tsml_get_locations()
{
    $locations = [];

    // get all regions with parents, need for sub_region below
    $regions = $regions_with_parents = [];
    $terms = get_categories(['taxonomy' => 'tsml_region']);
    foreach ($terms as $term) {
        $regions[$term->term_id] = $term->name;
        if ($term->parent) {
            $regions_with_parents[$term->term_id] = $term->parent;
        }
    }

    // get all locations
    $posts = tsml_get_all_locations(['publish', 'draft']);

    // much faster than doing get_post_meta() over and over
    $location_meta = tsml_get_meta('tsml_location');

    // make an array of all locations
    foreach ($posts as $post) {
        $region_id = !empty($location_meta[$post->ID]['region_id']) ? $location_meta[$post->ID]['region_id'] : null;
        if (array_key_exists($region_id, $regions_with_parents)) {
            $region = $regions[$regions_with_parents[$region_id]];
            $sub_region = $regions[$region_id];
        } else {
            $region = !empty($regions[$region_id]) ? $regions[$region_id] : '';
            $sub_region = null;
        }

        $locations[$post->ID] = [
            'location_id' => $post->ID, // so as not to conflict with another id when combined
            'location' => $post->post_title,
            'location_notes' => $post->post_content,
            'location_url' => get_permalink($post->ID),
            'formatted_address' => empty($location_meta[$post->ID]['formatted_address']) ? null : $location_meta[$post->ID]['formatted_address'],
            'approximate' => empty($location_meta[$post->ID]['approximate']) ? null : $location_meta[$post->ID]['approximate'],
            'latitude' => empty($location_meta[$post->ID]['latitude']) ? null : $location_meta[$post->ID]['latitude'],
            'longitude' => empty($location_meta[$post->ID]['longitude']) ? null : $location_meta[$post->ID]['longitude'],
            'timezone' => empty($location_meta[$post->ID]['timezone']) ? null : $location_meta[$post->ID]['timezone'],
            'region_id' => $region_id,
            'region' => $region,
            'sub_region' => $sub_region,
        ];

        // regions array eg ['Midwest', 'Illinois', 'Chicago', 'North Side']
        $regions_array = array_filter(array_map(function ($region_id) {
            $term = get_term($region_id, 'tsml_region');
            return !empty($term->name) ? $term->name : null;
        }, array_merge([$region_id], get_ancestors($region_id, 'tsml_region'))), function ($region) {
            return !empty($region);
        });

        // omit key if empty
        if (count($regions_array)) {
            $locations[$post->ID]['regions'] = array_reverse($regions_array);
        }
    }

    return $locations;
}

/**
 * template tag to get meeting and location, attach custom fields to it
 * $meeting_id can be false if there is a global $post object, eg on the single meeting template page
 * used: single-meetings.php
 * 
 * @param mixed $meeting_id
 * @return array|WP_Post|null
 */
function tsml_get_meeting($meeting_id = false)
{
    global $tsml_program, $tsml_programs, $tsml_contact_fields, $tsml_array_fields;

    $meeting = get_post($meeting_id);
    $custom = get_post_meta($meeting->ID);

    // add optional location information
    if ($meeting->post_parent) {
        $location = get_post($meeting->post_parent);
        $meeting->location_id = $location->ID;
        $custom = array_merge($custom, get_post_meta($location->ID));
        $meeting->location = htmlentities($location->post_title, ENT_QUOTES);
        $meeting->location_notes = esc_html($location->post_content);
        if ($region = get_the_terms($location, 'tsml_region')) {
            $meeting->region_id = $region[0]->term_id;
            $meeting->region = $region[0]->name;
        }

        // get other meetings at this location
        $meeting->location_meetings = tsml_get_meetings(['location_id' => $location->ID]);

        // directions link (obsolete 4/15/2018, keeping for compatibility)
        $meeting->directions = 'https://maps.google.com/maps/dir/?api=1&' . http_build_query([
            'destination' => $location->latitude . ',' . $location->longitude,
        ]);
        $meeting->approximate = $location->approximate;
    }

    if (empty($meeting->approximate)) {
        $meeting->approximate = 'no';
    }

    // escape meeting values
    $meeting->types = [];
    foreach ($custom as $key => $value) {
        if (is_array($value)) {
            $value = count($value) ? $value[0] : '';
        }
        if (in_array($key, $tsml_array_fields, true)) {
            $value = array_values((array) maybe_unserialize($value));
        } else {
            $value = htmlentities(strval($value), ENT_QUOTES);
        }
        $meeting->{$key} = $value;
    }
    $meeting->post_title = htmlentities($meeting->post_title, ENT_QUOTES);
    $meeting->notes = esc_html($meeting->post_content);

    // type description? (todo support multiple)
    if (!empty($tsml_programs[$tsml_program]['type_descriptions'])) {
        $types_with_descriptions = array_intersect($meeting->types, array_keys($tsml_programs[$tsml_program]['type_descriptions']));
        foreach ($types_with_descriptions as $type) {
            $meeting->type_description = $tsml_programs[$tsml_program]['type_descriptions'][$type];
            break;
        }
    }

    // if meeting is part of a group, include group info
    if ($meeting->group_id) {
        $group = get_post($meeting->group_id);
        $meeting->group = htmlentities($group->post_title, ENT_QUOTES);
        $meeting->group_notes = esc_html($group->post_content);
        $group_custom = tsml_get_meta('tsml_group', $meeting->group_id);
        foreach ($tsml_contact_fields as $field => $type) {
            $meeting->{$field} = empty($group_custom[$field]) ? null : $group_custom[$field];
        }

        if ($district = get_the_terms($group, 'tsml_district')) {
            $meeting->district_id = $district[0]->term_id;
            $meeting->district = $district[0]->name;
        }
    } else {
        $meeting->group_id = null;
        $meeting->group = null;
    }

    $meeting->attendance_option = tsml_calculate_attendance_option($meeting->types, $meeting->approximate);

    // remove TC when online only meeting has approximate address
    if (!empty($meeting->types) && $meeting->attendance_option == 'online' && $meeting->approximate == 'yes') {
        $meeting->types = array_values(array_diff($meeting->types, ['TC']));
    }

    // expand and alphabetize types
    array_map('trim', $meeting->types);
    $meeting->types_expanded = [];
    foreach ($meeting->types as $type) {
        if ($type == 'ONL' || $type == 'TC') {
            continue;
        }

        if (!empty($tsml_programs[$tsml_program]['types'][$type])) {
            $meeting->types_expanded[] = $tsml_programs[$tsml_program]['types'][$type];
        }
    }
    sort($meeting->types_expanded);

    return $meeting;
}

/**
 * get feedback_url
 * called in tsml_get_meta
 * 
 * @param mixed $meeting
 * @return mixed
 */
function tsml_feedback_url($meeting)
{
    global $tsml_export_columns, $tsml_feedback_url;

    if (empty($tsml_feedback_url)) {
        return;
    }

    $url = $tsml_feedback_url;

    foreach ($tsml_export_columns as $key => $heading) {
        $value = !empty($meeting[$key]) ? $meeting[$key] : '';
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        $url = str_replace('{{' . $key . '}}', urlencode($value), $url);
    }
    return $url;
}

/**
 * get meetings based on unsanitized $arguments
 * $from_cache is only false when calling from tsml_cache_rebuild()
 * used in tsml_ajax_meetings(), single-locations.php, archive-meetings.php
 * 
 * @param mixed $arguments
 * @param mixed $from_cache
 * @param mixed $full_export
 * @return array
 */
function tsml_get_meetings($arguments = [], $from_cache = true, $full_export = false)
{
    global $tsml_cache, $tsml_cache_writable, $tsml_contact_fields, $tsml_contact_display, $tsml_data_sources, $tsml_custom_meeting_fields, $tsml_source_fields_map, $tsml_entity_fields, $tsml_array_fields;

    $tsml_entity = tsml_get_entity();

    // start by grabbing all meetings
    if ($from_cache && $tsml_cache_writable && file_exists(WP_CONTENT_DIR . $tsml_cache) && $meetings = file_get_contents(WP_CONTENT_DIR . $tsml_cache)) {
        $meetings = json_decode($meetings, true);
    } else {
        // from database
        $meetings = [];

        // can specify post_status (for PR #33)
        if (empty($arguments['post_status'])) {
            $arguments['post_status'] = 'publish';
        } elseif (is_array($arguments['post_status'])) {
            $arguments['post_status'] = array_map('sanitize_title', $arguments['post_status']);
        } else {
            $arguments['post_status'] = sanitize_title($arguments['post_status']);
        }

        $posts = get_posts([
            'post_type' => 'tsml_meeting',
            'numberposts' => -1,
            'post_status' => $arguments['post_status'],
        ]);

        $meeting_meta = tsml_get_meta('tsml_meeting');
        $groups = tsml_get_groups();
        $locations = tsml_get_locations();

        // make an array of the meetings
        foreach ($posts as $post) {
            // shouldn't ever happen, but just in case
            if (empty($locations[$post->post_parent])) {
                continue;
            }

            // append to array
            $meeting = array_merge([
                'id' => $post->ID,
                'name' => $post->post_title,
                'slug' => $post->post_name,
                'notes' => $post->post_content,
                'updated' => $post->post_modified_gmt,
                'location_id' => $post->post_parent,
                'url' => get_permalink($post->ID),
                'day' => isset($meeting_meta[$post->ID]['day']) ? $meeting_meta[$post->ID]['day'] : null,
                'time' => isset($meeting_meta[$post->ID]['time']) ? $meeting_meta[$post->ID]['time'] : null,
                'end_time' => isset($meeting_meta[$post->ID]['end_time']) ? $meeting_meta[$post->ID]['end_time'] : null,
                'time_formatted' => isset($meeting_meta[$post->ID]['time']) ? tsml_format_time($meeting_meta[$post->ID]['time']) : null,
                'edit_url' => get_edit_post_link($post, ''),
                'conference_url' => isset($meeting_meta[$post->ID]['conference_url']) ? $meeting_meta[$post->ID]['conference_url'] : null,
                'conference_url_notes' => isset($meeting_meta[$post->ID]['conference_url_notes']) ? $meeting_meta[$post->ID]['conference_url_notes'] : null,
                'conference_phone' => isset($meeting_meta[$post->ID]['conference_phone']) ? $meeting_meta[$post->ID]['conference_phone'] : null,
                'conference_phone_notes' => isset($meeting_meta[$post->ID]['conference_phone_notes']) ? $meeting_meta[$post->ID]['conference_phone_notes'] : null,
                'types' => empty($meeting_meta[$post->ID]['types']) ? [] : array_values((array) ($meeting_meta[$post->ID]['types'])),
                'author' => get_the_author_meta('user_login', $post->post_author)
            ], $locations[$post->post_parent]);

            // include user-defined meeting fields when doing a full export
            if ($full_export && !empty($tsml_custom_meeting_fields)) {
                foreach ($tsml_custom_meeting_fields as $field => $title) {
                    if (!empty($meeting_meta[$post->ID][$field])) {
                        $meeting[$field] = $meeting_meta[$post->ID][$field];
                    }
                }
            }

            // Include the data source when doing a full export
            if ($full_export && isset($meeting_meta[$post->ID]['data_source'])) {
                $meeting['data_source'] = $meeting_meta[$post->ID]['data_source'];
                $meeting['data_source_name'] = $tsml_data_sources[$meeting_meta[$post->ID]['data_source']]['name'];
            }

            // include the name of the data source
            if (!empty($meeting_meta[$post->ID]['data_source']) && !empty($tsml_data_sources[$meeting_meta[$post->ID]['data_source']]['name'])) {
                $meeting['data_source_name'] = $tsml_data_sources[$meeting_meta[$post->ID]['data_source']]['name'];
            }

            // Include the source slug, modified slug, and formatted address
            if (!empty($meeting_meta[$post->ID]['data_source'])) {
                foreach (array_keys($tsml_source_fields_map) as $source_field) {
                    $meeting[$source_field] = isset($meeting_meta[$post->ID][$source_field]) ? $meeting_meta[$post->ID][$source_field] : null;
                }
            }

            // append contact info to meeting
            if (!empty($meeting_meta[$post->ID]['group_id']) && array_key_exists($meeting_meta[$post->ID]['group_id'], $groups)) {
                $meeting = array_merge($meeting, $groups[$meeting_meta[$post->ID]['group_id']]);
            } else {
                foreach ($tsml_contact_fields as $field => $type) {
                    if (!empty($meeting_meta[$post->ID][$field])) {
                        $meeting[$field] = $meeting_meta[$post->ID][$field];
                    }
                }
            }

            // Only show contact information when 'public' or doing a full export
            if ($tsml_contact_display !== 'public' && !$full_export) {
                for ($i = 1; $i < 4; $i++) {
                    unset($meeting['contact_' . $i . '_name']);
                    unset($meeting['contact_' . $i . '_email']);
                    unset($meeting['contact_' . $i . '_phone']);
                }
            }

            // Ensure each meeting has an address approximate value
            if (empty($meeting['approximate'])) {
                $meeting['approximate'] = 'no';
            }

            // Calculate the attendance option
            $meeting['attendance_option'] = tsml_calculate_attendance_option($meeting['types'], $meeting['approximate']);

            // Remove TC when online only meeting has approximate address
            if (!empty($meeting['types']) && $meeting['attendance_option'] == 'online' && $meeting['approximate'] == 'yes') {
                $meeting['types'] = array_values(array_diff($meeting['types'], ['TC']));
            }

            // add feedback_url only if present
            if ($feedback_url = tsml_feedback_url($meeting)) {
                $meeting['feedback_url'] = $feedback_url;
            }

            // add entity fields
            if (!empty($meeting_meta[$post->ID]['data_source'])) {
                // add sourced entity fields
                foreach ($tsml_entity_fields as $entity_field) {
                    if (isset($meeting_meta[$post->ID][$entity_field])) {
                        $meeting[$entity_field] = $meeting_meta[$post->ID][$entity_field];
                    }
                }
            } else {
                // else add local entity info
                $meeting = array_merge($meeting, $tsml_entity);
            }

            // ensure array fields are arrays
            foreach ($tsml_array_fields as $array_field) {
                if (isset($meeting[$array_field]) && !is_array($meeting[$array_field])) {
                    $meeting[$array_field] = array_values((array) $meeting[$array_field]);
                }
            }

            $meetings[] = $meeting;
        }

        // Clean up the array
        $meetings = array_map(function ($meeting) {
            foreach ($meeting as $key => $value) {
                if (empty($meeting[$key]) && $meeting[$key] !== '0') {
                    unset($meeting[$key]);
                } elseif (in_array($key, ['id', 'day', 'latitude', 'longitude', 'location_id', 'group_id', 'region_id', 'district_id'])) {
                    $meeting[$key] -= 0;
                }
            }
            return $meeting;
        }, $meetings);

        // write array to cache
        if (!$full_export) {
            $filepath = WP_CONTENT_DIR . $tsml_cache;
            // Check if the file is writable, and if so, write it
            if (count($meetings) && is_writable($filepath) || (!file_exists($filepath) && is_writable(WP_CONTENT_DIR))) {
                $filesize = file_put_contents($filepath, json_encode($meetings));
                update_option('tsml_cache_writable', $filesize === false ? 0 : 1);
            } else {
                update_option('tsml_cache_writable', 0);
            }
        }
    }

    // check if we are filtering
    $allowed = [
        'mode',
        'data_source',
        'day',
        'time',
        'region',
        'district',
        'type',
        'query',
        'group_id',
        'location_id',
        'latitude',
        'longitude',
        'distance_units',
        'distance',
        'attendance_option'
    ];
    if ($arguments = array_intersect_key($arguments, array_flip($allowed))) {
        $filter = new tsml_filter_meetings($arguments);
        $meetings = $filter->apply($meetings);
    }

    // sort meetings
    usort($meetings, function ($a, $b) {
        global $tsml_days_order, $tsml_sort_by;

        // sub_regions are regions in this scenario
        if (!empty($a['sub_region'])) {
            $a['region'] = $a['sub_region'];
        }
        if (!empty($b['sub_region'])) {
            $b['region'] = $b['sub_region'];
        }

        // custom sort order?
        if ($tsml_sort_by !== 'time') {
            if ($a[$tsml_sort_by] != $b[$tsml_sort_by]) {
                return strcmp($a[$tsml_sort_by], $b[$tsml_sort_by]);
            }
        }

        // get the user-settable order of days
        $a_day_index = isset($a['day']) && strlen($a['day']) ? array_search($a['day'], $tsml_days_order) : false;
        $b_day_index = isset($b['day']) && strlen($b['day']) ? array_search($b['day'], $tsml_days_order) : false;
        if ($a_day_index === false && $b_day_index !== false) {
            return 1;
        } elseif ($a_day_index !== false && $b_day_index === false) {
            return -1;
        } elseif ($a_day_index != $b_day_index) {
            return $a_day_index - $b_day_index;
        } else {
            // days are the same or both null
            $a_time = empty($a['time']) ? '' : (($a['time'] == '00:00') ? '23:59' : $a['time']);
            $b_time = empty($b['time']) ? '' : (($b['time'] == '00:00') ? '23:59' : $b['time']);
            $time_diff = strcmp($a_time, $b_time);
            if ($time_diff) {
                return $time_diff;
            } else {
                $a_location = empty($a['location']) ? '' : $a['location'];
                $b_location = empty($b['location']) ? '' : $b['location'];
                $location_diff = strcmp($a_location, $b_location);
                if ($location_diff) {
                    return $location_diff;
                } else {
                    $a_name = empty($a['name']) ? '' : $a['name'];
                    $b_name = empty($b['name']) ? '' : $b['name'];
                    return strcmp($a_name, $b_name);
                }
            }
        }
    });

    return $meetings;
}

/**
 * get metadata for all meetings very quickly
 * called in tsml_get_meetings(), tsml_get_locations()
 * 
 * @param mixed $type
 * @param mixed $id
 * @return mixed
 */
function tsml_get_meta($type, $id = null)
{
    global $wpdb, $tsml_custom_meeting_fields, $tsml_contact_fields, $tsml_source_fields_map, $tsml_entity_fields, $tsml_array_fields;
    $keys = [
        'tsml_group' => array_keys($tsml_contact_fields),
        'tsml_location' => ['formatted_address', 'latitude', 'longitude', 'approximate', 'timezone'],
        'tsml_meeting' => array_merge(
            [
                'day',
                'time',
                'end_time',
                'types',
                'group_id',
                'conference_url',
                'conference_url_notes',
                'conference_phone',
                'conference_phone_notes',
                'data_source',
            ],
            array_keys($tsml_contact_fields),
            array_keys($tsml_source_fields_map),
            $tsml_entity_fields,
            empty($tsml_custom_meeting_fields) ? [] : array_keys($tsml_custom_meeting_fields)
        ),
    ];

    if (!array_key_exists($type, $keys)) {
        return trigger_error('tsml_get_meta for unexpected type ' . esc_html($type));
    }
    $meta = [];
    $field_names_for_sql = implode(', ', array_map(function ($field) {
        return '"' . $field . '"';
    }, $keys[$type]));
    $query = 'SELECT post_id, meta_key, meta_value FROM ' . $wpdb->postmeta . ' WHERE
		meta_key IN (' . $field_names_for_sql . ') AND
		post_id ' . ($id ? '= ' . $id : 'IN (SELECT id FROM ' . $wpdb->posts . ' WHERE post_type = "' . $type . '")');
    $values = $wpdb->get_results($query);
    foreach ($values as $value) {
        if (in_array($value->meta_key, $tsml_array_fields, true)) {
            $value->meta_value = array_values((array) maybe_unserialize($value->meta_value));
        }
        $meta[$value->post_id][$value->meta_key] = $value->meta_value;
    }

    // get taxonomy
    if ($type == 'tsml_location') {
        $regions = $wpdb->get_results('SELECT
				r.`object_id` location_id,
				t.`term_id` region_id,
				t.`name` region
			FROM ' . $wpdb->term_relationships . ' r
			JOIN ' . $wpdb->term_taxonomy . ' x ON r.term_taxonomy_id = x.term_taxonomy_id
			JOIN ' . $wpdb->terms . ' t ON x.term_id = t.term_id
			WHERE x.taxonomy = "tsml_region"');
        foreach ($regions as $region) {
            $meta[$region->location_id]['region'] = $region->region;
            $meta[$region->location_id]['region_id'] = $region->region_id;
        }
    } elseif ($type == 'tsml_group') {
        $districts = $wpdb->get_results('SELECT
				r.`object_id` group_id,
				t.`term_id` district_id,
				t.`name` district
			FROM ' . $wpdb->term_relationships . ' r
			JOIN ' . $wpdb->term_taxonomy . ' x ON r.term_taxonomy_id = x.term_taxonomy_id
			JOIN ' . $wpdb->terms . ' t ON x.term_id = t.term_id
			WHERE x.taxonomy = "tsml_district"');
        foreach ($districts as $district) {
            $meta[$district->group_id]['district'] = $district->district;
            $meta[$district->group_id]['district_id'] = $district->district_id;
        }
    }

    if ($id) {
        return array_key_exists($id, $meta) ? $meta[$id] : [];
    }
    return $meta;
}

/**
 * get an array from wp options and confirm it's an array
 * 
 * @param mixed $option
 * @param mixed $default
 * @return mixed
 */
function tsml_get_option_array($option, $default = [])
{
    $value = get_option($option, $default);
    return is_array($value) ? $value : $default;
}
