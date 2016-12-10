<?php

//function: helper for debugging
//used:		ad-hoc
if (!function_exists('dd')) {
	function dd($array) {
		echo '<pre>';
		print_r($array);
		exit;	
	}
}

//function: sanitize multi-line text
//used:		tsml_import() and save.php
if (!function_exists('sanitize_text_area')) {
	function sanitize_text_area($value) {
		return implode("\n", array_map('sanitize_text_field', explode("\n", $value)));
	}
}

//function:	add an admin screen update message
//used:		tsml_import() and admin_types.php
function tsml_alert($message, $type='updated') {
	global $tsml_alerts;
	$tsml_alerts[] = compact('message', 'type');
	add_action('admin_notices', 'tsml_alert_messages');
}

//function:	run through alert stack and output them all
//used:		tsml_alert()
function tsml_alert_messages() {
	global $tsml_alerts;
	foreach ($tsml_alerts as $alert) {
		echo '<div class="' . $alert['type'] . '"><p>' . $alert['message'] . '</p></div>';
	}
}

//function: enqueue assets for public or admin page
//used: in templates and on admin_edit.php
function tsml_assets() {
	global $tsml_types, $tsml_program, $tsml_google_api_key, $tsml_google_overrides;
		
	//google maps api needed for maps and address verification, can't be onboarded
	wp_enqueue_script('google_maps_api', '//maps.googleapis.com/maps/api/js?key=' . $tsml_google_api_key);
	
	if (is_admin()) {
		//dashboard page assets
		wp_enqueue_style('tsml_admin_css', plugins_url('../assets/css/admin.min.css', __FILE__), array(), TSML_VERSION);
		wp_enqueue_script('tsml_admin_js', plugins_url('../assets/js/admin.min.js', __FILE__), array('jquery'), TSML_VERSION, true);
		wp_localize_script('tsml_admin_js', 'myAjax', array(
			'ajaxurl' => admin_url('admin-ajax.php'),
			'language' => current(explode('-', get_bloginfo('language'))),
			'google_api_key' => $tsml_google_api_key,
			'google_overrides' => json_encode($tsml_google_overrides),
		));
	} else {
		//public page assets
		wp_enqueue_style('bootstrap_css', plugins_url('../assets/css/bootstrap.min.css', __FILE__), array(), TSML_VERSION);
		wp_enqueue_script('bootstrap_js', plugins_url('../assets/js/bootstrap.min.js', __FILE__), array('jquery'), TSML_VERSION, true);
		wp_enqueue_script('tsml_public_js', plugins_url('../assets/js/public.min.js', __FILE__), array('jquery'), TSML_VERSION, true);
		wp_localize_script('tsml_public_js', 'myAjax', array(
			'ajaxurl' => admin_url('admin-ajax.php'),
			'types' => $tsml_types[$tsml_program],
			'days' => array(
				__('Sunday', '12-step-meeting-list'),
				__('Monday', '12-step-meeting-list'),
				__('Tuesday', '12-step-meeting-list'),
				__('Wednesday', '12-step-meeting-list'),
				__('Thursday', '12-step-meeting-list'),
				__('Friday', '12-step-meeting-list'),
				__('Saturday', '12-step-meeting-list'),
			),
			'strings' => array(
				'groups' => __('Groups', '12-step-meeting-list'),
				'locations' => __('Locations', '12-step-meeting-list'),
				'regions' => __('Regions', '12-step-meeting-list'),
				'meetings' => __('Meetings', '12-step-meeting-list'),
				'men' => __('Meetings', '12-step-meeting-list'),
				'women' => __('Women', '12-step-meeting-list'),
				'email_not_sent' => __('Email was not sent.', '12-step-meeting-list'),
			),
		));
		wp_enqueue_style('tsml_public_css', plugins_url('../assets/css/public.min.css', __FILE__), array(), TSML_VERSION);
		wp_enqueue_script('validate_js', plugins_url('../assets/js/jquery.validate.min.js', __FILE__), array('jquery'), TSML_VERSION, true);
	}
}

//called by register_activation_hook in 12-step-meeting-list.php
//hands off to tsml_custom_post_types
function tsml_change_activation_state() {
	tsml_custom_post_types();
	flush_rewrite_rules();
}

//function:	return integer number of live groups
//used:		shortcode, admin-import.php, tsml_ajax_import()
function tsml_count_groups() {
	return count(tsml_get_all_groups('publish'));
}

//function:	return integer number of live locations
//used:		shortcode, admin-import.php, tsml_ajax_import()
function tsml_count_locations() {
	return count(tsml_get_all_locations('publish'));
}

//function:	return integer number of live meetings
//used:		shortcode, admin-import.php, tsml_ajax_import()
function tsml_count_meetings() {
	return count(tsml_get_all_meetings('publish'));
}

//function:	return integer number of live regions
//used:		shortcode, admin-import.php, tsml_ajax_import()
function tsml_count_regions() {
	return count(tsml_get_all_regions());
}

