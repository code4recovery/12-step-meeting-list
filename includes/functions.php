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
//used:		importer
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

//function: deletes all the meetings in the database
//used:		importer
function meetings_delete_all_meetings() {
	//delete locations
	$meetings = get_posts('post_type=meetings&numberposts=-1');
	foreach ($meetings as $meeting) {
		wp_delete_post($meeting->ID, true);
	}
}

//function: remove all regions from database
//used: importer
function meetings_delete_all_regions() {
	$terms = get_terms('region', 'hide_empty=0');
	foreach ($terms as $term) {
		wp_delete_term($term->term_id, 'region');
	}
}

//function: load the regions array
//used: init & importer
function meetings_get_regions() {
	$regions = array();
	$region_terms = get_terms('region', 'hide_empty=0');
	foreach ($region_terms as $region) $regions[$region->term_id] = $region->name;
	return $regions;
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

//for debugging
function meetings_print($array, $exit=false) {
	echo '<pre>';
	print_r($array);
	echo '</pre>';
	if ($exit) exit;
}

//get meetings based on post information
//used by meetings_list and meetings_map ajax functions (for theme)
function meetings_get() {
	if (empty($_POST['day'])) $_POST['day'] = date('w');

	$meta_query = array(
		'relation'	=> 'AND',
		array(
			'key'	=> 'day',
			'value'	=> $_POST['day'],
		)
	);

	if (!empty($_POST['region'])) {
		$meta_query[] = array(
			'key'	=> 'region',
			'value'	=> $_POST['region'],
		);
	}

	if (!empty($_POST['types'])) {
		foreach ($_POST['types'] as $type) {
			$meta_query[] = array(
				'key'	=> 'types',
				'value'	=> '"' . $type . '"',
				'compare'=>'LIKE',
			);
		}
	}

	//meetings_print($meta_query);	
	
	return get_posts(array(
	    'post_type'		=> 'meetings',
	    'numberposts'	=> -1,
		'meta_key'		=> 'time',
		'orderby'		=> 'meta_value',
		'order'			=> 'asc',
		'meta_query'	=> $meta_query,
	));
}

//get meetings ajax
add_action('wp_ajax_nopriv_meetings_list', 'meetings_list');
add_action('wp_ajax_meetings_list', 'meetings_list');

function meetings_list() {

	global $regions;

	if (!$meetings = meetings_get()) {?>
		<div class="alert alert-warning">No meetings were found matching those criteria.</div>
	<?php } else {?>

		<table class="table table-striped">
			<thead>
				<tr>
					<th>Time</th>
					<th>Name</th>
					<th>Location</th>
					<th>Region</th>
				</tr>
			</head>
			<tbody>
			<?php 
			foreach ($meetings as $meeting) {
				$custom = get_post_meta($meeting->ID);
				?>
				<tr>
					<td><?php echo meetings_format_time($custom['time'][0])?></td>
					<td><a href="<?php echo $meeting->post_name ?>"><?php echo $meeting->post_title ?></a></td>
					<td><?php echo $custom['location'][0]?></td>
					<td><?php echo $regions[$custom['region'][0]]?></td>
				</tr>
			<?php }?>
		</table>

	<?php
	}
	if (!empty($_POST)) die();
}

//map json
add_action('wp_ajax_nopriv_meetings_map', 'meetings_map');
add_action('wp_ajax_meetings_map', 'meetings_map');

function meetings_map() {
	global $regions;

	$meetings = meetings_get();
	$locations = array();
	foreach ($meetings as &$meeting) {
		$meeting->custom = get_post_meta($meeting->ID);
		if (!isset($locations[$meeting->custom['location_id'][0]])) {
			$locations[$meeting->custom['location_id'][0]] = array(
				'title'		=>$meeting->custom['location'][0],
				'latitude'	=>$meeting->custom['latitude'][0],
				'longitude'	=>$meeting->custom['longitude'][0],
				'address'	=>$meeting->custom['address'][0],
				'region'	=>$regions[$meeting->custom['region'][0]],
				'meetings'	=>array(),
			);
		}
		$locations[$meeting->custom['location_id'][0]]['meetings'][] = $meeting;
	}

	wp_send_json($locations);
}