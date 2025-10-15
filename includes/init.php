<?php

// for all users
add_action('init', function () {

    // load text domain
    tsml_load_config();

    // register post types and taxonomies
    tsml_custom_post_types();

    // Handle legacy redirects
    add_action('template_redirect', function() {
        if (is_post_type_archive('tsml_meeting') && isset($_GET['post_type']) && $_GET['post_type'] === 'tsml_meeting') {
            $get_params = $_GET;
            unset($get_params['post_type']);
            if (empty($get_params) || (count($get_params) == 1 && isset($get_params['page_id']))) {
                $redirect_url = tsml_meetings_url();
                if (!headers_sent()) {
                    wp_redirect($redirect_url);
                    exit;
                } else {
                    // Fallback: JavaScript redirect if headers already sent
                    echo '<script>window.location.href="' . esc_js($redirect_url) . '";</script>';
                    exit;
                }
            }
        }
    });

    // meeting list page
    add_filter('archive_template', function ($template) {
        global $tsml_user_interface;

        if (is_post_type_archive('tsml_meeting')) {
            if ($tsml_user_interface == 'tsml_ui') {
                // when UI switch set to tsml_ui we use special template
                tsml_redirect_legacy_query_params();
                return dirname(__FILE__) . '/../templates/archive-tsml-ui.php';
            } else {
                // legacy_ui
                $user_theme_file = get_stylesheet_directory() . '/archive-meetings.php';
                if (file_exists($user_theme_file)) {
                    return $user_theme_file;
                }
                return dirname(__FILE__) . '/../templates/archive-meetings.php';
            }
        }

        if (is_post_type_archive('tsml_location')) {
            return dirname(__FILE__) . '/../templates/archive-locations.php';
        }

        return $template;
    });


    // meeting & location detail pages
    add_filter('single_template', function ($template) {
        global $post, $tsml_user_interface;

        if ($post->post_type === 'tsml_meeting') {

            // when TSML UI is enabled, redirect legacy meeting detail page to TSML UI detail page
            if ($tsml_user_interface === 'tsml_ui') {
                return dirname(__FILE__) . '/../templates/archive-tsml-ui.php';
            }

            // user has a custom meeting detail page
            $user_theme_file = get_stylesheet_directory() . '/single-meetings.php';
            if (file_exists($user_theme_file)) {
                return $user_theme_file;
            }

            // show legacy meeting detail page
            return dirname(__FILE__) . '/../templates/single-meetings.php';
        } elseif ($post->post_type == 'tsml_location') {

            // when TSML UI is enabled, redirect legacy location page to main meetings page
            if ($tsml_user_interface == 'tsml_ui') {
                $url = tsml_meetings_url();
                wp_redirect($url);
                exit;
            }

            // user has a custom location detail page
            $user_theme_file = get_stylesheet_directory() . '/single-locations.php';
            if (file_exists($user_theme_file)) {
                return $user_theme_file;
            }

            // show legacy location detail page
            return dirname(__FILE__) . '/../templates/single-locations.php';
        }
        return $template;
    });


    // add theme name to body class, for per-theme CSS fixes
    add_filter('body_class', function ($classes) {
        $theme = wp_get_theme();
        $classes[] = sanitize_title($theme->Template);
        return $classes;
    });

});

if (is_admin()) {
    // Rebuild tsml cache when using bulk actions to edit status.
    // Uses a transient to keep track the array of post ids that are being processed
    add_action('transition_post_status', function ($new_status, $old_status, $post) {
        global $pagenow;
        if ($post->post_type === 'tsml_meeting' && $new_status === 'publish' && $pagenow !== 'post.php' && $pagenow !== 'admin-ajax.php') {
            tsml_require_meetings_permission();

            // Try to load transient first or $_GET['post'] if it doesn't exist
            if ($bulk_ids = get_transient('tsml_bulk_process')) {
                // Remove current post ID from array
                if (($key = array_search($post->ID, $bulk_ids)) !== false) {
                    unset($bulk_ids[$key]);
                }
                // Resave transient
                set_transient('tsml_bulk_process', $bulk_ids, HOUR_IN_SECONDS);
                // If the array is empty, we are done. Process now.
                if (empty($bulk_ids)) {
                    tsml_update_types_in_use();
                    tsml_cache_rebuild();
                    delete_transient('tsml_bulk_process');
                }
            } else {
                if (!empty($_GET['post'])) {
                    $bulk_ids = $_GET['post'];
                    // Remove current post ID from array
                    if (($key = array_search($post->ID, $bulk_ids)) !== false) {
                        unset($bulk_ids[$key]);
                    }
                    // Set transient. Expire in 5 minutes.
                    set_transient('tsml_bulk_process', $bulk_ids, 5 * MINUTE_IN_SECONDS);
                }
            }
        }
    }, 10, 3);
    // rebuild cache when trashing or untrashing posts
    add_action('trashed_post', 'tsml_trash_change');
    add_action('untrashed_post', 'tsml_trash_change');
    function tsml_trash_change($post_id)
    {
        if (get_post_type($post_id) === 'tsml_meeting') {
            tsml_cache_rebuild();
        }
    }
} else {
    // add plugin version number to header on public site
    add_action('wp_head', function () {
        global $tsml_sharing;
        echo '<meta name="12_step_meeting_list" content="' . esc_attr(TSML_VERSION) . '">' . PHP_EOL;
        if ($tsml_sharing == 'open') {
            echo '<link rel="alternate" type="application/json" title="Meetings Feed" href="' . esc_attr(admin_url('admin-ajax.php?action=meetings')) . '">' . PHP_EOL;
        }
    });
}
