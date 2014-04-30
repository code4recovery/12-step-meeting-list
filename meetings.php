<?php
/**
 * Plugin Name: Meetings
 * Plugin URI: https://github.com/intergroup/plugin
 * Description: CMS for maintaining lists of meetings and locations
 * Version: 1.0
 * Author: Santa Clara County Intergroup
 * Author URI: http://aasanjose.org
 * License: none
 */

$days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');

add_action('admin_init', function(){
	include('hooks/admin_init.php');
});

add_action('admin_menu', function(){
});

add_action('init', function(){
	include('hooks/init.php');
});

add_action('save_post', function(){
	include('hooks/save_post.php');
});