//function: register custom post types
//used: 	init.php on every request, also in change_activation_state() for plugin activation or deactivation
function tsml_custom_post_types() {
	register_taxonomy('tsml_region', 'tsml_location', array(
		'labels' => array(
			'name' => __('Regions', '12-step-meeting-list'),
			'singular_name' => __('Region', '12-step-meeting-list'),
			'menu_name'  => __('Regions', '12-step-meeting-list'),
			'all_items'  => __('All Regions', '12-step-meeting-list'),
			'edit_item'  => __('Edit Region', '12-step-meeting-list'),
			'view_item'  => __('View Region', '12-step-meeting-list'),
			'update_item'  => __('Update Region', '12-step-meeting-list'),
			'add_new_item'  => __('Add New Region', '12-step-meeting-list'),
			'new_item_name'  => __('New Region', '12-step-meeting-list'),
			'parent_item'  => __('Parent Region', '12-step-meeting-list'),
			'parent_item_colon'  => __('Parent Region:', '12-step-meeting-list'),
			'search_items'  => __('Search Regions', '12-step-meeting-list'),
			'popular_items'  => __('Popular Regions', '12-step-meeting-list'),
			'not_found'  => __('No regions found.', '12-step-meeting-list'),
		),
		'hierarchical' => true,
	));

	register_post_type('tsml_meeting',
		array(
			'labels'		=> array(
				'name'			=>	__('Meetings', '12-step-meeting-list'),
				'singular_name'	=>	__('Meeting', '12-step-meeting-list'),
				'not_found'		=>	__('No meetings added yet.', '12-step-meeting-list'),
				'add_new_item'	=>	__('Add New Meeting', '12-step-meeting-list'),
				'search_items'	=>	__('Search Meetings', '12-step-meeting-list'),
				'edit_item'		=>	__('Edit Meeting', '12-step-meeting-list'),
				'view_item'		=>	__('View Meeting', '12-step-meeting-list'),
			),
			'supports'		=> array('title'),
			'public'		=> true,
			'has_archive'	=> true,
			'menu_icon'		=> 'dashicons-groups',
			'rewrite'		=> array('slug'=>'meetings'),
		)
	);

	register_post_type('tsml_location',
		array(
			'supports'		=> array('title'),
			'public'		=> true,
			'show_ui'		=> false,
			'has_archive'	=> true,
			'capabilities'	=> array('create_posts' => false),
			'rewrite'		=> array('slug'=>'locations'),
			'taxonomies'	=> array('tsml_region'),
		)
	);	

	register_post_type('tsml_group',
		array(
			'supports'		=> array('title'),
			'public'		=> true,
			'show_ui'		=> false,
			'has_archive'	=> false,
			'capabilities'	=> array('create_posts' => false),
		)
	);	
}

//fuction:	define custom meeting types for your area
//used:		theme's functions.php
function tsml_custom_types($types) {
	global $tsml_types, $tsml_program;
	foreach ($types as $key=>$value) {
		$tsml_types[$tsml_program][$key] = $value;
	}
	asort($tsml_types[$tsml_program]);
}

//called by tsml_import() and in the future elsewhere
function tsml_debug($string) {
	global $tsml_timestamp;
	if (!WP_DEBUG) return;
	tsml_alert($string . ' in ' . round(microtime(true) - $tsml_timestamp, 2) . 's', 'notice notice-warning');
	$tsml_timestamp = microtime(true);
}

//function:	efficiently remove an array of post_ids
//used:		tsml_delete_orphans(), admin-import.php
function tsml_delete($post_ids) {
	global $wpdb;

	if (empty($post_ids) || !is_array($post_ids)) return;
	
	//sanitize
	$post_ids = array_map('intval', $post_ids);
	$post_ids = array_unique($post_ids);
	$post_ids = implode(', ', $post_ids);
	
	//run deletes
	$wpdb->query('DELETE FROM ' . $wpdb->posts . ' WHERE id IN (' . $post_ids . ')');
	$wpdb->query('DELETE FROM ' . $wpdb->postmeta . ' WHERE post_id IN (' . $post_ids . ')');
	$wpdb->query('DELETE FROM ' . $wpdb->term_relationships . ' WHERE object_id IN (' . $post_ids . ')');
}

//function: efficiently deletes all orphaned locations and groups (have no meetings associated)
//used:		save_post filter
function tsml_delete_orphans() {
	global $wpdb;
	$location_ids = $wpdb->get_col('SELECT ID FROM ' . $wpdb->posts . ' l WHERE l.post_type = "tsml_location" AND (SELECT COUNT(*) FROM ' . $wpdb->posts . ' m WHERE m.post_type="tsml_meeting" AND m.post_parent = l.id) = 0');
	$group_ids = $wpdb->get_col('SELECT ID FROM ' . $wpdb->posts . ' g WHERE g.post_type = "tsml_group" AND (SELECT COUNT(*) FROM ' . $wpdb->postmeta . ' m WHERE m.meta_key="group_id" AND m.meta_value = g.id) = 0');
	tsml_delete(array_merge($location_ids, $group_ids));
}

//set content type for emails to html, remember to remove after use
//used by tsml_feedback()
function tsml_email_content_type_html() {
	return 'text/html';
}

//take a full address and return it formatted for the front-end
//used on template pages
function tsml_format_address($formatted_address, $street_only=false) {
	$parts = explode(',', esc_attr($formatted_address));
	$parts = array_map('trim', $parts);
	if (in_array(end($parts), array('USA', 'US'))) {
		array_pop($parts);
		$state_zip = array_pop($parts);
		$parts[count($parts) - 1] .= ', ' . $state_zip;
	}
	if ($street_only) return array_shift($parts);
	return implode('<br>', $parts);
}

