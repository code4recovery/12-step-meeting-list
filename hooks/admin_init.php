<?php
//set up page
wp_enqueue_script('google_maps_api', 'http://maps.google.com/maps/api/js?sensor=false');
wp_enqueue_style('meetings_meta_style', plugin_dir_url(__FILE__) . '../css/admin.css');
wp_enqueue_script('meetings_admin_js', plugin_dir_url(__FILE__) . '../js/admin.js', array('jquery'), '', true);
wp_enqueue_script('typeahead_js', plugin_dir_url(__FILE__) . '../js/typeahead.bundle.js', array('jquery'), '', true);
wp_localize_script('meetings_admin_js', 'myAjax', array('ajaxurl'=>admin_url('admin-ajax.php')));        

remove_meta_box('tagsdiv-region', 'meetings', 'side' );
remove_meta_box('tagsdiv-types', 'meetings', 'side' );
remove_meta_box('revisionsdiv', 'meetings', 'normal' );

//add meta boxes
add_meta_box('info', 'General Info', function(){
	global $post, $days, $types, $custom;

	//get post metadata
	$custom 	= get_post_custom($post->ID);
	$custom['types'] = unserialize($custom['types'][0]);
	if (!is_array($custom['types'])) $custom['types'] = array();
	?>
	<div class="meta_form_row">
		<label for="day">Day</label>
		<select name="day" id="day">
			<?php foreach ($days as $key=>$day) {?>
			<option value="<?php echo $key?>"<?php selected($custom['day'][0], $key)?>><?php echo $day?></option>
			<?php }?>
		</select>
	</div>
	<div class="meta_form_row">
		<label for="time">Time</label>
		<input type="time" name="time" id="time" value="<?php echo $custom['time'][0]?>" step="900">
	</div>
	<div class="meta_form_row">
		<label for="tags">Types</label>
		<div class="checkboxes">
			<?php foreach ($types as $key=>$type) {?>
				<label>
					<input type="checkbox" name="types[]" value="<?php echo $key?>" <?php if (in_array($key, $custom['types'])) {?> checked="checked"<?php }?>>
					<?php echo $type?>
				</label>
			<?php }?>
		</div>
	</div>
	<div class="meta_form_row">
		<label for="notes">Notes</label>
		<textarea name="notes" id="notes" placeholder="eg. Birthday speaker meeting last Saturday of the month"><?php echo $custom['notes'][0]?></textarea>
	</div>
	<?php
}, 'meetings', 'normal', 'low');

add_meta_box('location', 'Location', function(){
	global $regions, $custom;
	?>
	<div class="meta_form_row typeahead">
		<label for="location">Location</label>
		<input type="text" name="location" id="location" value="<?php echo $custom['location'][0]?>">
		<input type="hidden" name="location_id" id="location_id" value="<?php echo $custom['location_id'][0]?>">
	</div>
	<div class="meta_form_row">
		<label for="address">Address</label>
		<input type="text" name="address" id="address" value="<?php echo $custom['address'][0]?>">
		<input type="hidden" name="latitude" id="latitude" value="<?php echo $custom['latitude'][0]?>">
		<input type="hidden" name="longitude" id="longitude" value="<?php echo $custom['longitude'][0]?>">
	</div>
	<div class="meta_form_row">
		<label for="region">Region</label>
		<select name="region" id="region">
			<?php foreach ($regions as $key=>$region) {?>
				<option value="<?php echo $key?>" <?php selected($custom['region'][0], $key)?>><?php echo $region?></option>
			<?php }?>
		</select>
	</div>
	<div class="meta_form_row">
		<label for="map">Map</label>
		<div id="map"></div>
	</div>
	<?php
}, 'meetings', 'normal', 'low');
