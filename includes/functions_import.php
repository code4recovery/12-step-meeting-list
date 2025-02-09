<?php

/**
 * sanitize and import an array of meetings to an 'import buffer' (an wp_option that's iterated on progressively)
 * called from admin_import.php (both CSV and JSON)
 * 
 * @param mixed $meetings
 * @param mixed $data_source_url
 * @param mixed $data_source_parent_region_id
 * @return void
 */
function tsml_import_buffer_set($meetings, $data_source_url = null, $data_source_parent_region_id = null)
{
    global $tsml_programs, $tsml_program, $tsml_days, $tsml_meeting_attendance_options, $tsml_data_sources;

    /* 
     * Most of the meeting transformation code has been extracted to a function so it can be executed early before the the Data Comparison code.
     * Only those feed records deemed to be out-of-sync with those already stored locally will be passed here. Google Sheets and CSV Imports do
     * not pass through the compare code and are passed here directly where the full meetings feed is transformed before updating. 
     * */

    foreach ($meetings as $i => $meeting) {
        // If the meeting doesn't have a data_source, use the one from the function call
        $meeting_data_source = isset($meeting['data_source']) ? $meeting['data_source'] : '';
        if (empty($meeting_data_source)) {
            $meetings[$i]['data_source'] = $data_source_url;
        } else {
            // Check if this data sources is in our list of feeds
            if (!array_key_exists($meeting_data_source, $tsml_data_sources)) {
                // Not already there, so add it
                $tsml_data_sources[$meeting_data_source] = [
                    'status' => 'OK',
                    'last_import' => current_time('timestamp'),
                    'count_meetings' => 0,
                    'name' => parse_url($meeting_data_source, PHP_URL_HOST),
                    'parent_region_id' => $data_source_parent_region_id,
                    'change_detect' => null,
                    'type' => 'JSON',
                ];
            }
        }

        $meetings[$i]['data_source_parent_region_id'] = $data_source_parent_region_id;
    }

    // save data source configuration
    update_option('tsml_data_sources', $tsml_data_sources);

    // prepare import buffer in wp_options
    update_option('tsml_import_buffer', $meetings, false);
}

/**
 * Build output table to report on import changes
 * @param array $change_log Built from tsml_import_get_changed_meetings()
 * @return string
 */
function tsml_import_build_change_report($change_log)
{
    global $tsml_days;
    $message = '<table style="width:100%;margin-bottom:10px;text-align:left;border:1px solid #dddddd;padding:8px;border-spacing:5px">';

    foreach ($change_log as $change_entry) {
        $meeting_id = isset($change_entry['meeting_id']) ? $change_entry['meeting_id'] : '';
        $meeting = (array) $change_entry['meeting'];
        $meeting_name = isset($meeting['name']) ? $meeting['name'] : $meeting['slug'];
        $meeting_day_val = intval(isset($meeting['day']) ? $meeting['day'] : -1);
        $meeting_day = isset($tsml_days[$meeting_day_val]) ? $tsml_days[$meeting_day_val] : '?';
        $meeting_time = isset($meeting['time']) ? tsml_format_time($meeting['time']) : '?';
        $notes = isset($change_entry['notes']) ? $change_entry['notes'] : '';

        $permalink = get_permalink($meeting_id);
        if ($permalink !== false) {
            $meeting_name_linked = '<a href=' . $permalink . '>' . $meeting_name . '</a><br>';
        } else {
            $meeting_name_linked = $meeting_name;
        }
        $message .= '<tr><td>' . $meeting_name_linked . '  ' . $meeting_day . ' @ ' . $meeting_time . ' </td>' . '<td>' . $notes . ' </td></tr>';
    }
    $message .= '</table>';
    return $message;
}

/**
 * Checks a list of imported meetings for necessary import changes / updates
 *
 * @param array  $feed_meetings                array of feed meetings to test for changes
 * @param string $data_source_url              feed source url
 * @param int    $data_source_parent_region_id feed parent region id
 * @return array[array, array, array]
 *       $import_meetings    array of imported meetings that need to be processes
 *       $delete_meeting_ids array of existing meeting post id's to delete
 *       $change_log         array of changes to existing
 */
