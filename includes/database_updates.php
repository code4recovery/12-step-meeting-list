<?php

function tsml_db_update_remove_all_approximate_location_cache()
{
  $addresses_cache = get_option('tsml_addresses');
  $updated_address_cache = [];
  if (!empty($addresses_cache)) {
    foreach ($addresses_cache as $key => $entry) {
      if (array_key_exists('is_approximate_location', $entry)) {
        unset($entry['is_approximate_location']);
      }
      $updated_address_cache[$key] = $entry;
    }
    update_option('tsml_addresses', $updated_address_cache);
  }
}

function tsml_db_update_remove_all_is_approximate_location_meta()
{
  $location_posts = tsml_get_all_locations();
  foreach ($location_posts as $location_post) {
    $location_custom = get_post_meta($location_post->ID);
    if (array_key_exists('is_approximate_location', $location_custom)) {
      delete_post_meta($location_post->ID, 'is_approximate_location');
    }
  }
}

// May want do a geocode sometime in a later version of the plugin
function tsml_db_set_location_approximate()
{
  $locations = tsml_get_locations();
  $addresses_cache = get_option('tsml_addresses');
  $tmp_cache = [];

  // Remove addresses from cache if approximate is not set
  foreach ($addresses_cache as $key => $address) {
    if (!empty($address['approximate'])) {
      $tmp_cache[$key] = $address;
    }
  }
  $addresses_cache = $tmp_cache;
  update_option('tsml_addresses', $addresses_cache);

  foreach ($locations as $location) {
    // if location doesn't have the approximate tag
    if (empty($location['approximate'])) {
      $tmp_address = $location['formatted_address'];

      // if the cached address has the approximate tag
      if (!empty($addresses_cache[$tmp_address]['approximate'])) {
        // Location in Database doesn't have approximate, and it's in the address cache, so write it to the database
        update_post_meta($location['location_id'], 'approximate', $addresses_cache[$tmp_address]['approximate']);
      }
    }
  }
}
