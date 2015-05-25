<?php

//function: enqueue assets for public or admin page
//used: in templates and on admin_edit.php
function tsml_assets($context) {
	
	//google maps api needed for maps and address verification, can't be onboarded
	wp_enqueue_script('google_maps_api', '//maps.googleapis.com/maps/api/js?sensor=false');
	
	if ($context == 'public') {
		wp_enqueue_style('bootstrap_css', plugin_dir_url(__DIR__ . '/../css') . '/css/bootstrap.min.css');
		wp_enqueue_script('bootstrap_js', plugin_dir_url(__DIR__ . '/../js') . '/js/bootstrap.min.js', array('jquery'), '', true);
		wp_enqueue_script('tsml_public_js', plugin_dir_url(__DIR__ . '/../js') . '/js/archive-meetings.js', array('jquery'), '', true);
		wp_localize_script('tsml_public_js', 'myAjax', array('ajaxurl' => admin_url('admin-ajax.php')));
		wp_enqueue_style('tsml_public_css', plugin_dir_url(__DIR__ . '/../css') . '/css/archive-meetings.min.css');		
	} elseif ($context == 'admin') {
		wp_enqueue_style('tsml_admin_style', plugin_dir_url(__FILE__) . '../css/admin.css');
		wp_enqueue_script('tsml_admin_js', plugin_dir_url(__FILE__) . '../js/admin_edit.js', array('jquery'), '', true);
		wp_localize_script('tsml_admin_js', 'myAjax', array('ajaxurl'=>admin_url('admin-ajax.php')));        
		wp_enqueue_script('typeahead_js', plugin_dir_url(__FILE__) . '../js/typeahead.bundle.js', array('jquery'), '', true);
	}
}

//function: register custom post types
//used: 	init.php on every request, also meeting.php in plugin activation hook
function tsml_custom_post_types() {
	global $tsml_regions;
	
	register_taxonomy('region', array('meetings'), array(
		'label' => 'Region', 
		'labels' => array('menu_name'=>'Regions'),
		'hierarchical' => true,
	));

	//build quick access array of regions
	$tsml_regions = tsml_get_regions();

	register_post_type('meetings',
		array(
			'labels'		=> array(
				'name'			=>	'Meetings',
				'singular_name'	=>	'Meeting',
				'not_found'		=>	'No meetings added yet.',
				'add_new_item'	=>	'Add New Meeting',
				'search_items'	=>	'Search Meetings',
				'edit_item'		=>	'Edit Meeting',
				'view_item'		=>	'View Meeting',
			),
			'supports'		=> array('title', 'revisions'),
			'public'		=> true,
			'has_archive'	=> true,
			'menu_icon'		=> 'dashicons-groups',
		)
	);

	register_post_type('locations',
		array(
			'labels'		=> array(
				'name'			=>	'Locations',
				'singular_name'	=>	'Location',
				'not_found'		=>	'No locations added yet.',
				'add_new_item'	=>	'Add New Location',
			),
	        'taxonomies'	=>	array('region'),
			'supports'		=> array('title', 'revisions'),
			'public'		=> true,
			'show_ui'		=> true,
			'has_archive'	=> true,
			'show_in_menu'	=> 'edit.php?post_type=meetings',
			'menu_icon'		=> 'dashicons-location',
			'capabilities'	=> array('create_posts'=>false),
		)
	);	
}