function tsml_import_get_changed_meetings($feed_meetings, $data_source_url)
{
    $import_meetings = $delete_meeting_ids = $source_meetings = $found_meeting_ids = [];
    $change_log = [];

    // get local meetings
    $data_source_ids = tsml_get_data_source_ids($data_source_url);
    sort($data_source_ids);

    // filter out all but the data source meetings
    $source_meetings = tsml_get_meetings(['data_source' => $data_source_url], false, true);

    // create array of database slugs for matching
    $meeting_slugs_map = array_column($source_meetings, 'slug', 'id');
    $meeting_ids = array_keys($meeting_slugs_map);

    // create another array of the database source_slugs for matching to the original slug which may be overriden by the importer
    $meeting_source_slugs_map = array_column($source_meetings, 'source_slug', 'id');

    // list changed and new meetings found in the data source feed
    foreach ($feed_meetings as $index => $feed_meeting) {
        $feed_meeting_slug = $source_meeting = $source_meeting_id = null;

        if (empty($feed_meeting) || !is_array($feed_meeting)) {
            continue;
        }

        $feed_meeting_slug = isset($feed_meeting['slug']) ? $feed_meeting['slug'] : null;
        $source_meeting_id = array_search($feed_meeting_slug, $meeting_source_slugs_map);
        if (!$source_meeting_id) {
            $source_meeting_id = array_search($feed_meeting_slug, $meeting_slugs_map);
        }
        if ($source_meeting_id) {
            $source_meeting_index = array_search($source_meeting_id, $meeting_ids);
            $source_meeting = ($source_meeting_index !== false) ? $source_meetings[$source_meeting_index] : null;
        }
        // if we found a local meeting, compare for update need
        if ($source_meeting) {
            $source_meeting = (array) $source_meeting;
            $found_meeting_ids[] = $source_meeting['id'];
            $changed_fields = tsml_compare_imported_meeting($source_meeting, $feed_meeting);
            if (!empty($changed_fields)) {
                $change_log[] = array(
                    'action' => 'update',
                    'description' => __('Updated', '12-step-meeting-list'),
                    'notes' => __('Changed', '12-step-meeting-list') . ': ' . implode(', ', $changed_fields),
                    'changed_fields' => $changed_fields,
                    'meeting' => $feed_meeting,
                    'meeting_id' => $source_meeting_id,
                );

                // add `ID` field to meeting to trigger an existing post update versus new post insert
                $feed_meeting['ID'] = $source_meeting_id;
                $import_meetings[] = $feed_meeting;
            }
        } else {
            $change_log[] = array(
                'action' => 'add',
                'description' => __('New', '12-step-meeting-list'),
                'notes' => __('New', '12-step-meeting-list'),
                'meeting' => $feed_meeting,
            );
            $import_meetings[] = $feed_meeting;
        }
    }

    // meetings to delete will be any we didn't find in feed
    $delete_meeting_ids = array_diff($meeting_ids, $found_meeting_ids);
    // add to change log
    foreach ($delete_meeting_ids as $delete_meeting_id) {
        $source_meeting_index = array_search($delete_meeting_id, $meeting_ids);
        $delete_meeting = ($source_meeting_index !== false) ? $source_meetings[$source_meeting_index] : null;
        $change_log[] = array(
            'action' => 'remove',
            'description' => __('Removed / Missing', '12-step-meeting-list'),
            'notes' => __('Removed', '12-step-meeting-list'),
            'meeting' => $delete_meeting,
            'meeting_id' => $delete_meeting_id,
        );
    }

    return array($import_meetings, $delete_meeting_ids, $change_log);
}

/**
 * filter workaround for setting post_modified dates
 * used in tsml_ajax_import
 * 
 * @param mixed $data
 * @param mixed $postarr
 * @return mixed
 */
function tsml_import_post_modified($data, $postarr)
{

    if (!empty($postarr['post_modified'])) {
        $data['post_modified'] = $postarr['post_modified'];
    }
    if (!empty($postarr['post_modified_gmt'])) {
        $data['post_modified_gmt'] = $postarr['post_modified_gmt'];
    }
    return $data;
}

