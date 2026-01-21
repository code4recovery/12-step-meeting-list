<?php

add_action('admin_menu', function () {

    // badge the settings page
    $warnings = 0;
    if (!is_ssl()) {
        $warnings++;
    }

    $badge = $warnings ? ' <span class="update-plugins"><span class="update-count">' . $warnings . '</span></span>' : '';

    // add menu items
    add_submenu_page(
        'edit.php?post_type=tsml_meeting',
        __('Regions', '12-step-meeting-list'),
        __('Regions', '12-step-meeting-list'),
        TSML_MEETINGS_PERMISSION,
        'edit-tags.php?taxonomy=tsml_region&post_type=tsml_location'
    );
    add_submenu_page(
        'edit.php?post_type=tsml_meeting',
        __('Districts', '12-step-meeting-list'),
        __('Districts', '12-step-meeting-list'),
        TSML_MEETINGS_PERMISSION,
        'edit-tags.php?taxonomy=tsml_district&post_type=tsml_group'
    );
    add_submenu_page(
        'edit.php?post_type=tsml_meeting',
        __('Import & Export', '12-step-meeting-list'),
        __('Import & Export', '12-step-meeting-list'),
        TSML_MEETINGS_PERMISSION,
        'import',
        'tsml_import_page'
    );
    add_submenu_page(
        'edit.php?post_type=tsml_meeting',
        __('Generate PDF', '12-step-meeting-list'),
        __('Generate PDF', '12-step-meeting-list'),
        TSML_MEETINGS_PERMISSION,
        'pdf',
        'tsml_pdf_page'
    );
    add_submenu_page(
        'edit.php?post_type=tsml_meeting',
        __('Event Log', '12-step-meeting-list'),
        __('Event Log', '12-step-meeting-list'),
        TSML_SETTINGS_PERMISSION,
        'log',
        'tsml_log_page'
    );
    add_submenu_page(
        'edit.php?post_type=tsml_meeting',
        __('Settings', '12-step-meeting-list'),
        __('Settings', '12-step-meeting-list') . $badge,
        TSML_SETTINGS_PERMISSION,
        'settings',
        'tsml_settings_page'
    );

    // don't collapse the menu when regions or distrits are selected
    add_filter('parent_file', function ($parent_file) {
        global $submenu_file, $current_screen, $pagenow;
        if ($current_screen->post_type == 'tsml_location') {
            if ($pagenow == 'edit-tags.php') {
                $submenu_file = 'edit-tags.php?taxonomy=tsml_region&post_type=tsml_location';
            }
            $parent_file = 'edit.php?post_type=tsml_meeting';
        } elseif ($current_screen->post_type == 'tsml_group') {
            if ($pagenow == 'edit-tags.php') {
                $submenu_file = 'edit-tags.php?taxonomy=tsml_district&post_type=tsml_group';
            }
            $parent_file = 'edit.php?post_type=tsml_meeting';
        }
        return $parent_file;
    });
});

// add a widget to the main dashboard page
add_action(
    'wp_dashboard_setup',
    function () {
        wp_add_dashboard_widget('tsml_help_widget', '12 Step Meeting List Plugin', 'tsml_about_message', null, null, 'normal', 'high');
    }
);

