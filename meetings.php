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

	register_taxonomy('region', array('meetings'), array(
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
			'has_archive'	=> true,
			'menu_icon'		=> 'dashicons-location',
		)
	);

});

add_action('admin_print_styles', function(){
	wp_enqueue_style('meetings_meta_style', plugin_dir_url( __FILE__ ) . 'admin.css' );
});

add_action('admin_init', function(){
	//add_meta_box("year_completed-meta", "Year Completed", "year_completed", 'meetings', "side", "low");

	remove_meta_box('tagsdiv-region', 'meetings', 'side' );

	add_meta_box('info', 'General Info', function(){
		global $post;
		?>
		<div class="meta_form_row">
			<label for="day">Day</label>
			<select name="day" id="day">
				<option value="Sunday">Sunday</option>
				<option value="Monday">Monday</option>
				<option value="Tuesday">Tuesday</option>
				<option value="Wednesday">Wednesday</option>
				<option value="Thursday">Thursday</option>
				<option value="Friday">Friday</option>
				<option value="Saturday">Saturday</option>
			</select>
		</div>
		<div class="meta_form_row">
			<label for="time">Time</label>
			<input type="time" name="time" id="time">
		</div>
		<div class="meta_form_row">
			<label for="type">Type</label>
			<div class="checkboxes">
				<div><input type="radio" name="type" value="open"> Open</div>
				<div><input type="radio" name="type" value="closed"> Closed</div>
			</div>
		</div>
		<div class="meta_form_row">
			<label for="tags">Tags</label>
			<div class="checkboxes">
				<div><input type="checkbox" name="tags[]"> Chips</div>
				<div><input type="checkbox" name="tags[]"> Men Only</div>
				<div><input type="checkbox" name="tags[]"> Women Only</div>
				<div><input type="checkbox" name="tags[]"> Adults Only</div>
				<div><input type="checkbox" name="tags[]"> Gay</div>
				<div><input type="checkbox" name="tags[]"> Lesbian</div>
				<div><input type="checkbox" name="tags[]"> Spanish</div>
				<div><input type="checkbox" name="tags[]"> Wheelchair Access</div>
				<div><input type="checkbox" name="tags[]"> Young People</div>
				<div><input type="checkbox" name="tags[]"> Newcomer</div>
			</div>
		</div>
		<div class="meta_form_row">
			<label for="notes">Notes</label>
			<textarea name="notes" id="notes" placeholder="eg. Babysitting is available"></textarea>
		</div>
		<?php
	}, 'meetings', 'normal', 'low');

	add_meta_box('location', 'Location', function(){
		global $post;
		?>
		<div class="meta_form_row">
			<label for="location">Location</label>
			<input type="text" name="location" id="location" placeholder="Calvary Church">
		</div>
		<div class="meta_form_row">
			<label for="address1">Address 1</label>
			<input type="text" name="address1" id="address1" placeholder="123 Main Street">
		</div>
		<div class="meta_form_row">
			<label for="address2">Address 2</label>
			<input type="text" name="address2" id="address2" placeholder="2nd Floor">
		</div>
		<div class="meta_form_row">
			<label for="region">Region</label>
			<select name="region" id="region">
				<option value="Campbell">Campbell</option>
				<option value="Cupertino">Cupertino</option>
			</select>
		</div>
		<?php
	}, 'meetings', 'normal', 'low');
});


