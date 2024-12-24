<?php
/**
 * Renders the meetings block
 */

/** Set font size from attribute */
$fontSizeList = ['px', 'em', 'rem', 'vh', 'vw'];
$fontSizes    = ['small', 'medium', 'large', 'x-large', 'xx-large'];
if (isset($attributes['fontSize']) && $attributes['fontSize'] !== null) {
    $fontSize = $attributes['fontSize'];
    foreach ($fontSizeList as $unit) {
        if (strpos($fontSize, $unit) !== false) {
            // Set the size to that
            $size = $fontSize;
            break;
        }
    }
    
    if (in_array($fontSize, $fontSizes)) {
        // set to the size to var(--wp--preset--font-size--x-large)
        $size = "var(--wp--preset--font-size--$fontSize)";
    }
}

/** Set all styles */
$styles = [
    '--background' => $attributes['backgroundColor'] ?? null,
    '--alert-background' => $attributes['alertBackgroundColor'] ?? null,
    '--alert-text' => $attributes['alertTextColor'] ?? null,
    '--in-person' => $attributes['inPersonBadgeColor'] ?? null,
    '--inactive' => $attributes['inactiveBadgeColor'] ?? null,
    '--link' => $attributes['linkColor'] ?? null,
    '--online' => $attributes['onlineBadgeColor'] ?? null,
    '--text' => $attributes['textColor'] ?? null,
    '--focus' => $attributes['focusColor'] ?? null,
    '--border-radius' => isset($attributes['borderRadius']) ? $attributes['borderRadius'].'px' : null,
    '--font-family' => isset($attributes['fontFamily']) ? 'var(--wp--preset--font-family--'.$attributes['fontFamily'].')' : null,
    '--online-background-image' => isset($attributes['onlineBackgroundImage']) ? 'url('.$attributes['onlineBackgroundImage'].')' : null,
    "--font-size" => $size ?? null,
];

/** Load TSML assets */
tsml_assets();

/** Loop through styles & output inline <style> */
$styleStr = "";
foreach($styles as $key => $value) {
    if($value) {
        $styleStr .= "$key: $value;";
    }
}
echo '<style>#tsml-ui {' . $styleStr . '}</style>';
?>

<div <?php echo wp_kses_data(get_block_wrapper_attributes()); ?>>
    <?php echo do_shortcode('[tsml_ui]'); ?>
</div>
