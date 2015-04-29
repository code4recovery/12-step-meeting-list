<?php

//function: takes 18:30 and returns 6:30 p.m.
//used:		meetings_get and theme
function meetings_format_time($string) {
	if (!strstr($string, ':')) return 'n/a';
	if ($string == '12:00') return 'Noon';
	if ($string == '23:59') return 'Midnight';
	list($hours, $minutes) = explode(':', $string);
	$hours -= 0;
	$ampm = ($hours > 11) ? 'p.m.' : 'a.m.';
	$hours = ($hours > 12) ? $hours - 12 : $hours;
	return $hours . ':' . $minutes . ' ' . $ampm;
}

//function:	appends men or women if type present
//used:		archive-meetings.php
function meetings_name($name, $types) {
	if (in_array('M', $types)) {
		$name .= ' <small>Men</small>';
	} elseif (in_array('W', $types)) {
		$name .= ' <small>Women</small>';
	}
	return $name;
}

//function: deletes all the locations in the database
//used:		importer
function meetings_delete_all_locations() {
	//delete locations
	$locations = get_posts('post_type=locations&numberposts=-1');
	foreach ($locations as $location) {
		wp_delete_post($location->ID, true);
	}
	//delete associations with meetings
	$meetings = get_posts('post_type=meetings&numberposts=-1');
	foreach ($meetings as $meeting) {
		meetings_remove_location($meeting->ID);
	}
}

//function: deletes all the meetings in the database
//used:		importer
function meetings_delete_all_meetings() {
	//delete locations
	$meetings = get_posts('post_type=meetings&numberposts=-1');
	foreach ($meetings as $meeting) {
		wp_delete_post($meeting->ID, true);
	}
}

//function: remove all regions from database
//used: importer
function meetings_delete_all_regions() {
	$terms = get_terms('region', 'hide_empty=0');
	foreach ($terms as $term) {
		wp_delete_term($term->term_id, 'region');
	}
}

//function: load the regions array
//used: init, importer and api
function meetings_get_regions() {
	$regions = array();
	$region_terms = get_terms('region', 'hide_empty=0');
	foreach ($region_terms as $region) $regions[$region->term_id] = $region->name;
	return $regions;
}

//function: deletes all orphaned locations (has no meetings associated)
//used:		save_post filter and ad-hoc
function meetings_delete_orphaned_locations() {

	//get all active location_ids
	$active = array();
	$meetings = get_posts(array(
		'post_type'  =>'meetings',
		'numberposts'=>-1,
		'post_status'=> array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash'),
	));
	foreach ($meetings as $meeting) {
		$active[] = $meeting->post_parent;
	}

	//get all location ids
	$all_locations = array();
	$locations = get_posts('post_type=locations&numberposts=-1');
	foreach ($locations as $location) {
		$all_locations[] = $location->ID;
	}

	//foreach location id not active, delete it
	$inactive = array_diff($all_locations, $active);
	foreach($inactive as $location_id) {
		wp_delete_post($location_id, true);
	}
}

//get meetings based on post information
//used by meetings_api and theme 
function meetings_get($arguments=array()) {
	global $regions;

	//debugging
	//$arguments = $_GET;

	$meta_query = array(
		'relation'	=> 'AND',
	);

	if (!isset($arguments['location_id'])) $arguments['location_id'] = null;

	if (isset($arguments['day'])) {
		$meta_query[] = array(
			'key'	=> 'day',
			'value'	=> $arguments['day'],
		);
	}

	if (!empty($arguments['region'])) {
		$meta_query[] = array(
			'key'	=> 'region',
			'value'	=> $arguments['region'],
		);
	}

	if (!empty($arguments['types'])) {
		foreach ($arguments['types'] as $type) {
			$meta_query[] = array(
				'key'	=> 'types',
				'value'	=> '"' . $type . '"',
				'compare'=>'LIKE',
			);
		}
	}
	
	$meetings = $locations = array();

	# Get all locations
	$posts = get_posts(array(
		'post_type'		=> 'locations',
		'numberposts'	=> -1,
	));

	# Make an array of all locations
	foreach ($posts as $post) {
		$custom = get_post_meta($post->ID);
		$locations[$post->ID] = array(
			'address'			=>$custom['address'][0],
			'city'				=>$custom['city'][0],
			'state'				=>$custom['state'][0],
			'latitude'			=>$custom['latitude'][0],
			'longitude'			=>$custom['longitude'][0],
			'region_id'			=>$custom['region'][0],
			'region'			=>$regions[$custom['region'][0]],
			'location'			=>$post->post_title,
			'location_url'		=>get_permalink($post->ID),
			'location_slug'		=>$post->post_name,
			'location_updated'	=>$post->post_modified_gmt,
		);
	}

	# If searching, three extra queries
	$post_ids = array();
	if (!empty($arguments['search'])) {
		$post_ids = get_posts(array(
			'post_type'			=> 'meetings',
			'numberposts'		=> -1,
			's'					=> $arguments['search'],
			'fields'			=> 'ids',
		));
		$parents = get_posts(array(
			'post_type'			=> 'locations',
			'numberposts'		=> -1,
			's'					=> $arguments['search'],
			'fields'			=> 'ids',
		));
		if (count($parents)) {
			$children = get_posts(array(
				'post_type'			=> 'meetings',
				'numberposts'		=> -1,
				'post_parent__in'	=> $parents,
				'fields'			=> 'ids',
			));
			$post_ids = array_unique(array_merge($post_ids, $children));
		}
	}

	# Search meetings
	$posts = get_posts(array(
		'post_type'		=> 'meetings',
		'numberposts'	=> -1,
		'meta_key'		=> 'time',
		'orderby'		=> 'meta_value',
		'order'			=> 'asc',
		'meta_query'	=> $meta_query,
		//'s'				=> $arguments['search'],
		'post__in'		=> $post_ids,
		'post_parent'	=> $arguments['location_id'],
	));

	# Make an array of the meetings
	foreach ($posts as $post) {
		//shouldn't ever happen, but just in case
		if (empty($locations[$post->post_parent])) continue;

		$custom = get_post_meta($post->ID);
		$meetings[] = array_merge(array(
			'id'			=>$post->ID,
			'name'			=>$post->post_title,
			'slug'			=>$post->post_name,
			'notes'			=>$post->post_content,
			'updated'		=>$post->post_modified_gmt,
			'location_id'	=>$post->post_parent,
			'url'			=>get_permalink($post->ID),
			'time'			=>$custom['time'][0],
			'time_formatted'=>meetings_format_time($custom['time'][0]),
			'day'			=>$custom['day'][0],
			'types'			=>empty($custom['types'][0]) ? array() : unserialize($custom['types'][0]),
		), $locations[$post->post_parent]);
	}

	# Because you can't yet order by multiple meta_keys, manually sort the days
	if (!isset($arguments['day'])) {
		$days = array();
		foreach ($meetings as $meeting) {
			if (!isset($days[$meeting['day']])) $days[$meeting['day']] = array();
			$days[$meeting['day']][] = $meeting;
		}
		$meetings = array();
		$day_keys = array_keys($days);
		sort($day_keys);
		foreach ($day_keys as $day) {
			$meetings = array_merge($meetings, $days[$day]);
		}
	}

	return $meetings;
}

