<?php

//function for shortcode
function tsml_count_groups() {
	return number_format(count(tsml_count_groups()));
}
add_shortcode('tsml_group_count', 'tsml_group_count');

//function for shortcode
function tsml_count_locations() {
	return number_format(count(tsml_get_all_locations('publish')));
}
add_shortcode('tsml_location_count', 'tsml_count_locations');

//function for shortcode
function tsml_count_meetings() {
	return number_format(count(tsml_get_all_meetings('publish')));
}
add_shortcode('tsml_meeting_count', 'tsml_count_meetings');

//function for shortcode
function tsml_count_regions() {
	return number_format(count(tsml_get_all_regions()));
}
add_shortcode('tsml_region_count', 'tsml_count_regions');

//function for shortcode: get a table of the next $count meetings
function tsml_next_meetings($arguments) {
	$arguments = shortcode_atts(array(
		'count' => 5,
	), $arguments, 'tsml_next_meetings');
	$meetings = tsml_get_meetings();
	usort($meetings, 'tsml_next_meetings_sort');
	$meetings = array_slice($meetings, 0, $arguments['count']);
	$rows = '';
	foreach ($meetings as $meeting) {
		if (in_array('M', $meeting['types'])) {
			$meeting['name'] .= '<small>' . __('Men') . '</small>';
		} elseif (in_array('W', $meeting['types'])) {
			$meeting['name'] .= '<small>' . __('Women') . '</small>';
		}
		$rows .= '<tr>
			<td class="time">' . tsml_format_time($meeting['time']) . '</td>
			<td class="name"><a href="' . $meeting['url'] . '">' . $meeting['name'] . '</a></td>
			<td class="location">' . $meeting['location'] . '</td>
			<td class="region">' . ($meeting['sub_region'] ? $meeting['sub_region'] : $meeting['region']) . '</td>
		</tr>';
	}
	return '<table class="tsml_next_meetings table table-striped">
		<thead>
			<tr>
				<th class="time">' . __('Time') . '</td>
				<th class="name">' . __('Meeting') . '</td>
				<th class="location">' . __('Location') . '</td>
				<th class="region">' . __('Region') . '</td>
			</tr>
		</thead>
		<tbody>' . $rows . '</tbody>
	</table>';
}
add_shortcode('tsml_next_meetings', 'tsml_next_meetings');

//function:	usort for next meetings
//used:		tsml_next_meetings()
function tsml_next_meetings_sort($a, $b) {
	$today = current_time('w');
	$time = current_time('H:i');

	//increment day to be 'next week' if earlier than now
	if ($a['day'] < $today || ($a['day'] == $today && $a['time'] < $time)) $a['day'] += 7;
	if ($b['day'] < $today || ($b['day'] == $today && $b['time'] < $time)) $b['day'] += 7;
	
	//return standard compare	
	return tsml_sort_meetings($a, $b);
}
