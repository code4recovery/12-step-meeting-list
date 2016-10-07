<?php

//import page
add_action('admin_menu', 'tsml_admin_menu');

function tsml_admin_menu() {
	global $tsml_nonce, $tsml_program, $tsml_feedback_addresses;
	
	//run import
	if (isset($_FILES['tsml_import']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		ini_set('auto_detect_line_endings', 1); //to handle mac \r line endings
		$extension = explode('.', strtolower($_FILES['tsml_import']['name']));
		$extension = end($extension);
		if ($_FILES['tsml_import']['error'] > 0) {
			tsml_alert(__('File upload error #' . $_FILES['tsml_import']['error'], '12-step-meeting-list'), 'error');
		} elseif (empty($extension)) {
			tsml_alert(__('Uploaded file did not have a file extension. Please add .csv to the end of the file name.', '12-step-meeting-list'), 'error');
		} elseif ($extension != 'csv') {
			tsml_alert(__('Please upload a csv file. Your file ended in .' . $extension . '.', '12-step-meeting-list'), 'error');
		} elseif (!$handle = fopen($_FILES['tsml_import']['tmp_name'], 'r')) {
			tsml_alert(__('Error opening CSV file', '12-step-meeting-list'), 'error');
		} else {
			$meetings = array();
			while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
				$meetings[] = $data;
			}
			tsml_import($meetings, !empty($_POST['delete']));
		}
	}
		
	//change program
	if (!empty($_POST['tsml_program']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		$tsml_program = sanitize_text_field($_POST['tsml_program']);
		update_option('tsml_program', $tsml_program);
		tsml_alert('Program setting updated.');
	}
		
	//add a feedback email
	if (!empty($_POST['tsml_add_feedback_address']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		$email = sanitize_text_field($_POST['tsml_add_feedback_address']);
		if (!is_email($email)) tsml_alert('"' . $email . '" is not a valid email address. Please try again.', 'error');
		$tsml_feedback_addresses[] = $email;
		$tsml_feedback_addresses = array_unique($tsml_feedback_addresses);
		sort($tsml_feedback_addresses);
		update_option('tsml_feedback_addresses', $tsml_feedback_addresses);
		tsml_alert('Feedback address added.');
	}
	
	//remove a feedback email
	if (!empty($_POST['tsml_remove_feedback_address']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		$email = sanitize_text_field($_POST['tsml_remove_feedback_address']);
		if (($key = array_search($email, $tsml_feedback_addresses)) !== false) {
			unset($tsml_feedback_addresses[$key]);
		} else {
			tsml_alert('"' . $email . '" was not found in the list of addresses. Please try again.', 'error');
		}
		if (empty($tsml_feedback_addresses)) {
			delete_option('tsml_feedback_addresses');
		} else {
			update_option('tsml_feedback_addresses', $tsml_feedback_addresses);
		}
		tsml_alert('Feedback address removed.');
	}
			
	/*add a notification email
	if (!empty($_POST['tsml_add_notification_address']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		$email = sanitize_text_field($_POST['tsml_add_notification_address']);
		if (!is_email($email)) tsml_alert('"' . $email . '" is not a valid email address. Please try again.', 'error');
		$tsml_notification_addresses[] = $email;
		$tsml_notification_addresses = array_unique($tsml_notification_addresses);
		sort($tsml_notification_addresses);
		update_option('tsml_notification_addresses', $tsml_notification_addresses);
		tsml_alert('Notification address added.');
	}
	
	//remove a notification email
	if (!empty($_POST['tsml_remove_notification_address']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		$email = sanitize_text_field($_POST['tsml_remove_notification_address']);
		if (($key = array_search($email, $tsml_notification_addresses)) !== false) {
			unset($tsml_notification_addresses[$key]);
		} else {
			tsml_alert('"' . $email . '" was not found in the list of addresses. Please try again.', 'error');
		}
		if (empty($tsml_notification_addresses)) {
			delete_option('tsml_notification_addresses');
		} else {
			update_option('tsml_notification_addresses', $tsml_notification_addresses);
		}
		tsml_alert('Notification address removed.');
	}*/
	
	//add menu items			
	add_submenu_page('edit.php?post_type=tsml_meeting', __('Regions', '12-step-meeting-list'), __('Regions', '12-step-meeting-list'), 'edit_posts', 'edit-tags.php?taxonomy=tsml_region&post_type=tsml_location');
	add_submenu_page('edit.php?post_type=tsml_meeting', __('Import & Settings', '12-step-meeting-list'),  __('Import & Settings', '12-step-meeting-list'), 'manage_options', 'import', 'tmsl_import_page');

	//fix the highlighted state of the regions page
	function tsml_fix_highlight($parent_file){
		global $submenu_file, $current_screen, $pagenow;
		if ($current_screen->post_type == 'tsml_location') {
			if ($pagenow == 'edit-tags.php') {
				$submenu_file = 'edit-tags.php?taxonomy=tsml_region&post_type=tsml_location';
			}
			$parent_file = 'edit.php?post_type=tsml_meeting';
		}
		return $parent_file;
	}
	add_filter('parent_file', 'tsml_fix_highlight');

	//import text file
	function tmsl_import_page() {
		global $tsml_types, $tsml_programs, $tsml_program, $tsml_nonce, $tsml_days, $tsml_feedback_addresses, $tsml_notification_addresses;

	    ?>
		<div class="wrap">
		    <h2><?php _e('Import & Settings', '12-step-meeting-list')?></h2>
		    
		    <div id="poststuff">
			    <div id="post-body" class="columns-2">
				    <div id="post-body-content">
					    <div class="postbox">
						    <div class="inside">
								<h3><?php _e('Import Data', '12-step-meeting-list')?></h3>
								<p>You can import a CSV of meeting info using the form below. <a href="<?php echo plugin_dir_url(__FILE__) . '../template.csv'?>">Here is a spreadsheet</a> you can use as a template. Save it as a comma-delimited CSV before uploading it. The header row must kept in place.</p>
								<ul class="ul-disc">
									<li><strong>Time</strong>, if present, should be in a standard date format such as 6:00 AM or 06:00. Non-standard or empty dates will be imported as 'by appointment.'</li>
									<li><strong>End Time</strong>, if present, should be in a standard date format such as 6:00 AM or 06:00.</li>
									<li><strong>Day</strong>, if present, should either Sunday, Monday, Tuesday, Wednesday, Thursday, Friday, or Saturday. Meetings that occur on multiple days should be listed separately. 'Daily' or 'Mondays' will not work. Non-standard days will be imported as 'by appointment.'</li>
									<li><strong>Name</strong> is the name of the meeting, and is optional, although it's valuable information for the user. If it's missing, a name will be created by combining the location, day, and time.</li>
									<li><strong>Location</strong> is the name of the location, and is optional. Generally it's the group or building name. If it's missing, the address will be used. In the event that there are multiple location names for the same address, the first location name will be used.</li>
 									<li><strong>Address</strong> is strongly encouraged and will be corrected by Google, so it may look different afterward. Ideally, every address for the same location should be exactly identical, and not contain extra information about the address, such as the building name or descriptors like 'around back.'</li>
									<li>If Address is specified, then <strong>City</strong>, <strong>State</strong>, and <strong>Country</strong> are optional, but they might be useful if your addresses sound ambiguous to Google. If address is not specified, then these fields are required.</li>
									<li><strong>Notes</strong> are freeform notes that are specific to the meeting. For example, "last Saturday is birthday night."</li>
									<li><strong>Region</strong> is user-defined and can be anything. Often this is a small municipality or neighborhood. Since these go in a dropdown, ideally you would have 10 to 20 regions, although it's ok to be over or under.</li>
									<li><strong>Sub Region</strong> makes the Region hierarchical; in San Jose we have sub regions for East San Jose, West San Jose, etc. New York City might have Manhattan be a Region, and Greenwich Village be a Sub Region.</li>
									<li><strong>Location Notes</strong> are freeform notes that will show up on every meeting that this location. For example, "Enter from the side."</li>
									<li><strong>Group</strong> is a way of grouping contacts. Meetings with the name Group name will be grouped together and share contact information.</li>
									<li><strong>Group Notes</strong> is for stuff like a short group history, or when the business meeting meets.</li>
									<li><strong>Contact 1/2/3 Name/Email/Phone</strong> (nine fields in total) are all optional, but will not be saved if there is not also a Group name specified. By default, contact information is only visible inside the WordPress dashboard.</li>
									<li><strong>Types</strong> should be a comma-separated list of the following options. This list is determined by which program is selected at right.
										<ul class="types">
										<?php foreach ($tsml_types[$tsml_program] as $value) {?>
											<li><?php echo $value?></li>
										<?php }?>
										</ul>
									</li>
								</ul>
								<form method="post" action="edit.php?post_type=tsml_meeting&page=import" enctype="multipart/form-data">
									<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
									<p>It takes a while for the address verification to do its thing, please be patient. Importing 500 meetings usually takes about one minute.</p>
									<input type="file" name="tsml_import"></textarea>
									<p><label><input type="checkbox" name="delete"> Delete all meetings, locations, groups, and regions prior to import</label></p>
									<p><input type="submit" class="button button-primary" value="Begin"></p>
								</form>
						    </div>
					    </div>
					</div>
				    <div id="postbox-container-1" class="postbox-container">
						<div class="postbox">
							<div class="inside">
								<h3><?php _e('Choose Your Program', '12-step-meeting-list')?></h3>
								<form method="post" action="edit.php?post_type=tsml_meeting&page=import">
								<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
								<p>This determines which meeting types are available. If your program is 
									not listed, please <a href="mailto:wordpress@meetingguide.org">let us know</a> about 
									your program and what types of meetings you have 
									(Open, Closed, Topic Discussion, etc).
								</p>
								<select name="tsml_program" onchange="this.form.submit()">
									<?php foreach ($tsml_programs as $key=>$value) {?>
									<option value="<?php echo $key?>"<?php selected($tsml_program, $key)?>><?php echo $value?></option>
									<?php }?>
								</select>
								</form>
							</div>
						</div>
						<div class="postbox" id="wheres_my_info">
							<div class="inside">
								<h3><?php _e('Where\'s My Info?', '12-step-meeting-list')?></h3>
								<p>Your meeting list page is <a href="<?php echo get_post_type_archive_link('tsml_meeting'); ?>">right here</a>. 
								Link that page from your site's nav menu to make it visible to the public.</p>
								<p>You can also download your meetings in <a href="<?php echo admin_url('admin-ajax.php')?>?action=csv">CSV format</a>.</p>
								<?php
								$meetings = count(tsml_get_all_meetings());
								$locations = count(tsml_get_all_locations());
								$regions = count(tsml_get_all_regions());
								$groups = count(tsml_get_all_groups());
								if ($meetings || $locations || $regions || $groups) {?>
								<p>You have:</p>
								<ul class="ul-disc">
									<?php
									if ($meetings) {?>
									<li><?php printf(
										    _n('%s meeting', '%s meetings', $meetings),
										    number_format_i18n($meetings)
										)?></li>
									<?php }
									if ($locations) {?>
									<li><?php printf(
										    _n('%s location', '%s locations', $locations),
										    number_format_i18n($locations)
										)?></li>
									<?php }
									if ($regions) {?>
									<li><?php printf(
										    _n('%s region', '%s regions', $regions),
										    number_format_i18n($regions)
										)?></li>
									<?php }
									if ($groups) {?>
									<li><?php printf(
										    _n('%s group', '%s groups', $groups),
										    number_format_i18n($groups)
										)?></li>
									<?php }?>
								</ul>
								<?php
								}
								if ($groups) {?>
								Want to send a mass email to your group contacts? <a href="<?php echo admin_url('admin-ajax.php')?>?action=contacts" target="_blank">Click here</a> to see their email addresses.
								<?php }?>
							</div>
						</div>
						<div class="postbox" id="get_feedback">
							<div class="inside">
								<h3>Want User Feedback?</h3>
								<p>Enable a meeting info feedback form by adding email addresses below:</p>
								<?php if (!empty($tsml_feedback_addresses)) {?>
								<table>
									<?php foreach ($tsml_feedback_addresses as $address) {?>
									<tr>
										<td><?php echo $address?></td>
										<td>
											<form method="post" action="edit.php?post_type=tsml_meeting&page=import">
												<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
												<input type="hidden" name="tsml_remove_feedback_address" value="<?php echo $address?>">
												<span class="dashicons dashicons-no-alt"></span>
											</form>
										</td>
									</tr>
									<?php }?>
								</table>
								<?php }?>
								<form method="post" action="edit.php?post_type=tsml_meeting&page=import">
									<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
									<input type="email" name="tsml_add_feedback_address" placeholder="email@example.org">
									<input type="submit" class="button" value="Add">
								</form>
							</div>
						</div>
						<?php /*
						<div class="postbox" id="get_feedback">
							<div class="inside">
								<h3>Get Notified</h3>
								<p>Receive notifications of meeting changes by adding email addresses below:</p>
								<?php if (!empty($tsml_notification_addresses)) {?>
								<table>
									<?php foreach ($tsml_notification_addresses as $address) {?>
									<tr>
										<td><?php echo $address?></td>
										<td>
											<form method="post" action="edit.php?post_type=tsml_meeting&page=import">
												<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
												<input type="hidden" name="tsml_remove_notification_address" value="<?php echo $address?>">
												<span class="dashicons dashicons-no-alt"></span>
											</form>
										</td>
									</tr>
									<?php }?>
								</table>
								<?php }?>
								<form method="post" action="edit.php?post_type=tsml_meeting&page=import">
									<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
									<input type="email" name="tsml_add_notification_address" placeholder="email@example.org">
									<input type="submit" class="button" value="Add">
								</form>
							</div>
						</div>
						<?php */
						if ($tsml_program == 'aa') {?>
						<div class="postbox" id="try_the_apps">
							<div class="inside">
								<h3><?php _e('Try the Apps!', '12-step-meeting-list')?></h3>
								<p>Want to have your meetings listed in a simple, free mobile app? <a href="https://meetingguide.org/" target="_blank">Many areas are currently participating</a>,
									but we always want more! No extra effort is required; simply continue to update your meetings in Wordpress and the updates will flow down to app users.
								<p class="buttons">
									<a href="https://itunes.apple.com/us/app/meeting-guide/id1042822181">
										<img src="<?php echo plugin_dir_url(__FILE__)?>../assets/img/apple.svg">
									</a>
									<a href="https://play.google.com/store/apps/details?id=org.meetingguide.app">
										<img src="<?php echo plugin_dir_url(__FILE__)?>../assets/img/google.svg">
									</a>
								</p>
								<p>To get involved, please get in touch by emailing <a href="mailto:app@meetingguide.org">app@meetingguide.org</a>.</p>
							</div>
						</div>
						<?php }?>
						<div class="postbox">
							<div class="inside">
								<h3><?php _e('About this Plugin', '12-step-meeting-list')?></h3>
								<p>This plugin was developed by AA volunteers in <a href="http://aasanjose.org/technology">Santa 
									Clara County</a> to help provide accessible, accurate information about meetings to 
									those who need it.</p>
								<p>Get in touch by sending email to <a href="mailto:wordpress@aasanjose.org">wordpress@aasanjose.org</a>.</p>
							</div>
						</div>
				    </div>
			    </div>
		    </div>
		    
		<?php
	}
}
