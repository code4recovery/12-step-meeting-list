<?php

//function:	add an admin screen update message
//used:		tsml_import() and admin_types.php
//$type:		can be success, warning or error
function tsml_alert($message, $type = 'success')
{
    echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . $message . '</p></div>';
}

//function: enqueue assets for public or admin page
//used: in templates and on admin_edit.php
function tsml_assets()
{
    global $post_type, $tsml_street_only, $tsml_programs, $tsml_strings, $tsml_program, $tsml_google_maps_key,
    $tsml_mapbox_key, $tsml_mapbox_theme, $tsml_distance_units, $tsml_defaults, $tsml_columns, $tsml_nonce;

    //google maps api
    if ($tsml_google_maps_key) {
        wp_enqueue_script('google_maps_api', '//maps.googleapis.com/maps/api/js?key=' . $tsml_google_maps_key);
    }

    if (is_admin()) {
        //dashboard page assets
        wp_enqueue_style('tsml_admin', plugins_url('../assets/css/admin.min.css', __FILE__), [], TSML_VERSION);
        wp_enqueue_script('tsml_admin', plugins_url('../assets/js/admin.min.js', __FILE__), ['jquery'], TSML_VERSION, true);
        wp_localize_script('tsml_admin', 'tsml', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'debug' => WP_DEBUG,
            'google_maps_key' => $tsml_google_maps_key, //to see if map should have been called
            'mapbox_key' => $tsml_mapbox_key,
            'mapbox_theme' => $tsml_mapbox_theme,
            'nonce' => wp_create_nonce($tsml_nonce),
        ]);
    } else {
        //public page assets
        global $post;

        wp_enqueue_style('tsml_public', plugins_url('../assets/css/public.min.css', __FILE__), [], TSML_VERSION);
        wp_enqueue_script('jquery_validate', plugins_url('../assets/js/jquery.validate.min.js', __FILE__), ['jquery'], TSML_VERSION, true);
        wp_enqueue_script('tsml_public', plugins_url('../assets/js/public.min.js', __FILE__), ['jquery'], TSML_VERSION, true);
        wp_enqueue_script('jquery-ui-autocomplete');
        wp_localize_script('tsml_public', 'tsml', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'columns' => array_keys($tsml_columns),
            'days' => [
                __('Sunday', '12-step-meeting-list'),
                __('Monday', '12-step-meeting-list'),
                __('Tuesday', '12-step-meeting-list'),
                __('Wednesday', '12-step-meeting-list'),
                __('Thursday', '12-step-meeting-list'),
                __('Friday', '12-step-meeting-list'),
                __('Saturday', '12-step-meeting-list'),
            ],
            'debug' => WP_DEBUG,
            'defaults' => $tsml_defaults,
            'distance_units' => $tsml_distance_units,
            'flags' => $tsml_programs[$tsml_program]['flags'],
            'google_maps_key' => $tsml_google_maps_key, //to see if map should have been called
            'mapbox_key' => $tsml_mapbox_key,
            'mapbox_theme' => $tsml_mapbox_theme,
            'nonce' => wp_create_nonce($tsml_nonce),
            'program' => empty($tsml_programs[$tsml_program]['abbr']) ? $tsml_programs[$tsml_program]['name'] : $tsml_programs[$tsml_program]['abbr'],
            'street_only' => $tsml_street_only,
            'strings' => $tsml_strings,
            'types' => empty($tsml_programs[$tsml_program]['types']) ? [] : $tsml_programs[$tsml_program]['types'],
            'meeting_id' => isset($post->ID) ? $post->ID : '',
        ]);
    }
}

