<?php
/*
Plugin Name: 12 Step Meeting List
Plugin URI: https://wordpress.org/plugins/12-step-meeting-list/
Description: CMS for maintaining database of 12-step meetings and locations
Version: 1.9.3
Author: Santa Clara County Intergroup
Author URI: aasanjose.org
License: none
*/

//tsml version, for managing updates
if (!defined('TSML_VERSION')) define('TSML_VERSION', '1.9.3');

//include key files
include('includes/variables.php');
include('includes/functions.php');
include('includes/init.php');
include('includes/admin_edit.php');
include('includes/save.php');
include('includes/admin_lists.php');
include('includes/admin_import.php');

//these hooks need to be in this file
register_activation_hook(__FILE__, 'tsml_change_activation_state');
register_deactivation_hook(__FILE__, 'tsml_change_activation_state');