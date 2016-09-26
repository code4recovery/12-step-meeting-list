<?php

//get assets for page
tsml_assets();

get_header();

//parse query string
$search	= isset($_GET['sq']) ? sanitize_text_field($_GET['sq']) : null;
$region	= isset($_GET['r']) && term_exists(intval($_GET['r']), 'tsml_region') ? $_GET['r'] : null;
$type	= isset($_GET['t']) && array_key_exists($_GET['t'], $tsml_types[$tsml_program]) ? $_GET['t'] : null;
$time	= isset($_GET['i']) ? sanitize_text_field(strtolower($_GET['i'])) : null;
$view	= (isset($_GET['v']) && $_GET['v'] == 'map') ? 'map' : 'list';

//need later
$times  = array(
	'morning' => __('Morning'),
	'midday' => __('Midday'),
	'evening' => __('Evening'),
	'night' => __('Night'),
);

if (!isset($_GET['d'])) {
	$day = intval(current_time('w')); //if not specified, day is current day
} elseif ($_GET['d'] == 'any') {
	$day = false;
} else {
	$day = intval($_GET['d']);
}

//labels
$day_default = __('Any Day');
$day_label = ($day === false) ? $day_default : $tsml_days[$day];
$time_default = __('Any Time');
$time_label = $time ? $times[$time] : $time_default;
$region_default = $region_label = __('Everywhere');
if ($region) {
	$term = get_term($region, 'tsml_region');
	$region_label = $term->name;
} else {
}
$type_default = __('Any Type');
$type_label = ($type && array_key_exists($type, $tsml_types[$tsml_program])) ? $tsml_types[$tsml_program][$type] : $type_default;

//need this later
$locations	= array();

//run query
$meetings	= tsml_get_meetings(compact('search', 'day', 'time', 'region', 'type'));
//dd($meetings);

class Walker_Regions_Dropdown extends Walker_Category {
	function start_el(&$output, $category, $depth=0, $args=array(), $id=0) {
		//dd($args);
		$classes = array();
		if ($args['value'] == esc_attr($category->term_id)) $classes[] = 'active';
		$classes = count($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
		$output .= '<li' . $classes . '><a href="#" data-id="' . esc_attr($category->term_id) . '">' . esc_attr($category->name) . '</a>';
		if ($args['has_children']) $output .= '<div class="expand"></div>';
	}
	function end_el(&$output, $item, $depth=0, $args=array()) {
		$output .= '</li>';
	}
}

?>
<div id="meetings" data-type="<?php echo $view?>" class="container" role="main">
	<div class="row controls hidden-print">
		<div class="col-md-2 col-sm-6">
			<form id="search" role="search">
				<div class="input-group">
					<input type="text" name="query" class="form-control" value="<?php echo $search?>" placeholder="<?php _e('Search')?>" aria-label="Search">
					<span class="input-group-btn">
						<button class="btn btn-default" type="submit"><i class="glyphicon glyphicon-search"></i></button>
					</span>
				</div>
			</form>
		</div>
		<div class="col-md-2 col-sm-6">
			<div class="dropdown" id="day">
				<a data-toggle="dropdown" class="btn btn-default btn-block" role="button" aria-haspopup="true" aria-expanded="false">
					<span class="selected"><?php echo $day_label?></span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu" role="menu">
					<li<?php if ($day === false) echo ' class="active"'?>><a href="#"><?php echo $day_default?></a></li>
					<li class="divider"></li>
					<?php foreach ($tsml_days as $key=>$value) {?>
					<li<?php if (intval($key) === $day) echo ' class="active"'?>><a href="#" data-id="<?php echo $key?>"><?php echo $value?></a></li>
					<?php }?>
				</ul>
			</div>
		</div>
		<div class="col-md-2 col-sm-6">
			<div class="dropdown" id="time">
				<a data-toggle="dropdown" class="btn btn-default btn-block" role="button" aria-haspopup="true" aria-expanded="false">
					<span class="selected"><?php echo $time_label?></span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu" role="menu">
					<li<?php if (empty($time)) echo ' class="active"'?>><a href="#"><?php echo $time_default?></a></li>
					<li class="divider"></li>
					<?php foreach ($times as $key=>$value) {?>
					<li<?php if ($key === $time) echo ' class="active"'?>><a href="#" data-id="<?php echo $key?>"><?php echo $value?></a></li>
					<?php }?>
				</ul>
			</div>
		</div>
		<div class="col-md-2 col-sm-6">
			<div class="dropdown" id="region">
				<a data-toggle="dropdown" class="btn btn-default btn-block" role="button" aria-haspopup="true" aria-expanded="false">
					<span class="selected"><?php echo $region_label?></span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu" role="menu">
					<li<?php if (empty($region)) echo ' class="active"'?>><a href="#"><?php echo $region_default?></a></li>
					<li class="divider"></li>
					<?php wp_list_categories(array(
						'taxonomy' => 'tsml_region',
						'hierarchical' => true,
						'orderby' => 'name',
						'title_li' => null,
						'hide_empty' => false,
						'walker' => new Walker_Regions_Dropdown,
						'value' => $region,
					)); ?>
				</ul>
			</div>
		</div>
		<div class="col-md-2 col-sm-6">
			<?php if (count($tsml_types_in_use)) {?>
			<div class="dropdown" id="type">
				<a data-toggle="dropdown" class="btn btn-default btn-block" role="button" aria-haspopup="true" aria-expanded="false">
					<span class="selected"><?php echo $type_label?></span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu" role="menu">
					<li<?php if (empty($type)) echo ' class="active"'?>><a href="#"><?php echo $type_default?></a></li>
					<li class="divider"></li>
					<?php 
					$types_to_list = array_intersect_key($tsml_types[$tsml_program], array_flip($tsml_types_in_use));
					foreach ($types_to_list as $key=>$thistype) {?>
					<li<?php if ($key == $type) echo ' class="active"'?>><a href="#" data-id="<?php echo $key?>"><?php echo $thistype?></a></li>
					<?php } ?>
				</ul>
			</div>
			<?php }?>
		</div>
		<div class="col-md-2 col-sm-12 visible-md visible-lg visible-xl">
			<div class="btn-group btn-group-justified" id="action">
				<a class="btn btn-default toggle-view<?php if ($view == 'list') {?> active<?php }?>" data-id="list" role="button">
					<?php _e('List')?>
				</a>
				<div class="btn-group">
					<a class="btn btn-default toggle-view<?php if ($view == 'map') {?> active<?php }?> dropdown-toggle" data-toggle="dropdown" data-id="map" role="button" aria-haspopup="true" aria-expanded="false">
						<?php _e('Map')?>
						<span class="caret"></span>
					</a>
					<ul class="dropdown-menu pull-right" role="menu">
						<li><a href="#fullscreen"><?php _e('Expand')?></a></li>
						<li class="geolocator hidden"><a href="#geolocator"><?php _e('Find Me')?></a></li>
					</ul>
				</div>
			</div>
		</div>
	</div>
	<div class="row results">
		<div class="col-xs-12">
			<div id="alert" class="alert alert-warning<?php if (count($meetings)) {?> hidden<?php }?>">
				<?php _e('No results matched those criteria')?>
			</div>
			
