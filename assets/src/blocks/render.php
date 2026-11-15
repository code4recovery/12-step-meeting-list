<?php

/**
 * Renders the meetings block
 */

/** Set font size from attribute */
$fontSizeList = ['px', 'em', 'rem', 'vh', 'vw'];
$fontSizes = ['small', 'medium', 'large', 'x-large', 'xx-large'];
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
    '--border-radius' => isset($attributes['borderRadius']) ? $attributes['borderRadius'] . 'px' : null,
    '--font-family' => isset($attributes['fontFamily']) ? 'var(--wp--preset--font-family--' . $attributes['fontFamily'] . ')' : null,
    '--online-background-image' => isset($attributes['onlineBackgroundImage']) ? 'url(' . $attributes['onlineBackgroundImage'] . ')' : null,
    "--font-size" => $size ?? null,
];

/** Load TSML assets */
tsml_assets();

/** Filter each declaration before outputting CSS in a style element. */
$styleStr = '';
foreach ($styles as $key => $value) {
    if (!is_string($value) || $value === '') {
        continue;
    }

    // A value must not introduce HTML, another declaration, or another rule.
    if (preg_match('/[<>{};\x00-\x1f\x7f]/', $value)) {
        continue;
    }

    $properties = [
        '--border-radius' => 'border-radius',
        '--font-family' => 'font-family',
        '--online-background-image' => 'background-image',
        '--font-size' => 'font-size',
    ];
    $property = isset($properties[$key]) ? $properties[$key] : 'color';
    $declaration = safecss_filter_attr("$property: $value");
    // Older WordPress CSS filters reject even simple rgb()/rgba() colours.
    if ($declaration === '' && $property === 'color' && preg_match('/^rgba?\(\s*\d+(?:\.\d+)?%?\s*,\s*\d+(?:\.\d+)?%?\s*,\s*\d+(?:\.\d+)?%?(?:\s*,\s*(?:\d*\.)?\d+%?)?\s*\)$/iD', $value)) {
        $declaration = "$property: $value";
    }
    if ($declaration !== '' && !preg_match('/[<>{}]/', $declaration)) {
        $styleStr .= $key . substr($declaration, strpos($declaration, ':')) . ';';
    }
}

if ($styleStr !== '') {
    // Only constrained, filtered CSS declarations reach this style element.
    echo '<style>#tsml-ui {' . $styleStr . '}</style>';
}
?>

<div <?php echo wp_kses_data(get_block_wrapper_attributes()); ?>>
    <?php echo do_shortcode('[tsml_ui]'); ?>
</div>