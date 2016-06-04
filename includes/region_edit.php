<?php

//when region modified, update post_modified for meetings in region

function tsml_edit_category($region_id) {

	$post_ids = get_posts(array(
		'numberposts' => -1,
		'meta_key' => 'region',
		'meta_value' => $region_id,
		'fields' => 'ids',
		'post_type' => 'meetings',
	));
	
	foreach ($post_ids as $post_id) {
		wp_update_post(array('ID'=>$post_id));
	}
}

add_action('edited_region', 'tsml_edit_category');
