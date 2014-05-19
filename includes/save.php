<?php

add_action('save_post', function(){
	global $post;

	//security, todo verify nonce
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	//if (!wp_verify_nonce($_POST['meetings_nonce'], plugin_basename(__FILE__))) return;
	if ($_POST['post_type'] != 'meetings') return;


	//todo server-side validation here (at least time)


	//save ordinary meeting metadata
	update_post_meta($post->ID, 'day',			$_POST['day']);
	update_post_meta($post->ID, 'time',			$_POST['time']);
	update_post_meta($post->ID, 'types',		$_POST['types']);
	update_post_meta($post->ID, 'region',		$_POST['region']); //double-entry just for searching

	//save location information
	if (empty($_POST['address'])) {

	} else {

		$_POST['post_type'] = 'locations';

		//see if address is already in the database
		if ($locations = get_posts('post_type=locations&numberposts=1&orderby=id&order=ASC&meta_key=address&meta_value=' . $_POST['address'])) {
			$location_id = $locations[0]->ID;
			wp_update_post(array(
				'ID'			=> $location_id,
				'post_title'	=> $_POST['location'],
			));
		} else {
			$location_id = wp_insert_post(array(
			  'post_title'	=> $_POST['location'],
			  'post_type'	=> 'locations',
			  'post_status'	=> 'publish',
			));
		}

		//update address & info on location
		update_post_meta($location_id, 'address',	$_POST['address']);
		update_post_meta($location_id, 'latitude',	$_POST['latitude']);
		update_post_meta($location_id, 'longitude',	$_POST['longitude']);
		update_post_meta($location_id, 'region',	$_POST['region']);

		//set parent
		wp_update_post(array(
			'ID'			=> $post->ID,
			'post_parent'	=> $location_id,
		));

		//clean up orphans
		meetings_delete_orphaned_locations();
	}


});
