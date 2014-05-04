<?php

add_action('admin_menu', function() {

	//settings
	register_setting('meetings_group_a', 'hllo_name', 'intval'); 

	add_options_page('Meetings Options', 'Meetings', 'manage_options', 'meetings', function() {
		?>
		<div class="wrap">
			<h2>Meetings Settings</h2>
			<p>Options relating to the Custom Plugin.</p>
			<form action="options.php" method="post">
				<?php settings_fields('meetings_group_a'); ?>
				<?php do_settings_sections('meetings'); ?>
				 
				<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
			</form>
		</div>
		 
		<?php
	});
});
