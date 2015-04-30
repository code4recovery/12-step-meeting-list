<?php
/**
 * Plugin Name:	Meetings
 * Plugin URI:	github.com/intergroup/plugin
 * Description:	CMS for maintaining database of meetings and locations
 * Version:		1.0
 * Author:		Santa Clara County Intergroup
 * Author URI:	aasanjose.org
 * License:		none
 */

//include key files
include('includes/variables.php');

include('includes/functions.php');

include('includes/init.php');

include('includes/admin_edit.php');

include('includes/save.php');

include('includes/admin_lists.php');

include('includes/admin_options.php');


//these hooks are easier in this file
register_activation_hook(__FILE__, function(){
	wp_schedule_event(time(), 'daily', 'meetings_announce');
	meetings_custom_post_types();
	flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function(){
	wp_clear_scheduled_hook('meetings_announce');
	flush_rewrite_rules();
});
