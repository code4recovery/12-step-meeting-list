<?php

//for all users
add_action('init', function(){

	//register post types and taxonomies
	tsml_custom_post_types();

	//meeting list page
	add_filter('archive_template', function($template) {
		if (is_post_type_archive('meetings')) {
			$user_theme_file = get_stylesheet_directory() . '/archive-meetings.php';
			if (file_exists($user_theme_file)) return $user_theme_file;
			return dirname(__FILE__) . '/../templates/archive-meetings.php';
		}
		return $template;
	});

	//meeting & location detail pages
	add_filter('single_template', function($template) {
		global $post;
		if ($post->post_type == 'meetings') {
			$user_theme_file = get_stylesheet_directory() . '/single-meetings.php';
			if (file_exists($user_theme_file)) return $user_theme_file;
			return dirname(__FILE__) . '/../templates/single-meetings.php';
		} elseif ($post->post_type == 'locations') {
			$user_theme_file = get_stylesheet_directory() . '/single-locations.php';
			if (file_exists($user_theme_file)) return $user_theme_file;
			return dirname(__FILE__) . '/../templates/single-locations.php';
		}
		return $template;
	});

});