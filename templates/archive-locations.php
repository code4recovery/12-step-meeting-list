<?php
$title = sprintf(
    __('Index of %s Meetings', '12-step-meeting-list'),
    $tsml_programs[$tsml_program]['name']
);

$meetings = tsml_get_meetings();

$title = __(sprintf('Index of %s Meetings', $tsml_programs[$tsml_program]['name']), '12-step-meeting-list');

$days = [
    __('Sunday', '12-step-meeting-list'),
    __('Monday', '12-step-meeting-list'),
    __('Tuesday', '12-step-meeting-list'),
    __('Wednesday', '12-step-meeting-list'),
    __('Thursday', '12-step-meeting-list'),
    __('Friday', '12-step-meeting-list'),
    __('Saturday', '12-step-meeting-list')
];

$days_en = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday'
];

$website_timezone = $tsml_timezone ? $tsml_timezone : get_option('timezone_string');

$schema = [
    '@context' => 'https://schema.org',
    '@graph' => array_map(function ($meeting) use ($days_en, $website_timezone) {
        $meeting['time'] = DateTime::createFromFormat('H:i', $meeting['time']);
        $meeting['start_date'] = new DateTime('this ' . $days_en[$meeting['day']]);
        if (empty($meeting['end_time'])) {
            $meeting['end_time'] = $meeting['time']->modify('+1 hour');
        } else {
            $meeting['end_time'] = DateTime::createFromFormat('H:i', $meeting['end_time']);
        }
        $timezone = $meeting['timezone'] ?? $website_timezone;
        $entry = [
            '@type' => 'Event',
            'name' => $meeting['name'],
            'url' => @$meeting['url'],
            'description' => tsml_meeting_types($meeting['types']),
            "eventSchedule" => [
                "@type" => "Schedule",
                "repeatFrequency" => "P1W",
                "byDay" => "https://schema.org/" . $days_en[$meeting['day']],
                "startDate" => $meeting['start_date']->format('Y-m-d'),
                "startTime" => $meeting['time']->format('H:i'),
                "endTime" => $meeting['end_time']->format('H:i'),
                "scheduleTimezone" => $timezone
            ],
            'location' => [
                '@type' => 'Place',
                'name' => @$meeting['location'],
                'address' => @$meeting['formatted_address'],
                'geo' => ('yes' == $meeting['approximate']) ?
                    [
                        '@type' => 'GeoCircle',
                        'geoMidpoint' => [
                            '@type' => 'GeoCoordinates',
                            'latitude' => @$meeting['latitude'],
                            'longitude' => @$meeting['longitude']
                        ],
                        'geoRadius' => 50
                    ] :
                    [
                        '@type' => 'GeoCoordinates',
                        'latitude' => @$meeting['latitude'],
                        'longitude' => @$meeting['longitude']
                    ]
            ]
        ];
        if (!empty($meeting['group'])) {
            $entry['organizer'] = [
                '@type' => 'Organization',
                'name' => $meeting['group'],
                'url' => @$meeting['group_url'],
                'description' => @$meeting['group_notes']
            ];
        }
        if (!empty($meeting['location_notes'])) {
            $entry['location']['description'] = $meeting['location_notes'];
        }
        return $entry;
    }, array_values(array_filter($meetings, function ($meeting) {
        return !empty($meeting['name']) && isset($meeting['day']) && !empty($meeting['time']);
    }))),
];
?><!doctype html>
<html lang="<?php echo esc_attr(substr(get_bloginfo('language'), 0, 2)); ?>">

<head>
    <meta charset="utf-8">
    <title><?php echo esc_html($title); ?></title>
    <script type="application/ld+json">
        <?php echo json_encode($schema, JSON_PRETTY_PRINT); ?>
    </script>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            font-size: 16px;
            margin: 1em;
            color: #333;
        }
    </style>
</head>

<body>
    <h1><?php echo esc_html($title); ?></h1>
    <p>
        <?php echo sprintf(
            __('This page is intended for web crawlers. To find a meeting, please visit our <a href="%s">meetings page</a>.', '12-step-meeting-list'),
            esc_url(get_post_type_archive_link('tsml_meeting'))
        ); ?>
    </p>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th><?php _e('Day', '12-step-meeting-list'); ?></th>
                <th><?php _e('Time', '12-step-meeting-list'); ?></th>
                <th><?php _e('Meeting', '12-step-meeting-list'); ?></th>
                <th><?php _e('Location', '12-step-meeting-list'); ?></th>
                <th><?php _e('Formatted Address', '12-step-meeting-list'); ?></th>
                <th><?php _e('Region', '12-step-meeting-list'); ?></th>
                <th><?php _e('Sub-Region', '12-step-meeting-list'); ?></th>
                <th><?php _e('Types', '12-step-meeting-list'); ?></th>
                <th><?php _e('Latitude', '12-step-meeting-list'); ?></th>
                <th><?php _e('Longitude', '12-step-meeting-list'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($meetings as $meeting) { ?>
                <tr>
                    <td><?php echo esc_html(@$tsml_days[$meeting['day']]); ?></td>
                    <td><?php echo tsml_format_time(@$meeting['time']); ?></td>
                    <td><a href="<?php echo esc_url($meeting['url']); ?>"><?php echo esc_html($meeting['name']); ?></a></td>
                    <td><?php echo esc_html($meeting['location'] ?? ''); ?></td>
                    <td><?php echo esc_html($meeting['formatted_address'] ?? ''); ?></td>
                    <td><?php echo esc_html($meeting['region'] ?? ''); ?></td>
                    <td><?php echo esc_html($meeting['sub_region'] ?? ''); ?></td>
                    <td><?php echo esc_html(tsml_meeting_types($meeting['types']) ?? ''); ?></td>
                    <td><?php echo esc_html($meeting['latitude'] ?? ''); ?></td>
                    <td><?php echo esc_html($meeting['longitude'] ?? ''); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</body>

</html>