<?php
/*
Plugin Name: 12 Step Meeting List
Plugin URI: https://wordpress.org/plugins/12-step-meeting-list/
Description: Manage a list of recovery meetings
Version: 3.5.2
Author: Code4Recovery
Author URI: https://github.com/code4recovery/12-step-meeting-list
Text Domain: 12-step-meeting-list
 */

//define constants
if (!defined('GROUP_CONTACT_COUNT')) {
    define('GROUP_CONTACT_COUNT', 3);
}

if (!defined('TSML_CONTACT_EMAIL')) {
    define('TSML_CONTACT_EMAIL', 'meetingguide@aa.org');
}

if (!defined('TSML_PATH')) {
    define('TSML_PATH', plugin_dir_path(__FILE__));
}

if (!defined('TSML_VERSION')) {
    define('TSML_VERSION', '3.5.2');
}

//include these files first
include TSML_PATH . '/includes/filter_meetings.php';
include TSML_PATH . '/includes/functions.php';
include TSML_PATH . '/includes/variables.php';

//include public files
include TSML_PATH . '/includes/ajax.php';
include TSML_PATH . '/includes/init.php';
include TSML_PATH . '/includes/shortcodes.php';
include TSML_PATH . '/includes/widgets.php';
include TSML_PATH . '/includes/widgets_init.php';

//include admin files
if (is_admin()) {
    include TSML_PATH . '/includes/admin_import.php';
    include TSML_PATH . '/includes/admin_lists.php';
    include TSML_PATH . '/includes/admin_meeting.php';
    include TSML_PATH . '/includes/admin_menu.php';
    include TSML_PATH . '/includes/admin_region.php';
    include TSML_PATH . '/includes/save.php';
}

//these hooks need to be in this file
register_activation_hook(__FILE__, 'tsml_change_activation_state');
register_activation_hook(__FILE__, 'tsml_activate_cron_jobs');
register_deactivation_hook(__FILE__, 'tsml_change_activation_state');
register_deactivation_hook(__FILE__, 'tsml_deactivate_cron_jobs');
