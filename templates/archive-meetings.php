<?php

//get assets for page
tsml_assets();

get_header();

//define search dropdown options
$modes = array(
	'search' => array('title' => __('Search', '12-step-meeting-list'), 'icon' => 'glyphicon glyphicon-search'),
	'location' => array('title' => __('Near Location', '12-step-meeting-list'), 'icon' => 'glyphicon glyphicon-map-marker'),
);
//proximity only enabled over SSL
if (is_ssl()) $modes['me'] = array('title' => __('Near Me', '12-step-meeting-list'), 'icon' => 'glyphicon glyphicon-user');

//define distance dropdown
$distances = array();
foreach (array(1, 2, 5, 10, 25, 50) as $distance) {
	if ($tsml_distance_units == 'mi') {
		$distances[$distance] = sprintf(_n('Within %d Mile', 'Within %d Miles', $distance, '12-step-meeting-list'), $distance);
	} else {
		$distances[$distance] = sprintf(_n('Within %d Kilometer', 'Within %d Kilometers', $distance, '12-step-meeting-list'), $distance);
	}
}

//define times dropdown
$times  = array(
	'morning' => __('Morning', '12-step-meeting-list'),
	'midday' => __('Midday', '12-step-meeting-list'),
	'evening' => __('Evening', '12-step-meeting-list'),
	'night' => __('Night', '12-step-meeting-list'),
);

//legacy query string stuff, we don't want to break everyone's links (just yet)
if (isset($_GET['d'])) $_GET['tsml-day'] = $_GET['d'];
if (isset($_GET['r'])) $_GET['tsml-region'] = $_GET['r'];
if (isset($_GET['t'])) $_GET['tsml-type'] = $_GET['t'];
if (isset($_GET['i'])) $_GET['tsml-time'] = $_GET['i'];
if (isset($_GET['v'])) $_GET['tsml-view'] = $_GET['v'];
if (isset($_GET['sq'])) $_GET['tsml-query'] = $_GET['sq'];

extract($tsml_defaults);

//parse query string
if (isset($_GET['tsml-query'])) $query = sanitize_text_field($_GET['tsml-query']);
if (isset($_GET['tsml-region']) && term_exists(intval($_GET['tsml-region']), 'tsml_region')) $region = $_GET['tsml-region'];
if (isset($_GET['tsml-type']) && array_key_exists($_GET['tsml-type'], $tsml_types[$tsml_program])) $type = $_GET['tsml-type'];
if (isset($_GET['tsml-time']) && array_key_exists($_GET['tsml-time'], $times)) $time = $_GET['tsml-time'];
if (isset($_GET['tsml-view']) && in_array($_GET['tsml-view'], array('list', 'map'))) $view = $_GET['tsml-view'];
if (isset($_GET['tsml-distance']) && intval($_GET['tsml-distance'])) $distance = $_GET['tsml-distance'];
if (isset($_GET['tsml-mode']) && array_key_exists($_GET['tsml-mode'], $modes)) $mode = $_GET['tsml-mode'];

//day default
if (isset($_GET['tsml-day'])) {
	$day = ($_GET['tsml-day'] == 'any') ? null : intval($_GET['tsml-day']);
}

//time can only be upcoming if it's today
if (($time == 'upcoming') && ($day != intval(current_time('w')))) $time = null;

//labels
$day_default = __('Any Day', '12-step-meeting-list');
$day_label = ($day === null) ? $day_default : $tsml_days[$day];
$time_default = __('Any Time', '12-step-meeting-list');
if ($time == 'upcoming') {
	$time_label = __('Upcoming', '12-step-meeting-list');
} else {
	$time_label = $time ? $times[$time] : $time_default;
}
$region_default = $region_label = __('Everywhere', '12-step-meeting-list');
if ($region) {
	$term = get_term($region, 'tsml_region');
	$region_label = $term->name;
}
$type_default = __('Any Type', '12-step-meeting-list');
$type_label = ($type && array_key_exists($type, $tsml_types[$tsml_program])) ? $tsml_types[$tsml_program][$type] : $type_default;
$mode_label = array_key_exists($mode, $modes) ? $modes[$mode]['title'] : $modes[0]['title'];
$distance_label = $distances[$distance];

