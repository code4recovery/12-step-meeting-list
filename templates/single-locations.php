<?php 
tsml_assets();

$location = tsml_get_location();

//define some vars for the map
wp_localize_script('tsml_public', 'tsml_map', array(
	'latitude' => $location->latitude,
	'longitude' => $location->longitude,
	'location' => get_the_title(),
	'address' => $location->formatted_address,
	'directions_url' => $location->directions,
	'directions' => __('Directions', '12-step-meeting-list'),
));

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
							<ul class="list-group">
								<a href="<?php echo $location->directions?>" class="list-group-item list-group-item-address">
									<?php echo tsml_format_address($location->formatted_address)?>
								</a>
	
								<?php if ($location->region) {?>
									<li class="list-group-item list-group-item-region"><?php echo $location->region?></li>
								<?php }
									
								if (!empty($location->notes)) {?>
									<li class="list-group-item list-group-item-location-notes"><?php echo $location->notes?></li>
								<?php }
								
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
									<ul class="meetings"><?php echo implode($meetings)?></ul>
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