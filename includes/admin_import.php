<?php

//import and settings

if (!function_exists('tsml_import_page')) {

	function tsml_import_page()
	{
		global $tsml_data_sources, $tsml_programs, $tsml_program, $tsml_nonce, $tsml_feedback_addresses,
			$tsml_notification_addresses, $tsml_distance_units, $tsml_sharing, $tsml_sharing_keys, $tsml_contact_display,
			$tsml_google_maps_key, $tsml_mapbox_key, $tsml_geocoding_method, $tsml_slug, $tsml_change_detect, $tsml_user_interface;

		$error = false;
		$tsml_data_sources = get_option('tsml_data_sources', array());

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
			} elseif (!in_array($extension, ['csv', 'txt'])) {
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

				//remove any rows that aren't arrays
				$meetings = array_filter($meetings, 'is_array');

				//crash if no data
				if (count($meetings) < 2) {
					$error = __('Nothing was imported because no data rows were found.', '12-step-meeting-list');
				} else {

					//allow theme-defined function to reformat CSV prior to import (New Hampshire, Ventura)
					if (function_exists('tsml_import_reformat')) {
						$meetings = tsml_import_reformat($meetings);
					}

					//if it's FNV data, reformat it
					$meetings = tsml_import_reformat_fnv($meetings);

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

						//import into buffer, also done this way in data source import
						tsml_import_buffer_set($meetings);

						//run deletes
						if ($_POST['delete'] == 'regions') {

							//get all regions present in array
							$regions = [];
							foreach ($meetings as $meeting) {
								$regions[] = empty($meeting['sub_region']) ? $meeting['region'] : $meeting['sub_region'];
							}

							//get locations for those meetings
							$location_ids = get_posts([
								'post_type' => 'tsml_location',
								'numberposts' => -1,
								'fields' => 'ids',
								'tax_query' => [
									[
										'taxonomy' => 'tsml_region',
										'field' => 'name',
										'terms' => array_unique($regions),
									],
								],
							]);

							//get posts for those meetings
							$meeting_ids = get_posts([
								'post_type' => 'tsml_meeting',
								'numberposts' => -1,
								'fields' => 'ids',
								'post_parent__in' => $location_ids,
							]);

							tsml_delete($meeting_ids);

							tsml_delete_orphans();
						} elseif ($_POST['delete'] == 'no_data_source') {

							tsml_delete(get_posts([
								'post_type' => 'tsml_meeting',
								'numberposts' => -1,
								'fields' => 'ids',
								'meta_query' => [
									[
										'key' => 'data_source',
										'compare' => 'NOT EXISTS',
										'value' => '',
									],
								],
							]));

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

			//sanitize URL, name, parent region id, and Change Detection values
			$data_source_url = trim(esc_url_raw($_POST['tsml_add_data_source'], array('http', 'https')));
			$data_source_name = sanitize_text_field($_POST['tsml_add_data_source_name']);
			$data_source_parent_region_id = (int) $_POST['tsml_add_data_source_parent_region_id'];
			$data_source_change_detect = sanitize_text_field($_POST['tsml_add_data_source_change_detect']);

			//try fetching	
			$response = wp_remote_get($data_source_url, [
				'timeout' => 30,
				'sslverify' => false,
			]);
			if (is_array($response) && !empty($response['body']) && ($body = json_decode($response['body'], true))) {

				//if already set, hard refresh
				if (array_key_exists($data_source_url, $tsml_data_sources)) {
					tsml_delete(tsml_get_data_source_ids($data_source_url));
					tsml_delete_orphans();
				}

				$tsml_data_sources[$data_source_url] = [
					'status' => 'OK',
					'last_import' => current_time('timestamp'),
					'count_meetings' => 0,
					'name' => $data_source_name,
					'parent_region_id' => $data_source_parent_region_id,
					'change_detect' => $data_source_change_detect,
					'type' => 'JSON',
				];

				//import feed
				tsml_import_buffer_set($body, $data_source_url, $data_source_parent_region_id);

				//save data source configuration
				update_option('tsml_data_sources', $tsml_data_sources);

				// Create a cron job to run daily when Change Detection is enabled for the new data source
				if ( $data_source_change_detect === 'enabled' ) {
					tsml_CreateAndScheduleCronJob($data_source_url, $data_source_name);
				} 
					
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
		$meetings = get_option('tsml_import_buffer', []);

		//remove data source
		if (!empty($_POST['tsml_remove_data_source'])) {

			//sanitize URL
			$_POST['tsml_remove_data_source'] = esc_url_raw($_POST['tsml_remove_data_source'], ['http', 'https']);

			if (array_key_exists($_POST['tsml_remove_data_source'], $tsml_data_sources)) {

				//remove all meetings for this data source
				tsml_delete(tsml_get_data_source_ids($_POST['tsml_remove_data_source']));

				//clean up orphaned locations & groups
				tsml_delete_orphans();

				//remove data source
				unset($tsml_data_sources[$_POST['tsml_remove_data_source']]);
				update_option('tsml_data_sources', $tsml_data_sources);

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

		//change contact display
		if (!empty($_POST['tsml_contact_display']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
			$tsml_contact_display = ($_POST['tsml_contact_display'] == 'public') ? 'public' : 'private';
			update_option('tsml_contact_display', $tsml_contact_display);
			tsml_cache_rebuild(); //this value affects what's in the cache
			tsml_alert(__('Contact privacy updated.', '12-step-meeting-list'));
		}

		//change sharing setting
		if (!empty($_POST['tsml_sharing']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
			$tsml_sharing = ($_POST['tsml_sharing'] == 'open') ? 'open' : 'restricted';
			update_option('tsml_sharing', $tsml_sharing);
			tsml_alert(__('Sharing setting updated.', '12-step-meeting-list'));
		}

		//add a sharing key
		if (!empty($_POST['tsml_add_sharing_key']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
			$name = sanitize_text_field($_POST['tsml_add_sharing_key']);
			$key = md5(uniqid($name, true));
			$tsml_sharing_keys[$key] = $name;
			asort($tsml_sharing_keys);
			update_option('tsml_sharing_keys', $tsml_sharing_keys);
			tsml_alert(__('Sharing key added.', '12-step-meeting-list'));

			//users might expect that if they add "meeting guide" that then they are added to the app
			if (strtolower($name) == 'meeting guide') {
				$current_user = wp_get_current_user();
				$message = admin_url('admin-ajax.php?') . http_build_query([
					'action' => 'meetings',
					'key' => $key,
				]);
				tsml_email(TSML_MEETING_GUIDE_APP_NOTIFY, 'Sharing Key', $message, $current_user->user_email);
			}
		}

		//remove a sharing key
		if (!empty($_POST['tsml_remove_sharing_key']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
			$key = sanitize_text_field($_POST['tsml_remove_sharing_key']);
			if (array_key_exists($key, $tsml_sharing_keys)) {
				unset($tsml_sharing_keys[$key]);
				if (empty($tsml_sharing_keys)) {
					delete_option('tsml_sharing_keys');
				} else {
					update_option('tsml_sharing_keys', $tsml_sharing_keys);
				}
				tsml_alert(__('Sharing key removed.', '12-step-meeting-list'));
			} else {
				//theoretically should never get here, because user is choosing from a list
				tsml_alert(sprintf(esc_html__('<p><code>%s</code> was not found in the list of sharing keys. Please try again.</p>', '12-step-meeting-list'), $key), 'error');
			}
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

		//add a Mapbox access token
		if (isset($_POST['tsml_add_mapbox_key']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
			$tsml_mapbox_key = sanitize_text_field($_POST['tsml_add_mapbox_key']);
			if (empty($tsml_mapbox_key)) {
				delete_option('tsml_mapbox_key');
				tsml_alert(__('API key removed.', '12-step-meeting-list'));
			} else {
				update_option('tsml_mapbox_key', $tsml_mapbox_key);
				tsml_alert(__('API key saved.', '12-step-meeting-list'));
			}

			//there can be only one
			$tsml_google_maps_key = null;
			delete_option('tsml_google_maps_key');
		}

		//add a Google API key
		if (isset($_POST['tsml_add_google_maps_key']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
			$key = sanitize_text_field($_POST['tsml_add_google_maps_key']);
			if (empty($key)) {
				delete_option('tsml_google_maps_key');
				tsml_alert(__('API key removed.', '12-step-meeting-list'));
			} else {
				update_option('tsml_google_maps_key', $key);
				$tsml_google_maps_key = $key;
				tsml_alert(__('API key saved.', '12-step-meeting-list'));
			}

			//there can be only one
			$tsml_mapbox_key = null;
			delete_option('tsml_mapbox_key');
		}

		//change geocoding method
		if (!empty($_POST['tsml_geocoding_method']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
			$tsml_geocoding_method = sanitize_text_field($_POST['tsml_geocoding_method']);
			update_option('tsml_geocoding_method', $tsml_geocoding_method);
			tsml_alert(__('Geocoding method updated.', '12-step-meeting-list'));
		}

		//change user interface
		if (!empty($_POST['tsml_user_interface']) && isset($_POST['tsml_nonce']) && wp_verify_nonce($_POST['tsml_nonce'], $tsml_nonce)) {
			$tsml_user_interface = sanitize_text_field($_POST['tsml_user_interface']);
			update_option('tsml_user_interface', $tsml_user_interface);
			if ($tsml_user_interface == 'tsml_ui') {
				$tsml_ui = "TSML UI";
			} else {
				$tsml_ui = "LEGACY UI";
			}
			tsml_alert(__('Switch UI is now set to <strong>' . $tsml_ui . '</strong>', '12-step-meeting-list') );
			if ( empty( $tsml_mapbox_key ) && ($tsml_user_interface == 'tsml_ui') ) {
				tsml_alert(__('<b>Please note</b> that TSML UI only supports Mapbox. To enable mapping you will need a Mapbox token. <br>To sign up for Mapbox <a href="https://www.mapbox.com/signup/" target="_blank">go here</a>. Only a valid email address is required. Copy your access token and paste it in the Mapping & Geocoding section\'s <b>Mapbox Access Token</b> field.', '12-step-meeting-list'),'warning');
			} 		
		}

		//Get the active tab from the $_GET param
		$default_tab = null;
		$tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;

		/*debugging
		delete_option('tsml_data_sources');
		tsml_delete('everything');
		tsml_delete_orphans();
		*/
		?>


		<!-- Admin page content should all be inside .wrap -->
		<div id="import_settings_wrap" class="wrap">

			<?php if (!is_ssl()) { ?>
				<div class="notice notice-warning inline">
					<p><?php _e('If you enable SSL (https), your users will be able to search near their location.', '12-step-meeting-list') ?></p>
				</div>
			<?php } ?>

			<h1></h1> <!-- Set alerts here -->

			<div id="poststuff" >
				<div id="post-body" class="inside ">
					<div id="post-body-content" >

						<?php if ($error) { ?>
							<div class="error inline">
								<p><?php echo $error ?></p>
							</div>
						<?php } elseif ($total = count($meetings)) { ?>
							<div id="tsml_import_progress" class="progress" data-total="<?php echo $total ?>">
								<div class="progress-bar"></div>
							</div>
							<ol id="tsml_import_errors" class="error inline hidden"></ol>
						<?php } ?>

						<?php if (empty($tsml_mapbox_key) && empty($tsml_google_maps_key)) { ?>
							<div class="notice notice-warning inline">
								<div class="inside" >
									<h1>Enable Maps on Your Site</h1>
									<p>If you want to enable maps on your site you have two options: <strong>Mapbox</strong> or <strong>Google</strong>. *See the Google restriction noted below.
										They are both good options, although Google is not completely supported by all our features! In all likelihood neither one will charge you money. Mapbox gives
										<a href="https://www.mapbox.com/pricing/" target="_blank">50,000 free map views</a> / month, Google gives
										<a href="https://cloud.google.com/maps-platform/pricing/" target="_blank">28,500 free views</a>.
										That's a lot of traffic!
									</p>
									<p>To sign up for Mapbox <a href="https://www.mapbox.com/signup/" target="_blank">go here</a>. You will only need
										a valid email address, no credit card required. Copy your access token and paste it below:</p>
									<form class="columns" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>" >
										<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
										<div class="input">
											<input type="text" name="tsml_add_mapbox_key" placeholder="Enter Mapbox access token here">
										</div>
										<div class="btn">
											<input type="submit" class="button" value="<?php _e('Add', '12-step-meeting-list') ?>">
										</div>
									</form>

									<p>*Please note: Only Mapbox is currently supported by our <b>TSML UI</b> user interface!  Google is not an option.

									<p>For our legacy user interface (<b>Legacy UI</b>), you can alternatively choose to use Google. Their interface is slightly more complex because they offer more
										services. <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank">Go here</a>
										to get a key from Google. The process should only take a few minutes, although you will have to enter a	credit card.
										<a href="https://theeventscalendar.com/knowledgebase/setting-up-your-google-maps-api-key/" target="_blank">Here
											are some instructions</a>.
									</p>

									<p>Be sure to:<br>
										<span class="dashicons dashicons-yes"></span> Enable the Google Maps Javascript API<br>
										<span class="dashicons dashicons-yes"></span> Secure your credentials by adding your website URL to the list
										of allowed referrers
									</p>

									<p>Once you're done, paste your new key below.</p>

									<form class="columns" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>" >
										<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
										<div class="input">
											<input type="text" name="tsml_add_google_maps_key" placeholder="Enter Google API key here">
										</div>
										<div class="btn">
											<input type="submit" class="button" value="<?php _e('Add', '12-step-meeting-list') ?>">
										</div>
									</form>
								</div>
							</div>
						<?php } ?>
					</div>
				</div>
			</div>

			<nav class="nav-tab-wrapper">
				<a href="?post_type=tsml_meeting&page=import" class="nav-tab <?php if($tab===null):?>nav-tab-active<?php endif; ?>">Import</a>
				<a href="?post_type=tsml_meeting&page=import&tab=settings" class="nav-tab <?php if($tab==='settings'):?>nav-tab-active<?php endif; ?>">Settings</a>
				<a href="?post_type=tsml_meeting&page=import&tab=example" class="nav-tab <?php if($tab==='example'):?>nav-tab-active<?php endif; ?>">Spreadsheet Example & Spec</a>
			</nav>

			<div class="tab-content">
			<?php switch($tab) :
			case 'settings':   

				$file = TSML_PATH . "includes/admin_settings_tab.php";

				if (file_exists($file)) {
					require $file;
				}
				?>

			  <?php break;
			case 'example':   ?>
				<!-- Spreadsheet Example & Spec goes here -->
				<div class="col">
					<div class="postbox">
						<div class="inside">
							<!-- <h3><strong><?php _e('Spreadsheet Example & Spec', '12-step-meeting-list') ?></strong></h3> -->
							<p><a href="<?php echo plugin_dir_url(__FILE__) . '../template.csv' ?>" class="button button-large"><span class="dashicons dashicons-media-spreadsheet"></span><?php _e('Example spreadsheet', '12-step-meeting-list') ?></a></p>
							<ul class="ul-disc">
								<li><?php _e('<strong>Time</strong>, if present, should be in a standard date format such as 6:00 AM or 06:00. Non-standard or empty dates will be imported as "by appointment."', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>End Time</strong>, if present, should be in a standard date format such as 6:00 AM or 06:00.', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>Day</strong> if present, should either Sunday, Monday, Tuesday, Wednesday, Thursday, Friday, or Saturday. Meetings that occur on multiple days should be listed separately. \'Daily\' or \'Mondays\' will not work. Non-standard days will be imported as "by appointment."', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>Name</strong> is the name of the meeting, and is optional, although it\'s valuable information for the user. If it\'s missing, a name will be created by combining the location, day, and time.', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>Slug</strong> optional, and sets the meeting post\'s "slug" or unique string, which is used in the URL. This should be unique to the meeting. Setting this helps preserve user bookmarks.', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>Location</strong> is the name of the location, and is optional. Generally it\'s the group or building name. If it\'s missing, the address will be used. In the event that there are multiple location names for the same address, the first location name will be used.', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>Address</strong> is strongly encouraged and will be corrected by Google, so it may look different afterward. Ideally, every address for the same location should be exactly identical, and not contain extra information about the address, such as the building name or descriptors like "around back."', '12-step-meeting-list') ?></li>
								<li><?php _e('If <strong>Address</strong> is specified, then <strong>City</strong>, <strong>State</strong>, and <strong>Country</strong> are optional, but they might be useful if your addresses sound ambiguous to Google. If address is not specified, then these fields are required.', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>Notes</strong> are freeform notes that are specific to the meeting. For example, "last Saturday is birthday night."', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>Region</strong> is user-defined and can be anything. Often this is a small municipality or neighborhood. Since these go in a dropdown, ideally you would have 10 to 20 regions, although it\'s ok to be over or under.', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>Sub Region</strong> makes the Region hierarchical; in San Jose we have sub regions for East San Jose, West San Jose, etc. New York City might have Manhattan be a Region, and Greenwich Village be a Sub Region.', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>Location Notes</strong> are freeform notes that will show up on every meeting that this location. For example, "Enter from the side."', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>Group</strong> is a way of grouping contacts. Meetings with the same group name will be linked and share contact information.', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>District</strong> is user-defined and can be anything, but should be a string rather than an integer (e.g. <b>District 01</b> rather than <b>1</b>). A group name must also be specified.', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>Sub District</strong> makes the District hierachical.', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>Group Notes</strong> is for stuff like a short group history, or when the business meeting meets.', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>Website</strong> and <strong>Website 2</strong> are optional.', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>Email</strong> is optional. This is a public email address.', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>Phone</strong> is optional. This is a public phone number.', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>Contact 1/2/3 Name/Email/Phone</strong> (nine fields in total) are all optional. By default, contact information is only visible inside the WordPress dashboard.', '12-step-meeting-list') ?></li>
								<li><?php _e('<strong>Last Contact</strong> is an optional date.', '12-step-meeting-list') ?></li>
								<?php if (!empty($tsml_programs[$tsml_program]['types'])) { ?>
									<li><?php _e('<strong>Types</strong> should be a comma-separated list of the following options. This list is determined by which program is selected on the Settings tab.', '12-step-meeting-list') ?>
										<ul class="types">
											<?php foreach ($tsml_programs[$tsml_program]['types'] as $value) { ?>
												<li><?php echo $value ?></li>
											<?php } ?>
										</ul>
									</li>
								<?php } ?>
							</ul>
							<?php if ($tsml_program == 'aa') { ?>
								<p><?php _e('Additionally, you may import spreadsheets that are in the General Service Office\'s FNV database "Group Search Results" format. This format has 162 columns, the first column is <code>ServiceNumber</code>.', '12-step-meeting-list') ?></p>
							<?php } ?>
						</div>
					</div>
				</div>
			  <?php break;

			default:  
				$file = TSML_PATH . "includes/admin_import_tab.php";

				if (file_exists($file)) {
					require $file;
				}
				?>
				<div class="clear"></div>

			  <?php break;
			endswitch; ?>
			</div>
		</div>
	<?php
	}

}