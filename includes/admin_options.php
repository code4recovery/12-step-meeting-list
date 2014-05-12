<?php

add_action('admin_menu', function() {

	//import text file
	add_options_page('Meetings Options', 'Meetings', 'manage_options', 'meetings', function() {
		global $days;

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

	    if ($current == 'homepage') {
		    echo '<h2>Meetings Settings</h2>';
	    	echo 'this page doesn\'t do anything yet.';

	    } elseif ($current == 'import') {
		    echo '<h2>Import Meetings</h2>';

	    	//import meetings from file
			if (!$meetings = file('/Users/joshreisner/Dropbox/intergroup/export.txt')) {
				echo 'Import file does not exist';
			} else {

		    	//delete current data
				meetings_delete_all_meetings();
				meetings_delete_all_locations();
				meetings_delete_all_regions();
				echo 'deleted existing data<br>';

				//run through one time and do basic formatting and duplicate location detection
				$addresses = $regions = array();
				foreach ($meetings as &$meeting) {
					list($id, $day, $time, $title, $location, $address, $city, $state, $region, $types) = explode("\t", $meeting);

					//ad-hoc replacements (san jose specific)
					if (trim(strtolower($location)) == 'moffett central shopping center') {
						$location = 'Freedom Fellowship Group';
					}

					//day
					if (!in_array($day, $days)) die('day ' . $day . ' not valid day for id ' . $id);
					$day = array_search($day, $days);

					//regions
					$region = trim(str_replace(',', ':', $region));
					if (!in_array($region, $regions)) $regions[] = $region;

					//types
					$types = explode(',', $types);					
					if (stristr($title, '(chips)') && !in_array('H', $types)) $types[] = 'H';

					//title
					if ($pos = stripos($title, '(')) $title = substr($title, 0, $pos);
					$title = trim($title);

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
						'types'=>$types,
					);
				}
				echo 'first pass complete<br>';

				//load new regions from regions array
				foreach ($regions as $region) {
					wp_insert_term($region, 'region');
				}
				$regions = meetings_get_regions();
				echo 'regions loaded<br>';

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
							'region'	=>array_search($info['region'], $regions),
							'location'	=>$info['location'],
							'latitude'	=>$data->results[0]->geometry->location->lat,
							'longitude'	=>$data->results[0]->geometry->location->lng,
						);
					}

					//fill empty location title
					if (empty($formatted[$formatted_address]['locaiton']) && !empty($info['location'])) {
						$formatted[$formatted_address]['locaiton'] = $info['location'];
					}

					//attach meetings to existing location
					$formatted[$formatted_address]['meetings'] = array_merge(
						$formatted[$formatted_address]['meetings'],
						$info['meetings']
					);
				}
				echo 'second pass complete<br>';

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

						wp_set_post_terms($meeting_id, $info['region'], 'region');

						echo 'added ' . $meeting['title'] . '<br>';
					}
				}
			    echo '<hr>all done';
			}
	    }
	    
		echo '</div>';
	});
});
