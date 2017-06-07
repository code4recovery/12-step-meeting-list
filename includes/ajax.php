<?php
//ajax functions

//ajax for the search typeahead and the location typeahead on the meeting edit page
add_action('wp_ajax_tsml_locations', 'tsml_ajax_locations');
add_action('wp_ajax_nopriv_tsml_locations', 'tsml_ajax_locations');
function tsml_ajax_locations() {
	$locations = tsml_get_locations();
	$results = array();
    foreach ($locations as $location) {
        $results[] = array(
            'value'				=> html_entity_decode($location['location']),
            'formatted_address'	=> $location['formatted_address'],
            'latitude'			=> $location['latitude'],
            'longitude'			=> $location['longitude'],
            'region'			=> $location['region_id'],
            'notes'				=> html_entity_decode($location['location_notes']),
            'tokens'			=> tsml_string_tokens($location['location']),
            'type'				=> 'location',
            'url'				=> $location['location_url'],
        );
	}
	wp_send_json($results);
}

//ajax for the search typeahead and the meeting edit group typeahead
add_action('wp_ajax_tsml_groups', 'tsml_ajax_groups');
add_action('wp_ajax_nopriv_tsml_groups', 'tsml_ajax_groups');
function tsml_ajax_groups() {
	$groups = get_posts('post_type=tsml_group&numberposts=-1');
	$results = array();
    foreach ($groups as $group) {
        $title  = get_the_title($group->ID);
        $group_custom = get_post_meta($group->ID);
        $results[] = array(
            'value'				=> html_entity_decode($title),
            'contact_1_name'		=> @$group_custom['contact_1_name'][0],
            'contact_1_email'	=> @$group_custom['contact_1_email'][0],
            'contact_1_phone'	=> @$group_custom['contact_1_phone'][0],
            'contact_2_name'		=> @$group_custom['contact_2_name'][0],
            'contact_2_email'	=> @$group_custom['contact_2_email'][0],
            'contact_2_phone'	=> @$group_custom['contact_2_phone'][0],
            'contact_3_name'		=> @$group_custom['contact_3_name'][0],
            'contact_3_email'	=> @$group_custom['contact_3_email'][0],
            'contact_3_phone'	=> @$group_custom['contact_3_phone'][0],
            'notes'				=> html_entity_decode($group->post_content),
            'tokens'				=> tsml_string_tokens($title),
            'type'				=> 'group',
        );
	}
	wp_send_json($results);
}

//ajax for the search typeahead
add_action('wp_ajax_tsml_regions', 'tsml_ajax_regions');
add_action('wp_ajax_nopriv_tsml_regions', 'tsml_ajax_regions');
function tsml_ajax_regions() {
	$regions = get_terms('tsml_region');
	$results = array();
    foreach ($regions as $region) {
        $results[] = array(
	        'id'				=> $region->term_id,
            'value'				=> html_entity_decode($region->name),
            'type'				=> 'region',
            'tokens'			=> tsml_string_tokens($region->name),
        );
	}
	wp_send_json($results);
}

//ajax for address checking
add_action('wp_ajax_address', 'tsml_admin_ajax_address');
function tsml_admin_ajax_address() {
	if (!$posts = get_posts(array(
		'post_type'		=> 'tsml_location',
		'numberposts'	=> 1,
		'meta_key'		=> 'formatted_address',
		'meta_value'	=> sanitize_text_field($_GET['formatted_address']),
	))) return array();

	$region = array_values(get_the_terms($posts[0]->ID, 'tsml_region'));

	//return info to user
	wp_send_json(array(
		'location' => $posts[0]->post_title,
		'location_notes' => $posts[0]->post_content,
		'region' => $region[0]->term_id,
	));
}

//function:	clear google address cache, only necessary if parsing logic changes
//used:		utility function, run manually
add_action('wp_ajax_tsml_cache', 'tsml_ajax_cache_clear');
function tsml_ajax_cache_clear() {
	delete_option('tsml_addresses');
	die('address cache cleared!');	
}

//get all contact email addresses (for europe)
//linked from admin_import.php
add_action('wp_ajax_contacts', 'tsml_ajax_contacts');
function tsml_ajax_contacts() {
	global $wpdb;
	$group_ids = $wpdb->get_col('SELECT id FROM ' . $wpdb->posts . ' WHERE post_type = "tsml_group"');
	$emails = $wpdb->get_col('SELECT meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_key IN ("contact_1_email", "contact_2_email", "contact_3_email") AND post_id IN (' . implode(',', $group_ids) . ')');
	$emails = array_unique(array_filter($emails));
	sort($emails);
	die(implode(',', $emails));
}

