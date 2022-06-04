<?php
//ajax functions

//delete all meetings and locations
add_action('wp_ajax_tsml_delete', function () {
	tsml_delete('everything');
	die('deleted');
});


//ajax for the search typeahead and the location typeahead on the meeting edit page
add_action('wp_ajax_tsml_locations', 'tsml_ajax_locations');
add_action('wp_ajax_nopriv_tsml_locations', 'tsml_ajax_locations');
function tsml_ajax_locations()
{
	$locations = tsml_get_locations();
	$results = [];
	foreach ($locations as $location) {
		$results[] = [
			'value'				=> html_entity_decode($location['location']),
			'formatted_address'	=> $location['formatted_address'],
			'latitude'			=> $location['latitude'],
			'longitude'			=> $location['longitude'],
			'region'			=> $location['region_id'],
			'notes'				=> html_entity_decode($location['location_notes']),
			'tokens'			=> tsml_string_tokens($location['location']),
			'type'				=> 'location',
			'url'				=> $location['location_url'],
		];
	}
	wp_send_json($results);
}


//ajax for the search typeahead and the meeting edit group typeahead
add_action('wp_ajax_tsml_groups', 'tsml_ajax_groups');
add_action('wp_ajax_nopriv_tsml_groups', 'tsml_ajax_groups');
function tsml_ajax_groups()
{
	$groups = get_posts('post_type=tsml_group&numberposts=-1');
	$results = [];
	foreach ($groups as $group) {
		$group_custom = get_post_meta($group->ID);
		$results[] = [
			'value'				=> $group->post_title,
			'website'			=> @$group_custom['website'][0],
			'website_2'			=> @$group_custom['website_2'][0],
			'email'				=> @$group_custom['email'][0],
			'phone'				=> @$group_custom['phone'][0],
			'mailing_address'	=> @$group_custom['mailing_address'][0],
			'contact_1_name'	=> @$group_custom['contact_1_name'][0],
			'contact_1_email'	=> @$group_custom['contact_1_email'][0],
			'contact_1_phone'	=> @$group_custom['contact_1_phone'][0],
			'contact_2_name'	=> @$group_custom['contact_2_name'][0],
			'contact_2_email'	=> @$group_custom['contact_2_email'][0],
			'contact_2_phone'	=> @$group_custom['contact_2_phone'][0],
			'contact_3_name'	=> @$group_custom['contact_3_name'][0],
			'contact_3_email'	=> @$group_custom['contact_3_email'][0],
			'contact_3_phone'	=> @$group_custom['contact_3_phone'][0],
			'last_contact'		=> @$group_custom['last_contact'][0],
			'notes'				=> $group->post_content,
			'tokens'			=> tsml_string_tokens($group->post_title),
			'type'				=> 'group',
		];
	}
	wp_send_json($results);
}


//PDF meeting schedule linked on import & settings
add_action('wp_ajax_tsml_pdf', 'tsml_ajax_pdf');
add_action('wp_ajax_nopriv_tsml_pdf', 'tsml_ajax_pdf');
function tsml_ajax_pdf()
{

	//include the file, which includes TCPDF
	include(TSML_PATH . '/includes/pdf.php');

	//create new PDF document
	$pdf = new TSMLPDF([
		'margin' => !empty($_GET['margin']) ? floatval($_GET['margin']) : .25,
		'width' => !empty($_GET['width']) ? floatval($_GET['width']) : 4.25,
		'height' => !empty($_GET['height']) ? floatval($_GET['height']) : 11,
	]);

	//send to browser
	if (!headers_sent()) {
		$pdf->Output('meeting-schedule.pdf', 'I');
	}

	exit;
}

//ajax for the search typeahead
add_action('wp_ajax_tsml_regions', 'tsml_ajax_regions');
add_action('wp_ajax_nopriv_tsml_regions', 'tsml_ajax_regions');
function tsml_ajax_regions()
{
	$regions = get_terms('tsml_region');
	$results = [];
	foreach ($regions as $region) {
		$results[] = [
			'id' => $region->slug,
			'value' => html_entity_decode($region->name),
			'type' => 'region',
			'tokens' => tsml_string_tokens($region->name),
		];
	}
	wp_send_json($results);
}

