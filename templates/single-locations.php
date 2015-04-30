<?php 

//get assets for page
wp_enqueue_style('bootstrap-css', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css');
wp_enqueue_script('bootstrap-js', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js', array('jquery'));
wp_enqueue_script('google_maps-js',	'//maps.googleapis.com/maps/api/js?sensor=false');
wp_enqueue_script('main.js', plugin_dir_url(__DIR__ . '/../js') . '/js/archive-meetings.js');
wp_enqueue_style('main.css', plugin_dir_url(__DIR__ . '/../css') . '/css/archive-meetings.min.css');

get_header(); ?>

<div class="container">
	<div class="row">
		<div class="col-md-10 col-md-offset-1">
		
			<?php 
			$custom = get_post_meta($post->ID);
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
						<dd><?php echo $custom['address'][0]?><br><?php echo $custom['city'][0]?>, <?php echo $custom['state'][0]?></dd>

						<dt>Region</dt>
						<dd><?php echo $regions[$custom['region'][0]]?></dd>
						<?php if (!empty($post->post_content)) {?>

						<dt>Notes</dt>
						<dd><?php echo nl2br($post->post_content)?></dd>
						<?php } 
						$meetings = meetings_get(array('location_id'=>$post->ID));
						$days = array();
						foreach ($meetings as $meeting) {
							if (!isset($days[$meeting['day']])) $days[$meeting['day']] = array();
							$days[$meeting['day']][] = '<li><span>' . $meeting['time_formatted'] . '</span> <a href="' . $meeting['url'] . '">'. meetings_name($meeting['name'], $meeting['types']) . '</a></li>';
						}
						ksort($days);
						foreach ($days as $day=>$meetings) {
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
								center: new google.maps.LatLng(<?php echo $custom['latitude'][0] + .0025 ?>,<?php echo $custom['longitude'][0]?>),
								mapTypeId: google.maps.MapTypeId.ROADMAP
							});

							var contentString = '<div class="infowindow">'+
							  '<h3><?php esc_attr_e($parent->post_title)?></h3>'+
							  '<p><?php esc_attr_e($custom['address'][0])?><br><?php esc_attr_e($custom['city'][0])?>, <?php echo $custom['state'][0]?></p>'+
							  '<p><a class="btn btn-default" href="http://maps.apple.com/?q=<?php echo urlencode($custom['formatted_address'][0])?>" target="_blank">Directions</a></p>' +
							  '</div>';

							var infowindow = new google.maps.InfoWindow({
							  content: contentString
							});

							var marker = new google.maps.Marker({
							  position: new google.maps.LatLng(<?php echo $custom['latitude'][0]?>,<?php echo $custom['longitude'][0]?>),
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