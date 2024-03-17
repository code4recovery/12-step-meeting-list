<?php

//import and settings

if (!function_exists('tsml_import_page')) {

    function tsml_import_page()
    {
        global $tsml_data_sources, $tsml_programs, $tsml_program, $tsml_nonce, $tsml_sharing, $tsml_slug, $tsml_change_detect;

        // todo consider whether this check is necessary, since it is run from add_submenu_page() which is already checking for the same permission
        // potentially tsml_import_page() could be a closure within the call to add_submenu_page which would prevent it from being reused elsewhere
        tsml_require_meetings_permission();

        $error = false;
        $tsml_data_sources = tsml_get_option_array('tsml_data_sources');

        //if posting a CSV, check for errors and add it to the import buffer
        if (isset($_FILES['tsml_import']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
            ini_set('auto_detect_line_endings', 1); //to handle mac \r line endings
            $extension = explode('.', strtolower($_FILES['tsml_import']['name']));
            $extension = end($extension);
            if ($_FILES['tsml_import']['error'] > 0) {
                if ($_FILES['tsml_import']['error'] == 1) {
                    $error = __('The uploaded file exceeds the <code>upload_max_filesize</code> directive in php.ini.', '12-step-meeting-list');
                } elseif ($_FILES['tsml_import']['error'] == 2) {
                    $error = __('The uploaded file exceeds the <code>MAX_FILE_SIZE</code> directive that was specified in the HTML form.', '12-step-meeting-list');
                } elseif ($_FILES['tsml_import']['error'] == 3) {
                    $error = __('The uploaded file was only partially uploaded.', '12-step-meeting-list');
                } elseif ($_FILES['tsml_import']['error'] == 4) {
                    $error = __('No file was uploaded.', '12-step-meeting-list');
                } elseif ($_FILES['tsml_import']['error'] == 6) {
                    $error = __('Missing a temporary folder.', '12-step-meeting-list');
                } elseif ($_FILES['tsml_import']['error'] == 7) {
                    $error = __('Failed to write file to disk.', '12-step-meeting-list');
                } elseif ($_FILES['tsml_import']['error'] == 8) {
                    $error = __('A PHP extension stopped the file upload.', '12-step-meeting-list');
                } else {
                    $error = sprintf(__('File upload error #%d', '12-step-meeting-list'), $_FILES['tsml_import']['error']);
                }
            } elseif (empty($extension)) {
                $error = __('Uploaded file did not have a file extension. Please add .csv to the end of the file name.', '12-step-meeting-list');
            } elseif (!in_array($extension, ['csv', 'txt'])) {
                $error = sprintf(__('Please upload a csv file. Your file ended in .%s.', '12-step-meeting-list'), $extension);
            } elseif (!$handle = fopen($_FILES['tsml_import']['tmp_name'], 'r')) {
                $error = __('Error opening CSV file', '12-step-meeting-list');
            } else {

                //extract meetings from CSV
                while (($data = fgetcsv($handle, 3000, ',')) !== false) {
                    //skip empty rows
                    if (strlen(trim(implode($data)))) {
                        $meetings[] = $data;
                    }
                }

                //remove any rows that aren't arrays
                $meetings = array_filter($meetings, 'is_array');

                //crash if no data
                if (count($meetings) < 2) {
                    $error = __('Nothing was imported because no data rows were found.', '12-step-meeting-list');
                } else {

                    //allow theme-defined function to reformat CSV prior to import (New Hampshire, Ventura)
                    if (function_exists('tsml_import_reformat')) {
                        $meetings = tsml_import_reformat($meetings);
                    }

                    //if it's FNV data, reformat it
                    $meetings = tsml_import_reformat_fnv($meetings);

                    //get header
                    $header = array_shift($meetings);
                    $header = array_map('sanitize_title_with_dashes', $header);
                    $header = str_replace('-', '_', $header);
                    $header_count = count($header);

                    //check header for required fields
                    if (!in_array('address', $header) && !in_array('city', $header)) {
                        $error = __('Either Address or City is required.', '12-step-meeting-list');
                    } else {

                        //loop through data and convert to array
                        foreach ($meetings as &$meeting) {
                            //check length
                            if ($header_count > count($meeting)) {
                                $meeting = array_pad($meeting, $header_count, null);
                            } elseif ($header_count < count($meeting)) {
                                $meeting = array_slice($meeting, 0, $header_count);
                            }

                            //associate
                            $meeting = array_combine($header, $meeting);
                        }

                        //import into buffer, also done this way in data source import
                        tsml_import_buffer_set($meetings);

                        //run deletes
                        if ($_POST['delete'] == 'regions') {

                            //get all regions present in array
                            $regions = [];
                            foreach ($meetings as $meeting) {
                                $regions[] = empty($meeting['sub_region']) ? $meeting['region'] : $meeting['sub_region'];
                            }

                            //get locations for those meetings
                            $location_ids = get_posts([
                                'post_type' => 'tsml_location',
                                'numberposts' => -1,
                                'fields' => 'ids',
                                'tax_query' => [
                                    [
                                        'taxonomy' => 'tsml_region',
                                        'field' => 'name',
                                        'terms' => array_unique($regions),
                                    ],
                                ],
                            ]);

                            //get posts for those meetings
                            $meeting_ids = get_posts([
                                'post_type' => 'tsml_meeting',
                                'numberposts' => -1,
                                'fields' => 'ids',
                                'post_parent__in' => $location_ids,
                            ]);

                            tsml_delete($meeting_ids);

                            tsml_delete_orphans();
                        } elseif ($_POST['delete'] == 'no_data_source') {

                            tsml_delete(get_posts([
                                'post_type' => 'tsml_meeting',
                                'numberposts' => -1,
                                'fields' => 'ids',
                                'meta_query' => [
                                    [
                                        'key' => 'data_source',
                                        'compare' => 'NOT EXISTS',
                                        'value' => '',
                                    ],
                                ],
                            ]));

                            tsml_delete_orphans();
                        } elseif ($_POST['delete'] == 'all') {
                            tsml_delete('everything');
                        }
                    }
                }
            }
        }

        //add data source
        if (!empty($_POST['tsml_add_data_source']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {

            //sanitize URL, name, parent region id, and Change Detection values
            $data_source_url = trim(esc_url_raw($_POST['tsml_add_data_source'], array('http', 'https')));
            $data_source_name = sanitize_text_field($_POST['tsml_add_data_source_name']);
            $data_source_parent_region_id = (int) $_POST['tsml_add_data_source_parent_region_id'];
            $data_source_change_detect = sanitize_text_field($_POST['tsml_add_data_source_change_detect']);

            //try fetching	
            $response = wp_safe_remote_get($data_source_url, [
                'timeout' => 30,
                'sslverify' => false,
            ]);
            if (is_array($response) && !empty($response['body']) && ($body = json_decode($response['body'], true))) {

                //if already set, hard refresh
                if (array_key_exists($data_source_url, $tsml_data_sources)) {
                    tsml_delete(tsml_get_data_source_ids($data_source_url));
                    tsml_delete_orphans();
                }

                $tsml_data_sources[$data_source_url] = [
                    'status' => 'OK',
                    'last_import' => current_time('timestamp'),
                    'count_meetings' => 0,
                    'name' => $data_source_name,
                    'parent_region_id' => $data_source_parent_region_id,
                    'change_detect' => $data_source_change_detect,
                    'type' => 'JSON',
                ];

                //import feed
                tsml_import_buffer_set($body, $data_source_url, $data_source_parent_region_id);

                //save data source configuration
                update_option('tsml_data_sources', $tsml_data_sources);

                // Create a cron job to run daily when Change Detection is enabled for the new data source
                if ($data_source_change_detect === 'enabled') {
                    tsml_schedule_import_scan($data_source_url, $data_source_name);
                }
            } elseif (!is_array($response)) {

                tsml_alert(__('Invalid response, <pre>' . print_r($response, true) . '</pre>.', '12-step-meeting-list'), 'error');
            } elseif (empty($response['body'])) {

                tsml_alert(__('Data source gave an empty response, you might need to try again.', '12-step-meeting-list'), 'error');
            } else {

                switch (json_last_error()) {
                    case JSON_ERROR_NONE:
                        tsml_alert(__('JSON: no errors.', '12-step-meeting-list'), 'error');
                        break;
                    case JSON_ERROR_DEPTH:
                        tsml_alert(__('JSON: Maximum stack depth exceeded.', '12-step-meeting-list'), 'error');
                        break;
                    case JSON_ERROR_STATE_MISMATCH:
                        tsml_alert(__('JSON: Underflow or the modes mismatch.', '12-step-meeting-list'), 'error');
                        break;
                    case JSON_ERROR_CTRL_CHAR:
                        tsml_alert(__('JSON: Unexpected control character found.', '12-step-meeting-list'), 'error');
                        break;
                    case JSON_ERROR_SYNTAX:
                        tsml_alert(__('JSON: Syntax error, malformed JSON.', '12-step-meeting-list'), 'error');
                        break;
                    case JSON_ERROR_UTF8:
                        tsml_alert(__('JSON: Malformed UTF-8 characters, possibly incorrectly encoded.', '12-step-meeting-list'), 'error');
                        break;
                    default:
                        tsml_alert(__('JSON: Unknown error.', '12-step-meeting-list'), 'error');
                        break;
                }
            }
        }

        //check for existing import buffer
        $meetings = tsml_get_option_array('tsml_import_buffer');

        //remove data source
        if (!empty($_POST['tsml_remove_data_source']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {

            //sanitize URL
            $_POST['tsml_remove_data_source'] = esc_url_raw($_POST['tsml_remove_data_source'], ['http', 'https']);

            if (array_key_exists($_POST['tsml_remove_data_source'], $tsml_data_sources)) {

                //remove all meetings for this data source
                tsml_delete(tsml_get_data_source_ids($_POST['tsml_remove_data_source']));

                //clean up orphaned locations & groups
                tsml_delete_orphans();

                //remove data source
                unset($tsml_data_sources[$_POST['tsml_remove_data_source']]);
                update_option('tsml_data_sources', $tsml_data_sources);

                tsml_alert(__('Data source removed.', '12-step-meeting-list'));
            }
        }
        ?>

        <!-- Admin page content should all be inside .wrap -->
        <div class="wrap">

            <h1></h1> <!-- Set alerts here -->

            <div class="stack">

                <?php if ($error) { ?>
                    <div class="error inline">
                        <p>
                            <?php echo $error ?>
                        </p>
                    </div>
                <?php } elseif ($total = count($meetings)) { ?>
                    <div id="tsml_import_progress" class="progress" data-total="<?php echo $total ?>">
                        <div class="progress-bar"></div>
                    </div>
                    <ol id="tsml_import_errors" class="error inline hidden"></ol>
                <?php } ?>

                <!-- Import Data Sources -->
                <div class="postbox stack">
                    <h2>
                        <?php _e('Import Data Sources', '12-step-meeting-list') ?>
                    </h2>
                    <p>
                        <?php printf(__('Data sources are JSON feeds that contain a website\'s public meeting data. They can be used to aggregate meetings from different sites into a single master list. 
				Data sources listed below will pull meeting information into this website. A configurable schedule allows for each enabled data source to be scanned at least once per day looking 
				for updates to the listing. Change Notification email addresses are sent an email when action is required to re-sync a data source with its meeting list information. 
				Please note: records that you intend to maintain on your website should always be imported using the Import CSV feature below. <b>Data Source records will be overwritten when the 
				parent data source is refreshed.</b> More information is available at the <a href="%s" target="_blank">Meeting Guide API Specification</a>.', '12-step-meeting-list'), 'https://github.com/code4recovery/spec') ?>
                    </p>
                    <?php if (!empty($tsml_data_sources)) { ?>
                        <table>
                            <thead>
                                <tr>
                                    <th class="small align-center"></th>
                                    <th>
                                        <?php _e('Feed', '12-step-meeting-list') ?>
                                    </th>
                                    <th class="align-left">
                                        <?php _e('Parent Region', '12-step-meeting-list') ?>
                                    </th>
                                    <th class="align-left">
                                        <?php _e('Change Detection', '12-step-meeting-list') ?>
                                    </th>
                                    <th class="align-center">
                                        <?php _e('Meetings', '12-step-meeting-list') ?>
                                    </th>
                                    <th class="align-right">
                                        <?php _e('Last Refresh', '12-step-meeting-list') ?>
                                    </th>
                                    <th class="small"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tsml_data_sources as $feed => $properties) { ?>
                                    <tr data-source="<?php echo $feed ?>">
                                        <td class="small ">
                                            <form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
                                                <?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
                                                <input type="hidden" name="tsml_add_data_source" value="<?php echo $feed ?>">
                                                <input type="hidden" name="tsml_add_data_source_name"
                                                    value="<?php echo @$properties['name'] ?>">
                                                <input type="hidden" name="tsml_add_data_source_parent_region_id"
                                                    value="<?php echo @$properties['parent_region_id'] ?>">
                                                <input type="hidden" name="tsml_add_data_source_change_detect"
                                                    value="<?php echo @$properties['change_detect'] ?>">
                                                <input type="submit" value="Refresh" class="button button-small">
                                            </form>
                                        </td>
                                        <td>
                                            <a href="<?php echo $feed ?>" target="_blank">
                                                <?php echo !empty($properties['name']) ? $properties['name'] : __('Unnamed Feed', '12-step-meeting-list') ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php
                                            $parent_region = null;
                                            if (empty($properties['parent_region_id']) || $properties['parent_region_id'] == -1) {
                                                $parent_region = __('Top-level region', '12-step-meeting-list');
                                            } elseif (empty($regions[$properties['parent_region_id']])) {
                                                $term = get_term_by('term_id', $properties['parent_region_id'], 'tsml_region');
                                                //$parent_region = $term[3];
                                                $parent_region = $term->name;
                                                if ($parent_region == null) {
                                                    $parent_region = __('Top-level region', '12-step-meeting-list');
                                                    $parent_region = 'Missing Parent Region: ' . $properties['parent_region_id'];
                                                }
                                            } else {
                                                $parent_region = $regions[$properties['parent_region_id']];
                                            }
                                            echo $parent_region;
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $change_detect = null;
                                            if (empty($properties['change_detect']) || $properties['change_detect'] == -1) {
                                                $change_detect = __('Disabled', '12-step-meeting-list');
                                            } else {
                                                $change_detect = ucfirst($properties['change_detect']);
                                            }

                                            echo $change_detect;
                                            ?>
                                        </td>
                                        <td class="align-center count_meetings">
                                            <?php echo number_format($properties['count_meetings']) ?>
                                        </td>

                                        <td class="align-right">
                                            <?php echo Date(get_option('date_format') . ' ' . get_option('time_format'), $properties['last_import']) ?>
                                        </td>

                                        <td class="small">
                                            <form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
                                                <?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
                                                <input type="hidden" name="tsml_remove_data_source" value="<?php echo $feed ?>">
                                                <span class="dashicons dashicons-no-alt"></span>
                                            </form>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } ?>
                    <form class="row" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
                        <?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>

                        <input type="text" name="tsml_add_data_source_name"
                            placeholder="<?php _e('District 02', '12-step-meeting-list') ?>">

                        <input type="text" name="tsml_add_data_source" placeholder="https://">

                        <?php wp_dropdown_categories(
                            array(
                                'name' => 'tsml_add_data_source_parent_region_id',
                                'taxonomy' => 'tsml_region',
                                'hierarchical' => true,
                                'hide_empty' => false,
                                'orderby' => 'name',
                                'selected' => null,
                                'title' => __('Append regions created by this data source to… (top-level, if none selected)', '12-step-meeting-list'),
                                'show_option_none' => __('Parent Region…', '12-step-meeting-list'),
                            )
                        ) ?>

                        <select name="tsml_add_data_source_change_detect" id="tsml_change_detect">
                            <?php
                            foreach (array('disabled' => __('Change Detection Disabled', '12-step-meeting-list'), 'enabled' => __('Change Detection Enabled', '12-step-meeting-list'), ) as $key => $value) { ?>
                                <option value="<?php echo $key ?>" <?php selected($tsml_change_detect, $key) ?>>
                                    <?php echo $value ?>
                                </option>
                            <?php } ?>
                        </select>

                        <input type="submit" class="button" value="<?php _e('Add Data Source', '12-step-meeting-list') ?>">
                    </form>
                </div>

                <div class="three-column">
                    <div class="stack">
                        <div class="postbox stack">
                            <h2>
                                <?php _e('Import CSV', '12-step-meeting-list') ?>
                            </h2>
                            <form method="post" class="radio stack" action="<?php echo $_SERVER['REQUEST_URI'] ?>"
                                enctype="multipart/form-data">
                                <?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
                                <input type="file" name="tsml_import">
                                <p>
                                    <?php _e('When importing...', '12-step-meeting-list') ?>
                                    <?php
                                    $delete_options = [
                                        'nothing' => __('don\'t delete anything', '12-step-meeting-list'),
                                        'regions' => __('delete only the meetings, locations, and groups for the regions present in this CSV', '12-step-meeting-list'),
                                        'all' => __('delete all meetings, locations, groups, districts, and regions', '12-step-meeting-list'),
                                    ];
                                    if (!empty($tsml_data_sources)) {
                                        $delete_options['no_data_source'] = __('delete all meetings, locations, and groups not from a data source', '12-step-meeting-list');
                                    }
                                    $delete_selected = (empty($_POST['delete']) || !array_key_exists($_POST['delete'], $delete_options)) ? 'nothing' : $_POST['delete'];
                                    foreach ($delete_options as $key => $value) { ?>
                                        <label>
                                            <input type="radio" name="delete" value="<?php echo $key ?>" <?php checked($key, $delete_selected) ?>>
                                            <?php echo $value ?>
                                        </label>
                                    <?php } ?>
                                </p>
                                <input type="submit" class="button" value="<?php _e('Begin', '12-step-meeting-list') ?>">
                            </form>
                        </div>

                        <div class="postbox stack">
                            <h2>
                                <?php _e('Example CSV', '12-step-meeting-list') ?>
                            </h2>

                            <p>
                                <a href="<?php echo plugin_dir_url(__FILE__) . '../template.csv' ?>" class="button">
                                    <?php _e('Example spreadsheet', '12-step-meeting-list') ?>
                                </a>
                            </p>

                            <details>
                                <summary>Field definitions</summary>

                                <p>
                                    <?php _e('<strong>Time</strong>, if present, should be in a standard date format such as 6:00 AM or 06:00. Non-standard or empty dates will be imported as "by appointment."', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>End Time</strong>, if present, should be in a standard date format such as <code>6:00 AM</code> or <code>06:00</code>.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Day</strong> if present, should either <code>Sunday</code>, <code>Monday</code>, <code>Tuesday</code>, <code>Wednesday</code>, <code>Thursday</code>, <code>Friday</code>, or <code>Saturday</code>. Meetings that occur on multiple days should be listed separately. \'Daily\' or \'Mondays\' will not work. Non-standard days will be imported as "by appointment."', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Name</strong> is the name of the meeting, and is optional, although it\'s valuable information for the user. If it\'s missing, a name will be created by combining the location, day, and time.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Slug</strong> optional, and sets the meeting post\'s "slug" or unique string, which is used in the URL. This should be unique to the meeting. Setting this helps preserve user bookmarks.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Location</strong> is the name of the location, and is optional. Generally it\'s the group or building name. If it\'s missing, the address will be used. In the event that there are multiple location names for the same address, the first location name will be used.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Address</strong> is strongly encouraged and will be corrected by Google, so it may look different afterward. Ideally, every address for the same location should be exactly identical, and not contain extra information about the address, such as the building name or descriptors like "around back."', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('If <strong>Address</strong> is specified, then <strong>City</strong>, <strong>State</strong>, and <strong>Country</strong> are optional, but they might be useful if your addresses sound ambiguous to Google. If address is not specified, then these fields are required.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Notes</strong> are freeform notes that are specific to the meeting. For example, <code>last Saturday is birthday night</code>.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Conference URL</strong> is an optional URL to a specific public videoconference meeting. This should be a common videoconferencing service such as Zoom or Google Hangouts. It should launch directly into the meeting and not link to an intermediary page.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Conference URL Notes</strong> is an optional string which contains metadata about the Conference URL (e.g. meeting password in plain text for those groups unwilling to publish a one-tap URL).', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Conference Phone</strong> is the telephone number to dial into a specific meeting. Should be numeric, except a plus(\'+\') symbol may be used for international dialers, and the comma(\',\'), asterisk(\'*\'), and pound(\'#\') symbols can be used to form one-tap phone links.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Conference Phone Notes</strong> is an optional string with metadata about the \'Conference Phone\' (e.g. a numeric meeting password or other user instructions).', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Region</strong> is user-defined and can be anything. Often this is a small municipality or neighborhood. Since these go in a dropdown, ideally you would have 10 to 20 regions, although it\'s ok to be over or under.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Sub Region</strong> makes the Region hierarchical; in San Jose we have sub regions for East San Jose, West San Jose, etc. New York City might have Manhattan be a Region, and Greenwich Village be a Sub Region.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Location Notes</strong> are freeform notes that will show up on every meeting that this location. For example, "Enter from the side."', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Group</strong> is a way of grouping contacts. Meetings with the same group name will be linked and share contact information.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>District</strong> is user-defined and can be anything, but should be a string rather than an integer (e.g. <b>District 01</b> rather than <b>1</b>). A group name must also be specified.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Sub District</strong> makes the District hierachical.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Group Notes</strong> is for stuff like a short group history, or when the business meeting meets.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Website</strong> and <strong>Website 2</strong> are optional.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Email</strong> is optional. This is a public email address.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Phone</strong> is optional. This is a public phone number.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Venmo</strong> is an optional string and should be a valid Venmo handle, (e.g. <code>@AAGroupName</code>). This is understood to be the address for 7th Tradition contributions to the meeting, and not any other entity.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Square</strong> is an optional string and should be a valid Square Cash App cashtag, (e.g. <code>$AAGroupName</code>). This is understood to be the address for 7th Tradition contributions to the meeting, and not any other entity.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>PayPal</strong> is an optional string and should be a valid PayPal username, (e.g. <code>AAGroupName</code>). This is understood to be the address for 7th Tradition contributions to the meeting, and not any other entity.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>URL</strong> is optional and should point to the meeting\'s listing on the area website.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Feedback URL</strong> is an optional string URL that can be used to provide feedback about the meeting. These could be local links (e.g. <code>https://mywebsite.org/feedback?meeting=meeting-slug-1</code>), remote links (e.g. <code>https://typeform.com/to/23904203?meeting=meeting-slug-1</code>), or email links (e.g. <code>mailto:webservant@domain.org?subject=meeting-slug-1</code>).', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Edit URL</strong> is an optional string URL that trusted servants can use to edit the specific meeting\'s listing.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Contact 1/2/3 Name/Email/Phone</strong> (nine fields in total) are all optional. By default, contact information is only visible inside the WordPress dashboard.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <?php _e('<strong>Last Contact</strong> is an optional date.', '12-step-meeting-list') ?>
                                </p>
                                <?php if (!empty($tsml_programs[$tsml_program]['types'])) { ?>
                                    <p>
                                        <?php _e('<strong>Types</strong> should be a comma-separated list of the following options. This list is determined by which program is selected on the Settings tab.', '12-step-meeting-list') ?>
                                    <ul class="types">
                                        <?php foreach ($tsml_programs[$tsml_program]['types'] as $value) { ?>
                                            <li>
                                                <?php echo $value ?>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                    </li>
                                <?php } ?>

                            </details>
                        </div>
                    </div>

                    <!-- Wheres My Info? -->
                    <div class="postbox stack">
                        <?php
                        $meetings = tsml_count_meetings();
                        $locations = tsml_count_locations();
                        $regions = tsml_count_regions();
                        $groups = tsml_count_groups();

                        $pdf_link = 'https://pdf.code4recovery.org/?' . http_build_query([
                            'json' => admin_url('admin-ajax.php') . '?' . http_build_query([
                                'action' => 'meetings',
                                'nonce' => $tsml_sharing === 'restricted' ? wp_create_nonce($tsml_nonce) : null
                            ])
                        ]);
                        ?>
                        <h2>
                            <?php _e('Where\'s My Info?', '12-step-meeting-list') ?>
                        </h2>
                        <?php if ($tsml_slug) { ?>
                            <p>
                                <?php printf(__('Your public meetings page is <a href="%s">right here</a>. Link that page from your site\'s nav menu to make it visible to the public.', '12-step-meeting-list'), get_post_type_archive_link('tsml_meeting')) ?>
                            </p>
                            <?php
                        } ?>

                        <div id="tsml_counts" <?php if (!($meetings + $locations + $groups + $regions)) { ?> class="hidden"
                            <?php } ?>>
                            <p>
                                <?php _e('You have:', '12-step-meeting-list') ?>
                            </p>
                            <div class="table">
                                <ul class="ul-disc">
                                    <li class="meetings<?php if (!$meetings) { ?> hidden<?php } ?>">
                                        <?php printf(_n('%s meeting', '%s meetings', $meetings, '12-step-meeting-list'), number_format_i18n($meetings)) ?>
                                    </li>
                                    <li class="locations<?php if (!$locations) { ?> hidden<?php } ?>">
                                        <?php printf(_n('%s location', '%s locations', $locations, '12-step-meeting-list'), number_format_i18n($locations)) ?>
                                    </li>
                                    <li class="groups<?php if (!$groups) { ?> hidden<?php } ?>">
                                        <?php printf(_n('%s group', '%s groups', $groups, '12-step-meeting-list'), number_format_i18n($groups)) ?>
                                    </li>
                                    <li class="regions<?php if (!$regions) { ?> hidden<?php } ?>">
                                        <?php printf(_n('%s region', '%s regions', $regions, '12-step-meeting-list'), number_format_i18n($regions)) ?>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Export Meeting List -->
                    <div class="postbox stack">
                        <h2>
                            <?php _e('Export Meeting List', '12-step-meeting-list') ?>
                        </h2>
                        <?php
                        if ($meetings) { ?>
                            <p>
                                <a href="<?php echo admin_url('admin-ajax.php') . '?action=csv' ?>" target="_blank" class="button">
                                    <?php _e('Download CSV') ?>
                                </a>
                                &nbsp;
                                <a href="<?php echo $pdf_link ?>" target="_blank" class="button">
                                    <?php _e('Generate PDF') ?>
                                </a>
                            </p>

                        <?php } ?>
                        <p>
                            <?php printf(__('Want to send a mass email to your contacts? <a href="%s" target="_blank">Click here</a> to see their email addresses.', '12-step-meeting-list'), admin_url('admin-ajax.php') . '?action=contacts') ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
