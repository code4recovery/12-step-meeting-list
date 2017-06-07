<?php 
tsml_assets();

$meeting = tsml_get_meeting();

//define some vars for the map
wp_localize_script('tsml_public', 'tsml_map', array(
	'latitude' => $meeting->latitude,
	'longitude' => $meeting->longitude,
	'location' => get_the_title($meeting->post_parent),
	'address' => $meeting->formatted_address,
	'location_url' => get_permalink($meeting->post_parent),
	'directions_url' => $meeting->directions,
	'directions' => __('Directions', '12-step-meeting-list'),
));

get_header();
?>

<div id="tsml">
	<div id="meeting" class="container">
		<div class="row">
			<div class="col-md-10 col-md-offset-1 main">
			
				<div class="page-header">
					<h1><?php echo tsml_format_name($meeting->post_title, $meeting->types)?></h1>
					<?php echo tsml_link(get_post_type_archive_link('tsml_meeting'), '<i class="glyphicon glyphicon-chevron-right"></i> ' . __('Back to Meetings', '12-step-meeting-list'), 'tsml_meeting')?>
				</div>
	
				<div class="row">
					<div class="col-md-4">
						<div class="panel panel-default">
							<ul class="list-group">
								<li class="list-group-item list-group-item-time">
									<?php 
									echo tsml_format_day_and_time($meeting->day, $meeting->time);
									if (!empty($meeting->end_time)) {
										/* translators: until */
										echo __(' to ', '12-step-meeting-list'), tsml_format_time($meeting->end_time);
									}
									?>
								</li>
								<?php
								$other_meetings = count($meeting->location_meetings) - 1;
								echo tsml_link(
									get_permalink($meeting->post_parent),
										'<h4>' . $meeting->location . '</h4>' . 
										($other_meetings ? sprintf(_n('%d other meeting at this location', '%d other meetings at this location', $other_meetings, '12-step-meeting-list'), $other_meetings) : '')
									, 'tsml_meeting', 'list-group-item list-group-item-location');
								?>
								<a href="<?php echo $meeting->directions?>" class="list-group-item list-group-item-address">
									<?php echo tsml_format_address($meeting->formatted_address)?>
								</a>
								<?php 
								if (!empty($meeting->group_id)) {?>
									<li class="list-group-item list-group-item-group">
										<?php echo $meeting->group?>
									</li>
									<?php if (!empty($meeting->website)) {?>
									<a href="<?php echo $meeting->website?>" target="_blank" class="list-group-item list-group-item-group-website">
										<?php echo $meeting->website?>
									</a>
									<?php }
									if (!empty($meeting->email)) {?>
									<a href="mailto:<?php echo $meeting->email?>" class="list-group-item list-group-item-group-email">
										<?php echo $meeting->email?>
									</a>
									<?php }
									if (!empty($meeting->phone)) {?>
									<a href="tel:<?php echo $meeting->phone?>" class="list-group-item list-group-item-group-phone">
										<?php echo $meeting->phone?>
									</a>
									<?php }
									if (!empty($meeting->group_notes)) {?>
									<li class="list-group-item list-group-item-group-notes">
										<?php echo $meeting->group_notes?>
									</li>
									<?php }
								}
								if (!empty($meeting->region)) {?>
								<li class="list-group-item list-group-item-region">
									<?php echo $meeting->region?>
								</li>
								<?php }
								if (count($meeting->types)) {?>
								<li class="list-group-item list-group-item-types">
									<?php foreach ($meeting->types as $type) {?>
									<div><i class="glyphicon glyphicon-ok"></i> <?php echo $type?></div>
									<?php }?>
								</li>
								<?php }
								if (!empty($meeting->type_description)) {?>
									<li class="list-group-item"><?php echo $meeting->type_description?></li>
								<?php }
								if (!empty($meeting->notes)) {?>
								<li class="list-group-item list-group-item-notes">
									<?php echo $meeting->notes?>
								</li>
								<?php }
								if (!empty($meeting->location_notes)) {?>
								<li class="list-group-item list-group-item-location-notes">
									<?php echo $meeting->location_notes?>
								</li>
								<?php }?>
								<li class="list-group-item list-group-item-updated">
									<?php _e('Updated', '12-step-meeting-list')?>
									<?php the_modified_date()?>
								</li>
							</ul>
						</div>
	
						<?php if (!empty($tsml_feedback_addresses)) {?>
						<div id="feedback">
							<button class="btn btn-default btn-block"><?php _e('Request a Change', '12-step-meeting-list')?></button>
							
							<form>
								<input type="hidden" name="action" value="tsml_feedback">
								<input type="hidden" name="tsml_formatted_address" value="<?php echo $meeting->formatted_address?>">
								<input type="hidden" name="tsml_url" value="<?php echo admin_url('post.php?post=' . get_the_ID() . '&action=edit')?>">
								<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
								<div class="form-group">
									<input type="text" id="tsml_name" name="tsml_name" placeholder="<?php _e('Your Name', '12-step-meeting-list')?>" class="form-control required">
								</div>
								<div class="form-group">
									<input type="email" id="tsml_email" name="tsml_email" placeholder="<?php _e('Email Address', '12-step-meeting-list')?>" class="form-control required email">
								</div>
								<div class="form-group">
									<textarea id="tsml_message" name="tsml_message" placeholder="<?php _e('Message', '12-step-meeting-list')?>" class="form-control required"></textarea>
								</div>
								<div class="row">
									<div class="col-xs-8 form-group">
										<input type="submit" class="btn btn-primary btn-block" value="<?php _e('Submit', '12-step-meeting-list')?>">
									</div>
									<div class="col-xs-4 form-group">
										<button class="btn btn-default btn-block"><?php _e('Cancel', '12-step-meeting-list')?></button>
									</div>
								</div>
							</form>
							
							<div class="alert alert-warning"></div>
						</div>
						<?php }?>
					</div>
					<div class="col-md-8">
						<div id="map" class="panel panel-default"></div>
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
