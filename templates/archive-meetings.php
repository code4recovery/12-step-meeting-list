<?php

//get assets for page
tsml_assets();

//define search dropdown options
$modes = [
    'search' => ['title' => __('Search', '12-step-meeting-list'), 'icon' => 'glyphicon glyphicon-search'],
    'location' => ['title' => __('Near Location', '12-step-meeting-list'), 'icon' => 'glyphicon glyphicon-map-marker'],
];
//proximity only enabled over SSL
if (is_ssl()) {
    $modes['me'] = ['title' => __('Near Me', '12-step-meeting-list'), 'icon' => 'glyphicon glyphicon-user'];
}

//define distance dropdown
$distances = [];
foreach ([1, 2, 5, 10, 25, 50, 100] as $distance) {
    if ($tsml_distance_units == 'mi') {
        $distances[$distance] = sprintf(_n('Within %d Mile', 'Within %d Miles', $distance, '12-step-meeting-list'), $distance);
    } else {
        $distances[$distance] = sprintf(_n('Within %d Kilometer', 'Within %d Kilometers', $distance, '12-step-meeting-list'), $distance);
    }
}

//define times dropdown
$times = [
    'morning' => __('Morning', '12-step-meeting-list'),
    'midday' => __('Midday', '12-step-meeting-list'),
    'evening' => __('Evening', '12-step-meeting-list'),
    'night' => __('Night', '12-step-meeting-list'),
];

//legacy query string stuff, we don't want to break everyone's links (just yet)
if (isset($_GET['d'])) {
    $_GET['tsml-day'] = $_GET['d'];
}

if (isset($_GET['r'])) {
    $_GET['tsml-region'] = $_GET['r'];
}

if (isset($_GET['t'])) {
    $_GET['tsml-type'] = $_GET['t'];
}

if (isset($_GET['i'])) {
    $_GET['tsml-time'] = $_GET['i'];
}

if (isset($_GET['v'])) {
    $_GET['tsml-view'] = $_GET['v'];
}

if (isset($_GET['sq'])) {
    $_GET['tsml-query'] = $_GET['sq'];
}

extract($tsml_defaults);

$region = $district = null;

//parse query string
if (isset($_GET['tsml-query'])) {
    $query = sanitize_text_field(stripslashes($_GET['tsml-query']));
}

if (isset($_GET['tsml-region'])) {
    if (term_exists(sanitize_text_field($_GET['tsml-region']), 'tsml_region')) {
        $region = $_GET['tsml-region'];
    } elseif (term_exists(intval($_GET['tsml-region']), 'tsml_region')) {
        //legacy integer region, redirect
        $term = get_term(intval($_GET['tsml-region']), 'tsml_region');
        wp_redirect(add_query_arg('tsml-region', $term->slug));
    } else {
        wp_redirect(add_query_arg('tsml-region', null));
    }
} elseif (isset($_GET['tsml-district'])) {
    if (term_exists(sanitize_text_field($_GET['tsml-district']), 'tsml_district')) {
        $district = $_GET['tsml-district'];
    } elseif (term_exists(intval($_GET['tsml-district']), 'tsml_district')) {
        //legacy integer district, redirect
        $term = get_term(intval($_GET['tsml-district']), 'tsml_district');
        wp_redirect(add_query_arg('tsml-district', $term->slug));
    } else {
        wp_redirect(add_query_arg('tsml-district', null));
    }
}

$types = [];
if (!empty($_GET['tsml-type'])) {
    $type_queries = explode(',', $_GET['tsml-type']);
    foreach ($type_queries as $type_query) {
        if (array_key_exists($type_query, $tsml_programs[$tsml_program]['types'])) {
            $types[] = $type_query;
        }
    }
}

$attendance_options = [];
if (!empty($_GET['tsml-attendance_option'])) {
    $attendance_option_queries = explode(',', $_GET['tsml-attendance_option']);
    // $tsml_meeting_attendance_options['active'] = null;
    foreach ($attendance_option_queries as $attendance_option_query) {
        if ((array_key_exists($attendance_option_query, $tsml_meeting_attendance_options)) || ($attendance_option_query === 'active')) {
            $attendance_options[] = $attendance_option_query;
        }
    }
}

