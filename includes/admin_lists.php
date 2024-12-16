<?php

// adding region dropdown to filter
add_action('restrict_manage_posts', function ($post_type) {
    global $tsml_program, $tsml_programs, $tsml_types_in_use;

    if ($post_type !== 'tsml_meeting') {
        return;
    }

    wp_dropdown_categories([
        'show_option_all' => 'Region',
        'orderby' => 'tax_name',
        'selected' => !empty($_GET['region']) ? sanitize_text_field($_GET['region']) : '',
        'hierarchical' => true,
        'name' => 'region',
        'taxonomy' => 'tsml_region',
        'hide_if_empty' => true,
    ]);

    $types = [];
    foreach ($tsml_types_in_use as $type) {
        $types[$type] = $tsml_programs[$tsml_program]['types'][$type];
    }
    asort($types);

    echo '<select name="type">';
    echo '<option value="">' . esc_html__('Type', '12-step-meeting-list') . '</option>';
    foreach ($types as $key => $value) {
        echo '<option value="' . esc_attr($key) . '"' . selected(isset($_GET['type']) && $_GET['type'] == $key) . '>' . esc_html($value) . '</option>';
    }
    echo '</select>';
}, 10, 1);

// if filter is set, restrict results
add_filter(
    'pre_get_posts',
    function ($query) {
        global $post_type, $pagenow, $wpdb;

        if ($pagenow === 'edit.php' && $post_type === 'tsml_meeting' && $query->is_main_query()) {

            if (!empty($_GET['region'])) {
                $parent_ids = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT p.ID FROM $wpdb->posts p 
	                    JOIN $wpdb->term_relationships r ON r.object_id = p.ID 
	                    JOIN $wpdb->term_taxonomy x ON x.term_taxonomy_id = r.term_taxonomy_id 
	                    WHERE x.term_id = %d",
                        intval(sanitize_text_field($_GET['region']))
                    )
                );
                $query->query_vars['post_parent__in'] = empty($parent_ids) ? [0] : $parent_ids;
            }

            if (!empty($_GET['type'])) {
                $query->set('meta_query', [
                    [
                        'key' => 'types',
                        'value' => '"' . sanitize_text_field($_GET['type']) . '"',
                        'compare' => 'LIKE',
                    ],
                ]);
            }
        }
    }
);

// custom columns for meetings
add_filter(
    'manage_edit-tsml_meeting_columns',
    function () {
        return [
            'cb' => '<input type="checkbox" />',
            'title' => __('Meeting', '12-step-meeting-list'),
            'day' => __('Day', '12-step-meeting-list'),
            'time' => __('Time', '12-step-meeting-list'),
            'region' => __('Region', '12-step-meeting-list'),
            'date' => __('Date', '12-step-meeting-list'),
        ];
    }
);

// if you're deleting meetings, also delete locations
add_action(
    'delete_post',
    function ($post_id) {
        $post = get_post($post_id);
        if ($post->post_type == 'tsml_meeting') {
            tsml_require_meetings_permission();
            tsml_delete_orphans();
        }
    }
);

