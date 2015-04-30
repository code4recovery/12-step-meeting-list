<?php 

md_assets('public');

get_header(); ?>

<div class="container">
	<div class="row">
		<div class="col-md-10 col-md-offset-1">
		
			<?php 
			$md_custom = get_post_meta($post->ID);
			$md_custom['types'][0] = empty($md_custom['types'][0]) ? array() : unserialize($md_custom['types'][0]);
			$parent = get_post($post->post_parent);
			$md_custom = array_merge($md_custom, get_post_meta($parent->ID));
			?>
		
			<div class="page-header">
				<h1><?php echo md_format_name($post->post_title, $md_custom['types'][0]) ?></h1>
				<a href="<?php echo get_post_type_archive_link('meetings'); ?>"><i class="glyphicon glyphicon-chevron-right"></i> Back to Meetings</a>
			</div>

			<div class="row">
				<div class="col-md-4">
					<dl>
						<dt>Time</dt>
						<dd>
							<?php echo $md_days[$md_custom['day'][0]]?>s at 
							<?php echo md_format_time($md_custom['time'][0])?>
						</dd>
						<br>
						<dt>Location</dt>
						<dd><a href="<?php echo get_permalink($parent->ID)?>"><?php echo $parent->post_title?></a></dd>
						<dd><?php echo $md_custom['address'][0]?><br><?php echo $md_custom['city'][0]?>, <?php echo $md_custom['state'][0]?></dd>
						<br>
						<dt>Region</dt>
						<dd><?php echo $md_regions[$md_custom['region'][0]]?></dd>
						<br>
						<?php 
						if (count($md_custom['types'][0])) {
							foreach ($md_custom['types'][0] as &$type) $type = $md_types[trim($type)];
							?>
							<dt>Type</dt>
							<dd><?php echo implode(', ', $md_custom['types'][0])?></dd>
						<?php }?>
						<?php if (!empty($post->post_content)) {?>
						<br>
						<dt>Notes</dt>
						<dd><?php echo nl2br($post->post_content)?></dd>
						<?php } ?>
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
							  '<h3><a href="<?php echo get_permalink($parent->ID)?>"><?php echo esc_attr_e($parent->post_title)?></a></h3>'+
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