<?php

add_action('save_post', function(){
	global $post;

	//security, todo verify nonce
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	//if (!wp_verify_nonce($_POST['meetings_nonce'], plugin_basename(__FILE__))) return;
	if ($_POST['post_type'] != 'meetings') return;


	//todo server-side validation here (at least time)


	//add a new location
	$_POST['post_type'] = 'locations';

	if (empty($_POST['location_id'])) {
		//save new post
		//todo check if exists
		$_POST['location_id'] = wp_insert_post(array(
		  'post_title'	=> $_POST['location'],
		  'post_type'	=> 'locations',
		  'post_status'	=> 'publish',
		  'post_author'	=> 1,
		));
	} else {
		//update any changes to title
		wp_update_post(array(
			'ID'			=> $_POST['location_id'],
			'post_title'	=> $_POST['location'],
		));
	}

	//update address & info on location
	update_post_meta($_POST['location_id'], 'address',	$_POST['address']);
	update_post_meta($_POST['location_id'], 'latitude',	$_POST['latitude']);
	update_post_meta($_POST['location_id'], 'longitude',$_POST['longitude']);
	update_post_meta($_POST['location_id'], 'region',	$_POST['region']);


	//also update address on meeting, repetitive but speedy
	update_post_meta($post->ID, 'location',		$_POST['location']);
	update_post_meta($post->ID, 'address',		$_POST['address']);
	update_post_meta($post->ID, 'latitude',		$_POST['latitude']);
	update_post_meta($post->ID, 'longitude',	$_POST['longitude']);
	update_post_meta($post->ID, 'region',		$_POST['region']);


	//save latest meeting info
	update_post_meta($post->ID, 'day',			$_POST['day']);
	update_post_meta($post->ID, 'time',			$_POST['time']);
	update_post_meta($post->ID, 'notes',		$_POST['notes']);
	update_post_meta($post->ID, 'location_id',	$_POST['location_id']);
	update_post_meta($post->ID, 'types',		$_POST['types']);

});
