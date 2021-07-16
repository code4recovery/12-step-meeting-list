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
		))) wp_send_json(false);

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
		$meetings = tsml_get_meetings();

		//define columns to output, always in English for portability (per Poland NA)
		$columns = array(
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
			'updated' =>				'Updated',
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
		global $tsml_feedback_addresses, $tsml_nonce, $tsml_programs, $tsml_program, $tsml_region;
		
		$host = $_SERVER['SERVER_NAME'];
		if ($host === 'aatemplate-wp.dev.cc') {	echo $host . ' <b>[Dev Only Display]</b><br>'; }

		$IsNew = false;
		$IsChange = false; 
		$IsRemove = false; 
		$IsFeedback = true;  // Is Default
		$RequestType = "feedback";

		// Determine Request Type 
		if ( isset( $_POST['submit'] ) ) {	$RequestType = $_POST['submit']; } 

			// /////////////////////////////////////////////////////////////////////////
		if ($host === 'aatemplate-wp.dev.cc') {
			if (isset($_POST['submit'])) {

				echo 'The submit button for a ' . $_POST['submit'] . ' request was pressed.<br><br>';
			} /////////////////////////////////////////////////////////////////////// */
		 }
		if ( isset( $_POST['submit'] ) && ( $_POST['submit'] === 'change') ) {
				$IsChange = true; // prove in the Change Processing that there really is a change
				$IsFeedback = false;
		} 
		elseif ( isset( $_POST['submit'] ) && ( $_POST['submit'] === 'new') ) {
			$IsNew = true;
			$IsFeedback = false;
		}
		elseif ( isset( $_POST['submit'] ) && ( $_POST['submit'] === 'remove') ) {
			$IsRemove = true;
			$IsFeedback = false;
		}
		else {
			$IsFeedback = true;
		}
	
		$name = stripslashes(sanitize_text_field($_POST['tsml_name']));
		$email = sanitize_email($_POST['tsml_email']);
		$myTypesArray = $tsml_programs[$tsml_program]['types'];

		
		//------------------ Start HTML Layout ----------------------
		$message = '<p style="padding-bottom: 20px; border-bottom: 2px dashed #ccc; margin-bottom: 20px;">' . nl2br(sanitize_text_area(stripslashes($_POST['tsml_message']))) . '</p>';
		$message .= "<table border='1' style='width:600px;'><tbody>";

		if ( $RequestType === 'new') {
			//break;
		}
		else 
		{
			//if ($host === 'aatemplate-wp.dev.cc') { echo "Initialization Processing started...<br>"; }
			$meeting_id = $_POST['meeting_id'];
			$meeting  = tsml_get_meeting ( intval( $meeting_id ) );
			$permalink = get_permalink($meeting->ID);
			$types_string = implode(', ', $meeting->types);
			$post_title = sanitize_text_field($meeting->post_title);

			$daytime = tsml_format_day_and_time( $meeting->day, $meeting->time);

			// Publish type descriptions in email instead of just type codes 
			//echo "=====================   MTG Array   =====================================<br>";

			$typesDescStr = '';
			$typesDescArray = $meeting->types;
			foreach ($typesDescArray as $mtg_key) {
				$mtg_description = $myTypesArray[$mtg_key];
				$typesDescStr .= $mtg_description.'<br>';
				$typesDescArray[$mtg_key] = $mtg_description;
				//	if ($host === 'aatemplate-wp.dev.cc') { echo "key: $mtg_key ----> value: $mtg_description<br>"; }
			}

			//if ($host === 'aatemplate-wp.dev.cc') { echo $typesDescStr.'<br>'; }

			//------------------ Continue with HTML table construction ----------------------
			$message_lines = array(
				__('Requestor', '12-step-meeting-list') =>  "<tr><td>Requestor</td><td>$name <a href='mailto:' $email > $email </a></td></tr>",
				__('Meeting', '12-step-meeting-list') => "<tr><td>Meeting</td><td><a href='$permalink'> $post_title </a></td></tr>",
				__('Meeting Id', '12-step-meeting-list') =>  "<tr><td>Meeting Id</td><td>$meeting_id</td></tr>",
				__('When', '12-step-meeting-list') => "<tr><td>When</td><td>$daytime</td></tr>",
			);

			if (!empty($meeting->types)) {
				$message_lines[__('Types', '12-step-meeting-list')] = "<tr><td>Types</td><td>$typesDescStr</td></tr>";
			}

			if (!empty($meeting->notes)) {
				$message_lines[__('Notes', '12-step-meeting-list')] = "<tr><td>Notes</td><td>$meeting->notes</td></tr>";  
			}

			if (!empty($meeting->conference_url)) {
				$message_lines[__('Conference URL', '12-step-meeting-list')] = "<tr><td>Conference URL</td><td>$meeting->conference_url</td></tr>";  
			}

			if (!empty($meeting->conference_url_notes)) {
				$message_lines[__('Conference URL Notes', '12-step-meeting-list')] = "<tr><td>Conference URL Notes</td><td>$meeting->conference_url_notes</td></tr>";  
			}

			if (!empty($meeting->conference_phone)) {
				$message_lines[__('Conference Phone', '12-step-meeting-list')] = "<tr><td>Conference Phone</td><td>$meeting->conference_phone</td></tr>";  
			}

			if (!empty($meeting->conference_phone_notes)) {
				$message_lines[__('Conference Phone Notes', '12-step-meeting-list')] = "<tr><td>Conference Phone Notes</td><td>$meeting->conference_phone_notes</td></tr>";  
			}

			if (!empty($meeting->location)) {
				$message_lines[__('Location', '12-step-meeting-list')] = "<tr><td>Location</td><td>$meeting->location</td></tr>";  
			}

			if (!empty($meeting->formatted_address)) {
				$message_lines[__('Address', '12-step-meeting-list')] = "<tr><td>Address</td><td>$meeting->formatted_address</td></tr>";  
			}

			if (!empty($meeting->region)) {
				$message_lines[__('Region', '12-step-meeting-list')] = "<tr><td>Region</td><td>$meeting->region</td></tr>";  
			}
			
			if (!empty($meeting->sub_region)) {
				$message_lines[__('Sub Region', '12-step-meeting-list')] = "<tr><td>Region</td><td>$meeting->sub_regioin</td></tr>";  
			}

			if (!empty($meeting->location_notes)) {
				$message_lines[__('Location Notes', '12-step-meeting-list')] = "<tr><td>Location Notes</td><td>$meeting->location_notes</td></tr>";  
			}

			/* Addition Group Information */

			if (!empty($meeting->group)) {
				$message_lines[__('Group Name', '12-step-meeting-list')] = "<tr><td>Group Name</td><td>$meeting->group</td></tr>";  
			}

			if ( $meeting->group_id ) {

				if (!empty($meeting->district) && strlen($meeting->district) > 0 ) {
					$message_lines[__('District', '12-step-meeting-list')] = "<tr><td>District</td><td>$meeting->district</td></tr>";  
				}

				if (!empty($meeting->sub_district)) {
					$message_lines[__('Sub District', '12-step-meeting-list')] = "<tr><td>Sub District</td><td>$meeting->sub_district</td></tr>";  
				}

				if (!empty($meeting->website)) {
					$message_lines[__('Website', '12-step-meeting-list')] = "<tr><td>Website</td><td>$meeting->website</td></tr>";  
				}

				if (!empty($meeting->website_2)) {
					$message_lines[__('Website 2', '12-step-meeting-list')] = "<tr><td>Website 2</td><td>$meeting->website_2</td></tr>";  
				}
			
				if (!empty($meeting->mailing_address)) {
					$message_lines[__('Mailing Address', '12-step-meeting-list')] = "<tr><td>Mailing Address</td><td>$meeting->mailing_address</td></tr>";  
				}

				if (!empty($meeting->email)) {
					$message_lines[__('Email', '12-step-meeting-list')] = "<tr><td>Email</td><td>$meeting->email</td></tr>";  
				}

				if (!empty($meeting->phone)) {
					$message_lines[__('Phone', '12-step-meeting-list')] = "<tr><td>Phone</td><td>$meeting->phone</td></tr>";  
				}

				if (!empty($meeting->email)) {
					$message_lines[__('Email', '12-step-meeting-list')] = "<tr><td>Email</td><td>$meeting->email</td></tr>";  
				}

				if (!empty($meeting->group_notes)) {
					$message_lines[__('Group Notes', '12-step-meeting-list')] = "<tr><td>Group Notes</td><td>$meeting->group_notes</td></tr>";  
				}

				if (!empty($meeting->venmo)) {
					$message_lines[__('Venmo', '12-step-meeting-list')] = "<tr><td>Venmo</td><td>$meeting->venmo</td></tr>";  
				}

				if (!empty($meeting->square)) {
					$message_lines[__('Square', '12-step-meeting-list')] = "<tr><td>Square</td><td>$meeting->square</td></tr>";  
				}

				if (!empty($meeting->paypal)) {
					$message_lines[__('Paypal', '12-step-meeting-list')] = "<tr><td>Paypal</td><td>$meeting->paypal</td></tr>";  
				}

				if (!empty($meeting->contact_1_name)) {
					$message_lines[__('Contact 1 Name', '12-step-meeting-list')] = "<tr><td>Contact 1 Name</td><td>$meeting->contact_1_name</td></tr>";  
				}

				if (!empty($meeting->contact_1_email)) {
					$message_lines[__('Contact 1 Email', '12-step-meeting-list')] = "<tr><td>Contact 1 Email</td><td>$meeting->contact_1_email</td></tr>";  
				}

				if (!empty($meeting->contact_1_phone)) {
					$message_lines[__('Contact 1 Phone', '12-step-meeting-list')] = "<tr><td>Contact 1 Phone</td><td>$meeting->contact_1_phone</td></tr>";  
				}

				if (!empty($meeting->contact_2_name)) {
					$message_lines[__('Contact 2 Name', '12-step-meeting-list')] = "<tr><td>Contact 2 Name</td><td>$meeting->contact_2_name</td></tr>";  
				}

				if (!empty($meeting->contact_2_email)) {
					$message_lines[__('Contact 2 Email', '12-step-meeting-list')] = "<tr><td>Contact 2 Email</td><td>$meeting->contact_2_email</td></tr>";  
				}

				if (!empty($meeting->contact_2_phone)) {
					$message_lines[__('Contact 2 Phone', '12-step-meeting-list')] = "<tr><td>Contact 2 Phone</td><td>$meeting->contact_2_phone</td></tr>";  
				}

				if (!empty($meeting->contact_3_name)) {
					$message_lines[__('Contact 3 Name', '12-step-meeting-list')] = "<tr><td>Contact 3 Name</td><td>$meeting->contact_3_name</td></tr>";  
				}

				if (!empty($meeting->contact_3_email)) {
					$message_lines[__('Contact 3 Email', '12-step-meeting-list')] = "<tr><td>Contact 3 Email</td><td>$meeting->contact_3_email</td></tr>";  
				}

				if (!empty($meeting->contact_3_phone)) {
					$message_lines[__('Contact 3 Phone', '12-step-meeting-list')] = "<tr><td>Contact 3 Phone'</td><td>$meeting->contact_3_phone</td></tr>";  
				}
			}

			//if ($host === 'aatemplate-wp.dev.cc') { echo "Initialization Processing finished...<br>"; }

			//---------------   Change Processing - skip for adds, removals, & feedback  --------------------

			if ( $RequestType === 'change' ) {
				//if ($host === 'aatemplate-wp.dev.cc') { echo "Change Processing started...<br>"; }

				$IsChange = false; // must prove to be real change

				//if ($host === 'aatemplate-wp.dev.cc') { echo $chg_typesDescStr.'<br>'; }

				$chg_name = stripslashes(sanitize_text_field($_POST['name']) );
				$chg_day = sanitize_text_field($_POST['day']);
				$chg_time = sanitize_text_field($_POST['start_time']);
				$chg_end_time = sanitize_text_field($_POST['end_time']);
				$chg_types_string = implode(', ', array_filter( $_POST['types'] ) );
				$chg_notes = sanitize_text_field($_POST['content']);
				$chg_conference_url = sanitize_text_field($_POST['conference_url']);
				$chg_conference_url_notes = sanitize_text_field($_POST['conference_url_notes']);
				$chg_conference_phone = sanitize_text_field($_POST['conference_phone']);
				$chg_conference_phone_notes = sanitize_text_field($_POST['conference_phone_notes']);
				$chg_location = stripslashes(sanitize_text_field($_POST['location']));
				$chg_address = stripslashes( sanitize_text_field( $_POST['formatted_address'] ) );
				$chg_region_id = sanitize_text_field($_POST['region']);
				$chg_sub_region = sanitize_text_field($_POST['sub_region']);
				$chg_location_notes = sanitize_text_field($_POST['location_notes'] );
				$chg_group = stripslashes(sanitize_text_field($_POST['group']));
				$chg_district_id = sanitize_text_field($_POST['district']);
				$chg_sub_district = sanitize_text_field($_POST['sub_district'] );
				$chg_group_notes = sanitize_text_field($_POST['group_notes'] );
				$chg_website = sanitize_text_field($_POST['website_1']);
				$chg_website_2 = sanitize_text_field($_POST['website_2']);
				$chg_email = sanitize_text_field($_POST['email']);
				$chg_phone = preg_replace('/[^[:digit:]]/', '', sanitize_text_field($_POST['phone']));
				$chg_mailing_address = stripslashes(sanitize_text_field($_POST['mailing_address']));
				$chg_venmo = sanitize_text_field($_POST['venmo']);
				$chg_square = sanitize_text_field($_POST['square']);
				$chg_paypal = sanitize_text_field($_POST['paypal']);
				$chg_contact_1_name = sanitize_text_field($_POST['contact_1_name']);
				$chg_contact_1_email = sanitize_text_field($_POST['contact_1_email']);
				$chg_contact_1_phone = preg_replace('/[^[:digit:]]/', '', sanitize_text_field($_POST['contact_1_phone']));
				$chg_contact_2_name = sanitize_text_field($_POST['contact_2_name']);
				$chg_contact_2_email = sanitize_text_field($_POST['contact_2_email']);
				$chg_contact_2_phone = preg_replace('/[^[:digit:]]/', '', sanitize_text_field($_POST['contact_2_phone']));
				$chg_contact_3_name = sanitize_text_field($_POST['contact_3_name']);
				$chg_contact_3_email = sanitize_text_field($_POST['contact_3_email']);
				$chg_contact_3_phone = preg_replace('/[^[:digit:]]/', '', sanitize_text_field($_POST['contact_3_phone']));

				$m_name = str_replace("\'s", "", $meeting->post_title );
				$c_name = str_replace("\'s", "", $_POST['name']);
				if ( ( strcmp( $m_name, $c_name ) !== 0) ) {
					$message_lines[__('Meeting', '12-step-meeting-list')] = "<tr><td style='color:red;'>Meeting</td><td'>$c_name</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { 
						$old = explode (' ', $m_name);
						$new = explode (' ', $c_name);
						$diff = array_diff($old, $new);

						echo 'Meeting Name has Changed' . '<br>';
						echo "1 [<b>$m_name</b>]<br>"; 
						echo "2 [<b>$c_name</b>]<br>"; 

						$str = implode(' ', $diff);
						echo 'Text that is changed: <b>' . $str . '</b><br>'; 
					}
				}

				$chg_daytime = tsml_format_day_and_time($chg_day, $chg_time);

				if ($chg_daytime !== $daytime) {
					$message_lines[__('When', '12-step-meeting-list')] = "<tr><td style='color:red;'>When</td><td>$chg_daytime</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') {
						echo "DayTime Changed<br>"; 
						echo "1 [<b>$daytime</b>]<br>"; 
						echo "2 [<b>$chg_daytime</b>]<br>"; 
					}
				}

				if ( $chg_end_time !== $meeting->endtime ) {
					$message_lines[__('End Time', '12-step-meeting-list')] = "<tr><td style='color:red;'>End Time</td><td>$chg_end_time</td></tr>";  
					$IsChange = true;
				}

				$chg_typesDescStr = '';
				$chg_typesDescArray = $_POST['types'];
				$typesArrayHasChanged = false;
				if (!empty($_POST['types'])){
					//if a meeting is both open and closed, make it closed
					if (in_array('C', $chg_typesDescArray) && in_array('O', $chg_typesDescArray)) {
						$chg_typesDescArray = array_diff($chg_typesDescArray, array('O'));
					}
					foreach ($chg_typesDescArray as $mtg_key) {
						$mtg_description = $myTypesArray[$mtg_key];
						$chg_typesDescStr .= $mtg_description.'<br>';
						$chg_typesDescArray[$mtg_key] = $mtg_description;
						if (!in_array($mtg_key, $typesDescArray)) { $typesArrayHasChanged = true; }
						//if ($host === 'aatemplate-wp.dev.cc') { echo "ADD Type key: $mtg_key ----> value: $mtg_description<br>"; }
					}
				}
				else {
					$chg_typesDescStr = 'No Types Selected';
				}

				if ( $typesArrayHasChanged === true )  {
					$message_lines[__('Types', '12-step-meeting-list')] = "<tr><td style='color:red;'>Types</td><td>$chg_typesDescStr</td></tr>";
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { 
						echo "Types Changed<br>";
						echo "1 [<b>$types_string</b>]<br>"; 
						echo "2 [<b>$chg_types_string</b>]<br>"; 
					}
				}

				$old = explode (' ', $meeting->notes);
				$new = explode (' ', $chg_notes);
				if ( $old !==  $new )  {
					// Try a 2nd comparison after dealing with with db notes bug '
					$m_notes = html_entity_decode(stripslashes(sanitize_text_field($meeting->notes)), ENT_QUOTES, 'UTF-8');
					if ( ( strcmp( $m_notes, $chg_notes ) !== 0) ) {
						$message_lines[__('Notes', '12-step-meeting-list')] = "<tr><td style='color:red;'>Notes</td><td>$chg_notes</td></tr>";  
						$IsChange = true;
						if ($host === 'aatemplate-wp.dev.cc') { 
							$diff = array_diff($old, $new);
							echo 'Notes Changed' . '<br>'; 
							echo '1-->[' . $m_notes . ']<br>';
							echo '2-->[' . $chg_notes  . ']<br><br>';
							$str = implode(' ', $diff);
							echo 'Text that is changed: <b>' . $str . '</b><br>'; 
						}
					}
				}

				if ( $chg_conference_url !== $meeting->conference_url ) {
					$message_lines[__('Conference URL', '12-step-meeting-list')] = "<tr><td style='color:red;'>Conference URL</td><td style='color:red;'>$chg_conference_url</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Conference URL Changed:<br>1-->[$meeting->conference_url]<br>2-->[$chg_conference_url]<br>"; }
				}

				$m_notes = str_replace(' ', '', $meeting->conference_url_notes);
				$c_notes = str_replace(' ', '', $chg_conference_url_notes);
				if ( ( strcmp( $m_notes, $c_notes ) !== 0) ) {
					$message_lines[__('Conference URL Notes', '12-step-meeting-list')] = "<tr><td style='color:red;'>Conference URL Notes</td><td>$chg_conference_url_notes</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Conference URL Notes Changed:<br>1-->[$meeting->conference_url_notes]<br>2-->[$chg_conference_url_notes]<br>"; }
				}

				if (  $chg_conference_phone !== $meeting->conference_phone )  {
					$message_lines[__('Conference Phone', '12-step-meeting-list')] = "<tr><td style='color:red;'>Conference Phone</td><td>$chg_conference_phone</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Conference Phone Changed:<br>1-->[$meeting->conference_phone]<br>2-->[$chg_conference_phone]<br>"; }
				}

				$m_notes = str_replace(' ', '', $meeting->conference_phone_notes);
				$c_notes = str_replace(' ', '', $chg_conference_phone_notes);
				if ( ( strcmp( $m_notes, $c_notes ) !== 0) ) {
					$message_lines[__('Conference Phone Notes', '12-step-meeting-list')] = "<tr><td style='color:red;'>Conference Phone Notes</td><td>$chg_conference_phone_notes</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Conference Phone Notes Changed:<br>1-->[$meeting->conference_phone_notes]<br>2-->[$chg_conference_phone_notes]<br>"; }
				}

				if (  $chg_location !== $meeting->location )  {
					$message_lines[__('Location', '12-step-meeting-list')] = "<tr><td style='color:red;'>Location</td><td>$chg_location</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Location Changed:<br>1-->[$meeting->location]<br>2-->[$chg_location]<br>"; }
				}

				if (strcmp(rtrim($meeting->formatted_address), rtrim($_POST['formatted_address'])) !== 0) {
					$message_lines[__('Address', '12-step-meeting-list')] = "<tr><td style='color:red;'>Address</td><td>$chg_address</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { 
						echo 'Address Changed' . '<br>'; 
						echo '1-->[' . $meeting->formatted_address . ']<br>';
						echo '2-->[' . $chg_address  . ']<br>';
					}
				}

				if ( $chg_region_id != $meeting->region_id) {
					$chg_region = '';
					$chg_region = get_the_category_by_ID($chg_region_id);
					$message_lines[__('Region', '12-step-meeting-list')] = "<tr><td style='color:red;'>Region</td><td>$chg_region</td></tr>"; 
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Region Changed<br>"; }
				}

				if ( $chg_sub_region !== $meeting->sub_region ) {
					$message_lines[__('Sub Region', '12-step-meeting-list')] = "<tr><td style='color:red;'>Sub Region</td><td>$chg_sub_region</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Sub Region Changed<br>"; }
				}

				if ( $chg_location_notes !== $meeting->location_notes ) {
					$message_lines[__('Location Notes', '12-step-meeting-list')] = "<tr><td style='color:red;'>Location Notes</td><td>$chg_location_notes</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Location Notes Changed:<br>1-->[$meeting->location_notes]<br>2-->[$chg_location_notes]<br>"; }
				}

				/* Addition Group Information - when meeting is registered group with an id */
				if ( ( strcmp( $meeting->group, $chg_group ) !== 0) ) {
					$message_lines[__('Group Name', '12-step-meeting-list')] = "<tr><td style='color:red;'>Group Name</td><td>$chg_group</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { 
						echo 'Group Name has Changed' . '<br>';
						echo "1 [<b>$meeting->group</b>]<br>"; 
						echo "2 [<b>$chg_group</b>]<br>"; 
					}
				}

				if ( !empty( $_POST['district'] ) ) {
					$chg_district = get_the_category_by_ID($chg_district_id);
					if ( strlen($chg_district) > 0  && $meeting->district_id != $chg_district_id ) {					
						$message_lines[__('District', '12-step-meeting-list')] = "<tr><td style='color:red;'>District</td><td>$chg_district</td></tr>"; 
						$IsChange = true;
						if ($host === 'aatemplate-wp.dev.cc') { 
							echo "District changed <br>"; 
							echo "1 [<b>$meeting->district</b>]<br>"; 
							echo "2 [<b>$chg_district</b>]<br>"; 
						}
					}
				}

				if ($meeting->sub_district != $chg_sub_district) {
					$message_lines[__('Sub District', '12-step-meeting-list')] = "<tr><td style='color:red;'>Sub District</td><td>$chg_sub_district</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Sub District Changed<br>"; }
				}

				if ( $meeting->group_notes != $chg_group_notes) {
					$message_lines[__('Group Notes', '12-step-meeting-list')] = "<tr><td style='color:red;'>Group Notes</td><td>$chg_group_notes</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Group Notes Changed<br>"; }
				}

				if ( $meeting->website != $chg_website ) {
					$message_lines[__('Website', '12-step-meeting-list')] = "<tr><td style='color:red;'>Website</td><td>$chg_website</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Website Changed<br>1-->[$meeting->website]<br>2-->[$chg_website]<br>"; }
				}

				if ( $meeting->website_2 != $chg_website_2 ) {
					$message_lines[__('Website 2', '12-step-meeting-list')] = "<tr><td style='color:red;'>Website 2</td><td>$chg_website_2</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Website 2 Changed<br>"; }
				}
			
				if ( $meeting->email != $chg_email ) {
					$message_lines[__('Email', '12-step-meeting-list')] = "<tr><td style='color:red;'>Email</td><td>$chg_email</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Group Email Changed:<br>1-->[$meeting->email]<br>2-->[$chg_email]<br>"; }
				}

				if ( $meeting->phone != $chg_phone ) {
					$message_lines[__('Phone', '12-step-meeting-list')] = "<tr><td style='color:red;'>Phone</td><td>$chg_phone</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Group Phone Changed:<br>1-->[$meeting->phone]<br>2-->[$chg_phone]<br>"; }
				}

				if ( $meeting->mailing_address != $chg_mailing_address ) {
					$message_lines[__('Mailing Address', '12-step-meeting-list')] = "<tr><td style='color:red;'>Mailing Address</td><td>$chg_mailing_address</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Group Mailing Address Changed:<br>1-->[$meeting->mailing_address]<br>2-->[$chg_mailing_address]<br>"; }
				}

				if ( $meeting->venmo != $chg_venmo) {
					$message_lines[__('Venmo', '12-step-meeting-list')] = "<tr><td style='color:red;'>Venmo</td><td >$chg_venmo</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Venmo Changed<br>"; }
				}

				if ( $meeting->square != $chg_square) {
					$message_lines[__('Square', '12-step-meeting-list')] = "<tr><td style='color:red;'>Square</td><td>$chg_square</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Square Changed<br>"; }
				}

				if ( $meeting->paypal != $chg_paypal) {
					$message_lines[__('Paypal', '12-step-meeting-list')] = "<tr><td style='color:red;'>Paypal</td><td>$chg_paypal</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "PayPal Changed<br>"; }
				}

				if ( $meeting->contact_1_name != $chg_contact_1_name ) {
					$message_lines[__('Contact 1 Name', '12-step-meeting-list')] = "<tr><td style='color:red;'>Contact 1 Name</td><td>$chg_contact_1_name</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Contact 1 Name Changed<br>"; }
				}

				if ( $meeting->contact_1_email != $chg_contact_1_email ) {
					$message_lines[__('Contact 1 Email', '12-step-meeting-list')] = "<tr><td style='color:red;'>Contact 1 Email</td><td>$chg_contact_1_email</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Contact 1 Email Changed<br>"; }
				}

				if ( $meeting->contact_1_phone != $chg_contact_1_phone ) {
					$message_lines[__('Contact 1 Phone', '12-step-meeting-list')] = "<tr><td style='color:red;'>Contact 1 Phone</td><td>$chg_contact_1_phone</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Contact 1 Phone Changed:<br>1-->[$meeting->contact_1_phone]<br>2-->[$chg_contact_1_phone]<br>"; }
				}

				if ( $meeting->contact_2_name != $chg_contact_2_name ) {
					$message_lines[__('Contact 2 Name', '12-step-meeting-list')] = "<tr><td style='color:red;'>Contact 2 Name</td><td>$chg_contact_2_name</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Contact 2 Name Changed<br>"; }
				}

				if ( $meeting->contact_2_email != $chg_contact_2_email ) {
					$message_lines[__('Contact 2 Email', '12-step-meeting-list')] = "<tr><td style='color:red;'>Contact 2 Email</td><td>$chg_contact_2_email</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Contact 2 Email Changed<br>"; }
				}

				if ( $meeting->contact_2_phone != $chg_contact_2_phone ) {
					$message_lines[__('Contact 2 Phone', '12-step-meeting-list')] = "<tr><td style='color:red;'>Contact 2 Phone</td><td >$chg_contact_2_phone</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Contact 2 Phone Changed<br>"; }
				}

				if ( $meeting->contact_3_name != $chg_contact_3_name ) {
					$message_lines[__('Contact 3 Name', '12-step-meeting-list')] = "<tr><td<td style='color:red;'>Contact 3 Name</td><td>$chg_contact_3_name</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Contact 3 Name Changed<br>"; }
				}

				if ( $meeting->contact_3_email != $chg_contact_3_email ) {
					$message_lines[__('Contact 3 Email', '12-step-meeting-list')] = "<tr><td style='color:red;'>Contact 3 Email</td><td>$chg_contact_3_email</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Contact 3 Email Changed<br>"; }
				}

				if ( $meeting->contact_3_phone != $chg_contact_3_phone ) {
					$message_lines[__('Contact 3 Phone', '12-step-meeting-list')] = "<tr><td style='color:red;'>Contact 3 Phone</td><td>$chg_contact_3_phone</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Contact 3 Phone Changed<br>"; }
				}


				//if ($host === 'aatemplate-wp.dev.cc') { echo "<br>Change Processing finished...<br>"; }

				if ( !$IsChange ) {
					$IsFeedback = true;
				}
			}
		}

		if ($host === 'aatemplate-wp.dev.cc') { 
			echo "__________________________________________________________<br>"; 
			echo "Is New: $IsNew , Is Change: $IsChange, Is Remove: $IsRemove , Is Feedback: $IsFeedback<br>";
			echo "__________________________________________________________<br>"; 
		}

		//---------------   New Meeting Processing - skip for removals  --------------------

		if ( $RequestType === 'new') {

			//if ($host === 'aatemplate-wp.dev.cc') { echo "New Processing started...<br>"; }

			$message  = '<p style="padding-bottom: 20px; border-bottom: 2px dashed #ccc; margin-bottom: 20px;">' . nl2br(sanitize_text_area(stripslashes($_POST['tsml_message']))) . '</p>';
			$message .= "<table border='1' style='width:600px;'><tbody>";

			$new_name = stripslashes(sanitize_text_field($_POST['new_name']));
			$meeting  = tsml_get_meeting();
			$permalink = get_permalink($meeting->ID);
			$new_day = sanitize_text_field($_POST['new_day']);
			$new_time = sanitize_text_field($_POST['new_time']);
			$new_daytime = tsml_format_day_and_time( $new_day, $new_time );

			//------------------ Continue with HTML table construction ----------------------
			$message_lines = array(
				__('Requestor', '12-step-meeting-list') =>  "<tr><td>Requestor</td><td>$name <a href='mailto:' $email > $email </a>;</td></tr>",
				__('Meeting', '12-step-meeting-list') => "<tr><td>Meeting</td><td style='color:blue;' >$new_name</td></tr>'",
				__('When', '12-step-meeting-list') => "<tr><td>When</td><td style='color:blue;' >$new_daytime</td></tr>",
			);

			$new_end_time = sanitize_text_field($_POST['new_end_time']);
			//$new_types_string = implode(', ', $_POST['new_types']);
			$new_notes = sanitize_text_field($_POST['new_content']);
			$new_conference_url = sanitize_text_field($_POST['new_conference_url']);
			$new_conference_url_notes = sanitize_text_field($_POST['new_conference_url_notes']);
			$new_conference_phone = sanitize_text_field($_POST['new_conference_phone']);
			$new_location = stripslashes( sanitize_text_field($_POST['new_location']));
			$new_address = stripslashes( sanitize_text_field($_POST['new_formatted_address']));
			$new_region_id = sanitize_text_field($_POST['new_region']);
			$new_region = get_the_category_by_ID($new_region_id);
			$new_sub_region = sanitize_text_field($_POST['new_sub_region']);
			$new_location_notes = sanitize_text_field($_POST['new_location_notes'] );
			$new_group = sanitize_text_field($_POST['new_group']);

			$new_typesDescArray = $_POST['new_types'];
			// If Conference URL, validate; or if phone, force 'ONL' type, else remove 'ONL'
			if (!empty( $new_conference_url ) ) {
				$url = esc_url_raw($new_conference_url, array('http', 'https'));
				if (tsml_conference_provider($url)) {
					$new_conference_url = $url;
					$new_typesDescArray = array_values(array_diff($_POST['new_types'], array('ONL')));
					//$new_types_string .= 'ONL';
				} else {
					$new_conference_url = null;
					$new_conference_url_notes = null;
					$new_conference_phone = null;
				}
			} 

			//echo '=====================  New MTG Array   =====================================';
			$new_typesDescStr = '';

			if ( ( !empty( $new_typesDescArray ) ) && ( is_array( $new_typesDescArray ) ) ) {

				//if a meeting is both open and closed, make it closed
				if (in_array('C', $new_typesDescArray) && in_array('O', $new_typesDescArray)) {
					$new_typesDescArray = array_diff($new_typesDescArray, array('O'));
				}

				foreach ( $new_typesDescArray as $mtg_key) {
					$mtg_description = $myTypesArray[$mtg_key];
					$new_typesDescStr .= $mtg_description.'<br>';
				}
			}
			else {
				$new_typesDescStr = 'No Types Selected';
			}
			if ($host === 'aatemplate-wp.dev.cc') { echo $new_typesDescStr.'<br>'; }

			if ( !empty($new_end_time) ) {
				$message_lines[__('End Time', '12-step-meeting-list')] = "<tr><td>End Time</td><td style='color:blue;'>$new_end_time</td></tr>";  
			}
			
			if ( !empty($new_typesDescStr) ) {
				$message_lines[__('Types', '12-step-meeting-list')] = "<tr><td>Types</td><td style='color:blue;'>$new_typesDescStr</td></tr>";
			}

			if ( !empty($new_notes) ) {
				$message_lines[__('Notes', '12-step-meeting-list')] = "<tr><td>Notes</td><td style='color:blue;'>$new_notes</td></tr>";  
			}

			if ( !empty($new_conference_url) ) {
				$message_lines[__('URL', '12-step-meeting-list')] = "<tr><td>URL</td><td style='color:blue;'>$new_conference_url</td></tr>";  
			}

			if ( !empty($new_conference_url_notes) ) {
				$message_lines[__('Conference URL Notes', '12-step-meeting-list')] = "<tr><td>Conference URL Notes</td><td style='color:blue;'>$new_conference_url_notes</td></tr>";  
			}

			if ( !empty($new_conference_phone) ) {
				$message_lines[__('Conference Phone', '12-step-meeting-list')] = "<tr><td>Conference Phone</td><td style='color:blue;'>$new_conference_phone</td></tr>";  
			}

			if (!empty($new_conference_phone_notes)) {
				$message_lines[__('Conference Phone Notes', '12-step-meeting-list')] = "<tr><td>Conference Phone Notes</td><td style='color:blue;'>$meeting->new_conference_phone_notes</td></tr>";  
			}

			if ( !empty($new_location) ) {
				$message_lines[__('Location', '12-step-meeting-list')] = "<tr><td>Location</td><td style='color:blue;'>$new_location</td></tr>";  
			}

			if ( !empty($new_address) ) {
				$message_lines[__('Address', '12-step-meeting-list')] = "<tr><td>Address</td><td style='color:blue;'>$new_address</td></tr>";  
			}

			if ( !empty($new_region_id) ) {
				$new_region = '';
				$new_region = get_the_category_by_ID($new_region_id);
				$message_lines[__('Region', '12-step-meeting-list')] = "<tr><td>Region</td><td style='color:blue;'>$new_region</td></tr>";  
			}

			if ( !empty($new_sub_region) ) {
				$message_lines[__('Sub Region', '12-step-meeting-list')] = "<tr><td>Sub Region</td><td style='color:blue;'>$new_sub_region</td></tr>";  
			}

			if ( !empty($new_location_notes) ) {
				$message_lines[__('Location Notes', '12-step-meeting-list')] = "<tr><td>Location Notes</td><td style='color:blue;'>$new_location_notes</td></tr>";  
			}

			//--------------- Do Additional Processing for a New Meeting --------------------

			if ( 1 == 1 )  {

				$new_district_id = sanitize_text_field($_POST['new_district_id']);
				$new_sub_district = sanitize_text_field($_POST['new_sub_district'] );
				$new_group_notes = sanitize_text_field($_POST['new_group_notes'] );
				$new_website = sanitize_text_field($_POST['new_website']);
				$new_website_2 = sanitize_text_field($_POST['new_website_2']);
				$new_email = sanitize_text_field($_POST['new_email']);
				$new_phone = preg_replace('/[^[:digit:]]/', '', sanitize_text_field($_POST['new_phone']));
				$new_mailing_address = stripslashes(sanitize_text_field($_POST['new_mailing_address']));
				$new_venmo = sanitize_text_field($_POST['new_venmo']);
				$new_square = sanitize_text_field($_POST['new_square']);
				$new_paypal = sanitize_text_field($_POST['new_paypal']);
				$new_contact_1_name = sanitize_text_field($_POST['new_contact_1_name']);
				$new_contact_1_email = sanitize_text_field($_POST['new_contact_1_email']);
				$chg_contact_1_phone = preg_replace('/[^[:digit:]]/', '', sanitize_text_field($_POST['contact_1_phone']));
				$new_contact_2_name = sanitize_text_field($_POST['new_contact_2_name']);
				$new_contact_2_email = sanitize_text_field($_POST['new_contact_2_email']);
				$chg_contact_2_phone = preg_replace('/[^[:digit:]]/', '', sanitize_text_field($_POST['contact_2_phone']));
				$new_contact_3_name = sanitize_text_field($_POST['new_contact_3_name']);
				$new_contact_3_email = sanitize_text_field($_POST['new_contact_3_email']);
				$chg_contact_3_phone = preg_replace('/[^[:digit:]]/', '', sanitize_text_field($_POST['contact_3_phone']));

				if ( !empty($new_district_id) ) {
					$new_district_name = '';
					$new_district_name = get_the_category_by_ID($new_district_id);
					$message_lines[__('District', '12-step-meeting-list')] = "<tr><td>District</td><td style='color:blue' >$new_district_name</td></tr>"; 
				}

				if (!empty($new_district)) {
					$message_lines[__('District', '12-step-meeting-list')] = "<tr><td>District</td><td style='color:blue' >$new_district</td></tr>";  
				}

				if (!empty($new_sub_district)) {
					$message_lines[__('Sub District', '12-step-meeting-list')] = "<tr><td>Sub District</td><td style='color:blue' >$new_sub_district</td></tr>";  
				}

				if (!empty($new_group_notes)) {
					$message_lines[__('Group Notes', '12-step-meeting-list')] = "<tr><td>Group Notes</td><td style='color:blue' >$new_group_notes</td></tr>";  
				}

				if (!empty($new_website)) {
					$message_lines[__('Website', '12-step-meeting-list')] = "<tr><td>Website</td><td style='color:blue' >$new_website</td></tr>";  
				}

				if (!empty($new_website_2)) {
					$message_lines[__('Website 2', '12-step-meeting-list')] = "<tr><td>Website 2</td><td style='color:blue' >$new_website_2</td></tr>";  
				}
			
				if (!empty($new_phone)) {
					$message_lines[__('Phone', '12-step-meeting-list')] = "<tr><td>Phone</td><td style='color:blue' >$new_phone</td></tr>";  
				}

				if (!empty($new_mailing_address)) {
					$message_lines[__('Mailing Address', '12-step-meeting-list')] = "<tr><td>Mailing Address</td><td style='color:blue' >$new_mailing_address</td></tr>";  
				}

				if (!empty($new_email)) {
					$message_lines[__('Email', '12-step-meeting-list')] = "<tr><td>Email</td><td style='color:blue' >$new_email</td></tr>";  
				}

				if (!empty($new_email)) {
					$message_lines[__('Email', '12-step-meeting-list')] = "<tr><td>Email</td><td style='color:blue' >$new_email</td></tr>";  
				}

				if (!empty($new_venmo)) {
					$message_lines[__('Venmo', '12-step-meeting-list')] = "<tr><td>Venmo</td><td style='color:blue' >$new_venmo</td></tr>";  
				}

				if (!empty($new_square)) {
					$message_lines[__('Square', '12-step-meeting-list')] = "<tr><td>Square</td><td style='color:blue' >$new_square</td></tr>";  
				}

				if (!empty($new_paypal)) {
					$message_lines[__('Paypal', '12-step-meeting-list')] = "<tr><td>Paypal</td><td style='color:blue' >$new_paypal</td></tr>";  
				}

				if (!empty($new_contact_1_name)) {
					$message_lines[__('Contact 1 Name', '12-step-meeting-list')] = "<tr><td>Contact 1 Name</td><td style='color:blue' >$new_contact_1_name</td></tr>";  
				}

				if (!empty($new_contact_1_email)) {
					$message_lines[__('Contact 1 Email', '12-step-meeting-list')] = "<tr><td>Contact 1 Email</td><td style='color:blue' >$new_contact_1_email</td></tr>";  
				}

				if (!empty($new_contact_1_phone)) {
					$message_lines[__('Contact 1 Phone', '12-step-meeting-list')] = "<tr><td>Contact 1 Phone'</td><td style='color:blue' >$new_contact_1_phone</td></tr>";  
				}

				if (!empty($new_contact_2_name)) {
					$message_lines[__('Contact 2 Name', '12-step-meeting-list')] = "<tr><td>Contact 2 Name</td><td style='color:blue' >$new_contact_2_name</td></tr>";  
				}

				if (!empty($new_contact_2_email)) {
					$message_lines[__('Contact 2 Email', '12-step-meeting-list')] = "<tr><td>Contact 2 Email</td><td style='color:blue' >$new_contact_2_email</td></tr>";  
				}

				if (!empty($new_contact_2_phone)) {
					$message_lines[__('Contact 2 Phone', '12-step-meeting-list')] = "<tr><td>Contact 2 Phone'</td><td style='color:blue' >$new_contact_2_phone</td></tr>";  
				}

				if (!empty($new_contact_3_name)) {
					$message_lines[__('Contact 3 Name', '12-step-meeting-list')] = "<tr><td>Contact 3 Name</td><td style='color:blue' >$new_contact_3_name</td></tr>";  
				}

				if (!empty($new_contact_3_email)) {
					$message_lines[__('Contact 3 Email', '12-step-meeting-list')] = "<tr><td>Contact 3 Email</td><td style='color:blue' >$new_contact_3_email</td></tr>";  
				}

				if (!empty($new_contact_3_phone)) {
					$message_lines[__('Contact 3 Phone', '12-step-meeting-list')] = "<tr><td>Contact 3 Phone</td><td style='color:blue' >$new_contact_3_phone</td></tr>";  
				}

				if ($host === 'aatemplate-wp.dev.cc') { echo "Additional Processing finished...<br>"; }
			}

			if ($host === 'aatemplate-wp.dev.cc') { echo "New Processing finished...<br>"; }
		}

		//--------------- Apply concatenated lines and close up the message --------------------
		foreach	($message_lines as $key => $value) {
			$message .= $value;
		}
		$message .= "</tbody></table>";
		/************************************ Send Email ****************************************/

		//email vars
		if (!isset($_POST['tsml_nonce']) || !wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
			_e("<div class='bg-danger text-dark'> Error: nonce value not set correctly. Email was not sent.</div>", '12-step-meeting-list');
		if ($host === 'aatemplate-wp.dev.cc') { echo "Nounce Error...<br>"; }
		}
		elseif (empty($tsml_feedback_addresses) || empty($name) || !is_email($email) || empty($message_lines) ) {
			_e("<div class='bg-danger text-dark'> Error: required form values missing. Email was not sent.<br></div>", '12-step-meeting-list');
				echo "Missing Form Input Error...<br>";
		}
		elseif (empty($_POST['tsml_message']) && $IsFeedback ) {
			_e("<div class='bg-danger text-dark'> Error: required Message from feedback form missing. Email was not sent.<br></div>", '12-step-meeting-list');
			if ($host === 'aatemplate-wp.dev.cc') { echo "No Feedback Message Error...<br>"; }
		}
		elseif ( $IsRemove ) {
			if ($host === 'aatemplate-wp.dev.cc') { echo "Sending Removal Request Email...<br>"; }
			//send Removal Request HTML email
			$subject = __('Meeting Removal Request', '12-step-meeting-list') . ': ' . $post_title;
			if (tsml_email($tsml_feedback_addresses, str_replace("'s", "s", $subject), $message, $name . ' <' . $email . '>')) {
				_e("<div class='bg-secondary text-white'> Thank you $name for helping keep our meeting list up-to-date. <br><br> You will receive a response email as soon as this request is processed by the site administrator.<br></div>", '12-step-meeting-list');
			} 
			else {
				global $phpmailer;
				if (!empty($phpmailer->ErrorInfo)) {
					printf(__('Error: %s', '12-step-meeting-list'), $phpmailer->ErrorInfo);
				} 
				else {
					_e("<div class='bg-warning text-dark'>An error occurred while sending email!<br></div>", '12-step-meeting-list');
				}
			}
			remove_filter('wp_mail_content_type', 'tsml_email_content_type_html');
		}
		elseif ( $IsNew ) {
			if ($host === 'aatemplate-wp.dev.cc') { echo "Sending New Meeting Request...<br>"; }
			//send New Request HTML email
			$subject = __('New Meeting Request', '12-step-meeting-list') . ': ' . $new_name;
			if (tsml_email($tsml_feedback_addresses, str_replace("'s", "s", $subject), $message, $name . ' <' . $email . '>')) {
				_e("<div class='bg-success text-white'> Thank you $name for helping keep our meeting list current with your new listing. <br><br> You will receive a response email as soon as this request is processed by the site administrator.<br></div>", '12-step-meeting-list');
			} 
			else {
				global $phpmailer;
				if (!empty($phpmailer->ErrorInfo)) {
					printf(__('Error: %s', '12-step-meeting-list'), $phpmailer->ErrorInfo);
				} 
				else {
					_e("<div class='bg-warning text-dark'>An error occurred while sending email!</div>", '12-step-meeting-list');
				}
			}
			remove_filter('wp_mail_content_type', 'tsml_email_content_type_html');
		}
		elseif ( $IsChange )  {
			if ($host === 'aatemplate-wp.dev.cc') { echo "Sending Change Meeting Request ...<br>"; }
			//send Change Request HTML email 
			$subject = __('Meeting Change Request', '12-step-meeting-list') . ': ' . $post_title;
			if (tsml_email($tsml_feedback_addresses, str_replace("'s", "s", $subject), $message, $name . ' <' . $email . '>')) {
				_e("<div class='bg-success text-light'> Thank you $name for helping keep our meeting list current with your latest changes.<br><br> You will receive a response email as soon as this request is processed by the site administrator. <br><br> That usually happens within 24 hours or so...<br></div>", '12-step-meeting-list');
			} 
			else {
				global $phpmailer;
				if (!empty($phpmailer->ErrorInfo)) {
					printf(__('Error: %s', '12-step-meeting-list'), $phpmailer->ErrorInfo);
				} 
				else {
					_e("<div class='bg-warning text-dark'>An error occurred while sending email!</div>", '12-step-meeting-list');
				}
			}
			remove_filter('wp_mail_content_type', 'tsml_email_content_type_html');
		}
		else {
			if ($host === 'aatemplate-wp.dev.cc') { echo "Sending Feedback Email...<br>"; }
			//send Feedback HTML email - without bootstrap in echo
			$subject = __('Meeting Feedback Form', '12-step-meeting-list') . ': ' . $post_title;
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

		/************************************ EXITl ****************************************/
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
		global $tsml_data_sources;

		$meetings	= get_option('tsml_import_buffer', array());
		$errors		= array();
		$limit		= 25;

		//manage import buffer
		if (count($meetings) > $limit) {
			//slice off the first batch, save the remaining back to the import buffer
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

		//passing post_modified and post_modified_gmt to wp_insert_post() below does not seem to work
		//todo occasionally remove this to see if it is working
		add_filter('wp_insert_post_data', 'tsml_import_post_modified', 99, 2);

		foreach ($meetings as $meeting) {
			//check address
			if (empty($meeting['formatted_address'])) {
				$errors[] = '<li value="' . $meeting['row'] . '">' . sprintf(__('No location information provided for <code>%s</code>.', '12-step-meeting-list'), $meeting['name']) . '</li>';
				continue;
			}

			//geocode address
			$geocoded = tsml_geocode($meeting['formatted_address']);

			if ($geocoded['status'] == 'error')	{
				$errors[] = '<li value="' . $meeting['row'] . '">' . $geocoded['reason'] . '</li>';
				continue;
			}

			//try to guess region from geocode
			if (empty($meeting['region']) && !empty($geocoded['city'])) $meeting['region'] = $geocoded['city'];

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
			if (empty($meeting['group'])) {
				$group_id = null;
			} else {
				if (!array_key_exists($meeting['group'], $groups)) {
					$group_id = wp_insert_post(array(
					  	'post_type'		=> 'tsml_group',
					  	'post_status'	=> 'publish',
						'post_title'	=> $meeting['group'],
						'post_content'  => empty($meeting['group_notes']) ? '' : $meeting['group_notes'],
					));

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

			//save meeting to this location
			$options = array(
				'post_title'		=> $meeting['name'],
				'post_type'			=> 'tsml_meeting',
				'post_status'		=> 'publish',
				'post_parent'		=> $location_id,
				'post_content'		=> trim($meeting['notes']), //not sure why recursive trim not catching this
				'post_modified'		=> $meeting['post_modified'],
				'post_modified_gmt'	=> $meeting['post_modified_gmt'],
				'post_author'		=> $meeting['post_author'],
			);
			if (!empty($meeting['slug'])) $options['post_name'] = $meeting['slug'];
			$meeting_id = wp_insert_post($options);

			//add day and time(s) if not appointment meeting
			if (!empty($meeting['time']) && (!empty($meeting['day']) || (string) $meeting['day'] === '0')) {
				add_post_meta($meeting_id, 'day',  $meeting['day']);
				add_post_meta($meeting_id, 'time', $meeting['time']);
				if (!empty($meeting['end_time'])) add_post_meta($meeting_id, 'end_time', $meeting['end_time']);
			}

			//add custom meeting fields if available
			foreach (array('types', 'data_source', 'conference_url', 'conference_url_notes', 'conference_phone', 'conference_phone_notes') as $key) {
				if (!empty($meeting[$key])) add_post_meta($meeting_id, $key, $meeting[$key]);
			}

			// Add Group Id and group specific info if applicable
			if (!empty($group_id)) {
				//link group to meeting
				add_post_meta($meeting_id, 'group_id', $group_id);
			}

			//handle contact information (could be meeting or group)
			$contact_entity_id = empty($group_id) ? $meeting_id : $group_id;
			for ($i = 1; $i <= GROUP_CONTACT_COUNT; $i++) {
				foreach (array('name', 'phone', 'email') as $field) {
					$key = 'contact_' . $i . '_' . $field;
					if (!empty($meeting[$key])) update_post_meta($contact_entity_id, $key, $meeting[$key]);
				}
			}

			if (!empty($meeting['website'])) {
				update_post_meta($contact_entity_id, 'website', esc_url_raw($meeting['website'], array('http', 'https')));
			}

			if (!empty($meeting['website_2'])) {
				update_post_meta($contact_entity_id, 'website_2', esc_url_raw($meeting['website_2'], array('http', 'https')));
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

		wp_send_json(array(
			'errors'		=> $errors,
			'remaining'		=> count($remaining),
			'counts'		=> compact('meetings', 'locations', 'regions', 'groups'),
			'data_sources' 	=> $tsml_data_sources,
			'descriptions'	=> array(
				'meetings'	=> sprintf(_n('%s meeting', '%s meetings', $meetings, '12-step-meeting-list'), number_format_i18n($meetings)),
				'locations'	=> sprintf(_n('%s location', '%s locations', $locations, '12-step-meeting-list'), number_format_i18n($locations)),
				'groups'	=> sprintf(_n('%s group', '%s groups', $groups, '12-step-meeting-list'), number_format_i18n($groups)),
				'regions'	=> sprintf(_n('%s region', '%s regions', $regions, '12-step-meeting-list'), number_format_i18n($regions)),
			),
		));
	}
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
		if (tsml_email(MEETING_GUIDE_APP_NOTIFY, 'Sharing Key', $message)) {
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
	$url        = get_post_meta( $meeting_id, 'conference_url', true );
	if ( $url ) {
		wp_send_json_success(array('meeting' => $url));
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
	$phone      = get_post_meta( $meeting_id, 'conference_phone', true );
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
