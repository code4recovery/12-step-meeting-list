<?php
tsml_assets();

$meeting = tsml_get_meeting();

// define some vars for the map
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

// adding custom body classes
add_filter('body_class', function ($classes) {
    global $meeting;

    $classes[] = 'tsml tsml-detail tsml-meeting';

    if ($type_classes = tsml_to_css_classes($meeting->types, 'tsml-type-')) {
        $classes[] = $type_classes;
    }

    // add the attendance option class to the body tag
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
                        <?php echo esc_html($meeting->post_title); ?>
                    </h1>
                    <?php
                    $meeting_types = tsml_format_types($meeting->types);
                    if (!empty($meeting_types)) { ?>
                        <small>
                            <span class="meeting_types">
                                (<?php echo esc_html($meeting_types) ?>)
                            </span>
                        </small>
                    <?php } ?>
                    <div class="attendance-option">
                        <?php echo esc_html($tsml_meeting_attendance_options[$meeting->attendance_option]) ?>
                    </div>
                    <a
                        href="<?php echo esc_url(tsml_link_url(get_post_type_archive_link('tsml_meeting'), 'tsml_meeting')) ?>">
                        <em class="glyphicon glyphicon-chevron-right"></em>
                        <?php esc_html_e('Back to Meetings', '12-step-meeting-list') ?>
                    </a>
                </div>

                <div class="row">
                    <div class="col-md-4">

                        <?php if (!in_array('TC', $meeting->types) && ($meeting->approximate !== 'yes')) { ?>
                            <div class="panel panel-default">
                                <a class="panel-heading tsml-directions" href="#"
                                    data-latitude="<?php echo esc_attr($meeting->latitude) ?>"
                                    data-longitude="<?php echo esc_attr($meeting->longitude) ?>"
                                    data-location="<?php echo esc_attr($meeting->location) ?>">
                                    <h3 class="panel-title">
                                        <?php esc_html_e('Get Directions', '12-step-meeting-list') ?>
                                        <span class="panel-title-buttons">
                                            <?php tsml_icon('directions') ?>
                                        </span>
                                    </h3>
                                </a>
                            </div>
                        <?php } ?>

                        <div class="panel panel-default">
                            <ul class="list-group">
                                <li class="list-group-item meeting-info">
                                    <h3 class="list-group-item-heading">
                                        <?php esc_html_e('Meeting Information', '12-step-meeting-list') ?>
                                    </h3>
                                    <p class="meeting-time">
                                        <?php
                                        echo esc_html(tsml_format_day_and_time($meeting->day, $meeting->time));
                                        if (!empty($meeting->end_time)) {
                                            // translators: until
                                            echo esc_html(__(' to ', '12-step-meeting-list') . tsml_format_time($meeting->end_time));
                                        }
                                        ?>
                                    </p>
                                    <ul class="meeting-types">
                                        <?php foreach ($meeting->attendance_option === 'hybrid' ? ['in_person', 'online'] : [$meeting->attendance_option] as $option) { ?>
                                            <li>
                                                <?php tsml_icon('check') ?>
                                                <?php echo esc_html($tsml_meeting_attendance_options[$option]) ?>
                                            </li>
                                        <?php } ?>
                                        <li>
                                            <hr style="margin:10px 0;" />
                                        </li>
                                        <?php foreach ($meeting->types_expanded as $type) { ?>
                                            <li>
                                                <?php tsml_icon('check') ?>
                                                <?php echo esc_html($type) ?>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                    <?php if (!empty($meeting->type_description)) { ?>
                                        <p class="meeting-type-description">
                                            <?php echo esc_html($meeting->type_description) ?>
                                        </p>
                                    <?php }

                                    if (!empty($meeting->notes)) { ?>
                                        <section class="meeting-notes">
                                            <?php tsml_format_notes($meeting->notes) ?>
                                        </section>
                                    <?php } ?>
                                </li>
                                <?php if (!empty($meeting->conference_url) || !empty($meeting->conference_phone)) { ?>
                                    <li class="list-group-item" style="padding-bottom: 0">
                                        <h3 class="list-group-item-heading">
                                            <?php esc_html_e('Online Meeting', '12-step-meeting-list') ?>
                                        </h3>
                                        <?php
                                        if (!empty($meeting->conference_url) && $provider = tsml_conference_provider($meeting->conference_url)) {
                                            tsml_icon_button($meeting->conference_url, $provider === true ? $meeting->conference_url :
                                                // translators: %s is the name of the conference provider
                                                sprintf(__('Join with %s', '12-step-meeting-list'), $provider), 'video');
                                            ?>
                                            <?php if ($meeting->conference_url_notes) { ?>
                                                <p style="margin: 7.5px 0 15px; color: #777; font-size: 90%;">
                                                    <?php echo esc_html($meeting->conference_url_notes) ?>
                                                </p>
                                            <?php } ?>
                                        <?php }
                                        if (!empty($meeting->conference_phone)) {
                                            tsml_icon_button('tel:' . $meeting->conference_phone, __('Join by Phone', '12-step-meeting-list'), 'phone');
                                            if ($meeting->conference_phone_notes) { ?>
                                                <p style="margin: 7.5px 0 15px; color: #777; font-size: 90%;">
                                                    <?php echo esc_html($meeting->conference_phone_notes) ?>
                                                </p>
                                            <?php }
                                        } ?>
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
                                            <?php esc_html_e('7th Tradition', '12-step-meeting-list') ?>
                                        </h3>
                                        <?php
                                        foreach ($active_services as $field) {
                                            $service = $services[$field];
                                            if (!empty($meeting->{$field})) {
                                                tsml_icon_button($service['url'] . substr($meeting->{$field}, $service['substr']), sprintf(
                                                    // translators: %s is the name of the contribution service
                                                    __('Contribute with %s', '12-step-meeting-list'),
                                                    $service['name']
                                                ), 'cash');
                                            }
                                        }
                                        ?>
                                    </li>
                                <?php }

                                if (!empty($meeting->location_id)) { ?>
                                    <a href="<?php echo esc_url(tsml_link_url(get_permalink($meeting->post_parent), 'tsml_meeting')) ?>"
                                        class="list-group-item list-group-item-location">
                                        <h3 class="list-group-item-heading notranslate">
                                            <?php echo esc_html($meeting->location) ?>
                                        </h3>

                                        <?php if ($other_meetings = count($meeting->location_meetings) - 1) { ?>
                                            <p class="location-other-meetings">
                                                <?php echo esc_html(sprintf(
                                                    // translators: %d is the number of other meetings at this location
                                                    _n('%d other meeting at this location', '%d other meetings at this location', $other_meetings, '12-step-meeting-list'),
                                                    $other_meetings
                                                )) ?>
                                            </p>
                                        <?php } ?>

                                        <p class="location-address notranslate">
                                            <?php echo wp_kses(tsml_format_address($meeting->formatted_address), TSML_ALLOWED_HTML) ?>
                                        </p>

                                        <?php if (!empty($meeting->location_notes)) { ?>
                                            <section class="location-notes">
                                                <?php tsml_format_notes($meeting->location_notes) ?>
                                            </section>
                                        <?php }

                                        if (!empty($meeting->region) && !strpos($meeting->formatted_address, $meeting->region)) { ?>
                                            <p class="location-region notranslate"><?php echo esc_html($meeting->region) ?></p>
                                        <?php } ?>

                                    </a>
                                <?php }

                                // whether this meeting has public contact info to show
                                $hasContactInformation = (($tsml_contact_display == 'public') && (!empty($meeting->contact_1_name) || !empty($meeting->contact_1_email) || !empty($meeting->contact_1_phone) ||
                                    !empty($meeting->contact_2_name) || !empty($meeting->contact_2_email) || !empty($meeting->contact_2_phone) ||
                                    !empty($meeting->contact_3_name) || !empty($meeting->contact_3_email) || !empty($meeting->contact_3_phone)));

                                if (!empty($meeting->group) || !empty($meeting->website) || !empty($meeting->website_2) || !empty($meeting->email) || !empty($meeting->phone) || $hasContactInformation) { ?>
                                    <li class="list-group-item list-group-item-group">
                                        <h3 class="list-group-item-heading">
                                            <?php echo esc_html(empty($meeting->group) ? __('Contact Information', '12-step-meeting-list') : $meeting->group) ?>
                                        </h3>
                                        <?php
                                        if (!empty($meeting->group_notes)) { ?>
                                            <section class="group-notes">
                                                <?php tsml_format_notes($meeting->group_notes) ?>
                                            </section>
                                        <?php }
                                        if (!empty($meeting->district)) { ?>
                                            <section class="group-district notranslate">
                                                <?php echo esc_html($meeting->district) ?>
                                            </section>
                                        <?php }
                                        if (!empty($meeting->website)) {
                                            tsml_icon_button($meeting->website, tsml_format_domain($meeting->website), 'link');
                                        }
                                        if (!empty($meeting->website_2)) {
                                            tsml_icon_button($meeting->website_2, tsml_format_domain($meeting->website_2), 'link');
                                        }
                                        if (!empty($meeting->email)) {
                                            tsml_icon_button('mailto:' . $meeting->email, $meeting->email, 'email');
                                        }
                                        if (!empty($meeting->phone)) {
                                            tsml_icon_button('tel:' . $meeting->phone, $meeting->phone, 'phone');
                                        }
                                        if ($hasContactInformation) {
                                            for ($i = 1; $i <= TSML_GROUP_CONTACT_COUNT; $i++) {
                                                $name = empty($meeting->{'contact_' . $i . '_name'}) ? sprintf(
                                                    // translators: %s is the contact's ordinal number
                                                    __('Contact %s', '12-step-meeting-list'),
                                                    $i
                                                ) : $meeting->{'contact_' . $i . '_name'};
                                                if (!empty($meeting->{'contact_' . $i . '_email'})) {
                                                    tsml_icon_button('mailto:' . $meeting->{'contact_' . $i . '_email'}, sprintf(
                                                        // translators: %s is the contact's name
                                                        __('%s’s Email', '12-step-meeting-list'),
                                                        $name
                                                    ), 'email');
                                                }
                                                if (!empty($meeting->{'contact_' . $i . '_phone'})) {
                                                    tsml_icon_button('sms:' . $meeting->{'contact_' . $i . '_phone'}, sprintf(
                                                        // translators: %s is the contact's name
                                                        __('%s’s Phone', '12-step-meeting-list'),
                                                        $name
                                                    ), 'phone');
                                                }
                                            }
                                        }
                                        ?>
                                    </li>
                                    <?php
                                } ?>
                                <li class="list-group-item list-group-item-updated">
                                    <?php
                                    if (!empty($meeting->data_source)) {
                                        if (!empty($meeting->entity) || !empty($meeting->entity_url)) { ?>
                                            <p class="meeting-entity-title">
                                                <?php esc_html_e('This listing is provided by:', '12-step-meeting-list') ?>
                                            </p>
                                            <?php
                                            if (!empty($meeting->entity)) { ?>
                                                <h3 class="meeting-entity-name">
                                                    <?php echo esc_html($meeting->entity) ?>
                                                </h3>
                                            <?php }
                                            if (!empty($meeting->entity_location)) { ?>
                                                <p class="meeting-entity-location">
                                                    <?php echo esc_html($meeting->entity_location) ?>
                                                </p>
                                            <?php }
                                            if (!empty($meeting->entity_phone)) {
                                                tsml_icon_button('tel:' . $meeting->entity_phone, $meeting->entity_phone, 'phone');

                                            }
                                            if (!empty($meeting->entity_url)) {
                                                tsml_icon_button($meeting->entity_url, preg_replace('%^https?\:\/+%', '', $meeting->entity_url), 'link');
                                            }
                                        }
                                    }
                                    ?>
                                    <p class="meeting-updated">
                                        <?php esc_html_e('Updated', '12-step-meeting-list') ?>
                                        <?php the_modified_date() ?>
                                    </p>
                                </li>
                            </ul>
                        </div>

                        <?php
                        if (!empty($tsml_feedback_addresses) || !empty($meeting->feedback_emails)) { ?>
                            <form id="feedback">
                                <input type="hidden" name="action" value="tsml_feedback">
                                <input type="hidden" name="meeting_id" value="<?php echo esc_attr($meeting->ID) ?>">
                                <?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
                                <div class="panel panel-default panel-expandable">
                                    <div class="panel-heading">
                                        <h3 class="panel-title">
                                            <?php esc_html_e('Request a change to this listing', '12-step-meeting-list') ?>
                                            <span class="panel-title-buttons">
                                                <span class="glyphicon glyphicon-chevron-left"></span>
                                            </span>
                                        </h3>
                                    </div>
                                    <ul class="list-group">
                                        <li class="list-group-item list-group-item-warning">
                                            <?php esc_html_e('Use this form to submit a change to the meeting information above.', '12-step-meeting-list') ?>
                                        </li>
                                        <li class="list-group-item list-group-item-form">
                                            <?php tsml_input_text('tsml_name', '', [
                                                'placeholder' => __('Your Name', '12-step-meeting-list'),
                                                'class' => 'required'
                                            ]) ?>
                                        </li>
                                        <li class="list-group-item list-group-item-form">
                                            <?php tsml_input_email('tsml_email', '', [
                                                'placeholder' => __('Email Address', '12-step-meeting-list'),
                                                'class' => 'required email'
                                            ]) ?>
                                        </li>
                                        <li class="list-group-item list-group-item-form">
                                            <textarea id="tsml_message" name="tsml_message"
                                                placeholder="<?php esc_html_e('Message', '12-step-meeting-list') ?>"
                                                class="required"></textarea>
                                        </li>
                                        <li class="list-group-item list-group-item-form">
                                            <button type="submit">
                                                <?php esc_html_e('Submit', '12-step-meeting-list') ?>
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </form>
                        <?php } ?>

                    </div>
                    <div class="col-md-8">
                        <?php if ('online' === $meeting->attendance_option) { ?>
                            <div class="panel panel-default panel-online"></div>
                        <?php } ?>
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
