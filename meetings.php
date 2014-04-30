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

add_action('admin_head', function(){

});

add_action('admin_menu', function(){
	//
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
    	'date' => 'Date'
    );	
});

add_action('manage_meetings_posts_custom_column', function($column_name, $post_ID){
	global $days;
	if ($column_name == 'day') {
		echo @$days[get_post_meta($post_ID, 'day', true)];
	}
}, 10, 2);

add_filter('manage_edit-meetings_sortable_columns', function($columns){
	$columns['day'] = 'day';
	return $columns;
});

add_filter('request', function($vars) {
    if (isset($vars['orderby']) && 'day' == $vars['orderby']) {
        $vars = array_merge($vars, array(
            'meta_key' => 'day',
            'orderby' => 'meta_value'
        ));
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
		echo '</select>';
		echo '<select name="time"><option>All times</option></select>';
		echo '<select name="region"><option>Everywhere</option></select>';
	}
});

add_filter('months_dropdown_results', '__return_empty_array');

/* does not work
add_filter('parse_query', function($query) {
	
	if (is_admin() AND $query->query['post_type'] == 'meetings') {

	    $qv = &$query->query_vars;

		if (!empty($_GET['day'])) {
			$qv['meta_query'][] = array(
				'key' => 'day',
				'value' => $_GET['day'],
				'compare' => '=',
				'type'=>'NUMERIC',
			);

		}
	}
});
*/


/*
add_filter('pre_get_posts', function(){
	global $wp_query;
	$wp_query->set( 'orderby', 'meta_value' );
	$wp_query->set( 'meta_key', 'day' );
});
*/