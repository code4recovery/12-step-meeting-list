<?php 

tsml_assets('public');

get_header();

$tsml_custom	= get_post_meta($post->ID);
$tsml_parent	= get_post($post->post_parent);
$tsml_custom	= array_merge($tsml_custom, get_post_meta($tsml_parent->ID));
$tsml_custom['types'][0] = empty($tsml_custom['types'][0]) ? array() : unserialize($tsml_custom['types'][0]);
$tsml_back		= wp_get_referer() ?: get_post_type_archive_link('meetings');
?>

<div class="container">
	<div class="row">
		<div class="col-md-10 col-md-offset-1 main">
		
			<div class="page-header">
				<h1><?php echo tsml_format_name($post->post_title, $tsml_custom['types'][0])?></h1>
				<a href="<?php echo $tsml_back?>"><i class="glyphicon glyphicon-chevron-right"></i> Back to Meetings</a>
			</div>

			<div class="row">
				<div class="col-md-4">
					<dl>
						<dt>Time</dt>
						<dd>
							<?php echo $tsml_days[$tsml_custom['day'][0]]?>s at 
							<?php echo tsml_format_time($tsml_custom['time'][0])?>
						</dd>
						<br>
						<dt>Location</dt>
						<dd><a href="<?php echo get_permalink($tsml_parent->ID)?>"><?php echo $tsml_parent->post_title?></a></dd>
						<dd><?php echo $tsml_custom['address'][0]?><br><?php echo $tsml_custom['city'][0]?>, <?php echo $tsml_custom['state'][0]?></dd>
						<br>
						<dt>Region</dt>
						<dd><?php echo $tsml_regions[$tsml_custom['region'][0]]?></dd>
						<br>
						<?php 
						if (count($tsml_custom['types'][0])) {
							foreach ($tsml_custom['types'][0] as &$type) $type = $tsml_types[trim($type)];
							?>
							<dt>Type</dt>
							<dd><?php echo implode(', ', $tsml_custom['types'][0])?></dd>
						<?php }?>
						<?php if (!empty($post->post_content)) {?>
						<br>
						<dt>Notes</dt>
						<dd><?php echo nl2br(esc_html($post->post_content))?></dd>
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
								center: new google.maps.LatLng(<?php echo $tsml_custom['latitude'][0] + .0025 ?>,<?php echo $tsml_custom['longitude'][0]?>),
								mapTypeId: google.maps.MapTypeId.ROADMAP
							});

							var contentString = '<div class="infowindow">'+
							  '<h3><a href="<?php echo get_permalink($tsml_parent->ID)?>"><?php echo esc_attr_e($tsml_parent->post_title)?></a></h3>'+
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