//need these later
$meetings = $locations = array();
$message = '';

//run query
if ($mode == 'search') {
	$meetings	= tsml_get_meetings(compact('mode', 'day', 'time', 'region', 'type'));	
	if (!count($meetings)) $message = $tsml_strings['no_meetings'];
} elseif ($mode == 'location') {
	$message = empty($_GET['query']) ? $tsml_strings['loc_empty'] : $tsml_strings['loc_thinking'];
} elseif ($mode == 'me') {
	$message = $tsml_strings['geo_thinking'];
}

class Walker_Regions_Dropdown extends Walker_Category {
	function start_el(&$output, $category, $depth=0, $args=array(), $id=0) {
		$classes = array();
		if ($args['value'] == esc_attr($category->term_id)) $classes[] = 'active';
		$classes = count($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
		$output .= '<li' . $classes . '><a data-id="' . esc_attr($category->term_id) . '">' . esc_attr($category->name) . '</a>';
		if ($args['has_children']) $output .= '<div class="expand"></div>';
	}
	function end_el(&$output, $item, $depth=0, $args=array()) {
		$output .= '</li>';
	}
}

?>
<div id="meetings" data-view="<?php echo $view?>" data-mode="<?php echo $mode?>" class="container<?php if (!count($meetings)) {?> empty<?php }?>" role="main">
	<div class="row controls hidden-print">
		<div class="col-sm-6 col-md-2">
			<form id="search" role="search">
				<div class="input-group">
					<input type="text" name="query" class="form-control" value="<?php echo $query?>" placeholder="<?php echo $mode_label?>" aria-label="Search" <?php echo ($mode == 'me') ? 'disabled' : 'autofocus'?>>
					<div class="input-group-btn" id="mode">
						<button class="btn btn-default dropdown-toggle" data-toggle="dropdown" type="button">
							<i class="<?php echo $modes[$mode]['icon']?>"></i>
							<span class="caret"></span>
						</button>
						<ul class="dropdown-menu dropdown-menu-right">
							<?php foreach ($modes as $key => $value) {?>
							<li class="<?php echo $key; if ($mode == $key) echo ' active';?>"><a data-id="<?php echo $key?>"><?php echo $value['title']?></a></li>
							<?php }?>
						</ul>
					</div>
				</div>
				<input type="submit" class="hidden">
			</form>
		</div>
		<div class="col-sm-6 col-md-2 col-md-push-8">
			<div class="btn-group btn-group-justified" id="action">
				<a class="btn btn-default toggle-view<?php if ($view == 'list') {?> active<?php }?>" data-id="list" role="button">
					<?php _e('List', '12-step-meeting-list')?>
				</a>
				<div class="btn-group">
					<a class="btn btn-default toggle-view<?php if ($view == 'map') {?> active<?php }?> dropdown-toggle" data-toggle="dropdown" data-id="map" role="button" aria-haspopup="true" aria-expanded="false">
						<?php _e('Map', '12-step-meeting-list')?>
					</a>
				</div>
			</div>
		</div>
		<div class="col-sm-6 col-md-2 col-md-pull-2">
			<div class="dropdown" id="region">
				<a data-toggle="dropdown" class="btn btn-default btn-block" role="button" aria-haspopup="true" aria-expanded="false">
					<span class="selected"><?php echo $region_label?></span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu" role="menu">
					<li<?php if (empty($region)) echo ' class="active"'?>><a><?php echo $region_default?></a></li>
					<li class="divider"></li>
					<?php wp_list_categories(array(
						'taxonomy' => 'tsml_region',
						'hierarchical' => true,
						'orderby' => 'name',
						'title_li' => null,
						'hide_empty' => false,
						'walker' => new Walker_Regions_Dropdown,
						'value' => $region,
					))?>
				</ul>
			</div>
			<div class="dropdown" id="distance">
				<a data-toggle="dropdown" class="btn btn-default btn-block" role="button" aria-haspopup="true" aria-expanded="false">
					<span class="selected"><?php echo $distance_label?></span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu" role="menu">
					<?php
					foreach ($distances as $key => $value) {
						echo '<li' . ($key == $distance ? ' class="active"' : '') . '><a data-id="' . $key . '">' . $value . '</a></li>';
					}?>
				</ul>
			</div>
		</div>
		<div class="col-sm-6 col-md-2 col-md-pull-2">
			<div class="dropdown" id="day">
				<a data-toggle="dropdown" class="btn btn-default btn-block" role="button" aria-haspopup="true" aria-expanded="false">
					<span class="selected"><?php echo $day_label?></span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu" role="menu">
					<li<?php if ($day === null) echo ' class="active"'?>><a><?php echo $day_default?></a></li>
					<li class="divider"></li>
					<?php foreach ($tsml_days as $key=>$value) {?>
					<li<?php if (intval($key) === $day) echo ' class="active"'?>><a data-id="<?php echo $key?>"><?php echo $value?></a></li>
					<?php }?>
				</ul>
			</div>
		</div>
		<div class="col-sm-6 col-md-2 col-md-pull-2">
			<div class="dropdown" id="time">
				<a data-toggle="dropdown" class="btn btn-default btn-block" role="button" aria-haspopup="true" aria-expanded="false">
					<span class="selected"><?php echo $time_label?></span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu" role="menu">
					<li<?php if (empty($time)) echo ' class="active"'?>><a><?php echo $time_default?></a></li>
					<li class="divider upcoming"></li>
					<li class="upcoming<?php if ($time == 'upcoming') echo ' active"'?>"><a data-id="upcoming"><?php echo __('Upcoming', '12-step-meeting-list')?></a></li>
					<li class="divider"></li>
					<?php foreach ($times as $key=>$value) {?>
					<li<?php if ($key === $time) echo ' class="active"'?>><a data-id="<?php echo $key?>"><?php echo $value?></a></li>
					<?php }?>
				</ul>
			</div>
		</div>
		<div class="col-sm-6 col-md-2 col-md-pull-2">
			<?php if (count($tsml_types_in_use)) {?>
			<div class="dropdown" id="type">
				<a data-toggle="dropdown" class="btn btn-default btn-block" role="button" aria-haspopup="true" aria-expanded="false">
					<span class="selected"><?php echo $type_label?></span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu" role="menu">
					<li<?php if (empty($type)) echo ' class="active"'?>><a><?php echo $type_default?></a></li>
					<li class="divider"></li>
					<?php 
					$types_to_list = array_intersect_key($tsml_types[$tsml_program], array_flip($tsml_types_in_use));
					foreach ($types_to_list as $key=>$thistype) {?>
					<li<?php if ($key == $type) echo ' class="active"'?>><a data-id="<?php echo $key?>"><?php echo $thistype?></a></li>
					<?php } ?>
				</ul>
			</div>
			<?php }?>
		</div>
	</div>
	<div class="row results">
		<div class="col-xs-12">
			<div id="alert" class="alert alert-warning<?php if (empty($message)) {?> hidden<?php }?>">
				<?php echo $message?>
			</div>
			
