<?php
/*
Plugin Name: 12 Step Meeting List
Plugin URI: https://wordpress.org/plugins/12-step-meeting-list/
Description: CMS for maintaining database of 12-step meetings and locations
Version: 1.7.1
Author: Santa Clara County Intergroup
Author URI: aasanjose.org
License: none
*/

//tsml version, for managing updates
if (!defined('TSML_VERSION')) define('TSML_VERSION', '1.7.1');

//require 5.3 for anonymous functions
if (version_compare(phpversion(), '5.3.0', '<')) {
	exit(sprintf('<p style="font-family:\'Open Sans\',sans-serif;font-style:italic;color:#555;font-size:13px;
		line-height:1;">Twelve Step Meeting List requires PHP 5.3 or greater because it makes use of 
		<a href="http://php.net/manual/en/functions.anonymous.php" style="color:#dd3d36" target="_blank"
		>anonymous functions</a>. You appear to be on version %s.<br><br>WordPress recommends running <a 
		href="https://wordpress.org/about/requirements/" style="color:#dd3d36" target="_blank">5.6 or 
		greater</a>.</p>', phpversion()));
}

//include key files
include('includes/variables.php');
include('includes/functions.php');
include('includes/init.php');
include('includes/admin_edit.php');
include('includes/save.php');
include('includes/admin_lists.php');
include('includes/admin_import.php');

//these hooks need to be in this file
register_activation_hook(__FILE__, function(){
	tsml_custom_post_types();
	flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function(){
	tsml_custom_post_types();
	flush_rewrite_rules();
});
