<?php
/**
 * Renders all versions of the meetings block
 * Takes $attributes['blocktype'] and translates it into a shortcode to render output
 */

// Set initial vars
$blockType  = isset($attributes['blockType']) ? $attributes['blockType'] : false;
$meeting_count = isset($attributes['count']) && $blockType === 'tsml_next_meetings'
    ? ' count="' . $attributes['count'] . '"'
    : '';
$meeting_message = isset($attributes['message']) && $blockType === 'tsml_next_meetings'
    ? ' message="' . $attributes['message'] . '"'
    : '';

// If we are rendering 12 Step Meeting List's main meetings block, load all the assets
if ($blockType === 'tsml_ui') {
    tsml_assets();
}

// Set shortcode for blocks
$shortcode = "[$blockType$meeting_count$meeting_message]";
?>

<div <?php echo wp_kses_data(get_block_wrapper_attributes()); ?>>
    <?php echo do_shortcode($shortcode); ?>
</div>
