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

add_action('init', function(){
	include('init.php');
});

add_action('admin_print_styles', function(){
	wp_enqueue_style('meetings_meta_style', plugin_dir_url(__FILE__) . 'admin.css' );
});

add_action('admin_init', function(){
	include('admin_init.php');
});