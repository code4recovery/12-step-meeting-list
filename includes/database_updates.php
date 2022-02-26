<?php

function tsml_db_update_remove_all_approximate_location_cache()
{
  $addresses_cache = get_option('tsml_addresses');
  $updated_address_cache = [];
  if (!empty($addresses_cache)) {
    foreach ($addresses_cache as $key => $entry) {
      if (isset($entry['is_approximate_location'])) {
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
    if (isset($location_custom['is_approximate_location'])) {
      delete_post_meta($location_post->ID, 'is_approximate_location');
    }
  }
}

// May want do a geocode sometime in a later version of the plugin
function tsml_db_set_location_approximate()
{
  $locations = tsml_get_locations();
  $addresses = get_option('tsml_addresses', []);

  // Remove addresses from cache if approximate or formatted_address is not set
  $addresses = array_filter($addresses, function ($address) {
    return !empty($address['approximate']) && !empty($address['formatted_address']);
  });

  update_option('tsml_addresses', $addresses);

  foreach ($locations as $location) {
    // if location doesn't have the approximate tag and cached address does
    if (empty($location['approximate']) && !empty($addresses_cache[$location['formatted_address']]['approximate'])) {
      // Location in Database doesn't have approximate, and it's in the address cache, so write it to the database
      update_post_meta($location['location_id'], 'approximate', $addresses[$location['formatted_address']]['approximate']);
    }
  }
}
