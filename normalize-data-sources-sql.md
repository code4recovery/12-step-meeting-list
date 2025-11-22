# SQL Approach to Normalize Data Sources

**Warning**: The `tsml_data_sources` option is stored as a serialized PHP array, which makes direct SQL updates complex and error-prone. The PHP script approach is recommended.

However, if you want to do it manually with SQL, here's how:

## Step 1: Export the Current Value

```sql
SELECT option_value 
FROM wp_options 
WHERE option_name = 'tsml_data_sources';
```

## Step 2: Normalize the URLs

The serialized data needs to be unserialized, normalized, and reserialized. Here's a PHP snippet you can run to generate the normalized SQL:

```php
<?php
// Get the serialized value from your database
$serialized = 'a:15:{s:61:"http://vadist15aa.org/wp-admin/admin-ajax.php?action=meetings";...}';

// Unserialize
$data_sources = unserialize($serialized);

// Normalize function
function normalize_url($url) {
    $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $url = preg_replace('#(?<!:)/{2,}#', '/', $url);
    $url = trim($url);
    $url = esc_url_raw($url, ['http', 'https']);
    return $url;
}

// Normalize keys
$normalized = [];
foreach ($data_sources as $key => $value) {
    $normalized_key = normalize_url($key);
    if (isset($normalized[$normalized_key])) {
        // Keep the one with the most recent last_import
        if (isset($value['last_import']) && isset($normalized[$normalized_key]['last_import'])) {
            if ($value['last_import'] > $normalized[$normalized_key]['last_import']) {
                $normalized[$normalized_key] = $value;
            }
        }
    } else {
        $normalized[$normalized_key] = $value;
    }
}

// Reserialize
$new_serialized = serialize($normalized);

// Output SQL
echo "UPDATE wp_options SET option_value = " . $wpdb->prepare('%s', $new_serialized) . " WHERE option_name = 'tsml_data_sources';";
```

## Step 3: Update Meeting Meta

After normalizing the data sources, you'll also need to update the meeting post meta:

```sql
-- Example: Update meetings that reference old URL format
-- Replace 'old_url' and 'new_url' with actual values

UPDATE wp_postmeta 
SET meta_value = 'new_normalized_url' 
WHERE meta_key = 'data_source' 
AND meta_value = 'old_url_with_ampersand';
```

## Recommended Approach

Instead of manual SQL, use the provided `normalize-data-sources.php` script:

```bash
# Via WP-CLI (recommended)
wp eval-file normalize-data-sources.php

# Or place it in WordPress root and access via browser once
# Then delete it immediately after running
```

This is safer because it:
- Handles serialization correctly
- Updates meeting meta automatically
- Shows you what changes will be made
- Prevents data corruption

