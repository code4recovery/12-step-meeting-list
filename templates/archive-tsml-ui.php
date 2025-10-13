<?php
tsml_header();

// protect against parents using display: flex
?>
<div style="width: 100%">

    <a hidden href="<?php echo esc_url(get_post_type_archive_link('tsml_location')) ?>"><?php
       _e(sprintf('Index of %s Meetings', $tsml_programs[$tsml_program]['name']), '12-step-meeting-list')
           ?></a>


    <?php if (is_active_sidebar('tsml_meetings_top')) { ?>
        <div class="widgets meetings-widgets meetings-widgets-top" role="complementary">
            <?php dynamic_sidebar('tsml_meetings_top') ?>
        </div>
    <?php }

    echo tsml_ui(['pretty' => true]);

    if (is_active_sidebar('tsml_meetings_bottom')) { ?>
        <div class="widgets meetings-widgets meetings-widgets-bottom" role="complementary">
            <?php dynamic_sidebar('tsml_meetings_bottom') ?>
        </div>
    <?php } ?>

</div>

<?php

tsml_footer();