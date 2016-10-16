<?php
/*
Plugin Name: 12 Step Meeting List
Plugin URI: https://wordpress.org/plugins/12-step-meeting-list/
Description: CMS for maintaining database of 12-step meetings and locations
Version: 2.6.6
Author: Meeting Guide
Author URI: https://meetingguide.org
License: none
Text Domain: 12-step-meeting-list
*/

//define constants
if (!defined('GROUP_CONTACT_COUNT'))	define('GROUP_CONTACT_COUNT',	3);
if (!defined('TSML_CONTACT_LINK'))		define('TSML_CONTACT_LINK',		'mailto:wordpress@meetingguide.org');
if (!defined('TSML_VERSION'))			define('TSML_VERSION',			'2.6.6');

//include key files
include('includes/variables.php');
include('includes/functions.php');
include('includes/init.php');
include('includes/admin_menu.php');
include('includes/admin_edit.php');
include('includes/region_edit.php');
include('includes/save.php');
include('includes/admin_lists.php');
include('includes/admin_import.php');
include('includes/ajax.php');
include('includes/shortcodes.php');

//these hooks need to be in this file
register_activation_hook(  __FILE__, 'tsml_change_activation_state');
register_deactivation_hook(__FILE__, 'tsml_change_activation_state');