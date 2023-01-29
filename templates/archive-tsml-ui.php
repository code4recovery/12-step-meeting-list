<?php
tsml_assets();

get_header();

if (is_active_sidebar('tsml_meetings_top')) {  ?>
    <aside class="widgets tsml-ui-widgets tsml-ui-widgets-meetings-top">
        <?php dynamic_sidebar('tsml_meetings_top') ?>
    </aside>
<?php }

echo tsml_ui();

if (is_active_sidebar('tsml_meetings_bottom')) { ?>
    <aside class="widgets tsml-ui-widgets tsml-ui-widgets-meetings-bottom">
        <?php dynamic_sidebar('tsml_meetings_bottom') ?>
    </aside>
<?php }

if (is_active_sidebar('tsml_meeting_bottom')) {  ?>
    <aside class="widgets tsml-ui-widgets tsml-ui-widgets-meeting-bottom">
        <?php dynamic_sidebar('tsml_meeting_bottom') ?>
    </aside>
<?php }

get_footer();
