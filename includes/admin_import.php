<?php
	
//import CSV file and handle settings
function tmsl_import_page() {
	global $wpdb, $tsml_types, $tsml_programs, $tsml_program, $tsml_nonce, $tsml_days, $tsml_feedback_addresses, $tsml_notification_addresses;

	$error = false;
	
	//if posting a CSV, check for errors and add it to the import buffer
	if (isset($_FILES['tsml_import']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		ini_set('auto_detect_line_endings', 1); //to handle mac \r line endings
		$extension = explode('.', strtolower($_FILES['tsml_import']['name']));
		$extension = end($extension);
		if ($_FILES['tsml_import']['error'] > 0) {
			$error = __('File upload error #' . $_FILES['tsml_import']['error'], '12-step-meeting-list');
		} elseif (empty($extension)) {
			$error = __('Uploaded file did not have a file extension. Please add .csv to the end of the file name.', '12-step-meeting-list');
		} elseif ($extension != 'csv') {
			$error = sprintf(__('Please upload a csv file. Your file ended in .%s.', '12-step-meeting-list'), $extension);
		} elseif (!$handle = fopen($_FILES['tsml_import']['tmp_name'], 'r')) {
			$error = __('Error opening CSV file', '12-step-meeting-list');
		} else {
			
			//extract meetings from CSV
			while (($data = fgetcsv($handle, 1000, ',')) !== false) {
				//skip empty rows
				if (strlen(trim(implode($data)))) {
					$meetings[] = $data;
				}
			}

			//allow theme-defined function to reformat CSV ahead of import (for New Hampshire)
			if (function_exists('tsml_import_reformat')) {
				$meetings = tsml_import_reformat($meetings);
			}
			
			//convert the array to UTF-8
			array_walk_recursive($meetings, 'tsml_format_utf8');

			//trim everything
			array_walk_recursive($meetings, 'trim');
			
			//crash if no data
			if (count($meetings) < 2) {
				$error = __('Nothing was imported because no data rows were found.', '12-step-meeting-list');
			} else {
				//get header
				$header = array_shift($meetings);
				$header = array_map('sanitize_title_with_dashes', $header);
				$header = str_replace('-', '_', $header);
				$header_count = count($header);
				$row_counter = 1;
				
				//check header for required fields
				if (!in_array('address', $header) && !in_array('city', $header)) {
					$error = __('Either Address or City is required.', '12-step-meeting-list');
				} else {
					
					//uppercasing for value matching later
					$upper_types = array_map('strtoupper', $tsml_types[$tsml_program]);
						
					//loop through data and sanitize
					foreach ($meetings as &$meeting) {
						$row_counter++;
				
						//sanitize fields
						$meeting = array_map('tsml_import_sanitize_field', $meeting);
						
						//check length
						if ($header_count > count($meeting)) {
							$meeting = array_pad($meeting, $header_count, null);
						}
						
						//associate, sanitize
						$meeting = array_combine($header, $meeting);
						foreach ($meeting as $key => $value) {
							if (in_array($key, array('notes', 'location_notes', 'group_notes'))) {
								$meeting[$key] = sanitize_text_area($value);
							} else {
								$meeting[$key] = sanitize_text_field($value);
							}
						}

						//if location (name) is missing, use address
						if (empty($meeting['location'])) {
							$meeting['location'] = empty($meeting['address']) ? __('Meeting Location', '12-step-meeting-list') : $meeting['address'];
						}
					
						//sanitize time & day
						if (empty($meeting['time']) || empty($meeting['day'])) {
							$meeting['time'] = $meeting['end_time'] = $meeting['day'] = ''; //by appointment
				
							//if meeting name missing, use location
							if (empty($meeting['name'])) $meeting['name'] = $meeting['location'] . __(' by Appointment', '12-step-meeting-list');
						} else {
							$meeting['time'] = tsml_format_time_reverse($meeting['time']);
							if (!empty($meeting['end_time'])) $meeting['end_time'] = tsml_format_time_reverse($meeting['end_time']);
							
							//if meeting name missing, use location, day, and time
							if (empty($meeting['name'])) {
								$meeting['name'] = $meeting['location'];
								if (in_array($meeting['day'], $tsml_days)) $meeting['name'] .= ' ' . $tsml_days[$meeting['day']] . 's';
								$meeting['name'] .= ' at ' . tsml_format_time($meeting['time']);
							}
						}
				
						//sanitize address, remove everything starting with @ (consider other strings as well?)
						if (!empty($meeting['address']) && $pos = strpos($meeting['address'], '@')) $meeting['address'] = trim(substr($meeting['address'], 0, $pos));
						
						//google prefers USA for geocoding
						if (!empty($meeting['country']) && $meeting['country'] == 'US') $meeting['country'] = 'USA'; 
						
						//build address
						$address = array();
						if (!empty($meeting['address'])) $address[] = $meeting['address'];
						if (!empty($meeting['city'])) $address[] = $meeting['city'];
						if (!empty($meeting['state'])) $address[] = $meeting['state'];
						if (!empty($meeting['postal_code'])) {
							if ((strlen($meeting['postal_code']) < 5) && ($meeting['country'] == 'USA')) $meeting['postal_code'] = str_pad($meeting['postal_code'], 5, '0', STR_PAD_LEFT);
							$address[] = $meeting['postal_code'];	
						}
						if (!empty($meeting['country'])) $address[] = $meeting['country'];
						$meeting['formatted_address'] = implode(', ', $address);

						//notes
						if (empty($meeting['notes'])) $meeting['notes'] = '';
						if (empty($meeting['location_notes'])) $meeting['location_notes'] = '';
						if (empty($meeting['group_notes'])) $meeting['group_notes'] = '';
				
						//updated
						$meeting['updated'] = empty($meeting['updated']) ? time() : strtotime($meeting['updated']);
						$meeting['post_modified'] = date('Y-m-d H:i:s', $meeting['updated']);
						$meeting['post_modified_gmt'] = get_gmt_from_date($meeting['updated']);
						
						//default region to city if not specified
						if (empty($meeting['region']) && !empty($meeting['city'])) $meeting['region'] = $meeting['city'];

						//sanitize types
						$types = explode(',', $meeting['types']);
						$meeting['types'] = $unused_types = array();
						foreach ($types as $type) {
							if (in_array(trim(strtoupper($type)), array_values($upper_types))) {
								$meeting['types'][] = array_search(trim(strtoupper($type)), $upper_types);
							} else {
								$unused_types[] = $type;
							}
						}
						
						//don't let a meeting be both open and closed
						if (in_array('C', $meeting['types']) && in_array('O', $meeting['types'])) {
							$meeting['types'] = array_diff($meeting['types'], array('C'));
						}
						
						//append unused types to notes
						if (count($unused_types)) {
							if (!empty($meeting['notes'])) $meeting['notes'] .= str_repeat(PHP_EOL, 2);
							$meeting['notes'] .= implode(', ', $unused_types);
						}

						//clean up
						foreach(array('address', 'city', 'state', 'postal_code', 'country', 'updated') as $key) {
							if (isset($meeting[$key])) unset($meeting[$key]);
						}
						
						//preserve row number for errors later
						$meeting['row'] = $row_counter;
						
					}
					
					//prepare import buffer in wp_options
					update_option('tsml_import_buffer', $meetings, false);
					
					//run deletes
					if ($_POST['delete'] == 'regions') {
						
						//get all regions present in array
						$regions = array();
						foreach ($meetings as $meeting) {
							$regions[] = empty($meeting['sub_region']) ? $meeting['region'] : $meeting['sub_region'];
						}
						
						//get locations for those meetings
						$location_ids = get_posts(array(
							'post_type'			=> 'tsml_location',
							'numberposts'		=> -1,
							'fields'			=> 'ids',
							'tax_query'			=> array(
								array(
									'taxonomy'	=> 'tsml_region',
									'field'		=> 'name',
									'terms'		=> array_unique($regions),
								),
							),
						));
						
						//get posts for those meetings
						$meeting_ids = get_posts(array(
							'post_type'			=> 'tsml_meeting',
							'numberposts'		=> -1,
							'fields'			=> 'ids',
							'post_parent__in'	=> $location_ids,
						));

						tsml_delete($meeting_ids);
						
						tsml_delete_orphans();
		
					} elseif ($_POST['delete'] == 'all') {
						//must be done with SQL statements becase there could be thousands of records to delete
						if ($post_ids = $wpdb->get_col('SELECT id FROM ' . $wpdb->posts . ' WHERE post_type IN ("tsml_meeting", "tsml_location", "tsml_group")')) {
							tsml_delete($post_ids);
						}
						if ($term_ids = implode(',', $wpdb->get_col('SELECT term_id FROM ' . $wpdb->term_taxonomy . ' WHERE taxonomy = "tsml_region"'))) {
							$wpdb->query('DELETE FROM ' . $wpdb->terms . ' WHERE term_id IN (' . $term_ids . ')');
							$wpdb->query('DELETE FROM ' . $wpdb->term_taxonomy . ' WHERE term_id IN (' . $term_ids . ')');
						}
					}
				}
			}
		}
	} else {
		//not uploading CSV, check for existing import buffer
		$meetings = get_option('tsml_import_buffer', array());
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
		if (!is_email($email)) echo '<div class="notice notice-error"><p><code>' . $email . '</code> is not a valid email address. Please try again.</p></div>';
		$tsml_feedback_addresses[] = $email;
		$tsml_feedback_addresses = array_unique($tsml_feedback_addresses);
		sort($tsml_feedback_addresses);
		update_option('tsml_feedback_addresses', $tsml_feedback_addresses);
		echo '<div class="notice notice-success"><p>Feedback address added.</p></div>';
	}
	
	//remove a feedback email
	if (!empty($_POST['tsml_remove_feedback_address']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		$email = sanitize_text_field($_POST['tsml_remove_feedback_address']);
		if (($key = array_search($email, $tsml_feedback_addresses)) !== false) {
			unset($tsml_feedback_addresses[$key]);
		} else {
			echo '<div class="notice notice-error"><p><code>' . $email . '</code> was not found in the list of addresses. Please try again.</p></div>';
		}
		if (empty($tsml_feedback_addresses)) {
			delete_option('tsml_feedback_addresses');
		} else {
			update_option('tsml_feedback_addresses', $tsml_feedback_addresses);
		}
		echo '<div class="notice notice-success"><p>Feedback address removed.</p></div>';
	}
			
	//add a notification email
	if (!empty($_POST['tsml_add_notification_address']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		$email = sanitize_text_field($_POST['tsml_add_notification_address']);
		if (!is_email($email)) echo '<div class="notice notice-error"><p><code>' . $email . '</code> is not a valid email address. Please try again.</p></div>';
		$tsml_notification_addresses[] = $email;
		$tsml_notification_addresses = array_unique($tsml_notification_addresses);
		sort($tsml_notification_addresses);
		update_option('tsml_notification_addresses', $tsml_notification_addresses);
		echo '<div class="notice notice-success"><p>Notification address added.</p></div>';
	}
	
	//remove a notification email
	if (!empty($_POST['tsml_remove_notification_address']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		$email = sanitize_text_field($_POST['tsml_remove_notification_address']);
		if (($key = array_search($email, $tsml_notification_addresses)) !== false) {
			unset($tsml_notification_addresses[$key]);
		} else {
			echo '<div class="notice notice-error"><p><code>' . $email . '</code> was not found in the list of addresses. Please try again.</p></div>';
		}
		if (empty($tsml_notification_addresses)) {
			delete_option('tsml_notification_addresses');
		} else {
			update_option('tsml_notification_addresses', $tsml_notification_addresses);
		}
		echo '<div class="notice notice-success"><p>Notification address removed.</p></div>';
	}
	?>
	<div class="wrap">
		<h2><?php _e('Import & Settings', '12-step-meeting-list')?></h2>
		
		<div id="poststuff">
			<div id="post-body" class="columns-2">
				<div id="post-body-content">
					
					<?php if ($error) {?>
					<div class="notice notice-error inline">
						<p><?php echo $error?></p>
					</div>
					<?php } elseif ($total = count($meetings)) {?>
					<div id="tsml_import_progress" class="progress" data-total="<?php echo $total?>">
						<div class="progress-bar"></div>
					</div>
					<ol id="tsml_import_errors" class="notice notice-error inline hidden"></ol>
					<?php }?>
					
					<div class="postbox">
						<div class="inside">
							<h3><?php _e('Import CSV', '12-step-meeting-list')?></h3>
							<form method="post" action="edit.php?post_type=tsml_meeting&page=import" enctype="multipart/form-data">
								<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
								<input type="file" name="tsml_import"></textarea>
								<p>
									<?php _e('When importing...')?><br>
									<?php if (empty($_POST['delete'])) $_POST['delete'] = 'nothing'?>
									<label><input type="radio" name="delete" value="nothing" <?php if ($_POST['delete'] == 'nothing') {?> checked<?php }?>> <?php _e('Don\'t delete anything')?></label><br>
									<label><input type="radio" name="delete" value="regions" <?php if ($_POST['delete'] == 'regions') {?> checked<?php }?>> <?php _e('Delete only the meetings, locations and groups for the regions present in this CSV')?></label><br>
									<label><input type="radio" name="delete" value="all" <?php if ($_POST['delete'] == 'all') {?> checked<?php }?>> <?php _e('Delete all meetings, locations, groups, and regions prior to import')?></label>
								</p>
								<p><input type="submit" class="button button-primary" value="Begin"></p>
							</form>
						</div>
					</div>

					<div class="postbox">
						<div class="inside">
							<h3><?php _e('CSV Format Specs', '12-step-meeting-list')?></h3>
							<p><a href="<?php echo plugin_dir_url(__FILE__) . '../template.csv'?>">Here is a sample file</a> you can use as a template. Save it as a comma-delimited CSV before uploading it. The header row should kept in place.</p>
							<ul class="ul-disc">
								<li><strong>Time</strong>, if present, should be in a standard date format such as 6:00 AM or 06:00. Non-standard or empty dates will be imported as 'by appointment.'</li>
								<li><strong>End Time</strong>, if present, should be in a standard date format such as 6:00 AM or 06:00.</li>
								<li><strong>Day</strong> if present, should either Sunday, Monday, Tuesday, Wednesday, Thursday, Friday, or Saturday. Meetings that occur on multiple days should be listed separately. 'Daily' or 'Mondays' will not work. Non-standard days will be imported as 'by appointment.'</li>
								<li><strong>Name</strong> is the name of the meeting, and is optional, although it's valuable information for the user. If it's missing, a name will be created by combining the location, day, and time.</li>
								<li><strong>Location</strong> is the name of the location, and is optional. Generally it's the group or building name. If it's missing, the address will be used. In the event that there are multiple location names for the same address, the first location name will be used.</li>
									<li><strong>Address</strong> is strongly encouraged and will be corrected by Google, so it may look different afterward. Ideally, every address for the same location should be exactly identical, and not contain extra information about the address, such as the building name or descriptors like 'around back.'</li>
								<li>If <strong>Address</strong> is specified, then <strong>City</strong>, <strong>State</strong>, and <strong>Country</strong> are optional, but they might be useful if your addresses sound ambiguous to Google. If address is not specified, then these fields are required.</li>
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
						</div>
					</div>
				</div>
				<div id="postbox-container-1" class="postbox-container">

					<?php if (version_compare(PHP_VERSION, '5.4') < 0) {?>
					<div class="notice notice-warning inline">
						<p><?php echo sprintf(__('You are running PHP <strong>%s</strong>, while <a href="%s" target="_blank">WordPress recommends</a> PHP %s or above. This can cause unexpected errors. Please contact your host and upgrade!'), PHP_VERSION, 'https://wordpress.org/about/requirements/', '5.6')?></p>
					</div>
					<?php }?>

					<div class="postbox">
						<div class="inside">
							<h3><?php _e('Choose Your Program', '12-step-meeting-list')?></h3>
							<form method="post" action="edit.php?post_type=tsml_meeting&page=import">
							<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
							<p><?php echo sprintf(__('This determines which meeting types are available. If your program is not listed, please <a href="%s">let us know</a> about your program and what types of meetings you have (Open, Closed, Topic Discussion, etc).'), TSML_CONTACT_LINK)?>
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
							<p><?php printf(__('Your public meetings page is <a href="%s">right here</a>. Link that page from your site\'s nav menu to make it visible to the public.'), get_post_type_archive_link('tsml_meeting'))?></p>
							<p><?php printf(__('You can also download your meetings in <a href="%s">CSV format</a>.'), admin_url('admin-ajax.php') . '?action=csv')?></p>
							<?php
							$meetings = tsml_count_meetings();
							$locations = tsml_count_locations();
							$regions = tsml_count_regions();
							$groups = tsml_count_groups();
							?>
							<div id="tsml_counts"<?php if (($meetings + $locations + $groups + $regions) == 0) {?> class="hidden"<?php }?>>
								<p><?php _e('You have:')?></p>
								<ul class="ul-disc">
									<li class="meetings<?php if (!$meetings) {?> hidden<?php }?>">
										<?php printf(_n('%s meeting', '%s meetings', $meetings), number_format_i18n($meetings))?>
									</li>
									<li class="locations<?php if (!$locations) {?> hidden<?php }?>">
										<?php printf(_n('%s location', '%s locations', $locations), number_format_i18n($locations))?>
									</li>
									<li class="groups<?php if (!$groups) {?> hidden<?php }?>">
										<?php printf(_n('%s group', '%s groups', $groups), number_format_i18n($groups))?>
									</li>
									<li class="regions<?php if (!$regions) {?> hidden<?php }?>">
										<?php printf(_n('%s region', '%s regions', $regions), number_format_i18n($regions))?>
									</li>
								</ul>
							</div>
							<?php if ($groups) {?>
								<p><?php printf(__('Want to send a mass email to your group contacts? <a href="%s" target="_blank">Click here</a> to see their email addresses.'), admin_url('admin-ajax.php') . '?action=contacts')?></p>
							<?php }?>
						</div>
					</div>
					<div class="postbox admin_contacts">
						<div class="inside">
							<h3><?php _e('Want User Feedback?')?></h3>
							<p><?php _e('Enable a meeting info feedback form by adding email addresses below.')?></p>
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
								<input type="submit" class="button" value="<?php _e('Add')?>">
							</form>
						</div>
					</div>
					<div class="postbox admin_contacts">
						<div class="inside">
							<h3><?php _e('Get Notified')?></h3>
							<p><?php _e('Receive notifications of meeting changes at the email addresses below.')?></p>
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
								<input type="submit" class="button" value="<?php _e('Add')?>">
							</form>
						</div>
					</div>
					<?php
					if ($tsml_program == 'aa') {?>
					<div class="postbox" id="try_the_apps">
						<div class="inside">
							<h3><?php _e('Try the Apps!', '12-step-meeting-list')?></h3>
							<p><?php echo sprintf(__('Want to have your meetings listed in a simple, free mobile app? <a href="%s" target="_blank">%d areas are currently participating</a>. No extra effort is required; simply continue to update your meetings here and the updates will flow down to app users.'), 'https://meetingguide.org/', 46)?></p>
							<p class="buttons">
								<a href="https://itunes.apple.com/us/app/meeting-guide/id1042822181">
									<img src="<?php echo plugin_dir_url(__FILE__)?>../assets/img/apple.svg">
								</a>
								<a href="https://play.google.com/store/apps/details?id=org.meetingguide.app">
									<img src="<?php echo plugin_dir_url(__FILE__)?>../assets/img/google.svg">
								</a>
							</p>
							<p><?php echo sprintf(__('To get involved, please <a href="%s">get in touch</a>.'), TSML_CONTACT_LINK)?></p>
						</div>
					</div>
					<?php } else {?>
					<div class="postbox">
						<div class="inside">
							<h3><?php _e('About this Plugin', '12-step-meeting-list')?></h3>
							<p><?php echo sprintf(__('This plugin was developed by AA volunteers in <a href="%s" target="_blank">Santa Clara County</a> to help provide accessible, accurate information about meetings to those who need it.'), 'https://aasanjose.org/central-office/technology')?></p>
							<p><?php echo sprintf(__('If you would like to help out with development, <a href="%s" target="_blank">visit us on GitHub</a>.'), 'https://github.com/meeting-guide/12-step-meeting-list')?>
						</div>
					</div>
					<?php }?>
				</div>
			</div>
		</div>
		
	<?php
}
