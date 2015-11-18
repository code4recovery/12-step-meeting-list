<?php

//function: enqueue assets for public or admin page
//used: in templates and on admin_edit.php
function tsml_assets($context) {
	
	//google maps api needed for maps and address verification, can't be onboarded
	wp_enqueue_script('google_maps_api', '//maps.googleapis.com/maps/api/js');
	
	if ($context == 'public') {
		wp_enqueue_style('bootstrap_css', plugin_dir_url(__DIR__ . '/../css') . '/css/bootstrap.min.css');
		wp_enqueue_script('bootstrap_js', plugin_dir_url(__DIR__ . '/../js') . '/js/bootstrap.min.js', array('jquery'), '', true);
		wp_enqueue_script('tsml_public_js', plugin_dir_url(__DIR__ . '/../js') . '/js/archive-meetings.js', array('jquery'), '', true);
		wp_localize_script('tsml_public_js', 'myAjax', array('ajaxurl' => admin_url('admin-ajax.php')));
		wp_enqueue_style('tsml_public_css', plugin_dir_url(__DIR__ . '/../css') . '/css/archive-meetings.min.css');		
	} elseif ($context == 'admin') {
		wp_enqueue_style('tsml_admin_style', plugin_dir_url(__FILE__) . '../css/admin.min.css');
		wp_enqueue_script('tsml_admin_js', plugin_dir_url(__FILE__) . '../js/admin_edit.js', array('jquery'), '', true);
		wp_localize_script('tsml_admin_js', 'myAjax', array('ajaxurl'=>admin_url('admin-ajax.php')));        
		wp_enqueue_script('typeahead_js', plugin_dir_url(__FILE__) . '../js/typeahead.bundle.js', array('jquery'), '', true);
	}
}

