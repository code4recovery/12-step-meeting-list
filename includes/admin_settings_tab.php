<div class="three-column">
	<div>
		<!-- General Settings -->
		<div class="postbox stack">
			<h2><?php _e('General', '12-step-meeting-list') ?></h2>
			<form class="stack compact" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<h3><?php _e('Program', '12-step-meeting-list') ?></h3>
				<p><?php _e('Select the Recovery Program your site targets here.', '12-step-meeting-list') ?></p>
				<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
				<select name="tsml_program" onchange="this.form.submit()">
					<?php foreach ($tsml_programs as $key => $value) { ?>
						<option value="<?php echo $key ?>" <?php selected($tsml_program, $key) ?>>
							<?php echo $value['name'] ?>
						</option>
					<?php } ?>
				</select>
			</form>
			<form class="stack compact" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<h3><?php _e('Distance Units', '12-step-meeting-list') ?></h3>
				<p><?php _e('This determines which units are used on the meeting list page.', '12-step-meeting-list') ?></p>
				<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
				<select name="tsml_distance_units" onchange="this.form.submit()">
					<?php
					foreach ([
						'km' => __('Kilometers', '12-step-meeting-list'),
						'mi' => __('Miles', '12-step-meeting-list'),
					] as $key => $value) { ?>
						<option value="<?php echo $key ?>" <?php selected($tsml_distance_units, $key) ?>>
							<?php echo $value ?>
						</option>
					<?php } ?>
				</select>
			</form>
			<form class="stack compact" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<h3><?php _e('Contact Visibility', '12-step-meeting-list') ?></h3>
				<p><?php _e('This determines whether contacts are displayed publicly on meeting detail pages.', '12-step-meeting-list') ?></p>
				<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
				<select name="tsml_contact_display" onchange="this.form.submit()">
					<?php
					foreach ([
						'public' => __('Public', '12-step-meeting-list'),
						'private' => __('Private', '12-step-meeting-list'),
					] as $key => $value) { ?>
						<option value="<?php echo $key ?>" <?php selected($tsml_contact_display, $key) ?>>
							<?php echo $value ?>
						</option>
					<?php } ?>
				</select>
			</form>
		</div>

		<div class="postbox stack">
			<!-- Feed Management -->
			<h2><?php _e('Feed Management', '12-step-meeting-list') ?></h2>
			<form class="stack compact" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<h3><?php _e('Feed Sharing', '12-step-meeting-list') ?></h3>
				<p><?php printf(__('Open means your feeds are available publicly. Restricted means people need a key or to be logged in to get the feed.', '12-step-meeting-list')) ?></p>
				<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
				<select name="tsml_sharing" onchange="this.form.submit()">
					<?php
					foreach ([
						'open' => __('Open', '12-step-meeting-list'),
						'restricted' => __('Restricted', '12-step-meeting-list'),
					] as $key => $value) { ?>
						<option value="<?php echo $key ?>" <?php selected($tsml_sharing, $key) ?>>
							<?php echo $value ?>
						</option>
					<?php } ?>
				</select>
			</form>

			<?php if ($tsml_sharing == 'restricted') { ?>

				<div class="stack compact">
					<h3><?php _e('Authorized Apps', '12-step-meeting-list') ?></h3>

					<p><?php _e('You may allow access to your meeting data for specific purposes, such as the <a target="_blank" href="https://meetingguide.org/">Meeting Guide App</a>.', '12-step-meeting-list') ?></p>

					<?php if (count($tsml_sharing_keys)) { ?>
						<table class="tsml_sharing_list">
							<?php foreach ($tsml_sharing_keys as $key => $name) {
								$address = admin_url('admin-ajax.php?') . http_build_query([
									'action' => 'meetings',
									'key' => $key,
								]);
							?>
								<tr>
									<td><a href="<?php echo $address ?>" target="_blank"><?php echo $name ?></a></td>
									<td>
										<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
											<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
											<input type="hidden" name="tsml_remove_sharing_key" value="<?php echo $key ?>">
											<span class="dashicons dashicons-no-alt"></span>
										</form>
									</td>
								</tr>
							<?php } ?>
						</table>
					<?php } ?>
					<form class="row" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
						<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
						<input type="text" name="tsml_add_sharing_key" placeholder="<?php _e('Meeting Guide', '12-step-meeting-list') ?>">
						<input type="submit" class="button" value="<?php _e('Add', '12-step-meeting-list') ?>">
					</form>
				</div>
			<?php } else { ?>
				<div class="stack compact">
					<h3><?php _e('Public Feed', '12-step-meeting-list') ?></h3>
					<p><?php _e('The following feed contains your publicly available meeting information.', '12-step-meeting-list') ?></p>
					<p><?php printf(__('<a class="public_feed" href="%s" target="_blank">Public Data Source</a>', '12-step-meeting-list'), admin_url('admin-ajax.php?action=meetings')) ?></p>
				</div>
			<?php } ?>
		</div>
	</div>

	<div>
		<div class="postbox stack">
			<!-- Switch UI -->
			<div class="stack compact">
				<h2><?php _e('User Interface Display', '12-step-meeting-list') ?></h2>
				<p><?php _e('Please select the user interface that is right for your site. Choose between our latest design that we call <b>TSML UI</b> or stay with the standard <b>Legacy UI</b>.', '12-step-meeting-list') ?></p>

				<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
					<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
					<select name="tsml_user_interface" onchange="this.form.submit()">
						<option value="legacy" <?php selected($tsml_user_interface, 'legacy_ui') ?>>
							<?php _e('Legacy UI', '12-step-meeting-list') ?>
						</option>
						<option value="tsml_ui" <?php selected($tsml_user_interface, 'tsml_ui') ?>>
							<?php _e('TSML UI', '12-step-meeting-list') ?>
						</option>
					</select>
				</form>
			</div>
		</div>

		<!-- Map Settings -->
		<div class="postbox stack">
			<h2><?php _e('Mapping & Geocoding', '12-step-meeting-list') ?></h2>
			<p><?php _e('Display of maps requires an authorization key from <strong><a href="https://www.mapbox.com/" target="_blank">Mapbox</a></strong> or <strong><a href="https://console.cloud.google.com/home/" target="_blank">Google</a></strong>.', '12-step-meeting-list') ?></p>

			<div class="stack compact">
				<h3><?php _e('Mapbox Access Token', '12-step-meeting-list') ?></h3>
				<p><?php _e('Enter a key from Mapbox to enable their Web Map Services.', '12-step-meeting-list') ?></p>

				<form class="row" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
					<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
					<input type="text" name="tsml_add_mapbox_key" value="<?php echo $tsml_mapbox_key ?>" placeholder="Enter Mapbox access token here">
					<?php if (empty($tsml_mapbox_key)) { ?>
						<input type="submit" class="button" value="<?php _e('Add', '12-step-meeting-list') ?>">
					<?php } else { ?>
						<input type="submit" class="button" value="<?php _e('Update', '12-step-meeting-list') ?>">
					<?php } ?>
				</form>
			</div>

			<div class="stack compact">
				<h3><?php _e('Google Maps API Key', '12-step-meeting-list') ?></h3>
				<p class="no_space_after"><?php _e('Enter a key from Google authorizing use of their Map Services (<a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank">get instructions here</a>).', '12-step-meeting-list') ?></p>
				<form class="row" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
					<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
					<input type="text" name="tsml_add_google_maps_key" value="<?php echo $tsml_google_maps_key ?>" placeholder="Enter Google API key here">
					<?php if (empty($tsml_google_maps_key)) { ?>
						<input type="submit" class="button" value="<?php _e('Add', '12-step-meeting-list') ?>">
					<?php } else { ?>
						<input type="submit" class="button" value="<?php _e('Update', '12-step-meeting-list') ?>">
					<?php } ?>
				</form>
			</div>

			<div class="stack compact">
				<h3><?php _e('Address Geocoding', '12-step-meeting-list') ?></h3>
				<p><?php _e('Code for Recovery is working on a new method for geocoding addresses. You can decide whether to use the old way, or whether to help us test the new way.', '12-step-meeting-list') ?></p>
				<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
					<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
					<select name="tsml_geocoding_method" onchange="this.form.submit()">
						<option value="legacy" <?php selected($tsml_geocoding_method, 'legacy') ?>>
							<?php _e('Legacy Method', '12-step-meeting-list') ?>
						</option>
						<?php if (!empty($tsml_google_maps_key)) { ?>
							<option value="google_key" <?php selected($tsml_geocoding_method, 'google_key') ?>>
								<?php _e('Use my Google API Key', '12-step-meeting-list') ?>
							</option>
						<?php } ?>
						<option value="api_gateway" <?php selected($tsml_geocoding_method, 'api_gateway') ?>>
							<?php _e('BETA - API Gateway - BETA', '12-step-meeting-list') ?>
						</option>
					</select>
				</form>
				<?php if (!empty($tsml_google_maps_key)) { ?>
					<p>If you select "Use my Google API Key", then you <strong>must</strong> go into the Google Console and enable the geocode API for your key</p>
				<?php } ?>
			</div>
		</div>
	</div>

	<div>
		<!-- About Us -->
		<div class="postbox stack">
			<h2><?php _e('About Us', '12-step-meeting-list') ?></h2>
			<p>
				<a href="https://code4recovery.org/" target="_blank" class="logo">
					<img src="<?php echo plugin_dir_url(__FILE__) . '../assets/img/code4recovery.svg'; ?>" alt="Code for Recovery">
				</a>
				<?php _e(
					'This <b>12 Step Meeting List</b> plugin (TSML) is one of the free services offered by the nonprofit organization <b>Code For Recovery</b> whose volunteer members build and maintain technology services for recovery fellowships such as AA and Al-Anon.',
					'12-step-meeting-list'
				) ?>
			</p>
		</div>

		<div class="postbox stack">
			<!-- Need Help -->
			<h2><?php _e('Need Help?', '12-step-meeting-list') ?></h2>

			<p><?php _e(
					'To get information about this product or our organization, simply use one of the linked buttons below which are great sources for information and answers.',
					'12-step-meeting-list'
				) ?></p>
			<p class="row">
				<a href="https://code4recovery.org/docs/12-step-meeting-list" target="_blank" class="button">
					<?php _e('View Documentation', '12-step-meeting-list') ?>
				</a>
				<a href="https://github.com/code4recovery/12-step-meeting-list/discussions" target="_blank" class="button">
					<?php _e('Ask a Question', '12-step-meeting-list') ?>
				</a>
			</p>
		</div>

		<!-- Email Settings -->
		<div class="postbox stack">
			<h2><?php _e('Email Addresses', '12-step-meeting-list') ?></h2>

			<div class="stack compact">
				<h3><?php _e('User Feedback Emails', '12-step-meeting-list') ?></h3>
				<p><?php _e('Enable a meeting info feedback form by adding email addresses here.', '12-step-meeting-list') ?></p>
				<?php if (!empty($tsml_feedback_addresses)) { ?>
					<table class="tsml_address_list">
						<?php foreach ($tsml_feedback_addresses as $address) { ?>
							<tr>
								<td><?php echo $address ?></td>
								<td>
									<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
										<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
										<input type="hidden" name="tsml_remove_feedback_address" value="<?php echo $address ?>">
										<span class="dashicons dashicons-no-alt"></span>
									</form>
								</td>
							</tr>
						<?php } ?>
					</table>
				<?php } ?>
				<form class="row" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
					<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
					<input type="email" name="tsml_add_feedback_address" placeholder="email@example.org">
					<input type="submit" class="button" value="<?php _e('Add', '12-step-meeting-list') ?>">
				</form>
			</div>

			<div class="stack compact">
				<h3><?php _e('Change Notification Emails', '12-step-meeting-list') ?></h3>
				<p><?php _e('Receive notifications of meeting changes at the email addresses below.', '12-step-meeting-list') ?></p>
				<?php if (!empty($tsml_notification_addresses)) { ?>
					<table class="tsml_address_list">
						<?php foreach ($tsml_notification_addresses as $address) { ?>
							<tr>
								<td><?php echo $address ?></td>
								<td>
									<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
										<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
										<input type="hidden" name="tsml_remove_notification_address" value="<?php echo $address ?>">
										<span class="dashicons dashicons-no-alt"></span>
									</form>
								</td>
							</tr>
						<?php } ?>
					</table>
				<?php } ?>
				<form class="row" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
					<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
					<input type="email" name="tsml_add_notification_address" placeholder="email@example.org">
					<input type="submit" class="button" value="<?php _e('Add', '12-step-meeting-list') ?>">
				</form>
			</div>
		</div>
	</div>
</div>