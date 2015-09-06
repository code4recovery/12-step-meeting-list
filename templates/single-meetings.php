<?php 

tsml_assets('public');

get_header();

$meeting_custom				= get_post_meta($post->ID);
$location					= get_post($post->post_parent);
$meeting_custom				= array_merge($meeting_custom, get_post_meta($location->ID));
$meeting_custom['types'][0] = empty($meeting_custom['types'][0]) ? array() : unserialize($meeting_custom['types'][0]);
$post->post_title			= htmlentities($post->post_title, ENT_QUOTES);
$location->post_title		= htmlentities($location->post_title, ENT_QUOTES);
?>

<div id="meeting" class="container">
	<div class="row">
		<div class="col-md-10 col-md-offset-1 main">
		
			<div class="page-header">
				<h1><?php echo tsml_format_name($post->post_title, $meeting_custom['types'][0])?></h1>
				<?php echo tsml_link(get_post_type_archive_link('meetings'), '<i class="glyphicon glyphicon-chevron-right"></i> Back to Meetings', 'meetings')?>
			</div>

			<div class="row">
				<div class="col-md-4">
					<dl>
						<dt><?php _e('Time', '12-step-meeting-list')?></dt>
						<dd>
							<?php echo tsml_format_day_and_time($meeting_custom['day'][0], $meeting_custom['time'][0])?>
						</dd>

						<dt><?php _e('Location', '12-step-meeting-list')?></dt>
						<dd>
							<?php echo tsml_link(get_permalink($location->ID), $location->post_title, 'meetings')?>
							<br>
							<?php echo $meeting_custom['address'][0]?>
							<br>
							<?php echo $meeting_custom['city'][0]?>, <?php echo $meeting_custom['state'][0]?> <?php echo $meeting_custom['postal_code'][0]?>
						</dd>

						<?php if (!empty($tsml_regions[$meeting_custom['region'][0]])) {?>
						<dt><?php _e('Region', '12-step-meeting-list')?></dt>
						<dd><?php echo $tsml_regions[$meeting_custom['region'][0]]?></dd>
						<?php }
						if (count($meeting_custom['types'][0])) {
							foreach ($meeting_custom['types'][0] as &$type) $type = $tsml_types[$tsml_program][trim($type)];
							?>
							<dt><?php _e('Type', '12-step-meeting-list')?></dt>
							<dd><?php echo implode(', ', $meeting_custom['types'][0])?></dd>
						<?php }
						if (!empty($post->post_content)) {?>
						<dt><?php _e('Meeting Notes', '12-step-meeting-list')?></dt>
						<dd><?php echo nl2br(esc_html($post->post_content))?></dd>
						<?php } 
						if (!empty($location->post_content)) {?>
						<dt><?php _e('Location Notes', '12-step-meeting-list')?></dt>
						<dd><?php echo nl2br(esc_html($location->post_content))?></dd>
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
								center: new google.maps.LatLng(<?php echo $meeting_custom['latitude'][0] + .0025 ?>,<?php echo $meeting_custom['longitude'][0]?>),
								mapTypeId: google.maps.MapTypeId.ROADMAP
							});

							var contentString = '<div class="infowindow">'+
								'<h3><?php echo tsml_link(get_permalink($location->ID), $location->post_title, 'meetings')?></h3>'+
								'<p><?php esc_attr_e($meeting_custom['address'][0])?><br><?php esc_attr_e($meeting_custom['city'][0])?>, <?php echo $meeting_custom['state'][0]?> <?php echo $meeting_custom['postal_code'][0]?></p>'+
								'<p><a class="btn btn-default" href="http://maps.apple.com/?q=<?php echo urlencode($meeting_custom['formatted_address'][0])?>" target="_blank">Directions</a></p>' +
								'</div>';

							var infowindow = new google.maps.InfoWindow({
								content: contentString
							});

							var marker = new google.maps.Marker({
								position: new google.maps.LatLng(<?php echo $meeting_custom['latitude'][0]?>,<?php echo $meeting_custom['longitude'][0]?>),
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