			<div id="map"></div>
			
			<div id="table-wrapper">
				<table class="table table-striped">
					<thead class="hidden-print">
						<th class="time" data-sort="asc"><?php _e('Time', '12-step-meeting-list')?></th>
						<th class="distance"><?php _e('Distance', '12-step-meeting-list')?></th>
						<th class="name"><?php _e('Meeting', '12-step-meeting-list')?></th>
						<th class="location"><?php _e('Location', '12-step-meeting-list')?></th>
						<th class="address"><?php _e('Address', '12-step-meeting-list')?></th>
						<th class="region"><?php _e('Region', '12-step-meeting-list')?></th>
						<th class="types"><?php _e('Types', '12-step-meeting-list')?></th>
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
							<td class="time" data-sort="<?php echo $sort_time . '-' . sanitize_title($meeting['location'])?>"><span><?php 
								if (($day === null) && !empty($meeting['time'])) {
									echo tsml_format_day_and_time($meeting['day'], $meeting['time'], '</span><span>');
								} else {
									echo $meeting['time_formatted'];
								}
								?></span></td>
							<td class="distance" data-sort="<?php echo $meeting['distance']?>"><?php echo $meeting['distance']?></td>
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
	var locations = <?php echo json_encode($locations)?>;
</script>

<?php
get_footer();