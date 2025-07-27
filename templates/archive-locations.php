<?php
$title = sprintf(
    __('Index of %s Meetings', '12-step-meeting-list'),
    $tsml_programs[$tsml_program]['name']
);
?><!doctype html>
<html lang="<?php echo esc_attr(substr(get_bloginfo('language'), 0, 2)); ?>">

<head>
    <meta charset="utf-8">
    <title><?php echo esc_html($title); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            font-size: 16px;
            margin: 1em;
            color: #333;
        }

        .table {
            display: table;
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 2em;
            border: 1px solid #ccc;

            .row {
                display: table-row;
                border-bottom: 1px solid #ccc;

                &.header {
                    font-weight: bold;
                    background: #eee;
                    position: sticky;
                    top: 0;
                    z-index: 5;
                }

                .cell {
                    display: table-cell;
                    padding: 0.5em;
                    border-right: 1px solid #ccc;

                    &:last-child {
                        border-right: none;
                    }
                }
            }
        }
    </style>
</head>

<body>

    <h1><?php echo $title; ?></h1>

    <p>
        <?php echo sprintf(
            __('This page is intended for web crawlers. To find a meeting, please visit our <a href="%s">meetings page</a>.', '12-step-meeting-list'),
            esc_url(get_post_type_archive_link('tsml_meeting'))
        ); ?>
    </p>

    <div class="table">
        <div class="row header">
            <div class="cell">
                <?php echo __('Day', '12-step-meeting-list'); ?> / <?php echo __('Time', '12-step-meeting-list'); ?>
            </div>
            <div class="cell"><?php echo __('Meeting', '12-step-meeting-list'); ?></div>
            <div class="cell"><?php echo __('Location', '12-step-meeting-list'); ?></div>
            <div class="cell"><?php echo __('Types', '12-step-meeting-list'); ?></div>
            <div class="cell"><?php echo __('Region', '12-step-meeting-list'); ?></div>
        </div>
        <?php
        $meetings = tsml_get_meetings();
        $days_of_week = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        foreach ($meetings as $meeting) { ?>

            <div class="row" itemscope itemtype="https://schema.org/Event">
                <div class="cell" itemprop="eventSchedule" itemscope itemtype="https://schema.org/Schedule">
                    <meta itemprop="repeatFrequency" content="P1W" />
                    <div>
                        <?php if (isset($meeting['day'])) { ?>
                            <span itemprop="byDay" content="<?php echo esc_attr($days_of_week[$meeting['day']]); ?>">
                                <?php echo esc_html(@$tsml_days[$meeting['day']]); ?>
                            </span>
                        <?php } ?>
                        <?php if (!empty($meeting['time'])) { ?>
                            <span itemprop="startTime" content="<?php echo esc_attr($meeting['time']); ?>">
                                <?php echo tsml_format_time($meeting['time']); ?>
                            </span>
                        <?php } ?>
                    </div>
                    <meta itemprop="scheduleTimezone"
                        content="<?php echo esc_attr(empty($meeting['timezone']) ? $tsml_timezone : $meeting['timezone']); ?>" />
                </div>
                <div class="cell">
                    <div itemprop="name">
                        <?php echo esc_html($meeting['name']); ?>
                    </div>
                    <div itemprop="url">
                        <a href="<?php echo esc_url($meeting['url']); ?>"><?php echo esc_url($meeting['url']); ?></a>
                    </div>
                    <meta itemprop="url" content="<?php echo esc_url($meeting['url']); ?>" />
                    <?php if (!empty($meeting['group'])) { ?>
                        <div itemprop="organizer" itemscope itemtype="https://schema.org/Organization">
                            <meta itemprop="name" content="<?php echo esc_attr($meeting['group']); ?>" />
                            <?php if (!empty($meeting['group_website'])) { ?>
                                <meta itemprop="url" content="<?php echo esc_attr($meeting['group_website']); ?>" />
                            <?php } ?>
                            <?php if (!empty($meeting['group_notes'])) { ?>
                                <meta itemprop="description" content="<?php echo esc_attr($meeting['group_notes']); ?>" />
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <?php
                    if (!empty($meeting['attendance_option']) || 'inactive' !== $meeting['attendance_option']) {
                        $attendance_type = 'OfflineEventAttendanceMode';
                        if ($meeting['attendance_option'] == 'online') {
                            $attendance_type = 'OnlineEventAttendanceMode';
                        }
                        if ($meeting['attendance_option'] == 'hybrid') {
                            $attendance_type = 'MixedEventAttendanceMode';
                        }
                        ?>
                        <meta itemprop="eventAttendanceMode" content="https://schema.org/<?php echo $attendance_type; ?>" />
                    <?php } ?>
                </div>
                <div class="cell" itemprop="location" itemscope itemtype="https://schema.org/Place">
                    <div itemprop="name"><?php echo esc_html(@$meeting['location']); ?></div>
                    <?php if (!empty($meeting['location_notes'])) { ?>
                        <meta itemprop="description" content="<?php echo esc_attr(@$meeting['location_notes']); ?>" />
                    <?php } ?>
                    <div itemprop="address"><?php echo esc_html(@$meeting['formatted_address']); ?></div>
                    <div>
                        <span itemprop="latitude"><?php echo esc_html(@$meeting['latitude']); ?></span>
                        <span itemprop="longitude"><?php echo esc_html(@$meeting['longitude']); ?></span>
                    </div>
                </div>
                <div class="cell" itemprop="description">
                    <?php echo esc_html(tsml_meeting_types($meeting['types'])); ?>
                </div>
                <div class="cell">
                    <?php echo esc_html($meeting['region']) . PHP_EOL; ?>
                    <?php
                    if (!empty($meeting['sub_region'])) {
                        echo '(' . esc_html($meeting['sub_region']) . ')' . PHP_EOL;
                    }
                    ?>
                </div>
            </div>

        <?php } ?>
    </div>
</body>

</html>