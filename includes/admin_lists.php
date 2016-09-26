<?php

# Custom columns for meetings
add_filter('manage_edit-meetings_columns', 'tmsl_admin_meetings_columns');
function tmsl_admin_meetings_columns($defaults) {
    return array(
    	'cb'		=>'<input type="checkbox" />',
    	'title'		=>__('Meeting'),
    	'day'		=>__('Day'),
    	'time'		=>__('Time'),
    	//'region'	=>__('Region'),
    	'date'		=>__('Date'),
    );	
}

# If you're deleting meetings, also delete locations
add_action('delete_post', 'tsml_delete_post');
function tsml_delete_post($post_id) {
	$post = get_post($post_id);
	if ($post->post_type == 'tsml_meeting') tsml_delete_orphaned_locations();
}

# Custom list values for meetings
add_action('manage_meetings_posts_custom_column', 'tmsl_admin_meetings_custom_column', 10, 2);
function tmsl_admin_meetings_custom_column($column_name, $post_ID) {
	global $tsml_days;
	if ($column_name == 'day') {
		$day = get_post_meta($post_ID, 'day', true);
		echo (empty($day) && $day !== '0') ? __('Appointment') : $tsml_days[$day];
	} elseif ($column_name == 'time') {
		echo tsml_format_time(get_post_meta($post_ID, 'time', true));
	}
}

# Set custom meetings columns to be sortable
add_filter('manage_edit-meetings_sortable_columns', 'tsml_admin_meetings_sortable_columns');
function tsml_admin_meetings_sortable_columns($columns) {
	$columns['day']		= 'day';
	$columns['time']	= 'time';
	//$columns['region']	= 'region';
	return $columns;
}

# Apply sorting
add_filter('request', 'tsml_sorting');
function tsml_sorting($vars) {
    if (isset($vars['orderby'])) {
    	switch($vars['orderby']) {
    		case 'day':
	    		return array_merge($vars, array(
		            'meta_key' => 'day',
		            'orderby' => 'meta_value'
		        ));
    		case 'time':
	    		return array_merge($vars, array(
		            'meta_key' => 'time',
		            'orderby' => 'meta_value'
		        ));
    	/*	case 'region':
	    		return array_merge($vars, array(
		            'meta_key' => 'region',
		            'orderby' => 'meta_value'
		        )); */
    	}
    }
    return $vars;
}

//remove quick edit because meetings could get messed up without custom fields
if (is_admin()) {
	add_filter('post_row_actions', 'tsml_post_row_actions', 10, 2);
	function tsml_post_row_actions($actions) {
		global $post;
	    if ($post->post_type == 'tsml_meeting') {
			unset($actions['inline hide-if-no-js']);
		}
	    return $actions;
	}
}