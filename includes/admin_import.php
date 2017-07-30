<?php
	
//import CSV file and handle settings
function tmsl_import_page() {
	global $wpdb, $tsml_types, $tsml_data_sources, $tsml_programs, $tsml_program, $tsml_nonce, $tsml_days, $tsml_feedback_addresses, $tsml_notification_addresses, $tsml_distance_units;

	$error = false;
	
	//if posting a CSV, check for errors and add it to the import buffer
	if (isset($_FILES['tsml_import']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		ini_set('auto_detect_line_endings', 1); //to handle mac \r line endings
		$extension = explode('.', strtolower($_FILES['tsml_import']['name']));
		$extension = end($extension);
		if ($_FILES['tsml_import']['error'] > 0) {
			if ($_FILES['tsml_import']['error'] == 1) {
				$error = __('The uploaded file exceeds the <code>upload_max_filesize</code> directive in php.ini.', '12-step-meeting-list');
			} elseif ($_FILES['tsml_import']['error'] == 2) {
				$error = __('The uploaded file exceeds the <code>MAX_FILE_SIZE</code> directive that was specified in the HTML form.', '12-step-meeting-list');
			} elseif ($_FILES['tsml_import']['error'] == 3) {
				$error = __('The uploaded file was only partially uploaded.', '12-step-meeting-list');
			} elseif ($_FILES['tsml_import']['error'] == 4) {
				$error = __('No file was uploaded.', '12-step-meeting-list');
			} elseif ($_FILES['tsml_import']['error'] == 6) {
				$error = __('Missing a temporary folder.', '12-step-meeting-list');
			} elseif ($_FILES['tsml_import']['error'] == 7) {
				$error = __('Failed to write file to disk.', '12-step-meeting-list');
			} elseif ($_FILES['tsml_import']['error'] == 8) {
				$error = __('A PHP extension stopped the file upload.', '12-step-meeting-list');
			} else {
				$error = sprintf(__('File upload error #%d', '12-step-meeting-list'), $_FILES['tsml_import']['error']);
			}
		} elseif (empty($extension)) {
			$error = __('Uploaded file did not have a file extension. Please add .csv to the end of the file name.', '12-step-meeting-list');
		} elseif (!in_array($extension, array('csv', 'txt'))) {
			$error = sprintf(__('Please upload a csv file. Your file ended in .%s.', '12-step-meeting-list'), $extension);
		} elseif (!$handle = fopen($_FILES['tsml_import']['tmp_name'], 'r')) {
			$error = __('Error opening CSV file', '12-step-meeting-list');
		} else {
			
			//extract meetings from CSV
			while (($data = fgetcsv($handle, 3000, ',')) !== false) {
				//skip empty rows
				if (strlen(trim(implode($data)))) {
					$meetings[] = $data;
				}
			}

			//do import reformatting
			$required_fnv_columns = array('ServiceNumber', 'GroupName', 'CountryCode', 'City', 'District', 'Website', 'DateChanged', 'PrimaryFirstName', 'SecondaryPrimaryEmail', 'Meeting1Addr1', 'Meeting1SUNTimes');
			$missing_fnv_columns = array_diff($required_fnv_columns, $meetings[0]);
			if (count($meetings) && is_array($meetings[0]) && empty($missing_fnv_columns))  {
				//this is FNV data, reformat it automatically
				$meetings = tsml_import_reformat_fnv($meetings);
			} elseif (function_exists('tsml_import_reformat')) {
				//allow theme-defined function to reformat CSV prior to import (for New Hampshire)
				$meetings = tsml_import_reformat($meetings);
			}
			
			//crash if no data
			if (count($meetings) < 2) {
				$error = __('Nothing was imported because no data rows were found.', '12-step-meeting-list');
			} else {
				//get header
				$header = array_shift($meetings);
				$header = array_map('sanitize_title_with_dashes', $header);
				$header = str_replace('-', '_', $header);
				$header_count = count($header);
				
				//check header for required fields
				if (!in_array('address', $header) && !in_array('city', $header)) {
					$error = __('Either Address or City is required.', '12-step-meeting-list');
				} else {
					
					//loop through data and convert to array
					foreach ($meetings as &$meeting) {
						//check length
						if ($header_count > count($meeting)) {
							$meeting = array_pad($meeting, $header_count, null);
						} elseif ($header_count < count($meeting)) {
							$meeting = array_slice($meeting, 0, $header_count);
						}
						
						//associate
						$meeting = array_combine($header, $meeting);
					}

					//dd($meetings);
					
					//import into buffer, also done this way in data source import
					tsml_import_buffer_set($meetings);
					
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
					
					} elseif ($_POST['delete'] == 'no_data_source') {
						
						tsml_delete(get_posts(array(
							'post_type'		=> 'tsml_meeting',
							'numberposts'	=> -1,
							'fields'			=> 'ids',
							'meta_query'		=> array(
								array(
									'key' => 'data_source',
									'compare' => 'NOT EXISTS',
									'value' => '',
								),
							),
						)));

						tsml_delete_orphans();
						
					} elseif ($_POST['delete'] == 'all') {
						
						tsml_delete('everything');
						
					}
				}
			}
		}
	}
		
	//add data source
	if (!empty($_POST['tsml_add_data_source']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		
		//sanitize URL
		$_POST['tsml_add_data_source'] = esc_url_raw($_POST['tsml_add_data_source'], array('http', 'https'));
		
		//try fetching	
		$response = wp_remote_get($_POST['tsml_add_data_source'], array(
			'timeout' => 30,
			'sslverify' => false,
		));
		if (is_array($response) && !empty($response['body']) && ($body = json_decode($response['body'], true))) {

			//if already set, hard refresh
			if (array_key_exists($_POST['tsml_add_data_source'], $tsml_data_sources)) {
				tsml_delete(get_posts(array(
					'post_type'		=> 'tsml_meeting',
					'numberposts'	=> -1,
					'fields'			=> 'ids',
					'meta_query'		=> array(
						array(
							'key' => 'data_source',
							'value' => $_POST['tsml_add_data_source'],
							'compare' => '=',
						),
					),
				)));
				tsml_delete_orphans();
			}
			
			$tsml_data_sources[$_POST['tsml_add_data_source']] = array(
				'status' => 'OK',
				'last_import' => current_time('timestamp'),
				'count_meetings' => 0,
			);
			
			//import feed
			tsml_import_buffer_set($body, $_POST['tsml_add_data_source']);
			
			//save data source configuration
			update_option('tsml_data_sources', $tsml_data_sources);
			
			/*schedule cron
			if (!wp_next_scheduled('tsml_import_data_sources')) {
				wp_schedule_event(time(), 'hourly', 'tsml_import_data_sources');
			}*/
			
		} elseif (!is_array($response)) {
			
			tsml_alert(__('Invalid response, <pre>' . print_r($response, true) . '</pre>.', '12-step-meeting-list'), 'error');

		} elseif (empty($response['body'])) {
			
			tsml_alert(__('Data source gave an empty response, you might need to try again.', '12-step-meeting-list'), 'error');

		} else {

			switch (json_last_error()) {
				case JSON_ERROR_NONE:
					tsml_alert(__('JSON: no errors.', '12-step-meeting-list'), 'error');
					break;
				case JSON_ERROR_DEPTH:
					tsml_alert(__('JSON: Maximum stack depth exceeded.', '12-step-meeting-list'), 'error');
					break;
				case JSON_ERROR_STATE_MISMATCH:
					tsml_alert(__('JSON: Underflow or the modes mismatch.', '12-step-meeting-list'), 'error');
					break;
				case JSON_ERROR_CTRL_CHAR:
					tsml_alert(__('JSON: Unexpected control character found.', '12-step-meeting-list'), 'error');
					break;
				case JSON_ERROR_SYNTAX:
					tsml_alert(__('JSON: Syntax error, malformed JSON.', '12-step-meeting-list'), 'error');
					break;
				case JSON_ERROR_UTF8:
					tsml_alert(__('JSON: Malformed UTF-8 characters, possibly incorrectly encoded.', '12-step-meeting-list'), 'error');
					break;
				default:
					tsml_alert(__('JSON: Unknown error.', '12-step-meeting-list'), 'error');
					break;
			}
			
		}
	}
	
	//check for existing import buffer
	$meetings = get_option('tsml_import_buffer', array());
	
	//remove data source
	if (!empty($_POST['tsml_remove_data_source'])) {

		//sanitize URL
		$_POST['tsml_remove_data_source'] = esc_url_raw($_POST['tsml_remove_data_source'], array('http', 'https'));

		if (array_key_exists($_POST['tsml_remove_data_source'], $tsml_data_sources)) {
			
			//remove all meetings for this data source
			tsml_delete(get_posts(array(
				'post_type'		=> 'tsml_meeting',
				'numberposts'	=> -1,
				'fields'			=> 'ids',
				'meta_query'		=> array(
					array(
						'key' => 'data_source',
						'value' => $_POST['tsml_remove_data_source'],
						'compare' => '=',
					),
				),
			)));
			
			//clean up orphaned locations & groups
			tsml_delete_orphans();
			
			//remove data source
			unset($tsml_data_sources[$_POST['tsml_remove_data_source']]);
			update_option('tsml_data_sources', $tsml_data_sources);
			
			//unschedule cron?
			if (empty($tsml_data_sources) && wp_next_scheduled('tsml_import_data_sources')) {
				wp_clear_scheduled_hook('tsml_import_data_sources');
			}
			
			tsml_alert(__('Data source removed.', '12-step-meeting-list'));
		}
	}
	
	//change program
	if (!empty($_POST['tsml_program']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		$tsml_program = sanitize_text_field($_POST['tsml_program']);
		update_option('tsml_program', $tsml_program);
		tsml_alert(__('Program setting updated.', '12-step-meeting-list'));
	}
		
	//change distance units
	if (!empty($_POST['tsml_distance_units']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		$tsml_distance_units = ($_POST['tsml_distance_units'] == 'mi') ? 'mi' : 'km';
		update_option('tsml_distance_units', $tsml_distance_units);
		tsml_alert(__('Distance units updated.', '12-step-meeting-list'));
	}
		
	//add a feedback email
	if (!empty($_POST['tsml_add_feedback_address']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		$email = sanitize_text_field($_POST['tsml_add_feedback_address']);
		if (!is_email($email)) {
			//theoretically should never get here, because WordPress checks entry first
			tsml_alert(sprintf(esc_html__('<code>%s</code> is not a valid email address. Please try again.', '12-step-meeting-list'), $email), 'error');
		} else {
			$tsml_feedback_addresses[] = $email;
			$tsml_feedback_addresses = array_unique($tsml_feedback_addresses);
			sort($tsml_feedback_addresses);
			update_option('tsml_feedback_addresses', $tsml_feedback_addresses);
			tsml_alert(__('Feedback address added.', '12-step-meeting-list'));
		}
	}
	
	//remove a feedback email
	if (!empty($_POST['tsml_remove_feedback_address']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		$email = sanitize_text_field($_POST['tsml_remove_feedback_address']);
		if (($key = array_search($email, $tsml_feedback_addresses)) !== false) {
			unset($tsml_feedback_addresses[$key]);
			if (empty($tsml_feedback_addresses)) {
				delete_option('tsml_feedback_addresses');
			} else {
				update_option('tsml_feedback_addresses', $tsml_feedback_addresses);
			}
			tsml_alert(__('Feedback address removed.', '12-step-meeting-list'));
		} else {
			//theoretically should never get here, because user is choosing from a list
			tsml_alert(sprintf(esc_html__('<p><code>%s</code> was not found in the list of addresses. Please try again.</p>', '12-step-meeting-list'), $email), 'error');
		}
	}
			
	//add a notification email
	if (!empty($_POST['tsml_add_notification_address']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		$email = sanitize_text_field($_POST['tsml_add_notification_address']);
		if (!is_email($email)) {
			//theoretically should never get here, because WordPress checks entry first
			tsml_alert(sprintf(esc_html__('<p><code>%s</code> is not a valid email address. Please try again.</p>', '12-step-meeting-list'), $email), 'error');
		} else {
			$tsml_notification_addresses[] = $email;
			$tsml_notification_addresses = array_unique($tsml_notification_addresses);
			sort($tsml_notification_addresses);
			update_option('tsml_notification_addresses', $tsml_notification_addresses);
			tsml_alert(__('Notification address added.', '12-step-meeting-list'));
		}
	}
	
	//remove a notification email
	if (!empty($_POST['tsml_remove_notification_address']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
		$email = sanitize_text_field($_POST['tsml_remove_notification_address']);
		if (($key = array_search($email, $tsml_notification_addresses)) !== false) {
			unset($tsml_notification_addresses[$key]);
			if (empty($tsml_notification_addresses)) {
				delete_option('tsml_notification_addresses');
			} else {
				update_option('tsml_notification_addresses', $tsml_notification_addresses);
			}
			tsml_alert(__('Notification address removed.', '12-step-meeting-list'));
		} else {
			//theoretically should never get here, because user is choosing from a list
			tsml_alert(sprintf(esc_html__('<p><code>%s</code> was not found in the list of addresses. Please try again.</p>', '12-step-meeting-list'), $email), 'error');
		}
	}
	?>
	<div class="wrap">
		<h2><?php _e('Import & Settings', '12-step-meeting-list')?></h2>
		
		<div id="poststuff">
			<div id="post-body" class="columns-2">
				<div id="post-body-content">
					
					<?php if ($error) {?>
					<div class="error inline">
						<p><?php echo $error?></p>
					</div>
					<?php } elseif ($total = count($meetings)) {?>
					<div id="tsml_import_progress" class="progress" data-total="<?php echo $total?>">
						<div class="progress-bar"></div>
					</div>
					<ol id="tsml_import_errors" class="error inline hidden"></ol>
					<?php }?>
					
					<div class="postbox">
						<div class="inside">
							<h3><?php _e('Import CSV', '12-step-meeting-list')?></h3>
							<form method="post" action="<?php echo $_SERVER['REQUEST_URI']?>" enctype="multipart/form-data">
								<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
								<input type="file" name="tsml_import">
								<p>
									<?php _e('When importing...', '12-step-meeting-list')?><br>
									<?php 
									$delete_options = array(
										'nothing'	=> __('don\'t delete anything', '12-step-meeting-list'),
										'regions'	=> __('delete only the meetings, locations, and groups for the regions present in this CSV', '12-step-meeting-list'),
										'all' 		=> __('delete all meetings, locations, groups, and regions', '12-step-meeting-list'),
									);
									if (!empty($tsml_data_sources)) {
										$delete_options['no_data_source'] = __('delete all meetings, locations, and groups not from a data source', '12-step-meeting-list');
									}
									$delete_selected = (empty($_POST['delete']) || !array_key_exists($_POST['delete'], $delete_options)) ? 'nothing' : $_POST['delete'];
									foreach ($delete_options as $key => $value) {?>
										<label><input type="radio" name="delete" value="<?php echo $key?>" <?php checked($key, $delete_selected)?>> <?php echo $value?></label><br>
									<?php }?>
								</p>
								<p><input type="submit" class="button button-primary" value="<?php _e('Begin', '12-step-meeting-list')?>"></p>
							</form>
							<details>
								<summary><strong><?php _e('Spreadsheet Example & Specs', '12-step-meeting-list')?></strong></summary>
								<section>
									<p><a href="<?php echo plugin_dir_url(__FILE__) . '../template.csv'?>" class="button button-large"><span class="dashicons dashicons-media-spreadsheet"></span><?php _e('Example spreadsheet', '12-step-meeting-list')?></a></p>
									<ul class="ul-disc">
										<li><?php _e('<strong>Time</strong>, if present, should be in a standard date format such as 6:00 AM or 06:00. Non-standard or empty dates will be imported as "by appointment."', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>End Time</strong>, if present, should be in a standard date format such as 6:00 AM or 06:00.', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>Day</strong> if present, should either Sunday, Monday, Tuesday, Wednesday, Thursday, Friday, or Saturday. Meetings that occur on multiple days should be listed separately. \'Daily\' or \'Mondays\' will not work. Non-standard days will be imported as "by appointment."', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>Name</strong> is the name of the meeting, and is optional, although it\'s valuable information for the user. If it\'s missing, a name will be created by combining the location, day, and time.', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>Location</strong> is the name of the location, and is optional. Generally it\'s the group or building name. If it\'s missing, the address will be used. In the event that there are multiple location names for the same address, the first location name will be used.', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>Address</strong> is strongly encouraged and will be corrected by Google, so it may look different afterward. Ideally, every address for the same location should be exactly identical, and not contain extra information about the address, such as the building name or descriptors like "around back."', '12-step-meeting-list')?></li>
										<li><?php _e('If <strong>Address</strong> is specified, then <strong>City</strong>, <strong>State</strong>, and <strong>Country</strong> are optional, but they might be useful if your addresses sound ambiguous to Google. If address is not specified, then these fields are required.', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>Notes</strong> are freeform notes that are specific to the meeting. For example, "last Saturday is birthday night."', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>Region</strong> is user-defined and can be anything. Often this is a small municipality or neighborhood. Since these go in a dropdown, ideally you would have 10 to 20 regions, although it\'s ok to be over or under.', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>Sub Region</strong> makes the Region hierarchical; in San Jose we have sub regions for East San Jose, West San Jose, etc. New York City might have Manhattan be a Region, and Greenwich Village be a Sub Region.', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>Location Notes</strong> are freeform notes that will show up on every meeting that this location. For example, "Enter from the side."', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>Group</strong> is a way of grouping contacts. Meetings with the same group name will be linked and share contact information.', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>District</strong> is user-defined and can be anything, but should be a string rather than an integer, e.g. \'District 01\' rather than \'1.\' A group name must also be specified.', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>Sub District</strong> makes the District hierachical.', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>Website</strong> is optional, but a group name must also be specified.', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>Email</strong> is optional, but a group name must also be specified. This is a public email address.', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>Phone</strong> is optional, but a group name must also be specified. This is a public phone number.', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>Group Notes</strong> is for stuff like a short group history, or when the business meeting meets.', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>Contact 1/2/3 Name/Email/Phone</strong> (nine fields in total) are all optional, but will not be saved if there is not also a group name specified. By default, contact information is only visible inside the WordPress dashboard.', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>Last Contact</strong> is an optional date. A group name must be specified for it to be saved.', '12-step-meeting-list')?></li>
										<li><?php _e('<strong>Types</strong> should be a comma-separated list of the following options. This list is determined by which program is selected at right.', '12-step-meeting-list')?>
											<ul class="types">
											<?php foreach ($tsml_types[$tsml_program] as $value) {?>
												<li><?php echo $value?></li>
											<?php }?>
											</ul>
										</li>
									</ul>
									<?php if ($tsml_program == 'aa') {?>
									<p><?php _e('Additionally, you may import spreadsheets that are in the General Service Office\'s FNV database "Group Search Results" format. This format has 162 columns, the first column is <code>ServiceNumber</code>.', '12-step-meeting-list')?></p>
									<?php }?>
								</section>
							</details>
						</div>
					</div>
					<div class="postbox">
						<div class="inside">
							<h3><?php _e('Data Sources <small>Beta</small>', '12-step-meeting-list')?></h3>
							<p><?php printf(__('Data sources are JSON feeds that contain a website\'s public meeting data. They can be used to aggregate meetings from different sites into a single master list. 
								The data source for this website is <a href="%s" target="_blank">right here</a>. More information is available at the <a href="%s" target="_blank">Meeting Guide API Specification</a>.', '12-step-meeting-list'), admin_url('admin-ajax.php') . '?action=meetings', 'https://github.com/meeting-guide/api')?></p>
							<?php if (!empty($tsml_data_sources)) {?>
							<table>
								<thead>
									<tr>
										<th><?php _e('URL', '12-step-meeting-list')?></th>
										<th><?php _e('Last Update', '12-step-meeting-list')?></th>
										<th></th>
										<th><?php _e('Meetings', '12-step-meeting-list')?></th>
										<!--<th><?php _e('Status', '12-step-meeting-list')?></th>-->
										<th></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($tsml_data_sources as $feed => $properties) {?>
									<tr data-source="<?php echo $feed?>">
										<td><a href="<?php echo $feed?>" target="_blank"><?php echo $feed?></a></td>
										<td>
											<?php echo date(get_option('date_format') . ' ' . get_option('time_format'), $properties['last_import'])?>
										</td>
										<td>
											<form method="post" action="<?php echo $_SERVER['REQUEST_URI']?>">
												<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
												<input type="hidden" name="tsml_add_data_source" value="<?php echo $feed?>">
												<input type="submit" value="Refresh" class="button button-small">
											</form>
										</td>
										<td class="count_meetings"><?php echo number_format($properties['count_meetings'])?></td>
										<!--<td><?php echo $properties['status']?></td>-->
										<td>
											<form method="post" action="<?php echo $_SERVER['REQUEST_URI']?>">
												<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
												<input type="hidden" name="tsml_remove_data_source" value="<?php echo $feed?>">
												<span class="dashicons dashicons-no-alt"></span>
											</form>
										</td>
									</tr>
									<?php }?>
								</tbody>
							</table>
							<?php }?>
							<form class="columns" method="post" action="<?php echo $_SERVER['REQUEST_URI']?>">
								<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
								<div class="input">
									<input type="text" name="tsml_add_data_source" placeholder="https://">
								</div>
								<div class="btn">
									<input type="submit" class="button" value="<?php _e('Add Data Source', '12-step-meeting-list')?>">
								</div>
							</form>
						</div>
					</div>
				</div>
				<div id="postbox-container-1" class="postbox-container">

					<?php if (version_compare(PHP_VERSION, '5.4') < 0) {?>
					<div class="notice notice-warning inline">
						<p><?php printf(__('You are running PHP <strong>%s</strong>, while <a href="%s" target="_blank">WordPress recommends</a> PHP %s or above. This can cause unexpected errors. Please contact your host and upgrade!', '12-step-meeting-list'), PHP_VERSION, 'https://wordpress.org/about/requirements/', '5.6')?></p>
					</div>
					<?php }
					
					if (!is_ssl()) {?>
					<div class="notice notice-warning inline">
						<p><?php _e('If you enable SSL, your users will be able to search for meetings relative to their location.', '12-step-meeting-list')?></p>
					</div>
					<?php }?>

					<div class="postbox" id="settings">
						<div class="inside">
							<h3><?php _e('Settings', '12-step-meeting-list')?></h3>
							<p><?php printf(__('The program determines which meeting types are available. If your program isn\'t not listed, <a href="%s">let us know</a> what types of meetings it has (Open, Closed, Topic Discussion, etc).', '12-step-meeting-list'), TSML_CONTACT_LINK)?></p>
							<form method="post" action="<?php echo $_SERVER['REQUEST_URI']?>">
								<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
								<select name="tsml_program" onchange="this.form.submit()">
									<?php foreach ($tsml_programs as $key => $value) {?>
									<option value="<?php echo $key?>"<?php selected($tsml_program, $key)?>><?php echo $value?></option>
									<?php }?>
								</select>
							</form>
							<form method="post" action="<?php echo $_SERVER['REQUEST_URI']?>">
								<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
								<select name="tsml_distance_units" onchange="this.form.submit()">
								<?php 
								$distance_units = array(
									'km' => __('Kilometers', '12-step-meeting-list'),
									'mi' => __('Miles', '12-step-meeting-list'),	
								);
								foreach ($distance_units as $key => $value) {?>
								<option value="<?php echo $key?>"<?php selected($tsml_distance_units, $key)?>><?php echo $value?></option>
								<?php }?>
								</select>
							</form>
						</div>
					</div>
					<div class="postbox" id="wheres_my_info">
						<div class="inside">
							<h3><?php _e('Where\'s My Info?', '12-step-meeting-list')?></h3>
							<p><?php printf(__('Your public meetings page is <a href="%s">right here</a>. Link that page from your site\'s nav menu to make it visible to the public.', '12-step-meeting-list'), get_post_type_archive_link('tsml_meeting'))?></p>
							<p><?php printf(__('You can also download your meetings in <a href="%s">CSV format</a>.', '12-step-meeting-list'), admin_url('admin-ajax.php') . '?action=csv')?></p>
							<?php
							$meetings = tsml_count_meetings();
							$locations = tsml_count_locations();
							$regions = tsml_count_regions();
							$groups = tsml_count_groups();
							?>
							<div id="tsml_counts"<?php if (($meetings + $locations + $groups + $regions) == 0) {?> class="hidden"<?php }?>>
								<p><?php _e('You have:', '12-step-meeting-list')?></p>
								<ul class="ul-disc">
									<li class="meetings<?php if (!$meetings) {?> hidden<?php }?>">
										<?php printf(_n('%s meeting', '%s meetings', $meetings, '12-step-meeting-list'), number_format_i18n($meetings))?>
									</li>
									<li class="locations<?php if (!$locations) {?> hidden<?php }?>">
										<?php printf(_n('%s location', '%s locations', $locations, '12-step-meeting-list'), number_format_i18n($locations))?>
									</li>
									<li class="groups<?php if (!$groups) {?> hidden<?php }?>">
										<?php printf(_n('%s group', '%s groups', $groups, '12-step-meeting-list'), number_format_i18n($groups))?>
									</li>
									<li class="regions<?php if (!$regions) {?> hidden<?php }?>">
										<?php printf(_n('%s region', '%s regions', $regions, '12-step-meeting-list'), number_format_i18n($regions))?>
									</li>
								</ul>
							</div>
							<?php if ($groups) {?>
								<p><?php printf(__('Want to send a mass email to your group contacts? <a href="%s" target="_blank">Click here</a> to see their email addresses.', '12-step-meeting-list'), admin_url('admin-ajax.php') . '?action=contacts')?></p>
							<?php }?>
						</div>
					</div>
					<div class="postbox" id="want-user-feedback">
						<div class="inside">
							<h3><?php _e('Want User Feedback?', '12-step-meeting-list')?></h3>
							<p><?php _e('Enable a meeting info feedback form by adding email addresses below.', '12-step-meeting-list')?></p>
							<?php if (!empty($tsml_feedback_addresses)) {?>
							<table class="tsml_address_list">
								<?php foreach ($tsml_feedback_addresses as $address) {?>
								<tr>
									<td><?php echo $address?></td>
									<td>
										<form method="post" action="<?php echo $_SERVER['REQUEST_URI']?>">
											<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
											<input type="hidden" name="tsml_remove_feedback_address" value="<?php echo $address?>">
											<span class="dashicons dashicons-no-alt"></span>
										</form>
									</td>
								</tr>
								<?php }?>
							</table>
							<?php }?>
							<form class="columns" method="post" action="<?php echo $_SERVER['REQUEST_URI']?>">
								<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
								<div class="input">
									<input type="email" name="tsml_add_feedback_address" placeholder="email@example.org">
								</div>
								<div class="btn">
									<input type="submit" class="button" value="<?php _e('Add', '12-step-meeting-list')?>">
								</div>
							</form>
						</div>
					</div>
					<div class="postbox" id="get-notified">
						<div class="inside">
							<h3><?php _e('Get Notified', '12-step-meeting-list')?></h3>
							<p><?php _e('Receive notifications of meeting changes at the email addresses below.', '12-step-meeting-list')?></p>
							<?php if (!empty($tsml_notification_addresses)) {?>
							<table class="tsml_address_list">
								<?php foreach ($tsml_notification_addresses as $address) {?>
								<tr>
									<td><?php echo $address?></td>
									<td>
										<form method="post" action="<?php echo $_SERVER['REQUEST_URI']?>">
											<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
											<input type="hidden" name="tsml_remove_notification_address" value="<?php echo $address?>">
											<span class="dashicons dashicons-no-alt"></span>
										</form>
									</td>
								</tr>
								<?php }?>
							</table>
							<?php }?>
							<form class="columns" method="post" action="<?php echo $_SERVER['REQUEST_URI']?>">
								<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
								<div class="input">
									<input type="email" name="tsml_add_notification_address" placeholder="email@example.org">
								</div>
								<div class="btn">
									<input type="submit" class="button" value="<?php _e('Add', '12-step-meeting-list')?>">
								</div>
							</form>
						</div>
					</div>
					<?php
					if ($tsml_program == 'aa') {?>
					<div class="postbox" id="try_the_apps">
						<div class="inside">
							<h3><?php _e('Try the Apps!', '12-step-meeting-list')?></h3>
							<p class="buttons">
								<a href="https://itunes.apple.com/us/app/meeting-guide/id1042822181">
									<img src="<?php echo plugins_url('assets/img/apple.svg', __DIR__)?>" alt="<?php _e('Download on the iOS App Store')?>">
								</a>
								<a href="https://play.google.com/store/apps/details?id=org.meetingguide.app">
									<img src="<?php echo plugins_url('assets/img/google.svg', __DIR__)?>" alt="<?php _e('Download on the Google Play Store')?>">
								</a>
							</p>
							<p><?php printf(__('Want to have your meetings listed in a simple, free mobile app? <a href="%s" target="_blank">%d areas are currently participating</a>. No extra effort is required; simply continue to update your meetings in WordPress and the updates will flow down to app users.', '12-step-meeting-list'), 'https://meetingguide.org/', 90)?></p>
							<p><?php printf(__('To get involved, please <a href="%s">get in touch</a>.', '12-step-meeting-list'), TSML_CONTACT_LINK)?></p>
						</div>
					</div>
					<?php } else {?>
					<div class="postbox">
						<div class="inside">
							<h3><?php _e('About this Plugin', '12-step-meeting-list')?></h3>
							<p><?php printf(__('This plugin was developed by AA volunteers in <a href="%s" target="_blank">Santa Clara County</a> to help provide accessible, accurate information about meetings to those who need it.', '12-step-meeting-list'), 'https://aasanjose.org/central-office/technology')?></p>
							<p><?php printf(__('If you would like to help out with development, <a href="%s" target="_blank">visit us on GitHub</a>.', '12-step-meeting-list'), 'https://github.com/meeting-guide/12-step-meeting-list')?></p>
						</div>
					</div>
					<?php }?>
				</div>
			</div>
		</div>
	</div>	
	<?php
}
