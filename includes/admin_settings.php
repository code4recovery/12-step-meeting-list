<?php

//settings
if (!function_exists('tsml_settings_page')) {

    function tsml_settings_page()
    {
        global $tsml_data_sources, $tsml_programs, $tsml_program, $tsml_nonce, $tsml_feedback_addresses, $tsml_notification_addresses,
        $tsml_distance_units, $tsml_sharing, $tsml_sharing_keys, $tsml_contact_display, $tsml_google_maps_key, $tsml_mapbox_key,
        $tsml_user_interface, $tsml_timezone;

        // todo consider whether this check is necessary, since it is run from add_submenu_page() which is already checking for the same permission
        // potentially tsml_settings_page() could be a closure within the call to add_submenu_page which would prevent it from being reused elsewhere
        tsml_require_settings_permission();

        $tsml_data_sources = tsml_get_option_array('tsml_data_sources');

        // change program
        if (!empty($_POST['tsml_program']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
            $tsml_program = sanitize_text_field($_POST['tsml_program']);
            update_option('tsml_program', $tsml_program);
            tsml_alert(__('Program setting updated.', '12-step-meeting-list'));
        }

        // change distance units
        if (!empty($_POST['tsml_distance_units']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
            $tsml_distance_units = ($_POST['tsml_distance_units'] == 'mi') ? 'mi' : 'km';
            update_option('tsml_distance_units', $tsml_distance_units);
            tsml_alert(__('Distance units updated.', '12-step-meeting-list'));
        }

        // change contact display
        if (!empty($_POST['tsml_contact_display']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
            $tsml_contact_display = ($_POST['tsml_contact_display'] == 'public') ? 'public' : 'private';
            update_option('tsml_contact_display', $tsml_contact_display);
            tsml_cache_rebuild(); // this value affects what's in the cache
            tsml_alert(__('Contact privacy updated.', '12-step-meeting-list'));
        }

        // change sharing setting
        if (!empty($_POST['tsml_sharing']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
            $tsml_sharing = ($_POST['tsml_sharing'] == 'open') ? 'open' : 'restricted';
            update_option('tsml_sharing', $tsml_sharing);
            tsml_alert(__('Sharing setting updated.', '12-step-meeting-list'));
        }

        // add a sharing key
        if (!empty($_POST['tsml_add_sharing_key']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
            $name = sanitize_text_field($_POST['tsml_add_sharing_key']);
            $key = md5(uniqid($name, true));
            $tsml_sharing_keys[$key] = $name;
            asort($tsml_sharing_keys);
            update_option('tsml_sharing_keys', $tsml_sharing_keys);
            tsml_alert(__('Sharing key added.', '12-step-meeting-list'));

            // users might expect that if they add "meeting guide" that then they are added to the app
            if (strtolower($name) == 'meeting guide') {
                $current_user = wp_get_current_user();
                $message = admin_url('admin-ajax.php?') . http_build_query([
                    'action' => 'meetings',
                    'key' => $key,
                ]);
                tsml_email(TSML_MEETING_GUIDE_APP_NOTIFY, 'Sharing Key', $message, $current_user->user_email);
            }
        }

        // remove a sharing key
        if (!empty($_POST['tsml_remove_sharing_key']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
            $key = sanitize_text_field($_POST['tsml_remove_sharing_key']);
            if (array_key_exists($key, $tsml_sharing_keys)) {
                unset($tsml_sharing_keys[$key]);
                if (empty($tsml_sharing_keys)) {
                    delete_option('tsml_sharing_keys');
                } else {
                    update_option('tsml_sharing_keys', $tsml_sharing_keys);
                }
                tsml_alert(__('Sharing key removed.', '12-step-meeting-list'));
            } else {
                // theoretically should never get here, because user is choosing from a list
                tsml_alert(sprintf(
                    // translators: %s is the key that was not found
                    esc_html__('<code>%s</code> was not found in the list of sharing keys. Please try again.', '12-step-meeting-list'),
                    $key
                ), 'error');
            }
        }

        // add a feedback email
        if (!empty($_POST['tsml_add_feedback_address']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
            $email = sanitize_text_field($_POST['tsml_add_feedback_address']);
            if (!is_email($email)) {
                // theoretically should never get here, because WordPress checks entry first
                tsml_alert(sprintf(
                    // translators: %s is the email address that was not valid
                    esc_html__('<code>%s</code> is not a valid email address. Please try again.', '12-step-meeting-list'),
                    $email
                ), 'error');
            } else {
                $tsml_feedback_addresses[] = $email;
                $tsml_feedback_addresses = array_unique($tsml_feedback_addresses);
                sort($tsml_feedback_addresses);
                update_option('tsml_feedback_addresses', $tsml_feedback_addresses);
                tsml_alert(__('Feedback address added.', '12-step-meeting-list'));
                tsml_cache_rebuild(); // these values affect what's in the cache
            }
        }

        // remove a feedback email
        if (!empty($_POST['tsml_remove_feedback_address']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
            $email = sanitize_text_field($_POST['tsml_remove_feedback_address']);
            if (($key = array_search($email, $tsml_feedback_addresses)) !== false) {
                unset($tsml_feedback_addresses[$key]);
                if (empty($tsml_feedback_addresses)) {
                    delete_option('tsml_feedback_addresses');
                } else {
                    update_option('tsml_feedback_addresses', $tsml_feedback_addresses);
                }
                tsml_alert(__('Feedback address removed.', '12-step-meeting-list'));
                tsml_cache_rebuild(); // these values affect what's in the cache
            } else {
                // theoretically should never get here, because user is choosing from a list
                tsml_alert(sprintf(
                    // translators: %s is the email address that was not found
                    esc_html__('<code>%s</code> was not found in the list of addresses. Please try again.', '12-step-meeting-list'),
                    $email
                ), 'error');
            }
        }

        // add a notification email
        if (!empty($_POST['tsml_add_notification_address']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
            $email = sanitize_text_field($_POST['tsml_add_notification_address']);
            if (!is_email($email)) {
                // theoretically should never get here, because WordPress checks entry first
                tsml_alert(sprintf(
                    // translators: %s is the email address that was not valid
                    esc_html__('<code>%s</code> is not a valid email address. Please try again.', '12-step-meeting-list'),
                    $email
                ), 'error');
            } else {
                $tsml_notification_addresses[] = $email;
                $tsml_notification_addresses = array_unique($tsml_notification_addresses);
                sort($tsml_notification_addresses);
                update_option('tsml_notification_addresses', $tsml_notification_addresses);
                tsml_alert(__('Notification address added.', '12-step-meeting-list'));
            }
        }

        // remove a notification email
        if (!empty($_POST['tsml_remove_notification_address']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
            $email = sanitize_text_field($_POST['tsml_remove_notification_address']);
            if (($key = array_search($email, $tsml_notification_addresses)) !== false) {
                unset($tsml_notification_addresses[$key]);
                if (empty($tsml_notification_addresses)) {
                    delete_option('tsml_notification_addresses');
                } else {
                    update_option('tsml_notification_addresses', $tsml_notification_addresses);
                }
                tsml_alert(__('Notification address removed.', '12-step-meeting-list'));
            } else {
                // theoretically should never get here, because user is choosing from a list
                tsml_alert(sprintf(
                    // translators: %s is the email address that was not found
                    esc_html__('<code>%s</code> was not found in the list of addresses. Please try again.', '12-step-meeting-list'),
                    $email
                ), 'error');
            }
        }

        // add a Mapbox access token
        if (isset($_POST['tsml_add_mapbox_key']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
            $tsml_mapbox_key = sanitize_text_field($_POST['tsml_add_mapbox_key']);
            if (empty($tsml_mapbox_key)) {
                delete_option('tsml_mapbox_key');
                tsml_alert(__('API key removed.', '12-step-meeting-list'));
            } else {
                update_option('tsml_mapbox_key', $tsml_mapbox_key);
                tsml_alert(__('API key saved.', '12-step-meeting-list'));
            }

            // there can be only one
            $tsml_google_maps_key = null;
            delete_option('tsml_google_maps_key');
        }

        // add a Google API key
        if (isset($_POST['tsml_add_google_maps_key']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
            $key = sanitize_text_field($_POST['tsml_add_google_maps_key']);
            if (empty($key)) {
                delete_option('tsml_google_maps_key');
                tsml_alert(__('API key removed.', '12-step-meeting-list'));
            } else {
                update_option('tsml_google_maps_key', $key);
                $tsml_google_maps_key = $key;
                tsml_alert(__('API key saved.', '12-step-meeting-list'));
            }

            // there can be only one
            $tsml_mapbox_key = null;
            delete_option('tsml_mapbox_key');
        }

        // change user interface
        if (!empty($_POST['tsml_user_interface']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
            $tsml_user_interface = sanitize_text_field($_POST['tsml_user_interface']);
            update_option('tsml_user_interface', $tsml_user_interface);

            if ($tsml_user_interface == 'tsml_ui') {
                $tsml_ui = "TSML UI";
            } else {
                $tsml_ui = "LEGACY UI";
            }

            tsml_alert(sprintf(
                // translators: %s is the user interface that was selected
                __('User interface is now set to <strong>%s</strong>', '12-step-meeting-list'),
                $tsml_ui
            ));
        }

        // change timezone
        if (isset($_POST['timezone']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
            if (empty($_POST['timezone']) || tsml_timezone_is_valid($_POST['timezone'])) {
                $tsml_timezone = sanitize_text_field($_POST['timezone']);
                update_option('tsml_timezone', $tsml_timezone);
                tsml_alert(__('Timezone updated.', '12-step-meeting-list'));
            } else {
                tsml_alert(__('Invalid timezone selected.', '12-step-meeting-list'), 'error');
            }
        }

        // save entity fields
        if (isset($_POST['tsml_entity']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
            $current_tsml_entity = tsml_get_option_array('tsml_entity');
            $tsml_entity = [];
            global $tsml_entity_fields;
            foreach ($tsml_entity_fields as $field) {
                $tsml_entity[$field] = isset($current_tsml_entity[$field]) ? $current_tsml_entity[$field] : '';
                if (isset($_POST["tsml_$field"])) {
                    $tsml_entity[$field] = substr(trim(strval($_POST["tsml_$field"])), 0, 100);
                }
            }
            update_option('tsml_entity', $tsml_entity);
            tsml_cache_rebuild(); // these values affects what's in the cache
            tsml_alert(__('Entity details saved.', '12-step-meeting-list'));
        }
        ?>

        <!-- Admin page content should all be inside .wrap -->
        <div class="wrap">

            <h1></h1> <!-- Set alerts here -->

            <?php if (!is_ssl()) { ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php esc_attr_e('If you enable SSL (https), your users will be able to search near their location.', '12-step-meeting-list') ?>
                    </p>
                </div>
            <?php } ?>

            <?php if (empty($tsml_mapbox_key) && empty($tsml_google_maps_key)) { ?>
                <div class="notice notice-warning">
                    <h2>Enable Maps on Your Site</h2>
                    <p>
                        If you want to enable maps on your site you have two options: <strong>Mapbox</strong> or
                        <strong>Google</strong>. They are both good options, although Google is not completely supported by all our
                        features! In all likelihood neither one will charge you money. Mapbox gives
                        <a href="https://www.mapbox.com/pricing/" target="_blank">50,000 free map views</a> / month, Google gives
                        <a href="https://cloud.google.com/maps-platform/pricing/" target="_blank">28,500 free views</a>.
                        That's a lot of traffic!
                    </p>

                    <p>
                        To sign up for Mapbox <a href="https://www.mapbox.com/signup/" target="_blank">go here</a>. You will only
                        need a valid email address, no credit card required. Copy your access token and paste it below:
                    </p>

                    <form class="row" method="post">
                        <?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
                        <div class="input">
                            <?php tsml_input_text('tsml_add_mapbox_key', '', ['placeholder' => __('Enter Mapbox access token here', '12-step-meeting-list')]); ?>
                        </div>
                        <div class="btn">
                            <?php tsml_input_submit(__('Add', '12-step-meeting-list')); ?>
                        </div>
                    </form>

                    <p>* Please note: our <strong>TSML UI</strong> user interface supports Mapbox, not Google.

                    <p>
                        For our legacy user interface (<strong>Legacy UI</strong>), you can alternatively choose to use Google.
                        Their interface is slightly more complex because they offer more services. <a
                            href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank">Go
                            here</a> to get a key from Google. The process should only take a few minutes, although you will have to
                        enter a credit card.
                    </p>

                    <p>
                        Be sure to:<br>
                        <span class="dashicons dashicons-yes"></span> Enable the Google Maps Javascript API<br>
                        <span class="dashicons dashicons-yes"></span> Secure your credentials by adding your website URL to the list
                        of allowed referrers
                    </p>

                    <p>Once you're done, paste your new key below.</p>

                    <form class="row" method="post">
                        <?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
                        <div class="input">
                            <?php tsml_input_text('tsml_add_google_maps_key', '', ['placeholder' => __('Enter Google API key here', '12-step-meeting-list')]); ?>
                        </div>
                        <div class="btn">
                            <?php tsml_input_submit(__('Add', '12-step-meeting-list')); ?>
                        </div>
                    </form>
                </div>
            <?php } ?>

            <div class="three-column">
                <div class="stack">
                    <!-- General Settings -->
                    <div class="postbox stack">
                        <h2>
                            <?php esc_html_e('General', '12-step-meeting-list') ?>
                        </h2>
                        <form class="stack compact" method="post">
                            <h3>
                                <?php esc_html_e('Program', '12-step-meeting-list') ?>
                            </h3>
                            <p>
                                <?php esc_html_e('Select the recovery program your site targets here.', '12-step-meeting-list') ?>
                            </p>
                            <?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
                            <select name="tsml_program" onchange="this.form.submit()">
                                <?php foreach ($tsml_programs as $key => $value) { ?>
                                    <option value="<?php echo esc_attr($key) ?>" <?php selected($tsml_program, $key) ?>>
                                        <?php echo esc_html($value['name']) ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </form>
                        <form class="stack compact" method="post">
                            <h3>
                                <?php esc_html_e('Distance Units', '12-step-meeting-list') ?>
                            </h3>
                            <p>
                                <?php esc_html_e('This determines which units are used on the meeting list page.', '12-step-meeting-list') ?>
                            </p>
                            <?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
                            <select name="tsml_distance_units" onchange="this.form.submit()">
                                <?php
                                foreach (['km' => __('Kilometers', '12-step-meeting-list'), 'mi' => __('Miles', '12-step-meeting-list'),] as $key => $value) { ?>
                                    <option value="<?php echo esc_attr($key) ?>" <?php selected($tsml_distance_units, $key) ?>>
                                        <?php echo esc_html($value) ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </form>
                        <form class="stack compact" method="post">
                            <h3>
                                <?php esc_html_e('Contact Visibility', '12-step-meeting-list') ?>
                            </h3>
                            <p>
                                <?php esc_html_e('This determines whether contacts are displayed publicly on meeting detail pages.', '12-step-meeting-list') ?>
                            </p>
                            <?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
                            <select name="tsml_contact_display" onchange="this.form.submit()">
                                <?php
                                foreach (['public' => __('Public', '12-step-meeting-list'), 'private' => __('Private', '12-step-meeting-list'),] as $key => $value) { ?>
                                    <option value="<?php echo esc_attr($key) ?>" <?php selected($tsml_contact_display, $key) ?>>
                                        <?php echo esc_html($value) ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </form>
                    </div>

                    <div class="postbox stack">
                        <!-- Entity Form -->
                        <?php
                        $tsml_entity = tsml_get_entity();
                        if (!isset($tsml_entity['entity_phone'])) {
                            $tsml_entity['entity_phone'] = '';
                        }
                        if (!isset($tsml_entity['entity_location'])) {
                            $tsml_entity['entity_location'] = '';
                        }
                        ?>
                        <div class="stack compact">
                            <h2>
                                <?php esc_html_e('Service Entity Information', '12-step-meeting-list') ?>
                            </h2>
                            <p>
                                <?php esc_html_e('Enter information for your service entity here to help identity the meeting source when sharing feeds with others.', '12-step-meeting-list') ?>
                            </p>

                            <form method="post" class="stack compact">
                                <?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
                                <label class="h3" for="tsml_entity">
                                    <?php esc_html_e('Entity Name', '12-step-meeting-list') ?>
                                </label>
                                <?php tsml_input_text('tsml_entity', $tsml_entity['entity'], [
                                    'maxlength' => 100,
                                    'placeholder' => __('Entity Name', '12-step-meeting-list')
                                ]) ?>
                                <label class="h3" for="tsml_entity_email">
                                    <?php esc_html_e('Administrative Contact Email', '12-step-meeting-list') ?>
                                </label>
                                <?php tsml_input_text('tsml_entity_email', $tsml_entity['entity_email'], [
                                    'maxlength' => 100,
                                    'placeholder' => 'group@website.org',
                                ]) ?>
                                <label class="h3" for="tsml_entity_phone">
                                    <?php esc_html_e('Public Phone Number', '12-step-meeting-list') ?>
                                </label>
                                <?php tsml_input_text('tsml_entity_phone', $tsml_entity['entity_phone'], [
                                    'maxlength' => 100,
                                    'placeholder' => '+18005551212',
                                ]) ?>
                                <label class="h3" for="tsml_entity_location">
                                    <?php esc_html_e('Service Area', '12-step-meeting-list') ?>
                                </label>
                                <?php tsml_input_text('tsml_entity_location', $tsml_entity['entity_location'], [
                                    'maxlength' => 100,
                                    'placeholder' => __('City, State, Country', '12-step-meeting-list'),
                                ]) ?>
                                <label class="h3" for="tsml_entity_url">
                                    <?php esc_html_e('Website Address', '12-step-meeting-list') ?>
                                </label>
                                <?php tsml_input_url('tsml_entity_url', $tsml_entity['entity_url'], ['maxlength' => 100]) ?>
                                <p>
                                    <?php tsml_input_submit(__('Save', '12-step-meeting-list')); ?>
                                </p>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="stack">
                    <div class="postbox stack">
                        <!-- Feed Management -->
                        <h2>
                            <?php esc_html_e('Feed Management', '12-step-meeting-list') ?>
                        </h2>
                        <form class="stack compact" method="post">
                            <h3>
                                <?php esc_html_e('Feed Sharing', '12-step-meeting-list') ?>
                            </h3>
                            <p>
                                <?php esc_html_e('Open means your feeds are available publicly. Restricted means people need a key or to be logged in to get the feed.', '12-step-meeting-list') ?>
                            </p>
                            <?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
                            <select name="tsml_sharing" onchange="this.form.submit()">
                                <?php
                                foreach (['open' => __('Open', '12-step-meeting-list'), 'restricted' => __('Restricted', '12-step-meeting-list'),] as $key => $value) { ?>
                                    <option value="<?php echo esc_attr($key) ?>" <?php selected($tsml_sharing, $key) ?>>
                                        <?php echo esc_html($value) ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </form>

                        <?php if ($tsml_sharing == 'restricted') { ?>

                            <div class="stack compact">
                                <h3>
                                    <?php esc_html_e('Authorized Apps', '12-step-meeting-list') ?>
                                </h3>

                                <p>
                                    <?php esc_html_e('You may allow access to your meeting data for specific purposes, such as the Meeting Guide App.', '12-step-meeting-list') ?>
                                </p>

                                <?php if (count($tsml_sharing_keys)) { ?>
                                    <table class="tsml_sharing_list">
                                        <?php foreach ($tsml_sharing_keys as $key => $name) {
                                            $address = admin_url('admin-ajax.php?') . http_build_query([
                                                'action' => 'meetings',
                                                'key' => $key,
                                            ]);
                                            ?>
                                            <tr>
                                                <td>
                                                    <a href="<?php echo esc_attr($address) ?>" target="_blank">
                                                        <?php echo esc_html($name) ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <form method="post">
                                                        <?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
                                                        <?php tsml_input_hidden('tsml_remove_sharing_key', $key) ?>
                                                        <span class="dashicons dashicons-no-alt"></span>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </table>
                                <?php } ?>
                                <form class="row" method="post">
                                    <?php
                                    wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
                                    tsml_input_text('tsml_add_sharing_key', '', ['placeholder' => __('Meeting Guide', '12-step-meeting-list')]);
                                    tsml_input_submit(__('Add', '12-step-meeting-list'));
                                    ?>
                                </form>
                            </div>
                        <?php } else { ?>
                            <div class="stack compact">
                                <h3>
                                    <?php esc_html_e('Public Feed', '12-step-meeting-list') ?>
                                </h3>
                                <p>
                                    <?php esc_html_e('The following feed contains your publicly available meeting information.', '12-step-meeting-list') ?>
                                </p>
                                <p>
                                    <a href="<?php echo esc_attr(admin_url('admin-ajax.php?action=meetings')); ?>" target="_blank">
                                        <?php esc_html_e('Public Data Source', '12-step-meeting-list'); ?>
                                    </a>
                                </p>
                            </div>
                        <?php } ?>
                    </div>

                    <div class="postbox stack">
                        <!-- Switch UI -->
                        <div class="stack compact">
                            <h2>
                                <?php esc_html_e('User Interface Display', '12-step-meeting-list') ?>
                            </h2>
                            <p>
                                <?php echo wp_kses(sprintf(
                                    // translators: %s is a link to the meeting finder page
                                    __('Please select the user interface that is right for your <a href="%s" target="_blank">meeting finder page</a>. Choose between our latest design that we call <b>TSML UI</b> or stay with the standard <b>Legacy UI</b>.', '12-step-meeting-list'),
                                    get_post_type_archive_link('tsml_meeting')
                                ), TSML_ALLOWED_HTML) ?>
                            </p>

                            <form method="post">
                                <?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
                                <select name="tsml_user_interface" onchange="this.form.submit()">
                                    <option value="legacy_ui" <?php selected($tsml_user_interface, 'legacy_ui') ?>>
                                        <?php esc_html_e('Legacy UI', '12-step-meeting-list') ?>
                                    </option>
                                    <option value="tsml_ui" <?php selected($tsml_user_interface, 'tsml_ui') ?>>
                                        <?php esc_html_e('TSML UI', '12-step-meeting-list') ?>
                                    </option>
                                </select>
                            </form>
                        </div>
                    </div>

                    <div class="postbox stack">
                        <!-- Timezone -->
                        <div class="stack compact">
                            <h2>
                                <?php esc_html_e('Timezone', '12-step-meeting-list') ?>
                            </h2>
                            <?php if ($tsml_user_interface === 'tsml_ui') { ?>
                                <p>
                                    <?php esc_html_e('If your site features meetings in a variety of timezones, leave this blank and meetings will be displayed in the user\'s timezone. This requires a timezone to be set on each meeting location.', '12-step-meeting-list') ?>
                                </p>

                                <p>
                                    <?php esc_html_e('If your site features meetings in a single timezone, select it here and meetings will be displayed in that timezone. There is no need to specify a timezone for each meeting.', '12-step-meeting-list') ?>
                                </p>

                                <form method="post" onchange="this.submit()">
                                    <?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
                                    <?php tsml_timezone_select($tsml_timezone) ?>
                                </form>
                            <?php } else { ?>
                                <p>
                                    <?php esc_html_e('Timezone settings are only relevant to the TSML UI interface.', '12-step-meeting-list') ?>
                                </p>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- Map Settings -->
                    <div class="postbox stack">
                        <div class="stack compact">
                            <h2>
                                <?php esc_html_e('Maps', '12-step-meeting-list') ?>
                            </h2>
                            <p>
                                <?php echo wp_kses(__('Display of maps requires an authorization key from <strong><a href="https://www.mapbox.com/" target="_blank">Mapbox</a></strong> or <strong><a href="https://console.cloud.google.com/home/" target="_blank">Google</a></strong>.', '12-step-meeting-list'), TSML_ALLOWED_HTML) ?>
                            </p>
                            <p>
                                <?php esc_html_e('Please note that TSML UI only supports Mapbox.', '12-step-meeting-list') ?>
                            </p>
                        </div>
                        <div class="stack compact">
                            <h3>
                                <?php esc_html_e('Mapbox Access Token', '12-step-meeting-list') ?>
                            </h3>

                            <form class="row" method="post">
                                <?php
                                wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
                                tsml_input_text('tsml_add_mapbox_key', $tsml_mapbox_key, ['placeholder' => __('Enter Mapbox access token here', '12-step-meeting-list')]);
                                tsml_input_submit(empty($tsml_mapbox_key) ? __('Add', '12-step-meeting-list') : __('Update', '12-step-meeting-list'));
                                ?>
                            </form>
                        </div>

                        <div class="stack compact">
                            <h3>
                                <?php esc_html_e('Google Maps API Key', '12-step-meeting-list') ?>
                            </h3>
                            <p>
                                <?php echo wp_kses(__('Be sure to enable JavaScript Maps API (<a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank">read more</a>).', '12-step-meeting-list'), TSML_ALLOWED_HTML) ?>
                            </p>
                            <form class="row" method="post">
                                <?php
                                wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
                                tsml_input_text('tsml_add_google_maps_key', $tsml_google_maps_key, ['placeholder' => __('Enter Google API key here', '12-step-meeting-list')]);
                                tsml_input_submit(empty($tsml_google_maps_key) ? __('Add', '12-step-meeting-list') : __('Update', '12-step-meeting-list'));
                                ?>
                            </form>
                        </div>

                    </div>
                </div>

                <div class="stack">
                    <!-- About Us -->
                    <div class="postbox stack">
                        <div class="stack">
                            <h2>
                                <?php esc_html_e('About Us', '12-step-meeting-list') ?>
                            </h2>
                            <?php tsml_about_message() ?>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div class="postbox stack">
                        <h2>
                            <?php esc_html_e('Email Addresses', '12-step-meeting-list') ?>
                        </h2>

                        <div class="stack compact">
                            <h3>
                                <?php esc_html_e('User Feedback Emails', '12-step-meeting-list') ?>
                            </h3>
                            <p>
                                <?php esc_html_e('Enable a meeting info feedback form by adding email addresses here.', '12-step-meeting-list') ?>
                            </p>
                            <?php if (!empty($tsml_feedback_addresses)) { ?>
                                <table class="tsml_address_list">
                                    <?php foreach ($tsml_feedback_addresses as $address) { ?>
                                        <tr>
                                            <td>
                                                <?php echo esc_html($address) ?>
                                            </td>
                                            <td>
                                                <form method="post">
                                                    <?php
                                                    wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
                                                    tsml_input_hidden('tsml_remove_feedback_address', $address);
                                                    ?>
                                                    <span class="dashicons dashicons-no-alt"></span>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </table>
                            <?php } ?>
                            <form class="row" method="post">
                                <?php
                                wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
                                tsml_input_email('tsml_add_feedback_address', '', ['placeholder' => 'email@example.org']);
                                tsml_input_submit(__('Add', '12-step-meeting-list'));
                                ?>
                            </form>
                        </div>

                        <div class="stack compact">
                            <h3>
                                <?php esc_html_e('Change Notification Emails', '12-step-meeting-list') ?>
                            </h3>
                            <p>
                                <?php esc_html_e('Receive notifications of meeting changes at the email addresses below.', '12-step-meeting-list') ?>
                            </p>
                            <?php if (!empty($tsml_notification_addresses)) { ?>
                                <table class="tsml_address_list">
                                    <?php foreach ($tsml_notification_addresses as $address) { ?>
                                        <tr>
                                            <td>
                                                <?php echo esc_html($address) ?>
                                            </td>
                                            <td>
                                                <form method="post">
                                                    <?php
                                                    wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
                                                    tsml_input_hidden('tsml_remove_notification_address', $address);
                                                    ?>
                                                    <span class="dashicons dashicons-no-alt"></span>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </table>
                            <?php } ?>
                            <form class="row" method="post">
                                <?php
                                wp_nonce_field($tsml_nonce, 'tsml_nonce', false);
                                tsml_input_email('tsml_add_notification_address', '', ['placeholder' => 'email@example.org']);
                                tsml_input_submit(__('Add', '12-step-meeting-list'));
                                ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
