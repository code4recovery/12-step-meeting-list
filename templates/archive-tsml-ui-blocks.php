<!-- USE WITH TSML UI IN A BLOCK THEME  -->
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
echo do_blocks( '<!-- wp:template-part {"slug":"header","theme":"twentytwentytwo","tagName":"header","className":"site-header","layout":{"inherit":true}} /-->' );
//block_header_area();

echo do_shortcode('[tsml_ui]');

//block_footer_area();
echo do_blocks('<!-- wp:template-part {"slug":"footer","theme":"twentytwentytwo","tagName":"footer","className":"site-footer","layout":{"inherit":true}} /-->');

?>

</div>
<?php wp_footer(); ?>
</body>
</html>

