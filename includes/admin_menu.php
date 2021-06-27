<?php

add_action('admin_menu', 'tsml_admin_menu');

function tsml_admin_menu()
{
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

}
