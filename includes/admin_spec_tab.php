<?php
//example & spec tab 
?>

<div class="col">
	<div class="postbox">
		<div class="inside">
			<!-- <h3><strong><?php _e('Spreadsheet Example & Spec', '12-step-meeting-list') ?></strong></h3> -->
			<p><a href="<?php echo plugin_dir_url(__FILE__) . '../template.csv' ?>" class="button button-large"><span class="dashicons dashicons-media-spreadsheet"></span><?php _e('Example spreadsheet', '12-step-meeting-list') ?></a></p>
			<ul class="ul-disc">
				<li><?php _e('<strong>Time</strong>, if present, should be in a standard date format such as 6:00 AM or 06:00. Non-standard or empty dates will be imported as "by appointment."', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>End Time</strong>, if present, should be in a standard date format such as <code>6:00 AM</code> or <code>06:00</code>.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Day</strong> if present, should either <code>Sunday</code>, <code>Monday</code>, <code>Tuesday</code>, <code>Wednesday</code>, <code>Thursday</code>, <code>Friday</code>, or <code>Saturday</code>. Meetings that occur on multiple days should be listed separately. \'Daily\' or \'Mondays\' will not work. Non-standard days will be imported as "by appointment."', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Name</strong> is the name of the meeting, and is optional, although it\'s valuable information for the user. If it\'s missing, a name will be created by combining the location, day, and time.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Slug</strong> optional, and sets the meeting post\'s "slug" or unique string, which is used in the URL. This should be unique to the meeting. Setting this helps preserve user bookmarks.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Location</strong> is the name of the location, and is optional. Generally it\'s the group or building name. If it\'s missing, the address will be used. In the event that there are multiple location names for the same address, the first location name will be used.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Address</strong> is strongly encouraged and will be corrected by Google, so it may look different afterward. Ideally, every address for the same location should be exactly identical, and not contain extra information about the address, such as the building name or descriptors like "around back."', '12-step-meeting-list') ?></li>
				<li><?php _e('If <strong>Address</strong> is specified, then <strong>City</strong>, <strong>State</strong>, and <strong>Country</strong> are optional, but they might be useful if your addresses sound ambiguous to Google. If address is not specified, then these fields are required.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Notes</strong> are freeform notes that are specific to the meeting. For example, <code>last Saturday is birthday night</code>.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Conference URL</strong> is an optional URL to a specific public videoconference meeting. This should be a common videoconferencing service such as Zoom or Google Hangouts. It should launch directly into the meeting and not link to an intermediary page.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Conference URL Notes</strong> is an optional string which contains metadata about the Conference URL (e.g. meeting password in plain text for those groups unwilling to publish a one-tap URL).', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Conference Phone</strong> is the telephone number to dial into a specific meeting. Should be numeric, except a plus(\'+\') symbol may be used for international dialers, and the comma(\',\'), asterisk(\'*\'), and pound(\'#\') symbols can be used to form one-tap phone links.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Conference Phone Notes</strong> is an optional string with metadata about the \'Conference Phone\' (e.g. a numeric meeting password or other user instructions).', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Region</strong> is user-defined and can be anything. Often this is a small municipality or neighborhood. Since these go in a dropdown, ideally you would have 10 to 20 regions, although it\'s ok to be over or under.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Sub Region</strong> makes the Region hierarchical; in San Jose we have sub regions for East San Jose, West San Jose, etc. New York City might have Manhattan be a Region, and Greenwich Village be a Sub Region.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Location Notes</strong> are freeform notes that will show up on every meeting that this location. For example, "Enter from the side."', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Group</strong> is a way of grouping contacts. Meetings with the same group name will be linked and share contact information.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>District</strong> is user-defined and can be anything, but should be a string rather than an integer (e.g. <b>District 01</b> rather than <b>1</b>). A group name must also be specified.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Sub District</strong> makes the District hierachical.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Group Notes</strong> is for stuff like a short group history, or when the business meeting meets.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Website</strong> and <strong>Website 2</strong> are optional.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Email</strong> is optional. This is a public email address.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Phone</strong> is optional. This is a public phone number.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Venmo</strong> is an optional string and should be a valid Venmo handle, (e.g. <code>@AAGroupName</code>). This is understood to be the address for 7th Tradition contributions to the meeting, and not any other entity.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Square</strong> is an optional string and should be a valid Square Cash App cashtag, (e.g. <code>$AAGroupName</code>). This is understood to be the address for 7th Tradition contributions to the meeting, and not any other entity.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>PayPal</strong> is an optional string and should be a valid PayPal username, (e.g. <code>AAGroupName</code>). This is understood to be the address for 7th Tradition contributions to the meeting, and not any other entity.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>URL</strong> is optional and should point to the meeting\'s listing on the area website.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Feedback URL</strong> is an optional string URL that can be used to provide feedback about the meeting. These could be local links (e.g. <code>https://mywebsite.org/feedback?meeting=meeting-slug-1</code>), remote links (e.g. <code>https://typeform.com/to/23904203?meeting=meeting-slug-1</code>), or email links (e.g. <code>mailto:webservant@domain.org?subject=meeting-slug-1</code>).', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Edit URL</strong> is an optional string URL that trusted servants can use to edit the specific meeting\'s listing.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Contact 1/2/3 Name/Email/Phone</strong> (nine fields in total) are all optional. By default, contact information is only visible inside the WordPress dashboard.', '12-step-meeting-list') ?></li>
				<li><?php _e('<strong>Last Contact</strong> is an optional date.', '12-step-meeting-list') ?></li>
				<?php if (!empty($tsml_programs[$tsml_program]['types'])) { ?>
					<li><?php _e('<strong>Types</strong> should be a comma-separated list of the following options. This list is determined by which program is selected on the Settings tab.', '12-step-meeting-list') ?>
						<ul class="types">
							<?php foreach ($tsml_programs[$tsml_program]['types'] as $value) { ?>
								<li><?php echo $value ?></li>
							<?php } ?>
						</ul>
					</li>
				<?php } ?>
			</ul>
			<?php if ($tsml_program == 'aa') { ?>
				<p><?php _e('Additionally, you may import spreadsheets that are in the General Service Office\'s FNV database "Group Search Results" format. This format has 162 columns, the first column is <code>ServiceNumber</code>.', '12-step-meeting-list') ?></p>
			<?php } ?>
		</div>
	</div>
</div>