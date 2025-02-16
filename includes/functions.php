<?php

/**
 * "about this plugin" content
 * used: admin_menu.php and admin_settings.php
 * 
 * @return void
 */
function tsml_about_message()
{
    tsml_assets();
    ?>
    <p>
        <a href="https://code4recovery.org/" target="_blank" class="logo">
            <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/img/code4recovery.svg') ?>"
                alt="Code for Recovery">
        </a>
        <?php echo wp_kses(__('<strong>Code for Recovery</strong> is a nonprofit organization of volunteer members building technology services for recovery fellowships, such as A.A. and Al-Anon.', '12-step-meeting-list'), TSML_ALLOWED_HTML) ?>
    </p>
    <p>
        <strong><?php esc_html_e('Support our mission with a recurring contribution!', '12-step-meeting-list') ?></strong>
    </p>
    <p>
        <?php esc_html_e('Your donations help cover hosting fees, content delivery, geocoding, and other essential services that enable recovery communities to thrive. Every contribution makes a difference.', '12-step-meeting-list') ?>
    </p>
    <p>
        <a href="https://wordpress.org/plugins/12-step-meeting-list/#faq-header" target="_blank" class="button">
            <?php esc_html_e('View Documentation', '12-step-meeting-list') ?>
        </a>
        <a href="https://github.com/code4recovery/12-step-meeting-list/discussions" target="_blank" class="button">
            <?php esc_html_e('Request Help', '12-step-meeting-list') ?>
        </a>
        <a href="https://code4recovery.org/contribute" target="_blank" class="button button-primary">
            <?php esc_html_e('Contribute', '12-step-meeting-list') ?>
        </a>
    </p>
    <?php
}

/**
 * render a list of meetings for a location or group
 * used: admin_meeting.php
 * 
 * @param mixed $meetings
 * @param mixed $meeting_id
 * @return void
 */
function tsml_admin_meeting_list($meetings, $meeting_id)
{
    $output = '<ol>';
    foreach ($meetings as $m) {
        $output .= '<li>';
        $output .= '<span>' . tsml_format_day_and_time($m['day'], $m['time'], ' ', true) . '</span>';
        if ($m['id'] != $meeting_id) {
            $output .= '<a href="' . get_edit_post_link($m['id']) . '">' . $m['name'] . '</a>';
        } else {
            $output .= $m['name'];
        }
        $output .= '</li>';
    }
    $output .= '</ol>';
    echo wp_kses($output, ['ol' => [], 'li' => [], 'a' => ['href' => []], 'span' => [], 'time' => []]);
}

/**
 * add an admin screen update message
 * used: tsml_import() and admin_types.php
 * 
 * @param mixed $message
 * @param mixed $type can be success, warning, info, or error
 * @return void
 */
function tsml_alert($message, $type = 'success')
{
    echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . wp_kses($message, TSML_ALLOWED_HTML) . '</p></div>';
}

/**
 * enqueue assets for public or admin page
 * used: in templates and on admin_edit.php
 * 
 * @return void
 */
function tsml_assets()
{
    global $post_type, $tsml_street_only, $tsml_programs, $tsml_strings, $tsml_program, $tsml_google_maps_key,
    $tsml_mapbox_key, $tsml_mapbox_theme, $tsml_distance_units, $tsml_defaults, $tsml_columns, $tsml_nonce;

    // google maps api
    if ($tsml_google_maps_key) {
        wp_enqueue_script('google_maps_api', "//maps.googleapis.com/maps/api/js?key=$tsml_google_maps_key", [], TSML_VERSION, true);
    }

    if (is_admin()) {
        // dashboard page assets
        wp_enqueue_style('tsml_admin', plugins_url('../assets/css/admin.min.css', __FILE__), [], TSML_VERSION);
        wp_enqueue_script('tsml_admin', plugins_url('../assets/js/admin.min.js', __FILE__), ['jquery'], TSML_VERSION, true);
        wp_localize_script('tsml_admin', 'tsml', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'debug' => WP_DEBUG,
            'google_maps_key' => $tsml_google_maps_key, // to see if map should have been called
            'mapbox_key' => $tsml_mapbox_key,
            'mapbox_theme' => $tsml_mapbox_theme,
            'nonce' => wp_create_nonce($tsml_nonce),
        ]);
    } else {
        // public page assets
        global $post;

        wp_enqueue_style('tsml_public', plugins_url('../assets/css/public.min.css', __FILE__), [], TSML_VERSION);
        wp_enqueue_script('jquery_validate', plugins_url('../assets/js/jquery.validate.min.js', __FILE__), ['jquery'], TSML_VERSION, true);
        wp_enqueue_script('tsml_public', plugins_url('../assets/js/public.min.js', __FILE__), ['jquery'], TSML_VERSION, true);
        wp_enqueue_script('jquery-ui-autocomplete');
        wp_localize_script('tsml_public', 'tsml', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'columns' => array_keys($tsml_columns),
            'days' => [
                __('Sunday', '12-step-meeting-list'),
                __('Monday', '12-step-meeting-list'),
                __('Tuesday', '12-step-meeting-list'),
                __('Wednesday', '12-step-meeting-list'),
                __('Thursday', '12-step-meeting-list'),
                __('Friday', '12-step-meeting-list'),
                __('Saturday', '12-step-meeting-list'),
            ],
            'debug' => WP_DEBUG,
            'defaults' => $tsml_defaults,
            'distance_units' => $tsml_distance_units,
            'flags' => $tsml_programs[$tsml_program]['flags'],
            'google_maps_key' => $tsml_google_maps_key, // to see if map should have been called
            'mapbox_key' => $tsml_mapbox_key,
            'mapbox_theme' => $tsml_mapbox_theme,
            'nonce' => wp_create_nonce($tsml_nonce),
            'program' => empty($tsml_programs[$tsml_program]['abbr']) ? $tsml_programs[$tsml_program]['name'] : $tsml_programs[$tsml_program]['abbr'],
            'street_only' => $tsml_street_only,
            'strings' => $tsml_strings,
            'types' => empty($tsml_programs[$tsml_program]['types']) ? [] : $tsml_programs[$tsml_program]['types'],
            'meeting_id' => isset($post->ID) ? $post->ID : '',
        ]);
    }
}

/**
 * set geo boundaries from current data (for biased geocoding)
 * 
 * @return void
 */
