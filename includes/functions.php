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


//function: deletes all orphaned locations (has no meetings associated)
//used:		save_post filter
function tsml_delete_orphaned_locations() {

	//get all active location_ids
	$active_location_ids = array();
	$meetings = tsml_get_all_meetings();
	foreach ($meetings as $meeting) {
		$active_location_ids[] = $meeting->post_parent;
	}

	//get all location ids
	$all_location_ids = array();
	$locations = tsml_get_all_locations();
	foreach ($locations as $location) {
		$all_location_ids[] = $location->ID;
	}

	//foreach location id not active, delete it
	$inactive_location_ids = array_diff($all_location_ids, $active_location_ids);
	foreach($inactive_location_ids as $location_id) {
		wp_delete_post($location_id, true);
	}
}

//function: get all locations in the system
//used:		tsml_import(), tsml_delete_orphaned_locations(), and admin_import.php
function tsml_get_all_locations($status='any') {
	return get_posts('post_type=locations&post_status=' . $status . '&numberposts=-1');
}

//function: get all meetings in the system
//used:		tsml_import(), tsml_delete_orphaned_locations(), and admin_import.php
function tsml_get_all_meetings($status='any') {
	return get_posts('post_type=meetings&post_status=' . $status . '&numberposts=-1');
}

//function: get all regions in the system
//used:		tsml_import() and admin_import.php
function tsml_get_all_regions($status='any') {
	return get_terms('region', array('fields'=>'ids', 'hide_empty'=>false));
}

//function:	appends men or women if type present
//used:		archive-meetings.php
function tsml_format_name($name, $types=array()) {
	if (in_array('M', $types)) {
		$name .= ' <small>Men</small>';
	} elseif (in_array('W', $types)) {
		$name .= ' <small>Women</small>';
	}
	return $name;
}

//function: takes 18:30 and returns 6:30 p.m.
//used:		tsml_get_meetings(), single-meetings.php, admin_lists.php
function tsml_format_time($string) {
	if (!strstr($string, ':')) return 'n/a';
	if ($string == '12:00') return 'Noon';
	if ($string == '23:59') return 'Midnight';
	$date = strtotime($string);
	return date(get_option('time_format'), $date);
}