//function: takes 0, 18:30 and returns Sunday, 6:30 pm (depending on your settings)
//used:		admin_edit.php, archive-meetings.php, single-meetings.php
function tsml_format_day_and_time($day, $time, $separator=', ', $short=false) {
	global $tsml_days;
	if (empty($tsml_days[$day]) || empty($time)) return $short ? __('Appt', '12-step-meeting-list') : __('Appointment', '12-step-meeting-list');
	return ($short ? substr($tsml_days[$day], 0, 3) : $tsml_days[$day]) . $separator . '<time>' . tsml_format_time($time) . '</time>';
}

//function:	appends men or women if type present
//used:		archive-meetings.php
function tsml_format_name($name, $types=array()) {
	if (in_array('Men', $types) || in_array('M', $types)) {
		$name .= ' <small>' . __('Men', '12-step-meeting-list') . '</small>';
	} elseif (in_array('Women', $types) || in_array('W', $types)) {
		$name .= ' <small>' . __('Women', '12-step-meeting-list') . '</small>';
	}
	return $name;
}

//function: locale-aware number format
//used:		admin-import.php
function tsml_format_number($number) {
	$locale = localeconv();
	return number_format($number, 0, $locale['decimal_point'], $locale['thousands_sep']);
}

//function: takes 18:30 and returns 6:30 pm (depending on your settings)
//used:		tsml_get_meetings(), single-meetings.php, admin_lists.php
function tsml_format_time($string, $empty='Appointment') {
	if (empty($string)) return empty($empty) ? '' : __($empty, '12-step-meeting-list');
	if ($string == '12:00') return __('Noon', '12-step-meeting-list');
	if ($string == '23:59' || $string == '00:00') return __('Midnight', '12-step-meeting-list');
	$date = strtotime($string);
	return date(get_option('time_format'), $date);
}

//function: takes a time string, eg 6:30 pm, and returns 18:30
//used:		tsml_import(), tsml_time_duration()
function tsml_format_time_reverse($string) {
	$time_parts = date_parse($string);
	return sprintf('%02d', $time_parts['hour']) . ':' . sprintf('%02d', $time_parts['minute']);
}

//function:	convert a string to utf8 if it needs it
//used:		by tsml_import()
function tsml_format_utf8(&$item, $key) {
	if (!function_exists('mb_detect_encoding')) return;
	if (!mb_detect_encoding($item, 'utf-8', true)) {
		$item = utf8_encode($item);
	}
}

//function: display meeting list on home page (must be set to a static page)
//used:		by themes that want it, such as https://github.com/meeting-guide/one-page-meeting-list
function tsml_front_page($wp_query){
	if (is_admin()) return; //don't do this to inside pages
	if ($wp_query->get('page_id') == get_option('page_on_front')) {
		$wp_query->set('post_type', 'tsml_meeting');
		$wp_query->set('page_id', '');
		$wp_query->is_page = 0;
		$wp_query->is_singular = 0;
		$wp_query->is_post_type_archive = 1;
		$wp_query->is_archive = 1;
	}
}

//function: get all locations in the system
//used:		tsml_group_count()
function tsml_get_all_groups($status='any') {
	return get_posts('post_type=tsml_group&post_status=' . $status . '&numberposts=-1&orderby=name&order=asc');
}

//function: get all locations in the system
//used:		tsml_location_count(), tsml_import(), and admin_import.php
function tsml_get_all_locations($status='any') {
	return get_posts('post_type=tsml_location&post_status=' . $status . '&numberposts=-1&orderby=name&order=asc');
}

//function: get all meetings in the system
//used:		tsml_meeting_count(), tsml_import(), and admin_import.php
function tsml_get_all_meetings($status='any') {
	return get_posts('post_type=tsml_meeting&post_status=' . $status . '&numberposts=-1&orderby=name&order=asc');
}

//function: get all regions in the system
//used:		tsml_region_count(), tsml_import() and admin_import.php
function tsml_get_all_regions() {
	return get_terms('tsml_region', array('fields'=>'ids', 'hide_empty'=>false));
}

//function: get all locations with full location information
//used: tsml_import(), tsml_get_meetings(), admin_edit
function tsml_get_groups() {

	$groups = array();
	
	# Get all locations
	$posts = tsml_get_all_groups('publish');
	
	# Much faster than doing get_post_meta() over and over
	$group_meta = tsml_get_meta('tsml_group');

	# Make an array of all locations
	foreach ($posts as $post) {

		$groups[$post->ID] = array(
			'group_id'			=> $post->ID, //so as not to conflict with another id when combined
			'group'				=> $post->post_title,
			'group_notes'		=> $post->post_content,
			'contact_1_name'	=> @$group_meta[$post->ID]['contact_1_name'],
			'contact_1_email'	=> @$group_meta[$post->ID]['contact_1_email'],
			'contact_1_phone'	=> @$group_meta[$post->ID]['contact_1_phone'],
			'contact_2_name'	=> @$group_meta[$post->ID]['contact_2_name'],
			'contact_2_email'	=> @$group_meta[$post->ID]['contact_2_email'],
			'contact_2_phone'	=> @$group_meta[$post->ID]['contact_2_phone'],
			'contact_3_name'	=> @$group_meta[$post->ID]['contact_3_name'],
			'contact_3_email'	=> @$group_meta[$post->ID]['contact_3_email'],
			'contact_3_phone'	=> @$group_meta[$post->ID]['contact_3_phone'],
			'last_contact'		=> @$group_meta[$post->ID]['last_contact'],
		);
	}
			
	return $groups;
}

