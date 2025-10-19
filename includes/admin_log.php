<?php

// import log admin page
function tsml_log_page()
{
    global $tsml_nonce;

    // tsml_log types, with translated labels for admin page
    define('TSML_LOG_TYPES', [
        'data_source' => __('Data source', '12-step-meeting-list'),
        'data_source_error' => __('Data source error', '12-step-meeting-list'),
        'import_meeting' => __('Meeting import', '12-step-meeting-list'),
        'geocode_success' => __('Geocoding success', '12-step-meeting-list'),
        'geocode_error' => __('Geocoding error', '12-step-meeting-list'),
        'geocode_connection_error' => __('Geocoding connection error', '12-step-meeting-list'),
    ]);

    // clear log entries
    $valid_nonce = isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce);
    if ($valid_nonce && isset($_POST['clear_log'])) {
        delete_option('tsml_log');
        tsml_alert(__('Log cleared', '12-step-meeting-list'));
    }

    // get all entries to count them for the dropdown filter
    $log_entries = tsml_log_get();

    // query param filters the array
    $filter_type = !empty($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : null;
    $filtered_log_entries = array_filter($log_entries, function ($entry) use ($filter_type) {
        return !$filter_type || $filter_type === $entry['type'];
    });

    // dropdown filter
    $log_types = array_count_values(array_map(function (array $entry) {
        return $entry['type'];
    }, $log_entries));
    ?>

    <!-- Admin page content should all be inside .wrap -->
    <div class="wrap tsml_admin_settings">
        <h1></h1> <!-- Set alerts here -->

        <div class="stack">
            <div class="postbox stack">
                <h2>
                    <?php esc_html_e('Event Log', '12-step-meeting-list') ?>
                </h2>

                <?php if (!count($log_entries)) { ?>
                    <p>
                        <?php esc_html_e('No events within the last month.', '12-step-meeting-list') ?>
                    </p>
                <?php } else { ?>
                    <p>
                        <?php esc_html_e('These are the most recent events within the last month.', '12-step-meeting-list') ?>
                    </p>

                    <div>
                        <div class="alignleft">
                            <label for="filter_type" class="screen-reader-text">
                                <?php esc_html_e('Filter by event type', '12-step-meeting-list') ?>
                            </label>
                            <select name="filter_type" id="filter_type">
                                <option>
                                    <?php esc_html_e('Filter by event type', '12-step-meeting-list') ?>
                                </option>
                                <?php foreach (TSML_LOG_TYPES as $key => $value) {
                                    if (!array_key_exists($key, $log_types)) {
                                        continue;
                                    }
                                    ?>
                                    <option value="<?php echo $key; ?>" <?php selected($key, $filter_type) ?>>
                                        <?php echo $value ?> (<?php echo $log_types[$key] ?>)
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <form class="alignright" method="post">
                            <?php
                            wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
                            tsml_input_submit(__('Clear log', '12-step-meeting-list'), ['class' => 'button', 'name' => 'clear_log']);
                            ?>
                        </form>
                    </div>

                    <table class="log-table log-table--large">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Date', '12-step-meeting-list') ?></th>
                                <th><?php esc_html_e('Type', '12-step-meeting-list') ?></th>
                                <th><?php esc_html_e('Info', '12-step-meeting-list') ?></th>
                                <th><?php esc_html_e('Data', '12-step-meeting-list') ?></th>
                            </tr>
                        </thead>
                        <tbody id="tsml_import_log">
                            <?php
                            foreach ($filtered_log_entries as $entry) {
                                $type = strval(!empty($entry['type']) ? $entry['type'] : '');
                                $type_label = isset(TSML_LOG_TYPES[$type]) ? TSML_LOG_TYPES[$type] : $type;
                                $msg = tsml_log_format_entry_msg($entry);
                                $row_class = (false !== strpos($type, 'error')) ? 'error' : '';
                                ?>
                                <tr class="log-table <?php echo $row_class; ?>" data-type="<?php echo esc_attr($type); ?>">
                                    <td>
                                        <?php echo tsml_date_localised(get_option('date_format') . ' ' . get_option('time_format'), intval($entry['timestamp'])); ?>
                                    </td>
                                    <td><?php echo $type_label; ?></td>
                                    <td><?php echo $entry['info']; ?></td>
                                    <td><?php echo $msg; ?></td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php
}