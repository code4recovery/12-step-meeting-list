<?php

//function: takes 18:30 and returns 6:30 PM
//used:		on list pages and by theme
function meetings_format_time($string) {
	if (!strstr($string, ':')) return 'n/a';
	if ($string == '12:00') return 'Noon';
	if ($string == '23:59') return 'Midnight';
	list($hours, $minutes) = explode(':', $string);
	$hours -= 0;
	$ampm = ($hours > 11) ? 'p' : 'a';
	$hours = ($hours > 12) ? $hours - 12 : $hours;
	return $hours . ':' . $minutes . $ampm;
}

//function: deletes all the locations in the database
//used:		ad-hoc
function meetings_delete_all_locations() {
	//delete locations
	$locations = get_posts('post_type=locations&numberposts=-1');
	foreach ($locations as $location) {
		wp_delete_post($location->ID, true);
	}
	//delete associations with meetings
	$meetings = get_posts('post_type=meetings&numberposts=-1');
	foreach ($meetings as $meeting) {
		meetings_remove_location($meeting->ID);
	}
}

//function: remove all location info from a meeting
//used:		in save_post filter and meetings_delete_all_locations
function meetings_remove_location($meeting_id) {
	delete_post_meta($meeting_id, 'location_id');
	delete_post_meta($meeting_id, 'location');
	delete_post_meta($meeting_id, 'address');
	delete_post_meta($meeting_id, 'latitude');
	delete_post_meta($meeting_id, 'longitude');
	delete_post_meta($meeting_id, 'region');	
}

//function: deletes all orphaned locations (has no meetings associated)
//used:		save_post filter and ad-hoc
function meetings_delete_orphaned_locations() {

	//get all active location_ids
	$active = array();
	$meetings = get_posts('post_type=meetings&numberposts=-1');
	foreach ($meetings as $meeting) {
		$active[] = get_post_meta($meeting->ID, 'location_id', true);
	}

	//get all location ids
	$all_locations = array();
	$locations = get_posts('post_type=locations&numberposts=-1');
	foreach ($locations as $location) {
		$all_locations[] = $location->ID;
	}

	//foreach location id not active, delete it
	$inactive = array_diff($all_locations, $active);
	foreach($inactive as $location_id) {
		wp_delete_post($location_id, true);
	}
}


