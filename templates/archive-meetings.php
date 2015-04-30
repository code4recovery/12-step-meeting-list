<?php

//get assets for page
wp_enqueue_style('bootstrap-css', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css');
wp_enqueue_script('bootstrap-js', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js', array('jquery'));
wp_enqueue_script('google_maps-js',	'//maps.googleapis.com/maps/api/js?sensor=false');
wp_enqueue_script('main.js', plugin_dir_url(__DIR__ . '/../js') . '/js/archive-meetings.js');
wp_localize_script('main.js', 'meetings', array('ajaxurl' => admin_url('admin-ajax.php')));
wp_enqueue_style('main.css', plugin_dir_url(__DIR__ . '/../css') . '/css/archive-meetings.min.css');

get_header();

$today = date('w');
$locations = array();
$meetings = meetings_get(array('day'=>date('w')));

?>
<div id="meetings" data-type="list" class="container">
	<div class="row controls">
		<div class="col-md-2">
			<form id="search">
				<div class="input-group">
					<input type="text" name="query" class="form-control" placeholder="Search">
					<span class="input-group-btn">
						<button class="btn btn-default" type="submit"><i class="glyphicon glyphicon-search"></i></button>
					</span>
				</div>
			</form>
		</div>
		<div class="col-md-2">
			<div class="dropdown" id="day">
				<a data-toggle="dropdown" class="btn btn-default btn-block">
					<span class="selected"><?php echo $days[$today]?></span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu">
					<li><a href="#">Any Day</a></li>
					<li class="divider"></li>
					<?php foreach ($days as $key=>$day) {?>
					<li<?php if ($key == $today) echo ' class="active"'?>><a href="#" data-id="<?php echo $key?>"><?php echo $day?></a></li>
					<?php }?>
				</ul>
			</div>
		</div>
		<div class="col-md-2">
			<div class="dropdown" id="region">
				<a data-toggle="dropdown" class="btn btn-default btn-block">
					<span class="selected">Everywhere</span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu">
					<li class="active"><a href="#">Everywhere</a></li>
					<li class="divider"></li>
					<?php foreach ($regions as $key=>$region) {?>
					<li><a href="#" data-id="<?php echo $key?>"><?php echo $region?></a></li>
					<?php }?>
				</ul>
			</div>
		</div>
		<div class="col-md-2">
			<div class="dropdown" id="types">
				<a data-toggle="dropdown" class="btn btn-default btn-block">
					<span class="selected">Meeting Type</span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu">
					<?php foreach ($types as $key=>$type) {?>
					<li><a href="#" data-id="<?php echo $key?>"><?php echo $type?></a></li>
					<?php } ?>
				</ul>
			</div>
		</div>
		<div class="col-md-4 visible-md visible-lg visible-xl">
			<div class="btn-group pull-right" id="action">
				<a class="btn btn-default active" data-id="list">
					<i class="dashicons dashicons-list-view"></i> List
				</a>
				<a class="btn btn-default" data-id="map">
					<i class="dashicons dashicons-location"></i> Map
				</a>
			</div>

			<div class="btn-group hidden pull-right" id="map_options">
				<a class="btn btn-default" id="fullscreen">
					<!--<i class="dashicons dashicons-editor-expand"></i> -->Expand
				</a>
				<a class="btn btn-default" id="geolocator">
					<!--<i class="dashicons dashicons-admin-site"></i> -->Find Me
				</a>
			</div>
		</div>
	</div>
	<div class="row results">
		<div class="col-xs-12">
			<div id="alert" class="alert alert-warning<?php if (count($meetings)) {?> hidden<?php }?>">
				No results matched those criteria
			</div>
			
			<div id="map"></div>
			<table class="table table-striped<?php if (!count($meetings)) {?> hidden<?php }?>">
				<thead>
					<th class="time">Time</th>
					<th class="name">Meeting</th>
					<th class="location">Location</th>
					<th class="address">Address</th>
					<th class="region">Region</th>
				</thead>
				<tbody>
					<?php
					foreach ($meetings as $meeting) {
						if (!isset($locations[$meeting['location_id']])) {
							$locations[$meeting['location_id']] = array(
								'name'=>$meeting['location'],
								'coords'=>$meeting['latitude'] . ',' . $meeting['longitude'],
								'url'=>$meeting['location_url'],
								'address'=>$meeting['address'],
								'city_state'=>$meeting['city'] . ', ' . $meeting['state'],
								'meetings'=>array(),
							);
						}
	
						$locations[$meeting['location_id']]['meetings'][] = array(
							'time'=>$meeting['time_formatted'],
							'day'=>$meeting['day'],
							'name'=>$meeting['name'],
							'url'=>$meeting['url'],
							'types'=>$meeting['types'],
						);
						?>
					<tr>
						<td class="time"><?php echo $meeting['time_formatted']?></td>
						<td class="name"><a href="<?php echo $meeting['url']?>"><?php echo meetings_name($meeting['name'], $meeting['types'])?></a></td>
						<td class="location"><?php echo $meeting['location']?></td>
						<td class="address"><?php echo $meeting['address']?></td>
						<td class="region"><?php echo $meeting['region']?></td>
					</tr>
					<?php }?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<script>

jQuery(function(){

	//set some globals
	markers = [];
	infowindow = new google.maps.InfoWindow();
	map = new google.maps.Map(document.getElementById('map'), {
		panControl: false,
		mapTypeControl: false,
		mapTypeControlOptions: {
			mapTypeIds: [google.maps.MapTypeId.ROADMAP, 'map_style']
		}
	});
	bounds = new google.maps.LatLngBounds();				

	<?php foreach ($locations as $location) {?>
		var marker = new google.maps.Marker({
		    position: new google.maps.LatLng(<?php echo $location['coords']?>),
		    map: map,
		    title: "<?php echo $location['name']?>"
		});

		//add infowindow event
		google.maps.event.addListener(marker, 'click', (function(marker) {
			return function() {
				var dl  = '';
				<?php foreach ($location['meetings'] as $meeting) {?>
				dl += "<dt><?php echo $meeting['time']?></dt><dd><a href='<?php echo $meeting['url']?>'><?php echo meetings_name($meeting['name'], $meeting['types'])?></a></dd>";
				<?php }?>
				infowindow.setContent("<div class='infowindow'><h3><a href='<?php echo $location['url']?>'><?php echo $location['name']?></a></h3><address><?php echo $location['address']?><br><?php echo $location['city_state']?></address><h5><?php echo $days[date('w')]?></h5><dl>" + dl + "</dl></div>");
				infowindow.open(map, marker);
			}
		})(marker));					

		//add to map bounds
		bounds.extend(marker.position);

		//save marker so it can be removed later
		markers[markers.length] = marker;
	<?php }?>

	map.fitBounds(bounds);
});

</script>

<?php
get_footer();