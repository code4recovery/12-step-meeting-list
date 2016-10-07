=== 12 Step Meeting List ===
Contributors: meetingguide, aasanjose
Tags: meetings, aa, al-anon, na, 12-step, locations, groups
Requires at least: 3.2
Tested up to: 4.6
Stable tag: 2.6.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin is designed to help 12 step programs list their meetings and locations. It standardizes addresses, and displays in a list or map.

== Description ==

This plugin is the easiest way to have your area's meetings listed in the [Meeting Guide mobile app](https://meetingguide.org/) for iOS and Android devices.

It was originally designed to maintain a list of AA meetings in Santa Clara County, CA. It's now in use in the following areas:

**AA**

* [Arkansas](http://arkansascentraloffice.org/meetings/)
* [Austin, TX](http://austinaa.org/meetings/)
* [Baton Rouge, LA](http://aabatonrouge.org/meetings/)
* [Bowling Green, KY](http://bowlinggreenaa.org/?post_type=meetings)
* [Corpus Christi, TX](http://www.cbiaa.org/meetings/)
* [Davis, CA](http://aadavis.org/meetings/)
* [DuPage County, IL](http://dist41.aa-nia.org/meetings/)
* [East Bay, CA](http://eastbayaa.org/meetings)
* [Elk Grove Village, IL](http://d15aa.org/d15aa.org/?post_type=meetings)
* [Europe](http://alcoholics-anonymous.eu/index.php/meetings/)
* [Greensboro, NC](http://nc23.org/meetings/)
* [Maine and New Brunswick](http://csoaamaine.org/meetings/)
* [Mesa, AZ](http://aamesaaz.org/meetings/)
* [Minneapolis, MN](http://aaminneapolis.org/meetings/)
* [New Orleans, LA](http://www.aaneworleans.org/meetings/)
* [New York, NY](http://meetings.nyintergroup.org/)
* [Oahu, HI](http://oahucentraloffice.com/meetings/)
* [Orlando, FL](http://cflintergroup.org/meetings/)
* [Philadelphia, PA](http://www.aasepia.org/meetings/)
* [Portland, OR](http://home.pdxaa.org/meetings/)
* [Sacramento, CA](http://aasacramento.org/meetings/)
* [San Jose, CA](https://aasanjose.org/meetings)
* [San Mateo, CA](http://aa-san-mateo.org/meetings)
* [State College, PA](http://www.district43.com/meetings/)
* [Toronto, Canada](http://aatoronto.org/?post_type=meetings)
* [Tri Valley, CA](http://trivalleyaa.org/meetings/)
* [Virginia Beach, VA](http://www.tidewaterintergroup.org/meetings/)
* [Walnut Creek, CA](http://contracostaaa.org/meetings)
* [West Hawaii, HI](http://www.westhawaiiaa.org/meetings/)
* [Western Slope, CA](http://westernsloped22.org/meetings/)
* [Western Kentucky](http://wkintergroup.org/meetings/)
* [Western Washington](http://area72aa.org/meetings/)

**Al-Anon**

* [Lancaster, PA](http://lanclebalanon.org/meetings/?d=any)
* [Orange County, CA](http://ocalanon-d60.org/meetings?d=any)

**CoDA**

* [Los Angeles](http://www.lacoda.org/)

**NA**

* [Chinook, CA](http://chinookna.org/meetings/)

**SAA**

* [Indiana](http://indiana-saa.org/?post_type=meetings)

[Let us know](mailto:web@aasanjose.org) if you're using this plugin and would like to be listed here.

= Notes =

* The Notes field is for any non-standardized meeting info, such as Basement, or Building C
* Location should be a simple place-name, eg Queen of the Valley Hospital
* Address should only be address, no "Upstairs" or "Building C" or "Near 2nd Ave"
* You can fill in a very basic address and then when you tab away from that field you will see it try to 
standardize the address for you. If you write "1000 trancas, napa" it will return with "1000 Trancas Street, 
Napa, CA 94558, US."

== Installation ==

1. Upload files to your plugin folder.
1. Activate plugin.
1. Enter meetings.
1. The meetings archive should now be displaying data, visit the settings page to locate it. 
1. You may also use the tsml_meetings_get() function inside your template.

== Frequently Asked Questions ==

= My meeting type isn't listed! =
If it's a broadly-applicable meeting type, please [contact us](mailto:web@aasanjose.org) so we can include it for you. 
We want to maintain consistency for the [mobile apps](https://meetingguide.org/), so not all proposals are included.

If you have access to your functions.php, you may add additional meeting types for your area. Simply adapt the following
example to your purposes:

	tsml_custom_types(array(
		'ABSI' => 'As Bill Sees It',
	));
	
Please note a few things about custom types:

1. Be careful with the codes ("ASBI" in the above example) as this gives you the ability to replace existing types. 
1. Note that custom meeting types will not be imported into the mobile app.
1. They are for searching. If you can't imagine yourself searching for a meeting this way, then
it's probably not a type you need. Have you ever searched for a 90-minute-meeting? If not, then it's
probably information that better belongs in the meeting notes.
1. Don't add a type for the default, eg 'Hour Long Meeting' or 'Non-Smoking.' If you do that, then you
have to be careful about tagging every single meeting in order to make the data complete.

= I don't like the new expandable regions dropdown menu! How do I remove it? =
No problem, just add this CSS to your theme:

	#meetings .controls ul.dropdown-menu div.expand { display: none; }
	#meetings .controls ul.dropdown-menu ul.children { display: block; }

= The dropdowns aren't opening! =
Most likely, this is because bootstrap is being included twice. You should add the following to your theme 
so that the TSML's version is removed.

	add_action('wp_enqueue_scripts', function(){
		wp_dequeue_style('bootstrap_css');
		wp_dequeue_script('bootstrap_js');
	});

= Where are my meetings listed? =
Your meetings will be listed on their special WordPress Archive page. Where that is depends on your 
Permalinks setup. The easiest way to find the link is to go to the **Meetings > Import & Settings** page 
and look for the link under "Where's My Info?"

= How can I change some of the text on the template pages? =
You can make use of the [gettext filter](https://codex.wordpress.org/Plugin_API/Filter_Reference/gettext) 
to override the plugin's translation strings. For example, if you wanted to replace 'Region' with 'Province,'
you could add the following to your functions.php file.

	function theme_override_tsml_strings($translated_text, $text, $domain) {
		if ($domain == 'tsml') {
			switch ($translated_text) {
				case 'Region':
					return 'Province';
			}
		}
		return $translated_text;
	}
	add_filter('gettext', 'theme_override_tsml_strings', 20, 3);

= How can I override the meeting list or detail pages? =
Copy the files from the plugin's templates directory into your theme's root directory. If you're using a 
theme from the Theme Directory, you may be better off creating a 
[Child Theme](https://codex.wordpress.org/Child_Themes). Now, you may override those pages. The 
archive-meetings.php file controls the meeting list page, single-meetings.php controls the meetings 
detail, and single-locations.php controls the location detail.

= Are there any shortcodes? =
Yes, you can use `[tsml_meeting_count]`, `[tsml_location_count]`, `[tsml_group_count]`, and `[tsml_region_count]` to 
display human-formatted counts of your entities. "For example, our area currently comprises 
[tsml_meeting_count] meetings." Also `[tsml_next_meetings count="5"]` displays a small table with the next 
several meetings in it. Use the `count` parameter to adjust how many are diplayed.

= Are there translations to other languages? =
Currently no, but if someone will volunteer to help with the translating, we will add it.

= I entered contact information into the meeting edit page but don't see it displayed on the site. =
That's right, we don't display that information by default for the sake of anonymity. To display it in your 
theme, you should follow the instructions above for overriding the meeting detail and location detail pages 
and then drop some or all of these tags in your PHP:

	<?php echo $meeting->contact_1_name?>
	<?php echo $meeting->contact_1_email?>
	<?php echo $meeting->contact_1_phone?>
	
== Screenshots ==

1. Meeting list page
1. Meeting map
1. Meeting detail page
1. Edit meeting
1. Edit location

== Changelog ==

= 2.6.3 =
* Possible bug fix for Oahu

= 2.6.2 =
* Bug fix on group-saving

= 2.6.1 =
* Bug fix on tsml_get_meetings() refactor

= 2.6 =
* Fixing undefined issue for New Orleans
* Refactoring tsml_get_meetings() slightly for speed
* Displaying errors on meetings AJAX (also New Orleans)

= 2.5.9 =
* Fixing bug in timepicker

= 2.5.8 =
* Fixing bug in timepicker

= 2.5.7 =
* Adding timepicker to make admin screen better for non-Chrome browsers

= 2.5.6 =
* Fixing an issue that caused the wrong region to display on the meeting list and in the CSV

= 2.5.5 =
* CSV column names always in English for import/export portability, per NA Poland

= 2.5.4 =
* Javascript fix for East Bay
* CSS tweaks for East Bay and other sites

= 2.5.3 =
* Change to the way addresses are handled and formatted (you may need to update your custom templates)
* Removing seven hidden fields (address, city, state, postal_code, country and region from locations, region from meetings)
* Now using WordPress's built-in taxonomy system for regions
* Refactored search should now be faster and require less memory
* Addresses are now geocoded in your language
* Fixing region bug noticed by Central Texas
* Hyphens no longer interfering with search per Oakland
* End time auto-suggested when editing a start time
* Improved location name autocomplete on edit screen
* Fixes for twentyten theme per West Hawaii
* Counts are now correct on Regions admin page (but they are of locations, rather than meetings)

= 2.5.2 =
* More notification information courtesy of SEPIA

= 2.5.1 =
* End times importing correctly, import process sped up per Western Washington

= 2.5 =
* PHP < 5.3 compatibility per Oahu

= 2.4.9 =
* Fixing import bug when a sub-region has the name name as a parent region
* Fixing address bug when the address has double quotes in it
* Fixing edge-case meeting-saving bug affecting Sunday meetings

= 2.4.8 =
* Fixing directions button links, there was an issue on Google if the location name wasn't recognized

= 2.4.7 =
* New expandable regions menu (if you don't like it, see FAQ for how to remove)
* Stripping punctuation from search, eg. St. James should now match St James
* Address-saving bug fix

= 2.4.6 =
* Saving posts is now more efficient: fewer writes to the database, fewer custom fields

= 2.4.5 =
* Important bug fix for admin edit screen javascript

= 2.4.4 =
* New system for overriding Google's address lookups due to very infrequent issues in NYC and Maine
* New language-related meeting types for Toronto

= 2.4.3 =
* Adding meeting end time per NYC

= 2.4.2 =
* Search highlighter bug

= 2.4.1 =
* Fixing search highlighter per NYC

= 2.4 =
* Converting imported text to UTF-8 per Maine

= 2.3.9 =
* Fixing JavaScript sort bug

= 2.3.8 =
* XX other meetings at this location

= 2.3.7 =
* Possible now to show meeting types as column in list with CSS per Arkansas
* Group notes line breaks not getting removed by import per NYC

= 2.3.6 =
* Group name and address spacing per NYC

= 2.3.5 =
* Last contact date field for NYC

= 2.3.4 =
* Linking directions to geo coordinates for Apple Maps issue
* Updating contact email addresses

= 2.3.3 =
* Print view columns per Maine

= 2.3.2 =
* Adding logic to prevent a meeting from being both open and closed

= 2.3.1 =
* Removing shortened ternary operator; doesn't work with PHP < 5.3

= 2.3 =
* New shortcode for Next Meetings mini-table