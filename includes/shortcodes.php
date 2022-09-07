<?php

//make shortcodes from functions in functions.php
add_shortcode('tsml_group_count', 'tsml_count_groups');
add_shortcode('tsml_location_count', 'tsml_count_locations');
add_shortcode('tsml_meeting_count', 'tsml_count_meetings');
add_shortcode('tsml_region_count', 'tsml_count_regions');

//function for shortcode: get a table of the next $count meetings
//used here and in widgets.php
function tsml_next_meetings($arguments)
{
	global $tsml_meeting_attendance_options;
	$arguments = shortcode_atts(['count' => 5, 'message' => ''], $arguments, 'tsml_next_meetings');
	$meetings = tsml_get_meetings([
		'day' => intval(current_time('w')),
		'time' => 'upcoming',
		'attendance_option' => 'active',
	]);
	if (!count($meetings) && empty($arguments['message'])) {
		return false;
	}
	if (!count($meetings) && !empty($arguments['message'])) {
		return '<div class="tsml-no-upcoming-meetings">' . $arguments['message'] . '</div>';
	}

	$meetings = array_slice($meetings, 0, $arguments['count']);
	$rows = '';
	foreach ($meetings as $meeting) {
		$classes = tsml_to_css_classes($meeting['types']);

		if (!empty($meeting['notes'])) {
			$classes .= ' notes';
		}

		$meeting_types = tsml_format_types($meeting['types']);
		if (!empty($meeting_types)) {
			$meeting_types = ' <small><span class="meeting_types">' . $meeting_types . '</span></small>';
		}

		$meeting_location = $meeting['location'];
		if ($meeting['attendance_option'] == 'online' || $meeting['attendance_option'] == 'inactive') {
			$meeting_location = !empty($meeting['group']) ? $meeting['group'] : '';
		}

		$region = '';
		if (!empty($meeting['sub_region'])) {
			$region = $meeting['sub_region'];
		} elseif (!empty($meeting['region'])) {
			$region = $meeting['region'];
		}

		$rows .= '<tr class="meeting ' . $classes . ' attendance-' . $meeting['attendance_option'] . '">
				<td class="time">' . tsml_format_time($meeting['time']) . '</td>
				<td class="name"><a href="' . $meeting['url'] . '">' . @$meeting['name'] . '</a>' . $meeting_types . '</td>
				<td class="location">
					<div class="location-name">' . $meeting_location . '</div>
					<div class="attendance-option attendance-' . $meeting['attendance_option'] . '"><small>' . ($meeting['attendance_option'] != 'in_person' ? $tsml_meeting_attendance_options[$meeting['attendance_option']] : '')  . '</small></div>
				</td>
				<td class="region">' . $region . '</td>
			</tr>';
	}
	return '
	<style>
		table.tsml_next_meetings div.attendance-hybrid small,
		table.tsml_next_meetings div.attendance-online small {
			color: green;
		}

		table.tsml_next_meetings div.attendance-inactive small {
			color: #d40047;
		}
	</style>
	<table class="tsml_next_meetings table table-striped">
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
add_shortcode('tsml_next_meetings', 'tsml_next_meetings');

//output a list of types with links for AA-DC

add_shortcode('tsml_types_list', function () {
	global $tsml_types_in_use, $tsml_programs, $tsml_program;
	$types = [];
	$base = get_post_type_archive_link('tsml_meeting') . '?tsml-day=any&tsml-type=';
	foreach ($tsml_types_in_use as $type) {
		$types[$tsml_programs[$tsml_program]['types'][$type]] = '<li><a href="' . $base . $type . '">' . $tsml_programs[$tsml_program]['types'][$type] . '</a></li>';
	}
	ksort($types);
	return '<h3>Types</h3><ul>' . implode($types) . '</ul>';
});

//output a react meeting finder widget https://github.com/code4recovery/tsml-ui
function tsml_ui()
{
	global $tsml_mapbox_key, $tsml_nonce, $tsml_conference_providers, $tsml_language, $tsml_programs, $tsml_program, $tsml_ui_config,
		$tsml_feedback_addresses, $tsml_cache, $tsml_cache_writable, $tsml_distance_units, $tsml_columns;

	//enqueue app script
	$js = defined('TSML_UI_PATH') ? TSML_UI_PATH : 'https://tsml-ui.code4recovery.org/app.js';
	wp_enqueue_script('tsml_ui', $js, [], false, true);

	//apply settings
	wp_localize_script('tsml_ui', 'tsml_react_config', array_merge(
		[
			'columns' => array_keys($tsml_columns),
			'conference_providers' => $tsml_conference_providers,
			'distance_unit' => $tsml_distance_units,
			'feedback_emails' => array_values($tsml_feedback_addresses),
			'flags' => $tsml_programs[$tsml_program]['flags'],
			'strings' => [
				$tsml_language => array_merge($tsml_columns, [
					'types' => $tsml_programs[$tsml_program]['types'],
					'type_descriptions' => $tsml_programs[$tsml_program]['type_descriptions']
				]),
			],
		],
		$tsml_ui_config
	));

	// use meetings.json if it's writable, otherwise use the admin-ajax URL to the feed
	$data = $tsml_cache_writable && file_exists(WP_CONTENT_DIR . $tsml_cache) && defined('ABSPATH')
		? get_site_url() . str_replace(ABSPATH, '/', WP_CONTENT_DIR) . $tsml_cache  . '?' . filemtime(WP_CONTENT_DIR . $tsml_cache)
		: admin_url('admin-ajax.php') . '?action=meetings&nonce=' . wp_create_nonce($tsml_nonce);

	return '<div id="tsml-ui"
					data-src="' . $data . '"
					data-timezone="' . get_option('timezone_string', 'America/New_York') . '"
					data-mapbox="' . $tsml_mapbox_key . '"></div>';
}
add_shortcode('tsml_react', 'tsml_ui');
add_shortcode('tsml_ui', 'tsml_ui');

//output a list of regions with links for AA-DC
add_shortcode('tsml_regions_list', function () {
	//run function recursively
	function get_regions($parent = 0)
	{
		$taxonomy = 'tsml_region';
		$terms = get_terms(compact('taxonomy', 'parent'));
		if (!count($terms)) {
			return;
		}

		$base = get_post_type_archive_link('tsml_meeting') . '?tsml-day=any&tsml-region=';
		foreach ($terms as &$term) {
			$term = '<li><a href="' . $base . $term->term_id . '">' . $term->name . '</a>' . get_regions($term->term_id) . '</li>';
		}
		return '<ul>' . implode($terms) . '</ul>';
	}

	return '<h3>Regions</h3>' . get_regions();
});
