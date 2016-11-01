<?php
/*
Plugin Name: 12 Step Meeting List
Plugin URI: https://wordpress.org/plugins/12-step-meeting-list/
Description: CMS for maintaining database of 12-step meetings and locations
Version: 2.7.4
Author: Meeting Guide
Author URI: https://meetingguide.org
License: none
Text Domain: 12-step-meeting-list
*/

//define constants
if (!defined('GROUP_CONTACT_COUNT'))	define('GROUP_CONTACT_COUNT',	3);
if (!defined('TSML_CONTACT_LINK'))		define('TSML_CONTACT_LINK',		'mailto:wordpress@meetingguide.org');
if (!defined('TSML_VERSION'))			define('TSML_VERSION',			'2.7.4');

//include these files first
include(plugin_dir_path(__FILE__) . '/includes/variables.php');
include(plugin_dir_path(__FILE__) . '/includes/functions.php');

//include rest of files
include(plugin_dir_path(__FILE__) . '/includes/admin_import.php');
include(plugin_dir_path(__FILE__) . '/includes/admin_lists.php');
include(plugin_dir_path(__FILE__) . '/includes/admin_meeting.php');
include(plugin_dir_path(__FILE__) . '/includes/admin_menu.php');
include(plugin_dir_path(__FILE__) . '/includes/admin_region.php');
include(plugin_dir_path(__FILE__) . '/includes/ajax.php');
include(plugin_dir_path(__FILE__) . '/includes/init.php');
include(plugin_dir_path(__FILE__) . '/includes/save.php');
include(plugin_dir_path(__FILE__) . '/includes/shortcodes.php');
include(plugin_dir_path(__FILE__) . '/includes/widgets.php');

//these hooks need to be in this file
register_activation_hook(  __FILE__, 'tsml_change_activation_state');
register_deactivation_hook(__FILE__, 'tsml_change_activation_state');