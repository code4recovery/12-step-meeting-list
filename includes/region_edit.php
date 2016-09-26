<?php

//when a region is modified, update post_modified for meetings in that region

function tsml_edited_region($region_id) {
	$meetings = tsml_get_meetings(array('region' => $region_id));
	foreach ($meetings as $meeting) {
		wp_update_post(array('ID' => $meeting['id']));
	}
}

add_action('edited_region', 'tsml_edited_region');
