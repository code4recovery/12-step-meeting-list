<?php

// tsml_log types, with translated labels for admin page
define('TSML_LOG_TYPES', array(
    'data_source' => __('Data source', '12-step-meeting-list'),
    'data_source_error' => __('Data source error', '12-step-meeting-list'),
    'import_meeting' => __('Meeting import', '12-step-meeting-list'),
    'geocode_success' => __('Geocoding success', '12-step-meeting-list'),
    'geocode_error' => __('Geocoding error', '12-step-meeting-list'),
    'geocode_connection_error' => __('Geocoding connection error', '12-step-meeting-list'),
));

/**
 * add an entry to the activity log
 * used in tsml_ajax_info, tsml_geocode and anywhere else something could go wrong
 * 
 * @param mixed $type something short you can filter by, eg 'geocode_error'
 * @param mixed $info the bad result you got back
 * @param mixed $input any input that might have contributed to the result
 * @return void
 */
function tsml_log($type, $info = null, $input = null)
{
    global $tsml_log_updates;

    // to avoid too many db read / writes, save new entries
    // and write to option on shutdown
    if (!is_array($tsml_log_updates)) {
        $tsml_log_updates = [];
    }

    // default variables
    $entry = [
        'type' => $type,
        'timestamp' => time(),
    ];

    // optional variables
    if ($info) {
        $entry['info'] = $info;
    }
    if (!empty($input)) {
        if (is_array($input)) {
            $entry = array_merge($entry, $input);
        } else {
            $entry['input'] = $input;
        }
    }

    // prepend to array
    array_unshift($tsml_log_updates, $entry);

    // Check if the WordPress action hook 'shutdown' has been set
    if (!has_action('shutdown', 'tsml_log_save_updates')) {
        add_action('shutdown', 'tsml_log_save_updates');
    }
}

/**
 * Summary of tsml_log_save
 * @return void
 */
function tsml_log_save_updates()
{
    global $tsml_log_updates;
    if (is_array($tsml_log_updates) && !empty($tsml_log_updates)) {
        $tsml_log = tsml_get_option_array('tsml_log');
        $tsml_log = array_filter($tsml_log, 'is_array');
        // trim to last 30 days
        $cutoff = time() - (30 * 24 * 60 * 60);
        $tsml_log = array_filter($tsml_log, function ($entry) use ($cutoff) {
            if (!is_array($entry) || !isset($entry['timestamp'])) {
                return false;
            }
            // timestamp can be string (previous) or number (current)
            $time = is_numeric($entry['timestamp']) ? intval($entry['timestamp']) : strtotime($entry['timestamp']);
            return $time && $cutoff < $time;
        });
        // add updates
        $tsml_log = array_merge($tsml_log_updates, $tsml_log);
        update_option('tsml_log', $tsml_log);
    }
}

/**
 * get lot entries
 * @param array $args [optional] args to filters results
 *   [type] event type to filter
 *   [count] limit how many returned
 *   [start] offset to start returning
 */
function tsml_log_get($args = array())
{
    $args = (array) $args;
    $tsml_log = tsml_get_option_array('tsml_log');
    $tsml_log = array_filter($tsml_log, 'is_array');

    if (isset($args['type'])) {
        $entry_type = strval($args['type']);
        $tsml_log = array_filter($tsml_log, function ($entry) use ($entry_type) {
            return isset($entry['type']) && $entry['type'] === $entry_type;
        });
    }
    $count = isset($args['count']) ? intval($args['count']) : 0;
    $start = isset($args['start']) ? intval($args['start']) : 0;
    if ($count) {
        $tsml_log = array_slice($tsml_log, $start, $count);
    }
    return $tsml_log;
}