//function:	export csv
//used:		linked from admin-import.php, potentially also from theme
add_action('wp_ajax_csv', 'tsml_ajax_csv');
add_action('wp_ajax_nopriv_csv', 'tsml_ajax_csv');
function tsml_ajax_csv() {

	//going to need this later
	global $tsml_days, $tsml_types, $tsml_program;

	//get data source
	$meetings = tsml_get_meetings();

	//define columns to output, always in English for portability (per Poland NA)
	$columns = array(
		'time' =>				'Time',
		'end_time' =>			'End Time',
		'day' =>					'Day',
		'name' =>				'Name',
		'location' =>			'Location',
		'formatted_address' =>	'Address',
		'region' =>				'Region',
		'sub_region' =>			'Sub Region',
		'types' =>				'Types',
		'notes' =>				'Notes',
		'location_notes' =>		'Location Notes',
		'group' => 				'Group',
		'website' => 			'Website',
		'email' => 				'Email',
		'phone' => 				'Phone',
		'group_notes' => 		'Group Notes',
		'updated' =>				'Updated',
	);
	
	//append contact info if user has permission
	if (current_user_can('edit_posts')) {
		$columns = array_merge($columns, array(
			'contact_1_name' =>		'Contact 1 Name',
			'contact_1_email' =>		'Contact 1 Email',
			'contact_1_phone' =>		'Contact 1 Phone',
			'contact_2_name' =>		'Contact 2 Name',
			'contact_2_email' =>		'Contact 2 Email',
			'contact_2_phone' =>		'Contact 2 Phone',
			'contact_3_name' =>		'Contact 3 Name',
			'contact_3_email' =>		'Contact 3 Email',
			'contact_3_phone' =>		'Contact 3 Phone',
			'last_contact' => 		'Last Contact',
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
			if (in_array($column, array('time', 'end_time'))) {
				$line[] = $meeting[$column];
			} elseif ($column == 'day') {
				$line[] = $tsml_days[$meeting[$column]];
			} elseif ($column == 'types') {
				$types = $meeting[$column];
				foreach ($types as &$type) $type = $tsml_types[$tsml_program][trim($type)];
				sort($types);
				$line[] = $escape . implode(', ', $types) . $escape;
			} elseif (strstr($column, 'notes')) {
				$line[] = $escape . strip_tags(str_replace($escape, str_repeat($escape, 2), $meeting[$column])) . $escape;
			} else {
				$line[] = $escape . str_replace($escape, '', $meeting[$column]) . $escape;
			}
		}
		$return .= implode($delimiter, $line) . PHP_EOL;
	}

	//dd($return);
	
	//headers to trigger file download
	header('Cache-Control: maxage=1');
	header('Pragma: public');
	header('Content-Description: File Transfer');
	header('Content-Type: text/plain');
	header('Content-Length: ' . strlen($return));
	header('Content-Disposition: attachment; filename="meetings.csv"');

	//output
	wp_die($return);
}

//function: receives user feedback, sends email to admin
//used:		single-meetings.php
add_action('wp_ajax_tsml_feedback', 'tsml_ajax_feedback');
add_action('wp_ajax_nopriv_tsml_feedback', 'tsml_ajax_feedback');
function tsml_ajax_feedback() {
	global $tsml_feedback_addresses, $tsml_nonce;
	
    $formatted_address = sanitize_text_field($_POST['tsml_formatted_address']);
    $url = sanitize_text_field($_POST['tsml_url']);
    $name    = sanitize_text_field($_POST['tsml_name']);
    $email  = sanitize_email($_POST['tsml_email']);
    $message  = stripslashes(implode('<br>', array_map('sanitize_text_field', explode("\n", $_POST['tsml_message']))));

    //append footer to message
    $message .= '<hr><p>Address: ' . $formatted_address . '</p><p>Edit meeting: <a href="' . $url . '">' . $url . '</a></p>';

	//sanitize input
	
	//email vars
	$subject  = '[12 Step Meeting List] Meeting Feedback Form';
	$headers  = 'From: ' . $name . ' <' . $email . '>' . "\r\n";

	if (!isset($_POST['tsml_nonce']) || !wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		_e('Error: nonce value not set correctly. Email was not sent.', '12-step-meeting-list');
	} elseif (empty($tsml_feedback_addresses) || empty($name) || !is_email($email) || empty($message)) {
		_e('Error: required form value missing. Email was not sent.', '12-step-meeting-list');
	} else {
		//send HTML email
		add_filter('wp_mail_content_type', 'tsml_email_content_type_html');
		if (wp_mail($tsml_feedback_addresses, $subject, $message, $headers)) {
			_e('Thank you for your feedback.', '12-step-meeting-list');
		} else {
			global $phpmailer;
			if (!empty($phpmailer->ErrorInfo)) {
				printf(__('Error: %s', '12-step-meeting-list'), $phpmailer->ErrorInfo);
			} else {
				_e('An error occurred while sending email!', '12-step-meeting-list');
			}
		}
		remove_filter('wp_mail_content_type', 'tsml_email_content_type_html');
	}
	
	exit;
}

//ajax function to import the meetings in the import buffer
//used by admin_import.php
add_action('wp_ajax_tsml_import', 'tsml_ajax_import');
function tsml_ajax_import() {
	global $tsml_google_api_key, $tsml_google_overrides, $tsml_language, $tsml_data_sources;
	
	$meetings	= get_option('tsml_import_buffer', array());
	$addresses	= get_option('tsml_addresses', array());
	$errors		= array();
	$limit		= 25;
	$geocoded	= 0;
	
	if (count($meetings) > $limit) {
		//slice off the first hundred, save the remaining back to the import buffer
		$remaining = array_slice($meetings, $limit);
		update_option('tsml_import_buffer', $remaining);
		$meetings = array_slice($meetings, 0, $limit);
	} elseif (count($meetings)) {
		//take them all and remove the option (don't wait, to prevent an endless loop)
		$remaining = array();
		delete_option('tsml_import_buffer');
	}
	
	//get lookups, todo consider adding regions to this
	$locations = $groups = array();
	$all_locations = tsml_get_locations();
	foreach ($all_locations as $location) $locations[$location['formatted_address']] = $location['location_id'];
	$all_groups = tsml_get_all_groups();
	foreach ($all_groups as $group)	$groups[$group->post_title] = $group->ID;
	
	//prepare CURL handle for geocoding
	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_HEADER => 0, 
		CURLOPT_RETURNTRANSFER => true, 
		CURLOPT_TIMEOUT => 10,
		CURLOPT_SSL_VERIFYPEER => false,
	));
	
	//passing post_modified and post_modified_gmt to wp_insert_post() below does not seem to work
	//todo occasionally remove this to see if it is working
	add_filter('wp_insert_post_data', 'tsml_import_post_modified', 99, 2);

	foreach ($meetings as $meeting) {
		
		//check address
		if (empty($meeting['formatted_address'])) {
			$errors[] = '<li value="' . $meeting['row'] . '">' . sprintf(__('No location information provided for <code>%s</code>.', '12-step-meeting-list'), $meeting['name']) . '</li>';
			continue;
		}
		
		//now geocode the address
		if (array_key_exists($meeting['formatted_address'], $addresses)) {
			
			//retrieve address from cache and skip google
			extract($addresses[$meeting['formatted_address']]);
			
		} else {
			
			//request from google
			curl_setopt($ch, CURLOPT_URL, 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query(array(
				'key' => $tsml_google_api_key,
				'address' => $meeting['formatted_address'],
				'language' => $tsml_language,
			)));

			$result = curl_exec($ch);

			$geocoded++;
			
			//could not connect error, todo, not sure what to do here
			if (empty($result)) {
				$errors[] = '<li value="' . $meeting['row'] . '">Google could not validate the address <code>' . $meeting['formatted_address'] . '</code>. Response was <code>' . curl_error($ch) . '</code></li>';
				continue;
			}
			
			//decode result
			$data = json_decode($result);
	
			if ($data->status == 'OK') {
				//ok great
			} elseif ($data->status == 'OVER_QUERY_LIMIT') {
				//if over query limit, wait two seconds and retry, or then exit
				//this isn't structured well. what if there are zero_results on the second attempt?
				sleep(2);
				$data = json_decode(curl_exec($ch));
				if ($data->status == 'OVER_QUERY_LIMIT') {
					$errors[] = '<li value="' . $meeting['row'] . '">You are over the rate limit for the Google Geocoding API, you will need an API key to continue.</li>';
					continue;
				}
			} elseif ($data->status == 'ZERO_RESULTS') {
				$errors[] = '<li value="' . $meeting['row'] . '">Google could not find an address with <code>' . $meeting['formatted_address'] . '</code>.</li>';
				continue;
			} else {
				$errors[] = '<li value="' . $meeting['row'] . '">Google gave an unexpected response for address <code>' . $meeting['formatted_address'] . '</code>. Response was <pre>' . var_export($data, true) . '</pre></li>';
				continue;
			}
			
			$formatted_address = $data->results[0]->formatted_address;
			$latitude = $data->results[0]->geometry->location->lat;
			$longitude = $data->results[0]->geometry->location->lng;
			
			//some google API results are bad, and we can override them manually
			if (array_key_exists($formatted_address, $tsml_google_overrides)) {
				extract($tsml_google_overrides[$formatted_address]);
			} elseif (empty($formatted_address) || empty($latitude) || empty($longitude)) {
				//check for required values, not sure if this ever happens
				$errors[] = '<li value="' . $meeting['row'] . '">Google could not find address information for <code>' . $meeting['formatted_address'] . '</code>.</li>';
				continue;
			}
			
			//save in cache
			$addresses[$meeting['formatted_address']] = compact('formatted_address', 'latitude', 'longitude');
		}

		//add region to taxonomy if it doesn't exist yet
		if (!empty($meeting['region'])) {
			if (!$term = term_exists($meeting['region'], 'tsml_region', 0)) {
				$term = wp_insert_term($meeting['region'], 'tsml_region', 0);
			}
			$region_id = intval($term['term_id']);

			//can only have a subregion if you already have a region
			if (!empty($meeting['sub_region'])) {
				if (!$term = term_exists($meeting['sub_region'], 'tsml_region', $region_id)) {
					$term = wp_insert_term($meeting['sub_region'], 'tsml_region', array('parent'=>$region_id));
				}
				$region_id = intval($term['term_id']);
			}
		}
		
		//handle group (can't have a group if group name not specified)
		if (!empty($meeting['group'])) {
			if (!array_key_exists($meeting['group'], $groups)) {
				$group_id = wp_insert_post(array(
				  	'post_type'		=> 'tsml_group',
				  	'post_status'	=> 'publish',
					'post_title'	=> $meeting['group'],
					'post_content'  => empty($meeting['group_notes']) ? '' : $meeting['group_notes'],
				));
				
				for ($i = 1; $i <= GROUP_CONTACT_COUNT; $i++) {
					foreach (array('name', 'phone', 'email') as $field) {
						$key = 'contact_' . $i . '_' . $field;
						if (!empty($meeting[$key])) update_post_meta($group_id, $key, $meeting[$key]);
					}					
				}

				if (!empty($meeting['website'])) {
					update_post_meta($group_id, 'website', esc_url_raw($meeting['website'], array('http', 'https')));
				}
				
				if (!empty($meeting['email'])) {
					update_post_meta($group_id, 'email', $meeting['email']);
				}
				
				if (!empty($meeting['phone'])) {
					update_post_meta($group_id, 'phone', $meeting['phone']);
				}
				
				if (!empty($meeting['last_contact']) && ($last_contact = strtotime($meeting['last_contact']))) {
					update_post_meta($group_id, 'last_contact', date('Y-m-d', $last_contact));
				}

				//add district to taxonomy if it doesn't exist yet
				if (!empty($meeting['district'])) {
					if (!$term = term_exists($meeting['district'], 'tsml_district', 0)) {
						$term = wp_insert_term($meeting['district'], 'tsml_district', 0);
					}
					$district_id = intval($term['term_id']);
		
					//can only have a subregion if you already have a region
					if (!empty($meeting['sub_district'])) {
						if (!$term = term_exists($meeting['sub_district'], 'tsml_district', $district_id)) {
							$term = wp_insert_term($meeting['sub_district'], 'tsml_district', array('parent'=>$district_id));
						}
						$district_id = intval($term['term_id']);
					}
				}
				
				wp_set_object_terms($group_id, $district_id, 'tsml_district');
				
				$groups[$meeting['group']] = $group_id;
			}
		}
		
		//save location if not already in the database
		if (array_key_exists($formatted_address, $locations)) {
			$location_id = $locations[$formatted_address];
		} else {
			$location_id = wp_insert_post(array(
				'post_title'	=> $meeting['location'],
				'post_type'		=> 'tsml_location',
				'post_content'	=> $meeting['location_notes'],
				'post_status'	=> 'publish',
			));
			$locations[$formatted_address] = $location_id;
			add_post_meta($location_id, 'formatted_address',	$formatted_address);
			add_post_meta($location_id, 'latitude',				$latitude);
			add_post_meta($location_id, 'longitude',			$longitude);
			wp_set_object_terms($location_id, $region_id, 'tsml_region');
		}
				
		//save meeting to this location
		$meeting_id = wp_insert_post(array(
			'post_title'		=> $meeting['name'],
			'post_type'			=> 'tsml_meeting',
			'post_status'		=> 'publish',
			'post_parent'		=> $location_id,
			'post_content'		=> trim($meeting['notes']), //not sure why recursive trim not catching this
			'post_modified'		=> $meeting['post_modified'],
			'post_modified_gmt'	=> $meeting['post_modified_gmt'],
		));
		
		//add day and time(s) if not appointment meeting
		if (!empty($meeting['time']) && (!empty($meeting['day']) || (string) $meeting['day'] === '0')) {
			add_post_meta($meeting_id, 'day',  $meeting['day']);
			add_post_meta($meeting_id, 'time', $meeting['time']);
			if (!empty($meeting['end_time'])) add_post_meta($meeting_id, 'end_time', $meeting['end_time']);
		}
		
		//add types, group, and data_source if available
		if (!empty($meeting['types'])) add_post_meta($meeting_id, 'types', $meeting['types']);
		if (!empty($meeting['group'])) add_post_meta($meeting_id, 'group_id', $groups[$meeting['group']]);
		if (!empty($meeting['data_source'])) {
			add_post_meta($meeting_id, 'data_source', $meeting['data_source']);
			if (array_key_exists($meeting['data_source'], $tsml_data_sources)) {
				if (empty($tsml_data_sources[$meeting['data_source']]['count_meetings'])) $tsml_data_sources[$meeting['data_source']]['count_meetings'] = 0;
				$tsml_data_sources[$meeting['data_source']]['count_meetings']++;
			}
		}

	}

	//close curl handle
	curl_close($ch);
	
	//save updated geocoding cache
	update_option('tsml_addresses', $addresses);

	//save updated geocoding cache
	update_option('tsml_data_sources', $tsml_data_sources);

	//have to update the cache of types in use
	tsml_update_types_in_use();

	//remove post_modified thing added earlier
	remove_filter('wp_insert_post_data', 'tsml_import_post_modified', 99);
	
	//number format the data sources
	foreach ($tsml_data_sources as $url => $props) {
		$tsml_data_sources[$url]['count_meetings'] = number_format($tsml_data_sources[$url]['count_meetings']);
	}
	
	//send json result to browser
	$meetings  = tsml_count_meetings();
	$locations = tsml_count_locations();
	$regions   = tsml_count_regions();
	$groups    = tsml_count_groups();
	wp_send_json(array(
		'errors'			=> $errors,
		'remaining'		=> count($remaining),
		'counts'			=> compact('meetings', 'locations', 'regions', 'groups'),
		'data_sources' 	=> $tsml_data_sources,
		'geocoded'		=> $geocoded,
		'descriptions'	=> array(
			'meetings'	=> sprintf(_n('%s meeting', '%s meetings', $meetings, '12-step-meeting-list'), number_format_i18n($meetings)),
			'locations'	=> sprintf(_n('%s location', '%s locations', $locations, '12-step-meeting-list'), number_format_i18n($locations)),
			'groups'		=> sprintf(_n('%s group', '%s groups', $groups, '12-step-meeting-list'), number_format_i18n($groups)),
			'regions'	=> sprintf(_n('%s region', '%s regions', $regions, '12-step-meeting-list'), number_format_i18n($regions)),
		),
	));
}

//api ajax function
//used by theme, web app, mobile app
add_action('wp_ajax_meetings', 'tsml_ajax_meetings');
add_action('wp_ajax_nopriv_meetings', 'tsml_ajax_meetings');
function tsml_ajax_meetings() {
	if (!headers_sent()) header('Access-Control-Allow-Origin: *');
	$meetings = empty($_POST) ? tsml_get_meetings($_GET) : tsml_get_meetings($_POST);
	wp_send_json($meetings);
}
