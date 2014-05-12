<?php

add_action('init', function(){
	global $regions;
	
	register_taxonomy('region', array('meetings'), array(
		'label'=>'Region', 
		'labels'=>array('menu_name'=>'Regions')
	));

	//build quick access array of regions
	$regions = meetings_get_regions();

	register_post_type('meetings',
		array(
			'labels'		=> array(
				'name'			=>	'Meetings',
				'singular_name'	=>	'Meeting',
				'not_found'		=>	'No meetings added yet.',
				'add_new_item'	=>	'Add New Meeting',
				'search_items'	=>	'Search Meetings',
				'edit_item'		=>	'Edit Meeting',
				'view_item'		=>	'View Meeting',
			),
			'supports'		=> array('title', 'revisions'),
			'public'		=> true,
			'has_archive'	=> true,
			'menu_icon'		=> 'dashicons-groups',
		)
	);

	register_post_type('locations',
		array(
			'labels'		=> array(
				'name'			=>	'Locations',
				'singular_name'	=>	'Location',
				'not_found'		=>	'No locations added yet.',
				'add_new_item'	=>	'Add New Location',
			),
	        'taxonomies'	=>	array('region'),
			'supports'		=> array('title', 'revisions'),
			'public'		=> false,
			'show_ui'		=> true,
			'has_archive'	=> true,
			'show_in_menu'	=> 'edit.php?post_type=meetings',
			'menu_icon'		=> 'dashicons-location',
			'capabilities'	=> array('create_posts'=>false),
		)
	);
});	