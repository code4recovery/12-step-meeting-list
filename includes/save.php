<?php

add_action('save_post', function(){
	global $post, $tsml_nonce;

	//security
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!isset($_POST['tsml_nonce'])) return;
	if (!wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) return;
	if (!current_user_can('edit_post', $post->ID)) return;
	if ($_POST['post_type'] != 'meetings') return;

	//todo server-side validation here (at least time)
	$post_status = 'publish';
	if (empty($_POST['time']) || empty($_POST['formatted_address'])) {
		$_POST['post_status'] = 'draft';
	}

	//save ordinary meeting metadata
	update_post_meta($post->ID, 'day',			intval($_POST['day']));
	update_post_meta($post->ID, 'time',			sanitize_text_field($_POST['time']));
	update_post_meta($post->ID, 'types',		array_map('esc_attr', $_POST['types']));
	update_post_meta($post->ID, 'region',		intval($_POST['region'])); //double-entry just for searching

	//save location information
	if (empty($_POST['address'])) {

	} else {

		$_POST['post_type'] = 'locations';

		//see if address is already in the database
		if ($locations = get_posts('post_type=locations&numberposts=1&orderby=id&order=ASC&meta_key=address&meta_value=' . sanitize_text_field($_POST['address']))) {
			$location_id = $locations[0]->ID;
			wp_update_post(array(
				'ID'			=> $location_id,
				'post_title'	=> sanitize_text_field($_POST['location']),
			));
		} else {
			$location_id = wp_insert_post(array(
			  'post_title'	=> sanitize_text_field($_POST['location']),
			  'post_type'	=> 'locations',
			  'post_status'	=> 'publish',
			));
		}

		//update address & info on location
		update_post_meta($location_id, 'formatted_address',	sanitize_text_field($_POST['formatted_address']));
		update_post_meta($location_id, 'address',			sanitize_text_field($_POST['address']));
		update_post_meta($location_id, 'city',				sanitize_text_field($_POST['city']));
		update_post_meta($location_id, 'state',				sanitize_text_field($_POST['state']));
		update_post_meta($location_id, 'country',			sanitize_text_field($_POST['country']));
		update_post_meta($location_id, 'latitude',			floatval($_POST['latitude']));
		update_post_meta($location_id, 'longitude',			floatval($_POST['longitude']));
		update_post_meta($location_id, 'region',			intval($_POST['region']));

		//set parent
		wp_update_post(array(
			'ID'			=> $post->ID,
			'post_parent'	=> $location_id,
			'post_status'	=> sanitize_text_field($_POST['post_status']),
		));

		//clean up orphans
		tsml_delete_orphaned_locations();
	}

});