//function: takes 18:30 and returns 6:30 p.m.
//used:		tsml_get_meetings and theme
function tsml_format_time($string) {
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
function tsml_format_name($name, $tsml_types=array()) {
	if (in_array('M', $tsml_types)) {
		$name .= ' <small>Men</small>';
	} elseif (in_array('W', $tsml_types)) {
		$name .= ' <small>Women</small>';
	}
	return $name;
}

//function: load only the currently-used regions into a flat array
//used: init, for displaying in admin lists, api
function tsml_get_regions() {
	global $wpdb;
	$regions = $wpdb->get_col('SELECT DISTINCT
			m.meta_value
		FROM ' . $wpdb->postmeta . ' m
		JOIN ' . $wpdb->posts . ' p ON m.post_id = p.id
		WHERE p.post_type = "meetings" AND m.meta_key = "region" AND p.post_status = "publish"');
	$tsml_regions = array();
	$region_terms = get_terms('region', array('include' => $regions, 'hide_empty' => false));
	foreach ($region_terms as $region) $tsml_regions[$region->term_id] = $region->name;
	return $tsml_regions;
}

//function: deletes all orphaned locations (has no meetings associated)
//used:		save_post filter
function tsml_delete_orphaned_locations() {

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
//used by tsml_meetings_api and meeting list page 
function tsml_get_meetings($arguments=array()) {
	global $tsml_regions;

	$meta_query = array('relation'	=> 'AND');

	//sanitize input
	$arguments['location_id'] = (isset($arguments['location_id'])) ? intval($arguments['location_id']) : null;

	if (isset($arguments['day'])) {
		$meta_query[] = array(
			'key'	=> 'day',
			'value'	=> intval($arguments['day']),
		);
	}

	if (!empty($arguments['region'])) {
		$region = intval($arguments['region']);
		$regions = get_term_children($region, 'region');
		if (empty($regions)) {
			$meta_query[] = array(
				'key'	=> 'region',
				'value'	=> $region,
			);
		} else {
			$regions[] = $region;
			$meta_query[] = array(
				'key'	=> 'region',
				'compare' => 'IN',
				'value'	=> $regions,
			);
		}
		
	}

	if (!empty($arguments['types'])) {
		foreach ($arguments['types'] as $type) {
			if (!empty($type)) $meta_query[] = array(
				'key'	=> 'types',
				'value'	=> '"' . sanitize_text_field($type) . '"',
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
		$tsml_custom = get_post_meta($post->ID);
		$locations[$post->ID] = array(
			'address'			=> $tsml_custom['address'][0],
			'city'				=> $tsml_custom['city'][0],
			'state'				=> $tsml_custom['state'][0],
			'postal_code'		=> $tsml_custom['postal_code'][0],
			'country'			=> $tsml_custom['country'][0],
			'latitude'			=> $tsml_custom['latitude'][0],
			'longitude'			=> $tsml_custom['longitude'][0],
			'region_id'			=> $tsml_custom['region'][0],
			'region'			=> $tsml_regions[$tsml_custom['region'][0]],
			'location'			=> $post->post_title,
			'location_url'		=> get_permalink($post->ID),
			'location_slug'		=> $post->post_name,
			'location_updated'	=> $post->post_modified_gmt,
		);
	}

	# If searching, three extra queries
	$post_ids = array();
	if (!empty($arguments['search'])) {
		$post_ids = get_posts(array(
			'post_type'			=> 'meetings',
			'numberposts'		=> -1,
			's'					=> sanitize_text_field($arguments['search']),
			'fields'			=> 'ids',
		));
		$parents = get_posts(array(
			'post_type'			=> 'locations',
			'numberposts'		=> -1,
			's'					=> sanitize_text_field($arguments['search']),
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
		'post__in'		=> $post_ids,
		'post_parent'	=> $arguments['location_id'],
	));

	# Make an array of the meetings
	foreach ($posts as $post) {
		//shouldn't ever happen, but just in case
		if (empty($locations[$post->post_parent])) continue;

		$tsml_custom = get_post_meta($post->ID);
		$meetings[] = array_merge(array(
			'id'			=>$post->ID,
			'name'			=>$post->post_title,
			'slug'			=>$post->post_name,
			'notes'			=>$post->post_content,
			'updated'		=>$post->post_modified_gmt,
			'location_id'	=>$post->post_parent,
			'url'			=>get_permalink($post->ID),
			'time'			=>$tsml_custom['time'][0],
			'time_formatted'=>tsml_format_time($tsml_custom['time'][0]),
			'day'			=>$tsml_custom['day'][0],
			'types'			=>empty($tsml_custom['types'][0]) ? array() : unserialize($tsml_custom['types'][0]),
		), $locations[$post->post_parent]);
	}

	# Because you can't yet order by multiple meta_keys, manually sort the days
	if (!isset($arguments['day'])) {
		$tsml_days = array();
		foreach ($meetings as $meeting) {
			if (!isset($tsml_days[$meeting['day']])) $tsml_days[$meeting['day']] = array();
			$tsml_days[$meeting['day']][] = $meeting;
		}
		$meetings = array();
		$day_keys = array_keys($tsml_days);
		sort($day_keys);
		foreach ($day_keys as $day) {
			$meetings = array_merge($meetings, $tsml_days[$day]);
		}
	}

	return $meetings;
}

//get all locations
function tsml_get_locations() {
	global $tsml_regions;

	$locations = array();
	
	# Get all locations
	$posts = get_posts(array(
		'post_type'		=> 'locations',
		'numberposts'	=> -1,
	));

	# Make an array of all locations
	foreach ($posts as $post) {
		$tsml_custom = get_post_meta($post->ID);
		$locations[] = array(
			'id'				=> $post->ID,
			'location'			=> $post->post_title,
			'address'			=> $tsml_custom['address'][0],
			'city'				=> $tsml_custom['city'][0],
			'state'				=> $tsml_custom['state'][0],
			'postal_code'		=> $tsml_custom['postal_code'][0],
			'country'			=> $tsml_custom['country'][0],
			'latitude'			=> $tsml_custom['latitude'][0],
			'longitude'			=> $tsml_custom['longitude'][0],
			'region_id'			=> $tsml_custom['region'][0],
			'region'			=> $tsml_regions[$tsml_custom['region'][0]],
			'location_url'		=> get_permalink($post->ID),
			'location_slug'		=> $post->post_name,
			'location_updated'	=> $post->post_modified_gmt,
		);
	}
	
	return $locations;
}

//api ajax function
//used by theme and app
add_action('wp_ajax_meetings', 'tsml_meetings_api');
add_action('wp_ajax_nopriv_meetings', 'tsml_meetings_api');

function tsml_meetings_api() {
	header('Access-Control-Allow-Origin: *');
	wp_send_json(tsml_get_meetings($_POST)); //tsml_get_meetings sanitizes input
};

//api ajax function
//used by ios
add_action('wp_ajax_locations', 'tsml_locations_api');
add_action('wp_ajax_nopriv_locations', 'tsml_locations_api');

function tsml_locations_api() {
	header('Access-Control-Allow-Origin: *');
	wp_send_json(tsml_get_locations());
};

//csv function
//useful for exporting data
add_action('wp_ajax_csv', 'tsml_meetings_csv');
add_action('wp_ajax_nopriv_csv', 'tsml_meetings_csv');

function tsml_meetings_csv() {

	//going to need this later
	global $tsml_days, $tsml_types;

	//get data source
	$meetings = tsml_get_meetings();

	//define columns to output
	$columns = array(
		'time' =>		 'Time',
		'day' =>		 'Day',
		'name' =>		 'Name',
		'location' =>	 'Location',
		'address' =>	 'Address',
		'city' =>		 'City',
		'state' =>		 'State',
		'postal_code' => 'Postal Code',
		'country' =>	 'Country',
		'region' =>		 'Region',
		'types' => 		 'Types',
		'notes' => 		 'Notes',
		'updated' =>	 'Updated',
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
				$line[] = $tsml_days[$meeting[$column]];
			} elseif ($column == 'types') {
				$types = $meeting[$column];
				foreach ($types as &$type) $type = $tsml_types[$type];
				sort($types);
				$line[] = $escape . implode(', ', $types) . $escape;
			} elseif ($column == 'notes') {
				$line[] = $escape . strip_tags($meeting[$column]) . $escape;
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

	//echo '<pre>';
	//output
	die($return);
};

//todo: consider whether we really need this
add_action('wp_ajax_regions', 'tsml_regions_api');
add_action('wp_ajax_nopriv_regions', 'tsml_regions_api');

function tsml_regions_api() {
	$output = array();
	$tsml_regions = tsml_get_regions();
	foreach ($tsml_regions as $id=>$value) {
		$output[] = array('id'=>$id, 'value'=>$value);
	}
	header('Access-Control-Allow-Origin: *');
	wp_send_json($output);
};
