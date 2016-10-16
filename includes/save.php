<?php

//catch meetings without locations and save them as a draft
add_filter('wp_insert_post_data', 'tsml_insert_post_check', '99', 2);
function tsml_insert_post_check($post) {
	if (($post['post_type'] == 'tsml_meeting') && empty($post['post_parent']) && ($post['post_status'] == 'publish')) {
		$post['post_status'] = 'draft';
	}
	return $post;
}

//handle all the metadata, location
add_action('save_post', 'tsml_save_post', 10, 3);
function tsml_save_post($post_id, $post, $update) {
	global $tsml_nonce, $wpdb, $tsml_notification_addresses, $tsml_days;

	//security
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!current_user_can('edit_post', $post_id)) return;
	if (!isset($_POST['tsml_nonce']) || !wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) return;
	if (!isset($_POST['post_type']) || ($_POST['post_type'] != 'tsml_meeting')) return;
	
	//sanitize strings
	$strings = array('post_title', 'location', 'formatted_address', 'post_status', 'group', 'last_contact');
	foreach ($strings as $string) {
		$_POST[$string] = stripslashes(sanitize_text_field($_POST[$string]));
	}

	//sanitize textareas
	$strings = array('post_content', 'location_notes', 'group_notes');
	foreach ($strings as $string) {
		$_POST[$string] = stripslashes(sanitize_text_area($_POST[$string]));
	}

	//get current meeting state to compare against
	if ($update) {
		$old_meeting = tsml_get_meeting($post_id);
		$decode_keys = array('post_title', 'post_content', 'location', 'location_notes', 'group', 'group_notes');
		foreach ($decode_keys as $key) {
			$old_meeting->{$key} = html_entity_decode($old_meeting->{$key});
		}
	}
		
	//track changes to meeting
	$changes = array();
		
	if (!$update || strcmp($old_meeting->post_title, $_POST['post_title']) !== 0) {
		$changes[] = 'name';
	}
	
	if (!$update || strcmp($old_meeting->post_content, $_POST['post_content']) !== 0) {
		$changes[] = 'notes';
	}

	//check types for not-array-ness
	if (!is_array($_POST['types'])) $_POST['types'] = array(); //not sure if this actually happens
	
	//don't allow it to be both open and closed
	if (in_array('C', $_POST['types']) && in_array('O', $_POST['types'])) {
		$_POST['types'] = array_diff($_POST['types'], array('C'));
	}

	//compare types
	if (!$update || implode(', ', $old_meeting->types) != tsml_meeting_types($_POST['types'])) {
		$changes[] = 'types';
		if (empty($_POST['types'])) {
			delete_post_meta($post->ID, 'types');
		} else {
			update_post_meta($post->ID, 'types', array_map('esc_attr', $_POST['types']));
		}
	}

	//day could be null for appointment meeting
	if (in_array($_POST['day'], array('0', '1', '2', '3', '4', '5', '6'))) {
		if (!$update || !isset($old_meeting->day) || $old_meeting->day != intval($_POST['day'])) {
			$changes[] = 'day';
			update_post_meta($post->ID, 'day', intval($_POST['day']));
		}

		$_POST['time'] = tsml_sanitize_time($_POST['time']);
		if (!$update || strcmp($old_meeting->time, $_POST['time']) !== 0) {
			$changes[] = 'time';
			if (empty($_POST['time'])) {
				delete_post_meta($post->ID, 'time');
			} else {
				//$time_temp = $old_meeting->time;
				update_post_meta($post->ID, 'time', $_POST['time']);
				//if ($time_temp != $old_meeting->time) die('what the fuck');
			}
		}

		$_POST['end_time'] = tsml_sanitize_time($_POST['end_time']);
		if (!$update || $old_meeting->end_time != $_POST['end_time']) {
			$changes[] = 'end_time';
			if (empty($_POST['end_time'])) {
				delete_post_meta($post->ID, 'end_time');
			} else {
				update_post_meta($post->ID, 'end_time', $_POST['end_time']);
			}
		}
	} else {
		//appointment meeting
		if (!$update || !empty($old_meeting->day) || $old_meeting->day == '0') {
			$changes[] = 'day';
			delete_post_meta($post->ID, 'day');
		}
		if (!$update || !empty($old_meeting->time)) {
			$changes[] = 'time';
			delete_post_meta($post->ID, 'time');
		}
		if (!$update || !empty($old_meeting->end_time)) {
			$changes[] = 'end_time';
			delete_post_meta($post->ID, 'end_time');
		}
	}
	
	//exit here if the location is not ready
	if (empty($_POST['formatted_address']) || empty($_POST['latitude']) || empty($_POST['longitude'])) {
		return;
	}
	
	//save location information (set this value or get caught in a loop)
	$_POST['post_type'] = 'tsml_location';
	
	//see if address is already in the database
	if ($locations = get_posts(array(
		'post_type' => 'tsml_location',
		'numberposts' => 1,
		'orderby' => 'id',
		'order' => 'ASC',
		'meta_key' => 'formatted_address',
		'meta_value' => $_POST['formatted_address'],
	))) {
		$location_id = $locations[0]->ID;
		if ($locations[0]->post_title != $_POST['location'] || $locations[0]->post_content != $_POST['location_notes']) {
			if ($locations[0]->post_title != $_POST['location']) $changes[] = 'location';
			if ($locations[0]->post_content != $_POST['location_notes']) $changes[] = 'location_notes';
			wp_update_post(array(
				'ID'			=> $location_id,
				'post_title'	=> $_POST['location'],
				'post_content'  => $_POST['location_notes'],
			));
		}
	} else {
		$changes[] = 'location';
		$changes[] = 'location_notes';
		$location_id = wp_insert_post(array(
			'post_title'	=> $_POST['location'],
		  	'post_type'		=> 'tsml_location',
		  	'post_status'	=> 'publish',
			'post_content'  => $_POST['location_notes'],
		));
	}

	//update address & info on location
	if (!$update || html_entity_decode($old_meeting->formatted_address) != $_POST['formatted_address']) {
		$changes[] = 'formatted_address';
		update_post_meta($location_id, 'formatted_address', $_POST['formatted_address']);
	}

	foreach (array('latitude', 'longitude') as $field) {
		if (!$update || $old_meeting->{$field} != $_POST[$field]) {
			$changes[] = $field;
			update_post_meta($location_id, $field, floatval($_POST[$field]));
		}
	}

	//update region
	if (!$update || $old_meeting->region_id != $_POST['region']) {
		$changes[] = 'region';
		wp_set_object_terms($location_id, intval($_POST['region']), 'tsml_region');
	}
	
	//set parent on this post (and post status?) without re-triggering the save_posts hook
	if (($old_meeting->post_parent != $location_id) || ($old_meeting->post_status != $_POST['post_status'])) {
		$wpdb->get_var($wpdb->prepare('UPDATE ' . $wpdb->posts . ' SET post_parent = %d, post_status = %s WHERE ID = %d', $location_id, $_POST['post_status'], $post->ID));
	}

	//save group information (set this value or get caught in a loop)
	$_POST['post_type'] = 'tsml_group';

	if (empty($_POST['group'])) {
		if (!empty($old_meeting->group)) {
			$changes[] = 'group';
			if (!empty($old_meeting->group_notes)) $changes[] = 'group_notes';
			delete_post_meta($post->ID, 'group_id');
			if (!empty($_POST['apply_group_to_location'])) {
				foreach ($meetings as $meeting) delete_post_meta($meeting['id'], 'group_id');
				//todo other meetings affected by this change
			}
		}
	} else {
		if ($groups = $wpdb->get_results($wpdb->prepare('SELECT ID, post_title, post_content FROM ' . $wpdb->posts . ' WHERE post_type = "tsml_group" AND post_title = "%s" ORDER BY id', stripslashes($_POST['group'])))) {
			$group_id = $groups[0]->ID;
			if ($groups[0]->post_title != $_POST['group'] || $groups[0]->post_content != $_POST['group_notes']) {
				if (!$update || $old_meeting->group != $_POST['group']) $changes[] = 'group';
				if (!$update || $old_meeting->group_notes != $_POST['group_notes']) $changes[] = 'group_notes';
				wp_update_post(array(
					'ID'			=> $group_id,
					'post_title'	=> $_POST['group'],
					'post_content'  => $_POST['group_notes'],
				));
			}
		} else {
			$changes[] = 'group';
			$changes[] = 'group_notes';
			$group_id = wp_insert_post(array(
			  	'post_type'		=> 'tsml_group',
			  	'post_status'	=> 'publish',
				'post_title'	=> $_POST['group'],
				'post_content'  => $_POST['group_notes'],
			));
		}
	
		//save to meetings(s)
		if ($old_meeting->group_id != $group_id) {
			if (empty($_POST['apply_group_to_location'])) {
				update_post_meta($post->ID, 'group_id', $group_id);
			} else {
				foreach ($meetings as $meeting) update_post_meta($meeting['id'], 'group_id', $group_id); 	
				//todo other meetings affected by this change
			}
		}

		//contact info
		for ($i = 1; $i <= GROUP_CONTACT_COUNT; $i++) {
			foreach (array('name', 'email', 'phone') as $field) {
				$key = 'contact_' . $i . '_' . $field;
				$_POST[$key] = sanitize_text_field($_POST[$key]);
				if (!$update || $old_meeting->{$key} != $_POST[$key]) {
					$changes[] = $key;
					if (empty($_POST[$key])) {
						delete_post_meta($group_id, $key); 
					} else {
						update_post_meta($group_id, $key, $_POST[$key]);
					}
				}
			}
		}
		
		//last contact
		if (!empty($_POST['last_contact'])) $_POST['last_contact'] = date('Y-m-d', strtotime($_POST['last_contact']));
		if (!$update || $old_meeting->last_contact != $_POST['last_contact']) {
			$changes[] = 'last_contact';
			if (!empty($_POST['last_contact'])) {
				update_post_meta($group_id, 'last_contact', $_POST['last_contact']);
			} else {
				delete_post_meta($group_id, 'last_contact');
			}
		}
	}

	//deleted orphaned locations and groups
	tsml_delete_orphans();
	
	//update types in use
	tsml_update_types_in_use();
	
	//send out email notifications
	//if (wp_is_post_revision($post_id)) return;

	//remove self
	$user = wp_get_current_user();
	$tsml_notification_addresses = array_diff($tsml_notification_addresses, array($user->user_email));
	
	//don't notify for lat / lon changes
	$changes = array_diff($changes, array('latitude', 'longitude'));

	if (count($tsml_notification_addresses) && count($changes)) {
		$email =' <p style="font:14px arial;margin:15px 0;">';
		if ($update) {
			$email .= sprintf(__('This is to notify you that %s updated a <a style="color:#6699cc" href="%s">meeting</a> on the %s site.', '12-step-meeting-list'), $user->display_name, get_permalink($post->ID), get_bloginfo('name'));
		} else {
			$email .= sprintf(__('This is to notify you that %s created a <a style="color:#6699cc" href="%s">new meeting</a> on the %s site.', '12-step-meeting-list'), $user->display_name, get_permalink($post->ID), get_bloginfo('name'));
		}
		$email .= '</p><table style="font:14px arial;width:100%;border-collapse:collapse;padding:0;">';
		$fields = array('name', 'day', 'time', 'end_time', 'types', 'notes', 'location', 'formatted_address', 'region', 'location_notes', 'group', 'group_notes', 'contact_1_name', 'contact_1_email', 'contact_1_phone', 'contact_2_name', 'contact_2_email', 'contact_2_phone', 'contact_3_name', 'contact_3_email', 'contact_3_phone', 'last_contact');
		foreach ($fields as $field) {
			
			if ($field == 'types') {
				if ($update) $old = implode(', ', $old_meeting->types);
				$new = tsml_meeting_types($_POST['types']);
			} elseif ($field == 'name') {
				if ($update) $old = $old_meeting->post_title;
				$new = $_POST['post_title'];
			} elseif ($field == 'notes') {
				if ($update) $old = $old_meeting->post_content;
				$new = $_POST['post_content'];
			} elseif ($field == 'day') {
				if ($update) $old = empty($old_meeting->day) ? __('Appointment', '12-step-meeting-list') : $tsml_days[$old_meeting->day];
				$new = empty($_POST['day']) ? __('Appointment', '12-step-meeting-list') : $tsml_days[$_POST['day']];
			} elseif ($field == 'time') {
				if ($update) $old = empty($old_meeting->time) ? '' : tsml_format_time($old_meeting->time, '');
				$new = empty($_POST['time']) ? '' : tsml_format_time($_POST['time'], '');
			} elseif ($field == 'end_time') {
				if ($update) $old = empty($old_meeting->end_time) ? '' : tsml_format_time($old_meeting->end_time, '');
				$new = empty($_POST['end_time']) ? '' : tsml_format_time($_POST['end_time'], '');
			} else {
				if ($update) $old = $old_meeting->{$field};
				$new = $_POST[$field];
			}
			
			$field_name = __(ucwords(str_replace('_', ' ', $field)), '12-step-meeting-list');
			
			if (in_array($field, $changes)) {
				$email .= '<tr style="border:1px solid #999;background-color:#fff;"><td style="width:150px;padding:5px">' . $field_name . '</td><td style="padding:5px">';
				if (!empty($old)) $email .= '<strike style="color:#999">' . $old . '</strike> ';
				$email .= $new . '</td></tr>';
			} elseif (!empty($old)) {
				$email .= '<tr style="border:1px solid #999;background-color:#eee;"><td style="width:150px;padding:5px">' . $field_name . '</td><td style="padding:5px">' . $old . '</td></tr>';
			}
		}
		$email .= '</table>';
		$subject = $update ? __('Meeting Change Notification', '12-step-meeting-list') : __('New Meeting Notification', '12-step-meeting-list');
		wp_mail($tsml_notification_addresses, $subject, $email, array('Content-Type: text/html; charset=UTF-8'));
	} 

}