// custom list values for meetings
add_action('manage_tsml_meeting_posts_custom_column', function ($column_name, $post_ID) {
    global $tsml_days, $wpdb;
    if ($column_name == 'day') {
        $day = get_post_meta($post_ID, 'day', true);
        echo (empty($day) && $day !== '0') ? esc_html__('Appointment', '12-step-meeting-list') : esc_html($tsml_days[$day]);
    } elseif ($column_name == 'time') {
        echo esc_html(tsml_format_time(get_post_meta($post_ID, 'time', true)));
    } elseif ($column_name == 'region') {
        // don't know how to do this with fewer queries
        echo esc_html($wpdb->get_var('SELECT t.name
			FROM ' . $wpdb->terms . ' t
			JOIN ' . $wpdb->term_taxonomy . ' x ON t.term_id = x.term_id
			JOIN ' . $wpdb->term_relationships . ' r ON x.term_taxonomy_id = r.term_taxonomy_id
			JOIN ' . $wpdb->posts . ' p ON r.object_id = p.post_parent
			WHERE p.ID = ' . intval($post_ID)));
    }
}, 10, 2);


// set custom meetings columns to be sortable
add_filter('manage_edit-tsml_meeting_sortable_columns', function ($columns) {
    $columns['day'] = 'day';
    $columns['time'] = 'time';
    return $columns;
});


// apply sorting
add_filter('request', function ($vars) {
    if (isset($vars['orderby'])) {
        switch ($vars['orderby']) {
            case 'day':
                return array_merge($vars, [
                    'meta_key' => 'day',
                    'orderby' => 'meta_value',
                ]);
            case 'time':
                return array_merge($vars, [
                    'meta_key' => 'time',
                    'orderby' => 'meta_value',
                ]);
        }
    }
    return $vars;
});


// remove quick edit because meetings could get messed up without custom fields
add_filter('post_row_actions', function ($actions) {
    global $post;
    if ($post->post_type == 'tsml_meeting') {
        unset($actions['inline hide-if-no-js']);
    }
    return $actions;
}, 10, 2);


// adding "Remove Temporary Closure" to Bulk Actions dropdown
add_filter('bulk_actions-edit-tsml_meeting', function ($bulk_array) {
    $bulk_array['tsml_open_in_person'] = __('Reopen for In Person Attendees', '12-step-meeting-list');
    return $bulk_array;
});



// handle removing Temporary Closures
add_filter('handle_bulk_actions-edit-tsml_meeting', function ($redirect, $doaction, $object_ids) {
    tsml_require_meetings_permission();

    // handle tsml_add_tc
    // let's remove query args first
    $redirect = remove_query_arg(['tsml_add_tc'], $redirect);
    $redirect = remove_query_arg(['tsml_remove_tc'], $redirect);
    $redirect = remove_query_arg(['tsml_open_in_person'], $redirect);

    // do something for "Add Temporary Closure" bulk action
    if ($doaction == 'tsml_add_tc') {
        $count = 0;
        foreach ($object_ids as $post_id) {
            // for each select post, add TC if it's not selected in "types"
            $types = get_post_meta($post_id, 'types', false)[0];
            if (!in_array('TC', array_values($types))) {
                $types[] = 'TC';
                update_post_meta($post_id, 'types', array_map('esc_attr', $types));

                $count++;
            }
        }

        if ($count > 0) {
            // rebuild cache
            tsml_cache_rebuild();
            // update types in use
            tsml_update_types_in_use();
            // add number of meetings changed to query args
            $redirect = add_query_arg('tsml_add_tc', $count, $redirect);
        }
    }

    // do something for "Remove Temporary Closure" bulk action
    if ($doaction == 'tsml_remove_tc') {
        $count = 0;
        foreach ($object_ids as $post_id) {
            // For each select post, remove TC if it's selected in "types"
            $types = get_post_meta($post_id, 'types', false)[0];
            if (!empty($types) && in_array('TC', array_values($types))) {
                $types = array_diff($types, ['TC']);
                if (empty($types)) {
                    delete_post_meta($post_id, 'types');
                } else {
                    update_post_meta($post_id, 'types', array_map('esc_attr', $types));
                }

                $count++;
            }
        }

        if ($count > 0) {
            // rebuild cache
            tsml_cache_rebuild();
            // update types in use
            tsml_update_types_in_use();
            // add number of meetings changed to query args
            $redirect = add_query_arg('tsml_remove_tc', $count, $redirect);
        }
    }

    // do something for "Remove Temporary Closure" bulk action
    if ($doaction == 'tsml_open_in_person') {
        $count = 0;
        foreach ($object_ids as $post_id) {
            $meeting = tsml_get_meeting($post_id);

            if ($meeting->attendance_option == 'in_person' || $meeting->attendance_option == 'hybrid') {
                continue;
            }

            if (empty($meeting->formatted_address) || tsml_geocode($meeting->formatted_address)['approximate'] != 'no') {
                continue;
            }

            // for each select post, remove TC if it's selected in "types"
            $types = get_post_meta($post_id, 'types', false)[0];
            if (!empty($types) && in_array('TC', array_values($types))) {
                $types = array_diff($types, ['TC']);
                if (empty($types)) {
                    delete_post_meta($post_id, 'types');
                } else {
                    update_post_meta($post_id, 'types', array_map('esc_attr', $types));
                }
            }
            $count++;
        }

        if ($count > 0) {
            // rebuild cache
            tsml_cache_rebuild();
            // update types in use
            tsml_update_types_in_use();
            // add number of meetings changed to query args
            $redirect = add_query_arg('tsml_open_in_person', $count, $redirect);
        }
    }

    return $redirect;
}, 10, 3);

// notify how many Temporary Closures were removed
add_action(
    'admin_notices',
    function () {
        if (!empty($_REQUEST['tsml_add_tc'])) {
            // depending on how many posts were changed, make the message different
            tsml_alert(sprintf(
                // translators: %s is the number of meetings that were changed
                _n(
                    'Temporary Closure added to %s meeting',
                    'Temporary Closure added to %s meetings',
                    intval($_REQUEST['tsml_add_tc']),
                    '12-step-meeting-list'
                ),
                intval($_REQUEST['tsml_add_tc'])
            ));
        }
        if (!empty($_REQUEST['tsml_remove_tc'])) {
            // depending on how many posts were changed, make the message different
            tsml_alert(sprintf(
                // translators: %s is the number of meetings that were changed
                _n(
                    'Temporary Closure removed from %s meeting',
                    'Temporary Closure removed from %s meetings',
                    intval($_REQUEST['tsml_remove_tc']),
                    '12-step-meeting-list'
                ),
                intval($_REQUEST['tsml_remove_tc'])
            ));
        }
        if (!empty($_REQUEST['tsml_open_in_person'])) {
            // depending on how many posts were changed, make the message different
            tsml_alert(sprintf(
                // translators: %s is the number of meetings that were changed
                _n(
                    '%s meeting reopened for in person attendees',
                    '%s meetings reopended for in person attendees',
                    intval($_REQUEST['tsml_open_in_person']),
                    '12-step-meeting-list'
                ),
                intval($_REQUEST['tsml_open_in_person'])
            ));
        }
    }
);

// special global var to store region counts
$tsml_region_counts = [];

// customizing the "count" column for regions - would be nice if we could use the existing 'posts' column for sorting
add_filter('manage_edit-tsml_region_columns', function ($columns) {
    global $wpdb, $tsml_region_counts;

    // get region meeting counts (regions are associated with locations, not meetings)
    $results = $wpdb->get_results('SELECT 
        x.term_id, 
        (SELECT COUNT(*) FROM ' . $wpdb->posts . ' p WHERE p.post_parent IN 
            (SELECT tr.object_id FROM ' . $wpdb->term_relationships . ' tr WHERE tr.term_taxonomy_id = x.term_taxonomy_id )
        ) AS meetings
        FROM wp_term_taxonomy x
        WHERE x.taxonomy = "tsml_region"');
    foreach ($results as $result) {
        $tsml_region_counts[$result->term_id] = $result->meetings;
    }

    unset($columns['posts']);
    $columns['meetings'] = __('Meetings', '12-step-meeting-list');
    return $columns;
});

// customizing the "count" column for regions
add_action('manage_tsml_region_custom_column', function ($string, $column_name, $term_id) {
    global $tsml_region_counts;
    if ($column_name === 'meetings') {
        $term = get_term($term_id, 'tsml_region');
        $query = http_build_query([
            'post_status' => 'all',
            'post_type' => 'tsml_meeting',
            'region' => $term->term_id,
            'filter_action' => 'Filter',
            'paged' => 1,
        ]);
        return '<a href="' . admin_url("edit.php?$query") . '">' .
            @$tsml_region_counts[$term->term_id] .
            '</a>';
    }
    return $string;
}, 10, 3);