function tsml_bounds()
{
    global $wpdb, $tsml_bounds;

    // get north & south
    $latitudes = $wpdb->get_row('SELECT
			MAX(m.meta_value) north,
			MIN(m.meta_value) south
		FROM ' . $wpdb->postmeta . ' m
		JOIN ' . $wpdb->posts . ' p ON p.ID = m.post_id
		WHERE m.meta_key = "latitude" AND p.post_type = "tsml_location"');

    // get east & west
    $longitudes = $wpdb->get_row('SELECT
			MAX(m.meta_value) west,
			MIN(m.meta_value) east
		FROM ' . $wpdb->postmeta . ' m
		JOIN ' . $wpdb->posts . ' p ON p.ID = m.post_id
		WHERE m.meta_key = "longitude" AND p.post_type = "tsml_location"');

    // if results, get bounding box and cache it
    if ($latitudes && $longitudes) {

        // add 25% margin to the bounds
        $width = ($longitudes->east - $longitudes->west) / 25;
        $height = ($latitudes->north - $latitudes->south) / 25;

        $tsml_bounds = [
            'north' => $latitudes->north + $height,
            'east' => $longitudes->east + $width,
            'south' => $latitudes->south - $height,
            'west' => $longitudes->west - $width,
        ];

        update_option('tsml_bounds', $tsml_bounds);
    }
}

/**
 * try to build a cache of meetings to help with CPU load
 * 
 * @return void
 */
function tsml_cache_rebuild()
{
    // flush wp object cache
    wp_cache_flush();

    // rebuild TSML meeting cache file
    tsml_get_meetings([], false);
}

/**
 * calculate attendance option given types and address
 * called in tsml_get_meetings()
 * 
 * @param mixed $types
 * @param mixed $approximate
 * @return string
 */
function tsml_calculate_attendance_option($types, $approximate)
{
    $attendance_option = '';

    // Handle when the types list is empty, this prevents PHP warnings
    if (empty($types)) {
        $types = [];
    }

    if (in_array('TC', $types) && in_array('ONL', $types)) {
        // Types has both Location Temporarily Closed and Online, which means it should be an online meeting
        $attendance_option = 'online';
    } elseif (in_array('TC', $types)) {
        // Types has Location Temporarily Closed, but not online, which means it really is temporarily closed
        $attendance_option = 'inactive';
    } elseif (in_array('ONL', $types)) {
        // Types has Online, but not Temp closed, which means it's a hybrid (or online)
        $attendance_option = 'hybrid';
        if ($approximate == 'yes') {
            $attendance_option = 'online';
        }
    } else {
        // Neither Online or Temp Closed, which means it's in person (or inactive)
        $attendance_option = 'in_person';
        if ($approximate == 'yes') {
            $attendance_option = 'inactive';
        }
    }

    return $attendance_option;
}

/**
 * Render svg icon icon
 * 
 * @param string $icon cash|directions|email|link|phone
 * @return void
 */
function tsml_icon($icon)
{ ?>
    <svg class="icon" viewBox="0 0 16 16" fill="currentColor">
        <?php if ($icon === 'cash') { ?>
            <path d="M14 3H1a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1h-1z" />
            <path fill-rule="evenodd"
                d="M15 5H1v8h14V5zM1 4a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1H1z" />
            <path
                d="M13 5a2 2 0 0 0 2 2V5h-2zM3 5a2 2 0 0 1-2 2V5h2zm10 8a2 2 0 0 1 2-2v2h-2zM3 13a2 2 0 0 0-2-2v2h2zm7-4a2 2 0 1 1-4 0 2 2 0 0 1 4 0z" />
        <?php } elseif ($icon === 'check') { ?>
            <path
                d="M10.97 4.97a.75.75 0 0 1 1.071 1.05l-3.992 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.236.236 0 0 1 .02-.022z" />
        <?php } elseif ($icon === 'directions') { ?>
            <path
                d="M9.896 2.396a.5.5 0 0 0 0 .708l2.647 2.646-2.647 2.646a.5.5 0 1 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708 0z" />
            <path
                d="M13.25 5.75a.5.5 0 0 0-.5-.5h-6.5a2.5 2.5 0 0 0-2.5 2.5v5.5a.5.5 0 0 0 1 0v-5.5a1.5 1.5 0 0 1 1.5-1.5h6.5a.5.5 0 0 0 .5-.5z" />
        <?php } elseif ($icon === 'email') { ?>
            <path
                d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2zm13 2.383l-4.758 2.855L15 11.114v-5.73zm-.034 6.878L9.271 8.82 8 9.583 6.728 8.82l-5.694 3.44A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.739zM1 11.114l4.758-2.876L1 5.383v5.73z" />
        <?php } elseif ($icon === 'link') { ?>
            <path
                d="M4.715 6.542L3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1.001 1.001 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4.018 4.018 0 0 1-.128-1.287z" />
            <path
                d="M5.712 6.96l.167-.167a1.99 1.99 0 0 1 .896-.518 1.99 1.99 0 0 1 .518-.896l.167-.167A3.004 3.004 0 0 0 6 5.499c-.22.46-.316.963-.288 1.46z" />
            <path
                d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 0 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 0 0-4.243-4.243L6.586 4.672z" />
            <path
                d="M10 9.5a2.99 2.99 0 0 0 .288-1.46l-.167.167a1.99 1.99 0 0 1-.896.518 1.99 1.99 0 0 1-.518.896l-.167.167A3.004 3.004 0 0 0 10 9.501z" />
        <?php } elseif ($icon === 'phone') { ?>
            <path
                d="M3.925 1.745a.636.636 0 0 0-.951-.059l-.97.97c-.453.453-.62 1.095-.421 1.658A16.47 16.47 0 0 0 5.49 10.51a16.471 16.471 0 0 0 6.196 3.907c.563.198 1.205.032 1.658-.421l.97-.97a.636.636 0 0 0-.06-.951l-2.162-1.682a.636.636 0 0 0-.544-.115l-2.052.513a1.636 1.636 0 0 1-1.554-.43L5.64 8.058a1.636 1.636 0 0 1-.43-1.554l.513-2.052a.636.636 0 0 0-.115-.544L3.925 1.745zM2.267.98a1.636 1.636 0 0 1 2.448.153l1.681 2.162c.309.396.418.913.296 1.4l-.513 2.053a.636.636 0 0 0 .167.604L8.65 9.654a.636.636 0 0 0 .604.167l2.052-.513a1.636 1.636 0 0 1 1.401.296l2.162 1.681c.777.604.849 1.753.153 2.448l-.97.97c-.693.693-1.73.998-2.697.658a17.47 17.47 0 0 1-6.571-4.144A17.47 17.47 0 0 1 .639 4.646c-.34-.967-.035-2.004.658-2.698l.97-.969z" />
        <?php } elseif ($icon === 'video') { ?>
            <path
                d="M2.667 3.5c-.645 0-1.167.522-1.167 1.167v6.666c0 .645.522 1.167 1.167 1.167h6.666c.645 0 1.167-.522 1.167-1.167V4.667c0-.645-.522-1.167-1.167-1.167H2.667zM.5 4.667C.5 3.47 1.47 2.5 2.667 2.5h6.666c1.197 0 2.167.97 2.167 2.167v6.666c0 1.197-.97 2.167-2.167 2.167H2.667A2.167 2.167 0 0 1 .5 11.333V4.667z" />
            <path
                d="M11.25 5.65l2.768-1.605a.318.318 0 0 1 .482.263v7.384c0 .228-.26.393-.482.264l-2.767-1.605-.502.865 2.767 1.605c.859.498 1.984-.095 1.984-1.129V4.308c0-1.033-1.125-1.626-1.984-1.128L10.75 4.785l.502.865z" />
        <?php } ?>
    </svg>
<?php }

/**
 * Used on frontend pages
 * 
 * @param mixed $link
 * @param mixed $text
 * @param mixed $icon cash|directions|email|link|phone
 * @return void
 */
function tsml_icon_button($link, $text, $icon)
{
    ?>
    <a href="<?php echo esc_attr($link) ?>" class="btn btn-default btn-block">
        <?php tsml_icon($icon) ?>
        <?php echo esc_html($text) ?>
    </a>
    <?php
}

/**
 * handle plugin activation
 * called by register_activation_hook in 12-step-meeting-list.php
 * 
 * @return void
 */
function tsml_plugin_activation()
{
    tsml_custom_post_types();
    flush_rewrite_rules();
}

/**
 * clean up custom taxonomies / post types and flush rewrite rules
 * called by register_deactivation_hook in 12-step-meeting-list.php
 * 
 * @return void
 */
function tsml_plugin_deactivation()
{
    if (taxonomy_exists('tsml_region')) {
        unregister_taxonomy('tsml_region');
    }
    if (taxonomy_exists('tsml_location')) {
        unregister_taxonomy('tsml_location');
    }
    if (taxonomy_exists('tsml_district')) {
        unregister_taxonomy('tsml_district');
    }
    if (post_type_exists('tsml_meeting')) {
        unregister_post_type('tsml_meeting');
    }
    if (post_type_exists('tsml_location')) {
        unregister_post_type('tsml_location');
    }
    if (post_type_exists('tsml_group')) {
        unregister_post_type('tsml_group');
    }
    flush_rewrite_rules();
}

/**
 * validate conference provider and return name
 * 
 * @param mixed $url
 * @return mixed
 */
function tsml_conference_provider($url)
{
    global $tsml_conference_providers;
    if (empty($tsml_conference_providers)) {
        return true; // don't provide validation
    }
    $domains = array_keys($tsml_conference_providers);
    $url_parts = parse_url($url);
    foreach ($domains as $domain) {
        if (tsml_string_ends($url_parts['host'], $domain)) {
            return $tsml_conference_providers[$domain];
        }
    }
    return false;
}

/**
 * get an array of conference provider names
 * used: meeting edit screen
 * 
 * @return array
 */
function tsml_conference_providers()
{
    global $tsml_conference_providers;
    if (empty($tsml_conference_providers)) {
        return [];
    }
    $providers = array_unique(array_values($tsml_conference_providers));
    natcasesort($providers);
    return $providers;
}

/**
 * return integer number of live groups
 * used: shortcode, admin-import.php, tsml_ajax_import()
 * 
 * @return int
 */
function tsml_count_groups()
{
    return count(tsml_get_all_groups('publish'));
}

/**
 * return integer number of live locations
 * used: shortcode, admin-import.php, tsml_ajax_import()
 * 
 * @return int
 */
function tsml_count_locations()
{
    return count(tsml_get_all_locations('publish'));
}

/**
 * return integer number of live meetings
 * used: shortcode, admin-import.php, tsml_ajax_import()
 * 
 * @return int
 */
function tsml_count_meetings()
{
    return count(tsml_get_all_meetings('publish'));
}

/**
 * return integer number of live regions
 * used: shortcode, admin-import.php, tsml_ajax_import()
 * 
 * @return int
 */
function tsml_count_regions()
{
    return count(tsml_get_all_regions());
}

/**
 * add local overrides to google (this may someday be removed)
 * used: in user themes
 * 
 * @param mixed $custom_overrides
 * @return void
 */
function tsml_custom_addresses($custom_overrides)
{
    global $tsml_google_overrides;
    $tsml_google_overrides = array_merge((array) $tsml_google_overrides, (array) $custom_overrides);
}

/**
 * define custom descriptions for your area
 * used: theme's functions.php
 * 
 * @param mixed $descriptions
 * @return void
 */
function tsml_custom_descriptions($descriptions)
{
    add_action('init', function () use ($descriptions) {
        global $tsml_programs, $tsml_program;
        $tsml_programs[$tsml_program]['type_descriptions'] = $descriptions;
    });
}

/**
 * define custom flags for your area
 * used: theme's functions.php
 * 
 * @param mixed $flags
 * @return void
 */
function tsml_custom_flags($flags)
{
    add_action('init', function () use ($flags) {
        global $tsml_programs, $tsml_program;
        $tsml_programs[$tsml_program]['flags'] = $flags;
    });
}

/**
 * register custom post types
 * used: init.php on every request, also in change_activation_state() for plugin activation or deactivation
 * 
 * @return void
 */
function tsml_custom_post_types()
{
    global $tsml_slug;

    $is_public = !empty($tsml_slug);

    register_taxonomy('tsml_region', 'tsml_location', [
        'labels' => [
            'name' => __('Regions', '12-step-meeting-list'),
            'singular_name' => __('Region', '12-step-meeting-list'),
            'menu_name' => __('Regions', '12-step-meeting-list'),
            'all_items' => __('All Regions', '12-step-meeting-list'),
            'edit_item' => __('Edit Region', '12-step-meeting-list'),
            'view_item' => __('View Region', '12-step-meeting-list'),
            'update_item' => __('Update Region', '12-step-meeting-list'),
            'add_new_item' => __('Add New Region', '12-step-meeting-list'),
            'new_item_name' => __('New Region', '12-step-meeting-list'),
            'parent_item' => __('Parent Region', '12-step-meeting-list'),
            'parent_item_colon' => __('Parent Region:', '12-step-meeting-list'),
            'search_items' => __('Search Regions', '12-step-meeting-list'),
            'popular_items' => __('Popular Regions', '12-step-meeting-list'),
            'not_found' => __('No regions found.', '12-step-meeting-list'),
        ],
        'hierarchical' => true,
        'public' => false,
        'show_ui' => true,
    ]);

    register_taxonomy('tsml_district', 'tsml_group', [
        'labels' => [
            'name' => __('District', '12-step-meeting-list'),
            'singular_name' => __('District', '12-step-meeting-list'),
            'menu_name' => __('District', '12-step-meeting-list'),
            'all_items' => __('All Districts', '12-step-meeting-list'),
            'edit_item' => __('Edit District', '12-step-meeting-list'),
            'view_item' => __('View District', '12-step-meeting-list'),
            'update_item' => __('Update District', '12-step-meeting-list'),
            'add_new_item' => __('Add New District', '12-step-meeting-list'),
            'new_item_name' => __('New District', '12-step-meeting-list'),
            'parent_item' => __('Parent Area', '12-step-meeting-list'),
            'parent_item_colon' => __('Parent Area:', '12-step-meeting-list'),
            'search_items' => __('Search Districts', '12-step-meeting-list'),
            'popular_items' => __('Popular Districts', '12-step-meeting-list'),
            'not_found' => __('No districts found.', '12-step-meeting-list'),
        ],
        'hierarchical' => true,
        'public' => false,
        'show_ui' => true,
    ]);

    register_post_type(
        'tsml_meeting',
        [
            'labels' => [
                'name' => __('Meetings', '12-step-meeting-list'),
                'singular_name' => __('Meeting', '12-step-meeting-list'),
                'not_found' => __('No meetings added yet.', '12-step-meeting-list'),
                'add_new_item' => __('Add New Meeting', '12-step-meeting-list'),
                'search_items' => __('Search Meetings', '12-step-meeting-list'),
                'edit_item' => __('Edit Meeting', '12-step-meeting-list'),
                'view_item' => __('View Meeting', '12-step-meeting-list'),
            ],
            'supports' => ['title', 'author'],
            'public' => $is_public,
            'show_ui' => true,
            'has_archive' => $is_public,
            'menu_icon' => 'dashicons-groups',
            'rewrite' => ['slug' => $tsml_slug, 'with_front' => apply_filters('tsml_meeting_with_front', true)],
        ]
    );

    register_post_type(
        'tsml_location',
        [
            'labels' => [
                'name' => __('Locations', '12-step-meeting-list'),
                'singular_name' => __('Location', '12-step-meeting-list'),
                'menu_name' => __('Locations', '12-step-meeting-list'),
                'all_items' => __('All Locations', '12-step-meeting-list'),
                'edit_item' => __('Edit Location', '12-step-meeting-list'),
                'view_item' => __('View Location', '12-step-meeting-list'),
                'update_item' => __('Update Location', '12-step-meeting-list'),
                'add_new_item' => __('Add New Location', '12-step-meeting-list'),
                'new_item_name' => __('New Location', '12-step-meeting-list'),
                'parent_item' => __('Parent Location', '12-step-meeting-list'),
                'parent_item_colon' => __('Parent Location:', '12-step-meeting-list'),
                'search_items' => __('Search Locations', '12-step-meeting-list'),
                'popular_items' => __('Popular Locations', '12-step-meeting-list'),
                'not_found' => __('No locations found.', '12-step-meeting-list'),
            ],
            'supports' => ['title'],
            'public' => $is_public,
            'show_ui' => false,
            'has_archive' => $is_public,
            'capabilities' => ['create_posts' => false],
            'rewrite' => ['slug' => 'locations'],
            'taxonomies' => ['tsml_region'],
        ]
    );

    register_post_type(
        'tsml_group',
        [
            'labels' => [
                'name' => __('Groups', '12-step-meeting-list'),
                'singular_name' => __('Group', '12-step-meeting-list'),
                'menu_name' => __('Groups', '12-step-meeting-list'),
                'all_items' => __('All Groups', '12-step-meeting-list'),
                'edit_item' => __('Edit Group', '12-step-meeting-list'),
                'view_item' => __('View Group', '12-step-meeting-list'),
                'update_item' => __('Update Group', '12-step-meeting-list'),
                'add_new_item' => __('Add New Group', '12-step-meeting-list'),
                'new_item_name' => __('New Group', '12-step-meeting-list'),
                'parent_item' => __('Parent Group', '12-step-meeting-list'),
                'parent_item_colon' => __('Parent Group:', '12-step-meeting-list'),
                'search_items' => __('Search Groups', '12-step-meeting-list'),
                'popular_items' => __('Popular Groups', '12-step-meeting-list'),
                'not_found' => __('No groups found.', '12-step-meeting-list'),
            ],
            'supports' => ['title'],
            'public' => true,
            'show_ui' => false,
            'has_archive' => false,
            'capabilities' => ['create_posts' => false],
        ]
    );
}

/**
 * define custom meeting types for your area
 * used: theme's functions.php
 * 
 * @param mixed $types
 * @return void
 */
function tsml_custom_types($types)
{
    add_action('init', function () use ($types) {
        global $tsml_programs, $tsml_program;
        foreach ($types as $key => $value) {
            $tsml_programs[$tsml_program]['types'][$key] = $value;
        }
        asort($tsml_programs[$tsml_program]['types']);
    });
}

/**
 * function used for debugging
 * 
 * @param mixed $obj
 * @return never
 */
function tsml_dd($obj)
{
    echo '<pre>';
    print_r($obj);
    echo '</pre>';
    exit;
}

/**
 * efficiently remove an array of post_ids
 * used: tsml_delete_orphans(), admin-import.php
 * 
 * @param mixed $post_ids
 * @return void
 */
function tsml_delete($post_ids)
{
    global $wpdb;

    //special case
    if ($post_ids == 'everything') {

        $post_ids = get_posts([
            'post_type' => ['tsml_meeting', 'tsml_location', 'tsml_group'],
            'post_status' => 'any',
            'fields' => 'ids',
            'numberposts' => -1,
        ]);

        //when we're deleting *everything*, also delete regions & districts
        if ($term_ids = implode(',', $wpdb->get_col('SELECT term_id FROM ' . $wpdb->term_taxonomy . ' WHERE taxonomy IN ("tsml_district", "tsml_region")'))) {
            $wpdb->query('DELETE FROM ' . $wpdb->terms . ' WHERE term_id IN (' . $term_ids . ')');
            $wpdb->query('DELETE FROM ' . $wpdb->term_taxonomy . ' WHERE term_id IN (' . $term_ids . ')');
        }
    }

    if (empty($post_ids) || !is_array($post_ids)) {
        return;
    }

    // sanitize
    $post_ids = array_map('intval', $post_ids);
    $post_ids = array_unique($post_ids);
    $post_ids = implode(', ', $post_ids);

    // run deletes
    $wpdb->query('DELETE FROM ' . $wpdb->posts . ' WHERE ID IN (' . $post_ids . ')');
    $wpdb->query('DELETE FROM ' . $wpdb->postmeta . ' WHERE post_id IN (' . $post_ids . ')');
    $wpdb->query('DELETE FROM ' . $wpdb->term_relationships . ' WHERE object_id IN (' . $post_ids . ')');

    // rebuild cache
    tsml_cache_rebuild();
}

/**
 * efficiently deletes all orphaned locations and groups (have no meetings associated)
 * used: save_post filter
 * 
 * @return void
 */
function tsml_delete_orphans()
{
    global $wpdb;
    $location_ids = $wpdb->get_col('SELECT l.ID FROM ' . $wpdb->posts . ' l WHERE l.post_type = "tsml_location" AND (SELECT COUNT(*) FROM ' . $wpdb->posts . ' m WHERE m.post_type="tsml_meeting" AND m.post_parent = l.id) = 0');
    $group_ids = $wpdb->get_col('SELECT g.ID FROM ' . $wpdb->posts . ' g WHERE g.post_type = "tsml_group" AND (SELECT COUNT(*) FROM ' . $wpdb->postmeta . ' m WHERE m.meta_key="group_id" AND m.meta_value = g.id) = 0');
    tsml_delete(array_merge($location_ids, $group_ids));

    // edge case: draft-ify locations with only unpublished meetings
    $location_ids = $wpdb->get_col('SELECT l.ID FROM ' . $wpdb->posts . ' l
		WHERE l.post_type = "tsml_location" AND
			(SELECT COUNT(*) FROM ' . $wpdb->posts . ' m
			WHERE m.post_type="tsml_meeting" AND m.post_status="publish" AND m.post_parent = l.id) = 0');
    if (count($location_ids)) {
        $wpdb->query('UPDATE ' . $wpdb->posts . ' l SET l.post_status = "draft" WHERE ID IN (' . implode(', ', $location_ids) . ')');
    }
}

/**
 * send a nice-looking email
 * used by tsml_ajax_feedback() and save.php (change notifications)
 * 
 * @param mixed $to
 * @param mixed $subject
 * @param mixed $message
 * @param mixed $reply_to
 * @return bool
 */
function tsml_email($to, $subject, $message, $reply_to = false)
{

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    if ($reply_to) {
        $headers[] = 'Reply-To: ' . $reply_to;
    }

    // prepend subject as h1
    $message = '<h1>' . $subject . '</h1>' . $message;

    // inline styles where necessary
    $message = str_replace('<h1>', '<h1 style="margin: 0; font-weight:bold; font-size:24px;">', $message);
    $message = str_replace('<hr>', '<hr style="margin: 15px 0; border: 0; height: 1px; background: #cccccc;">', $message);
    $message = str_replace('<p>', '<p style="margin: 1em 0;">', $message);
    $message = str_replace('<a ', '<a style="color: #6699cc; text-decoration: underline;" ', $message);

    // wrap message in email-compliant html
    $message = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
		<title>' . $subject . '</title>
		<style type="text/css">
		</style>
	</head>
	<body style="width:100% !important; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; margin:0; padding:0; background-color:#eeeeee;">
		<table cellpadding="0" cellspacing="0" border="0" style="background-color:#eeeeee; width:100%; height:100%;">
			<tr>
				<td valign="top" style="text-align:center;padding-top:15px;">
					<table cellpadding="0" cellspacing="0" border="0" align="center">
						<tr>
							<td width="630" valign="top" style="background-color:#ffffff; text-align:left; padding:15px; font-size:15px; font-family:Arial, sans-serif;">
								' . $message . '
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</body>
</html>';

    return wp_mail($to, '[' . get_bloginfo('name') . '] ' . $subject, $message, $headers);
}

/**
 * display meeting list on home page (must be set to a static page)
 * used: by themes that want it, such as https://github.com/code4recovery/one-page-meeting-list
 * 
 * @param mixed $wp_query
 * @return void
 */
function tsml_front_page($wp_query)
{
    if (is_admin()) {
        return; //don't do this to inside pages
    }
    if ($wp_query->get('page_id') == get_option('page_on_front')) {
        $wp_query->set('post_type', 'tsml_meeting');
        $wp_query->set('page_id', '');
        $wp_query->is_page = 0;
        $wp_query->is_singular = 0;
        $wp_query->is_post_type_archive = 1;
        $wp_query->is_archive = 1;
    }
}

/**
 * request accurate address information from google
 * used: tsml_ajax_import(), tsml_ajax_geocode()
 * 
 * @param mixed $address
 * @return mixed
 */
function tsml_geocode($address)
{
    global $tsml_google_overrides;

    $address = stripslashes($address);

    // check overrides first before anything
    if (array_key_exists($address, $tsml_google_overrides)) {
        if (empty($tsml_google_overrides[$address]['approximate'])) {
            $tsml_google_overrides[$address]['approximate'] = 'no';
        }
        $tsml_google_overrides[$address]['status'] = 'override';
        return $tsml_google_overrides[$address];
    }

    // check cache
    $addresses = tsml_get_option_array('tsml_addresses');

    // if key exists && approximate is set for that address, return it
    if (array_key_exists($address, $addresses) && !empty($addresses[$address]['approximate'])) {
        $addresses[$address]['status'] = 'cache';
        return $addresses[$address];
    }

    $response = tsml_geocode_google($address);

    // Return if the status is error
    if ($response['status'] == 'error') {
        return $response;
    }

    // cache result
    $addresses[$address] = $response;
    $addresses[$response['formatted_address']] = $response;
    update_option('tsml_addresses', $addresses);

    return $response;
}

/**
 * call Google for geocoding of the address
 * 
 * @param mixed $address
 * @return mixed
 */
function tsml_geocode_google($address)
{
    global $tsml_curl_handle, $tsml_language, $tsml_google_overrides, $tsml_bounds, $tsml_google_geocoding_key;

    // Can't Geocode an empty address
    if (empty($address)) {
        return [
            'status' => 'error',
            'reason' => 'Addres string was empty',
        ];
    }

    // initialize curl handle if necessary
    if (!$tsml_curl_handle) {
        $tsml_curl_handle = curl_init();
        curl_setopt_array($tsml_curl_handle, [
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
    }

    // user can specify their own geocoding key in functions.php
    $key = !empty($tsml_google_geocoding_key) ? $tsml_google_geocoding_key : 'AIzaSyDm-pU-DlU-WsTkXJPGEVowY2hICRFLNeQ';

    // start list of options for geocoding request
    $options = [
        'key' => $key,
        'address' => $address,
        'language' => $tsml_language,
    ];

    // bias the viewport if we know the bounds
    if ($tsml_bounds) {
        $options['bounds'] = $tsml_bounds['south'] . ',' . $tsml_bounds['west'] . '|' . $tsml_bounds['north'] . ',' . $tsml_bounds['east'];
    }

    // send request to google
    curl_setopt($tsml_curl_handle, CURLOPT_URL, 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query($options));
    curl_setopt($tsml_curl_handle, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($tsml_curl_handle);

    // could not connect error
    if ($result === false) {
        $error = curl_error($tsml_curl_handle);
        tsml_log('geocode_connection_error', $error, $address);
        return [
            'status' => 'error',
            'reason' => 'Google could not validate the address <code>' . $address . '</code>. Response was <code>' . $error . '</code>',
        ];
    }

    // decode result
    $data = json_decode($result);

    // if over query limit, wait two seconds and retry, or then exit
    if ($data->status === 'OVER_QUERY_LIMIT') {
        sleep(2);
        $result = curl_exec($tsml_curl_handle);

        // could not connect error
        if ($result === false) {
            return [
                'status' => 'error',
                'reason' => 'Google could not validate the address <code>' . $address . '</code>. Response was <code>' . curl_error($tsml_curl_handle) . '</code>',
            ];
        }

        // decode result
        $data = json_decode($result);

        // if we're still over the limit, stop
        if ($data->status === 'OVER_QUERY_LIMIT') {
            tsml_log('geocode_error', 'OVER_QUERY_LIMIT', $address);
            return [
                'status' => 'error',
                'reason' => 'We are over the rate limit for the Google Geocoding API.'
            ];
        }
    }

    // if there are no results report it
    if ($data->status === 'ZERO_RESULTS') {
        tsml_log('geocode_error', 'ZERO_RESULTS', $address);
        return [
            'status' => 'error',
            'reason' => 'Google could not validate the address <code>' . $address . '</code>',
        ];
    }

    // if result is otherwise bad, stop
    if (($data->status !== 'OK') || empty($data->results[0]->formatted_address)) {
        tsml_log('geocode_error', $data->status, $address);
        return [
            'status' => 'error',
            'reason' => 'Google gave an unexpected response for address <code>' . $address . '</code>. Response was <pre>' . var_export($data, true) . '</pre>',
        ];
    }

    // check our overrides array again in case google is wrong
    if (array_key_exists($data->results[0]->formatted_address, $tsml_google_overrides)) {
        $response = $tsml_google_overrides[$data->results[0]->formatted_address];
        if (empty($response['approximate'])) {
            $response['approximate'] = 'no';
        }
    } else {
        tsml_log('geocode_success', $data->results[0]->formatted_address, $address);
        // start building response
        $response = [
            'formatted_address' => $data->results[0]->formatted_address,
            'latitude' => $data->results[0]->geometry->location->lat,
            'longitude' => $data->results[0]->geometry->location->lng,
            'approximate' => ($data->results[0]->geometry->location_type === 'APPROXIMATE') ? 'yes' : 'no',
            'city' => null,
            'status' => 'geocode',
        ];

        // get city, we might need it for the region, and we are going to cache it
        foreach ($data->results[0]->address_components as $component) {
            if (in_array('locality', $component->types)) {
                $response['city'] = $component->short_name;
            }
        }
    }

    return $response;
}

/**
 * return spelled-out meeting types
 * called from save.php (updates) and archive-meetings.php (display)
 * 
 * @param mixed $types
 * @return string | void
 */
function tsml_meeting_types($types)
{
    global $tsml_programs, $tsml_program;
    if (empty($tsml_programs[$tsml_program]['types'])) {
        return;
    }
    $return = [];
    foreach ($types as $type) {
        if (array_key_exists($type, $tsml_programs[$tsml_program]['types'])) {
            $return[] = $tsml_programs[$tsml_program]['types'][$type];
        }
    }
    sort($return);
    return implode(', ', $return);

}

/**
 * Render an anchor tag with a class
 * @deprecated this function is no longer used. it will be removed in the future
 * @param mixed $url
 * @param mixed $string
 * @param mixed $exclude
 * @param mixed $class
 * @return string
 */
function tsml_link($url, $string, $exclude = '', $class = false)
{
    $appends = $_GET;
    if (array_key_exists($exclude, $appends)) {
        unset($appends[$exclude]);
    }
    if (!empty($appends)) {
        $url .= strstr($url, '?') ? '&' : '?';
        $url .= http_build_query($appends, '', '&amp;');
    }
    $return = '<a href="' . $url . '"';
    if ($class) {
        $return .= ' class="' . $class . '"';
    }
    $return .= '>' . $string . '</a>';
    return $return;
}

/**
 * return string link with current query string appended
 * 
 * @param string $url
 * @param mixed $exclude
 * @return string
 */
function tsml_link_url($url, $exclude = '')
{
    $appends = $_GET;
    if (array_key_exists($exclude, $appends)) {
        unset($appends[$exclude]);
    }
    if (!empty($appends)) {
        $url .= strstr($url, '?') ? '&' : '?';
        $url .= http_build_query($appends, '', '&amp;');
    }
    return $url;
}

/**
 * add an entry to the activity log
 * used in tsml_ajax_info, tsml_geocode and anywhere else something could go wrong
 * 
 * @param mixed $type something short you can filter by, eg 'geocode_error'
 * @param mixed $info the bad result you got back
 * @param mixed $input any input that might have contributed to the result
 * @return void
 */
function tsml_log($type, $info = null, $input = null)
{
    // load
    $tsml_log = tsml_get_option_array('tsml_log');

    // default variables
    $entry = [
        'type' => $type,
        'timestamp' => current_time('mysql'),
    ];

    // optional variables
    if ($info) {
        $entry['info'] = $info;
    }
    if ($input) {
        $entry['input'] = $input;
    }

    // prepend to array
    array_unshift($tsml_log, $entry);

    // save
    update_option('tsml_log', $tsml_log);
}

/**
 * link to meetings page with parameters (added to link dropdown menus for SEO)
 * used: archive-meetings.php
 * 
 * @param mixed $parameters
 * @return string
 */
function tsml_meetings_url($parameters)
{
    $url = get_post_type_archive_link('tsml_meeting');
    $url .= (strpos($url, '?') === false) ? '?' : '&';
    $url .= http_build_query($parameters);
    return $url;
}

/**
 * convert line breaks in plain text to HTML paragraphs
 * used: save.php in lieu of nl2br()
 * 
 * @param mixed $string
 * @return string
 */
function tsml_paragraphs($string)
{
    $paragraphs = '';
    foreach (explode("\n", trim($string)) as $line) {
        if ($line = trim($line)) {
            $paragraphs .= '<p>' . $line . '</p>';
        }
    }
    return $paragraphs;
}

/**
 * boolean whether current program has types
 * used: meeting edit screen, meeting save
 * 
 * @return bool
 */
function tsml_program_has_types()
{
    global $tsml_programs, $tsml_program;
    return !empty($tsml_programs[$tsml_program]['types']);
}

/**
 * exit if user does not have permission to edit meetings
 * 
 * @return void
 */
function tsml_require_meetings_permission()
{
    if (!current_user_can(TSML_MEETINGS_PERMISSION)) {
        // translators: %s is the permission required
        wp_die(wp_kses(sprintf(__('You do not have sufficient permissions to access this page (<code>%s</code>).', '12-step-meeting-list'), TSML_MEETINGS_PERMISSION), TSML_ALLOWED_HTML));
    }
}

/**
 * exit if user does not have permission to edit settings
 * 
 * @return void
 */
function tsml_require_settings_permission()
{
    if (!current_user_can(TSML_SETTINGS_PERMISSION)) {
        // translators: %s is the permission required
        wp_die(wp_kses(sprintf(__('You do not have sufficient permissions to access this page (<code>%s</code>).', '12-step-meeting-list'), TSML_SETTINGS_PERMISSION), TSML_ALLOWED_HTML));
    }
}

/**
 * set an option with the currently-used types
 * used tsml_import() and save.php
 * 
 * @return void
 */
function tsml_update_types_in_use()
{
    global $tsml_types_in_use, $wpdb;

    // shortcut to getting all meta values without getting all posts first
    $types = $wpdb->get_col('SELECT
			m.meta_value
		FROM ' . $wpdb->postmeta . ' m
		JOIN ' . $wpdb->posts . ' p ON m.post_id = p.id
		WHERE p.post_type = "tsml_meeting" AND m.meta_key = "types" AND p.post_status = "publish"');

    // main array
    $all_types = [];

    // loop through results and append to main array
    foreach ($types as $type) {
        $type = unserialize($type);
        if (is_array($type)) {
            $all_types = array_merge($all_types, $type);
        }
    }

    // update global variable
    $tsml_types_in_use = array_unique($all_types);

    // set option value
    update_option('tsml_types_in_use', $tsml_types_in_use);
}

/**
 * sanitize a value
 * used: save.php
 * 
 * @param mixed $type
 * @param mixed $value
 * @return array|string|null
 */
function tsml_sanitize($type, $value)
{
    if ($type == 'url') {
        return esc_url_raw($value, ['http', 'https']);
    } elseif ($type == 'date') {
        return date('Y-m-d', strtotime($value));
    } elseif ($type == 'time') {
        return date('H:i', strtotime($value));
    } elseif ($type == 'phone') {
        return preg_replace('/[^0-9,+#]/', '', $value);
    }
    return sanitize_text_field($value);
}

/**
 * sanitize multi-line text
 * used tsml_import() and save.php
 * @param string $value
 * @return string
 */
function tsml_sanitize_text_area($value)
{
    return implode("\n", array_map('sanitize_text_field', explode("\n", trim($value))));
}

/**
 * does a string end with another string
 * used: save.php
 * 
 * @param mixed $string
 * @param mixed $end
 * @return bool
 */
function tsml_string_ends($string, $end)
{
    $length = strlen($end);
    if (!$length) {
        return true;
    }
    return (substr($string, -$length) === $end);
}

/**
 * tokenize string for the typeaheads
 * used: ajax functions
 * @param mixed $string
 * @return array
 */
function tsml_string_tokens($string)
{
    // shorten words that have quotes in them instead of splitting them
    $string = html_entity_decode($string);
    $string = str_replace("'", '', $string);
    $string = str_replace('’', '', $string);

    // remove everything that's not a letter or a number
    $string = preg_replace("/[^a-zA-Z 0-9]+/", ' ', $string);

    // return array
    return array_values(array_unique(array_filter(explode(' ', $string))));
}

/**
 * Implodes given array $types into lower-cased class names, prefixing each with $prefix
 *
 * @param string[] $types
 * @param string $prefix
 * @return string
 */
function tsml_to_css_classes($types, $prefix = 'type-')
{
    if (!$types) {
        return '';
    }

    $types = array_map('strtolower', $types);

    return $prefix . implode(' ' . $prefix, $types);
}

/**
 * Sanitizes a string for sorting purposes.  Similar to sanitize_title(), but uses Unicode regular expressions to support multiple languages.
 *
 * @param string $string
 * @return string
 */
function tsml_sanitize_data_sort($string)
{
    global $tsml_sanitize_data_sort_regexps;

    // Populate regex array only once
    if (!isset($tsml_sanitize_data_sort_regexps)) {
        $tsml_sanitize_data_sort_regexps = [
            ['/<[^>]+>/', ''], // Strip HTML Tags
            ['/[&\'"<>]+/', ''], // Strip unsupported chars
            ['/[\\/\\.\\p{Zs}\\p{Pd}]/u', '-'], // Change forward slashes, periods, spaces and dashes to dash (Unicode)
            ['/[^\\p{L}\\p{N}\\p{M}\-]+/u', ''], // Remove any Unicode char that is not an alpha-numeric, mark character or dash
            ['/\-+/', '-'], // Convert runs of dashes into a single dash
            ['/^\-|\-$/', ''] // Strip trailing/leading dash
        ];
    }

    // Convert all html entities to chars so encodings are uniform
    $t = html_entity_decode($string);

    // Do regex-based sanitization
    foreach ($tsml_sanitize_data_sort_regexps as $a) {
        $t = preg_replace($a[0], $a[1], $t);
    }

    // Unicode-aware lowercase of characters in string
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($t);
    } else {
        return strtolower($t);
    }
}

/**
 * called by register_activation_hook in admin_import
 * @return void
 */
function tsml_activate_data_source_scan()
{
    // Use wp_next_scheduled to check if the event is already scheduled
    $timestamp = wp_next_scheduled('tsml_scan_data_source');

    // If $timestamp is false schedule scan since it hasn't been done previously
    if ($timestamp == false) {
        // Schedule the event for right now, then to reoccur daily using the hook 'tsml_scan_data_source'
        wp_schedule_event(time(), 'daily', 'tsml_scan_data_source');
    }
}

// function:    scans passed data source url looking for recent updates
// used:		fired by cron job tsml_scan_data_source
// todo - think about what the right place for this is
add_action('tsml_scan_data_source', function ($data_source_url) {

    $data_source_name = null;
    $data_source_parent_region_id = -1;
    $data_source_count_meetings = 0;

    $tsml_notification_addresses = tsml_get_option_array('tsml_notification_addresses');
    $tsml_data_sources = tsml_get_option_array('tsml_data_sources');
    $data_source_count_meetings = (int) $tsml_data_sources[$data_source_url]['count_meetings'];

    if (empty($tsml_notification_addresses) || $data_source_count_meetings !== 0 || !array_key_exists($data_source_url, $tsml_data_sources)) {
        return;
    }

    $data_source_name = $tsml_data_sources[$data_source_url]['name'];
    $data_source_parent_region_id = $tsml_data_sources[$data_source_url]['parent_region_id'];

    // try fetching
    $response = wp_safe_remote_get($data_source_url, ['timeout' => 30, 'sslverify' => false]);

    if (empty($response['body']) || !($body = json_decode($response['body'], true))) {
        return;
    }

    // allow reformatting as necessary
    $meetings = tsml_import_sanitize_meetings($body, $data_source_url, $data_source_parent_region_id);

    // check import feed for changes and return array summing up changes detected
    list($import_meetings, $delete_meeting_ids, $change_log) = tsml_import_get_changed_meetings($meetings, $data_source_url);

    // send email notifying admins that this data source needs updating
    if (is_array($change_log) && count($change_log)) {
        // translators: %s is the name of the data source
        $message = '<p>' . sprintf(__('Please sign in to your website and refresh the <strong>%s</strong> feed on the Import & Export page.', '12-step-meeting-list'), $data_source_name) . '</p><br>';
        $message .= tsml_import_build_change_report($change_log);
        $import_page_url = admin_url('/edit.php?post_type=tsml_meeting&page=import');
        $button_text = __('Go to Import & Export page', '12-step-meeting-list');
        $message .= '<a href="' . $import_page_url . '" style="margin: 0 auto;background-color: #4CAF50;border: none;border-radius: 3px; color: white;padding: 25px 32px;text-align: center;text-decoration: none;display: block;font-size: 18px;">' . $button_text . '</a>';
        $subject = __('Data Source Changes Detected', '12-step-meeting-list');
        tsml_email($tsml_notification_addresses, $subject, $message);
    }
}, 10, 1);

/**
 * Creates and configures cron job to run a scheduled data source scan
 * 
 * @param mixed $data_source_url
 * @param mixed $data_source_name
 * @return void
 */
function tsml_schedule_import_scan($data_source_url, $data_source_name)
{

    $timestamp = tsml_strtotime('tomorrow midnight'); // Use tsml_strtotime to incorporate local site timezone with UTC.

    // Get the timestamp for the next event when found.
    $ts = wp_next_scheduled("tsml_scan_data_source", array($data_source_url));
    if ($ts) {
        $mydisplaytime = tsml_date_localised(get_option('date_format') . ' ' . get_option('time_format'), $ts); // Use tsml_date_localised to convert to specified format with local site timezone included.
        tsml_alert("The $data_source_name data source's next scheduled run is $mydisplaytime. You can adjust the recurrences and the times that the job ('<strong>tsml_scan_data_source</strong>') runs with the WP_Crontrol plugin.");
    } else {
        // When adding a data source we schedule its daily cron job
        register_activation_hook(__FILE__, 'tsml_activate_data_source_scan');

        // Schedule the refresh
        if (wp_schedule_event($timestamp, "daily", "tsml_scan_data_source", array($data_source_url)) === false) {
            tsml_alert("$data_source_name data source scan scheduling failed!");
        } else {
            $mydisplaytime = tsml_date_localised(get_option('date_format') . ' ' . get_option('time_format'), $timestamp); // Use tsml_date_localised to convert to specified format with local site timezone included.
            tsml_alert("The $data_source_name data source's next scheduled run is $mydisplaytime. You can adjust the recurrences and the times that the job ('<strong>tsml_scan_data_source</strong>') runs with the WP_Crontrol plugin.");
        }
    }
}

/**
 * incorporates wp timezone into php's StrToTime() function
 * used: here, admin-import.php
 * 
 * @param mixed $str
 * @return string
 */
function tsml_strtotime($str)
{
    // This function behaves a bit like PHP's StrToTime() function, but taking into account the Wordpress site's timezone
    // CAUTION: It will throw an exception when it receives invalid input - please catch it accordingly
    // From https://mediarealm.com.au/

    $tz_string = get_option('timezone_string');
    $tz_offset = get_option('gmt_offset', 0);

    if (!empty($tz_string)) {
        // If site timezone option string exists, use it
        $timezone = $tz_string;
    } elseif ($tz_offset == 0) {
        // get UTC offset, if it isn’t set then return UTC
        $timezone = 'UTC';
    } else {
        $timezone = $tz_offset;

        if (substr($tz_offset, 0, 1) != "-" && substr($tz_offset, 0, 1) != "+" && substr($tz_offset, 0, 1) != "U") {
            $timezone = "+" . $tz_offset;
        }
    }

    $datetime = new DateTime($str, new DateTimeZone($timezone));
    return $datetime->format('U');
}

/**
 * incorporates wp timezone into php's date() function
 * used: here, admin-import.php
 * 
 * @param mixed $format
 * @param mixed $timestamp
 * @return string
 */
function tsml_date_localised($format, $timestamp = null)
{
    // This function behaves a bit like PHP's Date() function, but taking into account the Wordpress site's timezone
    // CAUTION: It will throw an exception when it receives invalid input - please catch it accordingly
    // From https://mediarealm.com.au/

    $tz_string = get_option('timezone_string');
    $tz_offset = get_option('gmt_offset', 0);

    if (!empty($tz_string)) {
        // If site timezone option string exists, use it
        $timezone = $tz_string;
    } elseif ($tz_offset == 0) {
        // get UTC offset, if it isn’t set then return UTC
        $timezone = 'UTC';
    } else {
        $timezone = $tz_offset;

        if (substr($tz_offset, 0, 1) != "-" && substr($tz_offset, 0, 1) != "+" && substr($tz_offset, 0, 1) != "U") {
            $timezone = "+" . $tz_offset;
        }
    }

    if ($timestamp === null) {
        $timestamp = time();
    }

    $datetime = new DateTime();
    $datetime->setTimestamp($timestamp);
    $datetime->setTimezone(new DateTimeZone($timezone));
    return $datetime->format($format);
}

/**
 * Compares content of an import meeting against local meeting
 * @param array   $local_meeting   Local meeting
 * @param array   $import_meeting  Import meeting
 * @param boolean $translate_field [default true] Translate system fields to field labels
 * @return array|null
 */
function tsml_compare_imported_meeting($local_meeting, $import_meeting, $translate_fields = true)
{
    global $tsml_export_columns, $tsml_source_fields_map, $tsml_entity_fields, $tsml_array_fields;

    $local_meeting = (array) $local_meeting;
    $import_meeting = (array) $import_meeting;

    // update local meeting with stored source field values
    foreach ($tsml_source_fields_map as $source_field => $field) {
        if (isset($local_meeting[$source_field])) {
            $local_meeting[$field] = $local_meeting[$source_field];
        }
    }
    $compare_fields = array_merge(array_keys($tsml_export_columns), $tsml_entity_fields);
    $compare_fields = array_diff(
        $compare_fields,
        // these fields are unique internal fields, not content fields for comparison
        explode(',', 'id,slug,author,data_source,data_source_name,updated')
    );

    // normalize meetings for comparison
    $normalized_meetings = array();
    foreach (array($local_meeting, $import_meeting) as $index => $meeting) {
        $normalized_meetings[$index] = array();
        foreach ($compare_fields as $field) {
            $value = isset($meeting[$field]) ? $meeting[$field] : '';
            if (in_array($field, $tsml_array_fields, true)) {
                $value = empty($value) ? [] : ((array) $value);
            }
            // import meeting: post_title <=> name
            if (!$value && 'name' === $field) {
                $value = isset($meeting['post_title']) ? $meeting['post_title'] : '';
            }
            if (is_object($value) || is_array($value)) {
                $value = json_encode($value);
            } elseif (null !== $value) {
                $value = trim(htmlspecialchars_decode(html_entity_decode(strval($value)), ENT_QUOTES));
            } else {
                $value = '';
            }
            $normalized_meetings[$index][$field] = $value;
        }
    }

    // if 'group' is blank on both, we don't compare group_notes or district
    if (!$normalized_meetings[0]['group'] && !$normalized_meetings[1]['group']) {
        $compare_fields = array_diff($compare_fields, ['district', 'group_notes']);
    }

    $diff_fields = array();
    foreach ($compare_fields as $field) {
        if ($normalized_meetings[0][$field] !== $normalized_meetings[1][$field]) {
            $diff_fields[] = ($translate_fields && isset($tsml_export_columns[$field])) ? $tsml_export_columns[$field] : $field;
        }
    }
    return count($diff_fields) ? $diff_fields : null;
}

function tsml_header()
{
    if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
        include(TSML_PATH . '/templates/header.php');
    } else {
        get_header();
    }
}

function tsml_footer()
{
    if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
        include(TSML_PATH . '/templates/footer.php');
    } else {
        get_footer();
    }
}

/**
 * Redirect legacy query parameters to TSML UI's url structure
 */
function tsml_redirect_legacy_query_params()
{

    global $tsml_program, $tsml_programs;

    $replacements = [];

    if (isset($_GET['tsml-attendance_option'])) {
        if ($_GET['tsml-attendance_option'] === 'active') {
            $replacements['type'][] = 'active';
        } elseif ($_GET['tsml-attendance_option'] === 'in_person') {
            $replacements['type'][] = 'in-person';
        } elseif ($_GET['tsml-attendance_option'] === 'online') {
            $replacements['type'][] = 'online';
        }
    }

    if (isset($_GET['tsml-day'])) {
        $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        if (array_key_exists($_GET['tsml-day'], $days)) {
            $replacements['weekday'] = $days[$_GET['tsml-day']];
        }
    }

    if (isset($_GET['tsml-mode'])) {
        $replacements['mode'] = $_GET['tsml-mode'];

        if (isset($_GET['tsml-distance'])) {
            $replacements['distance'] = $_GET['tsml-distance'];
        } else {
            $replacements['distance'] = 10;
        }
    }

    if (isset($_GET['tsml-query'])) {
        $replacements['search'] = $_GET['tsml-query'];
    }

    if (isset($_GET['tsml-region'])) {
        $replacements['region'] = $_GET['tsml-region'];
    }

    if (isset($_GET['tsml-time'])) {
        $replacements['time'] = $_GET['tsml-time'];
    }

    if (isset($_GET['tsml-type'])) {
        $types = explode(',', $_GET['tsml-type']);
        foreach ($types as $type) {
            if (array_key_exists($type, $tsml_programs[$tsml_program]['types']))
                $replacements['type'][] = sanitize_title($tsml_programs[$tsml_program]['types'][$type]);
        }
    }

    if (isset($replacements['type'])) {
        $replacements['type'] = implode('/', $replacements['type']);
    }

    if (count($replacements) > 0) {
        $url = get_post_type_archive_link('tsml_meeting');

        foreach ($replacements as $key => $value) {
            $url = add_query_arg($key, $value, $url);
        }

        wp_redirect($url);
    }
}
