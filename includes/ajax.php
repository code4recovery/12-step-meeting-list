<?php
//ajax functions

//delete all meetings and locations
add_action('wp_ajax_tsml_delete', 'tsml_ajax_delete');
if (!function_exists('tsml_ajax_delete')) {
	function tsml_ajax_delete() {
		tsml_delete('everything');
		die('deleted');
	}
}

//ajax for the search typeahead and the location typeahead on the meeting edit page
add_action('wp_ajax_tsml_locations', 'tsml_ajax_locations');
add_action('wp_ajax_nopriv_tsml_locations', 'tsml_ajax_locations');
if (!function_exists('tsml_ajax_locations')) {
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
}

//ajax for the search typeahead and the meeting edit group typeahead
add_action('wp_ajax_tsml_groups', 'tsml_ajax_groups');
add_action('wp_ajax_nopriv_tsml_groups', 'tsml_ajax_groups');
if (!function_exists('tsml_ajax_groups')) {
	function tsml_ajax_groups() {
		$groups = get_posts('post_type=tsml_group&numberposts=-1');
		$results = array();
		foreach ($groups as $group) {
			$group_custom = get_post_meta($group->ID);
			$results[] = array(
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
			);
		}
		wp_send_json($results);
	}
}

//PDF meeting schedule linked on import & settings
add_action('wp_ajax_tsml_pdf', 'tsml_ajax_pdf');
add_action('wp_ajax_nopriv_tsml_pdf', 'tsml_ajax_pdf');
if (!function_exists('tsml_ajax_pdf')) {
	function tsml_ajax_pdf() {

		//include the file, which includes TCPDF
		include(TSML_PATH . '/includes/pdf.php');

		//create new PDF document
		$pdf = new TSMLPDF(array(
			'margin' => !empty($_GET['margin']) ? floatval($_GET['margin']) : .25,
			'width' => !empty($_GET['width']) ? floatval($_GET['width']) : 4.25,
			'height' => !empty($_GET['height']) ? floatval($_GET['height']) : 11,
		));

		//send to browser
		if (!headers_sent()) {
			$pdf->Output('meeting-schedule.pdf', 'I');
		}

		exit;
	}
}

//ajax for the search typeahead
add_action('wp_ajax_tsml_regions', 'tsml_ajax_regions');
add_action('wp_ajax_nopriv_tsml_regions', 'tsml_ajax_regions');
if (!function_exists('tsml_ajax_regions')) {
	function tsml_ajax_regions() {
		$regions = get_terms('tsml_region');
		$results = array();
		foreach ($regions as $region) {
			$results[] = array(
				'id'				=> $region->slug,
				'value'				=> html_entity_decode($region->name),
				'type'				=> 'region',
				'tokens'			=> tsml_string_tokens($region->name),
			);
		}
		wp_send_json($results);
	}
}

//ajax for address checking
add_action('wp_ajax_tsml_address', 'tsml_admin_ajax_address');
if (!function_exists('tsml_admin_ajax_address')) {
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
}

//get all contact email addresses (for europe)
//linked from admin_import.php
add_action('wp_ajax_contacts', 'tsml_ajax_contacts');
if (!function_exists('tsml_ajax_contacts')) {
	function tsml_ajax_contacts() {
		global $wpdb;
		$post_ids = $wpdb->get_col('SELECT id FROM ' . $wpdb->posts . ' WHERE post_type IN ("tsml_group", "tsml_meeting")');
		$emails = $wpdb->get_col('SELECT meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_key IN ("email", "contact_1_email", "contact_2_email", "contact_3_email") AND post_id IN (' . implode(',', $post_ids) . ')');
		$emails = array_unique(array_filter($emails));
		sort($emails);
		die(implode(',<br>', $emails));
	}
}

