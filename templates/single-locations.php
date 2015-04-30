<?php 

md_assets('public');

get_header(); ?>

<div class="container">
	<div class="row">
		<div class="col-md-10 col-md-offset-1">
		
			<?php 
			$md_custom = get_post_meta($post->ID);
			$parent = get_post($post->post_parent);
			?>
		
			<div class="page-header">
				<h1><?php echo $post->post_title ?></h1>
				<a href="<?php echo get_post_type_archive_link('meetings'); ?>"><i class="glyphicon glyphicon-chevron-right"></i> Back to Meetings</a>
			</div>

			<div class="row location">
				<div class="col-md-4 meta">
					<dl>
						<dt>Location</dt>
						<dd><?php echo $md_custom['address'][0]?><br><?php echo $md_custom['city'][0]?>, <?php echo $md_custom['state'][0]?></dd>

						<dt>Region</dt>
						<dd><?php echo $md_regions[$md_custom['region'][0]]?></dd>
						<?php if (!empty($post->post_content)) {?>

						<dt>Notes</dt>
						<dd><?php echo nl2br($post->post_content)?></dd>
						<?php } 
						$meetings = md_get_meetings(array('location_id'=>$post->ID));
						$md_days = array();
						foreach ($meetings as $meeting) {
							if (!isset($md_days[$meeting['day']])) $md_days[$meeting['day']] = array();
							$md_days[$meeting['day']][] = '<li><span>' . $meeting['time_formatted'] . '</span> <a href="' . $meeting['url'] . '">'. md_format_name($meeting['name'], $meeting['types']) . '</a></li>';
						}
						ksort($md_days);
						foreach ($md_days as $day=>$meetings) {
							echo '<dt>' . $aasj_days[$day] . '</dt><dd><ul>' . implode($meetings) . '</ul></dd>';
						}
						?>

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
								center: new google.maps.LatLng(<?php echo $md_custom['latitude'][0] + .0025 ?>,<?php echo $md_custom['longitude'][0]?>),
								mapTypeId: google.maps.MapTypeId.ROADMAP
							});

							var contentString = '<div class="infowindow">'+
							  '<h3><?php esc_attr_e($parent->post_title)?></h3>'+
							  '<p><?php esc_attr_e($md_custom['address'][0])?><br><?php esc_attr_e($md_custom['city'][0])?>, <?php echo $md_custom['state'][0]?></p>'+
							  '<p><a class="btn btn-default" href="http://maps.apple.com/?q=<?php echo urlencode($md_custom['formatted_address'][0])?>" target="_blank">Directions</a></p>' +
							  '</div>';

							var infowindow = new google.maps.InfoWindow({
							  content: contentString
							});

							var marker = new google.maps.Marker({
							  position: new google.maps.LatLng(<?php echo $md_custom['latitude'][0]?>,<?php echo $md_custom['longitude'][0]?>),
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