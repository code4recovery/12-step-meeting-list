<?php
get_header();
?>

<h1>Index of <?php echo $tsml_programs[$tsml_program]['name']; ?> Meetings</h1>

<p>
    This page is intended for web crawlers. To find a meeting, please visit our
    <a href="<?php echo esc_url(get_post_type_archive_link('tsml_meeting')); ?>">meetings page</a>.
</p>

<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr>
            <th>Day</th>
            <th>Time</th>
            <th>Meeting</th>
            <th>Location</th>
            <th>Formatted Address</th>
            <th>Region</th>
            <th>Sub-Region</th>
            <th>Types</th>
            <th>Latitude</th>
            <th>Longitude</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $meetings = tsml_get_meetings();
        foreach ($meetings as $meeting) { ?>
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
<?php
get_footer();
