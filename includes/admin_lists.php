<?php

add_filter('manage_edit-meetings_columns', function($defaults){
    return array(
    	'cb'=>'<input type="checkbox" />',
    	'title' => 'Title',
    	'day'	=>'Day',
    	'time'	=>'Time',
    	'date' => 'Date'
    );	
});

add_filter('manage_edit-locations_columns', function($defaults){
    return array(
    	'title' => 'Title',
    	'date' => 'Date'
    );	
});

add_action('manage_meetings_posts_custom_column', function($column_name, $post_ID){
	global $days;
	if ($column_name == 'day') {
		echo @$days[get_post_meta($post_ID, 'day', true)];
	} elseif ($column_name == 'time') {
		echo meetings_format_time(get_post_meta($post_ID, 'time', true));
	}
}, 10, 2);

add_filter('manage_edit-meetings_sortable_columns', function($columns){
	$columns['day'] = 'day';
	$columns['time'] = 'time';
	return $columns;
});

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
    	}
    }
    return $vars;
});

/*
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
			echo '<option value="' . $key . '">' . $region . '</option>';
		}

		echo '</select>';
	}
});

add_filter('months_dropdown_results', '__return_empty_array');
*/
