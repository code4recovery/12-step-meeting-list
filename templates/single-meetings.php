<?php
tsml_assets();

$meeting = tsml_get_meeting();

//define some vars for the map
wp_localize_script('tsml_public', 'tsml_map', array(
	'directions' => __('Directions', '12-step-meeting-list'),
	'directions_url' => in_array('TC', $meeting->types) ? null : $meeting->directions,
	'formatted_address' => $meeting->formatted_address,
	'latitude' => $meeting->latitude,
	'location' => get_the_title($meeting->post_parent),
	'location_id' => $meeting->post_parent,
	'location_url' => get_permalink($meeting->post_parent),
	'longitude' => $meeting->longitude,
));

$startDate = tsml_format_next_start($meeting);
$endDate = tsml_format_next_end($meeting);

//adding custom body classes
add_filter('body_class', 'tsml_body_class');
function tsml_body_class($classes) {
	global $meeting;

	$classes[] = 'tsml tsml-detail tsml-meeting';

	if ($type_classes = tsml_to_css_classes($meeting->types, 'tsml-type-')) {
		$classes[] = $type_classes;
	}

	return $classes;
}

get_header();
?>

<div id="tsml">
	<div id="meeting" class="container">
		<div class="row">
			<div class="col-md-10 col-md-offset-1 main">

				<div class="page-header">
					<h1><?php echo tsml_format_name($meeting->post_title, $meeting->types) ?></h1>
					<?php echo tsml_link(get_post_type_archive_link('tsml_meeting'), '<i class="glyphicon glyphicon-chevron-right"></i> ' . __('Back to Meetings', '12-step-meeting-list'), 'tsml_meeting') ?>
				</div>

				<div class="row">
					<div class="col-md-4">

						<?php if (!in_array('TC', $meeting->types)) { ?>
						<div class="panel panel-default">
							<a class="panel-heading tsml-directions" href="#" data-latitude="<?php echo $meeting->latitude ?>" data-longitude="<?php echo $meeting->longitude ?>" data-location="<?php echo $meeting->location ?>">
								<h3 class="panel-title">
									<?php _e('Get Directions', '12-step-meeting-list')?>
									<span class="panel-title-buttons">
										<span class="glyphicon glyphicon-share-alt"></span>
									</span>
								</h3>
							</a>
						</div>
						<?php }?>

						<div class="panel panel-default">
							<ul class="list-group">
								<li class="list-group-item meeting-info">
									<h3 class="list-group-item-heading"><?php _e('Meeting Information', '12-step-meeting-list')?></h3>
									<?php
									echo '<p class="meeting-time"' . ($startDate ? ' content="' . $startDate . '"' : '') . ($endDate ? ' data-end-date="' . $endDate . '"' : '') . '>';
									echo tsml_format_day_and_time($meeting->day, $meeting->time);
									if (!empty($meeting->end_time)) {
										/* translators: until */
										echo __(' to ', '12-step-meeting-list'), tsml_format_time($meeting->end_time);
									}
									echo '</p>';
									if (count($meeting->types_expanded)) { ?>
										<ul class="meeting-types">
										<?php foreach ($meeting->types_expanded as $type) {?>
											<li><i class="glyphicon glyphicon-ok"></i> <?php _e($type, '12-step-meeting-list')?></li>
										<?php }?>
										</ul>
										<?php if (!empty($meeting->type_description)) {?>
											<p class="meeting-type-description"><?php _e($meeting->type_description, '12-step-meeting-list')?></p>
										<?php }
									}

									if (!empty($meeting->notes)) {?>
										<section class="meeting-notes"><?php echo wpautop($meeting->notes) ?></section>
									<?php }?>
								</li>
								<?php if (!empty($meeting->conference_url) || !empty($meeting->conference_phone)) {?>
								<li class="list-group-item">
									<h3 class="list-group-item-heading">
										<?php _e('Online Meeting', '12-step-meeting-list')?>
									</h3>
									<?php 
									if (!empty($meeting->conference_url) && $provider = tsml_conference_provider($meeting->conference_url)) {?>
										<a class="btn btn-default btn-block" href="<?php echo $meeting->conference_url?>" target="_blank"><i class="glyphicon glyphicon-facetime-video"></i> <?php echo $provider === true ? $meeting->conference_url : $provider?></a>
									<?php }
									if (!empty($meeting->conference_phone)) {?>
										<a class="btn btn-default btn-block" href="tel:<?php echo preg_replace("/[^0-9,#+]/", '', $meeting->conference_phone)?>" target="_blank"><i class="glyphicon glyphicon-headphones"></i> <?php echo $meeting->conference_phone?></a>
									<?php }?>
								</li>
								<?php }?>

								</li>
								<?php
								if (!empty($meeting->location_id)) {
									$location_info = '
										<h3 class="list-group-item-heading">' . $meeting->location . '</h3>';

									if ($other_meetings = count($meeting->location_meetings) - 1) {
										$location_info .= '<p class="location-other-meetings">' . sprintf(_n('%d other meeting at this location', '%d other meetings at this location', $other_meetings, '12-step-meeting-list'), $other_meetings) . '</p>';
									}

									$location_info .= '<p class="location-address">' . tsml_format_address($meeting->formatted_address) . '</p>';

									if (!empty($meeting->location_notes)) {
										$location_info .= '<section class="location-notes">' . wpautop($meeting->location_notes) . '</section>';
									}

									if (!empty($meeting->region) && !strpos($meeting->formatted_address, $meeting->region)) {
										$location_info .= '<p class="location-region">' . $meeting->region . '</p>';
									}

									echo tsml_link(
										get_permalink($meeting->post_parent),
										$location_info,
										'tsml_meeting',
										'list-group-item list-group-item-location'
									);
								}

								if (!empty($meeting->group) || !empty($meeting->website) || !empty($meeting->website_2) || !empty($meeting->email) || !empty($meeting->phone)) {?>
									<li class="list-group-item list-group-item-group">
										<h3 class="list-group-item-heading"><?php echo $meeting->group ?></h3>
										<?php if (!empty($meeting->group_notes)) {?>
											<section class="group-notes"><?php echo wpautop($meeting->group_notes) ?></section>
										<?php }
										if (!empty($meeting->district)) {?>
											<section class="group-district"><?php echo $meeting->district ?></section>
										<?php }
										if (!empty($meeting->website)) {?>
											<p class="group-website">
												<a href="<?php echo $meeting->website ?>" target="_blank"><?php echo $meeting->website ?></a>
											</p>
										<?php }
										if (!empty($meeting->website_2)) {?>
											<p class="group-website_2">
												<a href="<?php echo $meeting->website_2 ?>" target="_blank"><?php echo $meeting->website_2 ?></a>
											</p>
										<?php }
										if (!empty($meeting->email)) {?>
											<p class="group-email">
												<a href="mailto:<?php echo $meeting->email ?>"><?php echo $meeting->email ?></a>
											</p>
											<?php }
										if (!empty($meeting->phone)) {?>
											<p class="group-phone">
												<a href="tel:<?php echo $meeting->phone ?>"><?php echo $meeting->phone ?></a>
											</p>
										</a>
										<?php }
										if (!empty($meeting->venmo)) {?>
											<p class="group-venmo">
												Venmo: <a href="https://venmo.com/<?php echo substr($meeting->venmo, 1) ?>" target="_blank"><?php echo $meeting->venmo ?></a>
											</p>
										</a>
										<?php }?>
									</li>
								<?php }?>
								<li class="list-group-item list-group-item-updated">
									<?php _e('Updated', '12-step-meeting-list')?>
									<?php the_modified_date()?>
								</li>
							</ul>
						</div>

						<?php
						if ($tsml_contact_display == 'public') {
							for ($i = 1; $i <= GROUP_CONTACT_COUNT; $i++) {
								if (!empty($meeting->{'contact_' . $i . '_name'}) || !empty($meeting->{'contact_' . $i . '_email'}) || !empty($meeting->{'contact_' . $i . '_phone'})) {?>
								<div class="panel panel-default">
									<div class="panel-heading">
										<h3 class="panel-title">
											<?php if (!empty($meeting->{'contact_' . $i . '_name'})) {
												echo $meeting->{'contact_' . $i . '_name'};
											}?>
											<span class="panel-title-buttons">
												<?php if (!empty($meeting->{'contact_' . $i . '_email'})) {?><a href="mailto:<?php echo $meeting->{'contact_' . $i . '_email'} ?>"><span class="glyphicon glyphicon-envelope"></span></a><?php }?>
												<?php if (!empty($meeting->{'contact_' . $i . '_phone'})) {?><a href="tel:<?php echo preg_replace('~\D~', '', $meeting->{'contact_' . $i . '_phone'}) ?>"><span class="glyphicon glyphicon-earphone"></span></a><?php }?>
											</span>
										</h3>
									</div>
								</div>
								<?php }
							}
						}

						if (!empty($tsml_feedback_addresses)) {?>
						<form id="feedback">
							<input type="hidden" name="action" value="tsml_feedback">
							<input type="hidden" name="meeting_id" value="<?php echo $meeting->ID ?>">
							<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
							<div class="panel panel-default panel-expandable">
								<div class="panel-heading">
									<h3 class="panel-title">
										<?php _e('Request a change to this listing', '12-step-meeting-list')?>
										<span class="panel-title-buttons">
											<span class="glyphicon glyphicon-chevron-left"></span>
										</span>
									</h3>
								</div>
								<ul class="list-group">
									<li class="list-group-item list-group-item-warning">
										<?php _e('Use this form to submit a change to the meeting information above.', '12-step-meeting-list')?>
									</li>
									<li class="list-group-item list-group-item-form">
										<input type="text" id="tsml_name" name="tsml_name" placeholder="<?php _e('Your Name', '12-step-meeting-list')?>" class="required">
									</li>
									<li class="list-group-item list-group-item-form">
										<input type="email" id="tsml_email" name="tsml_email" placeholder="<?php _e('Email Address', '12-step-meeting-list')?>" class="required email">
									</li>
									<li class="list-group-item list-group-item-form">
										<textarea id="tsml_message" name="tsml_message" placeholder="<?php _e('Message', '12-step-meeting-list')?>" class="required"></textarea>
									</li>
									<li class="list-group-item list-group-item-form">
										<button type="submit"><?php _e('Submit', '12-step-meeting-list')?></button>
									</li>
								</ul>
							</div>
						</form>
						<?php }?>

					</div>
					<div class="col-md-8">
						<?php /* if (has_post_thumbnail()) { ?>
						<img src="<?php echo get_the_post_thumbnail_url(); ?>" class="panel panel-default meeting-thumbnail img-responsive">
						<?php } */?>
						<?php if (!empty($tsml_mapbox_key) || !empty($tsml_google_maps_key)) {?>
						<div id="map" class="panel panel-default"></div>
						<?php }?>
					</div>
				</div>
			</div>
		</div>

		<?php if (is_active_sidebar('tsml_meeting_bottom')) {?>
			<div class="widgets meeting-widgets meeting-widgets-bottom" role="complementary">
				<?php dynamic_sidebar('tsml_meeting_bottom')?>
			</div>
		<?php }?>

	</div>
</div>
<?php
get_footer();