//function:	export csv
//used:		linked from admin-import.php, potentially also from theme
add_action('wp_ajax_csv', 'tsml_ajax_csv');
add_action('wp_ajax_nopriv_csv', 'tsml_ajax_csv');
if (!function_exists('tsml_ajax_csv')) {
	function tsml_ajax_csv() {

		//going to need this later
		global $tsml_days, $tsml_programs, $tsml_program, $tsml_sharing;

		//security
		if (($tsml_sharing != 'open') && !is_user_logged_in()) {
			tsml_ajax_unauthorized();
		}

		//get data source
		$meetings = tsml_get_meetings(array(), true);

		//define columns to output, always in English for portability (per Poland NA)
		$columns = array(
			'time' =>				'Time',
			'end_time' =>			'End Time',
			'day' =>				'Day',
			'name' =>				'Name',
			'location' =>			'Location',
			'formatted_address' =>	'Address',
			'region' =>				'Region',
			'sub_region' =>			'Sub Region',
			'types' =>				'Types',
			'notes' =>				'Notes',
			'location_notes' =>		'Location Notes',
			'group' => 				'Group',
			'district' => 			'District',
			'sub_district' => 		'Sub District',
			'website' => 			'Website',
			'website_2' => 			'Website 2',
			'mailing_address' =>	'Mailing Address',
			'venmo' => 				'Venmo',
			'square' => 			'Square',
			'paypal' => 			'Paypal',
			'email' => 				'Email',
			'phone' => 				'Phone',
			'group_notes' => 		'Group Notes',
			'contact_1_name' =>		'Contact 1 Name',
			'contact_1_email' =>	'Contact 1 Email',
			'contact_1_phone' =>	'Contact 1 Phone',
			'contact_2_name' =>		'Contact 2 Name',
			'contact_2_email' =>	'Contact 2 Email',
			'contact_2_phone' =>	'Contact 2 Phone',
			'contact_3_name' =>		'Contact 3 Name',
			'contact_3_email' =>	'Contact 3 Email',
			'contact_3_phone' =>	'Contact 3 Phone',
			'last_contact' => 		'Last Contact',
			'conference_url' => 	'Conference URL',
			'conference_phone' => 	'Conference Phone',
			'author' => 			'Author',
			'slug' => 				'Slug',
			'updated' =>			'Updated',
		);

		//helper vars
		$delimiter = ',';
		$escape = '"';

		//do header
		$return = implode($delimiter, array_values($columns)) . PHP_EOL;

		//get the preferred time format setting
		$time_format = get_option('time_format');

		//append meetings
		foreach ($meetings as $meeting) {
			$line = array();
			foreach ($columns as $column=>$value) {
				if (in_array($column, array('time', 'end_time'))) {
					$line[] = empty($meeting[$column]) ? null : date($time_format, strtotime($meeting[$column]));
				} elseif ($column == 'day') {
					$line[] = $tsml_days[$meeting[$column]];
				} elseif ($column == 'types') {
					$types = $meeting[$column];
					foreach ($types as &$type) $type = $tsml_programs[$tsml_program]['types'][trim($type)];
					sort($types);
					$line[] = $escape . implode(', ', $types) . $escape;
				} elseif (strstr($column, 'notes')) {
					$line[] = $escape . strip_tags(str_replace($escape, str_repeat($escape, 2), $meeting[$column])) . $escape;
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
}

//function: receives user feedback, sends email to admin
//used:		single-meetings.php
add_action('wp_ajax_tsml_feedback', 'tsml_ajax_feedback');
add_action('wp_ajax_nopriv_tsml_feedback', 'tsml_ajax_feedback');
if (!function_exists('tsml_ajax_feedback')) {
	function tsml_ajax_feedback() {
		global $tsml_feedback_addresses, $tsml_nonce;

		$meeting  = tsml_get_meeting(intval($_POST['meeting_id']));
		$name	 = sanitize_text_field($_POST['tsml_name']);
		$email	= sanitize_email($_POST['tsml_email']);

		$message  = '<p style="padding-bottom: 20px; border-bottom: 2px dashed #ccc; margin-bottom: 20px;">' . nl2br(sanitize_text_area(stripslashes($_POST['tsml_message']))) . '</p>';

		$message_lines = array(
			__('Requested By', '12-step-meeting-list') => $name . ' &lt;<a href="mailto:' . $email . '">' . $email . '</a>&gt;',
			__('Meeting', '12-step-meeting-list') => '<a href="' . get_permalink($meeting->ID) . '">' . $meeting->post_title . '</a>',
			__('When', '12-step-meeting-list') => tsml_format_day_and_time($meeting->day, $meeting->time),
		);

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

		foreach	($message_lines as $key => $value) {
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
}

//function: receives user feedback, sends email to admin
//used:		single-meetings.php
add_action('wp_ajax_tsml_geocode', 'tsml_ajax_geocode');
add_action('wp_ajax_nopriv_tsml_geocode', 'tsml_ajax_geocode');
if (!function_exists('tsml_ajax_geocode')) {
	function tsml_ajax_geocode() {
		global $tsml_nonce;
		if (!wp_verify_nonce(@$_GET['nonce'], $tsml_nonce)) tsml_ajax_unauthorized();
		wp_send_json(tsml_geocode(@$_GET['address']));
	}
}

//ajax function to import the meetings in the import buffer
//used by admin_import.php
add_action('wp_ajax_tsml_import', 'tsml_ajax_import');
if (!function_exists('function_name')) {
	function tsml_ajax_import() {
		$limit = 25;
		$import_result = tsml_import_next_batch_from_data_sources($limit);

		$meetings = $import_result['counts']['meetings'];
		$locations = $import_result['counts']['locations'];
		$groups = $import_result['counts']['groups'];
		$regions = $import_result['counts']['regions'];

		$printable_errors = array();
		foreach ($import_result['errors'] as $error) {
			list($row_number, $meeting_name, $error_message) = $error;
			$printable_errors[] = '<li value="' . $row_number . '">' . sprintf($error_message, $meeting_name) . '</li>';
		}

		$import_result['errors'] = $printable_errors;
		$import_result['descriptions'] = array(
			'meetings'	=> sprintf(_n('%s meeting', '%s meetings', $meetings, '12-step-meeting-list'), number_format_i18n($meetings)),
			'locations'	=> sprintf(_n('%s location', '%s locations', $locations, '12-step-meeting-list'), number_format_i18n($locations)),
			'groups'	=> sprintf(_n('%s group', '%s groups', $groups, '12-step-meeting-list'), number_format_i18n($groups)),
			'regions'	=> sprintf(_n('%s region', '%s regions', $regions, '12-step-meeting-list'), number_format_i18n($regions)),
		);

		wp_send_json($import_result);
	}
}

add_action('wp_ajax_tsml_break_import_lock', 'tsml_ajax_break_import_lock');
function tsml_ajax_break_import_lock() {
	wp_send_json(array(
		'value_before' => get_option('tsml_import_locked_until', null),
		'success' => update_option('tsml_import_locked_until', null),
	));
}

//imports the next batch of meetings from whatever data-source has been marked for refreshing
function tsml_import_next_batch_from_data_sources($limit = null) {
	$errors = array();
	$start_time_in_ms = microtime(true);

	//imports are prevented from running in parallel by their projected end timestamp.
	//if that end has elapsed, though, we can continue, the process surely has been killed/failed some time ago.
    $tsml_import_locked_until = (int) get_option('tsml_import_locked_until', null);
	$timestamp_now = time();
	$is_import_still_running = $tsml_import_locked_until && $tsml_import_locked_until >= $timestamp_now;

	if ($is_import_still_running) {
		$errors[] = array(
			null,
			date('r', $tsml_import_locked_until),
			__('An import is already running until %s! Doing nothing. Stopping.', '12-step-meeting-list'),
		);

		return array(
			'errors' => $errors,
		);
	}

	//lock the import process by telling other processes what our maximum runtime would be;
    //in case of script failure and long assumed runtime this may result in long idle times, though
    $tsml_import_started_at = $timestamp_now;
    $max_execution_time = (int) ini_get('max_execution_time');
    $tsml_import_locked_until = $tsml_import_started_at + $max_execution_time;
	update_option('tsml_import_locked_until', $tsml_import_locked_until);

	$remaining = get_option('tsml_import_buffer', array());
	$imported_meetings = array();

	$locations = array();
	$all_locations = tsml_get_locations();
	foreach ($all_locations as $location) {
		$locations[$location['formatted_address']] = $location['location_id'];
	}
	$all_locations = null;

	$groups = array();
	$all_groups = tsml_get_all_groups();
	foreach ($all_groups as $group) {
		$groups[$group->post_title] = $group->ID;
	}
	$all_groups = null;

	$regions = array();
	$all_regions = tsml_get_all_regions();
	foreach ($all_regions as $region) {
		if (!isset($regions[$region->name])) {
			$regions[$region->name] = array();
		}

		// include parent-term as sub-regions will test for that as well
		$regions[$region->name][$region->parent] = $region->term_id;
	}
	$all_regions = null;

	$districts = array();
	$all_districts = tsml_get_all_districts();
	foreach ($all_districts as $district) {
		if (!isset($districts[$district->name])) {
			$districts[$district->name] = array();
		}

		// include parent-term as sub-districts will test for that as well
		$districts[$district->name][$district->parent] = $district->term_id;
	}
	$all_districts = null;

		//passing post_modified and post_modified_gmt to wp_insert_post() below does not seem to work
		//todo occasionally remove this to see if it is working
		add_filter('wp_insert_post_data', 'tsml_import_post_modified', 99, 2);

	$may_continue = true;
	$time_we_should_pack_up_our_things = $tsml_import_started_at + 0.8 * $max_execution_time;

	//we are collecting updates to the group/meetings contacts and sending them afterwards to save db requests
	$contact_entity_updates = array();

	while ($remaining && $may_continue) {
		$meeting = array_shift($remaining);
		$imported_meetings[] = $meeting;
		$region_id = null;
		$district_id = null;
		$group_id = null;
		$contact_entity_update = array();

		$data_source_parent_region_id = empty($meeting['data_source_parent_region_id'])
			? -1
			: (int) $meeting['data_source_parent_region_id'];

		if ($data_source_parent_region_id == -1) {
			//no parent region has been selected, so use root
			$data_source_parent_region_id = 0;
		}

		//we can either try to manage as many inserts time allows, or import in small batches, the ajax-way
		if ($limit === null) {
			$may_continue = $time_we_should_pack_up_our_things > time();
		} else {
			$may_continue = count($imported_meetings) < $limit;
		}

			//check address
			if (empty($meeting['formatted_address'])) {
				$errors[] = array(
					$meeting['row'],
					$meeting['name'],
					__('No location information provided for <code>%s</code>.', '12-step-meeting-list'),
				);

				continue;
			}

			//geocode address
			$geocoded = tsml_geocode($meeting['formatted_address']);

		if ($geocoded['status'] == 'error')	{
			$errors[] = array(
				$meeting['row'],
				$meeting['name'],
				$geocoded['reason'],
			);

			continue;
		}

		//try to guess region from geocode
		if (empty($meeting['region']) && !empty($geocoded['city'])) $meeting['region'] = $geocoded['city'];

		//add region to taxonomy if it doesn't exist yet
		if (!empty($meeting['region'])) {
			if (isset($regions[$meeting['region']][$data_source_parent_region_id])) {
				$region_id = $regions[$meeting['region']][$data_source_parent_region_id];
			} else {
				$term = wp_insert_term(
					$meeting['region'],
					'tsml_region',
					array(
						'parent' => $data_source_parent_region_id,
					)
				);
				if (is_wp_error($term)) {
					$errors[] = array(
						$meeting['row'],
						$meeting['name'],
						$term->get_error_message(),
					);

					continue 1;
				}
				$regions[$meeting['region']][$data_source_parent_region_id] = $term['term_id'];
				$region_id = intval($term['term_id']);
			}

			//can only have a subregion if you already have a region
			if (!empty($meeting['sub_region'])) {
				if (isset($regions[$meeting['sub_region']][$region_id])) {
					$region_id = $regions[$meeting['sub_region']][$region_id];
				} else {
					$term = wp_insert_term($meeting['sub_region'], 'tsml_region', array('parent'=>$region_id));
					$regions[$meeting['sub_region']][$region_id] = $term['term_id'];
					$region_id = intval($term['term_id']);
				}
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

					//add district to taxonomy if it doesn't exist yet
					if (!empty($meeting['district'])) {
						if (isset($districts[$meeting['district']][0])) {
							$district_id = $districts[$meeting['district']][0];
						} else {
							$term = wp_insert_term($meeting['district'], 'tsml_district', 0);
							$districts[$meeting['district']][0] = $term['term_id'];
							$district_id = intval($term['term_id']);
						}

						//can only have a subdistrict if you already have a region
						if (!empty($meeting['sub_district'])) {
							if (isset($districts[$meeting['sub_district']][$district_id])) {
								$district_id = $districts[$meeting['sub_district']][$district_id];
							} else {
								$term = wp_insert_term($meeting['sub_district'], 'tsml_district', array('parent'=>$district_id));
								$districts[$meeting['sub_district']][$district_id] = $term['term_id'];
								$district_id = intval($term['term_id']);
							}
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
				$location_id = wp_insert_post(array(
					'post_title'		=> $meeting['location'],
					'post_type'		=> 'tsml_location',
					'post_content'	=> $meeting['location_notes'],
					'post_status'	=> 'publish',
				));
				$locations[$geocoded['formatted_address']] = $location_id;
				add_post_meta($location_id, 'formatted_address',	$geocoded['formatted_address']);
				add_post_meta($location_id, 'latitude',				$geocoded['latitude']);
				add_post_meta($location_id, 'longitude',			$geocoded['longitude']);
				wp_set_object_terms($location_id, $region_id, 'tsml_region');
			}

			$existing_meeting_id = isset($meeting['existing_meeting_id']) ? $meeting['existing_meeting_id'] : null;

			//save meeting to this location
			$meeting_post = array(
				'post_title'		=> $meeting['name'],
				'post_type'			=> 'tsml_meeting',
				'post_status'		=> 'publish',
				'post_parent'		=> $location_id,
				'post_content'		=> trim($meeting['notes']), //not sure why recursive trim not catching this
				'post_modified'		=> $meeting['post_modified'],
				'post_modified_gmt'	=> $meeting['post_modified_gmt'],
				'post_author'		=> $meeting['post_author'],
			);
			if (!empty($meeting['slug'])) {
				$meeting_post['post_name'] = $meeting['slug'];
			}
			if ($existing_meeting_id) {
				$meeting_post['ID'] = $existing_meeting_id;
				$meeting_id = $existing_meeting_id;
				tsml_import_mark_meeting_as_not_stale($existing_meeting_id);
				wp_update_post($meeting_post);
			} else {
				$meeting_id = wp_insert_post($meeting_post);
			}

			//add day and time(s) if not appointment meeting
			if (!empty($meeting['time']) && (!empty($meeting['day']) || (string) $meeting['day'] === '0')) {
				update_post_meta($meeting_id, 'day',  $meeting['day']);
				update_post_meta($meeting_id, 'time', $meeting['time']);

				if (!empty($meeting['end_time'])) {
					update_post_meta($meeting_id, 'end_time', $meeting['end_time']);
				} elseif ($existing_meeting_id) {
					delete_post_meta($meeting_id, 'end_time');
				}
			} elseif ($existing_meeting_id) {
				delete_post_meta($meeting_id, 'day');
				delete_post_meta($meeting_id, 'time');
				delete_post_meta($meeting_id, 'end_time');
			}

			//add custom meeting fields if available
			foreach (array('types', 'data_source', 'conference_url', 'conference_phone') as $key) {
				if (!empty($meeting[$key])) {
					update_post_meta($meeting_id, $key, $meeting[$key]);
				}
			}
			update_post_meta($meeting_id, 'data_source_id', $meeting['id']);
			update_post_meta($meeting_id, 'group_id', $group_id);

			//handle contact information (could be meeting or group)
			$contact_entity_id = empty($group_id) ? $meeting_id : $group_id;

			for ($i = 1; $i <= GROUP_CONTACT_COUNT; $i++) {
				foreach (array('name', 'phone', 'email') as $field) {
					$key = 'contact_' . $i . '_' . $field;

					if (!empty($meeting[$key])) {
						$contact_entity_update[$key] = $meeting[$key];
					} else {
						$contact_entity_update[$key] = null;
					}
				}
			}

		foreach (array('website', 'website_2') as $key) {
			if (!empty($meeting[$key])) {
				$contact_entity_update[$key] = esc_url_raw($meeting[$key], array('http', 'https'));
			} else {
				$contact_entity_update[$key] = null;
			}
		}

		foreach (array('email', 'phone', 'mailing_address', 'venmo', 'square', 'paypal') as $key) {
			if (!empty($meeting[$key])) {
				$contact_entity_update[$key] = $meeting[$key];
			} else {
				$contact_entity_update[$key] = null;
			}
		}

		if (!empty($meeting['last_contact']) && ($last_contact = strtotime($meeting['last_contact']))) {
			$contact_entity_update['last_contact'] = date('Y-m-d', $last_contact);
		} else {
			$contact_entity_update['last_contact'] = null;
		}

		$contact_entity_updates[$contact_entity_id] = $contact_entity_update;
	}

	//now quickly update collected contact entities
	foreach ($contact_entity_updates as $contact_entity_id => $contact_entity_update) {
		foreach ($contact_entity_update as $key => $value) {
			if ($value === null) {
				delete_post_meta($contact_entity_id, $key);
			} else {
				update_post_meta($contact_entity_id, $key, $value);
			}
		}
	}
	$contact_entity_updates = null;

	//remove post_modified thing added earlier
	remove_filter('wp_insert_post_data', 'tsml_import_post_modified', 99);

	do_action('tsml_refresh_cache_after_import');

	//send json result to browser
	$meetings  = tsml_count_meetings();
	$locations = tsml_count_locations();
	$regions   = tsml_count_regions();
	$groups	= tsml_count_groups();

	//now format the counts for JSON output
	$tsml_data_sources = get_option('tsml_data_sources', array());
	foreach ($tsml_data_sources as $url => $data_source) {
		$tsml_data_sources[$url]['count_meetings'] = number_format($data_source['count_meetings']);
	}

	if ($remaining) {
		update_option('tsml_import_buffer', $remaining);
	} else {
		foreach ($tsml_data_sources as $data_source_url => $data_source) {
			tsml_import_delete_stale_meetings_after_update($data_source_url);
		}

		delete_option('tsml_import_buffer');
	}

	//releases lock on import
	update_option('tsml_import_locked_until', null);

	$duration_in_ms = microtime(true) - $start_time_in_ms;

	return array(
		'errors' => $errors,
		'remaining' => count($remaining),
		'counts' => compact('meetings', 'locations', 'regions', 'groups'),
		'data_sources' => $tsml_data_sources,
		'duration_in_ms' => $duration_in_ms,
	);
}

add_action('tsml_refresh_cache_after_import', 'tsml_refresh_cache_after_import');
function tsml_refresh_cache_after_import() {
	//have to update the cache of types in use
	tsml_cache_rebuild();

	//have to update the cache of types in use
	tsml_update_types_in_use();

	//update viewport biasing for geocoding
	tsml_bounds();

	//update the data source counts for the database
	$tsml_data_sources = get_option('tsml_data_sources', array());
	foreach ($tsml_data_sources as $url => $props) {
		$tsml_data_sources[$url]['count_meetings'] = count(tsml_get_data_source_ids($url));
	}
	update_option('tsml_data_sources', $tsml_data_sources);
}

//api ajax function
//used by theme, web app, mobile app
add_action('wp_ajax_meetings', 'tsml_ajax_meetings');
add_action('wp_ajax_nopriv_meetings', 'tsml_ajax_meetings');
if (!function_exists('tsml_ajax_meetings')) {
	function tsml_ajax_meetings() {
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
}

//create and email a sharing key to meeting guide
add_action('wp_ajax_meeting_guide', 'tsml_ajax_meeting_guide');
add_action('wp_ajax_nopriv_meeting_guide', 'tsml_ajax_meeting_guide');
if (!function_exists('tsml_ajax_meeting_guide')) {
	function tsml_ajax_meeting_guide() {
		global $tsml_sharing, $tsml_sharing_keys;

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
		if (tsml_email(TSML_CONTACT_EMAIL, 'Sharing Key', $message)) {
			die('sent');
		}

		die('not sent!');
	}
}

//send a 401 and exit
function tsml_ajax_unauthorized() {
	if (!headers_sent()) header('HTTP/1.1 401 Unauthorized', true, 401);
	wp_send_json(array('error' => 'HTTP/1.1 401 Unauthorized'));
}

add_action( 'wp_ajax_meeting_link', 'tsml_ajax_meeting_link' );
add_action( 'wp_ajax_nopriv_meeting_link', 'tsml_ajax_meeting_link' );
/**
 * Ajax function to return Conference URL
 *
 * @return void
 * @since 3.6.4
 *
 */
function tsml_ajax_meeting_link() {

	global $tsml_nonce;

	if ( ! isset( $_GET['meeting_id'] ) || ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], $tsml_nonce ) ) {
		wp_send_json_error();
	}
	$meeting_id = intval( $_GET['meeting_id'] );
	$url		= get_post_meta( $meeting_id, 'conference_url', true );
	if ( $url ) {
		wp_send_json_success( [ 'meeting' => $url ] );
	} else {
		wp_send_json_error();
	}
}

add_action( 'wp_ajax_phone_link', 'tsml_ajax_phone_link' );
add_action( 'wp_ajax_nopriv_phone_link', 'tsml_ajax_phone_link' );
/**
 * Ajax function to return Conference phone
 *
 * @return void
 * @since 1.0.0
 *
 */
function tsml_ajax_phone_link() {

	global $tsml_nonce;

	if ( ! isset( $_GET['meeting_id'] ) || ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], $tsml_nonce ) ) {
		wp_send_json_error();
	}
	$meeting_id = intval( $_GET['meeting_id'] );
	$phone	  = get_post_meta( $meeting_id, 'conference_phone', true );
	if ( $phone ) {
		if ( false === strpos( $phone, 'tel:' ) ) {
			wp_send_json_success( [ 'phone' => 'tel:' . esc_attr( $phone ) ] );
		} else {
			wp_send_json_success( [ 'phone' => esc_attr( $phone ) ] );
		}

	} else {
		wp_send_json_error();
	}

}
