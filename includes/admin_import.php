<?php

add_action('admin_menu', function() {
	global $tsml_nonce, $tsml_program;
	
	//run import
	if (!empty($_POST['tsml_import']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		tsml_import($_POST['tsml_import'], !empty($_POST['delete']));
	}
		
	//change program
	if (!empty($_POST['tsml_program']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		$tsml_program = sanitize_text_field($_POST['tsml_program']);
		if (get_option('tsml_program') === false) {
			add_option('tsml_program', $tsml_program);
		} else {
			update_option('tsml_program', $tsml_program);
		}
		tsml_alert('Program setting updated.');
	}
		
	//import text file
	add_submenu_page('edit.php?post_type=meetings', 'Import &amp; Settings', 'Import &amp; Settings', 'manage_options', 'import', function(){
		global $tsml_types, $tsml_programs, $tsml_program, $tsml_nonce, $tsml_days;

	    ?>
		<div class="wrap">
		    <h2>Import &amp; Settings</h2>
		    
		    <div id="poststuff">
			    <div id="post-body" class="columns-2">
				    <div id="post-body-content">
					    <div class="postbox">
							<h3>Import Data</h3>
						    <div class="inside">
								<p>You can import a spreadsheet of meetings by pasting into the field below. <a href="<?php echo plugin_dir_url(__FILE__) . '../template.csv'?>">Here is a spreadsheet</a> you can use as a template. The header row must kept in place.</p>
								<ul class="ul-disc">
									<li><strong>Time</strong>, if present, should be in a standard date format such as 6:00 AM. Non-standard or empty dates will be imported as 'by appointment.'</li>
									<li><strong>Day</strong>, if present, should either Sunday, Monday, Tuesday, Wednesday, Thursday, Friday, or Saturday. Meetings that occur on multiple days should be listed separately. 'Daily' or 'Mondays' will not work. Non-standard days will be imported as 'by appointment.'</li>
 									<li><strong>Address</strong> is required and will be corrected by Google, so it may look different afterward. Ideally, every address for the same location should be exactly identical, and not contain extra information about the address, such as the building name or descriptors like 'around back.'</li>
									<li><strong>Name</strong> is the name of the meeting, and is optional, although it's valuable information for the user. If it's missing, a name will be created by combining the location, day, and time.</li>
									<li><strong>Location</strong> is the name of the location, and is optional. Generally it's the group or building name. If it's missing, the address will be used. In the event that there are multiple location names for the same address, the first location name will be used.</li>
									<li><strong>City</strong>, <strong>State</strong>, and <strong>Country</strong> are optional, but might be useful if your addresses sound ambiguous to Google.</li>
									<li><strong>Notes</strong> are freeform notes that will show up publicly. This is where 'around back' is useful.</li>
									<li><strong>Types</strong> should be a comma-separated list of the following options. This list is determined by which program is selected at right.
										<ul style="margin-top:10px;overflow:auto;">
										<?php foreach ($tsml_types[$tsml_program] as $value) {?>
											<li style="margin-bottom:0;width:33.33%;float:left;"><?php echo $value?></li>
										<?php }?>
										</ul>
									</li>
								</ul>
								<form method="post" action="edit.php?post_type=meetings&page=import">
									<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
									<p>It takes a while for the address verification to do its thing, please be patient. Importing 500 meetings usually takes about one minute.</p>
									<textarea name="tsml_import" class="widefat" rows="5" placeholder="Paste spreadsheet data here"></textarea>
									<p><label><input type="checkbox" name="delete"> Delete all meetings, locations, and regions prior to import</label></p>
									<div style="margin-top:12px;"><input type="submit" class="button button-primary" value="Begin"></div>
								</form>
						    </div>
					    </div>
					</div>
				    <div id="postbox-container-1" class="postbox-container">
						<div class="postbox">
							<h3>Choose Your Program</h3>
							<div class="inside">
								<form method="post" action="edit.php?post_type=meetings&page=import">
								<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
								<p>This determines which meeting types are available. If your program is 
									not listed, please <a href="mailto:web@aasanjose.org">let us know</a> about 
									your program and what types of meetings you have 
									(Open, Closed, Big Book, etc).
								</p>
								<select name="tsml_program" onchange="this.form.submit()">
									<?php foreach ($tsml_programs as $key=>$value) {?>
									<option value="<?php echo $key?>"<?php selected($tsml_program, $key)?>><?php echo $value?></option>
									<?php }?>
								</select>
								</form>
							</div>
						</div>
						<div class="postbox">
							<h3>Where's My Info?</h3>
							<div class="inside">
								<p>Your meeting list page is <a href="<?php echo get_post_type_archive_link('meetings'); ?>">right here</a>. 
								Link that page from your site's nav menu to make it visible to the public.</p>
								<p>You can also download your meetings in <a href="<?php echo admin_url('admin-ajax.php')?>?action=csv">CSV format</a>.</p>
								<p style="margin-bottom:0;">You have:</p>
								<ul class="ul-disc" style="margin-top:4px">
									<li style="margin: 0 0 2px;"><?php echo count(tsml_get_all_meetings())?> meetings</li>
									<li style="margin: 0 0 2px;"><?php echo count(tsml_get_all_locations())?> locations</li>
									<li style="margin: 0 0 2px;"><?php echo count(tsml_get_all_regions())?> regions</li>
								</ul>
							</div>
						</div>
						<div class="postbox">
							<h3>About this Plugin</h3>
							<div class="inside">
								<p>This plugin was developed by AA volunteers in <a href="http://aasanjose.org/technology">Santa 
									Clara County</a> to help provide accessible, accurate information about meetings to 
									those who need it.</p>
								<p>Get in touch by sending email to <a href="mailto:web@aasanjose.org">web@aasanjose.org</a>.</p>
							</div>
						</div>
				    </div>
			    </div>
		    </div>
		    
		<?php
	});
});
