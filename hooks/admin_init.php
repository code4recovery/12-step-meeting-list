<?php

//set up page
wp_enqueue_style('meetings_meta_style', plugin_dir_url(__FILE__) . '../css/admin.css');
remove_meta_box('tagsdiv-region', 'meetings', 'side' );
remove_meta_box('tagsdiv-tags', 'meetings', 'side' );
remove_meta_box('revisionsdiv', 'meetings', 'normal' );

//add meta boxes
add_meta_box('info', 'General Info', function(){
	global $post, $days;

	//get post metadata
	$custom 	= get_post_custom($post->ID);
	$regions	= get_terms('region', 'hide_empty=0');
	$tags 		= get_terms('tags', 'hide_empty=0');
	if (!$checked = get_the_terms($post->ID, 'tags')) $checked = array();
	foreach ($checked as &$check) $check = $check->term_id;
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
		<label for="type">Type</label>
		<div class="checkboxes">
			<label><input type="radio" name="type" value="open" <?php checked($custom['type'][0], 'open')?>> Open</label>
			<label><input type="radio" name="type" value="closed" <?php checked($custom['type'][0], 'closed')?>> Closed</label>
		</div>
	</div>
	<div class="meta_form_row">
		<label for="tags">Tags</label>
		<div class="checkboxes">
			<?php foreach ($tags as $tag) {?>
				<label>
					<input type="checkbox" name="tags[]" value="<?php echo $tag->name?>" <?php if (in_array($tag->term_id, $checked)) {?> checked="checked"<?php }?>>
					<?php echo $tag->name?>
				</label>
			<?php }?>
		</div>
	</div>
	<div class="meta_form_row">
		<label for="notes">Notes</label>
		<textarea name="notes" id="notes" placeholder="eg. Babysitting is available"><?php echo $custom['notes'][0]?></textarea>
	</div>
	<?php
}, 'meetings', 'normal', 'low');

add_meta_box('location', 'Location', function(){
	global $post;
	?>
	<div class="meta_form_row">
		<label for="location">Location</label>
		<input type="text" name="location" id="location" placeholder="Calvary Church">
	</div>
	<div class="meta_form_row">
		<label for="address1">Address 1</label>
		<input type="text" name="address1" id="address1" placeholder="123 Main Street">
	</div>
	<div class="meta_form_row">
		<label for="address2">Address 2</label>
		<input type="text" name="address2" id="address2" placeholder="2nd Floor">
	</div>
	<div class="meta_form_row">
		<label for="region">Region</label>
		<select name="region" id="region">
			<?php foreach ($regions as $region) {?>
				<option value="<?php echo $region->ID?>"><?php echo $region->name?></option>
			<?php }?>
		</select>
	</div>
	<?php
}, 'meetings', 'normal', 'low');
