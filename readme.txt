=== 12 Step Meeting List ===
Contributors: aasanjose
Tags: meetings, aa, al-anon, na, 12-step, locations, groups
Requires at least: 3.2
Tested up to: 4.6
Stable tag: 2.3.6
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
* [Davis, CA](http://district17cnia.org/meetings/)
* [East Bay, CA](http://eastbayaa.org/meetings)
* [Elk Grove Village, IL](http://d15aa.org/d15aa.org/?post_type=meetings)
* [Europe](http://alcoholics-anonymous.eu/index.php/meetings/)
* [Greensboro, NC](http://nc23.org/meetings/)
* [Maine and New Brunswick](http://csoaamaine.org/meetings/)
* [Mesa, AZ](http://aamesaaz.org/meetings/)
* [Minneapolis, MN](http://aaminneapolis.org/meetings/)
* [New Orleans, LA](http://www.aaneworleans.org/meetings/)
* [Northern Minnesota](http://www.area35.org/meetings/)
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
* [Western Slope, CA](http://westernsloped22.org/meetings/)
* [Western Kentucky](http://wkintergroup.org/meetings/)

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

* in the admin screen, it's best to use Chrome, because then the time field will be nicest
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

= 2.2.9 =
* Remembering to minify assets

= 2.2.8 =
* Better CSS for expanding the map to fullscreen mode
* Assets reorganization / using Laravel Elixir

= 2.2.7 =
* Refactored address correction algorithm: no postal_code without address, neighborhood is ok place-name in lieu of address
* Fixing bug when updating meeting to be appointment meeting

= 2.2.6 =
* Adding Google Maps API key
* Hiding geolocation on non-https connections

= 2.2.5 =
* Going back to sample spreadsheet in CSV format, rather than XLSX
* Fixing ordering of screenshot captions

= 2.2.4 =
* Importer now supports CR, LF, and CRLF line endings

= 2.2.3 =
* More descriptive error when wp_mail() fails
* Style tweaks to feedback form

= 2.2.2 =
* Meeting info feedback form
* Accessibility markup

= 2.2.1 =
* Adding more sites to the readme (Bowling Green, State College)

= 2.2 =
* Fixed empty row issue on importer reported by Maine
* Print view displays meeting types, per Maine
