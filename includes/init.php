<?php

//for all users
add_action('init', 'tsml_init');

function tsml_init()
{

    //register post types and taxonomies
    tsml_custom_post_types();

    //meeting list page
    add_filter('archive_template', 'tsml_archive_template');
    function tsml_archive_template($template)
    {
        if (is_post_type_archive('tsml_meeting')) {
            $user_theme_file = get_stylesheet_directory() . '/archive-meetings.php';
            if (file_exists($user_theme_file)) {
                return $user_theme_file;
            }

            return dirname(__FILE__) . '/../templates/archive-meetings.php';
        }
        return $template;
    }

    //meeting & location detail pages
    add_filter('single_template', 'tsml_single_template');
    function tsml_single_template($template)
    {
        global $post;
        if ($post->post_type == 'tsml_meeting') {
            $user_theme_file = get_stylesheet_directory() . '/single-meetings.php';
            if (file_exists($user_theme_file)) {
                return $user_theme_file;
            }

            return dirname(__FILE__) . '/../templates/single-meetings.php';
        } elseif ($post->post_type == 'tsml_location') {
            $user_theme_file = get_stylesheet_directory() . '/single-locations.php';
            if (file_exists($user_theme_file)) {
                return $user_theme_file;
            }

            return dirname(__FILE__) . '/../templates/single-locations.php';
        }
        return $template;
    }

    //add theme name to body class, for per-theme CSS fixes
    add_filter('body_class', 'tsml_theme_name');
    function tsml_theme_name($classes)
    {
        $theme = wp_get_theme();
        $classes[] = sanitize_title($theme->Template);
        return $classes;
    }
}

if (is_admin()) {
    //rebuild cache when trashing or untrashing posts
    add_action('trashed_post', 'tsml_trash_change');
    add_action('untrashed_post', 'tsml_trash_change');
    function tsml_trash_change($post_id)
    {
        if (get_post_type($post_id) === 'tsml_meeting') {
            tsml_cache_rebuild();
        }
    }
} else {
    //add plugin version number to header on public site
    add_action('wp_head', 'tsml_head');
    function tsml_head()
    {
        global $tsml_sharing;
        echo '<meta name="12_step_meeting_list" content="' . TSML_VERSION . '">' . PHP_EOL;
        if ($tsml_sharing == 'open') {
            echo '<link rel="alternate" type="application/json" title="Meetings Feed" href="' . admin_url('admin-ajax.php?action=meetings') . '">' . PHP_EOL;
        }
    }
}
