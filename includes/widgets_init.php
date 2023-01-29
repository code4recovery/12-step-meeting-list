<?php
//enables widgets on the meeting archive page
add_action('widgets_init', function () {

    $areas = [
        'tsml_meetings_top' => [
            __('Meetings Top', '12-step-meeting-list'),
            __('Shown at the top of the main meetings screen', '12-step-meeting-list'),
        ],
        'tsml_meetings_bottom' => [
            __('Meetings Bottom', '12-step-meeting-list'),
            __('Shown at the bottom of the main meetings screen', '12-step-meeting-list'),
        ],
        'tsml_meeting_bottom' => [
            __('Meeting Detail Bottom', '12-step-meeting-list'),
            __('Shown at the bottom of the meeting detail screen', '12-step-meeting-list'),
        ],
        'tsml_location_bottom' => [
            __('Location Detail Bottom', '12-step-meeting-list'),
            __('Shown at the bottom of the location detail screen (Legacy UI only)', '12-step-meeting-list'),
        ],
    ];

    foreach ($areas as $id => list($name, $description)) {
        register_sidebar([
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'before_widget' => '<div class="widget">',
            'after_widget' => '</div>',
            'before_title' => '<h3>',
            'after_title' => '</h3>',
        ]);
    }
});