//function: get all locations with full location information
//used: tsml_locations_api()
function tsml_get_locations() {
	global $tsml_regions;

	$locations = array();
	
	# Get all locations
	$posts = tsml_get_all_locations('publish');

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

//function: get meetings based on post information
//used:		tsml_meetings_api(), single-locations.php, archive-meetings.php 
function tsml_get_meetings($arguments=array()) {
	global $tsml_regions;

	$meta_query = array('relation'	=> 'AND');

	//sanitize input
	$arguments['location_id'] = (isset($arguments['location_id'])) ? intval($arguments['location_id']) : null;

	if (isset($arguments['day']) && ($arguments['day'] !== false)) {
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
		if (empty($post_ids)) return [];
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

	//dd($meta_query);
	//die('count was ' . count($posts));
	//dd($post_ids);

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

//function: load only the currently-used regions into a flat array
//used:		tsml_custom_post_types(), tsml_regions_api()
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

//api ajax function
//used by theme, web app, ios
add_action('wp_ajax_meetings', 'tsml_meetings_api');
add_action('wp_ajax_nopriv_meetings', 'tsml_meetings_api');

function tsml_meetings_api() {
	header('Access-Control-Allow-Origin: *');
	if (empty($_POST) && !empty($_GET)) return wp_send_json(tsml_get_meetings($_GET)); //debugging
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
	global $tsml_days, $tsml_types, $tsml_program;

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
	$delimiter = ',';
	$escape = '"';
	
	//do header
	$return = implode($delimiter, array_values($columns)) . PHP_EOL;

	//append meetings
	foreach ($meetings as $meeting) {
		$line = array();
		foreach ($columns as $column=>$value) {
			if ($column == 'day') {
				$line[] = $tsml_days[$meeting[$column]];
			} elseif ($column == 'types') {
				$types = $meeting[$column];
				foreach ($types as &$type) $type = $tsml_types[$tsml_program][$type];
				sort($types);
				$line[] = $escape . implode(', ', $types) . $escape;
			} elseif ($column == 'notes') {
				$line[] = $escape . strip_tags($meeting[$column]) . $escape;
			} else {
				$line[] = $escape . str_replace($escape, '', $meeting[$column]) . $escape;
			}
		}
		$return .= implode($delimiter, $line) . PHP_EOL;
	}

	//headers to trigger file download
	header('Cache-Control: maxage=1');
	header('Pragma: public');
	header('Content-Description: File Transfer');
	header('Content-Type: text/plain');
	header('Content-Length: ' . strlen($return));
	header('Content-Disposition: attachment; filename="meetings.csv"');

	//output
	wp_die($return);
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

//sanitize and import meeting data
//used by admin_import.php
function tsml_import($meetings, $delete='nothing') {
	global $tsml_types, $tsml_program, $tsml_days;
	
	//uppercasing for value matching later
	$upper_types = array_map('strtoupper', $tsml_types[$tsml_program]);
		
	//type translations for other groups
	$type_translations = array(
		'BB' => 'B',
		'BC' => 'H', 
		'BG' => 'BE', 
		'CL' => 'C',
		'GL' => 'G',
		'GM' => 'G',
		'LGBTQ' => 'T',
		'LW' => 'L',
		'MN' => 'M',
		'NT' => 'A',
		'SPK' => 'SP',
		'SPK-F' => 'SP',
		'SPK-L' => 'SP',
		'SS' => 'ST',
		'WC' => 'X',
		'WM' => 'W',
		'WP/TRANS' => 'T',
		'YP' => 'Y',
		'E' => 'S',
	);
	
	//counter of successful meetings imported
	$success = 0;
	
	//counter for errors
	$row_counter = 1;

	//arrays we will need
	$addresses = $locations = array();
	
	//remove line breaks between rows
	$meetings = preg_replace('#\\n(?=[^"]*"[^"]*(?:"[^"]*"[^"]*)*$)#' , ' ', $meetings);

	//split data into rows
	$meetings = explode(PHP_EOL, $meetings);
	
	//remove empty rows
	$meetings = array_filter($meetings, function($a){
		$a = trim($a);
		return !empty($a);
	});
	
	//crash if no data
	if (count($meetings) < 2) return tsml_admin_notice('Nothing was imported because no data rows were found.', 'error');
	
	//get header
	$header = explode("\t", array_shift($meetings));
	$header = array_map('sanitize_text_field', $header);
	$header = array_map('strtolower', $header);
	$header_count = count($header);
	
	//check header for required fields
	if (!in_array('time', $header)) return tsml_admin_notice('Time column is required.', 'error');
	if (!in_array('day', $header)) return tsml_admin_notice('Day column is required.', 'error');
	if (!in_array('address', $header)) return tsml_admin_notice('Address column is required.', 'error');

	//all the data is set, now delete everything
	$all_meetings = tsml_get_all_meetings();
	foreach ($all_meetings as $meeting) wp_delete_post($meeting->ID, true);
	$all_locations = tsml_get_all_locations();
	foreach ($all_locations as $location) wp_delete_post($location->ID, true);
	$all_regions = tsml_get_all_regions();
	foreach ($all_regions as $region) wp_delete_term($region, 'region');
		
	//loop through data and group by address
	foreach ($meetings as $meeting) {
		$row_counter++;
		$meeting = explode("\t", $meeting);
		if ($header_count != count($meeting)) {
			return tsml_admin_notice('Row #' . $row_counter . ' has ' . count($meeting) . ' columns while the header has ' . $header_count . '.', 'error');
		}
		$meeting = array_map('stripslashes', $meeting); //removing quotes
		$meeting = array_map('sanitize_text_field', $meeting); //safety
		$meeting = array_combine($header, $meeting); //apply header field names to array

		//check required fields
		if (empty($meeting['time'])) return tsml_admin_notice('Found a meeting with no time.', 'error');
		if (empty($meeting['day'])) return tsml_admin_notice('Found a meeting with no day.', 'error');
		if (empty($meeting['address'])) return tsml_admin_notice('Found a meeting with no address.', 'error');

		//sanitize time
		$meeting['time'] = date_parse($meeting['time']);
		$meeting['time'] = sprintf('%02d', $meeting['time']['hour']) . ':' . sprintf('%02d', $meeting['time']['minute']);
		if ($meeting['time'] == '00:00') $meeting['time'] = '23:59';
		
		//use address if location is missing
		if (empty($meeting['location'])) $meeting['location'] = $meeting['address'];
	
		//use location, day, and time for meeting name if missing
		if (empty($meeting['name'])) $meeting['name'] = $meeting['location'] . ' ' . $meeting['day'] . 's at ' . tsml_format_time($meeting['time']);
	
		//sanitize day
		if (!in_array($meeting['day'], $tsml_days)) return tsml_admin_notice('"' . $meeting['day'] . '" is an invalid value for day.', 'error');
		$meeting['day'] = array_search($meeting['day'], $tsml_days);

		//append city, state, and country to address if not already in it
		if (!empty($meeting['city']) && !stristr($meeting['address'], $meeting['city'])) $meeting['address'] .= ', ' . $meeting['city'];
		if (!empty($meeting['state']) && !stristr($meeting['address'], $meeting['state'])) $meeting['address'] .= ', ' . $meeting['state'];
		if (!empty($meeting['country']) && !stristr($meeting['address'], $meeting['country'])) $meeting['address'] .= ', ' . $meeting['country'];

		//add region to taxonomy if it doesn't exist yet
		if (!empty($meeting['region'])) {
			if ($term = term_exists($meeting['region'], 'region')) {
				$meeting['region'] = $term['term_id'];
			} else {
				$term = wp_insert_term($meeting['region'], 'region');
				$meeting['region'] = $term['term_id'];
			}
		}

		//sanitize types
		$types = explode(',', $meeting['types']);
		$meeting['types'] = $unused_types = array();
		foreach ($types as $type) {
			$type = trim($type);
			if (in_array($type, array_keys($upper_types))) {
				$meeting['types'][] = $type;
			} elseif (in_array($type, array_values($upper_types))) {
				$meeting['types'][] = array_search($type, $upper_types);
			} elseif (in_array($type, array_keys($type_translations))) {
				$meeting['types'][] = $type_translations[$type];
			} else {
				$unused_types[] = $type;
			}
		}
		
		//append unused types to notes
		if (!empty($meeting['notes'])) $meeting['notes'] .= PHP_EOL . PHP_EOL;
		$meeting['notes'] .= implode(', ', $unused_types);
				
		//group by address
		if (!array_key_exists($meeting['address'], $addresses)) {
			$addresses[$meeting['address']] = array(
				'meetings' => array(),
				'region' => $meeting['region'],
				'location' => $meeting['location'],		
			);
		}
		
		//attach meeting to address object
		$addresses[$meeting['address']]['meetings'][] = array(
			'name' => $meeting['name'],
			'day' => $meeting['day'],
			'time' => $meeting['time'],
			'types' => $meeting['types'],
			'notes' => $meeting['notes'],
		);
	}
	
	//dd($addresses);
	//wp_die('exiting before geocoding ' . count($addresses) . ' addresses.');
		
	//loop through again and geocode the addresses, making a location
	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_HEADER => 0, 
        CURLOPT_RETURNTRANSFER => TRUE, 
        CURLOPT_TIMEOUT => 4,
    ));
	foreach ($addresses as $address=>$info) {
		curl_setopt($ch, CURLOPT_URL, 'http://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address));
		if (!$result = curl_exec($ch)) {
			return tsml_admin_notice('Google did not respond for address "' . $address . '"', 'error');
		}
		
		//interpret result
		$data = json_decode($result);
		
		if ($data->status == 'OVER_QUERY_LIMIT') {
			sleep(2);
			$data = json_decode(curl_exec($ch));
			if ($data->status == 'OVER_QUERY_LIMIT') {
				return tsml_admin_notice('You are over your rate limit for the Google Geocoding API, you will need an API Key to continue.', 'error');
			}
		}
		
		if (empty($data->results[0]->address_components)) {
			return tsml_admin_notice('Google did not respond for address "' . $address . '". Response was <pre>' . var_export($data, true) . '</pre>', 'error');
		}
		
		$formatted_address = $data->results[0]->formatted_address;
		$address = $city = $state = $postal_code = $country = false;
		foreach ($data->results[0]->address_components as $component) {
			if (in_array('street_number', $component->types)) {
				$address = $component->long_name;
			} elseif (in_array('route', $component->types)) {
				$address .= ' ' . $component->long_name;
			} elseif (in_array('locality', $component->types)) {
				$city = $component->long_name;
			} elseif (in_array('administrative_area_level_1', $component->types)) {
				$state = $component->short_name;
			} elseif (in_array('postal_code', $component->types)) {
				$postal_code = $component->short_name;
			} elseif (in_array('country', $component->types)) {
				$country = $component->short_name;
			} elseif (in_array('point_of_interest', $component->types)) {
				//remove point of interest, eg Sunnyvale Presbyterian Church, from address
				$needle = $component->long_name . ', ';
				if (substr($formatted_address, 0, strlen($needle)) == $needle) {
					$formatted_address = substr($formatted_address, strlen($needle));
				}
			}
		}
		
		//intialize empty location
		if (!array_key_exists($formatted_address, $locations)) {
			$locations[$formatted_address] = array(
				'meetings'		=>array(),
				'address'		=>$address,
				'city'			=>$city,
				'state'			=>$state,
				'postal_code'	=>$postal_code,
				'country'    	=>$country,
				'region'		=>$info['region'],
				'location'		=>$info['location'],
				'latitude'		=>$data->results[0]->geometry->location->lat,
				'longitude'		=>$data->results[0]->geometry->location->lng,
			);
		}

		//attach meetings to existing location
		$locations[$formatted_address]['meetings'] = array_merge(
			$locations[$formatted_address]['meetings'],
			$info['meetings']
		);
	}
	
	//loop through and save everything to the database
	foreach ($locations as $formatted_address=>$location) {
		//save location
		$location_id = wp_insert_post(array(
			'post_title'	=> $location['location'],
			'post_type'		=> 'locations',
			'post_status'	=> 'publish',
		));
		update_post_meta($location_id, 'formatted_address',	$formatted_address);
		update_post_meta($location_id, 'address',			$location['address']);
		update_post_meta($location_id, 'city',				$location['city']);
		update_post_meta($location_id, 'state',				$location['state']);
		update_post_meta($location_id, 'postal_code',		$location['postal_code']);
		update_post_meta($location_id, 'country',			$location['country']);
		update_post_meta($location_id, 'latitude',			$location['latitude']);
		update_post_meta($location_id, 'longitude',			$location['longitude']);
		update_post_meta($location_id, 'region',			$location['region']);

		//save meetings to this location
		foreach ($location['meetings'] as $meeting) {
			$meeting_id = wp_insert_post(array(
				'post_title'	=> $meeting['name'],
				'post_type'		=> 'meetings',
				'post_status'	=> 'publish',
				'post_parent'	=> $location_id,
				'post_content'	=> $meeting['notes'],
			));
			update_post_meta($meeting_id, 'day',		$meeting['day']);
			update_post_meta($meeting_id, 'time',		$meeting['time']);
			update_post_meta($meeting_id, 'types',		$meeting['types']);
			update_post_meta($meeting_id, 'region',		$location['region']); //double-entry just for searching
			wp_set_object_terms($meeting_id, intval($location['region']), 'region');
			$success++;
		}
	}
	
	//update types in use
	tsml_update_types_in_use();
	
	//success
	return tsml_admin_notice('Successfully added ' . $success . ' meetings.');
}

//function: return an html link with query string appended
//used:		archive-meetings.php, single-locations.php, single-meetings.php
function tsml_link($url, $string, $exclude='') {
	$appends = $_GET;
	if (array_key_exists($exclude, $appends)) unset($appends[$exclude]);
	if (!empty($appends)) {
		$url .= strstr($url, '?') ? '&' : '?';
		$url .= http_build_query($appends, '', '&amp;');
	}
	return '<a href="' . $url . '">' . $string . '</a>';
}

//function: set an option with the currently-used types
//used: 	tsml_import() and save.php
function tsml_update_types_in_use() {
	global $tsml_types_in_use, $wpdb;
	
	//shortcut to getting all meta values without getting all posts first
	$types = $wpdb->get_col('SELECT
			m.meta_value 
		FROM ' . $wpdb->postmeta . ' m
		JOIN ' . $wpdb->posts . ' p ON m.post_id = p.id
		WHERE p.post_type = "meetings" AND m.meta_key = "types" AND p.post_status = "publish"');
		
	//master array
	$all_types = array();
	
	//loop through results and append to master array
	foreach ($types as $type) {
		$type = unserialize($type);
		if (is_array($type)) $all_types = array_merge($all_types, $type);
	}
	
	//update global variable
	$tsml_types_in_use = array_unique($all_types);
	
	//dd($tsml_types_in_use);
	
	//set option value
	if (get_option('tsml_types_in_use') === false) {
		add_option('tsml_types_in_use', $tsml_types_in_use);
	} else {
		update_option('tsml_types_in_use', $tsml_types_in_use);
	}
}

//admin screen update message
//used by tsml_import() and admin_types.php
function tsml_admin_notice($message, $type='updated') {
	add_action('admin_notices', function() use ($message, $type) {
		echo '<div class="' . $type . '"><p>' . $message . '</p></div>';
	});
}

//helper for debugging
function dd($array) {
	echo '<pre>';
	print_r($array);
	exit;	
}

//helper for search terms
function highlight($text, $words) {
    preg_match_all('~\w+~', $words, $m);
    if (!$m) return $text;
    $re = '~\\b(' . implode('|', $m[0]) . ')\\b~i';
    return preg_replace($re, '<mark>$0</mark>', $text);
}
