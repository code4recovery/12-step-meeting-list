<?php

add_action('admin_menu', function () {
    global $tsml_google_maps_key, $tsml_mapbox_key;

    //badge the import & settings page
    $warnings = 0;
    if (empty($tsml_google_maps_key) && empty($tsml_mapbox_key)) {
        $warnings++;
    }

    if (!is_ssl()) {
        $warnings++;
    }

    $badge = $warnings ? ' <span class="update-plugins"><span class="update-count">1</span></span>' : '';

    //add menu items
    add_submenu_page('edit.php?post_type=tsml_meeting', __('Regions', '12-step-meeting-list'), __('Regions', '12-step-meeting-list'), 'edit_posts', 'edit-tags.php?taxonomy=tsml_region&post_type=tsml_location');
    add_submenu_page('edit.php?post_type=tsml_meeting', __('Districts', '12-step-meeting-list'), __('Districts', '12-step-meeting-list'), 'edit_posts', 'edit-tags.php?taxonomy=tsml_district&post_type=tsml_group');
    add_submenu_page('edit.php?post_type=tsml_meeting', __('Import & Settings', '12-step-meeting-list'), __('Import & Settings', '12-step-meeting-list') . $badge, 'manage_options', 'import', 'tsml_import_page');

    //fix the highlighted state of the regions page
    function tsml_fix_highlight($parent_file)
    {
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
    }
    add_filter('parent_file', 'tsml_fix_highlight');
});

// Add a Widget to the main WordPress Dashboard Page
add_action('wp_dashboard_setup', 'tsml_dashboard_widgets');
function tsml_dashboard_widgets()
{
    global $wp_meta_boxes;

    wp_add_dashboard_widget('tsml_help_widget', 'Code for Recovery/12 Step Meeting List(TSML) Plugin', 'tsml_dashboard_help');
}
function tsml_dashboard_help()
{
    echo '<p><a href="https://code4recovery.org/">Code for Recovery</a>, the group that maintains TSML, and other recovery based projects, has a <a href="https://code4recovery.org/">new web site</a>. We continue to add information to this site, including information about our group, our projects, our volunteer opportunities. We are also adding project documentation to the website.</p>';
    echo '<p>Code for Recovery now has 501c3 nonprofit status, so we can take contribuitions to cover costs and build up a prudent reserve. More information at our <a href="https://code4recovery.org/donate">Donate to Code for Recovery</a> page.</p>';
}
