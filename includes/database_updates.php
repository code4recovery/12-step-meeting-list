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
        $updated_address_cache[$key] = $entry;
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

// Function: db_update_set_attendance_options
// This function looks at the types list, and determines what the attendance options should be
if (!function_exists('db_update_set_attendance_options')) {
  function db_update_set_attendance_options () {
    // Get all the meetings
    $meetings = tsml_get_meetings(['post_status' => ['publish', 'future', 'draft', 'pending', 'private', 'trash', 'auto-draft', 'inherit']], false);
    foreach ($meetings as $meeting) {
      // Only make changes if we don't already have something in attendance_option
      if (empty($meeting['attendance_option'])) {
        // Handle when the types list is empty, this prevents PHP warnings
        if (empty($meeting['types'])) $meeting['types'] = array();

        if (in_array('TC', $meeting['types']) && in_array('ONL', $meeting['types'])) {
          // Types has both Location Temporarily Closed and Online, which means it should be an online meeting
          $meeting['attendance_option'] = 'online';
        } elseif (in_array('TC', $meeting['types'])) {
          // Types has Location Temporarily Closed, but not online, which means it really is temporarily closed
          $meeting['attendance_option'] = 'inactive';
        } elseif (in_array('ONL', $meeting['types'])) {
          // Types has Online, but not Temp closed, which means it's a hybrid
          $meeting['attendance_option'] = 'hybrid';
        } else {
          // Neither Online or Temp Closed, which means it's in person
          $meeting['attendance_option'] = 'in_person';
        }

        // Write the option to the database
        update_post_meta($meeting['id'], 'attendance_option', $meeting['attendance_option']);
      } elseif ($meeting['attendance_option'] == 'temporarily_closed') {
        update_post_meta($meeting['id'], 'attendance_option', 'inactive');
      }
    }
    tsml_cache_rebuild();
  }
}
