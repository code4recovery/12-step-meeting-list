<?php 
tsml_assets();
get_header(); 
$location = tsml_get_location();
?>
<div id="location" class="container">
	<div class="row">
		<div class="col-md-10 col-md-offset-1 main">
		
			<div class="page-header">
				<h1><?php echo $location->post_title?></h1>
				<?php echo tsml_link(get_post_type_archive_link('meetings'), '<i class="glyphicon glyphicon-chevron-right"></i> ' . __('Back to Meetings', '12-step-meeting-list'), 'locations')?>
			</div>

			<div class="row location">
				<div class="col-md-4 meta">
					<dl>
						<dt><?php _e('Location', '12-step-meeting-list')?></dt>
						<dd>
							<?php if (!empty($location->address)) echo $location->address . '<br>'?>
							<?php echo $location->city?>, <?php echo $location->state?> <?php echo $location->postal_code?>
							<?php if (!empty($meeting->country) && $meeting->country != 'US') echo '<br>' . $meeting->country?>
						</dd>

						<?php if (!empty($tsml_regions[$location->region])) {?>
						<dt><?php _e('Region', '12-step-meeting-list')?></dt>
						<dd><?php echo $tsml_regions[$location->region]?></dd>
						<?php }
							
						if (!empty($location->notes)) {?>
						<dt><?php _e('Notes', '12-step-meeting-list')?></dt>
						<dd><?php echo $location->notes?></dd>
						<?php }
						
						$meetings = tsml_get_meetings(array('location_id'=>$location->ID));
						$location_days = array();
						foreach ($meetings as $meeting) {
							if (!isset($location_days[$meeting['day']])) $location_days[$meeting['day']] = array();
							$location_days[$meeting['day']][] = '<li><span>' . $meeting['time_formatted'] . '</span> ' . tsml_link($meeting['url'], tsml_format_name($meeting['name'], $meeting['types']), 'locations') . '</li>';
						}
						ksort($location_days);
						foreach ($location_days as $day=>$meetings) {?>
							<dt><?php if (!empty($tsml_days[$day])) echo $tsml_days[$day]?></dt>
							<dd><ul><?php echo implode($meetings)?></ul></dd>
						<?php }?>

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
								center: new google.maps.LatLng(<?php echo $location->latitude + .0025 . ',' . $location->longitude?>),
								mapTypeId: google.maps.MapTypeId.ROADMAP
							});

							var contentString = '<div class="infowindow">'+
								'<h3><?php esc_attr_e($location->post_title)?></h3>'+
								'<p><?php esc_attr_e($location->address)?><br><?php esc_attr_e($location->city)?>, <?php echo $location->state?> <?php echo $location->postal_code?></p>'+
								'<p><a class="btn btn-default" href="http://maps.apple.com/?q=<?php echo $location->latitude . ',' . $location->longitude?>&z=16" target="_blank"><?php _e('Directions', '12-step-meeting-list')?></a></p>' +
								'</div>';

							var infowindow = new google.maps.InfoWindow({
								content: contentString
							});

							var marker = new google.maps.Marker({
								position: new google.maps.LatLng(<?php echo $location->latitude . ',' . $location->longitude?>),
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