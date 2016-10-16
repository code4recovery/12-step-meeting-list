<?php

add_action('admin_menu', 'tsml_admin_menu');

function tsml_admin_menu() {
	
	//add menu items			
	add_submenu_page('edit.php?post_type=tsml_meeting', __('Regions', '12-step-meeting-list'), __('Regions', '12-step-meeting-list'), 'edit_posts', 'edit-tags.php?taxonomy=tsml_region&post_type=tsml_location');
	add_submenu_page('edit.php?post_type=tsml_meeting', __('Import & Settings', '12-step-meeting-list'),  __('Import & Settings', '12-step-meeting-list'), 'manage_options', 'import', 'tmsl_import_page');

	//fix the highlighted state of the regions page
	function tsml_fix_highlight($parent_file){
		global $submenu_file, $current_screen, $pagenow;
		if ($current_screen->post_type == 'tsml_location') {
			if ($pagenow == 'edit-tags.php') {
				$submenu_file = 'edit-tags.php?taxonomy=tsml_region&post_type=tsml_location';
			}
			$parent_file = 'edit.php?post_type=tsml_meeting';
		}
		return $parent_file;
	}
	add_filter('parent_file', 'tsml_fix_highlight');

}

