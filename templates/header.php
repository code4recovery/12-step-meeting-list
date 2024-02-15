<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php
    // must be before wp_head() to render CSS
    $header = do_blocks('<!-- wp:template-part {"slug":"header"} /-->');
    wp_head();
    ?>
</head>

<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>
    <div class="wp-site-blocks">
        <?php echo $header; ?>