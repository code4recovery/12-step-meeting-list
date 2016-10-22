<?php
/*
Plugin Name: 12 Step Meeting List
Plugin URI: https://wordpress.org/plugins/12-step-meeting-list/
Description: CMS for maintaining database of 12-step meetings and locations
Version: 2.6.9
Author: Meeting Guide
Author URI: https://meetingguide.org
License: none
Text Domain: 12-step-meeting-list
*/

//define constants
if (!defined('GROUP_CONTACT_COUNT'))	define('GROUP_CONTACT_COUNT',	3);
if (!defined('TSML_CONTACT_LINK'))		define('TSML_CONTACT_LINK',		'mailto:wordpress@meetingguide.org');
if (!defined('TSML_VERSION'))			define('TSML_VERSION',			'2.6.9');

//include these files first
include(__DIR__ . '/includes/variables.php');
include(__DIR__ . '/includes/functions.php');

//include rest of files
include(__DIR__ . '/includes/admin_edit.php');
include(__DIR__ . '/includes/admin_import.php');
include(__DIR__ . '/includes/admin_lists.php');
include(__DIR__ . '/includes/admin_menu.php');
include(__DIR__ . '/includes/ajax.php');
include(__DIR__ . '/includes/init.php');
include(__DIR__ . '/includes/region_edit.php');
include(__DIR__ . '/includes/save.php');
include(__DIR__ . '/includes/shortcodes.php');
include(__DIR__ . '/includes/widgets.php');

//these hooks need to be in this file
register_activation_hook(  __FILE__, 'tsml_change_activation_state');
register_deactivation_hook(__FILE__, 'tsml_change_activation_state');