//get all locations
function locations_get() {
	global $regions;

	$locations = array();
	
	# Get all locations
	$posts = get_posts(array(
		'post_type'		=> 'locations',
		'numberposts'	=> -1,
	));

	# Make an array of all locations
	foreach ($posts as $post) {
		$custom = get_post_meta($post->ID);
		$locations[] = array(
			'id'				=>$post->ID,
			'location'			=>$post->post_title,
			'address'			=>$custom['address'][0],
			'city'				=>$custom['city'][0],
			'state'				=>$custom['state'][0],
			'latitude'			=>$custom['latitude'][0],
			'longitude'			=>$custom['longitude'][0],
			'region_id'			=>$custom['region'][0],
			'region'			=>$regions[$custom['region'][0]],
			'location_url'		=>get_permalink($post->ID),
			'location_slug'		=>$post->post_name,
			'location_updated'	=>$post->post_modified_gmt,
		);
	}
	
	return $locations;
}

//api ajax function
//used by theme and app
add_action('wp_ajax_meetings', 'meetings_api');
add_action('wp_ajax_nopriv_meetings', 'meetings_api');

function meetings_api() {
	header('Access-Control-Allow-Origin: *');
	wp_send_json(meetings_get($_POST));
};

//api ajax function
//used by ios
add_action('wp_ajax_locations', 'locations_api');
add_action('wp_ajax_nopriv_locations', 'locations_api');

function locations_api() {
	header('Access-Control-Allow-Origin: *');
	wp_send_json(locations_get($_POST));
};

//csv function
//made by request from intergroup chair
add_action('wp_ajax_csv', 'meetings_csv');
add_action('wp_ajax_nopriv_csv', 'meetings_csv');

function meetings_csv() {

	//going to need this later
	global $days;

	//get data source
	$meetings = meetings_get();

	//define columns to output
	$columns = array(
		'time' =>		'Time',
		'day' =>		'Day',
		'name' =>		'Name',
		'location' =>	'Location',
		'address' =>	'Address',
		'city' =>		'City',
		'state' =>		'State',
		'region' =>		'Region',
	);

	//helper vars
	$delimiter = ",";
	$line_ending = "\r\n";
	$escape = '"';
	
	//do header
	$return = implode($delimiter, array_values($columns)) . $line_ending;

	//append meetings
	foreach ($meetings as $meeting) {
		$line = array();
		foreach ($columns as $column=>$value) {
			if ($column == 'day') {
				$line[] = $days[$meeting[$column]];
			} else {
				$line[] = $escape . str_replace($escape, '', $meeting[$column]) . $escape;
			}
		}
		$return .= implode($delimiter, $line) . $line_ending;
	}

	//headers to trigger file download
	header('Cache-Control: maxage=1');
	header('Pragma: public');
	header('Content-Description: File Transfer');
	header('Content-Type: text/plain');
	header('Content-Length: ' . strlen($return));
	header('Content-Disposition: attachment; filename="meetings.csv"');
	
	//output
	die($return);
};

//todo: consider whether we really need this
add_action('wp_ajax_regions', 'regions_api');
add_action('wp_ajax_nopriv_regions', 'regions_api');

function regions_api() {
	$output = array();
	$regions = meetings_get_regions();
	foreach ($regions as $id=>$value) {
		$output[] = array('id'=>$id, 'value'=>$value);
	}
	header('Access-Control-Allow-Origin: *');
	wp_send_json($output);
};
