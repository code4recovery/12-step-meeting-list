<?php

class TSML_Widget_Upcoming extends WP_Widget {

	//constructor
	public function __construct() {
		parent::__construct('tsml_widget_upcoming', __('Upcoming Meetings', '12-step-meeting-list'),
			array(
				'description' => __('Display a table of upcoming meetings.', '12-step-meeting-list'),
			)
		);
	}

	//front-end display of widget
	public function widget($args, $instance) {
		$table = tsml_next_meetings($instance);
		if (empty($table)) return false;
		if (empty($instance['title'])) $instance['title'] = __('Upcoming Meetings', '12-step-meeting-list');
		echo $args['before_widget'];
		if (!empty($instance['title'])) {
			echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
		}
		echo $table;
		$link = get_post_type_archive_link('tsml_meeting');
		$link .= (strpos($link, '?') === false) ? '?i=upcoming' : '&i=upcoming';
		echo '<p><a href="' . $link . '">' . __('View All Meetings', '12-step-meeting-list') . '</a></p>';
		echo $args['after_widget'];
	}

	//backend form
	public function form($instance) {
		$title = !empty($instance['title']) ? $instance['title'] : __('Upcoming Meetings', '12-step-meeting-list');
		$count = !empty($instance['count']) ? $instance['count'] : 5;
		?>
		<p>
			<label for="<?php echo esc_attr($this->get_field_id('title'))?>"><?php _e('Title:', '12-step-meeting-list')?></label> 
			<input class="widefat" id="<?php echo esc_attr($this->get_field_id('title'))?>" name="<?php echo esc_attr($this->get_field_name('title'))?>" type="text" value="<?php echo esc_attr($title)?>">
		</p>
		<p>
			<label for="<?php echo esc_attr($this->get_field_id('count'))?>"><?php _e('Show:', '12-step-meeting-list')?></label> 
			<select class="widefat" id="<?php echo esc_attr($this->get_field_id('title'))?>" name="<?php echo esc_attr($this->get_field_name('count'))?>">
				<?php for ($i = 1; $i < 11; $i++) {?>
					<option value="<?php echo $i?>"<?php selected($i, esc_attr($count))?>><?php echo $i?></option>
				<?php }?>
			</select>
		</p>
		<?php 
	}

	//sanitize widget form values as they are saved
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = (! empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
		$instance['count'] = (! empty($new_instance['count'])) ? intval($new_instance['count']) : 5;
		return $instance;
	}
}

// register widget
function tsml_widget() {
    register_widget('TSML_Widget_Upcoming');
}
add_action('widgets_init', 'tsml_widget');
