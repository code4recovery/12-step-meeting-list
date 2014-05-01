<?php
global $post;

if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
//if (!wp_verify_nonce($_POST['meetings_nonce'], plugin_basename(__FILE__))) return;

if ($_POST['post_type'] != 'meetings') return;

//do server-side validation here
//probably need to check at least the time field

//save meeting's metadata
update_post_meta($post->ID, 'day',		$_POST['day']);
update_post_meta($post->ID, 'time',		$_POST['time']);
update_post_meta($post->ID, 'type',		$_POST['type']);
update_post_meta($post->ID, 'notes',	$_POST['notes']);
wp_set_post_terms($post->ID, $_POST['tags'], 'tags');

//location
$_POST['post_type'] = 'locations';
$location_id = wp_insert_post(array(
  'post_title'	=> $_POST['location'],
  'post_type'	=> 'locations',
  'post_status'	=> 'publish',
  'post_author'	=> 1,
));
update_post_meta($location_id, 'address1',	$_POST['address1']);
update_post_meta($location_id, 'address2',	$_POST['address1']);
update_post_meta($location_id, 'region',	$_POST['region']);

p2p_create_connection('locations_to_meetings', array(
    'from'	=> $location_id,
    'to'	=> $post->ID,
    'meta'	=> array(
        'date' => current_time('mysql')
    )
));