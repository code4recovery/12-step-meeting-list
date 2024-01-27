<?php

add_action('admin_menu', function () {
    global $tsml_google_maps_key, $tsml_mapbox_key;

    //badge the settings page
    $warnings = 0;
    if (empty($tsml_google_maps_key) && empty($tsml_mapbox_key)) {
        $warnings++;
    }

    if (!is_ssl()) {
        $warnings++;
    }

    $badge = $warnings ? ' <span class="update-plugins"><span class="update-count">1</span></span>' : '';

    //add menu items
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

// Add a Widget to the main WordPress Dashboard Page
add_action(
    'wp_dashboard_setup',
    function () {
        wp_add_dashboard_widget('tsml_help_widget', '12 Step Meeting List Plugin', function () {
            printf(
                '<p>' . __('%1$s is a nonprofit organization of volunteer members building technology services for recovery fellowships, such as AA and Al-Anon. If you need help, please %2$s join our discussion forum%3$s. If you would like to make a tax-deductible contribution, please %4$s visit our website%5$s.', '12-step-meeting-list') . '</p>',
                '<a href="https://code4recovery.org/">Code for Recovery</a>',
                '<a href="https://github.com/code4recovery/12-step-meeting-list/discussions">',
                '</a>',
                '<a href="https://code4recovery.org/">',
                '</a>'
            );
        }, null, null, 'normal', 'high');
    }
);

