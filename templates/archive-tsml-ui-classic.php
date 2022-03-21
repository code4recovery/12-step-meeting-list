<!-- USE WITH TSML UI IN A CLASSIC THEME  -->
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
get_header();

echo do_shortcode('[tsml_ui]');

get_footer();
?>

</div>
<?php wp_footer(); ?>
</body>
</html>