if (isset($_GET['tsml-time']) && (($_GET['tsml-time'] == 'upcoming') || array_key_exists($_GET['tsml-time'], $times))) {
    $time = $_GET['tsml-time'];
}

if (isset($_GET['tsml-distance']) && intval($_GET['tsml-distance'])) {
    $distance = $_GET['tsml-distance'];
}

if (isset($_GET['tsml-mode']) && array_key_exists($_GET['tsml-mode'], $modes)) {
    $mode = $_GET['tsml-mode'];
}

if ($tsml_mapbox_key || $tsml_google_maps_key) {
    $maps_enabled = true;
    if (isset($_GET['tsml-view']) && in_array($_GET['tsml-view'], ['list', 'map'])) {
        $view = $_GET['tsml-view'];
    }
} else {
    $maps_enabled = false;
    $view = 'list';
}

//day default
$today = true;
if (isset($_GET['tsml-day'])) {
    $today = false;
    $day = ($_GET['tsml-day'] == 'any') ? null : intval($_GET['tsml-day']);
}

//time can only be upcoming if it's today
if (($time == 'upcoming') && ($day != intval(current_time('w')))) {
    $time = null;
}

//labels
$day_default = __('Any Day', '12-step-meeting-list');
$day_label = ($day === null) ? $day_default : $tsml_days[$day];
$time_default = __('Any Time', '12-step-meeting-list');
if ($time == 'upcoming') {
    $time_label = __('Upcoming', '12-step-meeting-list');
} else {
    $time_label = $time ? $times[$time] : $time_default;
}
$region_default = $region_label = __('Everywhere', '12-step-meeting-list');
if ($region) {
    $term = get_term_by('slug', $region, 'tsml_region');
    $region_label = $term->name;
} elseif ($district) {
    $term = get_term_by('slug', $district, 'tsml_district');
    $region_label = $term->name;
}
$type_default = __('Any Type', '12-step-meeting-list');
if (!count($types) && (!count($attendance_options))) {
    $type_label = $type_default;
} else {
    $type_label = [];
    foreach ($attendance_options as $attendance_option) {
        if ($attendance_option === 'active') {
            $type_label[] = "Active";
        }
        if (array_key_exists($attendance_option, $tsml_meeting_attendance_options)) {
            $type_label[] = $tsml_meeting_attendance_options[$attendance_option];
        }
    }
    foreach ($types as $type) {
        if (array_key_exists($type, $tsml_programs[$tsml_program]['types'])) {
            $type_label[] = $tsml_programs[$tsml_program]['types'][$type];
        }
    }
    $type_label = implode(' + ', $type_label);
}
$mode_label = array_key_exists($mode, $modes) ? $modes[$mode]['title'] : $modes[0]['title'];
$distance_label = $distances[$distance];

//create page title (todo redo with sprintf)
$tsml_page_title = [];
if ($day !== null) {
    $tsml_page_title[] = $today ? __('Today\'s', '12-step-meeting-list') : $tsml_days[$day];
}
if ($time) {
    $tsml_page_title[] = $time_label;
}

if (count($types)) {
    $tsml_page_title[] = $type_label;
}

$tsml_page_title[] = empty($tsml_programs[$tsml_program]['abbr']) ? $tsml_programs[$tsml_program]['name'] : $tsml_programs[$tsml_program]['abbr'];
$tsml_page_title[] = __('Meetings', '12-step-meeting-list');
if ($region) {
    $tsml_page_title[] = __('in', '12-step-meeting-list') . ' ' . $region_label;
}

$tsml_page_title = implode(' ', $tsml_page_title);

//set page title for SEO (only applies to this page)
add_filter('wp_title', function ($title, $separator = null) {
    global $tsml_page_title;
    if (empty($separator)) {
        return $tsml_page_title;
    }

    $title_parts = array_map('trim', explode($separator, $title));
    for ($i = 0; $i < count($title_parts); $i++) {
        if (strcmp($title_parts[$i], __('Meetings', '12-step-meeting-list')) == 0) {
            $title_parts[$i] = $tsml_page_title;
        }
    }
    return implode(' ' . $separator . ' ', $title_parts);
}, 10, 2);

//need these later
$meetings = $locations = [];
$message = '';

