<?php

define('TSML_GROUP_CONTACT_COUNT', 3);

$tsml_test_options = [];

function add_filter($hook, $callback)
{
    return true;
}

function esc_html__($text)
{
    return $text;
}

function __($text)
{
    return $text;
}

function get_option($name, $default = false)
{
    global $tsml_test_options;

    if ($name === 'start_of_week') {
        return 1;
    }

    return array_key_exists($name, $tsml_test_options) ? $tsml_test_options[$name] : $default;
}

function update_option($name, $value)
{
    global $tsml_test_options;

    $tsml_test_options[$name] = $value;
    return true;
}

function current_time($type)
{
    return $type === 'w' ? 1 : time();
}

function tsml_get_option_array($option, $default = [])
{
    $value = get_option($option, $default);
    return is_array($value) ? $value : $default;
}

function wp_timezone_string()
{
    return 'America/New_York';
}

function tsml_timezone_is_valid($timezone)
{
    return is_string($timezone) && strlen($timezone);
}

function get_bloginfo($show)
{
    return $show === 'language' ? 'en-US' : '';
}

function plugin_basename($file)
{
    return basename($file);
}

function load_plugin_textdomain()
{
    return true;
}

function tsml_languages($types = [])
{
    return $types;
}

function tsml_state_scope_test_fail($message)
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function tsml_state_scope_test_assert($condition, $message)
{
    if (!$condition) {
        tsml_state_scope_test_fail($message);
    }
}

function tsml_state_scope_test_loader()
{
    include dirname(__DIR__) . '/includes/state.php';
    include dirname(__DIR__) . '/includes/variables.php';
}

$GLOBALS['tsml_slug'] = 'schedule';

tsml_state_scope_test_loader();

tsml_state_scope_test_assert(tsml_state_get('tsml_slug') === 'schedule', 'Legacy slug was not imported into state.');
tsml_state_scope_test_assert(is_array(tsml_state_get('tsml_contact_fields')), 'Contact fields missing from state.');
tsml_state_scope_test_assert(count(tsml_state_get('tsml_contact_fields')) === 19, 'Unexpected contact field count.');
tsml_state_scope_test_assert(is_array($GLOBALS['tsml_contact_fields']), 'Contact fields were not exported as a compatibility global.');
tsml_state_scope_test_assert(tsml_state_get('tsml_sort_by') === 'time', 'Default sort state was not initialised.');

tsml_load_config();

tsml_state_scope_test_assert(isset($GLOBALS['tsml_programs']['aa']), 'Programs were not exported after translated config load.');
tsml_state_scope_test_assert(tsml_state_get('tsml_days_order') === [1, 2, 3, 4, 5, 6, 0], 'Day order did not respect start_of_week.');
tsml_state_scope_test_assert(isset($GLOBALS['tsml_meeting_attendance_options']['in_person']), 'Attendance options missing after config load.');

$GLOBALS['tsml_sort_by'] = 'region';
tsml_state_scope_test_assert(tsml_state_get('tsml_sort_by') === 'region', 'Legacy global mutation was not imported.');

echo 'state-loader-scope-ok' . PHP_EOL;
