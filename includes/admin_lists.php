<?php

# Custom columns for meetings
add_filter('manage_edit-meetings_columns', function($defaults){
    return array(
    	'cb'		=>'<input type="checkbox" />',
    	'title'		=>'Title',
    	'day'		=>'Day',
    	'time'		=>'Time',
    	'region'	=>'Region',
    	'date'		=>'Date'
    );	
});

# Custom columns for locations
add_filter('manage_edit-locations_columns', function($defaults){
    return array(
    	'title'		=> 'Title',
    	'address'	=> 'Address',
    	'city'		=> 'City',
    	'date'		=> 'Date'
    );	
});

# If you're deleting meetings, also delete locations
add_action('delete_post', function($post_id) {
	$post = get_post($post_id);
	if ($post->post_type == 'meetings') tsml_delete_orphaned_locations();
});

# Custom list values for meetings
add_action('manage_meetings_posts_custom_column', function($column_name, $post_ID){
	global $tsml_days, $tsml_regions;
	if ($column_name == 'day') {
		echo @$tsml_days[get_post_meta($post_ID, 'day', true)];
	} elseif ($column_name == 'time') {
		echo tsml_format_time(get_post_meta($post_ID, 'time', true));
	} elseif ($column_name == 'region') {
		echo @$tsml_regions[get_post_meta($post_ID, 'region', true)];
	}
}, 10, 2);

# Custom list values for locations
add_action('manage_locations_posts_custom_column', function ($column_name, $post_ID) {
	if ($column_name == 'address') {
		echo get_post_meta($post_ID, 'address', true);
	} elseif ($column_name == 'city') {
		echo get_post_meta($post_ID, 'city', true);
	}
}, 10, 2);

# Set custom meetings columns to be sortable
add_filter('manage_edit-meetings_sortable_columns', function($columns){
	$columns['day']		= 'day';
	$columns['time']	= 'time';
	$columns['region']	= 'region';
	return $columns;
});

# Apply sorting
add_filter('request', function($vars) {
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
});

//remove quick edit because meetings could get messed up without custom fields
if (is_admin()) {
	add_filter('post_row_actions',function($actions) {
		global $post;
	    if ($post->post_type == 'meetings') {
			unset($actions['inline hide-if-no-js']);
		}
	    return $actions;
	},10,2);
}
