<?php

function meetings_format_time($string) {
	//takes 18:30 and returns 6:30 PM
	if (!strstr($string, ':')) return 'n/a';
	if ($string == '12:00') return 'Noon';
	if ($string == '23:59') return 'Midnight';
	list($hours, $minutes) = explode(':', $string);
	$ampm = ($hours > 11) ? 'PM' : 'AM';
	$hours = ($hours > 12) ? $hours - 12 : $hours;
	return $hours . ':' . $minutes . ' ' . $ampm;
}

function meetings_delete_all_locations() {
	//deletes all the locations
	$locations = get_posts('post_type=locations&numberposts=-1');
	foreach ($locations as $location) {
		wp_delete_post($location->ID, true);
	}
}