//run query
if ($mode == 'search') {
    $type = implode(',', $types);
    $attendance_option = implode(',', $attendance_options);
    $meetings = tsml_get_meetings(compact('mode', 'day', 'time', 'region', 'district', 'type', 'query', 'attendance_option'));
    if (!count($meetings)) {
        $message = $tsml_strings['no_meetings'];
    }
} elseif ($mode == 'location') {
    $message = empty($_GET['query']) ? $tsml_strings['loc_empty'] : $tsml_strings['loc_thinking'];
} elseif ($mode == 'me') {
    $message = $tsml_strings['geo_thinking'];
}

class Walker_Regions_Dropdown extends Walker_Category
{
    public function start_el(&$output, $category, $depth = 0, $args = [], $id = 0)
    {
        $classes = ['region', 'notranslate'];
        if ($args['value'] == esc_attr($category->slug)) {
            $classes[] = 'active';
        }

        $classes = count($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
        $output .= '<li' . $classes . '><a href="' . tsml_meetings_url(['tsml-region' => $category->slug]) . '" data-id="' . $category->slug . '">' . $category->name . '</a>';
        if ($args['has_children']) {
            $output .= '<div class="expand"></div>';
        }
    }
    public function end_el(&$output, $item, $depth = 0, $args = [])
    {
        $output .= '</li>';
    }
}

$regions_dropdown = wp_list_categories([
    'taxonomy' => 'tsml_region',
    'hierarchical' => true,
    'orderby' => 'name',
    'title_li' => null,
    'hide_empty' => true,
    'walker' => new Walker_Regions_Dropdown,
    'value' => $region,
    'show_option_none' => null,
    'echo' => false,
]);

class Walker_Districts_Dropdown extends Walker_Category
{
    public function start_el(&$output, $category, $depth = 0, $args = [], $id = 0)
    {
        $classes = ['district', 'notranslate'];
        if ($args['value'] == esc_attr($category->slug)) {
            $classes[] = 'active';
        }

        $classes = count($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
        $output .= '<li' . $classes . '><a href="' . tsml_meetings_url(['tsml-district' => $category->slug]) . '" data-id="' . $category->slug . '">' . $category->name . '</a>';
        if ($args['has_children']) {
            $output .= '<div class="expand"></div>';
        }
    }
    public function end_el(&$output, $item, $depth = 0, $args = [])
    {
        $output .= '</li>';
    }
}

$districts_dropdown = wp_list_categories([
    'taxonomy' => 'tsml_district',
    'hierarchical' => true,
    'orderby' => 'name',
    'title_li' => null,
    'hide_empty' => true,
    'walker' => new Walker_Districts_Dropdown,
    'value' => $district,
    'show_option_none' => null,
    'echo' => false,
]);

//adding custom body classes
add_filter('body_class', function ($classes) {
    $classes[] = 'tsml tsml-meetings';
    return $classes;
});

//do this after everything is loaded
tsml_header();
?>
<div id="tsml">

    <div id="meetings" data-view="<?php echo $view ?>" data-mode="<?php echo $mode ?>"
        tax-mode="<?php echo $district ? 'district' : 'region' ?>"
        class="container<?php if (!count($meetings)) { ?> empty<?php } ?>" role="main">

        <div class="row title">
            <div class="col-xs-12">
                <div class="page-header">
                    <h1>
                        <?php echo $tsml_page_title ?>
                    </h1>
                </div>
            </div>
        </div>

        <?php if (is_active_sidebar('tsml_meetings_top')) { ?>
            <div class="widgets meetings-widgets meetings-widgets-top" role="complementary">
                <?php dynamic_sidebar('tsml_meetings_top') ?>
            </div>
        <?php } ?>

