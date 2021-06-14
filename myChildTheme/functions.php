<?php

add_action( 'wp_enqueue_scripts', 'enqueue_parent_styles' );
function enqueue_parent_styles() {
   wp_enqueue_style( 'parent-style', get_template_directory_uri().'/style.css' );
}

function wpbootstrap_enqueue_styles() {
   wp_enqueue_style( 'bootstrap', '//stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css' );
   wp_enqueue_style( 'my-style', get_template_directory_uri() . '/style.css');
}
add_action('wp_enqueue_scripts', 'wpbootstrap_enqueue_styles');

add_filter('wrap_red', function( $input ) {
	return "<span style='color: red'>$input</span>";
});

/* Workaround to prevent logging of Site Health “Scheduled event has failed” */
add_filter('action_scheduler_run_queue', function($arg) { return 86400; });

add_filter('wpcf7_form_tag_data_option', function($n, $options, $args) {
   if (in_array('m_times', $options)){
     $data = array(
     "12:00 AM",  
     "12:30 AM",  
     "1:00 AM",   
     "1:30 AM",   
     "2:00 AM",   
     "2:30 AM",   
     "3:00 AM",   
     "3:30 AM",   
     "4:00 AM",   
     "4:30 AM",   
     "5:00 AM",   
     "5:30 AM",   
     "6:00 AM",   
     "6:30 AM",   
     "7:00 AM",   
     "7:30 AM",   
     "8:00 AM",   
     "8:30 AM",   
     "9:00 AM",   
     "9:30 AM",   
     "10:00 AM",  
     "10:10 AM",  
     "10:30 AM",  
     "11:00 AM",  
     "11:30 AM",  
     "12:00 PM",  
     "12:01 PM",  
     "12:15 PM",  
     "12:30 PM",  
     "1:00 PM",   
     "1:30 PM",   
     "2:00 PM",   
     "2:30 PM",   
     "3:00 PM",   
     "3:30 PM",   
     "4:00 PM",   
     "4:30 PM",   
     "5:00 PM",   
     "5:15 PM",   
     "5:30 PM",   
     "6:00 PM",   
     "6:30 PM",   
     "7:00 PM",   
     "7:15 PM",   
     "7:30 PM",   
     "8:00 PM",   
     "8:06 PM",   
     "8:15 PM",   
     "8:30 PM",   
     "9:00 PM",   
     "9:30 PM",   
     "10:00 PM",  
     "10:30 PM",  
     "11:00 PM",  
     "11:30 PM",  
     "11:59 PM" 
   );
     return $data;
   }
   return $n;
 }, 10, 55);
 
 function ca_load_jquery_ui() {
    // first, register the style from the remote official source
    wp_register_style('jqueryuicss', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.min.css', array('jquery-ui-styles'), '1.12.1');
    wp_enqueue_style('jqueryuicss');
    // then, register the core file from the remote official source, in footer
    wp_register_script('jqueryui', '//code.jquery.com/ui/1.12.1/jquery-ui.min.js', array('jquery-ui'), '1.12.1', true);
    wp_enqueue_script('jqueryui');
}
add_action( 'wp_enqueue_scripts', 'ca_load_jquery_ui' );

/* ****************************   12 Step Meeting List Override Code   **************************************** */
 $tsml_columns = array(
   'region' => 'City',
   'time' => 'Time',
   'distance' => 'Distance',
   'name' => 'Name',
   'location' => 'Location',
   'address' => 'Address'
);

//$tsml_defaults['day'] = null;
$tsml_defaults['distance'] = 25;
//$tsml_conference_providers = array('Zoom');

function theme_override_tsml_strings($translated_text, $text, $domain) {
   if ($domain == '12-step-meeting-list') {
      switch ($translated_text) {
         case 'Region':
            return 'City';
         case 'Sub Region':
            return 'Neighborhood'; 
      }
   }
   return $translated_text;
}
add_filter('gettext', 'theme_override_tsml_strings', 20, 3);


/* ****************************    tsml_ajax_feedback Override    **************************************** */
remove_action( "wp_ajax_tsml_feedback", "tsml_ajax_feedback");
remove_action( "wp_ajax_nopriv_tsml_feedback", "tsml_ajax_feedback");
add_action("wp_ajax_tsml_feedback", "pcs_tsml_ajax_feedback");
add_action("wp_ajax_nopriv_tsml_feedback", "pcs_tsml_ajax_feedback");
if (!function_exists('pcs_tsml_ajax_feedback')) {
	function pcs_tsml_ajax_feedback() {
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

				echo 'The submit button for a ' . $_POST['submit'] . ' request was pressed.<br />';
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
			if ($host === 'aatemplate-wp.dev.cc') { echo "Initialization Processing started...<br>"; }
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

			if ($host === 'aatemplate-wp.dev.cc') { echo $typesDescStr.'<br>'; }

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

			if ($host === 'aatemplate-wp.dev.cc') { echo "Initialization Processing finished...<br>"; }

			//---------------   Change Processing - skip for adds, removals, & feedback  --------------------

			if ( $RequestType === 'change' ) {
				if ($host === 'aatemplate-wp.dev.cc') { echo "Change Processing started...<br>"; }

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
				$chg_website = sanitize_text_field($_POST['website']);
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

				if ($host === 'aatemplate-wp.dev.cc') { echo $chg_typesDescStr.'<br>'; }

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
					// Try a 2nd comparison with white space removed
					$m_notes = str_replace(' ', '', $meeting->notes);
					$c_notes = str_replace(' ', '', $chg_notes);
					if ( ( strcmp( $m_notes, $c_notes ) !== 0) ) {
						$message_lines[__('Notes', '12-step-meeting-list')] = "<tr><td style='color:red;'>Notes</td><td>$chg_notes</td></tr>";  
						$IsChange = true;
						if ($host === 'aatemplate-wp.dev.cc') { 
							$diff = array_diff($old, $new);
							echo 'Notes Changed' . '<br>'; 
							echo '1-->[' . $meeting->notes . ']<br>';
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
					if ($host === 'aatemplate-wp.dev.cc') { echo "Location Notes Changed:<br>1-->[$meeting->location_notes]<br>2-->[$chg_location_notes]<br>"; }
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

				if ( (!empty($chg_sub_district) ) && ($meeting->sub_district != $chg_sub_district) ) {
					$message_lines[__('Sub District', '12-step-meeting-list')] = "<tr><td style='color:red;'>Sub District</td><td>$chg_sub_district</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Sub District Changed<br>"; }
				}

				if ( !empty($chg_group_notes) && $meeting->group_notes != $chg_group_notes) {
					$message_lines[__('Group Notes', '12-step-meeting-list')] = "<tr><td style='color:red;'>Group Notes</td><td>$chg_group_notes</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Group Notes Changed<br>"; }
				}

				if ( !empty($chg_website) && $meeting->website != $chg_website) {
					$message_lines[__('Website', '12-step-meeting-list')] = "<tr><td style='color:red;'>Website</td><td>$chg_website</td></tr>";  
					if ($host === 'aatemplate-wp.dev.cc') { echo "Website Changed<br>"; }
					$IsChange = true;
				}

				if (!empty($meeting->website_2) && $meeting->website_2 != $chg_website_2) {
					$message_lines[__('Website 2', '12-step-meeting-list')] = "<tr><td style='color:red;'>Website 2</td><td>$chg_website_2</td></tr>";  
					$IsChange = true;
				}
			
				if ( ( !empty($chg_email) && ( strpos($chg_email, '.***') === false ) )  &&  $meeting->email != $chg_email ) {
					$message_lines[__('Email', '12-step-meeting-list')] = "<tr><td style='color:red;'>Email</td><td>$chg_email</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Group Email Changed:<br>1-->[$meeting->email]<br>2-->[$chg_email]<br>"; }
				}

				if ( ( !empty($chg_phone) && ( strpos($chg_phone, '***-') === false ) )  && $meeting->phone != $chg_phone ) {
					$message_lines[__('Phone', '12-step-meeting-list')] = "<tr><td style='color:red;'>Phone</td><td>$chg_phone</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Group Phone Changed:<br>1-->[$meeting->phone]<br>2-->[$chg_phone]<br>"; }
				}

				if ( ( !empty($chg_mailing_address)  && ( strpos($chg_mailing_address, ' ***') === false) )  &&  $meeting->mailing_address != $chg_mailing_address ) {
					$message_lines[__('Mailing Address', '12-step-meeting-list')] = "<tr><td style='color:red;'>Mailing Address</td><td>$chg_mailing_address</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Group Mailing Address Changed:<br>1-->[$meeting->mailing_address]<br>2-->[$chg_mailing_address]<br>"; }
				}

				if (!empty($chg_venmo) && $meeting->venmo != $chg_venmo) {
					$message_lines[__('Venmo', '12-step-meeting-list')] = "<tr><td style='color:red;'>Venmo</td><td >$chg_venmo</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Venmo Changed<br>"; }
				}

				if (!empty($chg_square) && $meeting->square != $chg_square) {
					$message_lines[__('Square', '12-step-meeting-list')] = "<tr><td style='color:red;'>Square</td><td>$chg_square</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Square Changed<br>"; }
				}

				if (!empty($chg_paypal) && $meeting->paypal != $chg_paypal) {
					$message_lines[__('Paypal', '12-step-meeting-list')] = "<tr><td style='color:red;'>Paypal</td><td>$chg_paypal</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "PayPal Changed<br>"; }
				}

				if ( ( !empty($chg_contact_1_name) && ( strpos($chg_contact_1_name, ' ******') === false ) )  && $meeting->contact_1_name != $chg_contact_1_name ) {
					$message_lines[__('Contact 1 Name', '12-step-meeting-list')] = "<tr><td style='color:red;'>Contact 1 Name</td><td>$chg_contact_1_name</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Contact 1 Name Changed<br>"; }
				}

				if ( ( !empty($chg_contact_1_email) && ( strpos($chg_contact_1_email, '.***') === false ) )  &&  $meeting->contact_1_email != $chg_contact_1_email ) {
					$message_lines[__('Contact 1 Email', '12-step-meeting-list')] = "<tr><td style='color:red;'>Contact 1 Email</td><td>$chg_contact_1_email</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Contact 1 Email Changed<br>"; }
				}

				if ( ( !empty($chg_contact_1_phone) && ( strpos($chg_contact_1_phone, '***-') === false ) )  && $meeting->contact_1_phone != $chg_contact_1_phone ) {
					$message_lines[__('Contact 1 Phone', '12-step-meeting-list')] = "<tr><td style='color:red;'>Contact 1 Phone'</td><td>$chg_contact_1_phone</td></tr>";  
					$IsChange = true;
					if ($host === 'aatemplate-wp.dev.cc') { echo "Contact 1 Phone Changed<br>"; }
				}

				if ( ( !empty($chg_contact_2_name) && ( strpos($chg_contact_2_name, ' ******') === false ) )  && $meeting->contact_2_name != $chg_contact_2_name ) {
					$message_lines[__('Contact 2 Name', '12-step-meeting-list')] = "<tr><td style='color:red;'>Contact 2 Name</td><td>$chg_contact_2_name</td></tr>";  
					$IsChange = true;
				}

				if ( ( !empty($chg_contact_2_email) && ( strpos($chg_contact_2_email, '.***') === false ) )  &&  $meeting->contact_2_email != $chg_contact_2_email ) {
					$message_lines[__('Contact 2 Email', '12-step-meeting-list')] = "<tr><td style='color:red;'>Contact 2 Email</td><td>$chg_contact_2_email</td></tr>";  
					$IsChange = true;
				}

				if ( ( !empty($chg_contact_2_phone) && ( strpos($chg_contact_2_phone, '***-') === false ) )  && $meeting->contact_2_phone != $chg_contact_2_phone ) {
					$message_lines[__('Contact 2 Phone', '12-step-meeting-list')] = "<tr><td style='color:red;'>Contact 2 Phone'</td><td >$chg_contact_2_phone</td></tr>";  
					$IsChange = true;
				}

				if ( ( !empty($chg_contact_3_name) && ( strpos($chg_contact_3_name, ' ******') === false ) )  && $meeting->contact_3_name != $chg_contact_3_name ) {
					$message_lines[__('Contact 3 Name', '12-step-meeting-list')] = "<tr><td<td style='color:red;'>Contact 3 Name</td><td>$chg_contact_3_name</td></tr>";  
					$IsChange = true;
				}

				if ( ( !empty($chg_contact_3_email) && ( strpos($chg_contact_3_email, '.***') === false ) )  &&  $meeting->contact_3_email != $chg_contact_3_email ) {
					$message_lines[__('Contact 3 Email', '12-step-meeting-list')] = "<tr><td style='color:red;'>Contact 3 Email</td><td>$chg_contact_3_email</td></tr>";  
					$IsChange = true;
				}

				if ( ( !empty($chg_contact_3_phone) && ( strpos($chg_contact_3_phone, '***-') === false ) )  && $meeting->contact_3_phone != $chg_contact_3_phone ) {
					$message_lines[__('Contact 3 Phone', '12-step-meeting-list')] = "<tr><td style='color:red;'>Contact 3 Phone'</td><td>$chg_contact_3_phone</td></tr>";  
					$IsChange = true;
				}


				if ($host === 'aatemplate-wp.dev.cc') { echo "<br>Change Processing finished...<br>"; }

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

			if ($host === 'aatemplate-wp.dev.cc') { echo "New Processing started...<br>"; }

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





