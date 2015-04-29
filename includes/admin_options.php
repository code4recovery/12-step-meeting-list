<?php

add_action('admin_menu', function() {

	//import text file
	add_options_page('Meetings Options', 'Meetings', 'manage_options', 'meetings', function() {
		global $programs;
	    ?>
		<div class="wrap">
		    <h2>Meetings Settings</h2>
			<p class="mp6-primary">This plugin was developed by AA volunteers in <a href="http://aasanjose.org/technology">Santa Clara County</a>
				to help share timely, accurate information about meetings with those who need it.</p>
			<p>Please help us by sharing your meeting data with our open database. You can opt out at
				any time. If you have any questions about this plugin or the open database we're 
				building, please contact <a href="mailto:web@aasanjose.org">web@aasanjose.org</a>.
			</p>
			<form method="post" action="options.php">
				<?php settings_fields('meetings'); ?>
				<?php do_settings_sections('meetings'); ?>
				<p><label><input type="checkbox" name="share"<?php checked(get_option('share')); ?>> Yes, you may use our information.</label></p>
				
				<p>We are listing meetings from this program:
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
		<?php
	});
});
