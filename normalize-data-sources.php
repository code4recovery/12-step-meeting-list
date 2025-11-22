<?php
/**
 * One-time script to normalize and deduplicate data sources
 * 
 * Usage:
 * 1. Via WP-CLI: wp eval-file normalize-data-sources.php
 * 2. Via browser: Place this file in WordPress root and access via browser (then delete it!)
 * 3. Via command line: php -r "require 'wp-load.php'; require 'normalize-data-sources.php';"
 */

// Load WordPress
if (!defined('ABSPATH')) {
    require_once dirname(__FILE__) . '/wp-load.php';
}

// Check if user has permission (if running via browser)
if (!is_admin() && php_sapi_name() !== 'cli') {
    die('This script should only be run by administrators or via WP-CLI.');
}

/**
 * Normalize a data source URL
 */
function normalize_url($url) {
    if (empty($url)) {
        return '';
    }
    
    // Decode HTML entities (e.g., &amp; -> &)
    $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Remove double slashes in the path (but preserve ://)
    $url = preg_replace('#(?<!:)/{2,}#', '/', $url);
    
    // Trim and sanitize
    $url = trim($url);
    $url = esc_url_raw($url, ['http', 'https']);
    
    return $url;
}

// Get current data sources
$data_sources = get_option('tsml_data_sources', []);

if (empty($data_sources) || !is_array($data_sources)) {
    echo "No data sources found.\n";
    exit;
}

echo "Found " . count($data_sources) . " data source(s).\n\n";

$normalized = [];
$key_mapping = [];
$duplicates_found = [];
$updated = false;

// Normalize all keys
foreach ($data_sources as $key => $value) {
    $normalized_key = normalize_url($key);
    
    // Track key changes
    if ($normalized_key !== $key) {
        $key_mapping[$key] = $normalized_key;
        $updated = true;
        echo "Key changed: \"$key\" -> \"$normalized_key\"\n";
    }
    
    // Check for duplicates (same normalized key)
    if (isset($normalized[$normalized_key])) {
        $duplicates_found[] = [
            'old_key' => $key,
            'normalized_key' => $normalized_key,
            'existing' => $normalized[$normalized_key],
            'duplicate' => $value
        ];
        $updated = true;
        
        // Keep the entry with the most recent last_import timestamp
        if (isset($value['last_import']) && isset($normalized[$normalized_key]['last_import'])) {
            if ($value['last_import'] > $normalized[$normalized_key]['last_import']) {
                echo "  -> Keeping newer duplicate (last_import: {$value['last_import']} vs {$normalized[$normalized_key]['last_import']})\n";
                $normalized[$normalized_key] = $value;
            } else {
                echo "  -> Keeping existing entry (last_import: {$normalized[$normalized_key]['last_import']} vs {$value['last_import']})\n";
            }
        } elseif (isset($value['last_import'])) {
            echo "  -> Keeping duplicate with last_import timestamp\n";
            $normalized[$normalized_key] = $value;
        } else {
            echo "  -> Keeping existing entry (no timestamp on duplicate)\n";
        }
    } else {
        $normalized[$normalized_key] = $value;
    }
}

if (!$updated) {
    echo "\nNo changes needed - all data sources are already normalized.\n";
    exit;
}

echo "\n" . count($duplicates_found) . " duplicate(s) found.\n";
echo count($key_mapping) . " key(s) need normalization.\n\n";

// Update meeting meta for changed keys
global $wpdb;
$meetings_updated = 0;

if (!empty($key_mapping)) {
    foreach ($key_mapping as $old_key => $new_key) {
        $updated_count = $wpdb->update(
            $wpdb->postmeta,
            ['meta_value' => $new_key],
            [
                'meta_key' => 'data_source',
                'meta_value' => $old_key
            ]
        );
        
        if ($updated_count > 0) {
            $meetings_updated += $updated_count;
            echo "Updated $updated_count meeting(s) from \"$old_key\" to \"$new_key\"\n";
        }
    }
}

// Save normalized data sources
update_option('tsml_data_sources', $normalized);

echo "\n✓ Normalization complete!\n";
echo "  - Original count: " . count($data_sources) . "\n";
echo "  - Normalized count: " . count($normalized) . "\n";
echo "  - Duplicates removed: " . count($duplicates_found) . "\n";
echo "  - Meetings updated: $meetings_updated\n";

