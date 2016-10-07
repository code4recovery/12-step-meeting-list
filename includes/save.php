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
add_action('save_post', 'tsml_save_post');
function tsml_save_post(){
	global $post, $tsml_nonce, $wpdb, $tsml_notification_addresses, $tsml_days;

	//security
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!isset($post->ID) || !current_user_can('edit_post', $post->ID)) return;
	if (!isset($_POST['tsml_nonce']) || !wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) return;
	if (!isset($_POST['post_type']) || ($_POST['post_type'] != 'tsml_meeting')) return;
	
	//get current meeting state to compare against
	$old_meeting = tsml_get_meeting($post->ID);
	
	//track changes to meeting
	$changes = array();
	
	if ($old_meeting->post_title != sanitize_text_field($_POST['post_title'])) $changes[] = 'name';
	if ($old_meeting->post_content != sanitize_text_area($_POST['post_content'])) $changes[] = 'notes';

	//check types for errors
	if (!is_array($_POST['types'])) $_POST['types'] = array(); //not sure if this actually happens
	if (in_array('C', $_POST['types']) && in_array('O', $_POST['types'])) {
		$_POST['types'] = array_diff($_POST['types'], array('C'));
	}

	//compare types
	if (implode(', ', $old_meeting->types) != tsml_meeting_types($_POST['types'])) {
		$changes[] = 'types';
		if (empty($_POST['types'])) {
			delete_post_meta($post->ID, 'types');
		} else {
			update_post_meta($post->ID, 'types', array_map('esc_attr', $_POST['types']));
		}
	}

	//day could be null for appointment meeting
	if (in_array($_POST['day'], array('0', '1', '2', '3', '4', '5', '6', '7'))) {
		if (!isset($old_meeting->day) || $old_meeting->day != intval($_POST['day'])) {
			$changes[] = 'day';
			update_post_meta($post->ID, 'day', intval($_POST['day']));
		}

		$_POST['time'] = tsml_sanitize_time($_POST['time']);
		if (strcmp($old_meeting->time, $_POST['time']) !== 0) {
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
		if ($old_meeting->end_time != $_POST['end_time']) {
			$changes[] = 'end_time';
			if (empty($_POST['end_time'])) {
				delete_post_meta($post->ID, 'end_time');
			} else {
				update_post_meta($post->ID, 'end_time', $_POST['end_time']);
			}
		}
	} else {
		//appointment meeting
		if (!empty($old_meeting->day) || $old_meeting->day == '0') {
			$changes[] = 'day';
			delete_post_meta($post->ID, 'day');
		}
		if (!empty($old_meeting->time)) {
			$changes[] = 'time';
			delete_post_meta($post->ID, 'time');
		}
		if (!empty($old_meeting->end_time)) {
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
	$_POST['location'] = sanitize_text_field($_POST['location']);
	$_POST['location_notes'] = sanitize_text_area($_POST['location_notes']);
	
	//see if address is already in the database
	if ($locations = get_posts(array(
		'post_type' => 'tsml_location',
		'numberposts' => 1,
		'orderby' => 'id',
		'order' => 'ASC',
		'meta_key' => 'formatted_address',
		'meta_value' => sanitize_text_field($_POST['formatted_address']),
	))) {
		$location_id = $locations[0]->ID;
		if ($locations[0]->post_title != $_POST['location'] || $locations[0]->post_content != $_POST['location_notes']) {
			$changes[] = 'updating location';
			wp_update_post(array(
				'ID'			=> $location_id,
				'post_title'	=> $_POST['location'],
				'post_content'  => $_POST['location_notes'],
			));
		}
	} else {
		$changes[] = 'creating new location';
		$location_id = wp_insert_post(array(
			'post_title'	=> $_POST['location'],
		  	'post_type'		=> 'tsml_location',
		  	'post_status'	=> 'publish',
			'post_content'  => $_POST['location_notes'],
		));
	}

	//update address & info on location
	$_POST['formatted_address'] = sanitize_text_field($_POST['formatted_address']);
	if ($old_meeting->formatted_address != $_POST['formatted_address']) {
		$changes[] = $field;
		update_post_meta($location_id, 'formatted_address', $_POST['formatted_address']);
	}

	foreach (array('latitude', 'longitude') as $field) {
		if ($old_meeting->{$field} != $_POST[$field]) {
			$changes[] = $field;
			update_post_meta($location_id, $field, floatval($_POST[$field]));
		}
	}

	//update region
	if ($old_meeting->region != $_POST['region']) {
		$changes[] = 'region';
		wp_set_object_terms($location_id, intval($_POST['region']), 'tsml_region');
	}
	
	//set parent on this post (and post status?) without re-triggering the save_posts hook
	if (($old_meeting->post_parent != $location_id) || ($old_meeting->post_status != $_POST['post_status'])) {
		$changes[] = 'post_parent and/or post_status';
		$wpdb->get_var($wpdb->prepare('UPDATE ' . $wpdb->posts . ' SET post_parent = %d, post_status = %s WHERE ID = %d', $location_id, sanitize_text_field($_POST['post_status']), $post->ID));
	}

	//deleted orphaned locations
	tsml_delete_orphaned_locations();
	
	//save group information (set this value or get caught in a loop)
	$_POST['post_type'] = 'tsml_group';
	$_POST['group'] = sanitize_text_field($_POST['group']);
	$_POST['group_notes'] = sanitize_text_area($_POST['group_notes']);

	if (empty($_POST['group'])) {
		if (!empty($old_meeting->group)) {
			$changes[] = 'removing group';
			delete_post_meta($post->ID, 'group_id');
			if (!empty($_POST['apply_group_to_location'])) {
				foreach ($meetings as $meeting) delete_post_meta($meeting['id'], 'group_id'); 	
			}
		}
	} else {
		if ($groups = $wpdb->get_results($wpdb->prepare('SELECT ID, post_title, post_content FROM ' . $wpdb->posts . ' WHERE post_type = "tsml_group" AND post_title = "%s" ORDER BY id', stripslashes($_POST['group'])))) {
			$group_id = $groups[0]->ID;
			if ($groups[0]->post_title != $_POST['group'] || $groups[0]->post_content != $_POST['group_notes']) {
				$changes[] = 'updating group';
				wp_update_post(array(
					'ID'			=> $group_id,
					'post_title'	=> $_POST['group'],
					'post_content'  => $_POST['group_notes'],
				));
			}
		} else {
			$changes[] = 'creating group';
			$group_id = wp_insert_post(array(
			  	'post_type'		=> 'tsml_group',
			  	'post_status'	=> 'publish',
				'post_title'	=> $_POST['group'],
				'post_content'  => $_POST['group_notes'],
			));
		}
	
		//save to meetings(s)
		if ($old_meeting->group_id != $group_id) {
			$changes[] = 'group_id';
			if (empty($_POST['apply_group_to_location'])) {
				update_post_meta($post->ID, 'group_id', $group_id);
			} else {
				foreach ($meetings as $meeting) update_post_meta($meeting['id'], 'group_id', $group_id); 	
			}
		}

		//contact info
		for ($i = 1; $i <= GROUP_CONTACT_COUNT; $i++) {
			foreach (array('name', 'email', 'phone') as $field) {
				$key = 'contact_' . $i . '_' . $field;
				$_POST[$key] = sanitize_text_field($_POST[$key]);
				if ($old_meeting->{$key} != $_POST[$key]) {
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
		if (!empty($_POST['last_contact'])) $_POST['last_contact'] = date('Y-m-d', strtotime(sanitize_text_field($_POST['last_contact'])));
		if ($old_meeting->last_contact != $_POST['last_contact']) {
			$changes[] = 'last_contact';
			if (!empty($_POST['last_contact'])) {
				update_post_meta($group_id, 'last_contact', $_POST['last_contact']);
			} else {
				delete_post_meta($group_id, 'last_contact');
			}
		}
	}

	//delete orphaned groups
	if ($groups_in_use = $wpdb->get_col('SELECT meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_key = "group_id"')) {
		$orphans = get_posts('post_type=tsml_group&numberposts=-1&exclude=' . implode(',', $groups_in_use));
		foreach ($orphans as $orphan) wp_delete_post($orphan->ID);
	}
	
	//update types in use
	tsml_update_types_in_use();
	
	/* upcoming feature: send out email notifications
	$user = wp_get_current_user();
	$changes = array_diff($changes, array('latitude', 'longitude')); //don't notify for lat / lon changes
	//$tsml_notification_addresses = array_diff($tsml_notification_addresses, array($user->user_email));
	if (count($tsml_notification_addresses) && count($changes)) {
		$email = '<p style="font:14px arial;margin:15px 0;">This is to notify you that ' . $user->display_name . ' updated a <a style="color:#6699cc" href="' . get_permalink($post->ID) . '">meeting</a> on the ' . get_bloginfo('name') . ' site.';
		if (count($changes) == 1) {
			$email .= ' There was one change.';
		} elseif (count($changes) < 10) {
			$numbers = array('one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine');
			$email .= ' There were ' . $numbers[count($changes) - 1] . ' changes.';
			//$email .= ' (' . implode(', ', $changes) . ')';
		} else {
			$email .= ' There were ' . count($changes) . ' changes.';
		}
		$email .= '</p><table style="font:14px arial;width:100%;border-collapse:collapse;padding:0;">';
		$fields = array('name', 'day', 'time', 'end_time', 'types', 'notes', 'location', 'formatted_address', 'region', 'location_notes', 'group', 'group_notes', 'contact_1_name', 'contact_1_email', 'contact_1_phone', 'contact_2_name', 'contact_2_email', 'contact_2_phone', 'contact_3_name', 'contact_3_email', 'contact_3_phone');
		foreach ($fields as $field) {
			
			if ($field == 'types') {
				$old = implode(', ', $old_meeting->types);
				$new = tsml_meeting_types($_POST['types']);
			} elseif ($field == 'name') {
				$old = $old_meeting->post_title;
				$new = $_POST['post_title'];
			} elseif ($field == 'notes') {
				$old = $old_meeting->post_content;
				$new = $_POST['post_content'];
			} elseif ($field == 'day') {
				$old = empty($old_meeting->day) ? __('Appointment') : $tsml_days[$old_meeting->day];
				$new = empty($_POST['day']) ? __('Appointment') : $tsml_days[$_POST['day']];
			} elseif ($field == 'time') {
				$old = empty($old_meeting->time) ? '' : tsml_format_time($old_meeting->time, '');
				$new = empty($_POST['time']) ? '' : tsml_format_time($_POST['time'], '');
			} elseif ($field == 'end_time') {
				$old = empty($old_meeting->end_time) ? '' : tsml_format_time($old_meeting->end_time, '');
				$new = empty($_POST['end_time']) ? '' : tsml_format_time($_POST['end_time'], '');
			} else {
				$old = $old_meeting->{$field};
				$new = $_POST[$field];
			}
			
			$field_name = ucwords(str_replace('_', ' ', $field));
			
			if (in_array($field, $changes)) {
				$email .= '<tr style="border:1px solid #999;background-color:#fff;"><td style="width:150px;padding:5px">' . $field_name . '</td><td style="padding:5px"><strike style="color:#999">' . $old . '</strike> ' . $new . '</td></tr>';
			} elseif (!empty($old)) {
				$email .= '<tr style="border:1px solid #999;background-color:#eee;"><td style="width:150px;padding:5px">' . $field_name . '</td><td style="padding:5px">' . $old . '</td></tr>';
			}
		}
		$email .= '</table>';
		die($email);
	} 
	*/
}