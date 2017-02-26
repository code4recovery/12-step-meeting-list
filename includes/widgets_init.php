<?php
//enables widgets on the meeting archive page

function tsml_widgets_init() {

	register_sidebar(array(
		'name'          => 'Meetings Top',
		'id'            => 'tsml_meetings_top',
		'before_widget' => '<div class="widget">',
		'after_widget'  => '</div>',
		'before_title'  => '<h3>',
		'after_title'   => '</h3>',
	));

	register_sidebar(array(
		'name'          => 'Meetings Bottom',
		'id'            => 'tsml_meetings_bottom',
		'before_widget' => '<div class="widget">',
		'after_widget'  => '</div>',
		'before_title'  => '<h3>',
		'after_title'   => '</h3>',
	));

}

add_action('widgets_init', 'tsml_widgets_init');
