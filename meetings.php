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

$days	= array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
$states = array('AL'=>'Alabama', 'AK'=>'Alaska', 'AZ'=>'Arizona', 'AR'=>'Arkansas', 'CA'=>'California',  
	'CO'=>'Colorado', 'CT'=>'Connecticut', 'DE'=>'Delaware', 'DC'=>'District of Columbia', 'FL'=>'Florida',  
	'GA'=>'Georgia', 'HI'=>'Hawaii', 'ID'=>'Idaho', 'IL'=>'Illinois', 'IN'=>'Indiana', 'IA'=>'Iowa',  
	'KS'=>'Kansas', 'KY'=>'Kentucky', 'LA'=>'Louisiana', 'ME'=>'Maine', 'MD'=>'Maryland', 'MA'=>'Massachusetts',  
	'MI'=>'Michigan', 'MN'=>'Minnesota', 'MS'=>'Mississippi', 'MO'=>'Missouri', 'MT'=>'Montana',
	'NE'=>'Nebraska', 'NV'=>'Nevada', 'NH'=>'New Hampshire', 'NJ'=>'New Jersey', 'NM'=>'New Mexico',
	'NY'=>'New York', 'NC'=>'North Carolina', 'ND'=>'North Dakota', 'OH'=>'Ohio', 'OK'=>'Oklahoma',  
	'OR'=>'Oregon', 'PA'=>'Pennsylvania', 'RI'=>'Rhode Island', 'SC'=>'South Carolina', 'SD'=>'South Dakota',
	'TN'=>'Tennessee', 'TX'=>'Texas', 'UT'=>'Utah', 'VT'=>'Vermont', 'VA'=>'Virginia', 'WA'=>'Washington',  
	'WV'=>'West Virginia', 'WI'=>'Wisconsin', 'WY'=>'Wyoming'
);
$types = array(
	'H'=>'Chips', 
	'C'=>'Closed', 
	'G'=>'Gay',
	'L'=>'Lesbian',
	'M'=>'Men Only', 
	'O'=>'Open',
	'S'=>'Spanish',
	'X'=>'Wheelchair Accessible',
	'W'=>'Women Only',
	'Y'=>'Young People',
);

$regions = $custom = array();

add_action('admin_init', function(){
	include('hooks/admin_init.php');
});

add_action('admin_menu', function() {
	include('hooks/admin_menu.php');
});

add_action('init', function(){
	include('hooks/init.php');
});

add_action('save_post', function(){
	include('hooks/save_post.php');
});

//for the typeahead
add_action('wp_ajax_location', function(){
	$locations = get_posts('post_type=locations&numberposts=-1');
	$results = array();
    foreach ($locations as $location) {
        $title  = get_the_title($location->ID);
        $custom = get_post_meta($location->ID);
        $results[] = array(
            'value'		=> $title,
            'address'	=> $custom['address'][0],
            'latitude'	=> $custom['latitude'][0],
            'longitude'	=> $custom['longitude'][0],
            'region'	=> $custom['region'][0],
            'tokens'	=> array_values(array_unique(explode(' ', str_replace(',', '', $title . ' ' . $custom['address'][0])))),
        );
	}
	wp_send_json($results);
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

function meetings_format_time($string) {
	//takes 18:30 and returns 6:30 PM
	if (!strstr($string, ':')) return 'n/a';
	if ($string == '12:00') return 'Noon';
	if ($string == '23:59') return 'Midnight';
	list($hours, $minutes) = explode(':', $string);
	$ampm = ($hours > 11) ? 'PM' : 'AM';
	$hours = ($hours > 12) ? $hours - 12 : $hours;
	return $hours . ':' . $minutes . ' ' . $ampm;
}

function meetings_delete_all_locations() {
	//deletes all the locations
	$locations = get_posts('post_type=locations&numberposts=-1');
	foreach ($locations as $location) {
		wp_delete_post($location->ID, true);
	}
}



