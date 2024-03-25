<?php
tsml_assets();

$meeting = tsml_get_meeting();

//define some vars for the map
wp_localize_script('tsml_public', 'tsml_map', [
    'directions' => __('Directions', '12-step-meeting-list'),
    'directions_url' => in_array('TC', $meeting->types) ? null : $meeting->directions,
    'formatted_address' => $meeting->formatted_address,
    'approximate' => $meeting->approximate,
    'latitude' => $meeting->latitude,
    'location' => get_the_title($meeting->post_parent),
    'location_id' => $meeting->post_parent,
    'location_url' => get_permalink($meeting->post_parent),
    'longitude' => $meeting->longitude,
]);

//adding custom body classes
add_filter('body_class', function ($classes) {
    global $meeting;

    $classes[] = 'tsml tsml-detail tsml-meeting';

    if ($type_classes = tsml_to_css_classes($meeting->types, 'tsml-type-')) {
        $classes[] = $type_classes;
    }

    // Add the attendance option class to the body tag
    $classes[] = 'attendance-' . sanitize_title($meeting->attendance_option);
    $classes[] = ($meeting->approximate === 'yes') ? 'address-approximate' : 'address-specific';

    return $classes;
});

tsml_header();
?>

<div id="tsml">
    <div id="meeting" class="container">
        <div class="row">
            <div class="col-md-10 col-md-offset-1 main">

                <div class="page-header">
                    <h1>
                        <?php echo $meeting->post_title; ?>
                    </h1>
                    <?php
                    $meeting_types = tsml_format_types($meeting->types);
                    if (!empty($meeting_types)) {
                        echo '<small><span class="meeting_types">(' . __($meeting_types, "12-step-meeting-list") . ')</span></small>';
                    }
                    ?>
                    <div class="attendance-option">
                        <?php _e($tsml_meeting_attendance_options[$meeting->attendance_option], "12-step-meeting-list") ?>
                    </div>
                    <br />
                    <?php echo tsml_link(get_post_type_archive_link('tsml_meeting'), '<i class="glyphicon glyphicon-chevron-right"></i> ' . __('Back to Meetings', '12-step-meeting-list'), 'tsml_meeting') ?>
                </div>

                <div class="row">
                    <div class="col-md-4">

                        <?php if (!in_array('TC', $meeting->types) && ($meeting->approximate !== 'yes')) { ?>
                            <div class="panel panel-default">
                                <a class="panel-heading tsml-directions" href="#"
                                    data-latitude="<?php echo $meeting->latitude ?>"
                                    data-longitude="<?php echo $meeting->longitude ?>"
                                    data-location="<?php echo $meeting->location ?>">
                                    <h3 class="panel-title">
                                        <?php _e('Get Directions', '12-step-meeting-list') ?>
                                        <span class="panel-title-buttons">
                                            <svg width="1em" height="1em" viewBox="0 0 16 16" fill="currentColor"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd"
                                                    d="M9.896 2.396a.5.5 0 0 0 0 .708l2.647 2.646-2.647 2.646a.5.5 0 1 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708 0z" />
                                                <path fill-rule="evenodd"
                                                    d="M13.25 5.75a.5.5 0 0 0-.5-.5h-6.5a2.5 2.5 0 0 0-2.5 2.5v5.5a.5.5 0 0 0 1 0v-5.5a1.5 1.5 0 0 1 1.5-1.5h6.5a.5.5 0 0 0 .5-.5z" />
                                            </svg>
                                        </span>
                                    </h3>
                                </a>
                            </div>
                        <?php } ?>

                        <div class="panel panel-default">
                            <ul class="list-group">
                                <li class="list-group-item meeting-info">
                                    <h3 class="list-group-item-heading">
                                        <?php _e('Meeting Information', '12-step-meeting-list') ?>
                                    </h3>
                                    <p class="meeting-time">
                                        <?php
                                        echo tsml_format_day_and_time($meeting->day, $meeting->time);
                                        if (!empty($meeting->end_time)) {
                                            /* translators: until */
                                            echo __(' to ', '12-step-meeting-list'), tsml_format_time($meeting->end_time);
                                        }
                                        ?>
                                    </p>
                                    <ul class="meeting-types">
                                        <?php
                                        $li_marker = '<svg class="icon" width="1em" height="1em" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
													<path fill-rule="evenodd" d="M10.97 4.97a.75.75 0 0 1 1.071 1.05l-3.992 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.236.236 0 0 1 .02-.022z"/>
												</svg>';
                                        if ($meeting->attendance_option === 'hybrid') {
                                            echo '<li>' . $li_marker . $tsml_meeting_attendance_options['in_person'] . '</li>' . PHP_EOL;
                                            echo '<li>' . $li_marker . $tsml_meeting_attendance_options['online'] . '</li>' . PHP_EOL;
                                        } else {
                                            echo '<li>' . $li_marker . $tsml_meeting_attendance_options[$meeting->attendance_option] . '</li>' . PHP_EOL;
                                        }
                                        ?>
                                        <li>
                                            <hr style="margin:10px 0;" />
                                        </li>
                                        <?php foreach ($meeting->types_expanded as $type) { ?>
                                            <li>
                                                <?php echo $li_marker;
                                                _e($type, '12-step-meeting-list'); ?>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                    <?php if (!empty($meeting->type_description)) { ?>
                                        <p class="meeting-type-description">
                                            <?php _e($meeting->type_description, '12-step-meeting-list') ?>
                                        </p>
                                    <?php }

                                    if (!empty($meeting->notes)) { ?>
                                        <section class="meeting-notes">
                                            <?php echo wpautop($meeting->notes) ?>
                                        </section>
                                    <?php } ?>
                                </li>
                                <?php if (!empty($meeting->conference_url) || !empty($meeting->conference_phone)) { ?>
                                    <li class="list-group-item" style="padding-bottom: 0">
                                        <h3 class="list-group-item-heading">
                                            <?php _e('Online Meeting', '12-step-meeting-list') ?>
                                        </h3>
                                        <?php
                                        if (!empty($meeting->conference_url) && $provider = tsml_conference_provider($meeting->conference_url)) { ?>
                                            <a class="btn btn-default btn-block" href="<?php echo $meeting->conference_url ?>"
                                                target="_blank">
                                                <svg class="icon" width="1em" height="1em" viewBox="0 0 16 16"
                                                    fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd"
                                                        d="M2.667 3.5c-.645 0-1.167.522-1.167 1.167v6.666c0 .645.522 1.167 1.167 1.167h6.666c.645 0 1.167-.522 1.167-1.167V4.667c0-.645-.522-1.167-1.167-1.167H2.667zM.5 4.667C.5 3.47 1.47 2.5 2.667 2.5h6.666c1.197 0 2.167.97 2.167 2.167v6.666c0 1.197-.97 2.167-2.167 2.167H2.667A2.167 2.167 0 0 1 .5 11.333V4.667z" />
                                                    <path fill-rule="evenodd"
                                                        d="M11.25 5.65l2.768-1.605a.318.318 0 0 1 .482.263v7.384c0 .228-.26.393-.482.264l-2.767-1.605-.502.865 2.767 1.605c.859.498 1.984-.095 1.984-1.129V4.308c0-1.033-1.125-1.626-1.984-1.128L10.75 4.785l.502.865z" />
                                                </svg>
                                                <?php echo $provider === true ? $meeting->conference_url : sprintf(__('Join with %s', '12-step-meeting-list'), $provider) ?>
                                            </a>
                                            <?php if ($meeting->conference_url_notes) { ?>
                                                <p style="margin: 7.5px 0 15px; color: #777; font-size: 90%;">
                                                    <?php echo nl2br($meeting->conference_url_notes) ?>
                                                </p>
                                            <?php } ?>
                                        <?php }
                                        if (!empty($meeting->conference_phone)) { ?>
                                            <a class="btn btn-default btn-block"
                                                href="tel:<?php echo $meeting->conference_phone ?>">
                                                <svg class="icon" width="1em" height="1em" viewBox="0 0 16 16"
                                                    fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd"
                                                        d="M3.925 1.745a.636.636 0 0 0-.951-.059l-.97.97c-.453.453-.62 1.095-.421 1.658A16.47 16.47 0 0 0 5.49 10.51a16.471 16.471 0 0 0 6.196 3.907c.563.198 1.205.032 1.658-.421l.97-.97a.636.636 0 0 0-.06-.951l-2.162-1.682a.636.636 0 0 0-.544-.115l-2.052.513a1.636 1.636 0 0 1-1.554-.43L5.64 8.058a1.636 1.636 0 0 1-.43-1.554l.513-2.052a.636.636 0 0 0-.115-.544L3.925 1.745zM2.267.98a1.636 1.636 0 0 1 2.448.153l1.681 2.162c.309.396.418.913.296 1.4l-.513 2.053a.636.636 0 0 0 .167.604L8.65 9.654a.636.636 0 0 0 .604.167l2.052-.513a1.636 1.636 0 0 1 1.401.296l2.162 1.681c.777.604.849 1.753.153 2.448l-.97.97c-.693.693-1.73.998-2.697.658a17.47 17.47 0 0 1-6.571-4.144A17.47 17.47 0 0 1 .639 4.646c-.34-.967-.035-2.004.658-2.698l.97-.969z" />
                                                </svg>
                                                <?php _e('Join by Phone', '12-step-meeting-list') ?>
                                            </a>
                                            <?php if ($meeting->conference_phone_notes) { ?>
                                                <p style="margin: 7.5px 0 15px; color: #777; font-size: 90%;">
                                                    <?php echo nl2br($meeting->conference_phone_notes) ?>
                                                </p>
                                            <?php } ?>
                                        <?php } ?>
                                    </li>
                                <?php }

                                $services = [
                                    'venmo' => [
                                        'name' => 'Venmo',
                                        'url' => 'https://venmo.com/',
                                        'substr' => 1,
                                    ],
                                    'square' => [
                                        'name' => 'Cash App',
                                        'url' => 'https://cash.app/',
                                        'substr' => 0,
                                    ],
                                    'paypal' => [
                                        'name' => 'PayPal',
                                        'url' => 'https://www.paypal.me/',
                                        'substr' => 0,
                                    ],
                                ];
                                $active_services = array_filter(array_keys($services), function ($service) use ($meeting) {
                                    return !empty($meeting->{$service});
                                });
                                if (count($active_services)) { ?>
                                    <li class="list-group-item list-group-item-group">
                                        <h3 class="list-group-item-heading">
                                            <?php _e('7th Tradition', '12-step-meeting-list') ?>
                                        </h3>
                                        <?php
                                        foreach ($active_services as $field) {
                                            $service = $services[$field];
                                            if (!empty($meeting->{$field})) { ?>
                                                <a id="<?php echo $field ?>-link" class="btn btn-default btn-block"
                                                    href="<?php echo $service['url'] . substr($meeting->{$field}, $service['substr']) ?>"
                                                    target="_blank">
                                                    <svg class="icon" width="1em" height="1em" viewBox="0 0 16 16"
                                                        fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M14 3H1a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1h-1z" />
                                                        <path fill-rule="evenodd"
                                                            d="M15 5H1v8h14V5zM1 4a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1H1z" />
                                                        <path
                                                            d="M13 5a2 2 0 0 0 2 2V5h-2zM3 5a2 2 0 0 1-2 2V5h2zm10 8a2 2 0 0 1 2-2v2h-2zM3 13a2 2 0 0 0-2-2v2h2zm7-4a2 2 0 1 1-4 0 2 2 0 0 1 4 0z" />
                                                    </svg>
                                                    <?php echo sprintf(__('Contribute with %s', '12-step-meeting-list'), $service['name']) ?>
                                                </a>
                                            <?php }
                                        }
                                        ?>
                                    </li>
                                <?php }

                                if (!empty($meeting->location_id)) {
                                    $location_info = '
										<h3 class="list-group-item-heading notranslate">' . $meeting->location . '</h3>';

                                    if ($other_meetings = count($meeting->location_meetings) - 1) {
                                        $location_info .= '<p class="location-other-meetings">' . sprintf(_n('%d other meeting at this location', '%d other meetings at this location', $other_meetings, '12-step-meeting-list'), $other_meetings) . '</p>';
                                    }

                                    $location_info .= '<p class="location-address notranslate">' . tsml_format_address($meeting->formatted_address) . '</p>';

                                    if (!empty($meeting->location_notes)) {
                                        $location_info .= '<section class="location-notes">' . wpautop($meeting->location_notes) . '</section>';
                                    }

                                    if (!empty($meeting->region) && !strpos($meeting->formatted_address, $meeting->region)) {
                                        $location_info .= '<p class="location-region notranslate">' . $meeting->region . '</p>';
                                    }

                                    echo tsml_link(
                                        get_permalink($meeting->post_parent),
                                        $location_info,
                                        'tsml_meeting',
                                        'list-group-item list-group-item-location'
                                    );
                                }

                                //whether this meeting has public contact info to show
                                $hasContactInformation = (($tsml_contact_display == 'public') && (!empty($meeting->contact_1_name) || !empty($meeting->contact_1_email) || !empty($meeting->contact_1_phone) ||
                                    !empty($meeting->contact_2_name) || !empty($meeting->contact_2_email) || !empty($meeting->contact_2_phone) ||
                                    !empty($meeting->contact_3_name) || !empty($meeting->contact_3_email) || !empty($meeting->contact_3_phone)));

                                if (!empty($meeting->group) || !empty($meeting->website) || !empty($meeting->website_2) || !empty($meeting->email) || !empty($meeting->phone) || $hasContactInformation) { ?>
                                    <li class="list-group-item list-group-item-group">
                                        <h3 class="list-group-item-heading">
                                            <?php echo empty($meeting->group) ? __('Contact Information', '12-step-meeting-list') : $meeting->group ?>
                                        </h3>
                                        <?php
                                        if (!empty($meeting->group_notes)) { ?>
                                            <section class="group-notes">
                                                <?php echo wpautop($meeting->group_notes) ?>
                                            </section>
                                        <?php }
                                        if (!empty($meeting->district)) { ?>
                                            <section class="group-district notranslate">
                                                <?php echo $meeting->district ?>
                                            </section>
                                        <?php }
                                        if (!empty($meeting->website)) { ?>
                                            <a href="<?php echo $meeting->website ?>"
                                                class="btn btn-default btn-block group-website" target="_blank">
                                                <svg class="icon" width="1em" height="1em" viewBox="0 0 16 16"
                                                    fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M4.715 6.542L3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1.001 1.001 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4.018 4.018 0 0 1-.128-1.287z" />
                                                    <path
                                                        d="M5.712 6.96l.167-.167a1.99 1.99 0 0 1 .896-.518 1.99 1.99 0 0 1 .518-.896l.167-.167A3.004 3.004 0 0 0 6 5.499c-.22.46-.316.963-.288 1.46z" />
                                                    <path
                                                        d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 0 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 0 0-4.243-4.243L6.586 4.672z" />
                                                    <path
                                                        d="M10 9.5a2.99 2.99 0 0 0 .288-1.46l-.167.167a1.99 1.99 0 0 1-.896.518 1.99 1.99 0 0 1-.518.896l-.167.167A3.004 3.004 0 0 0 10 9.501z" />
                                                </svg>
                                                <?php echo tsml_format_domain($meeting->website) ?>
                                            </a>
                                        <?php }
                                        if (!empty($meeting->website_2)) { ?>
                                            <a href="<?php echo $meeting->website_2 ?>"
                                                class="btn btn-default btn-block group-website_2" target="_blank">
                                                <svg class="icon" width="1em" height="1em" viewBox="0 0 16 16"
                                                    fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M4.715 6.542L3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1.001 1.001 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4.018 4.018 0 0 1-.128-1.287z" />
                                                    <path
                                                        d="M5.712 6.96l.167-.167a1.99 1.99 0 0 1 .896-.518 1.99 1.99 0 0 1 .518-.896l.167-.167A3.004 3.004 0 0 0 6 5.499c-.22.46-.316.963-.288 1.46z" />
                                                    <path
                                                        d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 0 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 0 0-4.243-4.243L6.586 4.672z" />
                                                    <path
                                                        d="M10 9.5a2.99 2.99 0 0 0 .288-1.46l-.167.167a1.99 1.99 0 0 1-.896.518 1.99 1.99 0 0 1-.518.896l-.167.167A3.004 3.004 0 0 0 10 9.501z" />
                                                </svg>
                                                <?php echo tsml_format_domain($meeting->website_2) ?>
                                            </a>
                                        <?php }
                                        if (!empty($meeting->email)) { ?>
                                            <a href="mailto:<?php echo $meeting->email ?>"
                                                class="btn btn-default btn-block group-email">
                                                <svg class="icon" width="1em" height="1em" viewBox="0 0 16 16"
                                                    fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd"
                                                        d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2zm13 2.383l-4.758 2.855L15 11.114v-5.73zm-.034 6.878L9.271 8.82 8 9.583 6.728 8.82l-5.694 3.44A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.739zM1 11.114l4.758-2.876L1 5.383v5.73z" />
                                                </svg>
                                                <?php echo $meeting->email ?>
                                            </a>
                                        <?php }
                                        if (!empty($meeting->phone)) { ?>
                                            <a href="tel:<?php echo $meeting->phone ?>"
                                                class="btn btn-default btn-block group-phone">
                                                <svg class="icon" width="1em" height="1em" viewBox="0 0 16 16"
                                                    fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd"
                                                        d="M3.925 1.745a.636.636 0 0 0-.951-.059l-.97.97c-.453.453-.62 1.095-.421 1.658A16.47 16.47 0 0 0 5.49 10.51a16.471 16.471 0 0 0 6.196 3.907c.563.198 1.205.032 1.658-.421l.97-.97a.636.636 0 0 0-.06-.951l-2.162-1.682a.636.636 0 0 0-.544-.115l-2.052.513a1.636 1.636 0 0 1-1.554-.43L5.64 8.058a1.636 1.636 0 0 1-.43-1.554l.513-2.052a.636.636 0 0 0-.115-.544L3.925 1.745zM2.267.98a1.636 1.636 0 0 1 2.448.153l1.681 2.162c.309.396.418.913.296 1.4l-.513 2.053a.636.636 0 0 0 .167.604L8.65 9.654a.636.636 0 0 0 .604.167l2.052-.513a1.636 1.636 0 0 1 1.401.296l2.162 1.681c.777.604.849 1.753.153 2.448l-.97.97c-.693.693-1.73.998-2.697.658a17.47 17.47 0 0 1-6.571-4.144A17.47 17.47 0 0 1 .639 4.646c-.34-.967-.035-2.004.658-2.698l.97-.969z" />
                                                </svg>
                                                <?php echo $meeting->phone ?>
                                            </a>
                                        <?php }
                                        if ($hasContactInformation) {
                                            for ($i = 1; $i <= TSML_GROUP_CONTACT_COUNT; $i++) {
                                                $name = empty($meeting->{'contact_' . $i . '_name'}) ? sprintf(__('Contact %s', '12-step-meeting-list'), $i) : $meeting->{'contact_' . $i . '_name'};
                                                if (!empty($meeting->{'contact_' . $i . '_email'})) { ?>
                                                    <a href="mailto:<?php echo $meeting->{'contact_' . $i . '_email'} ?>"
                                                        class="btn btn-default btn-block contact-email">
                                                        <svg class="icon" width="1em" height="1em" viewBox="0 0 16 16"
                                                            fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                                            <path fill-rule="evenodd"
                                                                d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2zm13 2.383l-4.758 2.855L15 11.114v-5.73zm-.034 6.878L9.271 8.82 8 9.583 6.728 8.82l-5.694 3.44A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.739zM1 11.114l4.758-2.876L1 5.383v5.73z" />
                                                        </svg>
                                                        <?php echo sprintf(__('%s’s Email', '12-step-meeting-list'), $name) ?>
                                                    </a>
                                                    <?php
                                                }
                                                if (!empty($meeting->{'contact_' . $i . '_phone'})) { ?>
                                                    <a href="tel:<?php echo $meeting->{'contact_' . $i . '_phone'} ?>"
                                                        class="btn btn-default btn-block contact-phone">
                                                        <svg class="icon" width="1em" height="1em" viewBox="0 0 16 16"
                                                            fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                                            <path fill-rule="evenodd"
                                                                d="M3.925 1.745a.636.636 0 0 0-.951-.059l-.97.97c-.453.453-.62 1.095-.421 1.658A16.47 16.47 0 0 0 5.49 10.51a16.471 16.471 0 0 0 6.196 3.907c.563.198 1.205.032 1.658-.421l.97-.97a.636.636 0 0 0-.06-.951l-2.162-1.682a.636.636 0 0 0-.544-.115l-2.052.513a1.636 1.636 0 0 1-1.554-.43L5.64 8.058a1.636 1.636 0 0 1-.43-1.554l.513-2.052a.636.636 0 0 0-.115-.544L3.925 1.745zM2.267.98a1.636 1.636 0 0 1 2.448.153l1.681 2.162c.309.396.418.913.296 1.4l-.513 2.053a.636.636 0 0 0 .167.604L8.65 9.654a.636.636 0 0 0 .604.167l2.052-.513a1.636 1.636 0 0 1 1.401.296l2.162 1.681c.777.604.849 1.753.153 2.448l-.97.97c-.693.693-1.73.998-2.697.658a17.47 17.47 0 0 1-6.571-4.144A17.47 17.47 0 0 1 .639 4.646c-.34-.967-.035-2.004.658-2.698l.97-.969z" />
                                                        </svg>
                                                        <?php echo sprintf(__('%s’s Phone', '12-step-meeting-list'), $name) ?>
                                                    </a>
                                                <?php }
                                            }
                                        }
                                        ?>
                                    </li>
                                    <?php
                                } ?>
                                <li class="list-group-item list-group-item-updated">
                                    <?php _e('Updated', '12-step-meeting-list') ?>
                                    <?php the_modified_date() ?>
                                </li>
                            </ul>
                        </div>

                        <?php
                        if (!empty($tsml_feedback_addresses)) { ?>
                            <form id="feedback">
                                <input type="hidden" name="action" value="tsml_feedback">
                                <input type="hidden" name="meeting_id" value="<?php echo $meeting->ID ?>">
                                <?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
                                <div class="panel panel-default panel-expandable">
                                    <div class="panel-heading">
                                        <h3 class="panel-title">
                                            <?php _e('Request a change to this listing', '12-step-meeting-list') ?>
                                            <span class="panel-title-buttons">
                                                <span class="glyphicon glyphicon-chevron-left"></span>
                                            </span>
                                        </h3>
                                    </div>
                                    <ul class="list-group">
                                        <li class="list-group-item list-group-item-warning">
                                            <?php _e('Use this form to submit a change to the meeting information above.', '12-step-meeting-list') ?>
                                        </li>
                                        <li class="list-group-item list-group-item-form">
                                            <input type="text" id="tsml_name" name="tsml_name"
                                                placeholder="<?php _e('Your Name', '12-step-meeting-list') ?>"
                                                class="required">
                                        </li>
                                        <li class="list-group-item list-group-item-form">
                                            <input type="email" id="tsml_email" name="tsml_email"
                                                placeholder="<?php _e('Email Address', '12-step-meeting-list') ?>"
                                                class="required email">
                                        </li>
                                        <li class="list-group-item list-group-item-form">
                                            <textarea id="tsml_message" name="tsml_message"
                                                placeholder="<?php _e('Message', '12-step-meeting-list') ?>"
                                                class="required"></textarea>
                                        </li>
                                        <li class="list-group-item list-group-item-form">
                                            <button type="submit">
                                                <?php _e('Submit', '12-step-meeting-list') ?>
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </form>
                        <?php } ?>

                    </div>
                    <div class="col-md-8">
                        <?php if (!empty($tsml_mapbox_key) || !empty($tsml_google_maps_key)) { ?>
                            <div id="map" class="panel panel-default"></div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (is_active_sidebar('tsml_meeting_bottom')) { ?>
            <div class="widgets meeting-widgets meeting-widgets-bottom" role="complementary">
                <?php dynamic_sidebar('tsml_meeting_bottom') ?>
            </div>
        <?php } ?>

    </div>
</div>
<?php
tsml_footer();