//ajax for address checking
add_action('wp_ajax_tsml_address', function () {

	if (!$posts = get_posts([
		'post_type' => 'tsml_location',
		'numberposts' => 1,
		'meta_key' => 'formatted_address',
		'meta_value' => sanitize_text_field($_GET['formatted_address']),
	])) wp_send_json(false);

	$region = get_the_terms($posts[0]->ID, 'tsml_region');

	//return info to user
	wp_send_json([
		'location' => $posts[0]->post_title,
		'location_notes' => $posts[0]->post_content,
		'region' => $region[0]->term_id,
	]);
});


//get all contact email addresses (for europe)
//linked from admin_import.php
add_action('wp_ajax_contacts', function () {
	global $wpdb;
	$post_ids = $wpdb->get_col('SELECT id FROM ' . $wpdb->posts . ' WHERE post_type IN ("tsml_group", "tsml_meeting")');
	$emails = $wpdb->get_col('SELECT meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_key IN ("email", "contact_1_email", "contact_2_email", "contact_3_email") AND post_id IN (' . implode(',', $post_ids) . ')');
	$emails = array_unique(array_filter($emails));
	sort($emails);
	die(implode(',<br>', $emails));
});


//function:	export csv
//used:		linked from admin-import.php, potentially also from theme
add_action('wp_ajax_csv', 'tsml_ajax_csv');
add_action('wp_ajax_nopriv_csv', 'tsml_ajax_csv');
function tsml_ajax_csv()
{

	//going to need this later
	global $tsml_days, $tsml_programs, $tsml_program, $tsml_sharing;

	//security
	if (($tsml_sharing != 'open') && !is_user_logged_in()) {
		tsml_ajax_unauthorized();
	}

	//get data source
	$meetings = tsml_get_meetings([], false, true);

	//define columns to output, always in English for portability (per Poland NA)
	$columns = [
		'time' =>					'Time',
		'end_time' =>				'End Time',
		'day' =>					'Day',
		'name' =>					'Name',
		'location' =>				'Location',
		'formatted_address' =>		'Address',
		'region' =>					'Region',
		'sub_region' =>				'Sub Region',
		'types' =>					'Types',
		'notes' =>					'Notes',
		'location_notes' =>			'Location Notes',
		'group' => 					'Group',
		'district' => 				'District',
		'sub_district' => 			'Sub District',
		'website' => 				'Website',
		'website_2' => 				'Website 2',
		'mailing_address' =>		'Mailing Address',
		'venmo' => 					'Venmo',
		'square' => 				'Square',
		'paypal' => 				'Paypal',
		'email' => 					'Email',
		'phone' => 					'Phone',
		'group_notes' => 			'Group Notes',
		'contact_1_name' =>			'Contact 1 Name',
		'contact_1_email' =>		'Contact 1 Email',
		'contact_1_phone' =>		'Contact 1 Phone',
		'contact_2_name' =>			'Contact 2 Name',
		'contact_2_email' =>		'Contact 2 Email',
		'contact_2_phone' =>		'Contact 2 Phone',
		'contact_3_name' =>			'Contact 3 Name',
		'contact_3_email' =>		'Contact 3 Email',
		'contact_3_phone' =>		'Contact 3 Phone',
		'last_contact' => 			'Last Contact',
		'conference_url' => 		'Conference URL',
		'conference_url_notes' => 	'Conference URL Notes',
		'conference_phone' => 		'Conference Phone',
		'conference_phone_notes' => 'Conference Phone Notes',
		'author' => 				'Author',
		'slug' => 					'Slug',
		'data_source' =>			'Data Source',
		'updated' =>				'Updated',
	];

	//helper vars
	$delimiter = ',';
	$escape = '"';

	//do header
	$return = implode($delimiter, array_values($columns)) . PHP_EOL;

	//get the preferred time format setting
	$time_format = get_option('time_format');

	//append meetings
	foreach ($meetings as $meeting) {
		$line = [];
		foreach ($columns as $column => $value) {
			if (in_array($column, ['time', 'end_time'])) {
				$line[] = empty($meeting[$column]) ? null : date($time_format, strtotime($meeting[$column]));
			} elseif ($column == 'day') {
				$line[] = $tsml_days[$meeting[$column]];
			} elseif ($column == 'types') {
				$types = !empty($meeting[$column]) ? $meeting[$column] : [];
				if (!is_array($types)) $types = [];
				foreach ($types as &$type) $type = $tsml_programs[$tsml_program]['types'][trim($type)];
				sort($types);
				$line[] = $escape . implode(', ', $types) . $escape;
			} elseif (strstr($column, 'notes')) {
				$line[] = $escape . strip_tags(str_replace($escape, str_repeat($escape, 2), !empty($meeting[$column]) ? $meeting[$column] : '')) . $escape;
			} elseif (array_key_exists($column, $meeting)) {
				$line[] = $escape . str_replace($escape, '', $meeting[$column]) . $escape;
			} else {
				$line[] = '';
			}
		}
		$return .= implode($delimiter, $line) . PHP_EOL;
	}

	//headers to trigger file download
	header('Content-Type: text/csv');
	header('Content-Disposition: attachment; filename="meetings.csv"');

	//output
	wp_die($return);
}

