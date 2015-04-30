<?php

//register the options
add_action('admin_init', function(){
	register_setting('meetings', 'share', 'meetings_callback_share');
	register_setting('meetings', 'program', 'meetings_callback_program');
});

//announcement function
add_action('meetings_announce', function(){
	$modified  = get_posts('orderby=modified&sort_order=desc&posts_per_page=1');
	$meetings  = wp_count_posts('meetings');
	$locations = wp_count_posts('locations');
	wp_remote_post('http://api.aasanjose.org/v1/announce', array(
		'body' => array(
			'name'		=> get_bloginfo('name'),
			'url'		=> get_bloginfo('url'),
			'email'		=> get_bloginfo('admin_email'),
			'version'	=> '1.0.0',
			'meetings'	=> $meetings->publish,
			'locations'	=> $locations->publish,
			'updated'	=> $modified[0]->post_modified_gmt,
			'share'		=> get_option('share'),
			'program'	=> get_option('program'),
		))
	);
});

add_action('admin_menu', function() {

	//schedule daily announce event if sharing
	if (get_option('share')) {
		wp_schedule_event(time(), 'daily', 'meetings_announce');
	} else {
		wp_clear_scheduled_hook('meetings_announce');			
	}
	
	//import text file
	add_options_page('Meetings Options', 'Meetings', 'manage_options', 'meetings', function(){
		global $programs;
	    ?>
		<div class="wrap">
		    <h2>Meetings Settings</h2>
		    
		    <div id="poststuff">
			    <div id="post-body" class="columns-2">
				    <div id="post-body-content" class="postbox">
					    <h3>Share Your Data With Us!</h3>
					    <div class="inside">
								    
							<p>Please help us improve this plugin by sharing your area's meeting information with our open 
								source database. This option is explicitly opt-in, and you may opt out at any time. Our ultimate
								goal is to design a database that freely shares recovery meeting information with everyone.</p>

							<p>If you have any questions about this plugin or the tools we're building, please contact 
								<a href="mailto:web@aasanjose.org">web@aasanjose.org</a>.</p>

							<form method="post" action="options.php">
								<?php settings_fields('meetings'); ?>
								<?php do_settings_sections('meetings'); ?>
								<p><label><input type="checkbox" name="share"<?php checked(get_option('share')); ?>> Yes, we would like to share our information.</label></p>
								
								<p>We are listing this type of meetings:
								<?php foreach ($programs as $code => $description) {?>
								<label style="display:block; margin:2px 0;">
									<input type="radio" name="program" value="<?php echo $code; ?>"<?php checked(get_option('program') == $code); ?>>
									<span><?php echo $description; ?></span>
								</label>
								<?php } ?>
								<label style="display:block;">
									<input type="radio" name="program" value="other"<?php checked(!in_array(get_option('program'), array_keys($programs))); ?>> 
									<span>Other</span>
									<input type="text" name="other" value="<?php if (!in_array(get_option('program'), array_keys($programs))) echo get_option('program'); ?>" style="vertical-align:top;">
								</label>
								</p>
								
								<?php submit_button(); ?>
							</form>
					    </div>
					</div>
				    <div id="postbox-container-1" class="postbox-container">
						<div class="postbox">
							<h3>Where's My Info?</h3>
							<div class="inside">
								<p>Your meeting list page is <a href="<?php echo get_post_type_archive_link('meetings'); ?>">right here</a>.</p>
							</div>
						</div>
						<div class="postbox">
							<h3>About this Plugin</h3>
							<div class="inside">
								<p>This plugin was developed by AA volunteers in <a href="http://aasanjose.org/technology">Santa 
									Clara County</a> to help provide accessible, accurate information about meetings to 
									those who need it.</p>
							</div>
						</div>
				    </div>
			    </div>
		    </div>
		    
		<?php
	});
});
