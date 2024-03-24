<?php
tsml_header();

if (is_active_sidebar('tsml_meetings_top')) { ?>
    <div class="widgets meetings-widgets meetings-widgets-top" role="complementary">
        <?php dynamic_sidebar('tsml_meetings_top') ?>
    </div>
<?php }

echo tsml_ui();

if (is_active_sidebar('tsml_meetings_bottom')) { ?>
    <div class="widgets meetings-widgets meetings-widgets-bottom" role="complementary">
        <?php dynamic_sidebar('tsml_meetings_bottom') ?>
    </div>
<?php }

tsml_footer();