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
				<?php echo tsml_link(get_post_type_archive_link('meetings'), '<i class="glyphicon glyphicon-chevron-right"></i> Back to Meetings', 'meetings')?>
			</div>

			<div class="row">
				<div class="col-md-4">
					<dl>
						<dt><?php _e('Time', '12-step-meeting-list')?></dt>
						<dd>
							<?php echo tsml_format_day_and_time($meeting->day, $meeting->time)?>
						</dd>

						<dt><?php _e('Location', '12-step-meeting-list')?></dt>
						<dd>
							<?php echo tsml_link(get_permalink($meeting->post_parent), $meeting->location, 'meetings')?>
							<br>
							<?php echo $meeting->address?>
							<br>
							<?php echo $meeting->city?>, <?php echo $meeting->state?> <?php echo $meeting->postal_code?>
						</dd>
						
						<?php if ($meeting->group_id) {?>
						<dt><?php _e('Group', '12-step-meeting-list')?></dt>
						<dd>
							<?php echo $meeting->group?><br>
							<?php echo $meeting->group_notes?>
						</dd>
						<?php }?>
						
						<?php if (!empty($tsml_regions[$meeting->region])) {?>
						<dt><?php _e('Region', '12-step-meeting-list')?></dt>
						<dd><?php echo $tsml_regions[$meeting->region]?></dd>
						<?php }
						if (count($meeting->types)) {
							foreach ($meeting->types as &$type) $type = $tsml_types[$tsml_program][trim($type)];
							?>
							<dt><?php _e('Type', '12-step-meeting-list')?></dt>
							<dd><?php echo implode(', ', $meeting->types)?></dd>
						<?php }
						if (!empty($meeting->notes)) {?>
						<dt><?php _e('Meeting Notes', '12-step-meeting-list')?></dt>
						<dd><?php echo $meeting->notes?></dd>
						<?php } 
						if (!empty($meeting->location_notes)) {?>
						<dt><?php _e('Location Notes', '12-step-meeting-list')?></dt>
						<dd><?php echo $meeting->location_notes?></dd>
						<?php } ?>
						<dt><?php _e('Updated', '12-step-meeting-list')?></dt>
						<dd><?php the_modified_date()?></dd>
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
								center: new google.maps.LatLng(<?php echo $meeting->latitude + .0025 ?>,<?php echo $meeting->longitude?>),
								mapTypeId: google.maps.MapTypeId.ROADMAP
							});

							var contentString = '<div class="infowindow">'+
								'<h3><?php echo tsml_link(get_permalink($meeting->post_parent), $meeting->location, 'meetings')?></h3>'+
								'<p><?php esc_attr_e($meeting->address)?><br><?php esc_attr_e($meeting->city)?>, <?php echo $meeting->state?> <?php echo $meeting->postal_code?></p>'+
								'<p><a class="btn btn-default" href="http://maps.apple.com/?q=<?php echo urlencode($meeting->formatted_address)?>">Directions</a></p>' +
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

<?php get_footer(); ?>