<?php

add_action('admin_menu', function() {

	//settings
	register_setting('meetings_group_a', 'hllo_name', 'intval'); 

	//import text file
	add_options_page('Meetings Options', 'Meetings', 'manage_options', 'meetings', function() {
		global $days;

		meetings_delete_all_meetings();
		meetings_delete_all_locations();

		echo '<div class="wrap"><h2>Meetings Settings</h2>';
		$addresses = array();
		$meetings = file('/Users/joshreisner/Dropbox/intergroup/export.txt');
		foreach ($meetings as $meeting) {
			list($id, $day, $time, $title, $location, $address, $city, $state, $region, $codes) = explode("\t", $meeting);

			//day
			if (!in_array($day, $days)) die('day ' . $day . ' not valid day for id ' . $id);
			$day = array_search($day, $days);

			//title
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

			//codes
			$codes = explode(',', $codes);

			//location
			$location = trim($location);

			$addresses[] = $address;
			/*
			echo 'day: ' . $day . '<br>';
			echo 'title: ' . $title . '<br>';
			echo 'address: ' . $address . '<br>';
			echo 'codes: ' . $codes . '<br>';
			echo 'location: ' . $location . '<br>';
			echo 'hours: ' . $time . '<br><br>';
			*/
		}
		$addresses = array_unique($addresses);
		sort($addresses);
		echo implode('<br>', $addresses);

		echo '</div>';
	});
});
