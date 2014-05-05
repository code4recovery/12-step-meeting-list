<?php

//add custom column headers
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


//add custom columns
add_action('manage_meetings_posts_custom_column', function($column_name, $post_ID){
	global $days, $regions;
	if ($column_name == 'day') {
		echo @$days[get_post_meta($post_ID, 'day', true)];
	} elseif ($column_name == 'time') {
		echo meetings_format_time(get_post_meta($post_ID, 'time', true));
	} elseif ($column_name == 'region') {
		echo @$regions[get_post_meta($post_ID, 'region', true)];
	}
}, 10, 2);


//set custom columns to be sortable
add_filter('manage_edit-meetings_sortable_columns', function($columns){
	$columns['day']		= 'day';
	$columns['time']	= 'time';
	$columns['region']	= 'region';
	return $columns;
});


//apply sorting
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


//remove quick edit
if (is_admin()) {
	add_filter('post_row_actions',function($actions) {
		global $post;
	    if ($post->post_type == 'meetings') {
			unset($actions['inline hide-if-no-js']);
		}
	    return $actions;
	},10,2);
}


/*add filter dropdowns to meeting list
add_action('restrict_manage_posts', function() {
	global $typenow, $days, $regions;
	if ($typenow == 'meetings') {
		echo '<select name="day"><option>All days</option>';
			foreach ($days as $key=>$day) {
				echo '<option value="' . $key . '"' . selected($key, $_GET['day']) . '>' . $day . '</option>';
			}
		echo '
		</select>
		<select name="region">
			<option>Everywhere</option>';

		foreach ($regions as $key=>$region) {
			echo '<option value="' . $key . '"' . selected($key, $_GET['region']) . '>' . $region . '</option>';
		}

		echo '</select>';
	}
});


//remove when posted filter
add_filter('months_dropdown_results', '__return_empty_array');


//actually filter by added dropdowns
add_filter('parse_query', function() {
    global $pagenow, $post_type;
    if (is_admin() && ($pagenow == 'edit.php') && !empty($_GET['region'])) {
		set_query_var('meta_query', array(array(
			'key' => 'region', 
			'value' => $_GET['region'],
			'type'=>'numeric',
		)));
    }
});
*/


//locations page
add_filter('manage_edit-locations_columns', function($defaults){
    return array(
    	'title' => 'Title',
    	'date' => 'Date'
    );	
});