/**
 * function: translates a Meeting Guide format Google Sheet to proper format for import
 * used: tsml_import_buffer_set
 * 
 * @param mixed $data
 * @return array
 */
function tsml_import_reformat_googlesheet($data)
{
    $meetings = [];

    $header = array_shift($data['values']);
    $header = array_map('sanitize_title_with_dashes', $header);
    $header = str_replace('-', '_', $header);
    $header_count = count($header);

    foreach ($data['values'] as $row) {

        // creates a meeting array with elements corresponding to each column header of the Google Sheet; updated for Google Sheets v4 API
        $meeting = [];
        for ($j = 0; $j < $header_count; $j++) {
            if (isset($row[$j])) {
                $meeting[$header[$j]] = $row[$j];
            }
        }

        array_push($meetings, $meeting);
    }

    return $meetings;
}

/**
 * return array of sanitized meetings
 * sanitizes imported meetings before processing
 * 
 * @param mixed $meetings
 * @param mixed $data_source_url
 * @param mixed $data_source_parent_region_id
 * @return array|object
 */
function tsml_import_sanitize_meetings($meetings, $data_source_url = null, $data_source_parent_region_id = null)
{

    global $tsml_programs, $tsml_program, $tsml_days, $tsml_meeting_attendance_options, $tsml_data_sources, $tsml_contact_fields, $tsml_entity_fields, $tsml_array_fields;

    //track group fields and unique_group_values
    $group_fields = array_keys($tsml_contact_fields);
    for ($i = 1; $i <= TSML_GROUP_CONTACT_COUNT; $i++) {
        foreach (['name', 'phone', 'email'] as $field) {
            $group_fields[] = 'contact_' . $i . '_' . $field;
        }
    }
    $group_fields[] = 'group_notes';
    $groups = [];

    // track sanitized meeting slug counts
    $meeting_slugs = array();

    // handle requests to Google Sheets API (obsolete - remove on or after May 2025)
    if (strpos($data_source_url, "sheets.googleapis.com") !== false) {
        tsml_alert(__('You can now add a Google Sheet directly to TSML. Please replace this feed with the Sheet URL. We will be dropping support for the Google Sheets API in a future release.', '12-step-meeting-list'), 'warning');
        $meetings = tsml_import_reformat_googlesheet($meetings);
    }

    // handle requests to C4R sheets service
    if (strpos($data_source_url, "sheets.code4recovery.org/tsml") !== false) {
        if (count($meetings['warnings'])) {
            $warnings = __('The following issues were detected with this Google Sheet:', '12-step-meeting-list') . '</p><ol>' .
                implode(array_map(function ($warning) {
                    return '<li><a href="' . $warning['link'] . '">' . $warning['error'] . '</a>: ' .
                        implode(array_map(function ($value) {
                            return '<code>' . $value . '</code>';
                        }, $warning['value'])) .
                        '</li>';
                }, $meetings['warnings'])) .
                '</ol><p hidden>';
            tsml_alert($warnings, 'warning');
        }
        $meetings = $meetings['meetings'];
    }

    // allow theme-defined function to reformat data source import - issue #439
    if (function_exists('tsml_import_reformat')) {
        // phpcs:ignore
        $meetings = tsml_import_reformat($meetings);
    }

    // uppercasing for value matching later
    $upper_types = array_map('strtoupper', $tsml_programs[$tsml_program]['types']);
    $upper_days = array_map('strtoupper', $tsml_days);

    // get users, keyed by username
    $users = [];
    foreach (get_users(['fields' => ['ID', 'user_login'],]) as $user) {
        $users[$user->user_login] = $user->ID;
    }

    $user_id = get_current_user_id();

    // convert the array to UTF-8
    if (function_exists('mb_detect_encoding')) {
        array_walk_recursive($meetings, function ($value) {
            if (!mb_detect_encoding($value, 'utf-8', true)) {
                return (string) mb_convert_encoding($value, 'UTF-8', 'auto');
            }
            return $value;
        });
    }

    // trim and sanitize everything
    array_walk_recursive($meetings, function (&$value, $key) {
        // preserve <br>s as line breaks if present, otherwise clean up
        $value = preg_replace('/\<br(\s*)?\/?\>/i', PHP_EOL, $value);
        $value = stripslashes($value);
        $value = trim($value);

        // turn "string" into string (only do if on both ends though)
        if ((substr($value, 0, 1) == '"') && (substr($value, -1) == '"')) {
            $value = trim(trim($value, '"'));
        }
        // sanitize
        if (tsml_string_ends($key, 'notes')) {
            $value = tsml_sanitize_text_area($value);
        } else {
            $value = sanitize_text_field($value);
        }

        return $value;
    });

    // check for any meetings with arrays of days and creates an individual meeting for each day in array
    $meetings_to_add = [];
    $indexes_to_remove = [];

    for ($i = 0; $i < count($meetings); $i++) {

        // figure out a singular slug from available sources, starting with 'slug' (of course)
        $meeting_slug = '';
        foreach (array('slug', 'post_name', 'ID', 'id', 'name', 'title', 'group', 'location') as $field) {
            if (isset($meetings[$i][$field])) {
                $meeting_slug = sanitize_title($meetings[$i][$field]);
            }
            if ($meeting_slug) {
                break;
            }
        }

        // if we have no slug at this point, something went wrong - remove the meeting and continue
        if (!$meeting_slug) {
            array_splice($meetings, $i, 1);
            --$i;
            continue;
        }

        // ensure every slug is unique for this import list
        if (isset($meeting_slugs[$meeting_slug])) {
            while (isset($meeting_slugs[$meeting_slug . '-' . $meeting_slugs[$meeting_slug]]) && $meeting_slugs[$meeting_slug] < 999) {
                $meeting_slugs[$meeting_slug]++;
            }
            $meeting_slug = $meeting_slug . '-' . $meeting_slugs[$meeting_slug]++;
        } else {
            $meeting_slugs[$meeting_slug] = 1;
        }
        $meetings[$i]['slug'] = $meeting_slug;

        if (isset($meetings[$i]['day']) && is_array($meetings[$i]['day'])) {
            array_push($indexes_to_remove, $i);
            foreach ($meetings[$i]['day'] as $single_day) {
                $temp_meeting = $meetings[$i];
                $temp_meeting['day'] = $single_day;
                $temp_meeting['slug'] = $meetings[$i]['slug'] . "-" . $single_day;
                array_push($meetings_to_add, $temp_meeting);
            }
        }
    }

    for ($i = 0; $i < count($indexes_to_remove); $i++) {
        unset($meetings[$indexes_to_remove[$i]]);
    }

    $meetings = array_merge($meetings, $meetings_to_add);

    // prepare array for import buffer
    $count_meetings = count($meetings);
    for ($i = 0; $i < $count_meetings; $i++) {

        // column aliases
        if (empty($meetings[$i]['postal_code']) && !empty($meetings[$i]['zip'])) {
            $meetings[$i]['postal_code'] = $meetings[$i]['zip'];
        }
        if (empty($meetings[$i]['name']) && !empty($meetings[$i]['meeting'])) {
            $meetings[$i]['name'] = $meetings[$i]['meeting'];
        }
        if (empty($meetings[$i]['location']) && !empty($meetings[$i]['location_name'])) {
            $meetings[$i]['location'] = $meetings[$i]['location_name'];
        }
        if (empty($meetings[$i]['time']) && !empty($meetings[$i]['start_time'])) {
            $meetings[$i]['time'] = $meetings[$i]['start_time'];
        }

        // if '@' is in address, remove it and everything after
        if (!empty($meetings[$i]['address']) && $pos = strpos($meetings[$i]['address'], '@')) {
            $meetings[$i]['address'] = trim(substr($meetings[$i]['address'], 0, $pos));
        }

        // if location name is missing, use address
        if (empty($meetings[$i]['location'])) {
            $meetings[$i]['location'] = empty($meetings[$i]['address']) ? __('Meeting Location', '12-step-meeting-list') : $meetings[$i]['address'];
        }

        // day can either be 0, 1, 2, 3 or Sunday, Monday, or empty
        if (isset($meetings[$i]['day']) && !array_key_exists($meetings[$i]['day'], $upper_days)) {
            $meetings[$i]['day'] = array_search(strtoupper($meetings[$i]['day']), $upper_days);
        }

        // sanitize time & day
        if (empty($meetings[$i]['time']) || ($meetings[$i]['day'] === false)) {
            $meetings[$i]['time'] = $meetings[$i]['end_time'] = $meetings[$i]['day'] = false; //by appointment

            // if meeting name missing, use location
            if (empty($meetings[$i]['name'])) {
                // translators: %s is the location name
                $meetings[$i]['name'] = sprintf(__('%s by Appointment', '12-step-meeting-list'), $meetings[$i]['location']);
            }
        } else {
            // if meeting name missing, use location, day, and time
            if (empty($meetings[$i]['name'])) {
                // translators: %1s is the location name, %2s is the day, %3s is the time
                $meetings[$i]['name'] = sprintf(__('%1$1s %2$2s at %3$3s', '12-step-meeting-list'), $meetings[$i]['location'], $tsml_days[$meetings[$i]['day']], $meetings[$i]['time']);
            }

            $meetings[$i]['time'] = tsml_format_time_reverse($meetings[$i]['time']);
            if (!empty($meetings[$i]['end_time'])) {
                $meetings[$i]['end_time'] = tsml_format_time_reverse($meetings[$i]['end_time']);
            }
        }

        // google prefers USA for geocoding
        if (!empty($meetings[$i]['country']) && $meetings[$i]['country'] == 'US') {
            $meetings[$i]['country'] = 'USA';
        }

        // build address
        if (empty($meetings[$i]['formatted_address'])) {
            $address = [];
            if (!empty($meetings[$i]['address'])) {
                $address[] = $meetings[$i]['address'];
            }
            if (!empty($meetings[$i]['city'])) {
                $address[] = $meetings[$i]['city'];
            }
            if (!empty($meetings[$i]['state'])) {
                $address[] = $meetings[$i]['state'];
            }
            if (!empty($meetings[$i]['postal_code'])) {
                if ((strlen($meetings[$i]['postal_code']) < 5) && ($meetings[$i]['country'] == 'USA')) {
                    $meetings[$i]['postal_code'] = str_pad($meetings[$i]['postal_code'], 5, '0', STR_PAD_LEFT);
                }
                $address[] = $meetings[$i]['postal_code'];
            }
            if (!empty($meetings[$i]['country'])) {
                $address[] = $meetings[$i]['country'];
            }
            $meetings[$i]['formatted_address'] = implode(', ', $address);
        }

        // notes
        if (empty($meetings[$i]['notes'])) {
            $meetings[$i]['notes'] = '';
        }
        if (empty($meetings[$i]['location_notes'])) {
            $meetings[$i]['location_notes'] = '';
        }
        if (empty($meetings[$i]['group_notes'])) {
            $meetings[$i]['group_notes'] = '';
        }

        // updated
        if (empty($meetings[$i]['updated']) || (!$meetings[$i]['updated'] = strtotime($meetings[$i]['updated']))) {
            $meetings[$i]['updated'] = time();
        }
        $meetings[$i]['post_modified'] = date('Y-m-d H:i:s', $meetings[$i]['updated']);
        $meetings[$i]['post_modified_gmt'] = get_gmt_from_date($meetings[$i]['post_modified']);

        // author
        if (!empty($meetings[$i]['author']) && array_key_exists($meetings[$i]['author'], $users)) {
            $meetings[$i]['post_author'] = $users[$meetings[$i]['author']];
        } else {
            $meetings[$i]['post_author'] = $user_id;
        }

        // default region to city if not specified
        if (empty($meetings[$i]['region']) && !empty($meetings[$i]['city'])) {
            $meetings[$i]['region'] = $meetings[$i]['city'];
        }

        // sanitize types (they can be Closed or C)
        if (empty($meetings[$i]['types'])) {
            $meetings[$i]['types'] = [];
        }
        $types = $meetings[$i]['types'];
        if (is_string($types)) {
            $types = explode(',', $types);
        }
        $meetings[$i]['types'] = $unused_types = [];
        foreach ($types as $type) {
            $type = trim($type);
            $upper_type = strtoupper($type);
            if (!$type) {
                continue;
            }
            if (in_array($upper_type, array_map('strtoupper', array_keys($upper_types)))) {
                $meetings[$i]['types'][] = $type;
            } elseif (in_array($upper_type, array_values($upper_types))) {
                $meetings[$i]['types'][] = array_search($upper_type, $upper_types);
            } else {
                $unused_types[] = $type;
            }
        }

        // if a meeting is both open and closed, make it closed
        if (in_array('C', $meetings[$i]['types']) && in_array('O', $meetings[$i]['types'])) {
            $meetings[$i]['types'] = array_diff($meetings[$i]['types'], ['O']);
        }

        // append unused types to notes
        if (count($unused_types)) {
            if (!empty($meetings[$i]['notes'])) {
                $meetings[$i]['notes'] .= str_repeat(PHP_EOL, 2);
            }
            $meetings[$i]['notes'] .= trim(implode(', ', $unused_types));
        }

        // If Conference URL, validate; or if phone, force 'ONL' type, else remove 'ONL'
        $meetings[$i]['types'] = array_values(array_diff($meetings[$i]['types'], ['ONL']));
        if (!empty($meetings[$i]['conference_url'])) {
            $url = esc_url_raw($meetings[$i]['conference_url'], ['http', 'https']);
            if (tsml_conference_provider($url)) {
                $meetings[$i]['conference_url'] = $url;
                $meetings[$i]['types'][] = 'ONL';
            } else {
                $meetings[$i]['conference_url'] = null;
                $meetings[$i]['conference_url_notes'] = null;
            }
        }
        if (!empty($meetings[$i]['conference_phone']) && empty($meetings[$i]['conference_url'])) {
            $meetings[$i]['types'][] = 'ONL';
        }
        if (empty($meetings[$i]['conference_phone'])) {
            $meetings[$i]['conference_phone_notes'] = null;
        }

        // Clean up attendance options
        if (!empty($meetings[$i]['attendance_option'])) {
            $meetings[$i]['attendance_option'] = trim(strtolower($meetings[$i]['attendance_option']));
            if (!array_key_exists($meetings[$i]['attendance_option'], $tsml_meeting_attendance_options)) {
                $meetings[$i]['attendance_option'] = '';
            }
        }

        // make sure we're not double-listing types
        $meetings[$i]['types'] = array_unique($meetings[$i]['types']);

        // clean up
        foreach (['address', 'city', 'state', 'postal_code', 'country', 'updated'] as $key) {
            if (isset($meetings[$i][$key])) {
                unset($meetings[$i][$key]);
            }
        }

        // track group values
        if (isset($meetings[$i]['group']) && !empty($meetings[$i]['group'])) {
            $group = $meetings[$i]['group'];
            if (!isset($groups[$group])) {
                $groups[$group] = array();
            }
            // currently first group value wins
            // @TODO: add some weighting to track / use most common value by occurence
            foreach ($group_fields as $group_field) {
                if (isset($meetings[$i][$group_field]) && !empty($meetings[$i][$group_field]) && !isset($groups[$group][$group_field])) {
                    $groups[$group][$group_field] = $meetings[$i][$group_field];
                }
            }
        }

        // cleanup entity fields
        foreach ($tsml_entity_fields as $field) {
            if (isset($meetings[$i][$field])) {
                // feedback_emails is an array
                if (in_array($field, $tsml_array_fields, true)) {
                    $meetings[$i][$field] = array_values((array) $meetings[$i][$field]);
                } else {
                    $meetings[$i][$field] = substr(trim(strval($meetings[$i][$field])), 0, 100);
                }
            }
        }

        // preserve row number for errors later
        $meetings[$i]['row'] = $i + 2;
    }

    // normalize group fields across potentially blank rows
    foreach ($meetings as $i => $meeting) {
        $group = isset($meeting['group']) ? $meeting['group'] : '';
        if (!$group) {
            continue;
        }
        foreach ($groups[$group] as $field => $value) {
            $meetings[$i][$field] = $value;
        }
    }

    // allow user-defined function to filter the meetings (for gal-aa.org)
    if (function_exists('tsml_import_filter')) {
        $meetings = array_filter($meetings, 'tsml_import_filter');
    }

    return $meetings;
}
