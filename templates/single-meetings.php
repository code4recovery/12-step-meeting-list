<?php 

tsml_assets();

get_header();

$meeting = tsml_get_meeting();

?>

<div id="meeting" class="container">
	<div class="row">
		<div class="col-md-10 col-md-offset-1 main">
		
			<div class="page-header">
				<h1><?php echo tsml_format_name($meeting->post_title, $meeting->types)?></h1>
				<?php echo tsml_link(get_post_type_archive_link('tsml_meeting'), '<i class="glyphicon glyphicon-chevron-right"></i> ' . __('Back to Meetings', '12-step-meeting-list'), 'meetings')?>
			</div>

			<div class="row">
				<div class="col-md-4">
					<div class="panel panel-default">
						<ul class="list-group">
							<li class="list-group-item">
								<?php echo tsml_format_day_and_time($meeting->day, $meeting->time)?>
								<?php if (!empty($meeting->end_time)) echo _e(' to ', '12-step-meeting-list') . tsml_format_time($meeting->end_time)?>
							</li>
							<a href="<?php echo $meeting->directions?>" class="list-group-item">
								<h4><?php echo $meeting->location?></h4>
								<?php echo tsml_format_address($meeting->formatted_address)?>
							</a>
							<?php if (count($meeting->location_meetings) > 1) {
								$other_meetings = count($meeting->location_meetings) - 1;
								?>
							<?php echo tsml_link(
								get_permalink($meeting->post_parent),
								sprintf(_n('%d other meeting at this location', '%d other meetings at this location', $other_meetings),
								$other_meetings), 'meeting', 'list-group-item')?>
							</a>
							<?php }?>
							<?php if (!empty($meeting->group_id)) {?>
							<li class="list-group-item">
								<?php echo $meeting->group?>
							</li>
							<?php if (!empty($meeting->group_notes)) {?>
							<li class="list-group-item">
								<?php echo $meeting->group_notes?>
							</li>
							<?php }
							}?>
							<?php if (!empty($meeting->region)) {?>
							<li class="list-group-item">
								<?php echo $meeting->region?>
							</li>
							<?php }?>
							<?php if (count($meeting->types)) {?>
							<li class="list-group-item">
								<?php foreach ($meeting->types as $type) {?>
								<div><i class="glyphicon glyphicon-ok"></i> <?php echo $type?></div>
								<?php }?>
							</li>
							<?php }?>
							<?php if (in_array(__('Open', '12-step-meeting-list'), $meeting->types)) {
								echo '<li class="list-group-item">' . __('This meeting is open and anyone may attend.') . '</li>';
							} elseif (in_array(__('Closed', '12-step-meeting-list'), $meeting->types)) {
								echo '<li class="list-group-item">' . __('This meeting is closed; only those who have a desire to stop drinking may attend.') . '</li>';
							}?>
							<?php if (!empty($meeting->notes)) {?>
							<li class="list-group-item">
								<?php echo $meeting->notes?>
							</li>
							<?php }?>
							<?php if (!empty($meeting->location_notes)) {?>
							<li class="list-group-item">
								<?php echo $meeting->location_notes?>
							</li>
							<?php }?>
							<li class="list-group-item">
								<?php _e('Updated')?>
								<?php the_modified_date()?>
							</li>
						</ul>
					</div>

					<?php if (!empty($tsml_feedback_addresses)) {?>
					<div id="feedback">
						<a href="#feedback" class="btn btn-default btn-block"><?php _e('Request a Change', '12-step-meeting-list')?></a>
						
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
							<input type="submit" class="btn btn-primary" value="<?php _e('Submit', '12-step-meeting-list')?>">
							<a href="#cancel" class="btn btn-default"><?php _e('Cancel', '12-step-meeting-list')?></a>
						</form>
						
						<div class="alert alert-warning"></div>
					</div>
					<?php }?>
				</div>
				<div class="col-md-8">
					<div id="map" class="panel panel-default"></div>
					<script>
						var map;

						google.maps.event.addDomListener(window, 'load', function() {
							map_desktop = new google.maps.Map(document.getElementById('map'), {
								zoom: 15,
								panControl: false,
								mapTypeControl: false,
								zoomControlOptions: { style: google.maps.ZoomControlStyle.SMALL },
								center: new google.maps.LatLng(<?php echo $meeting->latitude + .0025 . ',' . $meeting->longitude?>),
								mapTypeId: google.maps.MapTypeId.ROADMAP
							});

							var contentString = '<div class="infowindow">'+
								'<h3><?php echo tsml_link(get_permalink($meeting->post_parent), $meeting->location, 'meetings')?></h3>'+
								'<p><?php echo tsml_format_address($meeting->formatted_address)?></p>'+
								'<p><a class="btn btn-default" href="<?php echo $meeting->directions?>"><?php _e('Directions', '12-step-meeting-list')?></a></p>' +
								'</div>';

							var infowindow = new google.maps.InfoWindow({
								content: contentString
							});

							var marker = new google.maps.Marker({
								position: new google.maps.LatLng(<?php echo $meeting->latitude?>,<?php echo $meeting->longitude?>),
								map: map_desktop,
								title: '<?php the_title(); ?>'
							});

							infowindow.open(map_desktop, marker);

							google.maps.event.addListener(marker, 'click', function() {
								infowindow.open(map_desktop, marker);
							});
						});
					</script>
				</div>
			</div>
		</div>
	</div>
</div>
<?php 
get_footer();
