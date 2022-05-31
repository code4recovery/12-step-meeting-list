<!-- Import Data Sources -->
<div class="postbox stack">
	<h2><?php _e('Import Data Sources', '12-step-meeting-list') ?></h2>
	<p>
		<?php printf(__('Data sources are JSON feeds that contain a website\'s public meeting data. They can be used to aggregate meetings from different sites into a single master list. 
				Data sources listed below will pull meeting information into this website. A configurable schedule allows for each enabled data source to be scanned at least once per day looking 
				for updates to the listing. Change Notification email addresses are sent an email when action is required to re-sync a data source with its meeting list information. 
				Please note: records that you intend to maintain on your website should always be imported using the Import CSV feature below. <b>Data Source records will be overwritten when the 
				parent data source is refreshed.</b> More information is available at the <a href="%s" target="_blank">Meeting Guide API Specification</a>.', '12-step-meeting-list'), 'https://github.com/code4recovery/spec') ?>
	</p>
	<?php if (!empty($tsml_data_sources)) { ?>
		<table>
			<thead>
				<tr>
					<th class="small align-center"></th>
					<th><?php _e('Feed', '12-step-meeting-list') ?></th>
					<th class="align-left"><?php _e('Parent Region', '12-step-meeting-list') ?></th>
					<th class="align-left"><?php _e('Change Detection', '12-step-meeting-list') ?></th>
					<th class="align-center"><?php _e('Meetings', '12-step-meeting-list') ?></th>
					<th class="align-right"><?php _e('Last Refresh', '12-step-meeting-list') ?></th>
					<th class="small"></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($tsml_data_sources as $feed => $properties) { ?>
					<tr data-source="<?php echo $feed ?>">
						<td class="small ">
							<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
								<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
								<input type="hidden" name="tsml_add_data_source" value="<?php echo $feed ?>">
								<input type="hidden" name="tsml_add_data_source_name" value="<?php echo @$properties['name'] ?>">
								<input type="hidden" name="tsml_add_data_source_parent_region_id" value="<?php echo @$properties['parent_region_id'] ?>">
								<input type="hidden" name="tsml_add_data_source_change_detect" value="<?php echo @$properties['change_detect'] ?>">
								<input type="submit" value="Refresh" class="button button-small">
							</form>
						</td>
						<td>
							<a href="<?php echo $feed ?>" target="_blank">
								<?php echo !empty($properties['name']) ? $properties['name'] : __('Unnamed Feed', '12-step-meeting-list') ?>
							</a>
						</td>
						<td>
							<?php
							$parent_region = null;
							if (empty($properties['parent_region_id']) || $properties['parent_region_id'] == -1) {
								$parent_region = __('Top-level region', '12-step-meeting-list');
							} elseif (empty($regions[$properties['parent_region_id']])) {
								$term = get_term_by('term_id', $properties['parent_region_id'], 'tsml_region');
								//$parent_region = $term[3];
								$parent_region = $term->name;
								if ($parent_region == null) {
									$parent_region = __('Top-level region', '12-step-meeting-list');
									$parent_region = 'Missing Parent Region: ' . $properties['parent_region_id'];
								}
							} else {
								$parent_region = $regions[$properties['parent_region_id']];
							}
							echo $parent_region;
							?>
						</td>
						<td>
							<?php
							$change_detect = null;
							if (empty($properties['change_detect']) || $properties['change_detect'] == -1) {
								$change_detect = __('Disabled', '12-step-meeting-list');
							} else {
								$change_detect = ucfirst($properties['change_detect']);
							}

							echo $change_detect;
							?>
						</td>
						<td class="align-center count_meetings"><?php echo number_format($properties['count_meetings']) ?></td>

						<td class="align-right">
							<?php echo Date(get_option('date_format') . ' ' . get_option('time_format'), $properties['last_import']) ?>
						</td>

						<td class="small">
							<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
								<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
								<input type="hidden" name="tsml_remove_data_source" value="<?php echo $feed ?>">
								<span class="dashicons dashicons-no-alt"></span>
							</form>
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	<?php } ?>
	<form class="row" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
		<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>

		<input type="text" name="tsml_add_data_source_name" placeholder="<?php _e('District 02', '12-step-meeting-list') ?>">

		<input type="text" name="tsml_add_data_source" placeholder="https://">

		<?php wp_dropdown_categories(array(
			'name' => 'tsml_add_data_source_parent_region_id',
			'taxonomy' => 'tsml_region',
			'hierarchical' => true,
			'hide_empty' => false,
			'orderby' => 'name',
			'selected' => null,
			'title' => __('Append regions created by this data source to… (top-level, if none selected)', '12-step-meeting-list'),
			'show_option_none' => __('Parent Region…', '12-step-meeting-list'),
		)) ?>

		<select name="tsml_add_data_source_change_detect" id="tsml_change_detect">
			<?php
			foreach (array(
				'disabled' => __('Change Detection Disabled', '12-step-meeting-list'),
				'enabled' => __('Change Detection Enabled', '12-step-meeting-list'),
			) as $key => $value) { ?>
				<option value="<?php echo $key ?>" <?php selected($tsml_change_detect, $key) ?>><?php echo $value ?></option>
			<?php } ?>
		</select>

		<input type="submit" class="button" value="<?php _e('Add Data Source', '12-step-meeting-list') ?>">
	</form>
</div>

<div class="three-column">
	<div class="postbox stack">
		<h2><?php _e('Import CSV', '12-step-meeting-list') ?></h2>
		<form method="post" class="radio stack" action="<?php echo $_SERVER['REQUEST_URI'] ?>" enctype="multipart/form-data">
			<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
			<input type="file" name="tsml_import">
			<p>
				<?php _e('When importing...', '12-step-meeting-list') ?>
				<?php
				$delete_options = [
					'nothing'	=> __('don\'t delete anything', '12-step-meeting-list'),
					'regions'	=> __('delete only the meetings, locations, and groups for the regions present in this CSV', '12-step-meeting-list'),
					'all' 		=> __('delete all meetings, locations, groups, districts, and regions', '12-step-meeting-list'),
				];
				if (!empty($tsml_data_sources)) {
					$delete_options['no_data_source'] = __('delete all meetings, locations, and groups not from a data source', '12-step-meeting-list');
				}
				$delete_selected = (empty($_POST['delete']) || !array_key_exists($_POST['delete'], $delete_options)) ? 'nothing' : $_POST['delete'];
				foreach ($delete_options as $key => $value) { ?>
					<label>
						<input type="radio" name="delete" value="<?php echo $key ?>" <?php checked($key, $delete_selected) ?>>
						<?php echo $value ?>
					</label>
				<?php } ?>
			</p>
			<input type="submit" class="button" value="<?php _e('Begin', '12-step-meeting-list') ?>">
		</form>
	</div>

	<!-- Wheres My Info? -->
	<div class="postbox stack">
		<?php
		$meetings = tsml_count_meetings();
		$locations = tsml_count_locations();
		$regions = tsml_count_regions();
		$groups = tsml_count_groups();

		$pdf_link = 'https://pdf.code4recovery.org/?' . http_build_query([
			'json' => admin_url('admin-ajax.php') . '?' . http_build_query([
				'action' => 'meetings',
				'nonce' => $tsml_sharing === 'restricted' ? wp_create_nonce($tsml_nonce) : null
			])
		]);
		?>
		<h2><?php _e('Where\'s My Info?', '12-step-meeting-list') ?></h2>
		<?php if ($tsml_slug) { ?>
			<p><?php printf(__('Your public meetings page is <a href="%s">right here</a>. Link that page from your site\'s nav menu to make it visible to the public.', '12-step-meeting-list'), get_post_type_archive_link('tsml_meeting')) ?></p>
		<?php
		}
		if ($meetings) { ?>
			<p><?php printf(__('<strong>Going away soon:</strong> a very basic PDF schedule is available in three sizes: <a href="%s">4&times;7</a>, <a href="%s">half page</a> and <a href="%s">full page</a>.', '12-step-meeting-list'), admin_url('admin-ajax.php') . '?action=tsml_pdf&width=4&height=7', admin_url('admin-ajax.php') . '?action=tsml_pdf', admin_url('admin-ajax.php') . '?action=tsml_pdf&width=8.5') ?></p>
			<p><?php _e('<strong>New!</strong> We are developing a service to generate PDF directories of in-person meetings.', '12-step-meeting-list') ?></p>
			<p><a href="<?php echo $pdf_link ?>" target="_blank" class="button"><?php _e('Generate PDF') ?></a></p>
		<?php } ?>

		<div id="tsml_counts" <?php if (!($meetings + $locations + $groups + $regions)) { ?> class="hidden" <?php } ?>>
			<p><?php _e('You have:', '12-step-meeting-list') ?></p>
			<div class="table">
				<ul class="ul-disc">
					<li class="meetings<?php if (!$meetings) { ?> hidden<?php } ?>">
						<?php printf(_n('%s meeting', '%s meetings', $meetings, '12-step-meeting-list'), number_format_i18n($meetings)) ?>
					</li>
					<li class="locations<?php if (!$locations) { ?> hidden<?php } ?>">
						<?php printf(_n('%s location', '%s locations', $locations, '12-step-meeting-list'), number_format_i18n($locations)) ?>
					</li>
					<li class="groups<?php if (!$groups) { ?> hidden<?php } ?>">
						<?php printf(_n('%s group', '%s groups', $groups, '12-step-meeting-list'), number_format_i18n($groups)) ?>
					</li>
					<li class="regions<?php if (!$regions) { ?> hidden<?php } ?>">
						<?php printf(_n('%s region', '%s regions', $regions, '12-step-meeting-list'), number_format_i18n($regions)) ?>
					</li>
				</ul>
			</div>
		</div>
	</div>

	<!-- Export Meeting List -->
	<div class="postbox stack">
		<h2><?php _e('Export Meeting List', '12-step-meeting-list') ?></h2>
		<?php
		if ($meetings) { ?>
			<p><?php printf(__('You can download your meetings in <a href="%s">CSV format</a>.', '12-step-meeting-list'), admin_url('admin-ajax.php') . '?action=csv') ?></p>
		<?php } ?>
		<p><?php printf(__('Want to send a mass email to your contacts? <a href="%s" target="_blank">Click here</a> to see their email addresses.', '12-step-meeting-list'), admin_url('admin-ajax.php') . '?action=contacts') ?></p>
	</div>
</div>