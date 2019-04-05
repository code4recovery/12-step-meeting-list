<?php

//make shortcodes from functions in functions.php
add_shortcode('tsml_group_count', 'tsml_group_count');
add_shortcode('tsml_location_count', 'tsml_count_locations');
add_shortcode('tsml_meeting_count', 'tsml_count_meetings');
add_shortcode('tsml_region_count', 'tsml_count_regions'); 

//function for shortcode: get a table of the next $count meetings
if (!function_exists('tsml_next_meetings')) {
	function tsml_next_meetings($arguments) {
		$arguments = shortcode_atts(array('count' => 5), $arguments, 'tsml_next_meetings');
		$meetings = tsml_get_meetings(array('day' => intval(current_time('w')), 'time' => 'upcoming'));
		if (!count($meetings)) return false;
		//usort($meetings, 'tsml_next_meetings_sort');
		$meetings = array_slice($meetings, 0, $arguments['count']);
		$rows = '';
		foreach ($meetings as $meeting) {
			if (is_array($meeting['types'])) {
				if (in_array('M', $meeting['types'])) {
					$meeting['name'] .= '<small>' . __('Men', '12-step-meeting-list') . '</small>';
				} elseif (in_array('W', $meeting['types'])) {
					$meeting['name'] .= '<small>' . __('Women', '12-step-meeting-list') . '</small>';
				}
			}
			$rows .= '<tr>
				<td class="time">' . tsml_format_time($meeting['time']) . '</td>
				<td class="name"><a href="' . $meeting['url'] . '">' . @$meeting['name'] . '</a></td>
				<td class="location">' . @$meeting['location'] . '</td>
				<td class="region">' . (@$meeting['sub_region'] ? @$meeting['sub_region'] : @$meeting['region']) . '</td>
			</tr>';
		}
		return '<table class="tsml_next_meetings table table-striped">
			<thead>
				<tr>
					<th class="time">' . __('Time', '12-step-meeting-list') . '</th>
					<th class="name">' . __('Meeting', '12-step-meeting-list') . '</th>
					<th class="location">' . __('Location', '12-step-meeting-list') . '</th>
					<th class="region">' . __('Region', '12-step-meeting-list') . '</th>
				</tr>
			</thead>
			<tbody>' . $rows . '</tbody>
		</table>';
	}	
}
add_shortcode('tsml_next_meetings', 'tsml_next_meetings');

//output a list of types with links for AA-DC
if (!function_exists('tsml_types_list')) {
	function tsml_types_list() {
		global $tsml_types_in_use, $tsml_programs, $tsml_program;
		$types = array();
		$base = get_post_type_archive_link('tsml_meeting') . '?tsml-day=any&tsml-type=';
		foreach ($tsml_types_in_use as $type) {
			$types[$tsml_programs[$tsml_program]['types'][$type]] = '<li><a href="' . $base . $type . '">' . $tsml_programs[$tsml_program]['types'][$type] . '</a></li>';
		}
		ksort($types);
		return '<h3>Types</h3><ul>' . implode($types) . '</ul>';
	}
}
add_shortcode('tsml_types_list', 'tsml_types_list');

//output a list of regions with links for AA-DC
if (!function_exists('tsml_regions_list')) {
	function tsml_regions_list() {
		//run function recursively
		function get_regions($parent=0) {
			$taxonomy = 'tsml_region';
			$terms = get_terms(compact('taxonomy', 'parent'));
			if (!count($terms)) return;
			$base = get_post_type_archive_link('tsml_meeting') . '?tsml-day=any&tsml-region=';
			foreach ($terms as &$term) {
				$term = '<li><a href="' . $base . $term->term_id . '">' . $term->name . '</a>' . get_regions($term->term_id) . '</li>';
			}
			return '<ul>' . implode($terms) . '</ul>';
		}

		return '<h3>Regions</h3>' . get_regions();
	}
}
add_shortcode('tsml_regions_list', 'tsml_regions_list');
