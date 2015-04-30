<?php

add_action('admin_menu', function() {

	//import text file
	add_options_page('Meetings Options', 'Meetings', 'manage_options', 'meetings', function(){
		global $programs;
	    ?>
		<div class="wrap">
		    <h2>Meetings Settings</h2>
		    
		    <div id="poststuff">
			    <div id="post-body" class="columns-2">
				    <div id="post-body-content">
					    <div class="postbox">
						    <h3>Customize Meeting Types</h3>
						    <div class="inside">
									    
								<p>Please help us improve this plugin by sharing your area's meeting information with our open 
									source database. This option is explicitly opt-in, and you may opt out at any time. Our ultimate
									goal is to design a database that freely shares recovery meeting information with everyone.</p>
	
								<p>If you have any questions about this plugin or the tools we're building, please contact 
									<a href="mailto:web@aasanjose.org">web@aasanjose.org</a>.</p>
	
								<form method="post" action="options.php">
									<?php settings_fields('meetings'); ?>
									<?php do_settings_sections('meetings'); ?>
									
									<?php submit_button(); ?>
								</form>
						    </div>
					    </div>
					</div>
				    <div id="postbox-container-1" class="postbox-container">
						<div class="postbox">
							<h3>Where's My Info?</h3>
							<div class="inside">
								<p>Your meeting list page is <a href="<?php echo get_post_type_archive_link('md_meetings'); ?>">right here</a>.</p>
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
