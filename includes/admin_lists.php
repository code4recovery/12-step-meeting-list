<?php

# Custom columns for meetings
add_filter('manage_edit-meetings_columns', 'tmsl_admin_meetings_columns');
function tmsl_admin_meetings_columns($defaults) {
    return array(
    	'cb'		=>'<input type="checkbox" />',
    	'title'		=>__('Meeting', '12-step-meeting-list'),
    	'day'		=>__('Day', '12-step-meeting-list'),
    	'time'		=>__('Time', '12-step-meeting-list'),
    	'region'	=>__('Region', '12-step-meeting-list'),
    	'date'		=>__('Date', '12-step-meeting-list'),
    );	
}

# If you're deleting meetings, also delete locations
add_action('delete_post', 'tsml_delete_post');
function tsml_delete_post($post_id) {
	$post = get_post($post_id);
	if ($post->post_type == TSML_TYPE_MEETINGS) tsml_delete_orphaned_locations();
}

# Custom list values for meetings
add_action('manage_meetings_posts_custom_column', 'tmsl_admin_meetings_custom_column', 10, 2);
function tmsl_admin_meetings_custom_column($column_name, $post_ID) {
	global $tsml_days, $tsml_regions;
	if ($column_name == 'day') {
		$day = get_post_meta($post_ID, 'day', true);
		echo (empty($day) && $day !== '0') ? __('Appointment', '12-step-meeting-list') : $tsml_days[$day];
	} elseif ($column_name == 'time') {
		echo tsml_format_time(get_post_meta($post_ID, 'time', true));
	} elseif ($column_name == 'region') {
		echo @$tsml_regions[get_post_meta($post_ID, 'region', true)];
	}
}

# Set custom meetings columns to be sortable
add_filter('manage_edit-meetings_sortable_columns', 'tsml_admin_meetings_sortable_columns');
function tsml_admin_meetings_sortable_columns($columns) {
	$columns['day']		= 'day';
	$columns['time']	= 'time';
	$columns['region']	= 'region';
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
    		case 'region':
	    		return array_merge($vars, array(
		            'meta_key' => 'region',
		            'orderby' => 'meta_value'
		        ));
    	}
    }
    return $vars;
}

//remove quick edit because meetings could get messed up without custom fields
if (is_admin()) {
	add_filter('post_row_actions', 'tsml_post_row_actions', 10, 2);
	function tsml_post_row_actions($actions) {
		global $post;
	    if ($post->post_type == TSML_TYPE_MEETINGS) {
			unset($actions['inline hide-if-no-js']);
		}
	    return $actions;
	}
}

# Custom search // does this do anything?
add_action('pre_get_posts', 'tsml_pre_get_posts');
function tsml_pre_get_posts($query) {
	global $pagenow;
	if ($pagenow == 'edit.php' && $_GET['post_type'] == TSML_TYPE_MEETINGS) {
		//custom meeting search, can't use tsml_get_meetings() becuase of recursion
		//need to use wp-query to search locations and the address field
		//https://codex.wordpress.org/Plugin_API/Action_Reference/pre_get_posts
	}
}

# Customize post controls
add_action('restrict_manage_posts', 'tsml_restrict_manage_posts');
function tsml_restrict_manage_posts() {
	global $typenow;
	
	if ($typenow == TSML_TYPE_MEETINGS) {
		wp_dropdown_categories(array(
			'taxonomy' => 'region',
			'orderby' => 'name',
			'hierarchical' => true,
			'hide_if_empty' => true,
			'show_option_all' => __('Regions', '12-step-meeting-list'),
			'name' => 'region',
			'selected' => empty($_GET['region']) ? null : $_GET['region'],
		));
	}
}

# Make region control work on meeting admin list page
add_filter('parse_query', 'tsml_parse_query');
function tsml_parse_query($query){
    global $pagenow;
    $qv = &$query->query_vars;
    if ($pagenow == 'edit.php' && isset($qv['region']) && is_numeric($qv['region'])) {
		$term = get_term_by('id', $qv['region'], 'region');
		$qv['region'] = ($term ? $term->slug : '');
    }
}
