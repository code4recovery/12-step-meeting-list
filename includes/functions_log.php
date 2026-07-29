<?php

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
        'type' => sanitize_key($type),
        'timestamp' => time(),
    ];

    // optional variables
    if ($info) {
        $entry['info'] = tsml_log_sanitize($info);
    }
    if (!empty($input)) {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $key = sanitize_key($key);
                // don't let an input key clobber the entry's own fields
                if (!$key || in_array($key, ['type', 'timestamp'], true)) {
                    continue;
                }
                $entry[$key] = ('meeting_id' === $key) ? intval($value) : tsml_log_sanitize($value);
            }
        } else {
            $entry['input'] = tsml_log_sanitize($input);
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
 * sanitize a value before it is stored in the log
 * log values can originate from unauthenticated requests (the geocode endpoint
 * passes a visitor-supplied address) and from remote data source feeds, so
 * strip markup and cap the length before any of it reaches the database
 *
 * @param mixed $value
 * @return string
 */
function tsml_log_sanitize($value)
{
    if (is_array($value) || is_object($value)) {
        $value = print_r($value, true);
    } elseif (is_bool($value)) {
        $value = $value ? '1' : '';
    }

    // sanitize_text_field strips tags and control characters
    $value = sanitize_text_field(strval($value));

    // keep a single bad entry from bloating the tsml_log option
    if (function_exists('mb_substr') ? mb_strlen($value) > 1000 : strlen($value) > 1000) {
        $value = (function_exists('mb_substr') ? mb_substr($value, 0, 1000) : substr($value, 0, 1000)) . '…';
    }

    return $value;
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
            // previous timestamps are mysqldate strings, new are epoch numbers
            $time = intval($entry['timestamp']);
            return $time && $cutoff < $time;
        });
        // add updates
        $tsml_log = array_merge($tsml_log_updates, $tsml_log);

        // upper limit to log entries
        $tsml_log = array_slice($tsml_log, 0, 1000);

        update_option('tsml_log', $tsml_log);
    }
}

/**
 * get log entries
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

/**
 * Return escaped string output for log entry message (input)
 * entries stored before log sanitization was added may still contain markup,
 * so this always escapes and the return value is safe to echo
 *
 * @param array $entry
 * @return string
 */
function tsml_log_format_entry_msg($entry)
{
    $entry = (array) $entry;
    $msg = !empty($entry['input']) ? esc_html(strval($entry['input'])) : '';
    if ($msg && !empty($entry['meeting_id'])) {
        $url = admin_url('post.php?post=' . intval($entry['meeting_id']) . '&action=edit');
        $msg = '<a href="' . esc_url($url) . '">' . $msg . '</a>';
    }
    return $msg;
}

/**
 * Return escaped string output for log entry info
 * @param array $entry
 * @return string
 */
function tsml_log_format_entry_info($entry)
{
    $entry = (array) $entry;
    return !empty($entry['info']) ? esc_html(strval($entry['info'])) : '';
}