<?php

//catch meetings without locations and save them as a draft, also format text
add_filter('wp_insert_post_data', 'tsml_insert_post_check', '99', 2);
function tsml_insert_post_check($post) {
	
	//sanitize text (remove html, trim)
	if ($post['post_type'] == 'tsml_meeting') {
		$post['post_content'] = sanitize_text_area($post['post_content']);
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
	if (wp_is_post_revision($post_id)) return;
	if (!isset($_POST['tsml_nonce']) || !wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) return;
	if (!isset($_POST['post_type']) || ($_POST['post_type'] != 'tsml_meeting')) return;
	
	//update is always 1, probably because it's actually 'created' when the edit screen first loads (due to autosave)
	$update = ($post->post_date !== $post->post_modified);
	
	//sanitize strings
	$strings = array('post_title', 'location', 'formatted_address', 'post_status', 'group', 'last_contact');
	foreach ($strings as $string) {
		$_POST[$string] = stripslashes(sanitize_text_field($_POST[$string]));
	}

	//sanitize textareas
	$textareas = array('post_content', 'location_notes', 'group_notes');
	foreach ($textareas as $textarea) {
		$_POST[$textarea] = stripslashes(sanitize_text_area($_POST[$textarea]));
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
	
	if (!$update || strcmp(tsml_paragraphs($old_meeting->post_content), tsml_paragraphs($_POST['post_content'])) !== 0) {
		$changes[] = 'notes';
	}

	//check types for not-array-ness
	if (empty($_POST['types']) || !is_array($_POST['types'])) $_POST['types'] = array(); //not sure if this actually happens
	
	//don't allow it to be both open and closed
	if (in_array('C', $_POST['types']) && in_array('O', $_POST['types'])) {
		$_POST['types'] = array_values(array_diff($_POST['types'], array('C')));
	}

	//don't allow it to be both men and women
	if (in_array('M', $_POST['types']) && in_array('W', $_POST['types'])) {
		$_POST['types'] = array_values(array_diff($_POST['types'], array('W')));
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
	if (empty($_POST['formatted_address'])) {

		$location_id = null;

	} else {
		
		//save location information (set this value or get caught in a loop)
		$_POST['post_type'] = 'tsml_location';

		//location name changed?
		if (!$update || $old_meeting->location != $_POST['location']) $changes[] = 'location';
		if (!$update || $old_meeting->location_notes != $_POST['location_notes']) $changes[] = 'location_notes';
		
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
				wp_update_post(array(
					'ID'			=> $location_id,
					'post_title'	=> $_POST['location'],
					'post_content'  => $_POST['location_notes'],
				));
			}
	
			//latitude longitude only if updated
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
		} elseif (!empty($_POST['formatted_address'])) {
			$location_id = wp_insert_post(array(
				'post_title'	=> $_POST['location'],
			  	'post_type'		=> 'tsml_location',
			  	'post_status'	=> 'publish',
				'post_content'  => $_POST['location_notes'],
			));
			
			//set latitude, longitude and region
			add_post_meta($location_id, 'latitude', floatval($_POST['latitude']));
			add_post_meta($location_id, 'longitude', floatval($_POST['longitude']));
			wp_set_object_terms($location_id, intval($_POST['region']), 'tsml_region');
		}
	
		//update address & info on location
		if ($location_id && (!$update || html_entity_decode($old_meeting->formatted_address) != $_POST['formatted_address'])) {
			$changes[] = 'formatted_address';
			update_post_meta($location_id, 'formatted_address', $_POST['formatted_address']);
		}
	}	

	//set parent on this post (or all meetings at location) without re-triggering the save_posts hook (update 7/25/17: removing post_status from this)
	if (!$update || ($old_meeting->post_parent != $location_id)) {
		if (empty($_POST['apply_address_to_location'])) {
			$wpdb->query($wpdb->prepare('UPDATE ' . $wpdb->posts . ' SET post_parent = %d WHERE ID = %d', $location_id, $post->ID));
		} else {
			foreach ($old_meeting->location_meetings as $meeting) {
				$wpdb->query($wpdb->prepare('UPDATE ' . $wpdb->posts . ' SET post_parent = %d WHERE ID = %d', $location_id, $meeting['id']));
			}
		}
	}

	//location-less meetings should all be drafts
	$wpdb->query('UPDATE ' . $wpdb->posts . ' SET post_status = "draft" WHERE post_type = "tsml_meeting" AND post_status = "publish" AND post_parent = 0');
	
	//save group information (set this value or get caught in a loop)
	$_POST['post_type'] = 'tsml_group';

	if (empty($_POST['group'])) {
		//adding contact information to individual meeting
		//meeting website
		if (!$update || strcmp($old_meeting->website, $_POST['website']) !== 0) {
			$changes[] = 'website';
			if (empty($_POST['website'])) {
				delete_post_meta($post->ID, 'website');
			} else {
				update_post_meta($post->ID, 'website', esc_url_raw($_POST['website'], array('http', 'https')));
			}
		}
		
		//meeting website 2
		if (!$update || strcmp($old_meeting->website_2, $_POST['website_2']) !== 0) {
			$changes[] = 'website_2';
			if (empty($_POST['website_2'])) {
				delete_post_meta($post->ID, 'website_2');
			} else {
				update_post_meta($post->ID, 'website_2', esc_url_raw($_POST['website_2'], array('http', 'https')));
			}
		}
		
		//meeting email
		if (!$update || strcmp($old_meeting->email, $_POST['email']) !== 0) {
			$changes[] = 'email';
			if (empty($_POST['email'])) {
				delete_post_meta($post->ID, 'email');
			} else {
				update_post_meta($post->ID, 'email', sanitize_text_field($_POST['email']));
			}
		}
		
		//meeting phone
		if (!$update || strcmp($old_meeting->phone, $_POST['phone']) !== 0) {
			$changes[] = 'phone';
			if (empty($_POST['phone'])) {
				delete_post_meta($post->ID, 'phone');
			} else {
				update_post_meta($post->ID, 'phone', sanitize_text_field($_POST['phone']));
			}
		}

		//meeting info
		for ($i = 1; $i <= GROUP_CONTACT_COUNT; $i++) {
			foreach (array('name', 'email', 'phone') as $field) {
				$key = 'contact_' . $i . '_' . $field;
				$_POST[$key] = sanitize_text_field($_POST[$key]);
				if (!$update || strcmp($old_meeting->{$key}, $_POST[$key]) !== 0) {
					$changes[] = $key;
					if (empty($_POST[$key])) {
						delete_post_meta($post->ID, $key); 
					} else {
						update_post_meta($post->ID, $key, $_POST[$key]);
					}
				}
			}
		}
		
		//last contact
		if (!$update || strcmp($old_meeting->last_contact, date('Y-m-d', strtotime($_POST['last_contact']))) !== 0) {
			$changes[] = 'last_contact';
			if (empty($_POST['last_contact'])) {
				delete_post_meta($post->ID, 'last_contact');
			} else {
				update_post_meta($post->ID, 'last_contact', date('Y-m-d', strtotime($_POST['last_contact'])));
			}
		}
		
		//switching from group to no group
		if (!empty($old_meeting->group)) {
			$changes[] = 'group';
			if (!empty($old_meeting->group_notes)) $changes[] = 'group_notes';
			delete_post_meta($post->ID, 'group_id');
			if (!empty($_POST['apply_group_to_location'])) {
				foreach ($old_meeting->location_meetings as $meeting) delete_post_meta($meeting['id'], 'group_id');
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
			//update region
			if (!empty($_POST['district'])) {
				if (!$update || $old_meeting->district_id != $_POST['district']) {
					$changes[] = 'district';
					wp_set_object_terms($group_id, intval($_POST['district']), 'tsml_district');
				}
			}
		} else {
			$changes[] = 'group';
			if (!empty($_POST['group_notes'])) $changes[] = 'group_notes';
			$group_id = wp_insert_post(array(
			  	'post_type'		=> 'tsml_group',
			  	'post_status'	=> 'publish',
				'post_title'	=> $_POST['group'],
				'post_content'  => $_POST['group_notes'],
			));
			if (!empty($_POST['district'])) {
				$changes[] = 'district';
				wp_set_object_terms($group_id, intval($_POST['district']), 'tsml_district');
			}
		}
	
		//save to meetings(s)
		if ($old_meeting->group_id != $group_id) {
			if (empty($_POST['apply_group_to_location'])) {
				update_post_meta($post->ID, 'group_id', $group_id);
			} else {
				foreach ($old_meeting->location_meetings as $meeting) update_post_meta($meeting['id'], 'group_id', $group_id); 	
			}
		}
		
		//group website
		if (!$update || strcmp($old_meeting->website, $_POST['website']) !== 0) {
			$changes[] = 'website';
			if (empty($_POST['website'])) {
				delete_post_meta($group_id, 'website');
			} else {
				update_post_meta($group_id, 'website', esc_url_raw($_POST['website'], array('http', 'https')));
			}
		}

		//group website 2
		if (!$update || strcmp($old_meeting->website_2, $_POST['website_2']) !== 0) {
			$changes[] = 'website_2';
			if (empty($_POST['website_2'])) {
				delete_post_meta($group_id, 'website_2');
			} else {
				update_post_meta($group_id, 'website_2', esc_url_raw($_POST['website_2'], array('http', 'https')));
			}
		}
		
		//group email
		if (!$update || strcmp($old_meeting->email, $_POST['email']) !== 0) {
			$changes[] = 'email';
			if (empty($_POST['email'])) {
				delete_post_meta($group_id, 'email');
			} else {
				update_post_meta($group_id, 'email', sanitize_text_field($_POST['email']));
			}
		}
		
		//group phone
		if (!$update || strcmp($old_meeting->phone, $_POST['phone']) !== 0) {
			$changes[] = 'phone';
			if (empty($_POST['phone'])) {
				delete_post_meta($group_id, 'phone');
			} else {
				update_post_meta($group_id, 'phone', sanitize_text_field($_POST['phone']));
			}
		}
		
		//group mailing address
		if (!$update || strcmp($old_meeting->mailing_address, $_POST['mailing_address']) !== 0) {
			$changes[] = 'mailing_address';
			if (empty($_POST['mailing_address'])) {
				delete_post_meta($group_id, 'mailing_address');
			} else {
				update_post_meta($group_id, 'mailing_address', sanitize_text_field($_POST['mailing_address']));
			}
		}
		
		//group venmo
		if (!$update || strcmp($old_meeting->venmo, $_POST['venmo']) !== 0) {
			$changes[] = 'venmo';
			if (empty($_POST['venmo']) || (substr($_POST['venmo'], 0, 1) != '@')) {
				delete_post_meta($group_id, 'venmo');
			} else {
				update_post_meta($group_id, 'venmo', sanitize_text_field($_POST['venmo']));
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
		if (!empty($_POST['last_contact'])) {
			update_post_meta($group_id, 'last_contact', date('Y-m-d', strtotime($_POST['last_contact'])));
		} else {
			delete_post_meta($group_id, 'last_contact');
		}	
	}

	//deleted orphaned locations and groups
	tsml_delete_orphans();
	
	//update types in use
	tsml_update_types_in_use();

	//update bounds for geocoding
	tsml_bounds();

	//try to rebuild cache
	tsml_cache_rebuild();
	
	//remove self
	$user = wp_get_current_user();
	$tsml_notification_addresses = array_diff($tsml_notification_addresses, array($user->user_email));
	
	//don't notify for lat / lon changes
	$changes = array_diff($changes, array('latitude', 'longitude'));

	if (count($tsml_notification_addresses) && count($changes)) {
		$message =' <p>';
		if ($update) {
			$message .= sprintf(__('This is to notify you that %s updated a <a href="%s">meeting</a> on the %s site.', '12-step-meeting-list'), $user->display_name, get_permalink($post->ID), get_bloginfo('name'));
		} else {
			$message .= sprintf(__('This is to notify you that %s created a <a href="%s">new meeting</a> on the %s site.', '12-step-meeting-list'), $user->display_name, get_permalink($post->ID), get_bloginfo('name'));
		}
		$message .= '</p><table style="font:14px arial;width:100%;border-collapse:collapse;padding:0;">';
		$fields = array('name', 'day', 'time', 'end_time', 'types', 'notes', 'location', 
			'formatted_address', 'region', 'location_notes', 'group', 'district', 'group_notes', 
			'website', 'website_2', 'email', 'phone', 'mailing_address', 'venmo',
			'contact_1_name', 'contact_1_email', 'contact_1_phone', 
			'contact_2_name', 'contact_2_email', 'contact_2_phone', 
			'contact_3_name', 'contact_3_email', 'contact_3_phone', 'last_contact');
		foreach ($fields as $field) {
			$new = $old = '';
			
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
				if ($update) $old = in_array($old_meeting->day, array('0', '1', '2', '3', '4', '5', '6')) ? $tsml_days[$old_meeting->day] : __('Appointment', '12-step-meeting-list');
				$new = in_array($_POST['day'], array('0', '1', '2', '3', '4', '5', '6')) ? $tsml_days[$_POST['day']] : __('Appointment', '12-step-meeting-list');
			} elseif ($field == 'time') {
				if ($update) $old = empty($old_meeting->time) ? '' : tsml_format_time($old_meeting->time, '');
				$new = empty($_POST['time']) ? '' : tsml_format_time($_POST['time'], '');
			} elseif ($field == 'end_time') {
				if ($update) $old = empty($old_meeting->end_time) ? '' : tsml_format_time($old_meeting->end_time, '');
				$new = empty($_POST['end_time']) ? '' : tsml_format_time($_POST['end_time'], '');
			} elseif ($field == 'region') {
				if ($term = get_term($_POST['region'], 'tsml_region')) {
					$new = $term->name;
				}
				if ($update && !empty($old_meeting->region)) $old = $old_meeting->region;
			} elseif ($field == 'district') {
				if (!empty($_POST['district']) && ($term = get_term($_POST['district'], 'tsml_district'))) {
					$new = $term->name;
				}
				if ($update && !empty($old_meeting->district)) $old = $old_meeting->district;
			} else {
				if ($update) $old = $old_meeting->{$field};
				$new = $_POST[$field];
			}
			
			$field_name = __(ucwords(str_replace('_', ' ', $field)), '12-step-meeting-list');
			
			if (in_array($field, $changes)) {
				$message .= '<tr style="border:1px solid #999;background-color:#fff;"><td style="width:150px;padding:5px">' . $field_name . '</td><td style="padding:5px">';
				if (!empty($old)) $message .= '<strike style="color:#999">' . $old . '</strike> ';
				$message .= $new . '</td></tr>';
			} elseif (!empty($old)) {
				$message .= '<tr style="border:1px solid #999;background-color:#eee;"><td style="width:150px;padding:5px">' . $field_name . '</td><td style="padding:5px">' . $old . '</td></tr>';
			}
		}
		$message .= '</table>';
		$subject = $update ? __('Meeting Change Notification', '12-step-meeting-list') : __('New Meeting Notification', '12-step-meeting-list');
		$subject .= ': ' . sanitize_text_field($_POST['post_title']);
		tsml_email($tsml_notification_addresses, $subject, $message);
	}
}