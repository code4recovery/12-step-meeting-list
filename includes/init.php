<?php

//for all users
add_action('init', function(){

	//register post types and taxonomies
	tsml_custom_post_types();

	//meeting list page
	add_filter('archive_template', function($template) {
		if (is_post_type_archive('meetings')) {
			return dirname(__FILE__) . '/../templates/archive-meetings.php';
		}
		return $template;
	});

	//meeting & location detail pages
	add_filter('single_template', function($template) {
		global $post;
		if ($post->post_type == 'meetings') {
			return dirname(__FILE__) . '/../templates/single-meetings.php';
		} elseif ($post->post_type == 'locations') {
			return dirname(__FILE__) . '/../templates/single-locations.php';
		}
		return $template;
	});

});