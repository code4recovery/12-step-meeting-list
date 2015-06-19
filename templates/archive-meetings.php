<?php

//get assets for page
tsml_assets('public');

get_header();

//parse query string
$search		= sanitize_text_field($_GET['sq']);
$region     = intval($_GET['r']);
$types		= array_values(array_intersect(array_keys($tsml_types[$tsml_program]), explode('-', $_GET['t'])));
if (!isset($_GET['d'])) {
	$day = intval(current_time('w')); //if not specified, day is current day
} elseif ($_GET['d'] == 'any') {
	$day = false;
} else {
	$day = intval($_GET['d']);
}

//labels
$day_default = 'Any Day';
$day_label = ($day === false) ? $day_default : $tsml_days[$day];
$region_default = 'Everywhere';
$region_label = $region ? $tsml_regions[$region] : $region_default;
$types_default = 'Meeting Type';
$types_count = count($types);
$types_label = $types_count ? $types_default . ' [' . $types_count . ']': $types_default;
if ($types_count == 1) $types_label = $tsml_types[$tsml_program][$types[0]];

//need this later
$locations	= array();

//run query
$meetings	= tsml_get_meetings(compact('search', 'day', 'region', 'types'));

class Walker_Regions_Dropdown extends Walker_Category {
	function start_el(&$output, $item, $depth=0, $args=array()) {
		//die('args was ' . var_dump($args));
		$output .= '<li' . ($args['value'] == esc_attr($item->term_id) ? ' class="active"' : '') . '><a href="#" data-id="' . esc_attr($item->term_id) . '">' . esc_attr($item->name) . '</a>';
	}
	function end_el(&$output, $item, $depth=0, $args=array()) {
		$output .= '</li>';
	}
}

?>
<div id="meetings" data-type="list" class="container">
	<div class="row controls">
		<div class="col-md-2 col-sm-6">
			<form id="search">
				<div class="input-group">
					<input type="text" name="query" class="form-control" value="<?php echo $search?>" placeholder="Search">
					<span class="input-group-btn">
						<button class="btn btn-default" type="submit"><i class="glyphicon glyphicon-search"></i></button>
					</span>
				</div>
			</form>
		</div>
		<div class="col-md-2 col-sm-6">
			<div class="dropdown" id="day">
				<a data-toggle="dropdown" class="btn btn-default btn-block">
					<span class="selected"><?php echo $day_label?></span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu">
					<li<?php if ($day === false) echo ' class="active"'?>><a href="#"><?php echo $day_default?></a></li>
					<li class="divider"></li>
					<?php foreach ($tsml_days as $key=>$value) {?>
					<li<?php if (intval($key) === $day) echo ' class="active"'?>><a href="#" data-id="<?php echo $key?>"><?php echo $value?></a></li>
					<?php }?>
				</ul>
			</div>
		</div>
		<div class="col-md-2 col-sm-6">
			<div class="dropdown" id="region">
				<a data-toggle="dropdown" class="btn btn-default btn-block">
					<span class="selected"><?php echo $region_label?></span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu">
					<li<?php if (empty($region)) echo ' class="active"'?>><a href="#"><?php echo $region_default?></a></li>
					<li class="divider"></li>
					<?php wp_list_categories(array(
						'taxonomy' => 'region',
						'hierarchical' => true,
						'orderby' => 'name',
						'title_li' => '',
						'hide_empty' => false,
						'walker' => new Walker_Regions_Dropdown,
						'value' => $region,
						'show_option_none' => '',
					)); ?>
				</ul>
			</div>
		</div>
		<div class="col-md-2 col-sm-6">
			<?php if (count($tsml_types_in_use)) {?>
			<div class="dropdown" id="types">
				<a data-toggle="dropdown" class="btn btn-default btn-block">
					<span class="selected"><?php echo $types_label?></span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu">
					<?php 
					$types_to_list = array_intersect_key($tsml_types[$tsml_program], array_flip($tsml_types_in_use));
					foreach ($types_to_list as $key=>$type) {?>
					<li<?php if (in_array($key, $types)) echo ' class="active"'?>><a href="#" data-id="<?php echo $key?>"><?php echo $type?></a></li>
					<?php } ?>
				</ul>
			</div>
			<?php }?>
		</div>
		<div class="col-md-2 col-md-push-2 col-sm-12 visible-md visible-lg visible-xl">
			<div class="btn-group btn-group-justified" id="action">
				<a class="btn btn-default toggle-view active" data-id="list">
					List
				</a>
				<div class="btn-group">
					<a class="btn btn-default toggle-view dropdown-toggle" data-toggle="dropdown" data-id="map">
						Map
						<span class="caret"></span>
					</a>
					<ul class="dropdown-menu pull-right" role="menu">
						<li><a href="#fullscreen">Expand</a></li>
						<li><a href="#geolocator">Find Me</a></li>
					</ul>
				</div>
			</div>
		</div>
	</div>
	<div class="row results">
		<div class="col-xs-12">
			<div id="alert" class="alert alert-warning<?php if (count($meetings)) {?> hidden<?php }?>">
				No results matched those criteria
			</div>
			
			<div id="map"></div>
			
			<div id="table-wrapper">
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

							//apply search highlighter if needed
							if ($search) {
								$meeting['name'] = highlight($meeting['name'], $search);
								$meeting['location'] = highlight($meeting['location'], $search);
							}
							?>
						<tr>
							<td class="time"><?php echo $meeting['time_formatted']?></td>
							<td class="name"><?php echo tsml_link($meeting['url'], tsml_format_name($meeting['name'], $meeting['types']))?></td>
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
				dl += "<dt><?php echo $meeting['time']?></dt><dd><a href='<?php echo $meeting['url']?>'><?php echo tsml_format_name($meeting['name'], $meeting['types'])?></a></dd>";
				<?php }?>
				infowindow.setContent("<div class='infowindow'><h3><a href='<?php echo $location['url']?>'><?php echo $location['name']?></a></h3><address><?php echo $location['address']?><br><?php echo $location['city_state']?></address><h5><?php echo $tsml_days[$today]?></h5><dl>" + dl + "</dl></div>");
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