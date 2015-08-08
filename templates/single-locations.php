<?php 

tsml_assets('public');

get_header(); 

$tsml_custom	= get_post_meta($post->ID);
$tsml_parent	= get_post($post->post_parent);
?>

<div id="location" class="container">
	<div class="row">
		<div class="col-md-10 col-md-offset-1 main">
		
			<div class="page-header">
				<h1><?php echo $post->post_title?></h1>
				<?php echo tsml_link(get_post_type_archive_link('meetings'), '<i class="glyphicon glyphicon-chevron-right"></i> Back to Meetings', 'locations')?>
			</div>

			<div class="row location">
				<div class="col-md-4 meta">
					<dl>
						<dt><?php _e('Location', '12-step-meeting-list')?></dt>
						<dd><?php echo $tsml_custom['address'][0]?><br><?php echo $tsml_custom['city'][0]?>, <?php echo $tsml_custom['state'][0]?></dd>

						<dt><?php _e('Region', '12-step-meeting-list')?></dt>
						<dd><?php echo $tsml_regions[$tsml_custom['region'][0]]?></dd>
						<?php 
							
						if (!empty($post->post_content)) {?>
						<dt><?php _e('Notes', '12-step-meeting-list')?></dt>
						<dd><?php echo nl2br(esc_html($post->post_content))?></dd>
						<?php }
						
						$meetings = tsml_get_meetings(array('location_id'=>$post->ID));
						$location_days = array();
						foreach ($meetings as $meeting) {
							if (!isset($location_days[$meeting['day']])) $location_days[$meeting['day']] = array();
							$location_days[$meeting['day']][] = '<li><span>' . $meeting['time_formatted'] . '</span> ' . tsml_link($meeting['url'], tsml_format_name($meeting['name'], $meeting['types']), 'locations') . '</li>';
						}
						ksort($location_days);
						foreach ($location_days as $day=>$meetings) {?>
							<dt><?php echo $tsml_days[$day]?></dt>
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
								center: new google.maps.LatLng(<?php echo $tsml_custom['latitude'][0] + .0025 ?>,<?php echo $tsml_custom['longitude'][0]?>),
								mapTypeId: google.maps.MapTypeId.ROADMAP
							});

							var contentString = '<div class="infowindow">'+
								'<h3><?php esc_attr_e($tsml_parent->post_title)?></h3>'+
								'<p><?php esc_attr_e($tsml_custom['address'][0])?><br><?php esc_attr_e($tsml_custom['city'][0])?>, <?php echo $tsml_custom['state'][0]?></p>'+
								'<p><a class="btn btn-default" href="http://maps.apple.com/?q=<?php echo urlencode($tsml_custom['formatted_address'][0])?>" target="_blank">Directions</a></p>' +
								'</div>';

							var infowindow = new google.maps.InfoWindow({
								content: contentString
							});

							var marker = new google.maps.Marker({
								position: new google.maps.LatLng(<?php echo $tsml_custom['latitude'][0]?>,<?php echo $tsml_custom['longitude'][0]?>),
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