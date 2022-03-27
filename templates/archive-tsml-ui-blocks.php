 <!-- USING TSML UI IN A BLOCK THEME -->
 <!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">

<?php
tsml_assets();

echo do_blocks( '<!-- wp:template-part {"slug":"header","theme":"twentytwentytwo","tagName":"header","className":"site-header","layout":{"inherit":true}} /-->' );

if (is_active_sidebar('tsml_meetings_top')) {  ?>
    <div class="widgets meetings-widgets meetings-widgets-top" role="complementary">
        <?php dynamic_sidebar('tsml_meetings_top') ?>
    </div>
<?php } ?>

<div class="wp-site-blocks">

<?php echo tsml_ui();  ?>

</div>
<?php if (is_active_sidebar('tsml_meetings_bottom')) { ?>
    <div class="widgets meetings-widgets meetings-widgets-bottom" role="complementary">
        <?php dynamic_sidebar('tsml_meetings_bottom') ?>
    </div>
<?php } 

//block_footer_area();
echo do_blocks('<!-- wp:template-part {"slug":"footer","theme":"twentytwentytwo","tagName":"footer","className":"site-footer","layout":{"inherit":true}} /-->');

?>
</div>
<?php wp_footer(); ?>
</body>
</html>