//function: template tag to get location, attach custom fields to it
//used: single-locations.php
function tsml_get_location($location_id=false) {
	$location = get_post($location_id);
	$custom = get_post_meta($location->ID);
	foreach ($custom as $key=>$value) {
		$location->{$key} = htmlentities($value[0], ENT_QUOTES);
	}
	$location->post_title	= htmlentities($location->post_title, ENT_QUOTES);
	$location->notes 		= nl2br(esc_html($location->post_content));
	if ($region = get_the_terms($location, 'tsml_region')) {
		$location->region_id = $region[0]->term_id;
		$location->region = $region[0]->name;
	}

	//directions link
	$location->directions = 'https://maps.apple.com/?q=' . $location->latitude . ',' . $location->longitude . '&z=16';

	return $location;
}

//function: get all locations with full location information
//used: tsml_import(), tsml_get_meetings(), admin_edit
function tsml_get_locations() {
	$locations = array();
	
	# Get all regions with parents, need for sub_region below
	$regions = $regions_with_parents = array();
	$terms = get_categories(array('taxonomy' => 'tsml_region'));
	foreach ($terms as $term) {
		$regions[$term->term_id] = $term->name;
		if ($term->parent) $regions_with_parents[$term->term_id] = $term->parent;
	}
	
	# Get all locations
	$posts = tsml_get_all_locations('publish');
	
	# Much faster than doing get_post_meta() over and over
	$location_meta = tsml_get_meta('tsml_location');

	# Make an array of all locations
	foreach ($posts as $post) {
		$region_id = !empty($location_meta[$post->ID]['region_id']) ? $location_meta[$post->ID]['region_id'] : null;
		if (array_key_exists($region_id, $regions_with_parents)) {
			$region = $regions[$regions_with_parents[$region_id]];
			$sub_region = $regions[$region_id];
		} else {
			$region = !empty($regions[$region_id]) ? $regions[$region_id] : '';
			$sub_region = null;
		}
		
		$locations[$post->ID] = array(
			'location_id'		=> $post->ID, //so as not to conflict with another id when combined
			'location'			=> $post->post_title,
			'location_notes'	=> $post->post_content,
			'location_url'		=> get_permalink($post->ID),
			'formatted_address' => @$location_meta[$post->ID]['formatted_address'],
			'latitude'			=> @$location_meta[$post->ID]['latitude'],
			'longitude'			=> @$location_meta[$post->ID]['longitude'],
			'region_id'			=> $region_id,
			'region'			=> $region,
			'sub_region'		=> $sub_region,
		);
	}
	
	return $locations;
}

//function: template tag to get meeting and location, attach custom fields to it
//used: single-meetings.php
function tsml_get_meeting() {
	global $tsml_program, $tsml_type_descriptions, $tsml_types;
	
	$meeting				= get_post();
	$location				= get_post($meeting->post_parent);
	$custom					= array_merge(get_post_meta($meeting->ID), get_post_meta($location->ID));
	foreach ($custom as $key=>$value) {
		$meeting->{$key} = ($key == 'types') ? $value[0] : htmlentities($value[0], ENT_QUOTES);
	}
	$meeting->types				= empty($meeting->types) ? array() : unserialize($meeting->types);
	$meeting->post_title		= htmlentities($meeting->post_title, ENT_QUOTES);
	$meeting->location			= htmlentities($location->post_title, ENT_QUOTES);
	$meeting->notes 			= nl2br(esc_html($meeting->post_content));
	$meeting->location_notes	= nl2br(esc_html($location->post_content));
	
	if ($region = get_the_terms($location, 'tsml_region')) {
		$meeting->region_id = $region[0]->term_id;
		$meeting->region = $region[0]->name;
	}
	
	//type description?
	foreach (array('C', 'O') as $type) {
		if (in_array($type, $meeting->types) && !empty($tsml_type_descriptions[$tsml_program][$type])) {
			$meeting->type_description = $tsml_type_descriptions[$tsml_program][$type];
			break;
		}
	}
	
	//get other meetings at this location
	$meeting->location_meetings = tsml_get_meetings(array('location_id' => $location->ID));

	//link for directions
	$meeting->directions = 'https://maps.apple.com/?q=' . $meeting->latitude . ',' . $meeting->longitude . '&z=16';

	//if meeting is part of a group, include group info
	if ($meeting->group_id) {
		$group = get_post($meeting->group_id);
		$meeting->group = htmlentities($group->post_title, ENT_QUOTES);
		$meeting->group_notes = nl2br(esc_html($group->post_content));
		$group_custom = get_post_meta($meeting->group_id);
		foreach ($group_custom as $key=>$value) {
			$meeting->{$key} = $value[0];
		}
	} else {
		$meeting->group_id = null;
		$meeting->group = null;
	}
	
	//expand and alphabetize types
	array_map('trim', $meeting->types);
	$types = array();
	foreach ($meeting->types as $type) {
		if (!empty($tsml_types[$tsml_program][$type])) {
			$types[] = $tsml_types[$tsml_program][$type];
		}
	}
	sort($types);
	$meeting->types = $types;
	
	return $meeting;
}

