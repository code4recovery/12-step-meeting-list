<?php

// upcoming meetings widget
class TSML_Widget_Upcoming extends WP_Widget
{

    // constructor
    public function __construct()
    {
        parent::__construct(
            'tsml_widget_upcoming',
            __('Upcoming Meetings', '12-step-meeting-list'),
            [
                'description' => __('Display a table of upcoming meetings.', '12-step-meeting-list'),
            ]
        );
    }

    // front-end display of widget
    public function widget($args, $instance)
    {
        $table = tsml_next_meetings($instance);
        if (empty($table)) {
            return false;
        }

        if (empty($instance['title'])) {
            $instance['title'] = '';
        }

        if (!empty($instance['css'])) {
            echo '<style type="text/css">
				.tsml-widget-upcoming {
					background-color: transparent;
					padding: 0;
				}
				.tsml-widget-upcoming table thead { display: none; }
				.tsml-widget-upcoming table {
					border: 0;
					margin-bottom: 13px;
				}
				.tsml-widget-upcoming table tbody tr {
					display: block;
					padding: 6px 6px 6px 80px;
					position: relative;
					border-bottom: 1px solid rgba(0, 0, 0, 0.1);
				}
				.tsml-widget-upcoming table tbody td {
					display: block;
					border: 0;
				}
				.tsml-widget-upcoming table tbody td a {
					font-weight: 700;
				}
				.tsml-widget-upcoming table tbody td a small {
					color: #999;
					margin-left: 4px;
				}
				.tsml-widget-upcoming table tbody td a small:before {
					content: " / ";
					margin-right: 3px;
				}
				.tsml-widget-upcoming table tbody td.time {
					position: absolute;
					left: 0;
				}
				.tsml-widget-upcoming p a {
					font-size: 15px;
					font-weight: 700;
				}
				.tsml-widget-upcoming .type-tc {
					opacity: .5;
				}

				.tsml-widget-upcoming .type-onl {
					opacity: 1 !important;
				}
	
				.tsml-widget-upcoming .type-tc small {
					/* $color_alert */
					color: #d40047;
				}

				.tsml-widget-upcoming .type-onl small {
					color: green !important;
					opacity: 1 !important;
				}

				.tsml-widget-upcoming .tsml-no-upcoming-meetings {
					background-color: #f4f4f4;
					border: 1px solid #eee;
					border-radius: .25rem;
					color: #818182;
					margin: 0 0 1rem;
					padding: .75rem 1.25rem;
				}
				.widgets-meetings-top .tsml-widget-upcoming {
					margin: 0 0 15px;
				}
			</style>';
        }

        // don't know how to set this properly
        $args['before_widget'] = str_replace(' class="', ' class="tsml-widget-upcoming ', $args['before_widget']);

        $output = $args['before_widget'];
        if (!empty($instance['title'])) {
            $output .= $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }
        $output .= $table;
        $meetings = tsml_get_meetings(['day' => intval(current_time('w')), 'time' => 'upcoming']);
        $meetings_link = get_post_type_archive_link('tsml_meeting');
        if (!count($meetings) && !empty($instance['message'])) {
            $link = $meetings_link;
        } else {
            $link = $meetings_link . ((strpos($meetings_link, '?') === false) ? '?' : '&') . 'tsml-time=upcoming';
        }
        $output .= '<p><a href="' . $link . '">' . __('View More…', '12-step-meeting-list') . '</a></p>';
        $output .= $args['after_widget'];

        echo wp_kses($output, [
            'a' => ['href' => [], 'class' => []],
            'aside' => ['class' => []],
            'div' => ['class' => []],
            'h1' => ['class' => []],
            'p' => [],
            'small' => [],
            'span' => ['class' => []],
            'style' => ['type' => []],
            'table' => ['class' => []],
            'tbody' => [],
            'td' => ['class' => []],
            'th' => ['class' => []],
            'thead' => [],
            'tr' => ['class' => []],
        ]);
    }