			<div id="map"></div>
			
			<div id="table-wrapper">
				<table class="table table-striped<?php if (!count($meetings)) {?> hidden<?php }?>">
					<thead class="hidden-print">
						<th class="time" data-sort="asc"><?php _e('Time')?></th>
						<th class="name"><?php _e('Meeting')?></th>
						<th class="location"><?php _e('Location')?></th>
						<th class="address"><?php _e('Address')?></th>
						<th class="region"><?php _e('Region')?></th>
						<th class="types"><?php _e('Types')?></th>
					</thead>
					<tbody id="meetings_tbody">
						<?php
						foreach ($meetings as $meeting) {
							$meeting['name'] = htmlentities($meeting['name'], ENT_QUOTES);
							$meeting['location'] = htmlentities($meeting['location'], ENT_QUOTES);
							$meeting['formatted_address'] = htmlentities($meeting['formatted_address'], ENT_QUOTES);
							$meeting['region'] = (!empty($meeting['sub_region'])) ? htmlentities($meeting['sub_region'], ENT_QUOTES) : htmlentities($meeting['region'], ENT_QUOTES);
							$meeting['link'] = tsml_link($meeting['url'], tsml_format_name($meeting['name'], $meeting['types']), 'post_type');
							
							if (!isset($locations[$meeting['location_id']])) {
								$locations[$meeting['location_id']] = array(
									'name' => $meeting['location'],
									'latitude' => $meeting['latitude'] - 0,
									'longitude' => $meeting['longitude'] - 0,
									'url' => $meeting['location_url'], //can't use link here, unfortunately
									'formatted_address' => $meeting['formatted_address'],
									'meetings' => array(),
								);
							}
									
							$locations[$meeting['location_id']]['meetings'][] = array(
								'time'=>$meeting['time_formatted'],
								'day'=>$meeting['day'],
								'name'=>$meeting['name'],
								'url'=>$meeting['url'], //can't use link here, unfortunately
								'types'=>$meeting['types'],
							);
							
							$sort_time = $meeting['day'] . '-' . ($meeting['time'] == '00:00' ? '23:59' : $meeting['time']);
							?>
						<tr>
							<td class="time" data-sort="<?php echo $sort_time?>"><span><?php 
								if (($day === false) && !empty($meeting['time'])) {
									echo tsml_format_day_and_time($meeting['day'], $meeting['time'], '</span><span>');
								} else {
									echo $meeting['time_formatted'];
								}
								?></span></td>
							<td class="name" data-sort="<?php echo sanitize_title($meeting['name']) . '-' . $sort_time?>">
								<?php echo $meeting['link']?>
							</td>
							<td class="location" data-sort="<?php echo sanitize_title($meeting['location']) . '-' . $sort_time?>">
								<?php echo $meeting['location']?>
							</td>
							<td class="address" data-sort="<?php echo sanitize_title($meeting['formatted_address']) . '-' . $sort_time?>"><?php echo tsml_format_address($meeting['formatted_address'], true)?></td>
							<td class="region" data-sort="<?php echo sanitize_title($meeting['region']) . '-' . $sort_time?>"><?php echo $meeting['region']?></td>
							<td class="types" data-sort="<?php echo sanitize_title(tsml_meeting_types($meeting['types'])) . '-' . $sort_time?>"><?php echo tsml_meeting_types($meeting['types'])?></td>
						</tr>
						<?php }?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	var locations = <?php echo json_encode($locations)?>;
	loadMap(locations);
});
</script>

<?php
get_footer();