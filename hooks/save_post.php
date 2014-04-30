<?php
global $post;

//do server-side validation here
//probably need to check at least the time field

//save meeting's metadata
update_post_meta($post->ID, 'day',		$_POST['day']);
update_post_meta($post->ID, 'time',		$_POST['time']);
update_post_meta($post->ID, 'type',		$_POST['type']);
update_post_meta($post->ID, 'notes',	$_POST['notes']);
wp_set_post_terms($post->ID, $_POST['tags'], 'tags');

//location
