<?php

add_action('admin_menu', function() {

	//settings
	register_setting('meetings_group_a', 'hllo_name', 'intval'); 

	//import text file
	add_options_page('Meetings Options', 'Meetings', 'manage_options', 'meetings', function() {
		global $days;

		meetings_delete_all_meetings();
		meetings_delete_all_locations();

		echo '<div class="wrap">';

		//tabs
		$current = (empty($_GET['tab']) ? 'homepage' : $_GET['tab']);
	    $tabs	= array('homepage'=>'General Info', 'import'=>'Import Meetings', 'get-involved'=>'Get Involved');
	    echo '<h2 class="nav-tab-wrapper">';
	    foreach( $tabs as $tab => $name ){
	        $class = ( $tab == $current ) ? ' nav-tab-active' : '';
	        echo '<a class="nav-tab' . $class . '" href="?page=meetings&tab=' . $tab . '">' . $name . '</a>';

	    }
	    echo '</h2>';

	    echo '<h2>Meetings Settings</h2>';

	    if ($current == 'homepage') {

	    } elseif ($current == 'import') {

	    	//import meetings from file
			if (!$meetings = file('/Users/joshreisner/Dropbox/intergroup/export.txt')) {
				echo 'Import file does not exist';
			} else {

				//run through one time and do basic formatting and duplicate location detection
				$addresses = array();
				foreach ($meetings as &$meeting) {
					list($id, $day, $time, $title, $location, $address, $city, $state, $region, $codes) = explode("\t", $meeting);

					//day
					if (!in_array($day, $days)) die('day ' . $day . ' not valid day for id ' . $id);
					$day = array_search($day, $days);

					//title
					$title = trim($title);

					//codes
					$codes = explode(',', $codes);					
					if (stristr($address, '(chips)') && !in_array('H', $codes)) $codes[] = 'H';

					//address						
					$address = str_replace('.', '', $address);
					if ($pos = stripos($address, '(')) $address = substr($address, 0, $pos);
					if ($pos = stripos($address, '&')) $address = substr($address, 0, $pos);
					if ($pos = stripos($address, '@')) $address = substr($address, 0, $pos);
					if ($pos = stripos($address, '#')) $address = substr($address, 0, $pos);
					if ($pos = stripos($address, ' at ')) $address = substr($address, 0, $pos);
					$address = trim($address);
					$address .= ', ' . $city . ', CA';

					//location
					$location = trim($location);

					if (!array_key_exists($address, $addresses)) {
						$addresses[$address] = array(
							'meetings'=>array(),
							'region'=>$region,
							'location'=>$location,
						);
					}

					$addresses[$address]['meetings'][] = array(
						'title'=>$title,
						'day'=>$day,
						'time'=>$time,
						'codes'=>$codes,
					);

				}

				//run through again to verify addresses, do final duplicate location detection
				$formatted = array();
				foreach ($addresses as $address=>$info) {
					if (!$file = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&sensor=true&key=AIzaSyD_pInq9og_PMQfeIDUeIHTCVPbSN-2t-A')) {
						die('could not get json for address ' . $address);
					}
					$data = json_decode($file);
					$formatted_address = $data->results[0]->formatted_address;
					if (!array_key_exists($formatted_address, $formatted)) {
						//intialize empty location
						$formatted[$formatted_address] = array(
							'meetings'	=>array(),
							'region'	=>$info['region'],
							'location'	=>$info['location'],
							'latitude'	=>$data->results[0]->geometry->location->lat,
							'longitude'	=>$data->results[0]->geometry->location->lng,
						);
					}

					//attach meetings to existing location
					$formatted[$formatted_address]['meetings'] = array_merge(
						$formatted[$formatted_address]['meetings'],
						$info['meetings']
					);

					//echo '<pre>';
					//print_r($formatted[$formatted_address]['meetings']);
					//exit;
					

				}

				//loop through now and save everything to the database
				foreach ($formatted as $address=>$info) {

					//save location
					$location_id = wp_insert_post(array(
						'post_title'	=> $info['location'],
						'post_type'		=> 'locations',
						'post_status'	=> 'publish',
						'post_author'	=> 1,
					));
					update_post_meta($location_id, 'address',	$address);
					update_post_meta($location_id, 'latitude',	$info['latitude']);
					update_post_meta($location_id, 'longitude',	$info['longitude']);
					update_post_meta($location_id, 'region',	$info['region']);

					//save meetings to this location
					foreach ($info['meetings'] as $meeting) {
						$meeting_id = wp_insert_post(array(
							'post_title'	=> $meeting['title'],
							'post_type'		=> 'meetings',
							'post_status'	=> 'publish',
							'post_author'	=> 1,
						));
						update_post_meta($meeting_id, 'day',		$meeting['day']);
						update_post_meta($meeting_id, 'time',		$meeting['time']);
						update_post_meta($meeting_id, 'types',		$meeting['types']);

						update_post_meta($meeting_id, 'location_id',$location_id);
						update_post_meta($meeting_id, 'location',	$info['location']);
						update_post_meta($meeting_id, 'address',	$address);
						update_post_meta($meeting_id, 'latitude',	$info['latitude']);
						update_post_meta($meeting_id, 'longitude',	$info['longitude']);
						update_post_meta($meeting_id, 'region',		$info['region']);
					}
				}
			}
	    }

	    echo 'all done';
	    
		echo '</div>';
	});
});
