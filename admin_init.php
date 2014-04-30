<?php

remove_meta_box('tagsdiv-region', 'meetings', 'side' );
remove_meta_box('tagsdiv-tags', 'meetings', 'side' );

add_meta_box('info', 'General Info', function(){
	global $post;
	?>
	<div class="meta_form_row">
		<label for="day">Day</label>
		<select name="day" id="day">
			<option value="Sunday">Sunday</option>
			<option value="Monday">Monday</option>
			<option value="Tuesday">Tuesday</option>
			<option value="Wednesday">Wednesday</option>
			<option value="Thursday">Thursday</option>
			<option value="Friday">Friday</option>
			<option value="Saturday">Saturday</option>
		</select>
	</div>
	<div class="meta_form_row">
		<label for="time">Time</label>
		<input type="time" name="time" id="time">
	</div>
	<div class="meta_form_row">
		<label for="type">Type</label>
		<div class="checkboxes">
			<label><input type="radio" name="type" value="open" checked> Open</label>
			<label><input type="radio" name="type" value="closed"> Closed</label>
		</div>
	</div>
	<div class="meta_form_row">
		<label for="tags">Tags</label>
		<div class="checkboxes">
			<?php
			$tags = get_terms('tags', 'hide_empty=0');
			foreach ($tags as $tag) {
				echo '<label><input type="checkbox" name="tags[]" value="' . $tag->ID . '"> ' . $tag->name . '</label>';
			}
			?>
		</div>
	</div>
	<div class="meta_form_row">
		<label for="notes">Notes</label>
		<textarea name="notes" id="notes" placeholder="eg. Babysitting is available"></textarea>
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
			<?php
			$regions = get_terms('region', 'hide_empty=0');
			foreach ($regions as $region) {
				echo '<option value="' . $region->ID . '">' . $region->name . '</option>';
			}
			?>
		</select>
	</div>
	<?php
}, 'meetings', 'normal', 'low');
