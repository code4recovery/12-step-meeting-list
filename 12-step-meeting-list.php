<?php
/**
 * Plugin Name:	12 Step Meeting List
 * Plugin URI:	github.com/intergroup/plugin
 * Description:	CMS for maintaining database of 12-step meetings and locations
 * Version:		1.0.2
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

//coming soon
//include('includes/admin_options.php');


//these hooks are easier in this file
register_activation_hook(__FILE__, function(){
	tsml_custom_post_types();
	flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function(){
	tsml_custom_post_types();
	flush_rewrite_rules();
});
