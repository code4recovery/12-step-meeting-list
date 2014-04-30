<?php

register_taxonomy('region', array('meetings'), array(
	'label'=>'Region', 
	'labels'=>array('menu_name'=>'Regions')
));

register_taxonomy('tags', array('meetings'), array(
	'label'=>'Tags', 
	'labels'=>array('menu_name'=>'Tags')
));

register_post_type('meetings',
	array(
		'labels'		=> array(
			'name'			=>	'Meetings',
			'singular_name'	=>	'Meeting',
			'not_found'		=>	'No meetings added yet.',
			'add_new_item'	=>	'Add New Meeting',
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
	)
);