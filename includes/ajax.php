<?php
//ajax functions

//clear google address cache, only necessary if parsing logic changes
//utility function, run manually
add_action('wp_ajax_tsml_cache', 'tsml_clear_address_cache');
function tsml_clear_address_cache() {
	delete_option('tsml_addresses');
	die('address cache cleared!');	
}

//function: receives AJAX from single-meetings.php, sends email
add_action('wp_ajax_tsml_feedback', 'tsml_feedback');
add_action('wp_ajax_nopriv_tsml_feedback', 'tsml_feedback');
function tsml_feedback() {
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
		echo 'Error: nonce value not set correctly. Email was not sent.';
	} elseif (empty($tsml_feedback_addresses) || empty($name) || !is_email($email) || empty($message)) {
		echo 'Error: required form value missing. Email was not sent.';
	} else {
		//send HTML email
		add_filter('wp_mail_content_type', 'tsml_email_content_type_html');
		if (wp_mail($tsml_feedback_addresses, $subject, $message, $headers)) {
			echo 'Thank you for your feedback.';
		} else {
			global $phpmailer;
			if (!empty($phpmailer->ErrorInfo)) {
				echo 'Error: ' . $phpmailer->ErrorInfo;
			} else {
				echo 'An error occurred while sending email!';
			}
		}
		remove_filter('wp_mail_content_type', 'tsml_email_content_type_html');
	}
	
	exit;
}

//api ajax function
//used by theme, web app, mobile app
add_action('wp_ajax_meetings', 'tsml_meetings_api');
add_action('wp_ajax_nopriv_meetings', 'tsml_meetings_api');
function tsml_meetings_api() {
	if (!headers_sent()) header('Access-Control-Allow-Origin: *');
	if (empty($_POST)) wp_send_json(tsml_get_meetings($_GET));
	wp_send_json(tsml_get_meetings($_POST));
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
		'time' =>				__('Time'),
		'end_time' =>			__('End Time'),
		'day' =>				__('Day'),
		'name' =>				__('Name'),
		'location' =>			__('Location'),
		'formatted_address' =>	__('Address'),
		'region' =>				__('Region'),
		'sub_region' =>			__('Sub Region'),
		'types' =>				__('Types'),
		'notes' =>				__('Notes'),
		'location_notes' =>		__('Location Notes'),
		'group' => 				__('Group'),
		'group_notes' => 		__('Group Notes'),
		'updated' =>			__('Updated'),
	);
	
	//append contact info if user has permission
	if (current_user_can('edit_posts')) {
		$columns = array_merge($columns, array(
			'contact_1_name' =>		__('Contact 1 Name'),
			'contact_1_email' =>	__('Contact 1 Email'),
			'contact_1_phone' =>	__('Contact 1 Phone'),
			'contact_2_name' =>		__('Contact 2 Name'),
			'contact_2_email' =>	__('Contact 2 Email'),
			'contact_2_phone' =>	__('Contact 2 Phone'),
			'contact_3_name' =>		__('Contact 3 Name'),
			'contact_3_email' =>	__('Contact 3 Email'),
			'contact_3_phone' =>	__('Contact 3 Phone'),
			'last_contact' => 		__('Last Contact'),
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

//get all contact email addresses (for europe)
//linked from admin_import.php
add_action('wp_ajax_contacts', 'tsml_regions_contacts');
function tsml_regions_contacts() {
	global $wpdb;
	$group_ids = $wpdb->get_col('SELECT id FROM ' . $wpdb->posts . ' WHERE post_type = "' . 'tsml_group' . '"');
	$emails = $wpdb->get_col('SELECT meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_key IN ("contact_1_email", "contact_2_email", "contact_3_email") AND post_id IN (' . implode(',', $group_ids) . ')');
	$emails = array_unique(array_filter($emails));
	sort($emails);
	die(implode(',', $emails));
}
