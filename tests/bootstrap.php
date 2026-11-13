<?php

/**
 * PHPUnit bootstrap file for the 12 Step Meeting List plugin tests.
 *
 * Uses wp-phpunit/wp-phpunit (installed via Composer) as the test library.
 * WordPress core must be downloaded to WP_CORE_DIR (default: /tmp/wordpress).
 */

// Point wp-phpunit at our config file.
putenv('WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php');

$tsml_tests_dir = dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit';

if (!file_exists("{$tsml_tests_dir}/includes/functions.php")) {
    echo 'Could not find wp-phpunit. Run: composer install' . PHP_EOL;
    exit(1);
}

require_once "{$tsml_tests_dir}/includes/functions.php";

/**
 * Load the plugin being tested.
 */
function tsml_tests_manually_load_plugin()
{
    require dirname(__DIR__) . '/12-step-meeting-list.php';

    // admin-only files whose hooks are tested (the plugin loads them only when is_admin())
    require dirname(__DIR__) . '/includes/admin_region.php';
}
tests_add_filter('muplugins_loaded', 'tsml_tests_manually_load_plugin');

require "{$tsml_tests_dir}/includes/bootstrap.php";
