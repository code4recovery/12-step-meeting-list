<?php
/**
 * Plugin Name: Meetings
 * Plugin URI: https://github.com/intergroup/plugin
 * Description: CMS for maintaining lists of meetings and venues
 * Version: 1.0
 * Author: Santa Clara County Intergroup
 * Author URI: http://aasanjose.org
 * License: none
 */

add_action('init', function(){

	register_taxonomy('region', array('locations'), array(
		'label'=>'Region', 
		'labels'=>array('menu_name'=>'Regions')
	));

	register_post_type('meetings',
		array(
			'labels'		=> array(
				'name'			=>	'Meetings',
				'singular_name'	=>	'Meeting',
				'not_found'		=>	'No meetings added yet.',
				'add_new_item'	=>	'Add New Meeting',
			),
	        'taxonomies'	=> array('tags'),
			'supports'		=> array('title', 'revisions', 'custom-fields'),
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
			'supports'		=> array('title', 'revisions', 'custom-fields'),
			'public'		=> true,
			'has_archive'	=> true,
			'menu_icon'		=> 'dashicons-location',
		)
	);

});