//function: get meetings based on unsanitized $arguments
//used:		tsml_meetings_api(), single-locations.php, archive-meetings.php 
function tsml_get_meetings($arguments=array()) {
	
	//will need these later
	$post_ids = $meetings = array();
	$groups = tsml_get_groups();	
	$locations = tsml_get_locations();
	
	//start building meta_query for meetings
	$meta_query = array('relation' => 'AND');

	//location_id can be an array
	if (empty($arguments['location_id'])) {
		$arguments['location_id'] = null;
	} elseif (is_array($arguments['location_id'])) {
		$arguments['location_id'] = array_map('intval', $arguments['location_id']);
	} else {
		$arguments['location_id'] = array(intval($arguments['location_id']));
	}

	//day should be in integer 0-6 
	if (isset($arguments['day']) && ($arguments['day'] !== false)) {
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'	=> 'day',
				'value'	=> intval($arguments['day']),
			),
			array(
				'key'	=> 'day',
				'value'	=> '', //appointment meetings
			),
		);
	}

	//time should be a string 'morning', 'midday', 'evening', 'night', or 'upcoming'
	if (!empty($arguments['time'])) {
		if ($arguments['time'] == 'morning') {
			$meta_query[] = array(
				//Morning >=4am, < 12pm
				array('key' => 'time', 'value' => array('04:00', '11:59'), 'compare' => 'BETWEEN'),
			);
		} elseif ($arguments['time'] == 'midday') {
			$meta_query[] = array(
				//Midday >=11am, < 5pm
				array('key' => 'time', 'value' => array('11:00', '16:59'), 'compare' => 'BETWEEN'),
			);
		} elseif ($arguments['time'] == 'evening') {
			$meta_query[] = array(
				//Evening >=4pm, < 9pm
				array('key' => 'time', 'value' => array('16:00', '20:59'), 'compare' => 'BETWEEN'),
			);
		} elseif ($arguments['time'] == 'night') {
			$meta_query[] = array(
				//Night >=8pm, < 5am
				'relation' => 'OR',
				array('key' => 'time', 'value' => '04:59', 'compare' => '<='),
				array('key' => 'time', 'value' => '20:00', 'compare' => '>='),
			);
		} elseif ($arguments['time'] == 'upcoming') {
			$meta_query[] = array(
				array('key' => 'time', 'value' => current_time('H:i'), 'compare' => '>='),
			);
		}
	}

	//region should be an integer region id
	if (!empty($arguments['region'])) {
		$parents = get_posts(array(
			'post_type'			=> 'tsml_location',
			'numberposts'		=> -1,
			'fields'			=> 'ids',
			'tax_query'			=> array(
				array(
					'taxonomy'	=> 'tsml_region',
					'terms'		=> intval($arguments['region']),
				),
			),
		));
		$post_ids = array_merge($post_ids, get_posts(array(
			'post_type'			=> 'tsml_meeting',
			'numberposts'		=> -1,
			'fields'			=> 'ids',
			'post_parent__in'	=> $parents,
		)));
	}

	//todo convert this into a custom taxonomy
	if (!empty($arguments['type'])) {
		$meta_query[] = array(
			'key'	=> 'types',
			'compare'=>'LIKE',
			'value'	=> '"' . sanitize_text_field($arguments['type']) . '"',
		);
	}
	
	//group id must be an integer
	if (!empty($arguments['group_id'])) {
		$meta_query[] = array(
			'key'	=> 'group_id',
			'value'	=> intval($arguments['group_id']),
		);
	}
	
	//if searching, a few more queries
	if (!empty($arguments['search'])) {
		$search = sanitize_text_field($arguments['search']);
		
		//first search actual meetings
		$post_ids = array_merge($post_ids, get_posts(array(
			'post_type'			=> 'tsml_meeting',
			'numberposts'		=> -1,
			'fields'			=> 'ids',
			's'					=> $search,
		)));
		
		//then add groups
		if ($groups = get_posts(array(
				'post_type'			=> 'tsml_group',
				'numberposts'		=> -1,
				'fields'			=> 'ids',
				's'					=> $search,
			))) {
			$post_ids = array_merge($post_ids, get_posts(array(
				'post_type'			=> 'tsml_meeting',
				'numberposts'		=> -1,
				'fields'			=> 'ids',
				'meta_query'		=> array(
					array(
						'key'		=> 'group_id',
						'compare'	=> 'IN',
						'value'		=> $groups,
					),
				),
			)));
		}
		
		//also locations, match on name, notes and address...
		$parents = array_merge(
			//searching title and content
			get_posts(array(
				'post_type'			=> 'tsml_location',
				'numberposts'		=> -1,
				'fields'			=> 'ids',
				's'					=> $search,
			)),
			//searching address
			get_posts(array(
				'post_type'			=> 'tsml_location',
				'numberposts'		=> -1,
				'fields'			=> 'ids',
				'meta_query'		=> array(
					array(
						'key'		=> 'formatted_address',
						'value'		=> $search,
						'compare'	=> 'LIKE',
					),
				),
			))
		);
		
		//... and also regions
		if ($regions = get_terms('tsml_region', array(
				'search' => $search, 
				'fields' => 'ids', 
				'hide_empty' => false
			))) {
			$parents = array_merge($parents, get_posts(array(
				'post_type'			=> 'tsml_location',
				'numberposts'		=> -1,
				'fields'			=> 'ids',
				'meta_query'		=> array(
					array(
						'key'	=> 'region',
						'compare' => 'IN',
						'value'	=> $regions,
					),
				),
			)));
		}
		
		if (count($parents)) {
			$post_ids = array_merge($post_ids, get_posts(array(
				'post_type'			=> 'tsml_meeting',
				'numberposts'		=> -1,
				'fields'			=> 'ids',
				'post_parent__in'	=> $parents,
			)));
		}
		
		if (empty($post_ids)) return array();
	}
	
	//search meetings
	$posts = get_posts(array(
		'post_type'			=> 'tsml_meeting',
		'numberposts'		=> -1,
		'meta_query'		=> $meta_query,
		'post__in'			=> array_unique($post_ids),
		'post_parent__in'	=> $arguments['location_id'],
	));

	//need this later, need to supply default values to groupless meetings
	$null_group_info = (current_user_can('edit_posts')) ? array('group' => null, 'group_notes' => null, 'contact_1_name' => null, 'contact_1_email' => null, 'contact_1_phone' => null, 'contact_2_name' => null, 'contact_2_email' => null, 'contact_2_phone' => null, 'contact_3_name' => null, 'contact_3_email' => null, 'contact_3_phone' => null, ) : array('group' => null, 'group_notes' => null);

	$meeting_meta = tsml_get_meta('tsml_meeting');

	//make an array of the meetings
	foreach ($posts as $post) {
		//shouldn't ever happen, but just in case
		if (empty($locations[$post->post_parent])) continue;

		$array = array_merge(array(
			'id'				=> $post->ID,
			'name'				=> $post->post_title,
			'slug'				=> $post->post_name,
			'notes'				=> $post->post_content,
			'updated'			=> $post->post_modified_gmt,
			'location_id'		=> $post->post_parent,
			'url'				=> get_permalink($post->ID),
			'time'				=> @$meeting_meta[$post->ID]['time'],
			'end_time'			=> @$meeting_meta[$post->ID]['end_time'],
			'time_formatted'	=> tsml_format_time(@$meeting_meta[$post->ID]['time']),
			'day'				=> @$meeting_meta[$post->ID]['day'],
			'types'				=> empty($meeting_meta[$post->ID]['types']) ? array() : unserialize($meeting_meta[$post->ID]['types']),
		), $locations[$post->post_parent]);
		
		//append group info to meeting
		if (!empty($meeting_meta[$post->ID]['group_id']) && array_key_exists($meeting_meta[$post->ID]['group_id'], $groups)) {
			$array = array_merge($array, $groups[$meeting_meta[$post->ID]['group_id']]);
		} else {
			$array = array_merge($array, $null_group_info);
		}
		
		$meetings[] = $array;
	}

	usort($meetings, 'tsml_sort_meetings');
	
	return $meetings;
}

