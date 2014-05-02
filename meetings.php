<?php
/**
 * Plugin Name: Meetings
 * Plugin URI: https://github.com/intergroup/plugin
 * Description: CMS for maintaining lists of meetings and locations
 * Version: 1.0
 * Author: Santa Clara County Intergroup
 * Author URI: http://aasanjose.org
 * License: none
 */

$days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');

add_action('admin_init', function(){
	include('hooks/admin_init.php');
});

add_action('init', function(){
	include('hooks/init.php');
});

add_action('save_post', function(){
	include('hooks/save_post.php');
});

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

add_action('restrict_manage_posts', function() {
	global $typenow, $days;
	if ($typenow == 'meetings') {
		echo '<select name="day"><option>All days</option>';
			foreach ($days as $key=>$day) {
				echo '<option value="' . $key . '"' . selected($key, $_GET['day']) . '>' . $day . '</option>';
			}
		echo '
		</select>
		<select name="time">
			<option>All times</option>
			<option value="morning">Morning</option>
			<option value="afternoon">Morning</option>
			<option value="evening">Evening</option>
			<option value="night">Night</option>
		</select>
		<select name="region">
			<option>Everywhere</option>
		</select>';
	}
});

add_filter('months_dropdown_results', '__return_empty_array');

function meetings_format_time($string) {
	if (!strstr($string, ':')) return 'n/a';
	if ($string == '12:00') return 'Noon';
	if ($string == '23:59') return 'Midnight';
	list($hours, $minutes) = explode(':', $string);
	$ampm = ($hours > 11) ? 'PM' : 'AM';
	$hours = ($hours > 12) ? $hours - 12 : $hours;
	return $hours . ':' . $minutes . ' ' . $ampm;
}