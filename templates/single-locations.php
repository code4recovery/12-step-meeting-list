<?php
tsml_assets();

$location = tsml_get_location();

// define some vars for the map
wp_localize_script('tsml_public', 'tsml_map', [
    'formatted_address' => $location->formatted_address,
    'approximate' => $location->approximate,
    'directions' => __('Directions', '12-step-meeting-list'),
    'directions_url' => $location->directions,
    'latitude' => $location->latitude,
    'location' => get_the_title(),
    'location_id' => $location->ID,
    'location_url' => get_permalink($location->ID),
    'longitude' => $location->longitude,
]);

// adding custom body classes
add_filter('body_class', function ($classes) {
    $classes[] = 'tsml tsml-detail tsml-location';
    return $classes;
});

tsml_header();
?>

<div id="tsml">
    <div id="location" class="container">
        <div class="row">
            <div class="col-md-10 col-md-offset-1 main">

                <div class="page-header">
                    <h1>
                        <?php echo esc_html($location->post_title) ?>
                    </h1>
                    <div>
                        <a
                            href="<?php echo esc_url(tsml_link_url(get_post_type_archive_link('tsml_meeting'), 'tsml_location')) ?>">
                            <em class="glyphicon glyphicon-chevron-right"></em>
                            <?php esc_html_e('Back to Meetings', '12-step-meeting-list') ?>
                        </a>
                    </div>
                </div>

                <div class="row location">
                    <div class="col-md-4">
                        <?php if ($location->approximate !== 'yes') { ?>
                            <div class="panel panel-default">
                                <a class="panel-heading tsml-directions"
                                    data-latitude="<?php echo esc_attr($location->latitude) ?>"
                                    data-longitude="<?php echo esc_attr($location->longitude) ?>"
                                    data-location="<?php echo esc_attr($location->post_title) ?>">
                                    <h3 class="panel-title">
                                        <?php esc_html_e('Get Directions', '12-step-meeting-list') ?>
                                        <span class="panel-title-buttons">
                                            <span class="glyphicon glyphicon-share-alt"></span>
                                        </span>
                                    </h3>
                                </a>
                            </div>
                        <?php } ?>

                        <div class="panel panel-default">
                            <ul class="list-group">
                                <li class="list-group-item list-group-item-address">
                                    <p class="notranslate">
                                        <?php echo wp_kses(tsml_format_address($location->formatted_address), TSML_ALLOWED_HTML) ?>
                                    </p>

                                    <?php if ($location->region && !strpos($location->formatted_address, $location->region)) { ?>
                                        <p class="notranslate">
                                            <?php echo esc_html($location->region) ?>
                                        </p>
                                    <?php }

                                    if ($location->notes) {
                                        tsml_format_notes($location->notes);
                                    }
                                    ?>
                                </li>

                                <?php
                                $meetings = tsml_get_meetings(['location_id' => $location->ID]);
                                $location_days = [];
                                foreach ($meetings as $meeting) {
                                    // set types to be empty if it's not given, prevents php notices in log
                                    if (empty($meeting['types'])) {
                                        $meeting['types'] = [];
                                    }

                                    if (!isset($location_days[$meeting['day']])) {
                                        $location_days[$meeting['day']] = [];
                                    }

                                    $location_days[$meeting['day']][] = $meeting;
                                }
                                ksort($location_days);

                                if (count($location_days)) { ?>
                                    <li class="list-group-item list-group-item-meetings">
                                        <?php foreach ($location_days as $day => $meetings) { ?>
                                            <h4>
                                                <?php
                                                if (!empty($tsml_days[$day])) {
                                                    echo esc_html($tsml_days[$day]);
                                                }
                                                ?>
                                            </h4>
                                            <ul>
                                                <?php foreach ($meetings as $meeting) { ?>
                                                    <li class="meeting attendance-<?php esc_attr($meeting['attendance_option']) ?>">
                                                        <span><?php echo esc_html($meeting['time_formatted']) ?></span>
                                                        <a href="<?php echo esc_url(tsml_link_url($meeting['url'])) ?>"
                                                            class="notranslate">
                                                            <?php echo esc_html($meeting['name']) ?>
                                                        </a>
                                                        <?php
                                                        $meeting_types = tsml_format_types($meeting['types']);
                                                        if (!empty($meeting_types)) { ?>
                                                            <div class="meeting_types">
                                                                <small>(<?php echo esc_html($meeting_types) ?>)</small>
                                                            </div>
                                                        <?php } ?>
                                                        <div class="attendance-option">
                                                            <?php echo esc_html($tsml_meeting_attendance_options[$meeting['attendance_option']]) ?>
                                                        </div>
                                                    </li>
                                                <?php } ?>
                                            </ul>
                                        <?php } ?>
                                    </li>
                                <?php } ?>

                                <li class="list-group-item list-group-item-updated">
                                    <?php esc_html_e('Updated', '12-step-meeting-list') ?>
                                    <?php the_modified_date() ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <?php if (!empty($tsml_mapbox_key) || !empty($tsml_google_maps_key)) { ?>
                            <div id="map" class="panel panel-default"></div>
                        <?php } ?>
                    </div>
                </div>

            </div>
        </div>

        <?php if (is_active_sidebar('tsml_location_bottom')) { ?>
            <div class="widgets location-widgets location-widgets-bottom" role="complementary">
                <?php dynamic_sidebar('tsml_location_bottom') ?>
            </div>
        <?php } ?>

    </div>
</div>
<?php
tsml_footer();
