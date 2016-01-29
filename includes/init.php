<?php

//for all users
add_action('init', 'tsml_init');

function tsml_init() {

	//register post types and taxonomies
	tsml_custom_post_types();
	
	//run any necessary upgrades
	tsml_upgrades();
	
	//load internationalization
	add_action('plugins_loaded', 'tsml_plugins_loaded');
	function tsml_plugins_loaded() {
		load_plugin_textdomain('12-step-meeting-list', false, basename(dirname(__FILE__)) . '/languages/');
	}

	//meeting list page
	add_filter('archive_template', 'tsml_archive_template');
	function tsml_archive_template($template) {
		if (is_post_type_archive(TSML_TYPE_MEETINGS)) {
			$user_theme_file = get_stylesheet_directory() . '/archive-meetings.php';
			if (file_exists($user_theme_file)) return $user_theme_file;
			return dirname(__FILE__) . '/../templates/archive-meetings.php';
		}
		return $template;
	}	

	//meeting & location detail pages
	add_filter('single_template', 'tsml_single_template');
	function tsml_single_template($template) {
		global $post;
		if ($post->post_type == TSML_TYPE_MEETINGS) {
			$user_theme_file = get_stylesheet_directory() . '/single-' . TSML_TYPE_MEETINGS . '.php';
			if (file_exists($user_theme_file)) return $user_theme_file;
			return dirname(__FILE__) . '/../templates/single-' . TSML_TYPE_MEETINGS . '.php';
		} elseif ($post->post_type == TSML_TYPE_LOCATIONS) {
			$user_theme_file = get_stylesheet_directory() . '/single-' . TSML_TYPE_LOCATIONS . '.php';
			if (file_exists($user_theme_file)) return $user_theme_file;
			return dirname(__FILE__) . '/../templates/single-' . TSML_TYPE_LOCATIONS . '.php';
		}
		return $template;
	}
	
	//add theme name to body class, for per-theme CSS fixes
	add_filter('body_class', 'tsml_theme_name');
	function tsml_theme_name($classes) {
		$theme = wp_get_theme();
		$classes[] = sanitize_title($theme->name);
		return $classes;
	}
	
	//add api identification tag to header. more info: https://github.com/intergroup/api
	add_action('wp_head', 'tsml_head');
	function tsml_head() {
		echo '<meta name="12_step_meetings_api" content="' . admin_url('admin-ajax.php') . '?action=api">' . PHP_EOL;
	}

}