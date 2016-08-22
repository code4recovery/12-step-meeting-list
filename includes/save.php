<?php

//catch meetings without locations and save them as a draft
add_filter('wp_insert_post_data', 'tsml_insert_post_check', '99', 2);
function tsml_insert_post_check($post) {
	if (($post['post_type'] == TSML_TYPE_MEETINGS) && empty($post['post_parent']) && ($post['post_status'] == 'publish')) {
		$post['post_status'] = 'draft';
	}
	return $post;
}

//handle all the metadata, location
add_action('save_post', 'tsml_save_post');
function tsml_save_post(){
	global $post, $tsml_nonce, $wpdb;

	//security
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!isset($post->ID) || !current_user_can('edit_post', $post->ID)) return;
	if (!isset($_POST['tsml_nonce']) || !wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) return;
	if (!isset($_POST['post_type']) || ($_POST['post_type'] != TSML_TYPE_MEETINGS)) return;
	
	//get current meeting state to compare against
	$old_meeting = tsml_get_meeting($post->ID);
	
	//track changes to meeting
	$changes = array();
	
	//cache region on meeting
	if ($old_meeting->region != $_POST['region']) {
		$changes[] = 'group';
		if (empty($_POST['region'])) {
			delete_post_meta($post->ID, 'region');
		} else {
			update_post_meta($post->ID, 'region', intval($_POST['region']));
		}
	}
	
	//check types for errors first
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

		$_POST['time'] = sanitize_text_field($_POST['time']);
		if ($old_meeting->time != $_POST['time']) {
			$changes[] = 'time';
			if (empty($_POST['time'])) {
				delete_post_meta($post->ID, 'time');
			} else {
				update_post_meta($post->ID, 'time', $_POST['time']);
			}
		}

		$_POST['end_time'] = sanitize_text_field($_POST['end_time']);
		if ($old_meeting->end_time != $_POST['end_time']) {
			$changes[] = 'end_time';
			if (empty($_POST['end_time'])) {
				delete_post_meta($post->ID, 'end_time');
			} else {
				update_post_meta($post->ID, 'end_time', sanitize_text_field($_POST['end_time']));
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
	$_POST['post_type'] = TSML_TYPE_LOCATIONS;
	$_POST['location'] = sanitize_text_field($_POST['location']);
	$_POST['location_notes'] = sanitize_text_area($_POST['location_notes']);
	
	//see if address is already in the database
	if ($locations = get_posts(array(
		'post_type' => TSML_TYPE_LOCATIONS,
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
		  	'post_type'		=> TSML_TYPE_LOCATIONS,
		  	'post_status'	=> 'publish',
			'post_content'  => $_POST['location_notes'],
		));
	}

	//update address & info on location
	foreach (array('formatted_address', 'address', 'city', 'state', 'postal_code', 'country') as $field) {
		$_POST[$field] = sanitize_text_field($_POST[$field]);
		if ($old_meeting->{$field} != $_POST[$field]) {
			$changes[] = $field;
			if (empty($_POST[$field])) {
				delete_post_meta($location_id, $field);
			} else {
				update_post_meta($location_id, $field, $_POST[$field]);
			}
		}
	}

	foreach (array('latitude', 'longitude') as $field) {
		if ($old_meeting->{$field} != $_POST[$field]) {
			$changes[] = $field;
			update_post_meta($location_id, $field, floatval($_POST[$field]));
		}
	}

	//update region caches for other meetings at this location
	if ($old_meeting->region != $_POST['region']) {
		$meetings = tsml_get_meetings(array('location_id' => $location_id));
		if (empty($_POST['region'])) {
			delete_post_meta($location_id, 'region');
			foreach ($meetings as $meeting) delete_post_meta($location_id, 'region');
		} else {
			update_post_meta($location_id, 'region', intval($_POST['region']));
			foreach ($meetings as $meeting) update_post_meta($meeting['id'], 'region', intval($_POST['region']));
		}
	}

	//set parent on this post (and post status?) without re-triggering the save_posts hook
	if (($old_meeting->post_parent != $location_id) || ($old_meeting->post_status != $_POST['post_status'])) {
		$changes[] = 'post_parent and/or post_status';
		$wpdb->get_var($wpdb->prepare('UPDATE ' . $wpdb->posts . ' SET post_parent = %d, post_status = %s WHERE ID = %d', $location_id, sanitize_text_field($_POST['post_status']), $post->ID));
	}

	//deleted orphaned locations
	tsml_delete_orphaned_locations();
	
	//save group information (set this value or get caught in a loop)
	$_POST['post_type'] = TSML_TYPE_GROUPS;
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
		if ($groups = $wpdb->get_results($wpdb->prepare('SELECT ID, post_title, post_content FROM ' . $wpdb->posts . ' WHERE post_type = "%s" AND post_title = "%s" ORDER BY id', TSML_TYPE_GROUPS, stripslashes($_POST['group'])))) {
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
			  	'post_type'		=> TSML_TYPE_GROUPS,
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
		$orphans = get_posts('post_type=' . TSML_TYPE_GROUPS . '&numberposts=-1&exclude=' . implode(',', $groups_in_use));
		foreach ($orphans as $orphan) wp_delete_post($orphan->ID);
	}
	
	//update types in use
	tsml_update_types_in_use();
}