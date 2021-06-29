<?php 

if(!function_exists('decide_if_location_approximate')) {
  function decide_if_location_approximate($formatted_address) {
    if (substr_compare($formatted_address, 'Australia', -9, $case_insensitivity = TRUE) === 0) {
      $location_approximate = substr_count($formatted_address, ",") <= 1;
    } else $location_approximate = substr_count($formatted_address, ",") <= 2;
    return $location_approximate;
  }
}

// Function: db_update_remove_all_approximate_location_cache
if (!function_exists('db_update_remove_all_approximate_location_cache')) {
	function db_update_remove_all_approximate_location_cache() {
    $addresses_cache = get_option('tsml_addresses');
    $updated_address_cache = array();
		if (!empty($addresses_cache)) {
			foreach ( $addresses_cache as $key => $entry ) {
        $address = $entry['formatted_address'];
				if (array_key_exists('is_approximate_location', $entry)) {
          unset($entry['is_approximate_location']);
        };
        $updated_address_cache[$key] = $entry;
      };
      update_option('tsml_addresses', $updated_address_cache);
    };
  };
};

// Function: db_update_remove_all_is_approximate_location_meta
if (!function_exists('db_update_remove_all_is_approximate_location_meta')) {
  function db_update_remove_all_is_approximate_location_meta() {
    $location_posts = tsml_get_all_locations();
    foreach ($location_posts as $location_post) {
      $location_custom = get_post_meta($location_post->ID);
      if (array_key_exists('is_approximate_location', $location_custom)) {
        delete_post_meta($location_post->ID, 'is_approximate_location');
      };
    };
  };
};
