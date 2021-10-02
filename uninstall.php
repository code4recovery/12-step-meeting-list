<?php

//check
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

//delete settings (removed settings)

//delete taxonomy
global $wpdb;
$wpdb->query('DELETE t.*, tt.*
		FROM ' . $wpdb->terms . ' AS t
		INNER JOIN ' . $wpdb->term_taxonomy . ' AS tt ON t.term_id = tt.term_id
		WHERE tt.taxonomy = "tsml_region"');
$wpdb->delete($wpdb->term_taxonomy, ['taxonomy' => 'tsml_region'], ['%s']);

//remove custom post types
$locations = get_posts('post_type=tsml_location&numberposts=-1');
foreach ($locations as $location) {
    wp_delete_post($location->ID, true);
}

$meetings = get_posts('post_type=tsml_meeting&numberposts=-1');
foreach ($meetings as $meeting) {
    wp_delete_post($meeting->ID, true);
}

$groups = get_posts('post_type=tsml_group&numberposts=-1');
foreach ($groups as $group) {
    wp_delete_post($group->ID, true);
}

//flush rewrite once more for good measure
flush_rewrite_rules();