    // backend form
    public function form($instance)
    {
        $title = !empty($instance['title']) ? $instance['title'] : __('Upcoming Meetings', '12-step-meeting-list');
        $count = !empty($instance['count']) ? $instance['count'] : 5;
        $message = !empty($instance['message']) ? $instance['message'] : '';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')) ?>">
                <?php esc_html_e('Title:', '12-step-meeting-list') ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')) ?>"
                name="<?php echo esc_attr($this->get_field_name('title')) ?>" type="text"
                value="<?php echo esc_attr($title) ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('count')) ?>">
                <?php esc_html_e('Show:', '12-step-meeting-list') ?>
            </label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('title')) ?>"
                name="<?php echo esc_attr($this->get_field_name('count')) ?>">
                <?php for ($i = 1; $i < 26; $i++) { ?>
                    <option value="<?php echo esc_attr($i) ?>" <?php selected($i, $count) ?>>
                        <?php echo esc_attr($i) ?>
                    </option>
                <?php } ?>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('message')) ?>">
                <?php esc_html_e('Message (displayed if no upcoming meetings, optional):', '12-step-meeting-list') ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('message')) ?>"
                name="<?php echo esc_attr($this->get_field_name('message')) ?>" type="text"
                value="<?php echo esc_attr($message) ?>">
        </p>
        <p>
            <input id="<?php echo esc_attr($this->get_field_id('css')) ?>"
                name="<?php echo esc_attr($this->get_field_name('css')) ?>" type="checkbox" <?php checked(!empty($instance['css'])) ?>>
            <label for="<?php echo esc_attr($this->get_field_id('css')) ?>">
                <?php esc_html_e('Style with CSS?', '12-step-meeting-list') ?>
            </label>
        </p>
        <?php
    }

    // sanitize widget form values as they are saved
    public function update($new_instance, $old_instance)
    {
        return [
            'title' => !empty($new_instance['title']) ? strip_tags($new_instance['title']) : '',
            'count' => !empty($new_instance['count']) ? intval($new_instance['count']) : 5,
            'css' => !empty($new_instance['css']),
            'message' => !empty($new_instance['message']) ? strip_tags($new_instance['message']) : '',
        ];
    }
}

// app store links widget
class TSML_Widget_App_Store extends WP_Widget
{

    // constructor
    public function __construct()
    {
        parent::__construct(
            'tsml_widget_app_store',
            __('App Store', '12-step-meeting-list'),
            [
                'description' => __('Display links to the Meeting Guide app in the Apple and Android app stores.', '12-step-meeting-list'),
            ]
        );
    }

    // backend form
    public function form($instance)
    {
        $title = empty($instance['title']) ? '' : $instance['title'];
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')) ?>">
                <?php esc_html_e('Title (optional):', '12-step-meeting-list') ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')) ?>"
                name="<?php echo esc_attr($this->get_field_name('title')) ?>" type="text"
                value="<?php echo esc_attr($title) ?>">
        </p>
        <p>
            <input id="<?php echo esc_attr($this->get_field_id('css')) ?>"
                name="<?php echo esc_attr($this->get_field_name('css')) ?>" type="checkbox" <?php checked(!empty($instance['css'])) ?>>
            <label for="<?php echo esc_attr($this->get_field_id('css')) ?>">
                <?php esc_html_e('Style with CSS?', '12-step-meeting-list') ?>
            </label>
        </p>
        <?php
    }

    // sanitize widget form values as they are saved
    public function update($new_instance, $old_instance)
    {
        $instance = [];
        $instance['title'] = empty($new_instance['title']) ? '' : strip_tags($new_instance['title']);
        $instance['css'] = !empty($new_instance['css']);
        return $instance;
    }

    // front-end display of widget
    public function widget($args, $instance)
    {
        if (empty($instance['title'])) {
            $instance['title'] = '';
        }

        // don't know how to set this properly
        $args['before_widget'] = str_replace(' class="', ' class="tsml-widget-app-store ', $args['before_widget']);

        $output = $args['before_widget'];
        if (!empty($instance['title'])) {
            $output .= $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        if (!empty($instance['css'])) {
            echo '<style type="text/css">
			.tsml-widget-app-store {
				background-color: transparent;
				padding: 0;
			}
			.tsml-widget-app-store nav {
				overflow: auto;
				padding: 0;
				margin: 0 -7.5px;
			}
			.tsml-widget-app-store a {
				display: inline-block;
				width: 50%;
				box-sizing: border-box;
				padding: 0 7.5px;
				float: left;
			}
			.tsml-widget-app-store img {
				width: 100%;
				height: auto;
			}
			#tsml .meetings-widgets h3 {
				margin: 0 0 15px;
				border-bottom: 1px solid #ddd;
				padding: 0 0 10px;
				text-align: center;
			}
			#tsml .meetings-widgets-top .tsml-widget-app-store {
				margin: 0 0 15px;
			}
			#tsml .meetings-widgets-bottom .tsml-widget-app-store {
				margin: 30px 0;
			}
			</style>';
        }

        $output .= '
			<nav>
				<a href="https://itunes.apple.com/us/app/meeting-guide/id1042822181" target="_blank">
					<img src="' . plugins_url('assets/img/apple.svg', __DIR__) . '" alt="App Store" width="113.13" height="38.2">
				</a>
				<a href="https://play.google.com/store/apps/details?id=org.meetingguide" target="_blank">
					<img src="' . plugins_url('assets/img/google.svg', __DIR__) . '" alt="Google Play" width="113.13" height="38.2">
				</a>
			</nav>
		';

        $output .= $args['after_widget'];

        echo wp_kses_post($output);
    }
}

// register widgets
add_action('widgets_init', function () {
    register_widget('TSML_Widget_Upcoming');
    register_widget('TSML_Widget_App_Store');
});
