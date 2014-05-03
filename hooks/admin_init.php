<?php
//set up page
wp_enqueue_style('meetings_meta_style', plugin_dir_url(__FILE__) . '../css/admin.css');
wp_enqueue_script('meetings_admin_js', plugin_dir_url(__FILE__) . '../js/admin.js', array('jquery'), '', true);
wp_localize_script('meetings_admin_js', 'myAjax', array('ajaxurl'=>admin_url('admin-ajax.php')));        

remove_meta_box('tagsdiv-region', 'meetings', 'side' );
remove_meta_box('tagsdiv-types', 'meetings', 'side' );
remove_meta_box('revisionsdiv', 'meetings', 'normal' );

//add meta boxes
add_meta_box('info', 'General Info', function(){
	global $post, $days, $types, $custom;

	//get post metadata
	//if (!$checked = get_the_terms($post->ID, 'types')) $checked = array();
	//foreach ($checked as &$check) $check = $check->term_id;
	$checked = array();
	$custom 	= get_post_custom($post->ID);

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
		<input type="time" name="time" id="time" value="<?php echo $custom['time'][0]?>">
	</div>
	<div class="meta_form_row">
		<label for="tags">Types</label>
		<div class="checkboxes">
			<?php foreach ($types as $key=>$type) {?>
				<label>
					<input type="checkbox" name="types[]" value="<?php echo $key?>" <?php if (in_array($key, $checked)) {?> checked="checked"<?php }?>>
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
	global $post, $regions, $states, $custom;
	?>
	<div class="meta_form_row">
		<label for="location">Saved</label>
		<select name="location_id">
			<option value="">+ Add New Location</option>
		<?php 
		$locations = get_posts('post_type=locations&orderby=title&order=asc&numberposts=-1');
		foreach ($locations as $location) {
			$location_custom = get_post_custom($custom['location_id'][0]);
			?>
				<option value="<?php echo $location->ID?>"<?php selected($location->ID, $custom['location_id'][0])?>><?php echo $location->post_title?></option>
			<?php
		}
		?>
		</select>
	</div>
	<div class="meta_form_row">
		<label for="location">Location</label>
		<input type="text" name="location" id="location" value="<?php echo $custom['location'][0]?>" placeholder="Saturday Nite Live Group">
	</div>
	<div class="meta_form_row">
		<label for="address1">Address 1</label>
		<input type="text" name="address1" id="address1" value="<?php echo $custom['address1'][0]?>" placeholder="2634 Union Ave.">
	</div>
	<div class="meta_form_row">
		<label for="address2">Address 2</label>
		<input type="text" name="address2" id="address2" value="<?php echo $custom['address2'][0]?>" placeholder="Maplewood Plaza">
	</div>
	<div class="meta_form_row city">
		<label for="city">City</label>
		<input type="text" name="city" id="city" value="<?php echo $custom['city'][0]?>" placeholder="San Jose">
		<select name="state">
			<?php foreach ($states as $key=>$state) {?>
			<option <?php selected('CA', $key)?> value="<?php echo $key?>"><?php echo $state?></option>
			<?php }?>
		</select>
	</div>
	<div class="meta_form_row">
		<label for="region">Region</label>
		<select name="region" id="region">
			<?php foreach ($regions as $key=>$region) {?>
				<option value="<?php echo $key?>" <?php selected($custom['region'][0], $key)?>><?php echo $region?></option>
			<?php }?>
		</select>
	</div>
	<?php
}, 'meetings', 'normal', 'low');
