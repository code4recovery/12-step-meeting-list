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
				<?php echo tsml_link(get_post_type_archive_link('tsml_meeting'), '<i class="glyphicon glyphicon-chevron-right"></i> ' . __('Back to Meetings'), 'meetings')?>
			</div>

			<div class="row">
				<div class="col-md-4">
					<dl>
						<dt><?php _e('Time')?></dt>
						<dd>
							<?php echo tsml_format_day_and_time($meeting->day, $meeting->time)?>
							<?php if (!empty($meeting->end_time)) echo _e(' to ') . tsml_format_time($meeting->end_time)?>
						</dd>

						<dt><?php _e('Location')?></dt>
						<dd>
							<?php 
							$other_meetings = count($meeting->location_meetings) - 1;
							echo tsml_link(get_permalink($meeting->post_parent), $meeting->location . (count($meeting->location_meetings) == 1 ? '' : '<br>(' . sprintf(_n('%d other meeting at this location', '%d other meetings at this location', $other_meetings), $other_meetings) . ')'), 'meetings')?>
							<br>
							<?php echo tsml_format_address($meeting->formatted_address)?>
						</dd>
						
						<?php if ($meeting->group_id) {?>
						<dt><?php _e('Group')?></dt>
						<dd>
							<p><?php echo $meeting->group?></p>
							<?php if (!empty($meeting->group_notes)) {?><p><?php echo $meeting->group_notes?></p><?php }?>
						</dd>
						<?php }?>
						
						<?php if ($meeting->region) {?>
						<dt><?php _e('Region')?></dt>
						<dd><?php echo $meeting->region?></dd>
						<?php }
						if (count($meeting->types)) {
							?>
							<dt><?php _e('Type')?></dt>
							<dd><?php echo implode(', ', $meeting->types)?></dd>
						<?php }
						if (!empty($meeting->notes)) {?>
						<dt><?php _e('Meeting Notes')?></dt>
						<dd><?php echo $meeting->notes?></dd>
						<?php } 
						if (!empty($meeting->location_notes)) {?>
						<dt><?php _e('Location Notes')?></dt>
						<dd><?php echo $meeting->location_notes?></dd>
						<?php } ?>
						<dt><?php _e('Updated')?></dt>
						<dd><?php the_modified_date()?></dd>
						
						<?php if (!empty($tsml_feedback_addresses)) {?>
						<div id="feedback">
							<dt>Feedback</dt>
							<dd>
								See something wrong? <a href="#report">Report an issue</a> with this listing.
							</dd>
							
							<form>
								<input type="hidden" name="action" value="tsml_feedback">
								<input type="hidden" name="tsml_formatted_address" value="<?php echo $meeting->formatted_address?>">
								<input type="hidden" name="tsml_url" value="<?php echo admin_url('post.php?post=' . get_the_ID() . '&action=edit')?>">
								<?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false)?>
								<div class="form-group">
									<label for="tsml_name">Your Name</label>
									<input type="text" id="tsml_name" name="tsml_name" placeholder="John Q." class="form-control required">
								</div>
								<div class="form-group">
									<label for="tsml_email">Email Address</label>
									<input type="email" id="tsml_email" name="tsml_email" placeholder="john@example.org" class="form-control required email">
								</div>
								<div class="form-group">
									<label for="tsml_message">Message</label>
									<textarea id="tsml_message" name="tsml_message" placeholder="Please be specific." class="form-control required"></textarea>
								</div>
								<input type="submit" class="btn btn-default" value="Submit"> or <a href="#cancel">Cancel</a>
							</form>
							
							<div class="alert alert-warning"></div>
						</div>
						<?php }?>
					</dl>
				</div>
				<div class="col-md-8">
					<div id="map" style="height:400px;"></div>
					<script>
						var map;

						google.maps.event.addDomListener(window, 'load', function() {
							map = new google.maps.Map(document.getElementById('map'), {
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
								'<p><a class="btn btn-default" href="http://maps.apple.com/?q=<?php echo $meeting->latitude . ',' . $meeting->longitude?>&z=16" target="_blank"><?php _e('Directions')?></a></p>' +
								'</div>';

							var infowindow = new google.maps.InfoWindow({
								content: contentString
							});

							var marker = new google.maps.Marker({
								position: new google.maps.LatLng(<?php echo $meeting->latitude?>,<?php echo $meeting->longitude?>),
								map: map,
								title: '<?php the_title(); ?>'
							});

							infowindow.open(map,marker);

							google.maps.event.addListener(marker, 'click', function() {
								infowindow.open(map,marker);
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