        <div class="row controls hidden-print">
            <div class="col-sm-6 col-md-2 control-search">
                <form id="search" role="search" action=".">
                    <div class="input-group">
                        <input type="search" name="query" class="form-control" value="<?php echo $query ?>"
                            placeholder="<?php echo $mode_label ?>" aria-label="Search" <?php echo ($mode == 'me') ? 'disabled' : '' ?>>
                        <div class="input-group-btn" id="mode">
                            <button class="btn btn-default" data-toggle="tsml-dropdown" type="button">
                                <i class="<?php echo $modes[$mode]['icon'] ?>"></i>
                                <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-right">
                                <?php foreach ($modes as $key => $value) { ?>
                                    <li class="<?php echo $key;
                                    if ($mode == $key) {
                                        echo ' active';
                                    }
                                    ?>"><a data-id="<?php echo $key ?>">
                                            <?php echo $value['title'] ?>
                                        </a></li>
                                <?php } ?>
                            </ul>
                        </div>
                    </div>
                    <input type="submit">
                </form>
            </div>
            <div class="col-sm-6 col-md-2 col-md-push-8 control-view">
                <?php if ($maps_enabled) { ?>
                    <div class="btn-group btn-group-justified" id="action">
                        <a class="btn btn-default toggle-view<?php if ($view == 'list') { ?> active<?php } ?>"
                            href="<?php echo tsml_meetings_url(['tsml-view' => 'list']) ?>" data-id="list" role="button">
                            <?php _e('List', '12-step-meeting-list') ?>
                        </a>
                        <a class="btn btn-default toggle-view<?php if ($view == 'map') { ?> active<?php } ?>"
                            href="<?php echo tsml_meetings_url(['tsml-view' => 'map']) ?>" data-id="map" role="button">
                            <?php _e('Map', '12-step-meeting-list') ?>
                        </a>
                    </div>
                <?php } ?>
            </div>
            <div class="col-sm-6 col-md-2 col-md-pull-2 control-region">
                <?php if ($regions_dropdown || $districts_dropdown) { ?>
                    <div class="dropdown" id="region">
                        <a class="btn btn-default btn-block" data-toggle="tsml-dropdown" role="button" aria-haspopup="true"
                            aria-expanded="false">
                            <span class="selected">
                                <?php echo $region_label ?>
                            </span>
                            <span class="caret"></span>
                        </a>
                        <ul class="dropdown-menu" role="menu">
                            <li <?php if (empty($region) && empty($district)) {
                                echo ' class="active"';
                            } ?>>
                                <a href="#">
                                    <?php echo $region_default ?>
                                </a>
                            </li>
                            <li class="divider"></li>
                            <?php if ($regions_dropdown && $districts_dropdown) { ?>
                                <li class="region"><a class="switch">
                                        <?php _e('Switch to Districts', '12-step-meeting-list') ?>
                                    </a></li>
                                <li class="district"><a class="switch">
                                        <?php _e('Switch to Regions', '12-step-meeting-list') ?>
                                    </a></li>
                                <li class="divider"></li>
                            <?php } ?>
                            <?php echo $regions_dropdown ?>
                            <?php echo $districts_dropdown ?>
                        </ul>
                    </div>
                <?php } ?>
                <div class="dropdown" id="distance">
                    <a class="btn btn-default btn-block" data-toggle="tsml-dropdown" role="button" aria-haspopup="true"
                        aria-expanded="false">
                        <span class="selected">
                            <?php echo $distance_label ?>
                        </span>
                        <span class="caret"></span>
                    </a>
                    <ul class="dropdown-menu" role="menu">
                        <?php
                        foreach ($distances as $key => $value) {
                            echo '<li' . ($key == $distance ? ' class="active"' : '') . '><a href="' . tsml_meetings_url(['tsml-distance' => $key]) . '" data-id="' . $key . '">' . $value . '</a></li>';
                        } ?>
                    </ul>
                </div>
            </div>
            <div class="col-sm-6 col-md-2 col-md-pull-2 control-day">
                <div class="dropdown" id="day">
                    <a class="btn btn-default btn-block" data-toggle="tsml-dropdown" role="button" aria-haspopup="true"
                        aria-expanded="false">
                        <span class="selected">
                            <?php echo $day_label ?>
                        </span>
                        <span class="caret"></span>
                    </a>
                    <ul class="dropdown-menu" role="menu">
                        <li <?php if ($day === null) {
                            echo ' class="active"';
                        }
                        ?>>
                            <a href="#">
                                <?php echo $day_default; ?>
                            </a>
                        </li>
                        <li class="divider"></li>
                        <?php foreach ($tsml_days as $key => $value) { ?>
                            <li <?php if (intval($key) === $day) {
                                echo ' class="active"';
                            }
                            ?>>
                                <a href="<?php echo tsml_meetings_url(['tsml-day' => $key]) ?>"
                                    data-id="<?php echo $key ?>">
                                    <?php echo $value ?>
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
            <div class="col-sm-6 col-md-2 col-md-pull-2 control-time">
                <div class="dropdown" id="time">
                    <a class="btn btn-default btn-block" data-toggle="tsml-dropdown" role="button" aria-haspopup="true"
                        aria-expanded="false">
                        <span class="selected">
                            <?php echo $time_label ?>
                        </span>
                        <span class="caret"></span>
                    </a>
                    <ul class="dropdown-menu" role="menu">
                        <li <?php if (empty($time)) {
                            echo ' class="active"';
                        }
                        ?>>
                            <a href="#">
                                <?php echo $time_default ?>
                            </a>
                        </li>
                        <li class="divider upcoming"></li>
                        <li class="upcoming<?php if ($time == 'upcoming') {
                            echo ' active"';
                        }
                        ?>">
                            <a href="<?php echo tsml_meetings_url(['tsml-time' => 'upcoming']) ?>" data-id="upcoming">
                                <?php esc_html_e('Upcoming', '12-step-meeting-list') ?>
                            </a>
                        </li>
                        <li class="divider"></li>
                        <?php foreach ($times as $key => $value) { ?>
                            <li <?php if ($key === $time) {
                                echo ' class="active"';
                            }
                            ?>>
                                <a href="<?php echo tsml_meetings_url(['tsml-time' => $key]) ?>"
                                    data-id="<?php echo $key ?>">
                                    <?php echo $value ?>
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
            <div class="col-sm-6 col-md-2 col-md-pull-2 control-type">
                <?php if (count($tsml_types_in_use) && !empty($tsml_programs[$tsml_program]['types'])) { ?>
                    <div class="dropdown" id="type">
                        <a class="btn btn-default btn-block" data-toggle="tsml-dropdown" role="button" aria-haspopup="true"
                            aria-expanded="false">
                            <span class="selected">
                                <?php echo $type_label ?>
                            </span>
                            <span class="caret"></span>
                        </a>
                        <ul class="dropdown-menu" role="menu">
                            <li <?php if (!count($types) && (!count($attendance_options))) {
                                echo ' class="active"';
                            }
                            ?>>
                                <a href="#">
                                    <?php echo $type_default ?>
                                </a>
                            </li>
                            <li class="divider"></li>
                            <li <?php if (in_array('active', $attendance_options)) echo ' class="active"'; ?>>
                                <a href="<?php echo tsml_meetings_url(['tsml-attendance_option' => 'active']) ?>"
                                    data-id="active">Active</a>
                            </li>
                            <?php
                            global $tsml_meeting_attendance_options;
                            foreach ($tsml_meeting_attendance_options as $key => $value) {
                                if ($key == 'inactive' || $key == 'hybrid') continue; ?>
                                <li <?php
                                if (in_array($key, $attendance_options)) {
                                    echo ' class="active"';
                                }
                                ;
                                ?>>
                                    <a href="<?php echo tsml_meetings_url(['tsml-attendance_option' => $key]) ?>"
                                        data-id="<?php echo $key ?>">
                                        <?php echo $value ?>
                                    </a>
                                </li>
                            <?php }
                            ?>
                            <li class="divider"></li>
                            <?php
                            $types_to_list = array_intersect_key($tsml_programs[$tsml_program]['types'], array_flip($tsml_types_in_use));
                            foreach ($types_to_list as $key => $thistype) {
                                if ($key == 'ONL' || $key == 'TC') continue; //hide "Online Meeting" since it's not manually settable, neither is location Temporarily Closed
                                ?>
                                <li <?php if (in_array($key, $types)) echo ' class="active"' ?>>
                                        <a href="<?php echo tsml_meetings_url(['tsml-type' => $key]) ?>"
                                        data-id="<?php echo $key ?>">
                                        <?php echo $thistype ?>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="row results">
            <div class="col-xs-12">
                <div id="alert" class="alert alert-warning<?php if (empty($message)) { ?> hidden<?php } ?>">
                    <?php echo $message ?>
                </div>

                <div id="map"></div>

                <div id="table-wrapper">
                    <table class="table table-striped">
                        <thead class="hidden-print">
                            <tr>
                                <?php foreach ($tsml_columns as $key => $column) {
                                    echo '<th class="' . $key . '"' . ($tsml_sort_by == $key ? ' data-sort="asc"' : '') . '>' . __($column, '12-step-meeting-list') . '</th>';
                                } ?>
                            </tr>
                        </thead>
                        <tbody id="meetings_tbody">
                            <?php
                            foreach ($meetings as $meeting) {
                                $meeting['name'] = !empty($meeting['name']) ? htmlentities($meeting['name'], ENT_QUOTES) : '';
                                $meeting['location'] = !empty($meeting['location']) ? htmlentities(@$meeting['location'], ENT_QUOTES) : '';
                                $meeting['formatted_address'] = !empty($meeting['formatted_address']) ? htmlentities(@$meeting['formatted_address'], ENT_QUOTES) : '';
                                if (!empty($meeting['region'])) {
                                    $meeting['region'] = (!empty($meeting['sub_region'])) ? htmlentities($meeting['sub_region'], ENT_QUOTES) : htmlentities($meeting['region'], ENT_QUOTES);
                                } else {
                                    $meeting['region'] = '';
                                }
                                if (!empty($meeting['district'])) {
                                    $meeting['district'] = (!empty($meeting['sub_district'])) ? htmlentities($meeting['sub_district'], ENT_QUOTES) : htmlentities($meeting['district'], ENT_QUOTES);
                                } else {
                                    $meeting['district'] = '';
                                }
                                if (!isset($meeting['types'])) {
                                    $meeting['types'] = [];
                                }

                                //$meeting['link'] = tsml_link($meeting['url'], tsml_format_name($meeting['name'], $meeting['types']), 'post_type');
                            
                                if (!isset($locations[$meeting['location_id']])) {
                                    $locations[$meeting['location_id']] = [
                                        'name' => $meeting['location'],
                                        'latitude' => $meeting['latitude'] - 0,
                                        'longitude' => $meeting['longitude'] - 0,
                                        'url' => $meeting['location_url'], //can't use link here, unfortunately
                                        'formatted_address' => $meeting['formatted_address'],
                                        'meetings' => [],
                                    ];
                                }

                                $locations[$meeting['location_id']]['meetings'][] = [
                                    'time' => @$meeting['time_formatted'],
                                    'day' => @$meeting['day'],
                                    'name' => $meeting['name'],
                                    'url' => $meeting['url'], //can't use link here, unfortunately
                                    'types' => $meeting['types'],
                                ];

                                $sort_time = @$meeting['day'] . '-' . (@$meeting['time'] == '00:00' ? '23:59' : @$meeting['time']);

                                $classes = [];
                                if (!empty($meeting['notes'])) {
                                    $classes[] = 'notes';
                                }

                                // Fixes issue 41
                                if (intval(current_time('w')) === @$meeting['day']) {
                                    if (date('H:i', strtotime($meeting['time'])) <= date('H:i', current_time('U'))) {
                                        $classes[] = 'past';
                                    }
                                }

                                foreach ($meeting['types'] as $type) {
                                    $classes[] = 'type-' . sanitize_title($type);
                                }
                                $classes[] = 'attendance-' . sanitize_title($meeting['attendance_option']);
                                ?>
                                <tr class="<?php echo join(' ', $classes) ?>">
                                    <?php foreach ($tsml_columns as $key => $column) {
                                        switch ($key) {
                                            case 'time': ?>
                                                <td class="time"
                                                    data-sort="<?php echo $sort_time . '-' . tsml_sanitize_data_sort($meeting['location']) ?>">
                                                    <span>
                                                        <?php
                                                        if (($day === null) && !empty($meeting['time'])) {
                                                            echo tsml_format_day_and_time($meeting['day'], $meeting['time'], '</span><span>');
                                                        } elseif (!empty($meeting['time_formatted'])) {
                                                            echo $meeting['time_formatted'];
                                                        } else {
                                                            _e('Appointment', '12-step-meeting-list');
                                                        }
                                                        ?>
                                                    </span>
                                                </td>
                                                <?php
                                                break;

                                            case 'distance': ?>
                                                <td class="distance"
                                                    data-sort="<?php if (isset($meeting['distance'])) echo $meeting['distance'] ?>">
                                                    <?php if (isset($meeting['distance'])) echo $meeting['distance'] ?>
                                                    </td>
                                                    <?php
                                                    break;

                                            case 'name': ?>
                                                <td class="name"
                                                    data-sort="<?php echo tsml_sanitize_data_sort($meeting['name']) . '-' . $sort_time ?>">
                                                    <?php echo tsml_link($meeting['url'], $meeting['name']); ?>
                                                    <?php
                                                    $meeting_types = tsml_format_types($meeting['types']);
                                                    if (!empty($meeting_types)) {
                                                        echo ' <small><span class="meeting_types">' . $meeting_types . '</span></small>';
                                                    }
                                                    ?>
                                                </td>
                                                <?php
                                                break;

                                            case 'location': ?>
                                                <td class="location"
                                                    data-sort="<?php echo tsml_sanitize_data_sort($meeting['location']) . '-' . $sort_time ?>">
                                                    <div class="location-name notranslate">
                                                        <?php echo $meeting['location'] ?>
                                                    </div>
                                                    <div class="attendance-<?php echo $meeting['attendance_option'] ?>">
                                                        <small>
                                                            <?php if ($meeting['attendance_option'] != 'in_person') echo $tsml_meeting_attendance_options[$meeting['attendance_option']] ?>
                                                            </small>
                                                        </div>
                                                    </td>
                                                    <?php
                                                            break;

                                            case 'location_group': ?>
                                                <?php
                                                $meeting_location = $meeting['location'];
                                                if ($meeting['attendance_option'] == 'online' || $meeting['attendance_option'] == 'inactive') {
                                                    $meeting_location = !empty($meeting['group']) ? $meeting['group'] : '';
                                                }
                                                ?>
                                                <td class="location"
                                                    data-sort="<?php echo tsml_sanitize_data_sort($meeting['location']) . '-' . $sort_time ?>">
                                                    <div class="location-name notranslate">
                                                        <?php echo $meeting_location; ?>
                                                    </div>
                                                    <div class="attendance-<?php echo $meeting['attendance_option']; ?>"><small>
                                                            <?php if ($meeting['attendance_option'] != 'in_person') echo $tsml_meeting_attendance_options[$meeting['attendance_option']]; ?>
                                                        </small></div>
                                                </td>
                                                <?php
                                                break;

                                            case 'address': ?>
                                                <td class="address notranslate"
                                                    data-sort="<?php echo tsml_sanitize_data_sort($meeting['formatted_address']) . '-' . $sort_time ?>">
                                                    <?php echo tsml_format_address($meeting['formatted_address'], $tsml_street_only) ?>
                                                </td>
                                                <?php
                                                break;

                                            case 'region': ?>
                                                <td class="region notranslate"
                                                    data-sort="<?php echo tsml_sanitize_data_sort($meeting['region']) . '-' . $sort_time ?>">
                                                    <?php echo $meeting['region'] ?>
                                                </td>
                                                <?php
                                                break;

                                            case 'district': ?>
                                                <td class="district notranslate"
                                                    data-sort="<?php echo tsml_sanitize_data_sort($meeting['district']) . '-' . $sort_time ?>">
                                                    <?php echo $meeting['district'] ?>
                                                </td>
                                                <?php
                                                break;

                                            case 'types': ?>
                                                <td class="types"
                                                    data-sort="<?php echo tsml_sanitize_data_sort(tsml_meeting_types($meeting['types'])) . '-' . $sort_time ?>">
                                                    <?php echo tsml_meeting_types($meeting['types']) ?>
                                                </td>
                                                <?php
                                                break;
                                        }
                                    }
                                    ?>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if (is_active_sidebar('tsml_meetings_bottom')) { ?>
            <div class="widgets meetings-widgets meetings-widgets-bottom" role="complementary">
                <?php dynamic_sidebar('tsml_meetings_bottom') ?>
            </div>
        <?php } ?>

    </div>

</div>

<script>
    var locations = <?php echo json_encode($locations) ?>;
</script>

<?php
tsml_footer();