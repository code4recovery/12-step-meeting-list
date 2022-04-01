 <!-- USING TSML UI IN A CLASSIC THEME -->
<?php
tsml_assets();

get_header();

if (is_active_sidebar('tsml_meetings_top')) {  ?>
    <div class="widgets meetings-widgets meetings-widgets-top" role="complementary">
        <?php dynamic_sidebar('tsml_meetings_top') ?>
    </div>
<?php } ?>

<div class="wp-site-blocks">

<?php  

echo tsml_ui();  ?>

</div>
<?php if (is_active_sidebar('tsml_meetings_bottom')) { ?>
    <div class="widgets meetings-widgets meetings-widgets-bottom" role="complementary">
        <?php dynamic_sidebar('tsml_meetings_bottom') ?>
    </div>
<?php } 

get_footer();

