<?php 
tsml_assets();

$location = tsml_get_location();

//define some vars for the map
wp_localize_script('tsml_public', 'tsml_map', array(
	'latitude' => $location->latitude,
	'longitude' => $location->longitude,
	'location' => get_the_title(),
	'location_id' => $location->ID,
	'formatted_address' => $location->formatted_address,
	'directions_url' => $location->directions,
	'directions' => __('Directions', '12-step-meeting-list'),
));

//adding custom body classes
add_filter('body_class', 'tsml_body_class');
function tsml_body_class($classes) {
	$classes[] = 'tsml tsml-detail tsml-location';
	return $classes;
}

get_header(); 
?>

<div id="tsml">
	<div id="location" class="container">
		<div class="row">
			<div class="col-md-10 col-md-offset-1 main">
			
				<div class="page-header">
					<h1><?php echo $location->post_title?></h1>
					<?php echo tsml_link(get_post_type_archive_link('tsml_meeting'), '<i class="glyphicon glyphicon-chevron-right"></i> ' . __('Back to Meetings', '12-step-meeting-list'), 'tsml_location')?>
				</div>
	
				<div class="row location">
					<div class="col-md-4">
						<div class="panel panel-default">
							<a class="panel-heading tsml-directions" data-latitude="<?php echo $location->latitude?>" data-longitude="<?php echo $location->longitude?>" data-location="<?php echo $location->post_title?>">
								<h3 class="panel-title">
									<?php _e('Get Directions', '12-step-meeting-list')?>
									<span class="panel-title-buttons">
										<span class="glyphicon glyphicon-share-alt"></span>
									</span>
								</h3>
							</a>
						</div>

						<div class="panel panel-default">
							<ul class="list-group">
								<li class="list-group-item list-group-item-address">
									<p><?php echo tsml_format_address($location->formatted_address)?></p>
									<?php if ($location->region && !strpos($location->formatted_address, $location->region)) {?>
									<p><?php echo $location->region?></p>
									<?php }
									if ($location->notes) {?>
									<p><?php echo $location->notes?></p>
									<?php }	?>
								</li>
								
								<?php 
								$meetings = tsml_get_meetings(array('location_id'=>$location->ID));
								$location_days = array();
								foreach ($meetings as $meeting) {
									if (!isset($location_days[$meeting['day']])) $location_days[$meeting['day']] = array();
									$location_days[$meeting['day']][] = '<li><span>' . $meeting['time_formatted'] . '</span> ' . tsml_link($meeting['url'], tsml_format_name($meeting['name'], $meeting['types']), 'tsml_location') . '</li>';
								}
								ksort($location_days);
								if (count($location_days)) {?>
								<li class="list-group-item list-group-item-meetings">						
								<?php foreach ($location_days as $day=>$meetings) {?>
									<h4><?php if (!empty($tsml_days[$day])) echo $tsml_days[$day]?></h4>
									<ul><?php echo implode($meetings)?></ul>
								<?php }?>
								</li>
								<?php }?>
	
								<li class="list-group-item list-group-item-updated">
									<?php _e('Updated', '12-step-meeting-list')?>
									<?php the_modified_date()?>
								</li>
							</ul>
						</div>
					</div>
					<div class="col-md-8">
						<div id="map" class="panel panel-default"></div>
					</div>
				</div>
			
			</div>
		</div>
		
		<?php if (is_active_sidebar('tsml_location_bottom')) {?>
			<div class="widgets location-widgets location-widgets-bottom" role="complementary">
				<?php dynamic_sidebar('tsml_location_bottom')?>
			</div>
		<?php }?>
		
	</div>
</div>
<?php
get_footer();