<?php
/**
 * Generate SQL to normalize data sources
 * 
 * This script outputs SQL UPDATE statements you can run directly in phpMyAdmin
 * 
 * Usage:
 * 1. Update the database connection details below
 * 2. Run: php generate-normalize-sql.php
 * 3. Copy the output SQL and run it in phpMyAdmin
 */

// Database connection (update these for your environment)
$db_host = 'localhost';
$db_name = 'wp_aavirginia';  // Update this
$db_user = 'your_db_user';   // Update this
$db_pass = 'your_db_pass';   // Update this
$table_prefix = 'vvx_';      // Update this if different

// Connect to database
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
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
    
    // Trim
    $url = trim($url);
    
    // Basic URL validation (don't use esc_url_raw as it's WordPress-specific)
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return $url; // Return as-is if invalid
    }
    
    return $url;
}

// Get current data sources
$stmt = $pdo->prepare("SELECT option_value FROM {$table_prefix}options WHERE option_name = 'tsml_data_sources'");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result || empty($result['option_value'])) {
    die("No data sources found in database.\n");
}

$serialized = $result['option_value'];
$data_sources = unserialize($serialized);

if (!is_array($data_sources)) {
    die("Error: Could not unserialize data sources.\n");
}

echo "-- Normalizing data sources\n";
echo "-- Original count: " . count($data_sources) . "\n\n";

$normalized = [];
$key_mapping = [];
$duplicates_found = [];

// Normalize all keys
foreach ($data_sources as $key => $value) {
    $normalized_key = normalize_url($key);
    
    // Track key changes
    if ($normalized_key !== $key) {
        $key_mapping[$key] = $normalized_key;
        echo "-- Key change: \"$key\" -> \"$normalized_key\"\n";
    }
    
    // Check for duplicates (same normalized key)
    if (isset($normalized[$normalized_key])) {
        $duplicates_found[] = [
            'old_key' => $key,
            'normalized_key' => $normalized_key,
            'existing' => $normalized[$normalized_key],
            'duplicate' => $value
        ];
        
        // Keep the entry with the most recent last_import timestamp
        if (isset($value['last_import']) && isset($normalized[$normalized_key]['last_import'])) {
            if ($value['last_import'] > $normalized[$normalized_key]['last_import']) {
                echo "--   -> Keeping newer duplicate (last_import: {$value['last_import']} vs {$normalized[$normalized_key]['last_import']})\n";
                $normalized[$normalized_key] = $value;
            } else {
                echo "--   -> Keeping existing entry (last_import: {$normalized[$normalized_key]['last_import']} vs {$value['last_import']})\n";
            }
        } elseif (isset($value['last_import'])) {
            echo "--   -> Keeping duplicate with last_import timestamp\n";
            $normalized[$normalized_key] = $value;
        } else {
            echo "--   -> Keeping existing entry (no timestamp on duplicate)\n";
        }
    } else {
        $normalized[$normalized_key] = $value;
    }
}

echo "\n-- Duplicates found: " . count($duplicates_found) . "\n";
echo "-- Keys changed: " . count($key_mapping) . "\n\n";

// Reserialize
$new_serialized = serialize($normalized);

// Escape for SQL
$new_serialized_escaped = $pdo->quote($new_serialized);

// Generate SQL to update the option
echo "-- Update the tsml_data_sources option\n";
echo "UPDATE {$table_prefix}options \n";
echo "SET option_value = $new_serialized_escaped\n";
echo "WHERE option_name = 'tsml_data_sources';\n\n";

// Generate SQL to update meeting meta
if (!empty($key_mapping)) {
    echo "-- Update meeting post meta to use normalized URLs\n";
    foreach ($key_mapping as $old_key => $new_key) {
        $old_key_escaped = $pdo->quote($old_key);
        $new_key_escaped = $pdo->quote($new_key);
        echo "UPDATE {$table_prefix}postmeta \n";
        echo "SET meta_value = $new_key_escaped\n";
        echo "WHERE meta_key = 'data_source' AND meta_value = $old_key_escaped;\n\n";
    }
}

echo "-- Normalization complete!\n";
echo "-- Original count: " . count($data_sources) . "\n";
echo "-- Normalized count: " . count($normalized) . "\n";
echo "-- Duplicates removed: " . count($duplicates_found) . "\n";