//function: receives user feedback, sends email to admin
//used:		single-meetings.php
add_action('wp_ajax_tsml_feedback', 'tsml_ajax_feedback');
add_action('wp_ajax_nopriv_tsml_feedback', 'tsml_ajax_feedback');
function tsml_ajax_feedback()
{
	global $tsml_feedback_addresses, $tsml_nonce;

	$meeting  = tsml_get_meeting(intval($_POST['meeting_id']));
	$name	 = sanitize_text_field($_POST['tsml_name']);
	$email	= sanitize_email($_POST['tsml_email']);

	$message  = '<p style="padding-bottom: 20px; border-bottom: 2px dashed #ccc; margin-bottom: 20px;">' . nl2br(tsml_sanitize_text_area(stripslashes($_POST['tsml_message']))) . '</p>';

	$message_lines = [
		__('Requested By', '12-step-meeting-list') => $name . ' &lt;<a href="mailto:' . $email . '">' . $email . '</a>&gt;',
		__('Meeting', '12-step-meeting-list') => '<a href="' . get_permalink($meeting->ID) . '">' . $meeting->post_title . '</a>',
		__('When', '12-step-meeting-list') => tsml_format_day_and_time($meeting->day, $meeting->time),
	];

	if (!empty($meeting->types)) {
		$message_lines[__('Types', '12-step-meeting-list')] = implode(', ', $meeting->types);
	}

	if (!empty($meeting->notes)) {
		$message_lines[__('Notes', '12-step-meeting-list')] = $meeting->notes;
	}

	if (!empty($meeting->location)) {
		$message_lines[__('Location', '12-step-meeting-list')] = $meeting->location;
	}

	if (!empty($meeting->formatted_address)) {
		$message_lines[__('Address', '12-step-meeting-list')] = $meeting->formatted_address;
	}

	if (!empty($meeting->region)) {
		$message_lines[__('Region', '12-step-meeting-list')] = $meeting->region;
	}

	if (!empty($meeting->location_notes)) {
		$message_lines[__('Location Notes', '12-step-meeting-list')] = $meeting->location_notes;
	}

	foreach ($message_lines as $key => $value) {
		$message .= '<p>' . $key . ': ' . $value . '</p>';
	}

	//email vars
	if (!isset($_POST['tsml_nonce']) || !wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		_e('Error: nonce value not set correctly. Email was not sent.', '12-step-meeting-list');
	} elseif (empty($tsml_feedback_addresses) || empty($name) || !is_email($email) || empty($message)) {
		_e('Error: required form value missing. Email was not sent.', '12-step-meeting-list');
	} else {
		//send HTML email
		$subject = __('Meeting Feedback Form', '12-step-meeting-list') . ': ' . $meeting->post_title;
		if (tsml_email($tsml_feedback_addresses, $subject, $message, $name . ' <' . $email . '>')) {
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


//function: receives user feedback, sends email to admin
//used:		single-meetings.php
add_action('wp_ajax_tsml_geocode', 'tsml_ajax_geocode');
add_action('wp_ajax_nopriv_tsml_geocode', 'tsml_ajax_geocode');
function tsml_ajax_geocode()
{
	global $tsml_nonce;
	if (!wp_verify_nonce(@$_GET['nonce'], $tsml_nonce)) tsml_ajax_unauthorized();
	wp_send_json(tsml_geocode(@$_GET['address']));
}

//ajax function to import the meetings in the import buffer
//used by admin_import.php
add_action('wp_ajax_tsml_import', function () {
	global $tsml_data_sources;

	$meetings	= get_option('tsml_import_buffer', []);
	$errors		= [];
	$limit		= 25;

	//manage import buffer
	if (count($meetings) > $limit) {
		//slice off the first batch, save the remaining back to the import buffer
		$remaining = array_slice($meetings, $limit);
		update_option('tsml_import_buffer', $remaining);
		$meetings = array_slice($meetings, 0, $limit);
	} elseif (count($meetings)) {
		//take them all and remove the option (don't wait, to prevent an endless loop)
		$remaining = [];
		delete_option('tsml_import_buffer');
	}

	//get lookups, todo consider adding regions to this
	$locations = $groups = [];
	$all_locations = tsml_get_locations();
	foreach ($all_locations as $location) $locations[$location['formatted_address']] = $location['location_id'];
	$all_groups = tsml_get_all_groups();
	foreach ($all_groups as $group)	$groups[$group->post_title] = $group->ID;

	//passing post_modified and post_modified_gmt to wp_insert_post() below does not seem to work
	//todo occasionally remove this to see if it is working
	add_filter('wp_insert_post_data', 'tsml_import_post_modified', 99, 2);

	$data_source_parent_region_id = 0;

	foreach ($meetings as $meeting) {
		//check address
		if (empty($meeting['formatted_address'])) {
			$errors[] = '<li value="' . $meeting['row'] . '">' . sprintf(__('No location information provided for <code>%s</code>.', '12-step-meeting-list'), $meeting['name']) . '</li>';
			continue;
		}

		//geocode address
		$geocoded = tsml_geocode($meeting['formatted_address']);

		if ($geocoded['status'] == 'error') {
			$errors[] = '<li value="' . $meeting['row'] . '">' . $geocoded['reason'] . '</li>';
			continue;
		}

		if ($data_source_parent_region_id == 0 && array_key_exists($meeting['data_source'], $tsml_data_sources)) {
			$data_source_parent_region_id = intval($tsml_data_sources[$meeting['data_source']]['parent_region_id']) != -1 ? intval($tsml_data_sources[$meeting['data_source']]['parent_region_id']) : 0;
		}

		//try to guess region from geocode
		if (empty($meeting['region']) && !empty($geocoded['city'])) $meeting['region'] = $geocoded['city'];

		//add region to taxonomy if it doesn't exist yet
		if (!empty($meeting['region'])) {
			$args = ['parent' => $data_source_parent_region_id,];
			if (!$term = term_exists($meeting['region'], 'tsml_region', $args)) {
				$term = wp_insert_term($meeting['region'], 'tsml_region', $args);
			}
			$region_id = intval($term['term_id']);

			//can only have a subregion if you already have a region
			if (!empty($meeting['sub_region'])) {
				if (!$term = term_exists($meeting['sub_region'], 'tsml_region', $region_id)) {
					$term = wp_insert_term($meeting['sub_region'], 'tsml_region', ['parent' => $region_id]);
				}
				$region_id = intval($term['term_id']);
			}
		}

		//handle group (can't have a group if group name not specified)
		if (empty($meeting['group'])) {
			$group_id = null;
		} else {
			if (!array_key_exists($meeting['group'], $groups)) {
				$group_id = wp_insert_post([
					'post_type'		=> 'tsml_group',
					'post_status'	=> 'publish',
					'post_title'	=> $meeting['group'],
					'post_content'  => empty($meeting['group_notes']) ? '' : $meeting['group_notes'],
				]);

				//add district to taxonomy if it doesn't exist yet
				if (!empty($meeting['district'])) {
					if (!$term = term_exists($meeting['district'], 'tsml_district', 0)) {
						$term = wp_insert_term($meeting['district'], 'tsml_district', 0);
					}
					$district_id = intval($term['term_id']);

					//can only have a subregion if you already have a region
					if (!empty($meeting['sub_district'])) {
						if (!$term = term_exists($meeting['sub_district'], 'tsml_district', $district_id)) {
							$term = wp_insert_term($meeting['sub_district'], 'tsml_district', ['parent' => $district_id]);
						}
						$district_id = intval($term['term_id']);
					}

					wp_set_object_terms($group_id, $district_id, 'tsml_district');
				}

				$groups[$meeting['group']] = $group_id;
			} else {
				$group_id = $groups[$meeting['group']];
			}
		}

		//save location if not already in the database
		if (array_key_exists($geocoded['formatted_address'], $locations)) {
			$location_id = $locations[$geocoded['formatted_address']];
		} else {
			$location_id = wp_insert_post([
				'post_title' => $meeting['location'],
				'post_type' => 'tsml_location',
				'post_content' => $meeting['location_notes'],
				'post_status' => 'publish',
			]);
			$locations[$geocoded['formatted_address']] = $location_id;
			add_post_meta($location_id, 'formatted_address', $geocoded['formatted_address']);
			add_post_meta($location_id, 'latitude', $geocoded['latitude']);
			add_post_meta($location_id, 'longitude', $geocoded['longitude']);
			add_post_meta($location_id, 'approximate', $geocoded['approximate']);
			wp_set_object_terms($location_id, $region_id, 'tsml_region');
		}

		//save meeting to this location
		$options = [
			'post_title'		=> $meeting['name'],
			'post_type'			=> 'tsml_meeting',
			'post_status'		=> 'publish',
			'post_parent'		=> $location_id,
			'post_content'		=> trim($meeting['notes']), //not sure why recursive trim not catching this
			'post_modified'		=> $meeting['post_modified'],
			'post_modified_gmt'	=> $meeting['post_modified_gmt'],
			'post_author'		=> $meeting['post_author'],
		];
		if (!empty($meeting['slug'])) $options['post_name'] = $meeting['slug'];
		$meeting_id = wp_insert_post($options);

		//add day and time(s) if not appointment meeting
		if (!empty($meeting['time']) && (!empty($meeting['day']) || (string) $meeting['day'] === '0')) {
			add_post_meta($meeting_id, 'day',  $meeting['day']);
			add_post_meta($meeting_id, 'time', $meeting['time']);
			if (!empty($meeting['end_time'])) add_post_meta($meeting_id, 'end_time', $meeting['end_time']);
		}

		//add custom meeting fields if available
		foreach (['types', 'data_source', 'conference_url', 'conference_url_notes', 'conference_phone', 'conference_phone_notes'] as $key) {
			if (!empty($meeting[$key])) add_post_meta($meeting_id, $key, $meeting[$key]);
		}

		// Add Group Id and group specific info if applicable
		if (!empty($group_id)) {
			//link group to meeting
			add_post_meta($meeting_id, 'group_id', $group_id);
		}

		//handle contact information (could be meeting or group)
		$contact_entity_id = empty($group_id) ? $meeting_id : $group_id;
		for ($i = 1; $i <= TSML_GROUP_CONTACT_COUNT; $i++) {
			foreach (['name', 'phone', 'email'] as $field) {
				$key = 'contact_' . $i . '_' . $field;
				if (!empty($meeting[$key])) update_post_meta($contact_entity_id, $key, $meeting[$key]);
			}
		}

		if (!empty($meeting['website'])) {
			update_post_meta($contact_entity_id, 'website', esc_url_raw($meeting['website'], ['http', 'https']));
		}

		if (!empty($meeting['website_2'])) {
			update_post_meta($contact_entity_id, 'website_2', esc_url_raw($meeting['website_2'], ['http', 'https']));
		}

		if (!empty($meeting['email'])) {
			update_post_meta($contact_entity_id, 'email', $meeting['email']);
		}

		if (!empty($meeting['phone'])) {
			update_post_meta($contact_entity_id, 'phone', $meeting['phone']);
		}

		if (!empty($meeting['mailing_address'])) {
			update_post_meta($contact_entity_id, 'mailing_address', $meeting['mailing_address']);
		}

		if (!empty($meeting['venmo'])) {
			update_post_meta($contact_entity_id, 'venmo', $meeting['venmo']);
		}

		if (!empty($meeting['square'])) {
			update_post_meta($contact_entity_id, 'square', $meeting['square']);
		}

		if (!empty($meeting['paypal'])) {
			update_post_meta($contact_entity_id, 'paypal', $meeting['paypal']);
		}

		if (!empty($meeting['last_contact']) && ($last_contact = strtotime($meeting['last_contact']))) {
			update_post_meta($contact_entity_id, 'last_contact', date('Y-m-d', $last_contact));
		}
	}

	//have to update the cache of types in use
	tsml_cache_rebuild();

	//have to update the cache of types in use
	tsml_update_types_in_use();

	//update viewport biasing for geocoding
	tsml_bounds();

	//remove post_modified thing added earlier
	remove_filter('wp_insert_post_data', 'tsml_import_post_modified', 99);

	//send json result to browser
	$meetings  = tsml_count_meetings();
	$locations = tsml_count_locations();
	$regions   = tsml_count_regions();
	$groups	= tsml_count_groups();

	//update the data source counts for the database
	foreach ($tsml_data_sources as $url => $props) {
		$tsml_data_sources[$url]['count_meetings'] = count(tsml_get_data_source_ids($url));
	}
	update_option('tsml_data_sources', $tsml_data_sources);

	//now format the counts for JSON output
	foreach ($tsml_data_sources as $url => $props) {
		$tsml_data_sources[$url]['count_meetings'] = number_format($props['count_meetings']);
	}

	wp_send_json([
		'errors' => $errors,
		'remaining' => count($remaining),
		'counts' => compact('meetings', 'locations', 'regions', 'groups'),
		'data_sources' => $tsml_data_sources,
		'descriptions' => [
			'meetings' => sprintf(_n('%s meeting', '%s meetings', $meetings, '12-step-meeting-list'), number_format_i18n($meetings)),
			'locations' => sprintf(_n('%s location', '%s locations', $locations, '12-step-meeting-list'), number_format_i18n($locations)),
			'groups' => sprintf(_n('%s group', '%s groups', $groups, '12-step-meeting-list'), number_format_i18n($groups)),
			'regions' => sprintf(_n('%s region', '%s regions', $regions, '12-step-meeting-list'), number_format_i18n($regions)),
		],
	]);
});

//api ajax function
//used by theme, web app, mobile app
add_action('wp_ajax_meetings', 'tsml_ajax_meetings');
add_action('wp_ajax_nopriv_meetings', 'tsml_ajax_meetings');
function tsml_ajax_meetings()
{
	global $tsml_sharing, $tsml_sharing_keys, $tsml_nonce;

	//accepts GET or POST
	$input = empty($_POST) ? $_GET : $_POST;

	if ($tsml_sharing == 'open') {
		//sharing is open
	} elseif (!empty($input['nonce']) && wp_verify_nonce($input['nonce'], $tsml_nonce)) {
		//nonce checks out
	} elseif (!empty($input['key']) && array_key_exists($input['key'], $tsml_sharing_keys)) {
		//key checks out
	} else {
		tsml_ajax_unauthorized();
	}

	if (!headers_sent()) header('Access-Control-Allow-Origin: *');
	wp_send_json(tsml_get_meetings($input));
}

//create and email a sharing key to meeting guide
add_action('wp_ajax_meeting_guide', 'tsml_ajax_meeting_guide');
add_action('wp_ajax_nopriv_meeting_guide', 'tsml_ajax_meeting_guide');
function tsml_ajax_meeting_guide()
{
	global $tsml_sharing_keys;

	$mg_key = false;

	//check for existing keys
	foreach ($tsml_sharing_keys as $key => $value) {
		if ($value == 'Meeting Guide') {
			$mg_key = $key;
		}
	}

	//add new key
	if (empty($mg_key)) {
		$mg_key = md5(uniqid('Meeting Guide', true));
		$tsml_sharing_keys[$mg_key] = 'Meeting Guide';
		asort($tsml_sharing_keys);
		update_option('tsml_sharing_keys', $tsml_sharing_keys);
	}

	//build url
	$message = admin_url('admin-ajax.php?') . http_build_query(array(
		'action' => 'meetings',
		'key' => $mg_key,
	));

	//send email
	if (tsml_email(TSML_MEETING_GUIDE_APP_NOTIFY, 'Sharing Key', $message)) {
		die('sent');
	}

	die('not sent!');
}

//send a 401 and exit
function tsml_ajax_unauthorized()
{
	if (!headers_sent()) header('HTTP/1.1 401 Unauthorized', true, 401);
	wp_send_json(['error' => 'HTTP/1.1 401 Unauthorized']);
}
