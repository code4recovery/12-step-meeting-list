=== 12 Step Meeting List ===
Contributors: meetingguide, aasanjose
Tags: meetings, aa, al-anon, na, 12-step, locations, groups
Requires at least: 3.2
Tested up to: 4.7
Stable tag: 2.9.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin is designed to help 12 step programs list their meetings and locations. It standardizes addresses, and displays in a list or map.

== Description ==

This plugin is the easiest way to have your area's meetings listed in the [Meeting Guide mobile app](https://meetingguide.org/) for iOS and Android devices.

It was originally designed to maintain a list of AA meetings in Santa Clara County, CA. It's now in use in the following areas:

**Alcoholics Anonymous**

* [Arkansas](http://arkansascentraloffice.org/meetings/)
* [Austin, TX](http://austinaa.org/meetings/)
* [Baton Rouge, LA](http://aabatonrouge.org/meetings/)
* [Bowling Green, KY](http://bowlinggreenaa.org/meetings/)
* [Corpus Christi, TX](http://www.cbiaa.org/meetings/)
* [Davis, CA](http://aadavis.org/meetings/)
* [DuPage County, IL](http://dist41.aa-nia.org/meetings/)
* [East Bay, CA](http://eastbayaa.org/meetings)
* [Elk Grove Village, IL](http://d15aa.org/d15aa.org/?post_type=meetings)
* [Europe](http://alcoholics-anonymous.eu/index.php/meetings/)
* [Greensboro, NC](http://nc23.org/meetings/)
* [Joliet, IL](http://aadistrict51.org/meetings/)
* [Kansas](https://ks-aa.org/meetings/)
* [Maine and New Brunswick](http://csoaamaine.org/meetings/)
* [Mesa, AZ](http://aamesaaz.org/meetings/)
* [Miami, FL](http://aamiamidade.org/meetings)
* [Minneapolis, MN](http://aaminneapolis.org/meetings/)
* [Modesto, CA](http://wp.cviaa.org/meetings/)
* [Naples, FL](http://aanaples.org/meetings/)
* [New Orleans, LA](http://www.aaneworleans.org/meetings/)
* [New York, NY](http://meetings.nyintergroup.org/)
* [Oahu, HI](http://oahucentraloffice.com/meetings/)
* [Orlando, FL](http://cflintergroup.org/meetings/)
* [Philadelphia, PA](http://www.aasepia.org/meetings/)
* [Portland, OR](http://home.pdxaa.org/meetings/)
* [Sacramento, CA](http://aasacramento.org/meetings/)
* [San Jose, CA](https://aasanjose.org/meetings)
* [San Mateo, CA](http://aa-san-mateo.org/meetings)
* [Secular AA](https://www.secularaa.org/meetings/)
* [Southern Colorado](http://www.puebloaa.org/meetings/)
* [State College, PA](http://www.district43.com/meetings/)
* [Tidewater Area, VA](http://www.tidewaterintergroup.org/meetings/)
* [Toronto, Canada](https://aatoronto.org/meetings/)
* [Tri Valley, CA](http://trivalleyaa.org/meetings/)
* [Tulsa, OK](http://district40aa.com/meetings)
* [Vancouver, Canada](http://www.vancouveraa.ca/meetings/)
* [Vienna, Austria](https://www.aavienna.com/meetings/)
* [Virginia](https://aavirginia.org/meetings/)
* [WAAFT](http://www.waaft.org/meetings/)
* [Walnut Creek, CA](http://contracostaaa.org/meetings)
* [Warsaw, IN](http://www.aadistrict4143.com/meetings/)
* [Washington, DC](https://aa-dc.org/meetings)
* [West Hawaii, HI](http://www.westhawaiiaa.org/meetings/)
* [Western Slope, CA](http://westernsloped22.org/meetings/)
* [Western Kentucky](http://wkintergroup.org/meetings/)
* [Western Washington](http://area72aa.org/meetings/)

**Adult Children of Alcoholics**

* [Southern California](http://www.socalaca.org/meetings/?d=any)

**Al-Anon**

* [Lancaster, PA](http://lanclebalanon.org/meetings/?d=any)
* [Orange County, CA](http://ocalanon-d60.org/meetings?d=any)
* [Pennsylvania](http://pa-al-anon.org/meetings/)

**Codependents Anonymous**

* [Los Angeles](http://www.lacoda.org/)

**Narcotics Anonymous**

* [Chinook, CA](http://chinookna.org/meetings/)
* [Maine](http://www.namaine.org/meetings/)

**Sex Addicts Anonymous**

* [Indiana](http://indiana-saa.org/meetings/)

[Let us know](mailto:wordpress@meetingguide.org) if you're using this plugin and would like to be listed here.

= Notes =

* The Notes field is for any non-standardized meeting info, such as Basement, or Building C
* Location should be a simple place-name, eg Queen of the Valley Hospital
* Address should only be address, no "Upstairs" or "Building C" or "Near 2nd Ave"
* You can fill in a very basic address and then when you tab away from that field you will see it try to standardize the address for you. If you write "1000 trancas, napa" it will return with "1000 Trancas Street, Napa, CA 94558, US."

== Installation ==

Basically you can just install it and you should be good to go. For a quick walkthrough of the process, check out this screencast video:

[youtube https://www.youtube.com/watch?v=Qqg1RPX-FTQ]

== Frequently Asked Questions ==

= My meeting type isn't listed! =
If it's a broadly-applicable meeting type, please [contact us](mailto:wordpress@meetingguide.org) so we can include it for you. We want to maintain consistency for the [mobile apps](https://meetingguide.org/), so not all proposals are included.

If you have access to your theme's functions.php, you may add additional meeting types for your area. Simply adapt the following example to your purposes:

	if (function_exists('tsml_custom_types')) {
		tsml_custom_types(array(
			'ABSI' => 'As Bill Sees It',
		));
	}
	
Please note a few things about custom types:

1. Be careful with the codes ("ASBI" in the above example) as this gives you the ability to replace existing types. 
1. Note that custom meeting types are not imported into the Meeting Guide app.
1. They are for searching. If you can't imagine yourself searching for a meeting this way, then it's probably not a type you need. Have you ever searched for a 90-minute meeting? If not, then it's probably information that better belongs in the meeting notes.
1. Don't add a type for the default, eg 'Hour Long Meeting' or 'Non-Smoking.' If you do that, then you have to be careful about tagging every single meeting in order to make the data complete.

= I don't like the new expandable regions dropdown menu! How do I remove it? =
No problem, just add this CSS to your theme:

	#meetings .controls ul.dropdown-menu div.expand { display: none; }
	#meetings .controls ul.dropdown-menu ul.children { height: auto; }

= How do I change the default search radius for location searches? =
Add this to your functions.php. The value should be an existing value, ie 1, 5, 10, 25 or 50.

	$tsml_defaults['distance'] = 25;

= How can I get the meeting list to display the full address, including city, state and country? =
Add this to your functions.php.

	$tsml_street_only = false;

= The dropdowns aren't opening! =
Most likely, this is your theme also uses Bootstrap, so it is being included twice. Add the following to your theme so that the plugin's version is removed.

	add_action('wp_enqueue_scripts', function(){
		wp_dequeue_style('bootstrap_css');
		wp_dequeue_script('bootstrap_js');
	});

= Where are my meetings listed? =
Your meetings will be listed on their special WordPress Archive page. Where that is depends on your Permalinks setup. The easiest way to find the link is to go to the **Meetings > Import & Settings** page and look for the link under "Where's My Info?"

= How can I change some of the text on the template pages? =
You can make use of the [gettext filter](https://codex.wordpress.org/Plugin_API/Filter_Reference/gettext) to override the plugin's translation strings. For example, if you wanted to replace 'Region' with 'Province,' you could add the following to your functions.php file.
	function theme_override_tsml_strings($translated_text, $text, $domain) {
		if ($domain == '12-step-meeting-list') {
			switch ($translated_text) {
				case 'Region':
					return 'Province';
			}
		}
		return $translated_text;
	}
	add_filter('gettext', 'theme_override_tsml_strings', 20, 3);

= How can I override the meeting list or detail pages? =
Copy the files from the plugin's templates directory into your theme's root directory. If you're using a theme from the Theme Directory, you may be better off creating a [Child Theme](https://codex.wordpress.org/Child_Themes). Now, you may override those pages. The archive-meetings.php file controls the meeting list page, single-meetings.php controls the meetings detail, and single-locations.php controls the location detail.

= Are there any shortcodes? =
Yes, you can use `[tsml_meeting_count]`, `[tsml_location_count]`, `[tsml_group_count]`, and `[tsml_region_count]` to display human-formatted counts of your entities. For example, "Our area currently comprises [tsml_meeting_count] meetings." Also `[tsml_next_meetings count="5"]` displays a small table with the next several meetings in it. Use the `count` parameter to adjust how many are displayed. This will be unstyled if you're not using bootstrap in your theme.

= Are there translations to other languages? =
It is translated into Polish. If you would like to volunteer to help translate another language, we would be pleased to work with you.

= I entered contact information into the meeting edit page but don't see it displayed on the site. =
That's right, we don't display that information by default for the sake of anonymity. To display it in your theme, you should follow the instructions above for overriding the meeting detail and location detail pages and then drop some or all of these tags in your PHP:

	<?php echo $meeting->contact_1_name?>
	<?php echo $meeting->contact_1_email?>
	<?php echo $meeting->contact_1_phone?>

= Can I run this as my main website homepage? =
Sure. Try this code:

	add_action('pre_get_posts', 'tsml_front_page');
	
Also check out our [One Page Meeting List](https://github.com/meeting-guide/one-page-meeting-list) theme.
	
== Screenshots ==

1. Meeting list page
1. Meeting map
1. Meeting detail page
1. Edit meeting
1. Edit location

== Changelog ==

= 2.9.5 =
* Copy changes and bug fixes

= 2.9.4 =
* Adding setting to show the full address in the meeting list

= 2.9.3 =
* Removing vestige of dropdown on map button

= 2.9.2 =
* 'Upcoming' time bug fix

= 2.9.1 =
* New program: Adult Children of Alcoholics
* Bug fix on Any Day view per AA Vienna
* CSS layout tweaks

= 2.9 =
* New proximity search mode, courtesy of Washington Area Intergroup Association (WAIA)
* Enabled Google maps in search results
* Two new programs: Recovering Couples Anonymous and Sexual Compulsives Anonymous

= 2.8.9 =
* Per NYC, simplifying group last contact logic

= 2.8.8 =
* Region 'delete and reassign' dropdown now hierarchical
* Apply address change to other meetings at this location now checked by default

= 2.8.7 =
* Fixing incorrect Men javascript string

= 2.8.6 =
* Open / Closed descriptions for Al-Anon

= 2.8.5 =
* Fixing "O other meetings at this location"

= 2.8.4 =
* Per Baton Rouge, directions link fix
* Cache busting assets
* Removing 'undefined' from query string on certain map links

= 2.8.3 =
* Tested with WordPress 4.7

= 2.8.2 =
* Version bump

= 2.8.1 =
* Per Maine, updated FAQ instructions for expanding regions menu
* Per Maine, importer allows CSVs with uneven cell counts
* Per Toronto, fixed updated date in importer
* Per Toronto, problematic address added to overrides
* Fixed possible margin/padding bug on detail pages

= 2.8 =
* New look for meeting detail and location pages
* New description for open and closed meetings, per Area 23
* Translated javascript strings per Polish AA in UK
* Styling for nested regions dropdown
* New white expand/collapse icons for themes with dark dropdown background colors