//function: get metadata very quickly
//called in tsml_get_meetings(), tsml_get_locations()
function tsml_get_meta($type, $id=null) {
	global $wpdb;
	$keys = array(
		'tsml_group' => '"contact_1_name", "contact_1_email", "contact_1_phone", "contact_2_name", "contact_2_email", "contact_2_phone", "contact_3_name", "contact_3_email", "contact_3_phone", "last_contact"',
		'tsml_location' => '"formatted_address", "latitude", "longitude"',
		'tsml_meeting' => '"day", "time", "end_time", "types", "group_id"',
	);
	if (!array_key_exists($type, $keys)) return trigger_error('tsml_get_meta for unexpected type ' . $type);
	$meta = array();
	$query = 'SELECT post_id, meta_key, meta_value FROM ' . $wpdb->postmeta . ' WHERE 
		meta_key IN (' . $keys[$type] . ') AND 
		post_id ' . ($id ? '= ' . $id : 'IN (SELECT id FROM ' . $wpdb->posts . ' WHERE post_type = "' . $type . '")');
	$values = $wpdb->get_results($query);
	foreach ($values as $value) {
		$meta[$value->post_id][$value->meta_key] = $value->meta_value;
	}
	
	//if location, get region
	if ($type == 'tsml_location') {
		$regions = $wpdb->get_results('SELECT 
				r.`object_id` location_id,
				t.`term_id` region_id,
				t.`name` region
			FROM ' . $wpdb->term_relationships . ' r
			JOIN ' . $wpdb->term_taxonomy . ' x ON r.term_taxonomy_id = x.term_taxonomy_id
			JOIN ' . $wpdb->terms . ' t ON x.term_id = t.term_id
			WHERE x.taxonomy = "tsml_region"');
		foreach ($regions as $region) {
			$meta[$region->location_id]['region'] = $region->region;
			$meta[$region->location_id]['region_id'] = $region->region_id;
		}
	}
	
	if ($id) return $meta[$id];
	return $meta;
}

//return spelled-out meeting types
//called from save.php (updates) and archive-meetings.php (display)
function tsml_meeting_types($types) {
	global $tsml_types, $tsml_program;
	$return = array();
	foreach ($types as $type) {
		if (array_key_exists($type, $tsml_types[$tsml_program])) {
			$return[] = $tsml_types[$tsml_program][$type];
		}
	}
	sort($return);
	return implode(', ', $return);
}

//function:	filter workaround for setting post_modified dates
//used:		tsml_ajax_import()
function tsml_import_post_modified($data, $postarr) {
	if (!empty($postarr['post_modified'])) {
		$data['post_modified'] = $postarr['post_modified'];
	}
	if (!empty($postarr['post_modified_gmt'])) {
		$data['post_modified_gmt'] = $postarr['post_modified_gmt'];
	}
	return $data;
}

//function: turn "string" into string
//used:		tsml_ajax_import() inside array_map
function tsml_import_sanitize_field($value) {
	//preserve <br>s as line breaks if present, otherwise clean up
	$value = preg_replace('/\<br(\s*)?\/?\>/i', PHP_EOL, $value);
	$value = stripslashes($value);

	//turn "string" into string
	//$value = str_replace('""', '"', $value);
	$value = trim(trim($value, '"'));
	
	//fix newlines
	//$value = preg_split('/$\R?^/m', $value);
	//$value = array_map('trim', $value);
	//$value = trim(implode(PHP_EOL, $value));
	
	return $value;
}

//function: return an html link with current query string appended -- this is because query string permalink structure is an enormous pain in the ass
//used:		archive-meetings.php, single-locations.php, single-meetings.php
function tsml_link($url, $string, $exclude='', $class=false) {
	$appends = $_GET;
	if (array_key_exists($exclude, $appends)) unset($appends[$exclude]);
	if (!empty($appends)) {
		$url .= strstr($url, '?') ? '&' : '?';
		$url .= http_build_query($appends, '', '&amp;');
	}
	$return = '<a href="' . $url . '"';
	if ($class) $return .= ' class="' . $class . '"';
	$return .= '>' . $string . '</a>';
	return $return;
}

//function: set an option with the currently-used types
//used: 	tsml_import() and save.php
function tsml_update_types_in_use() {
	global $tsml_types_in_use, $wpdb;
	
	//shortcut to getting all meta values without getting all posts first
	$types = $wpdb->get_col('SELECT
			m.meta_value 
		FROM ' . $wpdb->postmeta . ' m
		JOIN ' . $wpdb->posts . ' p ON m.post_id = p.id
		WHERE p.post_type = "tsml_meeting" AND m.meta_key = "types" AND p.post_status = "publish"');
		
	//master array
	$all_types = array();
	
	//loop through results and append to master array
	foreach ($types as $type) {
		$type = unserialize($type);
		if (is_array($type)) $all_types = array_merge($all_types, $type);
	}
	
	//update global variable
	$tsml_types_in_use = array_unique($all_types);
	
	//set option value
	update_option('tsml_types_in_use', $tsml_types_in_use);
}

//function:	helper to debug out of memory errors
//used:		ad-hoc
function tsml_report_memory() {
	$size = memory_get_peak_usage(true);
	$units = array('B', 'KB', 'MB', 'GB');
	die(round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . $units[$i]);
}

//function:	sanitize a time field
//used:		save.php
function tsml_sanitize_time($string) {
	$string = sanitize_text_field($string);
	if ($time = strtotime($string)) return date('H:i', $time);
	return null;
}


//function: sort an array of meetings
//used: as a callback in tsml_get_meetings()
//method: sort by 
//	1) day, following "week starts on" user preference, with appointment meetings last, 
//	2) followed by time, where the day starts at 5am, 
//	3) followed by location name, 
//	4) followed by meeting name
function tsml_sort_meetings($a, $b) {
	global $tsml_days_order;
	$a_day_index = strlen($a['day']) ? array_search($a['day'], $tsml_days_order) : false;
	$b_day_index = strlen($b['day']) ? array_search($b['day'], $tsml_days_order) : false;
	if ($a_day_index === false && $b_day_index !== false) {
		return 1;
	} elseif ($a_day_index !== false && $b_day_index === false) {
		return -1;
	} elseif ($a_day_index != $b_day_index) {
		return $a_day_index - $b_day_index;
	} else {
		//days are the same or both null
		if ($a['time'] != $b['time']) {
			/*
			if (substr_count($a['time'], ':')) { //move meetings earlier than 5am to the end of the list
				$a_time = explode(':', $a['time'], 2);
				if (intval($a_time[0]) < 5) $a_time[0] = sprintf("%02d",  $a_time[0] + 24);
				$a_time = implode(':', $a_time);
			}
			if (substr_count($b['time'], ':')) { //move meetings earlier than 5am to the end of the list
				$b_time = explode(':', $b['time'], 2);
				if (intval($b_time[0]) < 5) $b_time[0] = sprintf("%02d",  $b_time[0] + 24);
				$b_time = implode(':', $b_time);
			}*/
			$a_time = ($a['time'] == '00:00') ? '23:59' : $a['time'];
			$b_time = ($b['time'] == '00:00') ? '23:59' : $b['time'];
			return strcmp($a_time, $b_time);
		} else {
			if ($a['location'] != $b['location']) {
				return strcmp($a['location'], $b['location']);
			} else {
				return strcmp($a['name'], $b['name']);
			}
		}
	}
}

//function:	tokenize string for the typeaheads
//used:		ajax functions
function tsml_string_tokens($string) {

	//shorten words that have quotes in them instead of splitting them
	$string = html_entity_decode($string);
	$string = str_replace("'", '', $string);
	$string = str_replace('’', '', $string);
	
	//remove everything that's not a letter or a number
	$string = preg_replace("/[^a-zA-Z 0-9]+/", ' ', $string);
	
	//return array
	return array_values(array_unique(array_filter(explode(' ', $string))));
}

//function:	run any outstanding database upgrades
//used: 	init.php (depends on constant set in 12-step-meeting-list.php)
function tsml_upgrades() {
	global $wpdb;

	$tsml_version = get_option('tsml_version');

	if ($tsml_version == TSML_VERSION) return;
	
	//populate new groups object with any locations that have contact information
	if (version_compare($tsml_version, '1.8.6', '<')) {

		//clear out old ones in case it crashed earlier
		if ($post_ids = implode(',', $wpdb->get_col('SELECT id FROM ' . $wpdb->posts . ' WHERE post_type = "tsml_group"'))) {
			$wpdb->query('DELETE FROM ' . $wpdb->posts . ' WHERE id IN (' . $post_ids . ')');
			$wpdb->query('DELETE FROM ' . $wpdb->postmeta . ' WHERE post_id IN (' . $post_ids . ')');
		}
		
		//build array of locations with meetings
		$locations = $group_names = array();
		$meetings = tsml_get_meetings();
		foreach ($meetings as $meeting) {
			if (!array_key_exists($meeting['location_id'], $locations)) {
				$locations[$meeting['location_id']] = array(
					'name' => $meeting['location'],
					'meetings' => array(),
				);
				$group_names[] = $meeting['location'];
			}
			$locations[$meeting['location_id']]['meetings'][] = $meeting['id'];
		}
		
		$group_names = array_unique($group_names);
		
		foreach ($locations as $location_id => $location) {
			$location_custom = get_post_meta($location_id);
			if (empty($location_custom['contact_1_name'][0]) &&
				empty($location_custom['contact_1_email'][0]) &&
				empty($location_custom['contact_1_phone'][0]) &&
				empty($location_custom['contact_2_name'][0]) &&
				empty($location_custom['contact_2_email'][0]) &&
				empty($location_custom['contact_2_phone'][0]) &&
				empty($location_custom['contact_3_name'][0]) &&
				empty($location_custom['contact_3_email'][0]) &&
				empty($location_custom['contact_3_phone'][0])) continue;

			//handle duplicate location names, hopefully this won't come up too much 
			$group_name = $location['name'];
			if (in_array($group_name, $group_names)) $group_name .= ' #' . $location_id;
			
			//create group
			$group_id = wp_insert_post(array(
			  	'post_type'		=> 'tsml_group',
			  	'post_status'	=> 'publish',
				'post_title'	=> $group_name,
			));
						
			//set contacts for group
			for ($i = 0; $i <= GROUP_CONTACT_COUNT; $i++) {
				foreach (array('name', 'email', 'phone') as $type) {
					$fieldname = 'contact_' . $i . '_' . $type;
					if (!empty($location_custom[$fieldname][0])) {
						update_post_meta($group_id, $fieldname, $location_custom[$fieldname][0]);
					}
				}
			}
			
			foreach ($location['meetings'] as $meeting_id) {
				update_post_meta($meeting_id, 'group_id', $group_id);
			}

		}
	}
	
	//clear old location contact details
	if (version_compare($tsml_version, '1.9', '<')) {
		$wpdb->query('DELETE FROM ' . $wpdb->postmeta . ' WHERE meta_key IN (
			"contact_1_name", "contact_1_email", "contact_1_phone", 
			"contact_2_name", "contact_2_email", "contact_2_phone",
			"contact_3_name", "contact_3_email", "contact_3_phone"
		) AND post_id IN (
			SELECT ID FROM ' . $wpdb->posts . ' WHERE post_type = "locations"
		)');
	}

	//database cleanup
	if (version_compare($tsml_version, '2.5.3', '<')) {

		//prefix entity names so as not to conflict with other plugins
		$wpdb->query('UPDATE ' . $wpdb->posts . ' SET post_type = "tsml_meeting" WHERE post_type = "meetings"');		
		$wpdb->query('UPDATE ' . $wpdb->posts . ' SET post_type = "tsml_location" WHERE post_type = "locations"');		
		$wpdb->query('UPDATE ' . $wpdb->term_taxonomy . ' SET taxonomy = "tsml_region" WHERE taxonomy = "region"');
		
		//make ", US" results back in to ", USA" results
		$wpdb->query('UPDATE ' . $wpdb->postmeta . ' SET meta_value = CONCAT(meta_value, "A") WHERE meta_key = "formatted_address" AND meta_value LIKE "%, US"');

		//clear out any taxonomy that's in there currently
		$wpdb->query('DELETE FROM ' . $wpdb->term_relationships . ' WHERE object_id IN (
			SELECT ID FROM ' . $wpdb->posts . ' WHERE post_type IN ("tsml_meeting", "tsml_location")
		)');
		
		//fetch all regions and move them over to taxonomies
		$locations = $wpdb->get_results('SELECT post_id, meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_key = "region" AND post_id IN (SELECT id FROM ' . $wpdb->posts . ' WHERE post_type = "tsml_location")');
		foreach ($locations as $location) {
			wp_set_object_terms($location->post_id, intval($location->meta_value), 'tsml_region');
		}

		//clear out old fields we're not using from meetings and locations
		$wpdb->query('DELETE FROM ' . $wpdb->postmeta . ' WHERE meta_key IN (
			"address", "city", "state", "postal_code", "country", "region"
		) AND post_id IN (
			SELECT ID FROM ' . $wpdb->posts . ' WHERE post_type IN ("tsml_meeting", "tsml_location")
		)');
	}
	
	//bug fix
	if (version_compare($tsml_version, '2.6.6', '<')) {
		$wpdb->query('UPDATE ' . $wpdb->posts . ' SET post_type = "tsml_group" WHERE post_type = "tsml_type_groups"');
	}

	flush_rewrite_rules();
	update_option('tsml_version', TSML_VERSION);
}