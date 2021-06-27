<?php

# Adding region dropdown to filter
add_action('restrict_manage_posts', 'tsml_restrict_manage_posts');
function tsml_restrict_manage_posts()
{

    if (@$_GET['post_type'] != 'tsml_meeting') {
        return;
    }

    wp_dropdown_categories(array(
        'show_option_all' => 'Region',
        'orderby' => 'tax_name',
        'hide_empty' => true,
        'selected' => @$_GET['region'],
        'hierarchical' => true,
        'name' => 'region',
        'taxonomy' => 'tsml_region',
        'hide_if_empty' => true,
        'value_field' => 'term_id',
    ));
}

# If filter is set, restrict results
add_filter('parse_query', 'tsml_posts_filter');
function tsml_posts_filter($query)
{
    global $pagenow, $wpdb;

    if (($pagenow != 'edit.php') || (@$_GET['post_type'] != 'tsml_meeting') || empty($_GET['region'])) {
        return;
    }

    $parent_ids = $wpdb->get_col('SELECT p.ID FROM ' . $wpdb->posts . ' p
		JOIN ' . $wpdb->term_relationships . ' r ON r.object_id = p.ID
		JOIN ' . $wpdb->term_taxonomy . ' x ON x.term_taxonomy_id = r.term_taxonomy_id
		WHERE x.term_id = ' . intval($_GET['region']));

    if (empty($parent_ids)) {
        $parent_ids = array(0);
    }
    //impossible scenario to show no results

    $query->query_vars['post_parent__in'] = $parent_ids;

}

# Custom columns for meetings
add_filter('manage_edit-tsml_meeting_columns', 'tsml_admin_meetings_columns');
function tsml_admin_meetings_columns($defaults)
{
    return array(
        'cb' => '<input type="checkbox" />',
        'title' => __('Meeting', '12-step-meeting-list'),
        'day' => __('Day', '12-step-meeting-list'),
        'time' => __('Time', '12-step-meeting-list'),
        'region' => __('Region'),
        'date' => __('Date', '12-step-meeting-list'),
    );
}

# If you're deleting meetings, also delete locations
add_action('delete_post', 'tsml_delete_post');
function tsml_delete_post($post_id)
{
    $post = get_post($post_id);
    if ($post->post_type == 'tsml_meeting') {
        tsml_delete_orphans();
    }

}

# Custom list values for meetings
add_action('manage_tsml_meeting_posts_custom_column', 'tsml_admin_meetings_custom_column', 10, 2);
function tsml_admin_meetings_custom_column($column_name, $post_ID)
{
    global $tsml_days, $wpdb;
    if ($column_name == 'day') {
        $day = get_post_meta($post_ID, 'day', true);
        echo (empty($day) && $day !== '0') ? __('Appointment', '12-step-meeting-list') : $tsml_days[$day];
    } elseif ($column_name == 'time') {
        echo tsml_format_time(get_post_meta($post_ID, 'time', true));
    } elseif ($column_name == 'region') {
        //don't know how to do this with fewer queries
        echo $wpdb->get_var('SELECT t.name
			FROM ' . $wpdb->terms . ' t
			JOIN ' . $wpdb->term_taxonomy . ' x ON t.term_id = x.term_id
			JOIN ' . $wpdb->term_relationships . ' r ON x.term_taxonomy_id = r.term_taxonomy_id
			JOIN ' . $wpdb->posts . ' p ON r.object_id = p.post_parent
			WHERE p.ID = ' . $post_ID);
    }
}

# Set custom meetings columns to be sortable
add_filter('manage_edit-tsml_meeting_sortable_columns', 'tsml_admin_meetings_sortable_columns');
function tsml_admin_meetings_sortable_columns($columns)
{
    $columns['day'] = 'day';
    $columns['time'] = 'time';
    //$columns['region']    = 'region';
    return $columns;
}

# Apply sorting
add_filter('request', 'tsml_sorting');
function tsml_sorting($vars)
{
    if (isset($vars['orderby'])) {
        switch ($vars['orderby']) {
            case 'day':
                return array_merge($vars, array(
                    'meta_key' => 'day',
                    'orderby' => 'meta_value',
                ));
            case 'time':
                return array_merge($vars, array(
                    'meta_key' => 'time',
                    'orderby' => 'meta_value',
                ));
                /* case 'region':
        return array_merge($vars, array(
        'meta_key' => 'region',
        'orderby' => 'meta_value'
        ));
         */
        }
    }
    return $vars;
}

//remove quick edit because meetings could get messed up without custom fields
add_filter('post_row_actions', 'tsml_post_row_actions', 10, 2);
function tsml_post_row_actions($actions)
{
    global $post;
    if ($post->post_type == 'tsml_meeting') {
        unset($actions['inline hide-if-no-js']);
    }
    return $actions;
}

# Adding "Remove Temporary Closure" to Bulk Actions dropdown
add_filter('bulk_actions-edit-tsml_meeting', 'tsml_my_bulk_actions');

function tsml_my_bulk_actions($bulk_array)
{
    //$bulk_array['tsml_add_tc'] = __('Add Temporary Closure', '12-step-meeting-list');
    //$bulk_array['tsml_remove_tc'] = __('Remove Temporary Closure', '12-step-meeting-list');
    /*
    $bulk_array['tsml_remove_onl'] = __('Remove Online Meeting', '12-step-meeting-list');
    */
    $bulk_array['tsml_open_in_person'] = __('Reopen for In Person Attendees', '12-step-meeting-list');
    return $bulk_array;
}

# Handle removing Temporary Closures
add_filter('handle_bulk_actions-edit-tsml_meeting', 'tsml_bulk_action_handler', 10, 3);

function tsml_bulk_action_handler($redirect, $doaction, $object_ids)
{
    // Handle tsml_add_tc
    // let's remove query args first
    $redirect = remove_query_arg(array('tsml_add_tc'), $redirect);
    $redirect = remove_query_arg(array('tsml_remove_tc'), $redirect);
    $redirect = remove_query_arg(array('tsml_open_in_person'), $redirect);
    //$redirect = remove_query_arg(array('tsml_remove_onl'), $redirect);

    // do something for "Add Temporary Closure" bulk action
    if ($doaction == 'tsml_add_tc') {
        $count = 0;
        foreach ($object_ids as $post_id) {
            // For each select post, add TC if it's not selected in "types"
            $types = get_post_meta($post_id, 'types', false)[0];
            if (!in_array('TC', array_values($types))) {
                $types[] = 'TC';
                update_post_meta($post_id, 'types', array_map('esc_attr', $types));

                $count++;
            }
        }

        if ($count > 0) {
            //rebuild cache
            tsml_cache_rebuild();
            //update types in use
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
                $types = array_diff($types, array('TC'));
                if (empty($types)) {
                    delete_post_meta($post_id, 'types');
                } else {
                    update_post_meta($post_id, 'types', array_map('esc_attr', $types));
                }

                $count++;
            }
        }

        if ($count > 0) {
            //rebuild cache
            tsml_cache_rebuild();
            //update types in use
            tsml_update_types_in_use();
            // add number of meetings changed to query args
            $redirect = add_query_arg('tsml_remove_tc', $count, $redirect);
        }
    }

    /*
    // do something for "Remove Online Meeting" bulk action
    if ($doaction == 'tsml_remove_onl') {
        $count = 0;
        foreach ($object_ids as $post_id) {
            // For each select post, remove TC if it's selected in "types"
            $types = get_post_meta($post_id, 'types', false)[0];
            if (!empty($types) && in_array('ONL', array_values($types))) {
                $types = array_diff($types, array('ONL'));
                if (empty($types)) {
                    delete_post_meta($post_id, 'types');
                } else {
                    update_post_meta($post_id, 'types', array_map('esc_attr', $types));
                }

                // Delete conference URL and phone if they exist
                if (!empty(get_post_meta($post_id, 'conference_url'))) {
                    delete_post_meta($post_id, 'conference_url');
                }
                if (!empty(get_post_meta($post_id, 'conference_phone'))) {
                    delete_post_meta($post_id, 'conference_phone');
                }

                $count++;
            }
        }

        if ($count > 0) {
            //rebuild cache
            tsml_cache_rebuild();
            //update types in use
            tsml_update_types_in_use();
            // add number of meetings changed to query args
            $redirect = add_query_arg('tsml_remove_onl', $count, $redirect);
        }
    }
    */

    // do something for "Remove Temporary Closure" bulk action
    if ($doaction == 'tsml_open_in_person') {
        $count = 0;
        foreach ($object_ids as $post_id) {
            $meeting = tsml_get_meeting($post_id);

            if ($meeting->attendance_option == 'in_person' || $meeting->attendance_option == 'hybrid') continue;

            if (empty($meeting->formatted_address) || tsml_geocode($meeting->formatted_address)['approximate'] != 'no') continue;

            if (!empty($meeting->conference_url) || !empty($meeting->conference_phone)) {
                $meeting->attendance_option = 'hybrid';
            } else {
                $meeting->attendance_option = 'in_person';
            }
            update_post_meta($post_id, 'attendance_option', $meeting->attendance_option);

            // For each select post, remove TC if it's selected in "types"
            $types = get_post_meta($post_id, 'types', false)[0];
            if (!empty($types) && in_array('TC', array_values($types))) {
                $types = array_diff($types, array('TC'));
                if (empty($types)) {
                    delete_post_meta($post_id, 'types');
                } else {
                    update_post_meta($post_id, 'types', array_map('esc_attr', $types));
                }
            }
            $count++;
        }

        if ($count > 0) {
            //rebuild cache
            tsml_cache_rebuild();
            //update types in use
            tsml_update_types_in_use();
            // add number of meetings changed to query args
            $redirect = add_query_arg('tsml_open_in_person', $count, $redirect);
        }
    }

    return $redirect;
}

// Notify how many Temporary Closures where removed
add_action('admin_notices', 'tsml_bulk_action_notices');

function tsml_bulk_action_notices () {
    if (!empty($_REQUEST['tsml_add_tc'])){
        // depending on how many posts were changed, make the message different
        printf('<div id="message" class="updated notice is-dismissible"><p>' .
            _n(
                'Temporary Closure added to %s meeting',
                'Temporary Closure added to %s meetings',
                intval($_REQUEST['tsml_add_tc'])
            ) . '</p></div>', intval($_REQUEST['tsml_add_tc']));
    }
    if (!empty($_REQUEST['tsml_remove_tc'])){
        // depending on how many posts were changed, make the message different
        printf('<div id="message" class="updated notice is-dismissible"><p>' .
            _n(
                'Temporary Closure removed from %s meeting',
                'Temporary Closure removed from %s meetings',
                intval($_REQUEST['tsml_remove_tc'])
            ) . '</p></div>', intval($_REQUEST['tsml_remove_tc']));
    }
    if (!empty($_REQUEST['tsml_open_in_person'])){
        // depending on how many posts were changed, make the message different
        printf('<div id="message" class="updated notice is-dismissible"><p>' .
            _n(
                '%s meeting reopened for in person attendees',
                '%s meetings reopended for in person attendees',
                intval($_REQUEST['tsml_open_in_person'])
            ) . '</p></div>', intval($_REQUEST['tsml_open_in_person']));
    }
    /*
    if (!empty($_REQUEST['tsml_remove_onl'])){
        // depending on how many posts were changed, make the message different
        printf('<div id="message" class="updated notice is-dismissible"><p>' .
            _n(
                'Online Meeting removed from %s meeting',
                'Online Meeting removed from %s meetings',
                intval($_REQUEST['tsml_remove_onl'])
            ) . '</p></div>', intval($_REQUEST['tsml_remove_onl']));
    }
    */
}
