<?php

//get assets for page
tsml_assets();

//define search dropdown options
$modes = array(
	'search' => array('title' => __('Search', '12-step-meeting-list'), 'icon' => 'glyphicon glyphicon-search'),
	'location' => array('title' => __('Near Location', '12-step-meeting-list'), 'icon' => 'glyphicon glyphicon-map-marker'),
);
//proximity only enabled over SSL
if (is_ssl()) $modes['me'] = array('title' => __('Near Me', '12-step-meeting-list'), 'icon' => 'glyphicon glyphicon-user');

//define distance dropdown
$distances = array();
foreach (array(1, 2, 5, 10, 25, 50, 100) as $distance) {
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

$region = $district = null;

//parse query string
if (isset($_GET['tsml-query'])) $query = sanitize_text_field($_GET['tsml-query']);
if (isset($_GET['tsml-region']) && term_exists(intval($_GET['tsml-region']), 'tsml_region')) {
	$region = $_GET['tsml-region'];
} elseif (isset($_GET['tsml-district']) && term_exists(intval($_GET['tsml-district']), 'tsml_district')) {
	$district = $_GET['tsml-district'];
}
$types = array();
if (!empty($_GET['tsml-type'])) {
	$type_queries = explode(',', $_GET['tsml-type']);
	foreach ($type_queries as $type_query) {
		if (array_key_exists($type_query, $tsml_programs[$tsml_program]['types'])) {
			$types[] = $type_query;
		}
	}
}
if (isset($_GET['tsml-time']) && (($_GET['tsml-time'] == 'upcoming') || array_key_exists($_GET['tsml-time'], $times))) $time = $_GET['tsml-time'];
if (isset($_GET['tsml-distance']) && intval($_GET['tsml-distance'])) $distance = $_GET['tsml-distance'];
if (isset($_GET['tsml-mode']) && array_key_exists($_GET['tsml-mode'], $modes)) $mode = $_GET['tsml-mode'];

if ($tsml_mapbox_key || $tsml_google_maps_key) {
	$maps_enabled = true;
	if (isset($_GET['tsml-view']) && in_array($_GET['tsml-view'], array('list', 'map'))) $view = $_GET['tsml-view'];
} else {
	$maps_enabled = false;
	$view = 'list';
}

//day default
$today = true;
if (isset($_GET['tsml-day'])) {
	$today = false;
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
} elseif ($district) {
	$term = get_term($district, 'tsml_district');
	$region_label = $term->name;
}
$type_default = __('Any Type', '12-step-meeting-list');
if (!count($types)) {
	$type_label	= $type_default;
} else {
	$type_label = implode(' + ', array_map(function($type) use ($tsml_programs, $tsml_program) {
		return $tsml_programs[$tsml_program]['types'][$type];
	}, $types));
}
$mode_label = array_key_exists($mode, $modes) ? $modes[$mode]['title'] : $modes[0]['title'];
$distance_label = $distances[$distance];

//create page title (todo redo with sprintf)
$tsml_page_title = array();
if ($day !== null) {
	$tsml_page_title[] = $today ? __('Today\'s', '12-step-meeting-list') : $tsml_days[$day];
}
if ($time) $tsml_page_title[] = $time_label;
if (count($types)) $tsml_page_title[] = $type_label;
$tsml_page_title[] = empty($tsml_programs[$tsml_program]['abbr']) ? $tsml_programs[$tsml_program]['name'] : $tsml_programs[$tsml_program]['abbr'];
$tsml_page_title[] = __('Meetings', '12-step-meeting-list');
if ($region) $tsml_page_title[] = __('in', '12-step-meeting-list') . ' ' . $region_label;
$tsml_page_title = implode(' ', $tsml_page_title);

//set page title for SEO (only applies to this page)
function tsml_set_title($title, $separator=null) {
	global $tsml_page_title;
	if (empty($separator)) return $tsml_page_title;
	$title_parts = array_map('trim', explode($separator, $title));
	for ($i = 0; $i < count($title_parts); $i++) {
		if (strcmp($title_parts[$i], __('Meetings', '12-step-meeting-list')) == 0) {
			$title_parts[$i] = $tsml_page_title;
		}
	}
	return implode(' ' . $separator . ' ', $title_parts);
};
add_filter('wp_title', 'tsml_set_title', 10, 2);

//need these later
$meetings = $locations = array();
$message = '';

//run query
if ($mode == 'search') {
	$type = implode(',', $types);
	$meetings	= tsml_get_meetings(compact('mode', 'day', 'time', 'region', 'district', 'type', 'query'));	
	if (!count($meetings)) $message = $tsml_strings['no_meetings'];
} elseif ($mode == 'location') {
	$message = empty($_GET['query']) ? $tsml_strings['loc_empty'] : $tsml_strings['loc_thinking'];
} elseif ($mode == 'me') {
	$message = $tsml_strings['geo_thinking'];
}

class Walker_Regions_Dropdown extends Walker_Category {
	function start_el(&$output, $category, $depth=0, $args=array(), $id=0) {
		$classes = array('region');
		if ($args['value'] == esc_attr($category->term_id)) $classes[] = 'active';
		$classes = count($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
		$output .= '<li' . $classes . '><a href="' . tmsl_meetings_url(array('tsml-region'=>$category->term_id)) . '" data-id="' . $category->term_id . '">' . $category->name . '</a>';
		if ($args['has_children']) $output .= '<div class="expand"></div>';
	}
	function end_el(&$output, $item, $depth=0, $args=array()) {
		$output .= '</li>';
	}
}

$regions_dropdown = wp_list_categories(array(
	'taxonomy' => 'tsml_region',
	'hierarchical' => true,
	'orderby' => 'name',
	'title_li' => null,
	'hide_empty' => false,
	'walker' => new Walker_Regions_Dropdown,
	'value' => $region,
	'show_option_none' => null,
	'echo' => false,
	//'show_count' => true,
));

class Walker_Districts_Dropdown extends Walker_Category {
	function start_el(&$output, $category, $depth=0, $args=array(), $id=0) {
		$classes = array('district');
		if ($args['value'] == esc_attr($category->term_id)) $classes[] = 'active';
		$classes = count($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
		$output .= '<li' . $classes . '><a href="' . tmsl_meetings_url(array('tsml-district'=>$category->term_id)) . '" data-id="' . $category->term_id . '">' . $category->name . '</a>';
		if ($args['has_children']) $output .= '<div class="expand"></div>';
	}
	function end_el(&$output, $item, $depth=0, $args=array()) {
		$output .= '</li>';
	}
}

$districts_dropdown = wp_list_categories(array(
	'taxonomy' => 'tsml_district',
	'hierarchical' => true,
	'orderby' => 'name',
	'title_li' => null,
	'hide_empty' => false,
	'walker' => new Walker_Districts_Dropdown,
	'value' => $district,
	'show_option_none' => null,
	'echo' => false,
	//'show_count' => true,
));

//adding custom body classes
add_filter('body_class', 'tsml_body_class');
function tsml_body_class($classes) {
	$classes[] = 'tsml tsml-meetings';
	return $classes;
}

//do this after everything is loaded
get_header();

?>
<div id="tsml">
	
	<div id="meetings" data-view="<?php echo $view?>" data-mode="<?php echo $mode?>" tax-mode="<?php echo $district ? 'district' : 'region'?>" class="container<?php if (!count($meetings)) {?> empty<?php }?>" role="main">

		<div class="row title">
			<div class="col-xs-12">
				<div class="page-header">
					<h1><?php echo $tsml_page_title?></h1>
				</div>
			</div>
		</div>

		<?php if (is_active_sidebar('tsml_meetings_top')) {?>
			<div class="widgets meetings-widgets meetings-widgets-top" role="complementary">
				<?php dynamic_sidebar('tsml_meetings_top')?>
			</div>
		<?php }?>
	
		<div class="row controls hidden-print">
			<div class="col-sm-6 col-md-2 control-search">
				<form id="search" role="search" action=".">
					<div class="input-group">
						<input type="search" name="query" class="form-control" value="<?php echo $query?>" placeholder="<?php echo $mode_label?>" aria-label="Search" <?php echo ($mode == 'me') ? 'disabled' : ''?>>
						<div class="input-group-btn" id="mode">
							<button class="btn btn-default" data-toggle="tsml-dropdown" type="button">
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
					<input type="submit">
				</form>
			</div>
			<div class="col-sm-6 col-md-2 col-md-push-8 control-view">
				<?php if ($maps_enabled) {?>
				<div class="btn-group btn-group-justified" id="action">
					<a class="btn btn-default toggle-view<?php if ($view == 'list') {?> active<?php }?>" href="<?php echo tmsl_meetings_url(array('tsml-view'=>'list'))?>" data-id="list" role="button">
						<?php _e('List', '12-step-meeting-list')?>
					</a>
					<a class="btn btn-default toggle-view<?php if ($view == 'map') {?> active<?php }?>" href="<?php echo tmsl_meetings_url(array('tsml-view'=>'map'))?>" data-id="map" role="button">
						<?php _e('Map', '12-step-meeting-list')?>
					</a>
				</div>
				<?php } ?>
			</div>
			<div class="col-sm-6 col-md-2 col-md-pull-2 control-region">
				<?php if ($regions_dropdown || $districts_dropdown) {?>
				<div class="dropdown" id="region">
					<a class="btn btn-default btn-block" data-toggle="tsml-dropdown" role="button" aria-haspopup="true" aria-expanded="false">
						<span class="selected"><?php echo $region_label?></span>
						<span class="caret"></span>
					</a>
					<ul class="dropdown-menu" role="menu">
						<li<?php if (empty($region) && empty($district)) echo ' class="active"'?>><a><?php echo $region_default?></a></li>
						<li class="divider"></li>
						<?php if ($regions_dropdown && $districts_dropdown) {?>
						<li class="region"><a class="switch"><?php _e('Switch to Districts', '12-step-meeting-list')?></a></li>
						<li class="district"><a class="switch"><?php _e('Switch to Regions', '12-step-meeting-list')?></a></li>
						<li class="divider"></li>
						<?php }?>
						<?php echo $regions_dropdown?>
						<?php echo $districts_dropdown?>
					</ul>
				</div>
				<?php }?>
				<div class="dropdown" id="distance">
					<a class="btn btn-default btn-block" data-toggle="tsml-dropdown" role="button" aria-haspopup="true" aria-expanded="false">
						<span class="selected"><?php echo $distance_label?></span>
						<span class="caret"></span>
					</a>
					<ul class="dropdown-menu" role="menu">
						<?php
						foreach ($distances as $key => $value) {
							echo '<li' . ($key == $distance ? ' class="active"' : '') . '><a href="' . tmsl_meetings_url(array('tsml-distance'=>$key)) . '" data-id="' . $key . '">' . $value . '</a></li>';
						}?>
					</ul>
				</div>
			</div>
			<div class="col-sm-6 col-md-2 col-md-pull-2 control-day">
				<div class="dropdown" id="day">
					<a class="btn btn-default btn-block" data-toggle="tsml-dropdown" role="button" aria-haspopup="true" aria-expanded="false">
						<span class="selected"><?php echo $day_label?></span>
						<span class="caret"></span>
					</a>
					<ul class="dropdown-menu" role="menu">
						<li<?php if ($day === null) echo ' class="active"'?>><a><?php echo $day_default?></a></li>
						<li class="divider"></li>
						<?php foreach ($tsml_days as $key=>$value) {?>
						<li<?php if (intval($key) === $day) echo ' class="active"'?>><a href="<?php echo tmsl_meetings_url(array('tsml-day'=>$key))?>" data-id="<?php echo $key?>"><?php echo $value?></a></li>
						<?php }?>
					</ul>
				</div>
			</div>
			<div class="col-sm-6 col-md-2 col-md-pull-2 control-time">
				<div class="dropdown" id="time">
					<a class="btn btn-default btn-block" data-toggle="tsml-dropdown" role="button" aria-haspopup="true" aria-expanded="false">
						<span class="selected"><?php echo $time_label?></span>
						<span class="caret"></span>
					</a>
					<ul class="dropdown-menu" role="menu">
						<li<?php if (empty($time)) echo ' class="active"'?>><a><?php echo $time_default?></a></li>
						<li class="divider upcoming"></li>
						<li class="upcoming<?php if ($time == 'upcoming') echo ' active"'?>"><a href="<?php echo tmsl_meetings_url(array('tsml-time'=>'upcoming'))?>" data-id="upcoming"><?php esc_html_e('Upcoming', '12-step-meeting-list')?></a></li>
						<li class="divider"></li>
						<?php foreach ($times as $key=>$value) {?>
						<li<?php if ($key === $time) echo ' class="active"'?>><a href="<?php echo tmsl_meetings_url(array('tsml-time'=>$key))?>" data-id="<?php echo $key?>"><?php echo $value?></a></li>
						<?php }?>
					</ul>
				</div>
			</div>
			<div class="col-sm-6 col-md-2 col-md-pull-2 control-type">
				<?php if (count($tsml_types_in_use) && !empty($tsml_programs[$tsml_program]['types'])) {?>
				<div class="dropdown" id="type">
					<a class="btn btn-default btn-block" data-toggle="tsml-dropdown" role="button" aria-haspopup="true" aria-expanded="false">
						<span class="selected"><?php echo $type_label?></span>
						<span class="caret"></span>
					</a>
					<ul class="dropdown-menu" role="menu">
						<li<?php if (!count($types)) echo ' class="active"'?>><a><?php echo $type_default?></a></li>
						<li class="divider"></li>
						<?php 
						$types_to_list = array_intersect_key($tsml_programs[$tsml_program]['types'], array_flip($tsml_types_in_use));
						foreach ($types_to_list as $key=>$thistype) {?>
						<li<?php if (in_array($key, $types)) echo ' class="active"'?>><a href="<?php echo tmsl_meetings_url(array('tsml-type'=>$key))?>" data-id="<?php echo $key?>"><?php echo $thistype?></a></li>
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
							<tr>
								<?php foreach ($tsml_columns as $key => $column) {
									echo '<th class="' . $key . '"' . ($tsml_sort_by == $key ? ' data-sort="asc"' : '') . '>' . __($column, '12-step-meeting-list') . '</th>';
								}?>
							</tr>
						</thead>
						<tbody id="meetings_tbody">
							<?php
							foreach ($meetings as $meeting) {
								$meeting['name'] = htmlentities($meeting['name'], ENT_QUOTES);
								$meeting['location'] = htmlentities($meeting['location'], ENT_QUOTES);
								$meeting['formatted_address'] = htmlentities($meeting['formatted_address'], ENT_QUOTES);
								$meeting['region'] = (!empty($meeting['sub_region'])) ? htmlentities($meeting['sub_region'], ENT_QUOTES) : htmlentities($meeting['region'], ENT_QUOTES);
								if (!empty($meeting['district'])) {
									$meeting['district'] = (!empty($meeting['sub_district'])) ? htmlentities($meeting['sub_district'], ENT_QUOTES) : htmlentities($meeting['district'], ENT_QUOTES);
								} else {
									$meeting['district'] = '';
								}
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
									'time' => $meeting['time_formatted'],
									'day' => $meeting['day'],
									'name' => $meeting['name'],
									'url' => $meeting['url'], //can't use link here, unfortunately
									'types' => $meeting['types'],
								);
								
								$sort_time = $meeting['day'] . '-' . ($meeting['time'] == '00:00' ? '23:59' : $meeting['time']);
								?>
							<tr <?php if (!empty($meeting['notes'])) {?> class="notes"<?php }?>>
								<?php foreach ($tsml_columns as $key => $column) {
									switch ($key) {
										case 'time':?>
									<td class="time" data-sort="<?php echo $sort_time . '-' . sanitize_title($meeting['location'])?>"><span><?php 
										if (($day === null) && !empty($meeting['time'])) {
											echo tsml_format_day_and_time($meeting['day'], $meeting['time'], '</span><span>');
										} else {
											echo $meeting['time_formatted'];
										}
									?></span></td>
									<?php
										break;

										case 'distance':?>
									<td class="distance" data-sort="<?php echo $meeting['distance']?>"><?php echo $meeting['distance']?></td>
									<?php
										break;

										case 'name':?>
									<td class="name" data-sort="<?php echo sanitize_title($meeting['name']) . '-' . $sort_time?>">
										<?php echo $meeting['link']?>
									</td>
									<?php
										break;

										case 'location':?>
									<td class="location" data-sort="<?php echo sanitize_title($meeting['location']) . '-' . $sort_time?>">
										<?php echo $meeting['location']?>
									</td>
									<?php
										break;

										case 'address':?>
									<td class="address" data-sort="<?php echo sanitize_title($meeting['formatted_address']) . '-' . $sort_time?>"><?php echo tsml_format_address($meeting['formatted_address'], $tsml_street_only)?></td>
									<?php
										break;

										case 'region':?>
									<td class="region" data-sort="<?php echo sanitize_title($meeting['region']) . '-' . $sort_time?>"><?php echo $meeting['region']?></td>
									<?php
										break;

										case 'district':?>
									<td class="district" data-sort="<?php echo sanitize_title($meeting['district']) . '-' . $sort_time?>"><?php echo $meeting['district']?></td>
									<?php
										break;

										case 'types':?>
									<td class="types" data-sort="<?php echo sanitize_title(tsml_meeting_types($meeting['types'])) . '-' . $sort_time?>"><?php echo tsml_meeting_types($meeting['types'])?></td>
									<?php
										break;
									}
								}
								?>
							</tr>
							<?php }?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<?php if (is_active_sidebar('tsml_meetings_bottom')) {?>
			<div class="widgets meetings-widgets meetings-widgets-bottom" role="complementary">
				<?php dynamic_sidebar('tsml_meetings_bottom')?>
			</div>
		<?php }?>

	</div>

</div>

<script>
	var locations = <?php echo json_encode($locations)?>;
</script>

<?php
get_footer();