//function: register custom post types
//used: 	init.php on every request, also meeting.php in plugin activation hook
function tsml_custom_post_types() {
	global $tsml_regions;
	
	register_taxonomy('region', 'meetings', array(
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

//function: takes 0, 18:30 and returns Sunday, 6:30 pm (depending on your settings)
//used:		admin_edit.php, archive-meetings.php, single-meetings.php
function tsml_format_day_and_time($day, $time, $separator=', ') {
	global $tsml_days;
	if (empty($tsml_days[$day]) || empty($time)) return 'Appointment';
	return $tsml_days[$day] . $separator . '<time>' . tsml_format_time($time) . '</time>';
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

//function: takes 18:30 and returns 6:30 pm (depending on your settings)
//used:		tsml_get_meetings(), single-meetings.php, admin_lists.php
function tsml_format_time($string) {
	if (empty($string)) return 'Appointment';
	if ($string == '12:00') return 'Noon';
	if ($string == '23:59') return 'Midnight';
	$date = strtotime($string);
	return date(get_option('time_format'), $date);
}


//function: get all locations with full location information
//used: tsml_import()
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
			'formatted_address' => $tsml_custom['formatted_address'][0],
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
			'location_notes'	=> $post->post_content,
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
			'relation' => 'OR',
			array(
				'key'	=> 'day',
				'value'	=> intval($arguments['day']),
			),
			array(
				'key'	=> 'day',
				'value'	=> '',
			),
		);
	}

	if (!empty($arguments['time'])) {
		if ($arguments['time'] == 'morning') {
			$meta_query[] = array(
				array('key' => 'time', 'value' => array('05:00', '09:59'), 'compare' => 'BETWEEN'),
			);
		} elseif ($arguments['time'] == 'day') {
			$meta_query[] = array(
				array('key' => 'time', 'value' => array('10:00', '16:59'), 'compare' => 'BETWEEN'),
			);
		} elseif ($arguments['time'] == 'evening') {
			$meta_query[] = array(
				array('key' => 'time', 'value' => array('17:00', '19:59'), 'compare' => 'BETWEEN'),
			);
		} elseif ($arguments['time'] == 'night') {
			$meta_query[] = array(
				'relation' => 'OR',
				array('key' => 'time', 'value' => '04:59', 'compare' => '<='),
				array('key' => 'time', 'value' => '20:00', 'compare' => '>='),
			);
		}
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

		//to be implemented later
		if (empty($tsml_custom['timezone'][0])) $tsml_custom['timezone'][0] = get_option('timezone_string');
		
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
			'timezone'			=> $tsml_custom['timezone'][0],
			'location'			=> $post->post_title,
			'location_url'		=> get_permalink($post->ID),
			'location_slug'		=> $post->post_name,
			'location_notes'	=> $post->post_content,
			'location_updated'	=> $post->post_modified_gmt,
		);
		
		//append contact info if user has permission
		if (current_user_can('edit_posts')) {
			$locations[$post->ID] = array_merge($locations[$post->ID], array(
				'contact_1_name'	=> array_key_exists('contact_1_name', $tsml_custom) ? $tsml_custom['contact_1_name'][0] : null,
				'contact_1_email'	=> array_key_exists('contact_1_email', $tsml_custom) ? $tsml_custom['contact_1_email'][0] : null,
				'contact_1_phone'	=> array_key_exists('contact_1_phone', $tsml_custom) ? $tsml_custom['contact_1_phone'][0] : null,
				'contact_2_name'	=> array_key_exists('contact_2_name', $tsml_custom) ? $tsml_custom['contact_2_name'][0] : null,
				'contact_2_email'	=> array_key_exists('contact_2_email', $tsml_custom) ? $tsml_custom['contact_2_email'][0] : null,
				'contact_2_phone'	=> array_key_exists('contact_2_phone', $tsml_custom) ? $tsml_custom['contact_2_phone'][0] : null,
				'contact_3_name'	=> array_key_exists('contact_3_name', $tsml_custom) ? $tsml_custom['contact_3_name'][0] : null,
				'contact_3_email'	=> array_key_exists('contact_3_email', $tsml_custom) ? $tsml_custom['contact_3_email'][0] : null,
				'contact_3_phone'	=> array_key_exists('contact_3_phone', $tsml_custom) ? $tsml_custom['contact_3_phone'][0] : null,
			));
		}
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
		$parents = array_merge(
			get_posts(array(
				'post_type'			=> 'locations',
				'numberposts'		=> -1,
				's'					=> sanitize_text_field($arguments['search']),
				'fields'			=> 'ids',
			)),
			get_posts(array(
				'post_type'			=> 'locations',
				'numberposts'		=> -1,
				'fields'			=> 'ids',
				'meta_query'		=> array(
					array(
						'key'		=> 'formatted_address',
						'value'		=> sanitize_text_field($arguments['search']),
						'compare'	=> 'LIKE',
					),
				),
			))
		);
		if (count($parents)) {
			$children = get_posts(array(
				'post_type'			=> 'meetings',
				'numberposts'		=> -1,
				'post_parent__in'	=> $parents,
				'fields'			=> 'ids',
			));
			$post_ids = array_unique(array_merge($post_ids, $children));
		}
		if (empty($post_ids)) return array();
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
	if (!isset($arguments['day']) || (empty($arguments['day']) && $arguments['day'] !== '0')) {
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
	
	# Stick 'by appointment' meetings at the end of the list
	$temp = array();
	foreach ($meetings as $key=>$meeting) {
		if (empty($meeting['time'])) {
			$temp[] = $meetings[$key];
			unset($meetings[$key]);
		}
	}
	$meetings = array_merge($meetings, $temp);

	return $meetings;
}

//function: template tag to get location, attach custom fields to it
//used: single-locations.php
function tsml_get_location() {
	$location = get_post();
	$custom = get_post_meta($location->ID);
	foreach ($custom as $key=>$value) {
		$location->{$key} = $value[0];
	}
	$location->post_title	= htmlentities($location->post_title, ENT_QUOTES);
	$location->notes 		= nl2br(esc_html($location->post_content));
	return $location;
}

//function: template tag to get meeting and location, attach custom fields to it
//used: single-meetings.php
function tsml_get_meeting() {
	$meeting				= get_post();
	$location				= get_post($meeting->post_parent);
	$custom					= array_merge(get_post_meta($meeting->ID), get_post_meta($location->ID));
	foreach ($custom as $key=>$value) {
		$meeting->{$key} = $value[0];
	}
	$meeting->types				= empty($meeting->types) ? array() : unserialize($meeting->types);
	$meeting->post_title		= htmlentities($meeting->post_title, ENT_QUOTES);
	$meeting->location			= htmlentities($location->post_title, ENT_QUOTES);
	$meeting->notes 			= nl2br(esc_html($meeting->post_content));
	$meeting->location_notes	= nl2br(esc_html($location->post_content));
	return $meeting;
}

//function: load only the currently-used regions (and their parents) into a flat array
//used:		tsml_custom_post_types(), tsml_regions_api()
function tsml_get_regions() {
	$tsml_regions = array();
	$region_terms = get_terms('region', array('hide_empty' => true));
	foreach ($region_terms as $region) $tsml_regions[$region->term_id] = $region->name;
	//dd($tsml_regions);
	return $tsml_regions;
}

//api ajax function
//used by theme, web app
add_action('wp_ajax_meetings', 'tsml_meetings_api');
add_action('wp_ajax_nopriv_meetings', 'tsml_meetings_api');

function tsml_meetings_api() {
	header('Access-Control-Allow-Origin: *');
	if (empty($_POST) && !empty($_GET)) return wp_send_json(tsml_get_meetings($_GET)); //debugging
	wp_send_json(tsml_get_meetings($_POST)); //tsml_get_meetings sanitizes input
};

//new api function
//more information at https://github.com/intergroup/api
add_action('wp_ajax_api', 'tsml_api');
add_action('wp_ajax_nopriv_api', 'tsml_api');

function tsml_api() {
	global $tsml_program, $tsml_version;
	header('Access-Control-Allow-Origin: *');
	$timezone = get_bloginfo('timezone');
	
	//prepare locations for output
	$meetings = tsml_get_meetings();
	$locations = array();
	foreach ($meetings as $meeting) {
		if (!isset($locations[$meeting['location_id']])) {
			$locations[$meeting['location_id']] = array(
				'id' => $meeting['location_slug'],
				'name' => $meeting['location'],
				'address' => $meeting['address'],
				'city' => $meeting['city'],
				'state' => $meeting['state'],
				'postal_code' => $meeting['postal_code'],
				'country' => $meeting['country'],
				'latitude' => $meeting['latitude'],
				'longitude' => $meeting['longitude'],
				'timezone' => $timezone,
				'notes' => $meeting['location_notes'],
				'regions' => array(),
				'url' => $meeting['location_url'],
				'updated' => $meeting['location_updated'],
				'meetings' => array(),
			);
		}
		$locations[$meeting['location_id']]['meetings'][] = array(
			'id' => $meeting['slug'],
			'name' => $meeting['name'],
			'time' => $meeting['time'],
			'day' => $meeting['day'],
			'types' => array(),
			'group' => null,
			'notes' => $meeting['notes'],
			'url' => $meeting['url'],
			'updated' => $meeting['updated'],
		);
	}
	
	
	$array = array(
		'name' => get_bloginfo('name'),
		'location' => get_option('tsml_location', null),
		'program' => strtoupper($tsml_program),
		'api_version' => '1.0',
		'software' => '12 Step Meeting List',
		'software_version' => $tsml_version,
		'locations' => array_values($locations),
	);
	wp_send_json($array);
}

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
		'time' =>				'Time',
		'day' =>				'Day',
		'name' =>				'Name',
		'location' =>			'Location',
		'address' =>			'Address',
		'city' =>				'City',
		'state' =>				'State',
		'postal_code' =>		'Postal Code',
		'country' =>			'Country',
		'region' =>				'Region',
		'types' =>				'Types',
		'notes' =>				'Notes',
		'updated' =>			'Updated',
	);
	
	//append contact info if user has permission
	if (current_user_can('edit_posts')) {
		$columns = array_merge($columns, array(
			'contact_1_name' =>		'Contact 1 Name',
			'contact_1_email' =>	'Contact 1 Email',
			'contact_1_phone' =>	'Contact 1 Phone',
			'contact_2_name' =>		'Contact 2 Name',
			'contact_2_email' =>	'Contact 2 Email',
			'contact_2_phone' =>	'Contact 2 Phone',
			'contact_3_name' =>		'Contact 3 Name',
			'contact_3_email' =>	'Contact 3 Email',
			'contact_3_phone' =>	'Contact 3 Phone',
		));
	}

	//helper vars
	$delimiter = ',';
	$escape = '"';
	
	//do header
	$return = implode($delimiter, array_values($columns)) . PHP_EOL;

	//append meetings
	foreach ($meetings as $meeting) {
		$line = array();
		foreach ($columns as $column=>$value) {
			if ($column == 'time') {
				$line[] = tsml_format_time($meeting[$column]);
			} elseif ($column == 'day') {
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
function tsml_import($meetings, $delete=false) {
	global $tsml_types, $tsml_program, $tsml_days, $wpdb;
	
	//uppercasing for value matching later
	$upper_types = array_map('strtoupper', $tsml_types[$tsml_program]);
		
	//counter of successful meetings imported
	$success = $geocoded = 0;
	
	//counter for errors
	$row_counter = 1;

	//arrays we will need
	$addresses = $existing_addresses = $locations = array();
	
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
	if (count($meetings) < 2) return tsml_alert('Nothing was imported because no data rows were found.', 'error');
	
	//get header
	$header = explode("\t", array_shift($meetings));
	$header = array_map('sanitize_text_field', $header);
	$header = array_map('strtolower', $header);
	$header_count = count($header);
	
	//check header for required fields
	if (!in_array('address', $header)) return tsml_alert('Address column is required.', 'error');

	//all the data is set, now delete everything
	if ($delete) {
		//must be done with SQL statements becase there could be thousands of records to delete
		if ($post_ids = implode(',', $wpdb->get_col('SELECT id FROM ' . $wpdb->posts . ' WHERE post_type IN ("meetings", "locations")'))) {
			$wpdb->query('DELETE FROM ' . $wpdb->posts . ' WHERE id IN (' . $post_ids . ')');
			$wpdb->query('DELETE FROM ' . $wpdb->postmeta . ' WHERE post_id IN (' . $post_ids . ')');
			$wpdb->query('DELETE FROM ' . $wpdb->term_relationships . ' WHERE object_id IN (' . $post_ids . ')');
		}
		if ($term_ids = implode(',', $wpdb->get_col('SELECT term_id FROM ' . $wpdb->term_taxonomy . ' WHERE taxonomy = "region"'))) {
			$wpdb->query('DELETE FROM ' . $wpdb->terms . ' WHERE term_id IN (' . $term_ids . ')');
			$wpdb->query('DELETE FROM ' . $wpdb->term_taxonomy . ' WHERE term_id IN (' . $term_ids . ')');
		}
	} else {
		$all_locations = tsml_get_locations();
		foreach ($all_locations as $location) $existing_addresses[$location['formatted_address']] = $location['id'];
	}
		
	//loop through data and group by address
	foreach ($meetings as $meeting) {
		$row_counter++;

		//preserve <br>s as line breaks if present, otherwise clean up
		$meeting = preg_replace('/\<br(\s*)?\/?\>/i', PHP_EOL, $meeting);
		$meeting = stripslashes($meeting);

		//split, check length, sanitize, associate
		$meeting = explode("\t", $meeting);
		if ($header_count != count($meeting)) {
			return tsml_alert('Row #' . $row_counter . ' has ' . count($meeting) . ' columns while the header has ' . $header_count . '.', 'error');
		}
		$meeting = array_map('strip_tags', $meeting);
		$meeting = array_map('trim', $meeting);
		$meeting = array_combine($header, $meeting);

		//dd($meeting);
		
		//check required fields
		if (empty($meeting['address'])) return tsml_alert('Found a meeting with no address at row #' . $row_counter . '.', 'error');

		//sanitize time & day
		if (empty($meeting['time']) || empty($meeting['day'])) {
			$meeting['time'] = $meeting['day'] = ''; //by appointment
		} else {
			$meeting['time'] = date_parse($meeting['time']);
			$meeting['time'] = sprintf('%02d', $meeting['time']['hour']) . ':' . sprintf('%02d', $meeting['time']['minute']);
			if ($meeting['time'] == '00:00') $meeting['time'] = '23:59';

			if (!in_array($meeting['day'], $tsml_days)) return tsml_alert('"' . $meeting['day'] . '" is an invalid value for day at row #' . $row_counter . '.', 'error');
			$meeting['day'] = array_search($meeting['day'], $tsml_days);
		}
		
		//if location is missing, use address
		if (empty($meeting['location'])) $meeting['location'] = $meeting['address'];
	
		//if meeting name missing, use location, day, and time
		if (empty($meeting['name'])) $meeting['name'] = $meeting['location'] . ' ' . $meeting['day'] . 's at ' . tsml_format_time($meeting['time']);
	
		//sanitize address, remove everything starting with @ (consider other strings as well?)
		if ($pos = strpos($meeting['address'], '@')) $meeting['address'] = trim(substr($meeting['address'], 0, $pos));

		//append city, state, and country to address if not already in it
		if (!empty($meeting['city'])) $meeting['address'] .= ', ' . $meeting['city'];
		if (!empty($meeting['state'])) $meeting['address'] .= ', ' . $meeting['state'];
		if ($meeting['country'] == 'US') $meeting['country'] = 'USA'; //helps geocoding
		if (!empty($meeting['country']) && !stristr($meeting['address'], $meeting['country'])) $meeting['address'] .= ', ' . $meeting['country'];

		//updated
		$meeting['updated'] = empty($meeting['updated']) ? time() : strtotime($meeting['updated']);
		$meeting['post_modified'] = date('Y-m-d H:i:s', $meeting['updated']);
		$meeting['post_modified_gmt'] = date('Y-m-d H:i:s', $meeting['updated']);

		//add region to taxonomy if it doesn't exist yet
		if (!empty($meeting['region'])) {
			if ($term = term_exists($meeting['region'], 'region')) {
				$meeting['region'] = $term['term_id'];
			} else {
				$term = wp_insert_term($meeting['region'], 'region');
				$meeting['region'] = $term['term_id'];
			}

			//can only have a subregion if you already have a region
			if (!empty($meeting['subregion'])) {
				if ($term = term_exists($meeting['subregion'], 'region', $meeting['region'])) {
					$meeting['region'] = $term['term_id'];
				} else {
					$term = wp_insert_term($meeting['subregion'], 'region', array('parent'=>$meeting['region']));
					$meeting['region'] = $term['term_id'];
				}
			}
		}

		//sanitize types
		$types = explode(',', $meeting['types']);
		$meeting['types'] = $unused_types = array();
		foreach ($types as $type) {
			$type = trim(strtoupper($type));
			if (in_array($type, array_values($upper_types))) {
				$meeting['types'][] = array_search($type, $upper_types);
			} else {
				$unused_types[] = $type;
			}
		}
		
		//append unused types to notes
		if (count($unused_types)) {
			if (!empty($meeting['notes'])) $meeting['notes'] .= PHP_EOL . PHP_EOL;
			$meeting['notes'] .= implode(', ', $unused_types);
		}
				
		//group by address
		if (!array_key_exists($meeting['address'], $addresses)) {
			$addresses[$meeting['address']] = array(
				'meetings' => array(),
				'lines' => array(),
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
			'post_modified' => $meeting['post_modified'],
			'post_modified_gmt' => $meeting['post_modified_gmt'],
		);
		
		//attach line number for reference if geocoding fails
		$addresses[$meeting['address']]['lines'][] = $row_counter;
	}
	
	//make sure script has enough time to run
	//usage limits: https://developers.google.com/maps/documentation/geocoding/
	$address_count = count($addresses);
	$seconds_needed = ceil($address_count / 5);
	$max_execution_time = ini_get('max_execution_time');
	$failed_addresses = array();
	if ($seconds_needed > $max_execution_time && !set_time_limit($seconds_needed)) {
		return tsml_alert('This script needs to geocode ' . number_format($address_count) . ' 
			addresses, which will take about ' . number_format($seconds_needed) . ' seconds. This  
			exceeds PHP\'s max_execution_time of ' . number_format($max_execution_time) . ' seconds.
			Please increase the limit in php.ini before retrying.', 'error');
	}

	//dd($addresses);
	//wp_die('exiting before geocoding ' . count($addresses) . ' addresses.');
		
	//prepare curl handle
	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_HEADER => 0, 
        CURLOPT_RETURNTRANSFER => TRUE, 
        CURLOPT_TIMEOUT => 10,
    ));
    
    //address cacheing
    $cached_addresses = get_option('tsml_addresses', array());

	//loop through again and geocode the addresses, making a location
	foreach ($addresses as $original_address=>$info) {
		
		if (array_key_exists($original_address, $cached_addresses)) {
			
			//retrieve address and skip google
			extract($cached_addresses[$original_address]);
			
		} else {
			
			//request from google
			curl_setopt($ch, CURLOPT_URL, 'https://maps.googleapis.com/maps/api/geocode/json?key=AIzaSyCC3p6PSf6iQbXi-Itwn9C24_FhkbDUkdg&address=' . urlencode($original_address));
			if (!$result = curl_exec($ch)) {
				return tsml_alert('Google did not respond for address <em>' . $original_address . '</em>.', 'error');
			}
			
			//decode result
			$data = json_decode($result);
	
			if ($data->status == 'OVER_QUERY_LIMIT') {
				//if over query limit, wait two seconds and retry, or then exit		
				sleep(2);
				$data = json_decode(curl_exec($ch));
				if ($data->status == 'OVER_QUERY_LIMIT') {
					return tsml_alert('You are over your rate limit for the Google Geocoding API, you will need an API key to continue.', 'error');
				}
			} elseif ($data->status == 'OK') {
				//ok great
			} elseif ($data->status == 'ZERO_RESULTS') {
				$failed_addresses[$original_address] = $info['lines'];
				continue;
			} else {
				return tsml_alert('Google gave an unexpected response for address <em>' . $original_address . '</em>. Response was <pre>' . var_export($data, true) . '</pre>', 'error');
			}
			
			//unpack response
			$address = $city = $state = $postal_code = $country = $point_of_interest = false;
			foreach ($data->results[0]->address_components as $component) {
				if (in_array('street_number', $component->types)) {
					$address = $component->long_name;
				} elseif (in_array('route', $component->types)) {
					$address .= ' ' . $component->long_name;
				} elseif (in_array('locality', $component->types)) {
					$city = $component->long_name;
				} elseif (in_array('sublocality', $component->types)) {
					if (!$city) $city = $component->long_name;
				} elseif (in_array('administrative_area_level_3', $component->types)) {
					if (!$city) $city = $component->long_name;
				} elseif (in_array('administrative_area_level_1', $component->types)) {
					$state = $component->short_name;
				} elseif (in_array('postal_code', $component->types)) {
					$postal_code = $component->short_name;
				} elseif (in_array('country', $component->types)) {
					$country = $component->short_name;
				} elseif (in_array('point_of_interest', $component->types) || empty($component->types)) {
					$point_of_interest = $component->short_name;
				} 
			}
			
			/*
			some legitimate meeting locations have no address
			http://maps.googleapis.com/maps/api/geocode/json?address=bagram%20airfield,%20afghanistan
			http://maps.googleapis.com/maps/api/geocode/json?address=River%20Light%20Park,%20Cornwall,%20NY,%20USA
			*/
			if (empty($address)) $address = $point_of_interest;
			
			//check for required values
			if (empty($address) || empty($city) || empty($data->results[0]->geometry->location->lat)) {
				$failed_addresses[$original_address] = $info['lines'];
				continue;
			}
			
			//create formatted address with the same methodology as in admin_edit.js
			$formatted_address = array();
			if (!empty($address)) $formatted_address[] = $address;
			if (!empty($city)) $formatted_address[] = $city;
			if (!empty($state)) $formatted_address[] = $state;
			if (!empty($postal_code)) $formatted_address[] = array_pop($formatted_address) . ' ' . $postal_code;
			if (!empty($country)) $formatted_address[] = $country;
			$formatted_address = implode(', ', $formatted_address);
			
			//lat and lon
			$latitude = $data->results[0]->geometry->location->lat;
			$longitude = $data->results[0]->geometry->location->lng;
			
			//save in cache
			$cached_addresses[$original_address] = compact('address', 'city', 'state', 'postal_code', 'country', 'latitude', 'longitude', 'formatted_address');
			
			$geocoded++;
		}
		
		//intialize empty location if needed
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
				'latitude'		=>$latitude,
				'longitude'		=>$longitude,
			);
		}

		//attach meetings to existing location
		$locations[$formatted_address]['meetings'] = array_merge(
			$locations[$formatted_address]['meetings'],
			$info['meetings']
		);
	}
	
	update_option('tsml_addresses', $cached_addresses, 'no');
	
	//loop through and save everything to the database
	foreach ($locations as $formatted_address=>$location) {

		//save location if not already in the database
		if (array_key_exists($formatted_address, $existing_addresses)) {
			$location_id = $existing_addresses[$formatted_address];
		} else {
			$location_id = wp_insert_post(array(
				'post_title'	=> $location['location'],
				'post_type'		=> 'locations',
				'post_status'	=> 'publish',
			));
		}
		
		//update location metadata
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
				'post_title'		=> $meeting['name'],
				'post_type'			=> 'meetings',
				'post_status'		=> 'publish',
				'post_parent'		=> $location_id,
				'post_content'		=> $meeting['notes'],
				'post_modified'		=> $meeting['post_modified'],
				'post_modified_gmt'	=> $meeting['post_modified_gmt'],
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
	if (count($failed_addresses)) {
		$message = $success ? number_format($success) . ' meetings were added successfully, however ' : '';
		$message .= 'Google rejected the following addresses:<ul style="padding-left:20px;list-style-type:square;">';
		foreach ($failed_addresses as $address=>$lines) {
			$message .= '<li><em>' . $address . '</em> on line ' . implode(', ', $lines) . '</li>';
		}
		$message .= '</ul>';
		if ($geocoded) $message .= ' (Geocoded ' . number_format($geocoded) . ' locations.)';
		return tsml_alert($message, 'error');		
	} else {
		$message = 'Successfully added ' . number_format($success) . ' meetings.';
		if ($geocoded) $message .= ' (Geocoded ' . number_format($geocoded) . ' locations.)';
		return tsml_alert($message);		
	}
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
	
	//set option value
	update_option('tsml_types_in_use', $tsml_types_in_use);
}

//admin screen update message
//used by tsml_import() and admin_types.php
function tsml_alert($message, $type='updated') {
	add_action('admin_notices', function() use ($message, $type) {
		echo '<div class="' . $type . '"><p>' . $message . '</p></div>';
	});
}

//run any outstanding upgrades, called in init.php
//depends on variable set in 12-step-meeting-list.php
function tsml_upgrades() {
	global $tsml_version, $wpdb;
	if ($tsml_version != TSML_VERSION) {
		if (version_compare($tsml_version, '1.6.2', '<')) {
			//this will get executed when you first install the plugin, as well as when upgrading to 1.6.2
			//fix any lingering addresses that end in ", USA" (two letter country codes only)
			$wpdb->get_results('UPDATE ' . $wpdb->postmeta . ' SET meta_value = LEFT(meta_value, LENGTH(meta_value) - 1) WHERE meta_key = "formatted_address" AND meta_value LIKE "%, USA"');
		}
	
		update_option('tsml_version', TSML_VERSION);
		$tsml_version = TSML_VERSION;		
	}
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
