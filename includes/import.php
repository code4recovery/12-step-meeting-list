<?php
//currently not in use

//tabs
$current = (empty($_GET['tab']) ? 'homepage' : $_GET['tab']);
$tabs	= array('homepage'=>'General Info', 'import'=>'Import Meetings', 'get-involved'=>'Get Involved');
echo '<h2 class="nav-tab-wrapper">';
foreach( $tabs as $tab => $name ){
    $class = ( $tab == $current ) ? ' nav-tab-active' : '';
    echo '<a class="nav-tab' . $class . '" href="?page=meetings&tab=' . $tab . '">' . $name . '</a>';

}
echo '</h2>';

echo '<h2>Import Meetings</h2>';

//import meetings from file
if (!$meetings = file(dirname(dirname(__FILE__)) . '/export.txt')) {
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
		list($id, $day, $time, $title, $location, $address, $city, $state, $region, $types, $notes) = explode("\t", $meeting);

		//ad-hoc replacements (san jose specific)
		if (trim(strtolower($location)) == 'moffett central shopping center') {
			$location = 'Freedom Fellowship Group';
		}

		if (trim(strtolower($location)) == 'saturday nite live group') {
			$address = '2634 Union Ave';
		}

		if (trim(strtolower($location)) == 'panara bread') {
			$location = 'Panera Bread';
		}

		if (trim(strtolower($location)) == 'forged from adversity group') {
			$address = '1025 The Dalles Ave';
		}

		if (trim(strtolower($location)) == 'south county fellowship') {
			$address = '17666 Crest Ave';
		}

		if (($id == 27) || ($id == 43)) {
			$address = '1040 Border Rd';
		} elseif ($id == 178) {
			$location = 'Stanford Work Life Center Bldg';
			$address = '845 Escondido Road';
		} elseif ($id == 181) {
			$location = 'Mental Health Services';
			$address = '231 Grant Ave';
		} elseif ($id == 423) {
			$location = 'Mt. Olive Lutheran Church';
			$address = '1989 E Calaveras Blvd';
		} elseif ($id == 460) {
			$location = 'Knights of Columbus';
			$address = '2211 Shamrock Dr';
		} elseif ($id == 627) {
			$location = 'Escondido Administration Building, Cottage Room';
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
		$title = str_replace('/', ' / ', $title);
		$title = str_replace('  ', ' ', $title);
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
			'id'=>$id,
			'title'=>$title,
			'day'=>$day,
			'time'=>$time,
			'types'=>$types,
			'notes'=>$notes,
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

		//interpreting result
		$data = json_decode($file);
		/*echo '<pre>';
		print_r($file);
		exit;*/

		$formatted_address = $data->results[0]->formatted_address;
		$address = $city = $state = false;
		foreach ($data->results[0]->address_components as $component) {
			if (in_array('street_number', $component->types)) {
				$address = $component->long_name;
			} elseif (in_array('route', $component->types)) {
				$address .= ' ' . $component->long_name;
			} elseif (in_array('locality', $component->types)) {
				$city = $component->long_name;
			} elseif (in_array('administrative_area_level_1', $component->types)) {
				$state = $component->short_name;
			} elseif (in_array('point_of_interest', $component->types)) {
				//remove point of interest, eg Sunnyvale Presbyterian Church, from address
				$needle = $component->long_name . ', ';
				if (substr($formatted_address, 0, strlen($needle)) == $needle) {
					$formatted_address = substr($formatted_address, strlen($needle));
				}
			}
		}

		//empty location names default to (clean) street addresses
		if (empty($info['location'])) $info['location'] = $address;

		if (!array_key_exists($formatted_address, $formatted)) {
			//intialize empty location
			$formatted[$formatted_address] = array(
				'meetings'	=>array(),
				'address'	=>$address,
				'city'		=>$city,
				'state'		=>$state,
				'region'	=>array_search($info['region'], $regions),
				'location'	=>$info['location'],
				'latitude'	=>$data->results[0]->geometry->location->lat,
				'longitude'	=>$data->results[0]->geometry->location->lng,
			);
		}

		//echo '<pre>';
		//print_r($formatted[$formatted_address]);
		//exit;

		//fill empty location title
		if (empty($formatted[$formatted_address]['location']) && !empty($info['location'])) {
			$formatted[$formatted_address]['location'] = $info['location'];
		}

		//attach meetings to existing location
		$formatted[$formatted_address]['meetings'] = array_merge(
			$formatted[$formatted_address]['meetings'],
			$info['meetings']
		);
	}
	echo 'second pass complete<br>';

	//loop through now and save everything to the database
	foreach ($formatted as $formatted_address=>$info) {

		//save location
		$location_id = wp_insert_post(array(
			'post_title'	=> $info['location'],
			'post_type'		=> 'locations',
			'post_status'	=> 'publish',
		));
		update_post_meta($location_id, 'formatted_address',	$formatted_address);
		update_post_meta($location_id, 'address',			$info['address']);
		update_post_meta($location_id, 'city',				$info['city']);
		update_post_meta($location_id, 'state',				$info['state']);
		update_post_meta($location_id, 'latitude',			$info['latitude']);
		update_post_meta($location_id, 'longitude',			$info['longitude']);
		update_post_meta($location_id, 'region',			$info['region']);

		//save meetings to this location
		foreach ($info['meetings'] as $meeting) {
			$meeting_id = wp_insert_post(array(
				'post_title'	=> $meeting['title'],
				'post_type'		=> 'meetings',
				'post_status'	=> 'publish',
				'post_parent'	=> $location_id,
				'post_content'	=> trim($meeting['notes']),
			));
			update_post_meta($meeting_id, 'day',		$meeting['day']);
			update_post_meta($meeting_id, 'time',		$meeting['time']);
			update_post_meta($meeting_id, 'types',		$meeting['types']);
			update_post_meta($meeting_id, 'region',		$info['region']); //double-entry just for searching

			wp_set_post_terms($meeting_id, $regions[$info['region']], 'region');

			echo 'added ' . $meeting['title'] . '<br>';
		}
	}
    echo '<hr>all done';
}