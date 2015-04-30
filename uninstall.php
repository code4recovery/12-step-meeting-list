<?php
	
if (!defined('WP_UNINSTALL_PLUGIN')) exit();

//clear crons
wp_clear_scheduled_hook('meetings_announce');


//remove settings
unregister_setting('meetings', 'share');
unregister_setting('meetings', 'program');


//remove custom post types
$locations = get_posts('post_type=locations&numberposts=-1');
foreach ($locations as $location) wp_delete_post($location->ID, true);

$meetings = get_posts('post_type=meetings&numberposts=-1');
foreach ($meetings as $meeting) wp_delete_post($meeting->ID, true);


//remove custom taxonomies
$regions = get_terms('region');
foreach ($regions as $region) wp_delete_term($term->term_id, 'region');
