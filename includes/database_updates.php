<?php 

if(!function_exists('decide_if_location_approximate')) {
  function decide_if_location_approximate($formatted_address) {
    if (substr_compare($formatted_address, 'Australia', -9, $case_insensitivity = TRUE) === 0) {
      $location_approximate = substr_count($formatted_address, ",") <= 1;
    } else $location_approximate = substr_count($formatted_address, ",") <= 2;
    return $location_approximate;
  }
}

// Function: db_update_addresses_cache_approximate_location
if (!function_exists('db_update_addresses_cache_approximate_location')) {
	function db_update_addresses_cache_approximate_location() {
    $addresses_cache = get_option('tsml_addresses');
    $updated_address_cache = array();
		if (!empty($addresses_cache)) {
			foreach ( $addresses_cache as $key => $entry ) {
        $address = $entry['formatted_address'];
				if (!array_key_exists('is_approximate_location', $entry)) {
          $entry['is_approximate_location'] = decide_if_location_approximate($address);					
        };
        $updated_address_cache[$address] = $entry;
      };
      update_option('tsml_addresses', $updated_address_cache);
    };
  };
};

if (!function_exists('db_update_tsml_locations_approximate_location')) {
  function db_update_tsml_locations_approximate_location() {
    $locations_posts = tsml_get_all_locations();
    foreach ($locations_posts as $location_post) {
      $location_custom = get_post_meta($location_post->ID);
      $is_location_approximate = decide_if_location_approximate($location_custom['formatted_address'][0]);
      update_post_meta($location_post->ID, 'is_approximate_location', $is_location_approximate);				
    }
  };
};