//set geo boundaries from current data (for biased geocoding)
function tsml_bounds()
{
    global $wpdb, $tsml_bounds;

    //get north & south
    $latitudes = $wpdb->get_row('SELECT
			MAX(m.meta_value) north,
			MIN(m.meta_value) south
		FROM ' . $wpdb->postmeta . ' m
		JOIN ' . $wpdb->posts . ' p ON p.ID = m.post_id
		WHERE m.meta_key = "latitude" AND p.post_type = "tsml_location"');

    //get east & west
    $longitudes = $wpdb->get_row('SELECT
			MAX(m.meta_value) west,
			MIN(m.meta_value) east
		FROM ' . $wpdb->postmeta . ' m
		JOIN ' . $wpdb->posts . ' p ON p.ID = m.post_id
		WHERE m.meta_key = "longitude" AND p.post_type = "tsml_location"');

    //if results, get bounding box and cache it
    if ($latitudes && $longitudes) {

        //add 25% margin to the bounds
        $width = ($longitudes->east - $longitudes->west) / 25;
        $height = ($latitudes->north - $latitudes->south) / 25;

        $tsml_bounds = [
            'north' => $latitudes->north + $height,
            'east' => $longitudes->east + $width,
            'south' => $latitudes->south - $height,
            'west' => $longitudes->west - $width,
        ];

        update_option('tsml_bounds', $tsml_bounds);
    }
}

//try to build a cache of meetings to help with CPU load
function tsml_cache_rebuild()
{
    // flush wp object cache
    wp_cache_flush();

    // rebuild TSML meeting cache file
    tsml_get_meetings([], false);
}

//function: calculate attendance option given types and address
// called in tsml_get_meetings()
function tsml_calculate_attendance_option($types, $approximate)
{
    $attendance_option = '';

    // Handle when the types list is empty, this prevents PHP warnings
    if (empty($types)) $types = [];

    if (in_array('TC', $types) && in_array('ONL', $types)) {
        // Types has both Location Temporarily Closed and Online, which means it should be an online meeting
        $attendance_option = 'online';
    } elseif (in_array('TC', $types)) {
        // Types has Location Temporarily Closed, but not online, which means it really is temporarily closed
        $attendance_option = 'inactive';
    } elseif (in_array('ONL', $types)) {
        // Types has Online, but not Temp closed, which means it's a hybrid (or online)
        $attendance_option = 'hybrid';
        if ($approximate == 'yes') {
            $attendance_option = 'online';
        }
    } else {
        // Neither Online or Temp Closed, which means it's in person (or inactive)
        $attendance_option = 'in_person';
        if ($approximate == 'yes') {
            $attendance_option = 'inactive';
        }
    }

    return $attendance_option;
}

//called by register_activation_hook in 12-step-meeting-list.php
//hands off to tsml_custom_post_types
function tsml_plugin_activation()
{
    tsml_custom_post_types();
    flush_rewrite_rules();
}

//called by register_deactivation_hook in 12-step-meeting-list.php
//clean up custom taxonomies / post types and flush rewrite rules
function tsml_plugin_deactivation()
{
    if (taxonomy_exists('tsml_region')) {
        unregister_taxonomy('tsml_region');
    }
    if (taxonomy_exists('tsml_location')) {
        unregister_taxonomy('tsml_location');
    }
    if (taxonomy_exists('tsml_district')) {
        unregister_taxonomy('tsml_district');
    }
    if (post_type_exists('tsml_meeting')) {
        unregister_post_type('tsml_meeting');
    }
    if (post_type_exists('tsml_location')) {
        unregister_post_type('tsml_location');
    }
    if (post_type_exists('tsml_group')) {
        unregister_post_type('tsml_group');
    }
    flush_rewrite_rules();
}

//validate conference provider and return name
function tsml_conference_provider($url)
{
    global $tsml_conference_providers;
    if (empty($tsml_conference_providers)) return true; //don't provide validation
    $domains = array_keys($tsml_conference_providers);
    $url_parts = parse_url($url);
    foreach ($domains as $domain) {
        if (tsml_string_ends($url_parts['host'], $domain)) {
            return $tsml_conference_providers[$domain];
        }
    }
    return false;
}

//function: get an array of conference provider names
//used:		meeting edit screen
function tsml_conference_providers()
{
    global $tsml_conference_providers;
    if (empty($tsml_conference_providers)) return [];
    $providers = array_unique(array_values($tsml_conference_providers));
    natcasesort($providers);
    return $providers;
}

//function:	return integer number of live groups
//used:		shortcode, admin-import.php, tsml_ajax_import()
function tsml_count_groups()
{
    return count(tsml_get_all_groups('publish'));
}

//function:	return integer number of live locations
//used:		shortcode, admin-import.php, tsml_ajax_import()
function tsml_count_locations()
{
    return count(tsml_get_all_locations('publish'));
}

//function:	return integer number of live meetings
//used:		shortcode, admin-import.php, tsml_ajax_import()
function tsml_count_meetings()
{
    return count(tsml_get_all_meetings('publish'));
}

//function:	return integer number of live regions
//used:		shortcode, admin-import.php, tsml_ajax_import()
function tsml_count_regions()
{
    return count(tsml_get_all_regions());
}

//function:	add local overrides to google (this may someday be removed)
//used:		in user themes
function tsml_custom_addresses($custom_overrides)
{
    global $tsml_google_overrides;
    $tsml_google_overrides = array_merge($tsml_google_overrides, $custom_overrides);
}

//fuction:	define custom type descriptions
//used:		theme's functions.php
function tsml_custom_descriptions($descriptions)
{
    global $tsml_programs, $tsml_program;
    $tsml_programs[$tsml_program]['type_descriptions'] = $descriptions;
}

//fuction:	define custom flags (/men, /women) for your area
//used:		theme's functions.php
function tsml_custom_flags($flags)
{
    global $tsml_programs, $tsml_program;
    $tsml_programs[$tsml_program]['flags'] = $flags;
}

//function: register custom post types
//used: 	init.php on every request, also in change_activation_state() for plugin activation or deactivation
function tsml_custom_post_types()
{
    global $tsml_slug;

    $is_public = !empty($tsml_slug);

    register_taxonomy('tsml_region', 'tsml_location', [
        'labels' => [
            'name' => __('Regions', '12-step-meeting-list'),
            'singular_name' => __('Region', '12-step-meeting-list'),
            'menu_name' => __('Regions', '12-step-meeting-list'),
            'all_items' => __('All Regions', '12-step-meeting-list'),
            'edit_item' => __('Edit Region', '12-step-meeting-list'),
            'view_item' => __('View Region', '12-step-meeting-list'),
            'update_item' => __('Update Region', '12-step-meeting-list'),
            'add_new_item' => __('Add New Region', '12-step-meeting-list'),
            'new_item_name' => __('New Region', '12-step-meeting-list'),
            'parent_item' => __('Parent Region', '12-step-meeting-list'),
            'parent_item_colon' => __('Parent Region:', '12-step-meeting-list'),
            'search_items' => __('Search Regions', '12-step-meeting-list'),
            'popular_items' => __('Popular Regions', '12-step-meeting-list'),
            'not_found' => __('No regions found.', '12-step-meeting-list'),
        ],
        'hierarchical' => true,
        'public' => false,
        'show_ui' => true,
    ]);

    register_taxonomy('tsml_district', 'tsml_group', [
        'labels' => [
            'name' => __('District', '12-step-meeting-list'),
            'singular_name' => __('District', '12-step-meeting-list'),
            'menu_name' => __('District', '12-step-meeting-list'),
            'all_items' => __('All Districts', '12-step-meeting-list'),
            'edit_item' => __('Edit District', '12-step-meeting-list'),
            'view_item' => __('View District', '12-step-meeting-list'),
            'update_item' => __('Update District', '12-step-meeting-list'),
            'add_new_item' => __('Add New District', '12-step-meeting-list'),
            'new_item_name' => __('New District', '12-step-meeting-list'),
            'parent_item' => __('Parent Area', '12-step-meeting-list'),
            'parent_item_colon' => __('Parent Area:', '12-step-meeting-list'),
            'search_items' => __('Search Districts', '12-step-meeting-list'),
            'popular_items' => __('Popular Districts', '12-step-meeting-list'),
            'not_found' => __('No districts found.', '12-step-meeting-list'),
        ],
        'hierarchical' => true,
        'public' => false,
        'show_ui' => true,
    ]);

    register_post_type(
        'tsml_meeting',
        [
            'labels' => [
                'name' => __('Meetings', '12-step-meeting-list'),
                'singular_name' => __('Meeting', '12-step-meeting-list'),
                'not_found' => __('No meetings added yet.', '12-step-meeting-list'),
                'add_new' => __('Add New Meeting', '12-step-meeting-list'),
                'search_items' => __('Search Meetings', '12-step-meeting-list'),
                'edit_item' => __('Edit Meeting', '12-step-meeting-list'),
                'view_item' => __('View Meeting', '12-step-meeting-list'),
            ],
            'supports' => ['title', 'author'],
            'public' => $is_public,
            'show_ui' => true,
            'has_archive' => $is_public,
            'menu_icon' => 'dashicons-groups',
            'rewrite' => ['slug' => $tsml_slug, 'with_front' => apply_filters('tsml_meeting_with_front', true)],
        ]
    );

    register_post_type(
        'tsml_location',
        [
            'labels' => [
                'name' => __('Locations', '12-step-meeting-list'),
                'singular_name' => __('Location', '12-step-meeting-list'),
                'menu_name' => __('Locations', '12-step-meeting-list'),
                'all_items' => __('All Locations', '12-step-meeting-list'),
                'edit_item' => __('Edit Location', '12-step-meeting-list'),
                'view_item' => __('View Location', '12-step-meeting-list'),
                'update_item' => __('Update Location', '12-step-meeting-list'),
                'add_new_item' => __('Add New Location', '12-step-meeting-list'),
                'new_item_name' => __('New Location', '12-step-meeting-list'),
                'parent_item' => __('Parent Location', '12-step-meeting-list'),
                'parent_item_colon' => __('Parent Location:', '12-step-meeting-list'),
                'search_items' => __('Search Locations', '12-step-meeting-list'),
                'popular_items' => __('Popular Locations', '12-step-meeting-list'),
                'not_found' => __('No locations found.', '12-step-meeting-list'),
            ],
            'supports' => ['title'],
            'public' => $is_public,
            'show_ui' => false,
            'has_archive' => $is_public,
            'capabilities' => ['create_posts' => false],
            'rewrite' => ['slug' => 'locations'],
            'taxonomies' => ['tsml_region'],
        ]
    );

    register_post_type(
        'tsml_group',
        [
            'labels' => [
                'name' => __('Groups', '12-step-meeting-list'),
                'singular_name' => __('Group', '12-step-meeting-list'),
                'menu_name' => __('Groups', '12-step-meeting-list'),
                'all_items' => __('All Groups', '12-step-meeting-list'),
                'edit_item' => __('Edit Group', '12-step-meeting-list'),
                'view_item' => __('View Group', '12-step-meeting-list'),
                'update_item' => __('Update Group', '12-step-meeting-list'),
                'add_new_item' => __('Add New Group', '12-step-meeting-list'),
                'new_item_name' => __('New Group', '12-step-meeting-list'),
                'parent_item' => __('Parent Group', '12-step-meeting-list'),
                'parent_item_colon' => __('Parent Group:', '12-step-meeting-list'),
                'search_items' => __('Search Groups', '12-step-meeting-list'),
                'popular_items' => __('Popular Groups', '12-step-meeting-list'),
                'not_found' => __('No groups found.', '12-step-meeting-list'),
            ],
            'supports' => ['title'],
            'public' => true,
            'show_ui' => false,
            'has_archive' => false,
            'capabilities' => ['create_posts' => false],
        ]
    );
}

//fuction:	define custom meeting types for your area
//used:		theme's functions.php
function tsml_custom_types($types)
{
    global $tsml_programs, $tsml_program;
    foreach ($types as $key => $value) {
        $tsml_programs[$tsml_program]['types'][$key] = $value;
    }
    asort($tsml_programs[$tsml_program]['types']);
}

// function used for debugging
function tsml_dd($obj)
{
    echo '<pre>';
    print_r($obj);
    echo '</pre>';
    exit;
}

//function:	efficiently remove an array of post_ids
//used:		tsml_delete_orphans(), admin-import.php
function tsml_delete($post_ids)
{
    global $wpdb;

    //special case
    if ($post_ids == 'everything') {

        $post_ids = get_posts([
            'post_type' => ['tsml_meeting', 'tsml_location', 'tsml_group'],
            'post_status' => 'any',
            'fields' => 'ids',
            'numberposts' => -1,
        ]);

        //when we're deleting *everything*, also delete regions & districts
        if ($term_ids = implode(',', $wpdb->get_col('SELECT term_id FROM ' . $wpdb->term_taxonomy . ' WHERE taxonomy IN ("tsml_district", "tsml_region")'))) {
            $wpdb->query('DELETE FROM ' . $wpdb->terms . ' WHERE term_id IN (' . $term_ids . ')');
            $wpdb->query('DELETE FROM ' . $wpdb->term_taxonomy . ' WHERE term_id IN (' . $term_ids . ')');
        }
    }

    if (empty($post_ids) || !is_array($post_ids)) return;

    //sanitize
    $post_ids = array_map('intval', $post_ids);
    $post_ids = array_unique($post_ids);
    $post_ids = implode(', ', $post_ids);

    //run deletes
    $wpdb->query('DELETE FROM ' . $wpdb->posts . ' WHERE ID IN (' . $post_ids . ')');
    $wpdb->query('DELETE FROM ' . $wpdb->postmeta . ' WHERE post_id IN (' . $post_ids . ')');
    $wpdb->query('DELETE FROM ' . $wpdb->term_relationships . ' WHERE object_id IN (' . $post_ids . ')');

    //rebuild cache
    tsml_cache_rebuild();
}

//function: efficiently deletes all orphaned locations and groups (have no meetings associated)
//used:		save_post filter
function tsml_delete_orphans()
{
    global $wpdb;
    $location_ids = $wpdb->get_col('SELECT l.ID FROM ' . $wpdb->posts . ' l WHERE l.post_type = "tsml_location" AND (SELECT COUNT(*) FROM ' . $wpdb->posts . ' m WHERE m.post_type="tsml_meeting" AND m.post_parent = l.id) = 0');
    $group_ids = $wpdb->get_col('SELECT g.ID FROM ' . $wpdb->posts . ' g WHERE g.post_type = "tsml_group" AND (SELECT COUNT(*) FROM ' . $wpdb->postmeta . ' m WHERE m.meta_key="group_id" AND m.meta_value = g.id) = 0');
    tsml_delete(array_merge($location_ids, $group_ids));

    //edge case: draft-ify locations with only unpublished meetings
    $location_ids = $wpdb->get_col('SELECT l.ID FROM ' . $wpdb->posts . ' l
		WHERE l.post_type = "tsml_location" AND
			(SELECT COUNT(*) FROM ' . $wpdb->posts . ' m
			WHERE m.post_type="tsml_meeting" AND m.post_status="publish" AND m.post_parent = l.id) = 0');
    if (count($location_ids)) {
        $wpdb->query('UPDATE ' . $wpdb->posts . ' l SET l.post_status = "draft" WHERE ID IN (' . implode(', ', $location_ids) . ')');
    }
}

//echo a property if it exists (used on admin_meeting.php)
function tsml_echo($object, $property)
{
    if (!empty($object->{$property})) {
        echo $object->{$property};
    }
}

//send a nice-looking email (used by tsml_ajax_feedback() and save.php (change notifications)
function tsml_email($to, $subject, $message, $reply_to = false)
{

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    if ($reply_to) $headers[] = 'Reply-To: ' . $reply_to;

    //prepend subject as h1
    $message = '<h1>' . $subject . '</h1>' . $message;

    //inline styles where necessary
    $message = str_replace('<h1>', '<h1 style="margin: 0; font-weight:bold; font-size:24px;">', $message);
    $message = str_replace('<hr>', '<hr style="margin: 15px 0; border: 0; height: 1px; background: #cccccc;">', $message);
    $message = str_replace('<p>', '<p style="margin: 1em 0;">', $message);
    $message = str_replace('<a ', '<a style="color: #6699cc; text-decoration: underline;" ', $message);

    //wrap message in email-compliant html
    $message = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
		<title>' . $subject . '</title>
		<style type="text/css">
		</style>
	</head>
	<body style="width:100% !important; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; margin:0; padding:0; background-color:#eeeeee;">
		<table cellpadding="0" cellspacing="0" border="0" style="background-color:#eeeeee; width:100%; height:100%;">
			<tr>
				<td valign="top" style="text-align:center;padding-top:15px;">
					<table cellpadding="0" cellspacing="0" border="0" align="center">
						<tr>
							<td width="630" valign="top" style="background-color:#ffffff; text-align:left; padding:15px; font-size:15px; font-family:Arial, sans-serif;">
								' . $message . '
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</body>
</html>';

    return wp_mail($to, '[' . get_bloginfo('name') . '] ' . $subject, $message, $headers);
}

//take a full address and return it formatted for the front-end
//used on template pages
function tsml_format_address($formatted_address, $street_only = false)
{
    $parts = explode(',', esc_attr($formatted_address));
    $parts = array_map('trim', $parts);
    if (in_array(end($parts), ['USA', 'US'])) {
        array_pop($parts);
        if (count($parts) > 1) {
            $state_zip = array_pop($parts);
            $parts[count($parts) - 1] .= ', ' . $state_zip;
        }
    }
    if ($street_only) return array_shift($parts);
    return implode('<br>', $parts);
}

//function: takes 0, 18:30 and returns Sunday, 6:30 pm (depending on your settings)
//used:		admin_edit.php, archive-meetings.php, single-meetings.php
function tsml_format_day_and_time($day, $time, $separator = ', ', $short = false)
{
    global $tsml_days;
    /* translators: Appt is abbreviation for Appointment */
    if (empty($tsml_days[$day]) || empty($time)) return $short ? __('Appt', '12-step-meeting-list') : __('Appointment', '12-step-meeting-list');
    return ($short ? substr($tsml_days[$day], 0, 3) : $tsml_days[$day]) . $separator . '<time>' . tsml_format_time($time) . '</time>';
}

//function:	appends men or women (or custom flags) if type present
//used:		archive-meetings.php
function tsml_format_name($name, $types = null)
{
    global $tsml_program, $tsml_programs;
    if (!is_array($types)) $types = [];
    if (empty($tsml_programs[$tsml_program]['flags']) || !is_array($tsml_programs[$tsml_program]['flags'])) return $name;
    $append = [];
    $meeting_is_online = in_array('ONL', $types);
    // Types assigned to the meeting passed to the function
    foreach ($types as $type) {
        // True if the type for the meeting exists in one of the predetermined flags
        $type_is_flagged = in_array($type, $tsml_programs[$tsml_program]['flags']);
        $type_not_tc_and_online = !($type === 'TC' && $meeting_is_online);

        if ($type_is_flagged && $type_not_tc_and_online) {
            $append[] = $tsml_programs[$tsml_program]['types'][$type];
        }
    }
    return count($append) ? $name . ' <small>' . implode(', ', $append) . '</small>' : $name;
}

//function:	get meeting types
//used:		archive-meetings.php
function tsml_format_types($types = [])
{
    global $tsml_program, $tsml_programs;
    if (!is_array($types)) $types = [];
    $append = [];
    // Types assigned to the meeting passed to the function
    foreach ($types as $type) {
        // True if the type for the meeting exists in one of the predetermined flags
        $type_is_flagged = in_array($type, $tsml_programs[$tsml_program]['flags']);

        if ($type_is_flagged && $type != 'TC' && $type != 'ONL') {
            $append[] = $tsml_programs[$tsml_program]['types'][$type];
        }
    }

    return implode(', ', $append);
}

//function: takes 18:30 and returns 6:30 pm (depending on your settings)
//used:		tsml_get_meetings(), single-meetings.php, admin_lists.php
function tsml_format_time($string, $empty = 'Appointment')
{
    if (empty($string)) return empty($empty) ? '' : __($empty, '12-step-meeting-list');
    if ($string == '12:00') return __('Noon', '12-step-meeting-list');
    if ($string == '23:59' || $string == '00:00') return __('Midnight', '12-step-meeting-list');
    $date = strtotime($string);
    return date(get_option('time_format'), $date);
}

//function: takes a time string, eg 6:30 pm, and returns 18:30
//used:		tsml_import(), tsml_time_duration()
function tsml_format_time_reverse($string)
{
    $time_parts = date_parse($string);
    return sprintf('%02d', $time_parts['hour']) . ':' . sprintf('%02d', $time_parts['minute']);
}

//function: takes a website URL, eg https://www.groupname.org and returns the domain
//used:		single-meetings.php
function tsml_format_domain($url)
{
    $parts = parse_url(strtolower($url));
    if (!$parts) return $url;
    if (substr($parts['host'], 0, 4) == 'www.') return substr($parts['host'], 4);
    return $parts['host'];
}

//function: display meeting list on home page (must be set to a static page)
//used:		by themes that want it, such as https://github.com/code4recovery/one-page-meeting-list
function tsml_front_page($wp_query)
{
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

//function: request accurate address information from google
//used:		tsml_ajax_import(), tsml_ajax_geocode()
function tsml_geocode($address)
{
    global $tsml_google_overrides;

    //check overrides first before anything
    if (array_key_exists($address, $tsml_google_overrides)) {
        if (empty($tsml_google_overrides[$address]['approximate'])) {
            $tsml_google_overrides[$address]['approximate'] = 'no';
        }
        return $tsml_google_overrides[$address];
    }

    //check cache
    $addresses = tsml_get_option_array('tsml_addresses');

    //if key exists && approximate is set for that address, return it
    if (array_key_exists($address, $addresses) && !empty($addresses[$address]['approximate'])) {
        $addresses[$address]['status'] = 'cache';
        return $addresses[$address];
    }

    $response = tsml_geocode_google($address);

    //Return if the status is error
    if ($response['status'] == 'error') {
        return $response;
    }

    //cache result
    $addresses[$address] = $response;
    $addresses[$response['formatted_address']] = $response;
    update_option('tsml_addresses', $addresses);

    return $response;
}

//function: Call Google for geocoding of the address
function tsml_geocode_google($address)
{
    global $tsml_curl_handle, $tsml_language, $tsml_google_overrides, $tsml_bounds, $tsml_google_geocoding_key;

    // Can't Geocode an empty address
    if (empty($address)) {
        return [
            'status' => 'error',
            'reason' => 'Addres string was empty',
        ];
    }

    //initialize curl handle if necessary
    if (!$tsml_curl_handle) {
        $tsml_curl_handle = curl_init();
        curl_setopt_array($tsml_curl_handle, [
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
    }

    //user can specify their own geocoding key in functions.php
    $key = !empty($tsml_google_geocoding_key) ? $tsml_google_geocoding_key : 'AIzaSyDm-pU-DlU-WsTkXJPGEVowY2hICRFLNeQ';

    //start list of options for geocoding request
    $options = [
        'key' => $key,
        'address' => $address,
        'language' => $tsml_language,
    ];

    //bias the viewport if we know the bounds
    if ($tsml_bounds) {
        $options['bounds'] = $tsml_bounds['south'] . ',' . $tsml_bounds['west'] . '|' . $tsml_bounds['north'] . ',' . $tsml_bounds['east'];
    }

    //send request to google
    curl_setopt($tsml_curl_handle, CURLOPT_URL, 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query($options));
    curl_setopt($tsml_curl_handle, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($tsml_curl_handle);

    //could not connect error
    if ($result === false) {
        $error = curl_error($tsml_curl_handle);
        tsml_log('geocode_connection_error', $error, $address);
        return [
            'status' => 'error',
            'reason' => 'Google could not validate the address <code>' . $address . '</code>. Response was <code>' . $error . '</code>',
        ];
    }

    //decode result
    $data = json_decode($result);

    //if over query limit, wait two seconds and retry, or then exit
    if ($data->status === 'OVER_QUERY_LIMIT') {
        sleep(2);
        $result = curl_exec($tsml_curl_handle);

        //could not connect error
        if ($result === false) {
            return [
                'status' => 'error',
                'reason' => 'Google could not validate the address <code>' . $address . '</code>. Response was <code>' . curl_error($tsml_curl_handle) . '</code>',
            ];
        }

        //decode result
        $data = json_decode($result);

        //if we're still over the limit, stop
        if ($data->status === 'OVER_QUERY_LIMIT') {
            tsml_log('geocode_error', 'OVER_QUERY_LIMIT', $address);
            return [
                'status' => 'error',
                'reason' => 'We are over the rate limit for the Google Geocoding API.'
            ];
        }
    }

    //if there are no results report it
    if ($data->status === 'ZERO_RESULTS') {
        tsml_log('geocode_error', 'ZERO_RESULTS', $address);
        return [
            'status' => 'error',
            'reason' => 'Google could not validate the address <code>' . $address . '</code>',
        ];
    }

    //if result is otherwise bad, stop
    if (($data->status !== 'OK') || empty($data->results[0]->formatted_address)) {
        tsml_log('geocode_error', $data->status, $address);
        return [
            'status' => 'error',
            'reason' => 'Google gave an unexpected response for address <code>' . $address . '</code>. Response was <pre>' . var_export($data, true) . '</pre>',
        ];
    }

    //check our overrides array again in case google is wrong
    if (array_key_exists($data->results[0]->formatted_address, $tsml_google_overrides)) {
        $response = $tsml_google_overrides[$data->results[0]->formatted_address];
        if (empty($response['approximate'])) {
            $response['approximate'] = 'no';
        }
    } else {
        tsml_log('geocode_success', $data->results[0]->formatted_address, $address);
        //start building response
        $response = [
            'formatted_address' => $data->results[0]->formatted_address,
            'latitude' => $data->results[0]->geometry->location->lat,
            'longitude' => $data->results[0]->geometry->location->lng,
            'approximate' => ($data->results[0]->geometry->location_type === 'APPROXIMATE') ? 'yes' : 'no',
            'city' => null,
            'status' => 'geocode',
        ];

        //get city, we might need it for the region, and we are going to cache it
        foreach ($data->results[0]->address_components as $component) {
            if (in_array('locality', $component->types)) {
                $response['city'] = $component->short_name;
            }
        }
    }

    return $response;
}

//function: get all locations in the system
//used:		tsml_group_count()
function tsml_get_all_groups($status = 'any')
{
    return get_posts([
        'post_type' => 'tsml_group',
        'post_status' => $status,
        'numberposts' => -1,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);
}

//function: get all locations in the system
//used:		tsml_location_count(), tsml_import(), and admin_import.php
function tsml_get_all_locations($status = 'any')
{
    return get_posts([
        'post_type' => 'tsml_location',
        'post_status' => $status,
        'numberposts' => -1,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);
}

//function: get all meetings in the system
//used:		tsml_meeting_count(), tsml_import(), and admin_import.php
function tsml_get_all_meetings($status = 'any')
{
    return get_posts([
        'post_type' => 'tsml_meeting',
        'post_status' => $status,
        'numberposts' => -1,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);
}

//function: get all regions in the system
//used:		tsml_region_count(), tsml_import() and admin_import.php
function tsml_get_all_regions()
{
    return get_terms('tsml_region', ['fields' => 'ids', 'hide_empty' => false]);
}

//function: get meeting ids for a data source
//used:		tsml_ajax_import, import/settings page
function tsml_get_data_source_ids($source)
{
    return get_posts([
        'post_type' => 'tsml_meeting',
        'numberposts' => -1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => 'data_source',
                'value' => $source,
                'compare' => '=',
            ]
        ],
    ]);
}

//function: get all locations with full location information
//used: tsml_get_meetings()
function tsml_get_groups()
{
    global $tsml_contact_fields;

    $groups = array();

    # Get all districts with parents, need for sub_district below
    $districts = $districts_with_parents = [];
    $terms = get_categories(['taxonomy' => 'tsml_district']);
    foreach ($terms as $term) {
        $districts[$term->term_id] = $term->name;
        if ($term->parent) $districts_with_parents[$term->term_id] = $term->parent;
    }

    # Get all locations
    $posts = tsml_get_all_groups('publish');

    # Much faster than doing get_post_meta() over and over
    $group_meta = tsml_get_meta('tsml_group');

    # Make an array of all groups
    foreach ($posts as $post) {

        $district_id = !empty($group_meta[$post->ID]['district_id']) ? $group_meta[$post->ID]['district_id'] : null;
        if (array_key_exists($district_id, $districts_with_parents)) {
            $district = $districts[$districts_with_parents[$district_id]];
            $sub_district = $districts[$district_id];
        } else {
            $district = !empty($districts[$district_id]) ? $districts[$district_id] : '';
            $sub_district = null;
        }

        $groups[$post->ID] = [
            'group_id' => $post->ID, //so as not to conflict with another id when combined
            'group' => $post->post_title,
            'district' => $district,
            'district_id' => $district_id,
            'sub_district' => $sub_district,
            'group_notes' => $post->post_content,
        ];

        foreach ($tsml_contact_fields as $field => $type) {
            $groups[$post->ID][$field] = empty($group_meta[$post->ID][$field]) ? null : $group_meta[$post->ID][$field];
        }
    }

    return $groups;
}

//function: template tag to get location, attach custom fields to it
//$location_id can be false if there is a global post, eg on the single-locations template page
//used: single-locations.php
function tsml_get_location($location_id = false)
{
    if (!$location = get_post($location_id)) return;
    if ($custom = get_post_meta($location->ID)) {
        foreach ($custom as $key => $value) {
            $location->{$key} = htmlentities($value[0], ENT_QUOTES);
        }
    }
    $location->post_title = htmlentities($location->post_title, ENT_QUOTES);
    $location->notes = esc_html($location->post_content);
    if ($region = get_the_terms($location, 'tsml_region')) {
        $location->region_id = $region[0]->term_id;
        $location->region = $region[0]->name;
    }

    //directions link (obsolete 4/15/2018, keeping for compatibility)
    $location->directions = 'https://maps.google.com/maps/dir/?api=1&' . http_build_query([
        'destination' => $location->latitude . ',' . $location->longitude,
    ]);

    return $location;
}

//function: get all locations with full location information
//used: tsml_import(), tsml_get_meetings(), admin_edit
function tsml_get_locations()
{
    $locations = [];

    # Get all regions with parents, need for sub_region below
    $regions = $regions_with_parents = [];
    $terms = get_categories(['taxonomy' => 'tsml_region']);
    foreach ($terms as $term) {
        $regions[$term->term_id] = $term->name;
        if ($term->parent) $regions_with_parents[$term->term_id] = $term->parent;
    }

    # Get all locations
    $posts = tsml_get_all_locations(['publish', 'draft']);

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

        $locations[$post->ID] = [
            'location_id' => $post->ID, //so as not to conflict with another id when combined
            'location' => $post->post_title,
            'location_notes' => $post->post_content,
            'location_url' => get_permalink($post->ID),
            'formatted_address' => empty($location_meta[$post->ID]['formatted_address']) ? null : $location_meta[$post->ID]['formatted_address'],
            'approximate' => empty($location_meta[$post->ID]['approximate']) ? null : $location_meta[$post->ID]['approximate'],
            'latitude' => empty($location_meta[$post->ID]['latitude']) ? null : $location_meta[$post->ID]['latitude'],
            'longitude' => empty($location_meta[$post->ID]['longitude']) ? null : $location_meta[$post->ID]['longitude'],
            'region_id' => $region_id,
            'region' => $region,
            'sub_region' => $sub_region,
        ];

        // regions array eg ['Midwest', 'Illinois', 'Chicago', 'North Side']
        $regions_array = array_filter(array_map(function ($region_id) {
            $term = get_term($region_id, 'tsml_region');
            return !empty($term->name) ? $term->name : null;
        }, array_merge([$region_id], get_ancestors($region_id, 'tsml_region'))), function ($region) {
            return !empty($region);
        });

        // omit key if empty
        if (count($regions_array)) {
            $locations[$post->ID]['regions'] = array_reverse($regions_array);
        }
    }

    return $locations;
}


//function: template tag to get meeting and location, attach custom fields to it
//$meeting_id can be false if there is a global $post object, eg on the single meeting template page
//used: single-meetings.php
function tsml_get_meeting($meeting_id = false)
{
    global $tsml_program, $tsml_programs, $tsml_contact_fields;

    $meeting = get_post($meeting_id);
    $custom = get_post_meta($meeting->ID);

    //add optional location information
    if ($meeting->post_parent) {
        $location = get_post($meeting->post_parent);
        $meeting->location_id = $location->ID;
        $custom = array_merge($custom, get_post_meta($location->ID));
        $meeting->location = htmlentities($location->post_title, ENT_QUOTES);
        $meeting->location_notes = esc_html($location->post_content);
        if ($region = get_the_terms($location, 'tsml_region')) {
            $meeting->region_id = $region[0]->term_id;
            $meeting->region = $region[0]->name;
        }

        //get other meetings at this location
        $meeting->location_meetings = tsml_get_meetings(['location_id' => $location->ID]);

        //directions link (obsolete 4/15/2018, keeping for compatibility)
        $meeting->directions = 'https://maps.google.com/maps/dir/?api=1&' . http_build_query([
            'destination' => $location->latitude . ',' . $location->longitude,
        ]);
        $meeting->approximate = $location->approximate;
    }

    if (empty($meeting->approximate)) $meeting->approximate = 'no';

    //escape meeting values
    $meeting->types = [];
    foreach ($custom as $key => $value) {
        if (is_array($value)) {
            $value = count($value) ? $value[0] : '';
        }
        if ('types' === $key) {
            $value = (array) maybe_unserialize($value);
        } else {
            $value = htmlentities(strval($value), ENT_QUOTES);
        }
        $meeting->{$key} = $value;
    }
    $meeting->post_title = htmlentities($meeting->post_title, ENT_QUOTES);
    $meeting->notes = esc_html($meeting->post_content);

    //type description? (todo support multiple)
    if (!empty($tsml_programs[$tsml_program]['type_descriptions'])) {
        $types_with_descriptions = array_intersect($meeting->types, array_keys($tsml_programs[$tsml_program]['type_descriptions']));
        foreach ($types_with_descriptions as $type) {
            $meeting->type_description = $tsml_programs[$tsml_program]['type_descriptions'][$type];
            break;
        }
    }

    //if meeting is part of a group, include group info
    if ($meeting->group_id) {
        $group = get_post($meeting->group_id);
        $meeting->group = htmlentities($group->post_title, ENT_QUOTES);
        $meeting->group_notes = esc_html($group->post_content);
        $group_custom = tsml_get_meta('tsml_group', $meeting->group_id);
        foreach ($tsml_contact_fields as $field => $type) {
            $meeting->{$field} = empty($group_custom[$field]) ? null : $group_custom[$field];
        }

        if ($district = get_the_terms($group, 'tsml_district')) {
            $meeting->district_id = $district[0]->term_id;
            $meeting->district = $district[0]->name;
        }
    } else {
        $meeting->group_id = null;
        $meeting->group = null;
    }

    $meeting->attendance_option = tsml_calculate_attendance_option($meeting->types, $meeting->approximate);

    // Remove TC when online only meeting has approximate address
    if (!empty($meeting->types) && $meeting->attendance_option == 'online' && $meeting->approximate == 'yes') {
        $meeting->types = array_values(array_diff($meeting->types, ['TC']));
    }

    //expand and alphabetize types
    array_map('trim', $meeting->types);
    $meeting->types_expanded = [];
    foreach ($meeting->types as $type) {
        if ($type == 'ONL' || $type == 'TC') continue;

        if (!empty($tsml_programs[$tsml_program]['types'][$type])) {
            $meeting->types_expanded[] = $tsml_programs[$tsml_program]['types'][$type];
        }
    }
    sort($meeting->types_expanded);

    return $meeting;
}

//function: get feedback_url
//called in tsml_get_meta
function tsml_feedback_url($meeting)
{
    global $tsml_export_columns, $tsml_feedback_url;

    if (empty($tsml_feedback_url)) return;

    $url = $tsml_feedback_url;

    foreach ($tsml_export_columns as $key => $heading) {
        $value = !empty($meeting[$key]) ? $meeting[$key] : '';
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        $url = str_replace('{{' . $key . '}}', urlencode($value), $url);
    }
    return $url;
}


//function: get meetings based on unsanitized $arguments
//$from_cache is only false when calling from tsml_cache_rebuild()
//used:		tsml_ajax_meetings(), single-locations.php, archive-meetings.php
function tsml_get_meetings($arguments = [], $from_cache = true, $full_export = false)
{
    global $tsml_cache, $tsml_cache_writable, $tsml_contact_fields, $tsml_contact_display, $tsml_data_sources, $tsml_custom_meeting_fields;

    //start by grabbing all meetings
    if ($from_cache && $tsml_cache_writable && $meetings = file_get_contents(WP_CONTENT_DIR . $tsml_cache)) {
        $meetings = json_decode($meetings, true);
    } else {
        //from database
        $meetings = [];

        //can specify post_status (for PR #33)
        if (empty($arguments['post_status'])) {
            $arguments['post_status'] = 'publish';
        } elseif (is_array($arguments['post_status'])) {
            $arguments['post_status'] = array_map('sanitize_title', $arguments['post_status']);
        } else {
            $arguments['post_status'] = sanitize_title($arguments['post_status']);
        }

        $posts = get_posts([
            'post_type' => 'tsml_meeting',
            'numberposts' => -1,
            'post_status' => $arguments['post_status'],
        ]);

        $meeting_meta = tsml_get_meta('tsml_meeting');
        $groups = tsml_get_groups();
        $locations = tsml_get_locations();

        //make an array of the meetings
        foreach ($posts as $post) {
            //shouldn't ever happen, but just in case
            if (empty($locations[$post->post_parent])) continue;

            //append to array
            $meeting = array_merge([
                'id' => $post->ID,
                'name' => $post->post_title,
                'slug' => $post->post_name,
                'notes' => $post->post_content,
                'updated' => $post->post_modified_gmt,
                'location_id' => $post->post_parent,
                'url' => get_permalink($post->ID),
                'day' => @$meeting_meta[$post->ID]['day'],
                'time' => isset($meeting_meta[$post->ID]['time']) ? $meeting_meta[$post->ID]['time'] : null,
                'end_time' => isset($meeting_meta[$post->ID]['end_time']) ? $meeting_meta[$post->ID]['end_time'] : null,
                'time_formatted' => isset($meeting_meta[$post->ID]['time']) ? tsml_format_time($meeting_meta[$post->ID]['time']) : null,
                'edit_url' => get_edit_post_link($post, ''),
                'conference_url' => isset($meeting_meta[$post->ID]['conference_url']) ? $meeting_meta[$post->ID]['conference_url'] : null,
                'conference_url_notes' => isset($meeting_meta[$post->ID]['conference_url_notes']) ? $meeting_meta[$post->ID]['conference_url_notes'] : null,
                'conference_phone' => isset($meeting_meta[$post->ID]['conference_phone']) ? $meeting_meta[$post->ID]['conference_phone'] : null,
                'conference_phone_notes' => isset($meeting_meta[$post->ID]['conference_phone_notes']) ? $meeting_meta[$post->ID]['conference_phone_notes'] : null,
                'types' => empty($meeting_meta[$post->ID]['types']) ? [] : array_values(unserialize($meeting_meta[$post->ID]['types'])),
                'author' => get_the_author_meta('user_login', $post->post_author)
            ], $locations[$post->post_parent]);

            // include user-defined meeting fields
            if (!empty($tsml_custom_meeting_fields)) {
                foreach ($tsml_custom_meeting_fields as $field => $title) {
                    if (!empty($meeting_meta[$post->ID][$field])) {
                        $meeting[$field] = $meeting_meta[$post->ID][$field];
                    }
                }
            }

            // Include the data source when doing a full export
            if ($full_export && isset($meeting_meta[$post->ID]['data_source'])) {
                $meeting['data_source'] = $meeting_meta[$post->ID]['data_source'];
            }

            // include the name of the data source
            if (!empty($meeting_meta[$post->ID]['data_source']) && !empty($tsml_data_sources[$meeting_meta[$post->ID]['data_source']]['name'])) {
                $meeting['data_source_name'] = $tsml_data_sources[$meeting_meta[$post->ID]['data_source']]['name'];
            }

            //append contact info to meeting
            if (!empty($meeting_meta[$post->ID]['group_id']) && array_key_exists($meeting_meta[$post->ID]['group_id'], $groups)) {
                $meeting = array_merge($meeting, $groups[$meeting_meta[$post->ID]['group_id']]);
            } else {
                foreach ($tsml_contact_fields as $field => $type) {
                    if (!empty($meeting_meta[$post->ID][$field])) {
                        $meeting[$field] = $meeting_meta[$post->ID][$field];
                    }
                }
            }

            // Only show contact information when 'public' or doing a full export
            if ($tsml_contact_display !== 'public' && !$full_export) {
                for ($i = 1; $i < 4; $i++) {
                    unset($meeting['contact_' . $i . '_name']);
                    unset($meeting['contact_' . $i . '_email']);
                    unset($meeting['contact_' . $i . '_phone']);
                }
            }

            // Ensure each meeting has an address approximate value
            if (empty($meeting['approximate'])) {
                $meeting['approximate'] = 'no';
            }

            // Calculate the attendance option
            $meeting['attendance_option'] = tsml_calculate_attendance_option($meeting['types'], $meeting['approximate']);

            // Remove TC when online only meeting has approximate address
            if (!empty($meeting['types']) && $meeting['attendance_option'] == 'online' && $meeting['approximate'] == 'yes') {
                $meeting['types'] = array_values(array_diff($meeting['types'], ['TC']));
            }

            //add feedback_url only if present
            if ($feedback_url = tsml_feedback_url($meeting)) {
                $meeting['feedback_url'] = $feedback_url;
            }

            $meetings[] = $meeting;
        }

        // Clean up the array
        $meetings = array_map(function ($meeting) {
            foreach ($meeting as $key => $value) {
                if (empty($meeting[$key]) && $meeting[$key] !== '0') {
                    unset($meeting[$key]);
                } elseif (in_array($key, ['id', 'day', 'latitude', 'longitude', 'location_id', 'group_id', 'region_id', 'district_id'])) {
                    $meeting[$key] -= 0;
                }
            }
            return $meeting;
        }, $meetings);

        //write array to cache
        if (!$full_export) {
            $filepath = WP_CONTENT_DIR . $tsml_cache;
            // Check if the file is writable, and if so, write it
            if (is_writable($filepath) || (!file_exists($filepath) && is_writable(WP_CONTENT_DIR))) {
                $filesize = file_put_contents($filepath, json_encode($meetings));
                update_option('tsml_cache_writable', $filesize === false ? 0 : 1);
            } else {
                update_option('tsml_cache_writable', 0);
            }
        }
    }

    //check if we are filtering
    $allowed = [
        'mode',
        'day',
        'time',
        'region',
        'district',
        'type',
        'query',
        'group_id',
        'location_id',
        'latitude',
        'longitude',
        'distance_units',
        'distance',
        'attendance_option'
    ];
    if ($arguments = array_intersect_key($arguments, array_flip($allowed))) {
        $filter = new tsml_filter_meetings($arguments);
        $meetings = $filter->apply($meetings);
    }

    //sort meetings
    usort($meetings, function ($a, $b) {
        global $tsml_days_order, $tsml_sort_by;

        //sub_regions are regions in this scenario
        if (!empty($a['sub_region'])) $a['region'] = $a['sub_region'];
        if (!empty($b['sub_region'])) $b['region'] = $b['sub_region'];

        //custom sort order?
        if ($tsml_sort_by !== 'time') {
            if ($a[$tsml_sort_by] != $b[$tsml_sort_by]) {
                return strcmp($a[$tsml_sort_by], $b[$tsml_sort_by]);
            }
        }

        //get the user-settable order of days
        $a_day_index = isset($a['day']) && strlen($a['day']) ? array_search($a['day'], $tsml_days_order) : false;
        $b_day_index = isset($b['day']) && strlen($b['day']) ? array_search($b['day'], $tsml_days_order) : false;
        if ($a_day_index === false && $b_day_index !== false) {
            return 1;
        } elseif ($a_day_index !== false && $b_day_index === false) {
            return -1;
        } elseif ($a_day_index != $b_day_index) {
            return $a_day_index - $b_day_index;
        } else {
            //days are the same or both null
            $a_time = empty($a['time']) ? '' : (($a['time'] == '00:00') ? '23:59' : $a['time']);
            $b_time = empty($b['time']) ? '' : (($b['time'] == '00:00') ? '23:59' : $b['time']);
            $time_diff = strcmp($a_time, $b_time);
            if ($time_diff) {
                return $time_diff;
            } else {
                $a_location = empty($a['location']) ? '' : $a['location'];
                $b_location = empty($b['location']) ? '' : $b['location'];
                $location_diff = strcmp($a_location, $b_location);
                if ($location_diff) {
                    return $location_diff;
                } else {
                    $a_name = empty($a['name']) ? '' : $a['name'];
                    $b_name = empty($b['name']) ? '' : $b['name'];
                    return strcmp($a_name, $b_name);
                }
            }
        }
    });

    return $meetings;
}

//function: get metadata for all meetings very quickly
//called in tsml_get_meetings(), tsml_get_locations()
function tsml_get_meta($type, $id = null)
{
    global $wpdb, $tsml_custom_meeting_fields, $tsml_contact_fields;
    $keys = [
        'tsml_group' => array_keys($tsml_contact_fields),
        'tsml_location' => ['formatted_address', 'latitude', 'longitude', 'approximate'],
        'tsml_meeting' => array_merge(
            [
                'day',
                'time',
                'end_time',
                'types',
                'group_id',
                'conference_url',
                'conference_url_notes',
                'conference_phone',
                'conference_phone_notes',
                'data_source'
            ],
            array_keys($tsml_contact_fields),
            empty($tsml_custom_meeting_fields) ? [] : array_keys($tsml_custom_meeting_fields)
        ),
    ];

    if (!array_key_exists($type, $keys)) return trigger_error('tsml_get_meta for unexpected type ' . $type);
    $meta = [];
    $field_names_for_sql = implode(', ', array_map(function ($field) {
        return '"' . $field . '"';
    }, $keys[$type]));
    $query = 'SELECT post_id, meta_key, meta_value FROM ' . $wpdb->postmeta . ' WHERE
		meta_key IN (' . $field_names_for_sql . ') AND
		post_id ' . ($id ? '= ' . $id : 'IN (SELECT id FROM ' . $wpdb->posts . ' WHERE post_type = "' . $type . '")');
    $values = $wpdb->get_results($query);
    foreach ($values as $value) {
        $meta[$value->post_id][$value->meta_key] = $value->meta_value;
    }

    //get taxonomy
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
    } elseif ($type == 'tsml_group') {
        $districts = $wpdb->get_results('SELECT
				r.`object_id` group_id,
				t.`term_id` district_id,
				t.`name` district
			FROM ' . $wpdb->term_relationships . ' r
			JOIN ' . $wpdb->term_taxonomy . ' x ON r.term_taxonomy_id = x.term_taxonomy_id
			JOIN ' . $wpdb->terms . ' t ON x.term_id = t.term_id
			WHERE x.taxonomy = "tsml_district"');
        foreach ($districts as $district) {
            $meta[$district->group_id]['district'] = $district->district;
            $meta[$district->group_id]['district_id'] = $district->district_id;
        }
    }

    if ($id) return array_key_exists($id, $meta) ? $meta[$id] : [];
    return $meta;
}

// get an array from wp options and confirm it's an array
function tsml_get_option_array($option, $default = []) {
    $value = get_option($option, $default);
    return is_array($value) ? $value : $default;
}

//return spelled-out meeting types
//called from save.php (updates) and archive-meetings.php (display)
function tsml_meeting_types($types)
{
    global $tsml_programs, $tsml_program;
    if (empty($tsml_programs[$tsml_program]['types'])) return;
    $return = [];
    foreach ($types as $type) {
        if (array_key_exists($type, $tsml_programs[$tsml_program]['types'])) {
            $return[] = $tsml_programs[$tsml_program]['types'][$type];
        }
    }
    sort($return);
    return implode(', ', $return);
}

//sanitize and import an array of meetings to an 'import buffer' (an wp_option that's iterated on progressively)
//called from admin_import.php (both CSV and JSON)
function tsml_import_buffer_set($meetings, $data_source_url = null, $data_source_parent_region_id = null)
{
    global $tsml_programs, $tsml_program, $tsml_days, $tsml_meeting_attendance_options, $tsml_data_sources;

    if (strpos($data_source_url, "sheets.googleapis.com") !== false) {
        $meetings = tsml_import_reformat_googlesheet($meetings);
    }

    //allow theme-defined function to reformat data source import - issue #439
    if (function_exists('tsml_import_reformat')) {
        $meetings = tsml_import_reformat($meetings);
    }

    //uppercasing for value matching later
    $upper_types = array_map('strtoupper', $tsml_programs[$tsml_program]['types']);
    $upper_days = array_map('strtoupper', $tsml_days);

    //get users, keyed by username
    $users = [];
    foreach (get_users(['fields' => ['ID', 'user_login'],]) as $user) {
        $users[$user->user_login] = $user->ID;
    }

    $user_id = get_current_user_id();

    //convert the array to UTF-8
    array_walk_recursive($meetings, function (&$item) {
        if (!mb_detect_encoding($item, 'utf-8', true)) {
            $item = utf8_encode($item);
        }
    });

    //trim everything
    array_walk_recursive($meetings, function ($value) {
        //preserve <br>s as line breaks if present, otherwise clean up
        $value = preg_replace('/\<br(\s*)?\/?\>/i', PHP_EOL, $value);
        $value = stripslashes($value);
        $value = trim($value);

        //turn "string" into string (only do if on both ends though)
        if ((substr($value, 0, 1) == '"') && (substr($value, -1) == '"')) {
            $value = trim(trim($value, '"'));
        }

        return $value;
    });

    //check for any meetings with arrays of days and creates an individual meeting for each day in array
    $meetings_to_add = [];
    $indexes_to_remove = [];

    for ($i = 0; $i < count($meetings); $i++) {
        if (isset($meetings[$i]['day']) && is_array($meetings[$i]['day'])) {
            array_push($indexes_to_remove, $i);
            foreach ($meetings[$i]['day'] as $single_day) {
                $temp_meeting = $meetings[$i];
                $temp_meeting['day'] = $single_day;
                $temp_meeting['slug'] = $meetings[$i]['slug'] . "-" . $single_day;
                array_push($meetings_to_add, $temp_meeting);
            }
        }
    }

    for ($i = 0; $i < count($indexes_to_remove); $i++) {
        unset($meetings[$indexes_to_remove[$i]]);
    }

    $meetings = array_merge($meetings, $meetings_to_add);

    //prepare array for import buffer
    $count_meetings = count($meetings);
    for ($i = 0; $i < $count_meetings; $i++) {

        // If the meeting doesn't have a data_source, use the one from the function call
        if (empty($meetings[$i]['data_source'])) {
            $meetings[$i]['data_source'] = $data_source_url;
        } else {
            // Check if this data sources is in our list of feeds
            if (!array_key_exists($meetings[$i]['data_source'], $tsml_data_sources)) {
                // Not already there, so add it
                $tsml_data_sources[$meetings[$i]['data_source']] = [
                    'status' => 'OK',
                    'last_import' => current_time('timestamp'),
                    'count_meetings' => 0,
                    'name' => parse_url($meetings[$i]['data_source'], PHP_URL_HOST),
                    'parent_region_id' => $data_source_parent_region_id,
                    'change_detect' => null,
                    'type' => 'JSON',
                ];
            }
        }
        $meetings[$i]['data_source_parent_region_id'] = $data_source_parent_region_id;

        //do wordpress sanitization
        foreach ($meetings[$i] as $key => $value) {

            //have to compress types down real quick (only happens with json)
            if (is_array($value)) $value = implode(',', $value);

            if (tsml_string_ends($key, 'notes')) {
                $meetings[$i][$key] = tsml_sanitize_text_area($value);
            } else {
                $meetings[$i][$key] = sanitize_text_field($value);
            }
        }

        //column aliases
        if (empty($meetings[$i]['postal_code']) && !empty($meetings[$i]['zip'])) {
            $meetings[$i]['postal_code'] = $meetings[$i]['zip'];
        }
        if (empty($meetings[$i]['name']) && !empty($meetings[$i]['meeting'])) {
            $meetings[$i]['name'] = $meetings[$i]['meeting'];
        }
        if (empty($meetings[$i]['location']) && !empty($meetings[$i]['location_name'])) {
            $meetings[$i]['location'] = $meetings[$i]['location_name'];
        }
        if (empty($meetings[$i]['time']) && !empty($meetings[$i]['start_time'])) {
            $meetings[$i]['time'] = $meetings[$i]['start_time'];
        }

        //if '@' is in address, remove it and everything after
        if (!empty($meetings[$i]['address']) && $pos = strpos($meetings[$i]['address'], '@')) $meetings[$i]['address'] = trim(substr($meetings[$i]['address'], 0, $pos));

        //if location name is missing, use address
        if (empty($meetings[$i]['location'])) {
            $meetings[$i]['location'] = empty($meetings[$i]['address']) ? __('Meeting Location', '12-step-meeting-list') : $meetings[$i]['address'];
        }

        //day can either be 0, 1, 2, 3 or Sunday, Monday, or empty
        if (isset($meetings[$i]['day']) && !array_key_exists($meetings[$i]['day'], $upper_days)) {
            $meetings[$i]['day'] = array_search(strtoupper($meetings[$i]['day']), $upper_days);
        }

        //sanitize time & day
        if (empty($meetings[$i]['time']) || ($meetings[$i]['day'] === false)) {
            $meetings[$i]['time'] = $meetings[$i]['end_time'] = $meetings[$i]['day'] = false; //by appointment

            //if meeting name missing, use location
            if (empty($meetings[$i]['name'])) $meetings[$i]['name'] = sprintf(__('%s by Appointment', '12-step-meeting-list'), $meetings[$i]['location']);
        } else {
            //if meeting name missing, use location, day, and time
            if (empty($meetings[$i]['name'])) {
                $meetings[$i]['name'] = sprintf(__('%s %ss at %s', '12-step-meeting-list'), $meetings[$i]['location'], $tsml_days[$meetings[$i]['day']], $meetings[$i]['time']);
            }

            $meetings[$i]['time'] = tsml_format_time_reverse($meetings[$i]['time']);
            if (!empty($meetings[$i]['end_time'])) $meetings[$i]['end_time'] = tsml_format_time_reverse($meetings[$i]['end_time']);
        }

        //google prefers USA for geocoding
        if (!empty($meetings[$i]['country']) && $meetings[$i]['country'] == 'US') $meetings[$i]['country'] = 'USA';

        //build address
        if (empty($meetings[$i]['formatted_address'])) {
            $address = [];
            if (!empty($meetings[$i]['address'])) $address[] = $meetings[$i]['address'];
            if (!empty($meetings[$i]['city'])) $address[] = $meetings[$i]['city'];
            if (!empty($meetings[$i]['state'])) $address[] = $meetings[$i]['state'];
            if (!empty($meetings[$i]['postal_code'])) {
                if ((strlen($meetings[$i]['postal_code']) < 5) && ($meetings[$i]['country'] == 'USA')) $meetings[$i]['postal_code'] = str_pad($meetings[$i]['postal_code'], 5, '0', STR_PAD_LEFT);
                $address[] = $meetings[$i]['postal_code'];
            }
            if (!empty($meetings[$i]['country'])) $address[] = $meetings[$i]['country'];
            $meetings[$i]['formatted_address'] = implode(', ', $address);
        }

        //notes
        if (empty($meetings[$i]['notes'])) $meetings[$i]['notes'] = '';
        if (empty($meetings[$i]['location_notes'])) $meetings[$i]['location_notes'] = '';
        if (empty($meetings[$i]['group_notes'])) $meetings[$i]['group_notes'] = '';

        //updated
        if (empty($meetings[$i]['updated']) || (!$meetings[$i]['updated'] = strtotime($meetings[$i]['updated']))) $meetings[$i]['updated'] = time();
        $meetings[$i]['post_modified'] = date('Y-m-d H:i:s', $meetings[$i]['updated']);
        $meetings[$i]['post_modified_gmt'] = get_gmt_from_date($meetings[$i]['post_modified']);

        //author
        if (!empty($meetings[$i]['author']) && array_key_exists($meetings[$i]['author'], $users)) {
            $meetings[$i]['post_author'] = $users[$meetings[$i]['author']];
        } else {
            $meetings[$i]['post_author'] = $user_id;
        }

        //default region to city if not specified
        if (empty($meetings[$i]['region']) && !empty($meetings[$i]['city'])) $meetings[$i]['region'] = $meetings[$i]['city'];

        //sanitize types (they can be Closed or C)
        if (empty($meetings[$i]['types'])) $meetings[$i]['types'] = '';
        $types = explode(',', $meetings[$i]['types']);
        $meetings[$i]['types'] = $unused_types = [];
        foreach ($types as $type) {
            $upper_type = trim(strtoupper($type));
            if (in_array($upper_type, array_map('strtoupper', array_keys($upper_types)))) {
                $meetings[$i]['types'][] = $type;
            } elseif (in_array($upper_type, array_values($upper_types))) {
                $meetings[$i]['types'][] = array_search($upper_type, $upper_types);
            } else {
                $unused_types[] = $type;
            }
        }

        //if a meeting is both open and closed, make it closed
        if (in_array('C', $meetings[$i]['types']) && in_array('O', $meetings[$i]['types'])) {
            $meetings[$i]['types'] = array_diff($meetings[$i]['types'], ['O']);
        }

        //append unused types to notes
        if (count($unused_types)) {
            if (!empty($meetings[$i]['notes'])) $meetings[$i]['notes'] .= str_repeat(PHP_EOL, 2);
            $meetings[$i]['notes'] .= implode(', ', $unused_types);
        }

        // If Conference URL, validate; or if phone, force 'ONL' type, else remove 'ONL'
        $meetings[$i]['types'] = array_values(array_diff($meetings[$i]['types'], ['ONL']));
        if (!empty($meetings[$i]['conference_url'])) {
            $url = esc_url_raw($meetings[$i]['conference_url'], ['http', 'https']);
            if (tsml_conference_provider($url)) {
                $meetings[$i]['conference_url'] = $url;
                $meetings[$i]['types'][] = 'ONL';
            } else {
                $meetings[$i]['conference_url'] = null;
                $meetings[$i]['conference_url_notes'] = null;
            }
        }
        if (!empty($meetings[$i]['conference_phone']) && empty($meetings[$i]['conference_url'])) {
            $meetings[$i]['types'][] = 'ONL';
        }
        if (empty($meetings[$i]['conference_phone'])) {
            $meetings[$i]['conference_phone_notes'] = null;
        }

        //Clean up attendance options
        if (!empty($meetings[$i]['attendance_option'])) {
            $meetings[$i]['attendance_option'] = trim(strtolower($meetings[$i]['attendance_option']));
            if (!array_key_exists($meetings[$i]['attendance_option'], $tsml_meeting_attendance_options)) {
                $meetings[$i]['attendance_option'] = '';
            }
        }

        //make sure we're not double-listing types
        $meetings[$i]['types'] = array_unique($meetings[$i]['types']);
	    
        //clean up
        foreach (['address', 'city', 'state', 'postal_code', 'country', 'updated'] as $key) {
            if (isset($meetings[$i][$key])) unset($meetings[$i][$key]);
        }

        //preserve row number for errors later
        $meetings[$i]['row'] = $i + 2;
    }

    //save data source configuration
    update_option('tsml_data_sources', $tsml_data_sources);

    //allow user-defined function to filter the meetings (for gal-aa.org)
    if (function_exists('tsml_import_filter')) {
        $meetings = array_filter($meetings, 'tsml_import_filter');
    }

    //prepare import buffer in wp_options
    update_option('tsml_import_buffer', $meetings, false);
}

//function:	filter workaround for setting post_modified dates
//used:		tsml_ajax_import()
function tsml_import_post_modified($data, $postarr)
{

    if (!empty($postarr['post_modified'])) {
        $data['post_modified'] = $postarr['post_modified'];
    }
    if (!empty($postarr['post_modified_gmt'])) {
        $data['post_modified_gmt'] = $postarr['post_modified_gmt'];
    }
    return $data;
}

//function: handle FNV (GSO) imports
//used:		tsml_import() - put here for code isolation
function tsml_import_reformat_fnv($rows)
{

    $meetings = [];

    $header = array_shift($rows);

    //check if it's a FNV file
    $required_fnv_columns = ['ServiceNumber', 'GroupName', 'CountryCode', 'City', 'District', 'Website', 'DateChanged', 'PrimaryFirstName', 'SecondaryPrimaryEmail', 'Meeting1Addr1', 'Meeting1SUNTimes'];
    $missing_fnv_columns = array_diff($required_fnv_columns, $header);
    //dd($missing_fnv_columns);
    if (!empty($missing_fnv_columns)) {
        array_unshift($rows, $header);
        return $rows;
    }

    $short_days = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $all_types = [];

    foreach ($rows as $row) {

        $row = array_combine($header, $row);

        if ($row['Type'] !== 'Regular') continue;

        for ($number = 1; $number < 5; $number++) {
            foreach ($short_days as $index => $day) {
                $key = 'Meeting' . $number . $day . 'Times';
                if (!empty($row[$key])) {
                    $times = explode('  ', strtolower($row[$key]));
                    foreach ($times as $time) {
                        $time_parts = explode('(', str_replace(')', '', $time));
                        $time = array_shift($time_parts);
                        $types = [];
                        foreach ($time_parts as $part) {
                            if (($part == 'c') || strstr($part, 'closed')) {
                                $types[] = 'Closed';
                            } elseif (($part == 'o') || strstr($part, 'open')) {
                                $types[] = 'Open';
                            }
                            if (strstr($part, 'women') || strstr($part, 'lady')) {
                                $types[] = 'Women';
                            } elseif (strstr($part, 'men')) {
                                $types[] = 'Men';
                            }
                            if (strstr($part, 'bb') || strstr($part, 'big book')) $types[] = 'Big Book';
                            if (strstr($part, '12') || strstr($part, 'step')) $types[] = 'Step Study';
                            if (strstr($part, 'candlelight')) $types[] = 'Candlelight';
                            if (strstr($part, 'speaker')) $types[] = 'Speaker';
                            if (strstr($part, 'disc')) $types[] = 'Discussion';
                            if (strstr($part, 'newcomer') || strstr($part, 'new comer')) $types[] = 'Newcomer';
                            if (strstr($part, 'grapevine')) $types[] = 'Grapevine';
                            if (strstr($part, 'spanish')) $types[] = 'Spanish';
                        }
                        $all_types = array_merge($all_types, $types);

                        //add language as type
                        $types[] = $row['Language'];

                        if ($pos = strpos($row['Meeting' . $number . 'Addr1'], '(')) {
                            $row['Meeting' . $number . 'Addr1'] = substr($row['Meeting' . $number . 'Addr1'], 0, $pos);
                            $row['Meeting' . $number . 'Comments'] .= PHP_EOL . substr($row['Meeting' . $number . 'Addr1'], $pos);
                        }

                        $meetings[] = [
                            'Name' => $row['GroupName'],
                            'Day' => $days[$index],
                            'Time' => $time,
                            'Location' => $row['Meeting' . $number . 'Location'],
                            'Address' => $row['Meeting' . $number . 'Addr1'],
                            'City' => $row['Meeting' . $number . 'City'],
                            'State' => $row['Meeting' . $number . 'StateCode'],
                            'Postal Code' => $row['Meeting' . $number . 'Zip'],
                            'Country' => $row['CountryCode'],
                            'Types' => implode(', ', $types),
                            'Region' => $row['City'],
                            'Group' => $row['GroupName'] . ' #' . $row['ServiceNumber'],
                            'Website' => $row['Website'],
                            'Updated' => $row['DateChanged'],
                            'District' => $row['District'] ? sprintf(__('District %s', '12-step-meeting-list'), $row['District']) : null,
                            'Last Contact' => $row['DateChanged'],
                            'Notes' => $row['Meeting' . $number . 'Comments'],
                            'Contact 1 Name' => $row['PrimaryFirstName'] . ' ' . $row['PrimaryLastName'],
                            'Contact 1 Phone' => preg_replace('~\D~', '', $row['PrimaryPrimaryPhone']),
                            'Contact 1 Email' => substr($row['PrimaryPrimaryEmail'], strpos($row['PrimaryPrimaryEmail'], ' ') + 1),
                            'Contact 2 Name' => $row['SecondaryFirstName'] . ' ' . $row['SecondaryLastName'],
                            'Contact 2 Phone' => preg_replace('~\D~', '', $row['SecondaryPrimaryPhone']),
                            'Contact 2 Email' => substr($row['SecondaryPrimaryEmail'], strpos($row['SecondaryPrimaryEmail'], ' ') + 1),
                        ];
                    }
                }
            }
        }
    }

    //debugging types
    $all_types = array_unique($all_types);
    sort($all_types);

    $return = [array_keys($meetings[0])];
    foreach ($meetings as $meeting) {
        $return[] = array_values($meeting);
    }
    return $return;
}

//function: translates a Meeting Guide format Google Sheet to proper format for import
//used: tsml_import_buffer_set
function tsml_import_reformat_googlesheet($data)
{
    $meetings = [];

    $header = array_shift($data['values']);
    $header = array_map('sanitize_title_with_dashes', $header);
    $header = str_replace('-', '_', $header);
    $header_count = count($header);

    foreach ($data['values'] as $row) {

        //creates a meeting array with elements corresponding to each column header of the Google Sheet; updated for Google Sheets v4 API
        $meeting = [];
        for ($j = 0; $j < $header_count; $j++) {
            if (isset($row[$j])) {
                $meeting[$header[$j]] = $row[$j];
            }
        }

        array_push($meetings, $meeting);
    }

    return $meetings;
}

//function: return an html link with current query string appended -- this is because query string permalink structure is an enormous pain in the ass
//used:		archive-meetings.php, single-locations.php, single-meetings.php
function tsml_link($url, $string, $exclude = '', $class = false)
{
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

//function: add an entry to the activity log
//$type is something short you can filter by, eg 'geocode_error'
//$info is the bad result you got back
//$input is any input that might have contributed to the result
//used in tsml_ajax_info, tsml_geocode and anywhere else something could go wrong
function tsml_log($type, $info = null, $input = null)
{
    //load
    $tsml_log = tsml_get_option_array('tsml_log');

    //default variables
    $entry = [
        'type' => $type,
        'timestamp' => current_time('mysql'),
    ];

    //optional variables
    if ($info) $entry['info'] = $info;
    if ($input) $entry['input'] = $input;

    //prepend to array
    array_unshift($tsml_log, $entry);

    //save
    update_option('tsml_log', $tsml_log);
}

//function: link to meetings page with parameters (added to link dropdown menus for SEO)
//used:		archive-meetings.php
function tsml_meetings_url($parameters)
{
    $url = get_post_type_archive_link('tsml_meeting');
    $url .= (strpos($url, '?') === false) ? '?' : '&';
    $url .= http_build_query($parameters);
    return $url;
}

//function: convert line breaks in plain text to HTML paragraphs
//used:		save.php in lieu of nl2br()
function tsml_paragraphs($string)
{
    $paragraphs = '';
    foreach (explode("\n", trim($string)) as $line) {
        if ($line = trim($line)) {
            $paragraphs .= '<p>' . $line . '</p>';
        }
    }
    return $paragraphs;
}

//function: boolean whether current program has types
//used:		meeting edit screen, meeting save
function tsml_program_has_types()
{
    global $tsml_programs, $tsml_program;
    return !empty($tsml_programs[$tsml_program]['types']);
}

// exit if user does not have permission to edit meetings
function tsml_require_meetings_permission()
{
    if (!current_user_can(TSML_MEETINGS_PERMISSION)) {
        wp_die(__('You do not have sufficient permissions to access this page (<code>' . TSML_MEETINGS_PERMISSION . '</code>).', '12-step-meeting-list'));
    }
}

// exit if user does not have permission to edit settings
function tsml_require_settings_permission()
{
    if (!current_user_can(TSML_SETTINGS_PERMISSION)) {
        wp_die(__('You do not have sufficient permissions to access this page (<code>' . TSML_SETTINGS_PERMISSION . '</code>).', '12-step-meeting-list'));
    }
}

//function: set an option with the currently-used types
//used: 	tsml_import() and save.php
function tsml_update_types_in_use()
{
    global $tsml_types_in_use, $wpdb;

    //shortcut to getting all meta values without getting all posts first
    $types = $wpdb->get_col('SELECT
			m.meta_value
		FROM ' . $wpdb->postmeta . ' m
		JOIN ' . $wpdb->posts . ' p ON m.post_id = p.id
		WHERE p.post_type = "tsml_meeting" AND m.meta_key = "types" AND p.post_status = "publish"');

    //master array
    $all_types = [];

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

//function:	sanitize a value
//used:		save.php
function tsml_sanitize($type, $value)
{
    if ($type == 'url') {
        return esc_url_raw($value, ['http', 'https']);
    } elseif ($type == 'date') {
        return date('Y-m-d', strtotime($value));
    } elseif ($type == 'time') {
        return date('H:i', strtotime($value));
    } elseif ($type == 'phone') {
        return preg_replace('/[^0-9,+#]/', '', $value);
    }
    return sanitize_text_field($value);
}

//function: sanitize multi-line text
//used:		tsml_import() and save.php
function tsml_sanitize_text_area($value)
{
    return implode("\n", array_map('sanitize_text_field', explode("\n", trim($value))));
}

//function:	does a string end with another string
//used:		save.php
function tsml_string_ends($string, $end)
{
    $length = strlen($end);
    if (!$length) return true;
    return (substr($string, -$length) === $end);
}

//function:	tokenize string for the typeaheads
//used:		ajax functions
function tsml_string_tokens($string)
{

    //shorten words that have quotes in them instead of splitting them
    $string = html_entity_decode($string);
    $string = str_replace("'", '', $string);
    $string = str_replace('’', '', $string);

    //remove everything that's not a letter or a number
    $string = preg_replace("/[^a-zA-Z 0-9]+/", ' ', $string);

    //return array
    return array_values(array_unique(array_filter(explode(' ', $string))));
}

/**
 * Implodes given array $types into lower-cased class names, prefixing each with $prefix
 *
 * @param string[] $types
 * @param string $prefix
 * @return string
 */
function tsml_to_css_classes($types, $prefix = 'type-')
{
    if (!$types) {
        return '';
    }

    $types = array_map('strtolower', $types);

    return $prefix . implode(' ' . $prefix, $types);
}

/**
 * Sanitizes a string for sorting purposes.  Similar to sanitize_title(), but uses Unicode regular expressions to support multiple languages.
 *
 * NOTE: Requires PHP 5.1.0 or later.  More details here:
 *   https://www.php.net/manual/en/regexp.reference.unicode.php
 *
 * @param string $string
 * @return string
 */
function tsml_sanitize_data_sort($string)
{
    global $tsml_sanitize_data_sort_regexps;

    // Populate regex array only once
    if (!isset($tsml_sanitize_data_sort_regexps)) {
        $tsml_sanitize_data_sort_regexps = [
            ['/<[^>]+>/', ''], # Strip HTML Tags
            ['/[&\'"<>]+/', ''], # Strip unsupported chars
            ['/[\\/\\.\\p{Zs}\\p{Pd}]/u', '-'], # Change forward slashes, periods, spaces and dashes to dash (Unicode)
            ['/[^\\p{L}\\p{N}\\p{M}\-]+/u', ''], # Remove any Unicode char that is not an alpha-numeric, mark character or dash
            ['/\-+/', '-'], # Convert runs of dashes into a single dash
            ['/^\-|\-$/', ''] # Strip trailing/leading dash
        ];
    }

    # Convert all html entities to chars so encodings are uniform
    $t = html_entity_decode($string);

    # Do regex-based sanitization
    foreach ($tsml_sanitize_data_sort_regexps as $a) {
        $t = preg_replace($a[0], $a[1], $t);
    }

    # Unicode-aware lowercase of characters in string
    return mb_strtolower($t);
}


/* ******************** start of data_source_change_detection ****************** */

//called by register_activation_hook in admin_import
function tsml_activate_data_source_scan()
{
    //Use wp_next_scheduled to check if the event is already scheduled
    $timestamp = wp_next_scheduled('tsml_scan_data_source');

    //If $timestamp is false schedule scan since it hasn't been done previously
    if ($timestamp == false) {
        //Schedule the event for right now, then to reoccur daily using the hook 'tsml_scan_data_source'
        wp_schedule_event(time(), 'daily', 'tsml_scan_data_source');
    }
}

//called by register_deactivation_hook in admin_import
//removes the cron-job set by tsml_activate_daily_refresh()
function tsml_deactivate_data_source_scan()
{
    wp_clear_scheduled_hook('tsml_scan_data_source');
}

//function: scans passed data source url looking for recent updates
//used:		fired by cron job tsml_scan_data_source
add_action('tsml_scan_data_source', 'tsml_scan_data_source', 10, 1);
if (!function_exists('tsml_scan_data_source')) {
    function tsml_scan_data_source($data_source_url)
    {

        $errors = array();
        $data_source_name = null;
        $data_source_parent_region_id = -1;
        $data_source_change_detect = 'disabled';
        $data_source_count_meetings = 0;
        $data_source_last_import = null;

        $tsml_notification_addresses = tsml_get_option_array('tsml_notification_addresses');
        $tsml_data_sources = tsml_get_option_array('tsml_data_sources');
        $data_source_count_meetings = (int) $tsml_data_sources[$data_source_url]['count_meetings'];

        if (!empty($tsml_notification_addresses) && $data_source_count_meetings !== 0) {
            if (array_key_exists($data_source_url, $tsml_data_sources)) {
                $data_source_name = $tsml_data_sources[$data_source_url]['name'];
                $data_source_parent_region_id = $tsml_data_sources[$data_source_url]['parent_region_id'];
                $data_source_change_detect = $tsml_data_sources[$data_source_url]['change_detect'];
                $data_source_last_import = (int) $tsml_data_sources[$data_source_url]['last_import'];
            } else {
                $errors .= "Data Source not registered in tsml_data_sources of the options table!";
                return;
            }

            //try fetching
            $response = wp_safe_remote_get(
                $data_source_url,
                array(
                    'timeout' => 30,
                    'sslverify' => false,
                )
            );

            if (is_array($response) && !empty($response['body']) && ($body = json_decode($response['body'], true))) {
                $meetings = $body;
                //allow theme-defined function to reformat prior to import
                if (function_exists('tsml_import_reformat')) {
                    $meetings = tsml_import_reformat($body);
                }

                // check import feed for changes and return list summing up changes detected
                $meetings_updated = tsml_import_changes($meetings, $data_source_url, $data_source_last_import);

                if (count($meetings_updated) > 0) {
                    // Send Email notifying Admins that this Data Source needs updating
                    $message = "Data Source changes were detected during a scheduled sychronization check with this feed: $data_source_url. Your website meeting list details based on the $data_source_name feed are no longer in sync. <br><br>Please sign-in to your website and refresh the $data_source_name Data Source feed found on the Meetings Import & Settings page.<br><br>";
                    $message .= "data_source_name: $data_source_name <br>";
                    $term = get_term_by('term_id', $data_source_parent_region_id, 'tsml_region');
                    $parent_region = $term->name;
                    $message .= "parent_region: $parent_region <br>";
                    $message .= "database record count: $data_source_count_meetings <br>";
                    $feedCount = count($meetings);
                    $message .= "data source feed count: $feedCount<br>";
                    $message .= "Last Refresh: <span style='color:red;'>*</span>" . Date("l F j, Y  h:i a", $data_source_last_import) . '<br>';
                    $message .= "<br><b><u>Detected Difference</b></u><br>";
                    $message .= "<table border='1' style='width:600px;'><tbody><tr><th>Update Mode</th><th>Meeting Name</th><th>Day of Week</th><th>Last Updated</th></tr>";
                    $message .= implode('', $meetings_updated);
                    $message .= "</tbody></table><br>";
                    $import_page_url = admin_url('edit.php?post_type=tsml_meeting&page=import');
                    $message .= "<a href='" . $import_page_url . "' style=' margin: 0 auto;background-color: #4CAF50;border: none;color: white;padding: 25px 32px;text-align: center;text-decoration: none;display: block;font-size: 18px;'>Go to Import & Settings page</a>";

                    // send Changes Detected email
                    $subject = __('Data Source Changes Detected', '12-step-meeting-list') . ': ' . $data_source_name;
                    if (tsml_email($tsml_notification_addresses, str_replace("'s", "s", $subject), $message)) {
                        _e("<div class='bg-success text-light'>Data Source changes were detected during the daily sychronization check with this feed: $data_source_url.<br></div>", '12-step-meeting-list');
                    } else {
                        global $phpmailer;
                        if (!empty($phpmailer->ErrorInfo)) {
                            printf(__('Error: %s', '12-step-meeting-list'), $phpmailer->ErrorInfo);
                        } else {
                            _e("<div class='bg-warning text-dark'>An error occurred while sending email!</div>", '12-step-meeting-list');
                        }
                    }
                    remove_filter('wp_mail_content_type', 'tsml_email_content_type_html');
                    tsml_alert(__('Send Email: Data Source Changes Detected.', '12-step-meeting-list'));
                }
            } elseif (!is_array($response)) {

                tsml_alert(__('Invalid response, <pre>' . print_r($response, true) . '</pre>.', '12-step-meeting-list'), 'error');
            } elseif (empty($response['body'])) {

                tsml_alert(__('Data source gave an empty response, you might need to try again.', '12-step-meeting-list'), 'error');
            } else {

                switch (json_last_error()) {
                    case JSON_ERROR_NONE:
                        tsml_alert(__('JSON: no errors.', '12-step-meeting-list'), 'error');
                        break;
                    case JSON_ERROR_DEPTH:
                        tsml_alert(__('JSON: Maximum stack depth exceeded.', '12-step-meeting-list'), 'error');
                        break;
                    case JSON_ERROR_STATE_MISMATCH:
                        tsml_alert(__('JSON: Underflow or the modes mismatch.', '12-step-meeting-list'), 'error');
                        break;
                    case JSON_ERROR_CTRL_CHAR:
                        tsml_alert(__('JSON: Unexpected control character found.', '12-step-meeting-list'), 'error');
                        break;
                    case JSON_ERROR_SYNTAX:
                        tsml_alert(__('JSON: Syntax error, malformed JSON.', '12-step-meeting-list'), 'error');
                        break;
                    case JSON_ERROR_UTF8:
                        tsml_alert(__('JSON: Malformed UTF-8 characters, possibly incorrectly encoded.', '12-step-meeting-list'), 'error');
                        break;
                    default:
                        tsml_alert(__('JSON: Unknown error.', '12-step-meeting-list'), 'error');
                        break;
                }
            }
        }
    }
}

//function:	Returns summary list of modified records when data source changes detected
function tsml_import_changes($feed_meetings, $data_source_url, $data_source_last_refresh)
{
    $db_meetings = $feed_slugs = $message_lines = [];
    $week_days = [
        __('Sunday', '12-step-meeting-list'),
        __('Monday', '12-step-meeting-list'),
        __('Tuesday', '12-step-meeting-list'),
        __('Wednesday', '12-step-meeting-list'),
        __('Thursday', '12-step-meeting-list'),
        __('Friday', '12-step-meeting-list'),
        __('Saturday', '12-step-meeting-list'),
    ];

    // get local meetings
    $all_db_meetings = tsml_get_meetings();
    $ds_ids = tsml_get_data_source_ids($data_source_url);
    sort($ds_ids);

    /* filter out all but the data source meetings  */
    foreach ($all_db_meetings as $db_meeting) {
        $db_id = $db_meeting['id'];
        if (in_array($db_id, $ds_ids)) {
            array_push($db_meetings, $db_meeting);
        }
    }

    // create array of database slugs for matching
    $db_slugs = array_column($db_meetings, 'slug', 'id');
    sort($db_slugs);

    // list changed and new meetings found in the data source feed
    foreach ($feed_meetings as $meeting) {

        list($day_of_week, $dow_number) = tsml_get_day_of_week_info($meeting['day'], $week_days);
        $meeting_slug = $meeting['slug'];

        // match feed/database on unique slug
        $is_matched = in_array($meeting_slug, $db_slugs);

        if (!$is_matched) {
            // numeric slugs may need some reformatting
            if (is_numeric($meeting_slug)) {
                $meeting_slug .= '-' . $dow_number;
            }
            $is_matched = in_array($meeting_slug, $db_slugs);
        }

        // add slug to feed array to help determine current db removals later on...
        $feed_slugs[] = $meeting_slug;

        // has the meeting been updated since the last refresh?
        $current_meeting_last_update = strtotime($meeting['updated']);
        if ($current_meeting_last_update > $data_source_last_refresh) {
            $permalink = get_permalink($meeting['id']);
            $meeting_name = '<a href=' . $permalink . '>' . $meeting['name'] . '</a>';
            $meeting_update_date = date('M j, Y  g:i a', $current_meeting_last_update);

            if ($is_matched) {
                $message_lines[] = "<tr style='color:gray;'><td>Change</td><td >$meeting_name</td><td>$day_of_week</td><td>$meeting_update_date</td></tr>";
            } else {
                $message_lines[] = "<tr style='color:green;'><td>Add New</td><td >$meeting_name</td><td>$day_of_week</td><td>$meeting_update_date</td></tr>";
            }
        }
    }

    // mark as "Remove" those meetings in local database which are not matched with feed
    foreach ($db_meetings as $db_meeting) {

        list($day_of_week, $dow_number) = tsml_get_day_of_week_info($db_meeting['day'], $week_days);
        $meeting_slug = $db_meeting['slug'];

        $is_matched = in_array($meeting_slug, $feed_slugs);

        // Check if slug has been modified on import by removing an appended suffix and test for match again
        if (!$is_matched) {
            for ($x = 0; $x <= 10; $x++) {
                if (str_contains($meeting_slug, '-' . $x)) {
                    $meeting_slug = str_replace('-' . $x, '', $meeting_slug);
                    break;
                }
            }
            $is_matched = in_array($meeting_slug, $feed_slugs);
        }

        if (!$is_matched) {
            $meeting_update_date = date('M j, Y  g:i a', $data_source_last_refresh);
            $permalink = get_permalink($db_meeting['id']);
            $meeting_name = '<a href=' . $permalink . '>' . $db_meeting['name'] . '</a>';
            $message_lines[] = "<tr style='color:red;'><td>Remove</td><td >$meeting_name</td><td>$day_of_week</td><td>* $meeting_update_date</td></tr>";
        }
    }

    return $message_lines;
}

//function:	Returns corresponding day of week string and number for the day input
if (!function_exists('tsml_get_day_of_week_info')) {
    function tsml_get_day_of_week_info($meeting_day_input, $week_days, $day_of_week = '', $dow_number = '')
    {
        // when day is like "Sunday" convert to number 0
        if (in_array($meeting_day_input, $week_days)) {
            $dow_number = array_search($meeting_day_input, $week_days);
        } elseif (is_array($meeting_day_input)) {
            $dow_number = implode("", $meeting_day_input);
        } else {
            $dow_number = $meeting_day_input;
        }

        // only accept valid day of week numbers
        $day_of_week = array_key_exists($dow_number, $week_days) ? $week_days[$dow_number] : 'invalid value';

        return [$day_of_week, $dow_number];
    }
}

//function:	Creates and configures cron job to run a scheduled data source scan
//used:		admin-import.php
function tsml_schedule_import_scan($data_source_url, $data_source_name)
{

    $timestamp = tsml_strtotime('tomorrow midnight'); // Use tsml_strtotime to incorporate local site timezone with UTC.

    // Get the timestamp for the next event when found.
    $ts = wp_next_scheduled("tsml_scan_data_source", array($data_source_url));
    if ($ts) {
        $mydisplaytime = tsml_date_localised(get_option('date_format') . ' ' . get_option('time_format'), $ts); // Use tsml_date_localised to convert to specified format with local site timezone included.
        tsml_alert("The $data_source_name data source's next scheduled run is $mydisplaytime.  You can adjust the recurrences and the times that the job ('<b>tsml_scan_data_source</b>') runs with the WP_Crontrol plugin.");
    } else {
        // When adding a data source we schedule its daily cron job
        register_activation_hook(__FILE__, 'tsml_activate_data_source_scan');

        //Schedule the refresh
        if (wp_schedule_event($timestamp, "daily", "tsml_scan_data_source", array($data_source_url)) === false) {
            tsml_alert("$data_source_name data source scan scheduling failed!");
        } else {
            $mydisplaytime = tsml_date_localised(get_option('date_format') . ' ' . get_option('time_format'), $timestamp); // Use tsml_date_localised to convert to specified format with local site timezone included.
            tsml_alert("The $data_source_name data source's next scheduled run is $mydisplaytime.  You can adjust the recurrences and the times that the job ('<b>tsml_scan_data_source</b>') runs with the WP_Crontrol plugin.");
        }
    }
}

//function:	incorporates wp timezone into php's StrToTime() function
//used:		here, admin-import.php
function tsml_strtotime($str)
{
    // This function behaves a bit like PHP's StrToTime() function, but taking into account the Wordpress site's timezone
    // CAUTION: It will throw an exception when it receives invalid input - please catch it accordingly
    // From https://mediarealm.com.au/

    $tz_string = get_option('timezone_string');
    $tz_offset = get_option('gmt_offset', 0);

    if (!empty($tz_string)) {
        // If site timezone option string exists, use it
        $timezone = $tz_string;
    } elseif ($tz_offset == 0) {
        // get UTC offset, if it isn’t set then return UTC
        $timezone = 'UTC';
    } else {
        $timezone = $tz_offset;

        if (substr($tz_offset, 0, 1) != "-" && substr($tz_offset, 0, 1) != "+" && substr($tz_offset, 0, 1) != "U") {
            $timezone = "+" . $tz_offset;
        }
    }

    $datetime = new DateTime($str, new DateTimeZone($timezone));
    return $datetime->format('U');
}

//function:	incorporates wp timezone into php's date() function
//used:		here, admin-import.php
function tsml_date_localised($format, $timestamp = null)
{
    // This function behaves a bit like PHP's Date() function, but taking into account the Wordpress site's timezone
    // CAUTION: It will throw an exception when it receives invalid input - please catch it accordingly
    // From https://mediarealm.com.au/

    $tz_string = get_option('timezone_string');
    $tz_offset = get_option('gmt_offset', 0);

    if (!empty($tz_string)) {
        // If site timezone option string exists, use it
        $timezone = $tz_string;
    } elseif ($tz_offset == 0) {
        // get UTC offset, if it isn’t set then return UTC
        $timezone = 'UTC';
    } else {
        $timezone = $tz_offset;

        if (substr($tz_offset, 0, 1) != "-" && substr($tz_offset, 0, 1) != "+" && substr($tz_offset, 0, 1) != "U") {
            $timezone = "+" . $tz_offset;
        }
    }

    if ($timestamp === null) {
        $timestamp = time();
    }

    $datetime = new DateTime();
    $datetime->setTimestamp($timestamp);
    $datetime->setTimezone(new DateTimeZone($timezone));
    return $datetime->format($format);
}
/* ******************** end of data_source_change_detection ******************** */

function tsml_header()
{
    if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
        include(TSML_PATH . '/templates/header.php');
    } else {
        get_header();
    }
}

function tsml_footer()
{
    if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
        include(TSML_PATH . '/templates/footer.php');
    } else {
        get_footer();
    }

}