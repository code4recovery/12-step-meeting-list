<?php

/**
 * Plugin Name: 12 Step Meeting List
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Description: Manage a list of recovery meetings
 * Version: 3.16.14
 * Requires PHP: 5.6
 * Author: Code for Recovery
 * Author URI: https://github.com/code4recovery/12-step-meeting-list
 * Text Domain: 12-step-meeting-list
 */

// define constants
define('TSML_ALLOWED_HTML', [
    'a' => ['class' => [], 'href' => [], 'title' => []],
    'br' => [],
    'code' => [],
    'em' => [],
    'pre' => [],
    'span' => ['class' => []],
    'small' => [],
    'strong' => [],
    'table' => ['style' => []],
    'td' => [],
    'tr' => []
]);
define('TSML_GROUP_CONTACT_COUNT', 3);
define('TSML_MEETING_GUIDE_APP_NOTIFY', 'appsupport@aa.org');
define('TSML_MEETINGS_PERMISSION', 'edit_posts');
define('TSML_PATH', plugin_dir_path(__FILE__));
define('TSML_SETTINGS_PERMISSION', 'manage_options');
define('TSML_VERSION', '3.16.14');

// include these files first
include TSML_PATH . '/includes/filter_meetings.php';
include TSML_PATH . '/includes/functions.php';
include TSML_PATH . '/includes/functions_format.php';
include TSML_PATH . '/includes/functions_get.php';
include TSML_PATH . '/includes/functions_import.php';
include TSML_PATH . '/includes/functions_input.php';
include TSML_PATH . '/includes/functions_timezone.php';
include TSML_PATH . '/includes/variables.php';

// include public files
include TSML_PATH . '/includes/ajax.php';
include TSML_PATH . '/includes/init.php';
include TSML_PATH . '/includes/shortcodes.php';
include TSML_PATH . '/includes/widgets.php';
include TSML_PATH . '/includes/widgets_init.php';
include TSML_PATH . '/includes/blocks.php';

// include admin files
if (is_admin()) {
    include TSML_PATH . '/includes/admin_import.php';
    include TSML_PATH . '/includes/admin_lists.php';
    include TSML_PATH . '/includes/admin_meeting.php';
    include TSML_PATH . '/includes/admin_menu.php';
    include TSML_PATH . '/includes/admin_region.php';
    include TSML_PATH . '/includes/admin_settings.php';
    include TSML_PATH . '/includes/save.php';
}

// these hooks need to be in this file
register_activation_hook(__FILE__, 'tsml_plugin_activation');
register_deactivation_hook(__FILE__, 'tsml_plugin_deactivation');
