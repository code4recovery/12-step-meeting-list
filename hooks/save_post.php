<?php
global $post, $states, $regions;


//security, todo verify nonce
if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
//if (!wp_verify_nonce($_POST['meetings_nonce'], plugin_basename(__FILE__))) return;
if ($_POST['post_type'] != 'meetings') return;


//todo server-side validation here (at least time)


//todo look up latitude / longitude, perhaps verify address by api


//add a new location
$_POST['post_type'] = 'locations';
$location_id = wp_insert_post(array(
  'post_title'	=> $_POST['location'],
  'post_type'	=> 'locations',
  'post_status'	=> 'publish',
  'post_author'	=> 1,
));
update_post_meta($location_id, 'address1',	$_POST['address1']);
update_post_meta($location_id, 'address2',	$_POST['address1']);
update_post_meta($location_id, 'city',		$_POST['address1']);
update_post_meta($location_id, 'state',		$_POST['address1']);
update_post_meta($location_id, 'region',	$_POST['region']);


//save latest meeting info, todo wipe out all legacy custom data
$_POST['post_type'] = 'meetings'; //prob unncessary

update_post_meta($post->ID, 'day',		$_POST['day']);
update_post_meta($post->ID, 'time',		$_POST['time']);
update_post_meta($post->ID, 'notes',	$_POST['notes']);
wp_set_post_terms($post->ID, $_POST['types'], 'types');
update_post_meta($post->ID, 'location',	$location_id);


//also update address on meeting, repetitive but speedy
update_post_meta($post->ID, 'address1',	$_POST['address1']);
update_post_meta($post->ID, 'address2',	$_POST['address2']);
update_post_meta($post->ID, 'city',		$_POST['city']);
update_post_meta($post->ID, 'state',	$states[$_POST['state']]);		//save nicename
update_post_meta($post->ID, 'region',	$regions[$_POST['region']]);	//save nicename

