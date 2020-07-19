=== 12 Step Meeting List ===
Contributors: aasanjose
Requires at least: 3.2
Tested up to: 5.4.2
Stable tag: 3.7.2

This plugin helps twelve step recovery programs list their meetings. It standardizes addresses, and displays results in a searchable list and map.

== Description ==

This plugin helps twelve step recovery programs list their meetings. It standardizes addresses, and displays results in a searchable list and map.

It's also the easiest way for Alcoholics Anonymous service entities to get listed in the [Meeting Guide mobile app](https://meetingguide.org/) for iOS and Android devices.

This plugin was originally designed to maintain a list of A.A. meetings in Santa Clara County, CA. It is being used to provide meeting information for AA and other 12-step/recovery groups around the world.

= Notes =

* The Meeting Notes field is for any non-standardized meeting info, such as Basement, or Building C
* Location should be a simple place-name, eg Queen of the Valley Hospital
* Address should only be the address; no "Upstairs" or "Building C" or "Near 2nd Ave"
* You can fill in a very basic address and then when you tab away from that field you will see it try to standardize the address for you. If you write "1000 trancas, napa" it will replace it with "1000 Trancas St, Napa, CA 94558, USA."

== Installation ==

Basically you can just install the plugin, add a mapping API key from Mapbox (or Google), and start 
entering your meetings. That is all it takes to get started!

== Frequently Asked Questions ==

= My meeting type isn't listed! =
If it's a broadly-applicable meeting type, please ask us in the [support forum](https://wordpress.org/support/plugin/12-step-meeting-list/). We must maintain consistency for the [mobile apps](https://meetingguide.org/), so not all proposals are accepted.

If you have access to your theme's functions.php, you may add additional meeting types or rename existing ones. Simply adapt the following example to your purposes:

	if (function_exists('tsml_custom_types')) {
		tsml_custom_types(array(
			'XYZ' => 'My Custom Type',
		));
	}
	
Please note a few things about custom types:

1. Once you've added the type, you will see it under 'More' on the Meeting edit screen. It will show up in the dropdown once you use it on a meeting.
1. Be careful with the codes ("XYZ" in the above example) as this gives you the ability to replace existing types. 
1. Note that custom meeting types are not imported into the Meeting Guide app.
1. They are for searching. If you can't imagine yourself searching for a meeting this way, then it's probably not a type you need. Have you ever searched for a 90-minute meeting? If not, then it's probably information that better belongs in the meeting notes.
1. Don't add a type for the default, eg 'Hour Long Meeting' or 'Non-Smoking.' If you do that, then you have to be careful about tagging every single meeting in order to make the data complete.

= Where are my meetings listed? =
It depends on your Permalinks setup. The easiest way to find the link is to go to the **Dashboard > Meetings > Import & Settings** page and look for it under "Where's My Info?"

= I need to correct a meeting address or change a pin's location =
We get our geocoding positions from Google (this true even if your maps are by Mapbox). Google is correct an amazing amount of the time, but not always. If you need to add a custom location, add this to your theme's functions.php. 

Note you can add multiple entries to the array below.

	if (function_exists('tsml_custom_types')) {
		tsml_custom_addresses(array(
			'5 Avenue Anatole France, 75007 Paris, France' => array(
				'formatted_address' => '5 Avenue Anatole France, 75007 Paris, France',
				'city' => 'Paris',
				'latitude' => 48.858372,
				'longitude' => 2.294481,
			),
		));
	}

= How can I make the Region dropdown not be collapsible? =
No problem, just add this CSS to your theme:

	div#tsml #meetings .controls ul.dropdown-menu div.expand { display: none; }
	div#tsml #meetings .controls ul.dropdown-menu ul.children { height: auto; }

= How can I show Any Day by default? =
The easiest way is to link to that view straight from your navigation. Usually that looks like `/meetings/?tsml-day=any`, but it can vary depending on your settings.

If you'd prefer to keep the default address, you could add this code to your theme's functions.php instead:

	$tsml_defaults['day'] = null;

= How do I change the default search radius for location searches? =
Add this to your theme's functions.php. The value should be an existing value, ie 1, 5, 10, 25 or 50.

	$tsml_defaults['distance'] = 25;

= Can I get the meeting list to display the full address, including city, state and country? =
Add this to your theme's functions.php.

	$tsml_street_only = false;

= Can I change the order of the columns on the meeting list page, eg put the Region first? =
Add this to your theme's functions.php. Feel free to change the order or column names (eg 'Region') but keep the keys the same (eg 'region').

	$tsml_columns = array(
		'region' => 'Region',
		'time' => 'Time',
		'distance' => 'Distance',
		'name' => 'Name',
		'location' => 'Location',
		'address' => 'Address',
		'types' => 'Types'
	);

= Can I change the default sort order on the meeting list page? =
By default, the plugin sorts by day, then time, then location name. To set your own sort index, add this to your functions.php:

	$tsml_sort_by = 'region'; //options are name, location, address, time, or region

= How can I override the meeting list or detail pages? =
Copy the files from the plugin's templates directory into your theme's root directory. If you're using a theme from the Theme Directory, you may be better off creating a [Child Theme](https://codex.wordpress.org/Child_Themes). Now, you may override those pages. The archive-meetings.php file controls the meeting list page, single-meetings.php controls the meetings detail, and single-locations.php controls the location detail.

*Please note* these pages will evolve over time. If you override, you will someday experience website errors after an update. If that happens, please update your theme's copy of the plugin pages. 

= Can I see types in the meeting list? And can I adjust the /Men and /Women after the meeting name? =
To see types in the meeting list, one way to do it is to add some CSS to your theme which will make a types column visible.

	@media screen and (min-width: 768px) {
		div#tsml #meetings .types { display: table-cell !important; }
	}

One drawback of this approach is that it shows all the meeting types, and you might not want all of them to be displayed over and over in the meeting list.

Another approach is to adjust which meeting types are "flagged" in the meeting names, by default for most programs this is /Men and /Women. To adjust this, find the meeting type code for each type you want to show and include it in your theme's functions.php like this:

	if (function_exists('tsml_custom_flags')) {
		tsml_custom_flags(array('M', 'W', 'O', 'C'));
	}

The code above will add "Open" and "Closed" flags to the meeting name.

= When there are notes on a meeting, can I indicate that somehow in the meeting list? =
Yes, with CSS. Rows that have meeting notes will have a 'notes' class. To add an asterisk, for example, try this:

	div#tsml tr.notes a:after { content: "*"; }

= Can I import a custom spreadsheet format? =
If you don't mind some PHP programming, then yes! Create a function called `tsml_import_reformat`, and use it to 
reformat your incoming data to the standard format

	if (!function_exists('tsml_import_reformat')) {
		function tsml_import_reformat($meetings) {
			//your code goes here
			return $meetings;
		}
	}

= How can I change some of the text on the template pages, eg the column headings? =
You can make use of the [gettext filter](https://codex.wordpress.org/Plugin_API/Filter_Reference/gettext) to override the plugin's translation strings. For example, if you wanted to replace 'Region' with 'City,' you could add the following to your functions.php file.

	function theme_override_tsml_strings($translated_text, $text, $domain) {
		if ($domain == '12-step-meeting-list') {
			switch ($translated_text) {
				case 'Region':
					return 'City';
			}
		}
		return $translated_text;
	}
	add_filter('gettext', 'theme_override_tsml_strings', 20, 3);

= How can I temporarily hide a meeting without deleting it? =
Save it as a draft by editing the meeting's Status.

= Are there shortcodes? =
Yes, you can use `[tsml_meeting_count]`, `[tsml_location_count]`, `[tsml_group_count]`, and `[tsml_region_count]` to display human-formatted counts of your entities. For example, "Our area currently comprises [tsml_meeting_count] meetings." Also `[tsml_next_meetings count="5"]` displays a small table with the next several meetings in it. Use the `count` parameter to adjust how many are displayed. This will be unstyled if you're not using bootstrap in your theme.

Additionally, you can use `[tsml_types_list]` and `[tsml_regions_list]` to output linked lists to your meeting finder.

= Are there translations to other languages? =
It is translated into Polish. If you would like to volunteer to help translate another language, we would be pleased to work with you.

= I entered contact information into the meeting edit page but don't see it displayed on the site. =
That's right, we don't display that information by default for the sake of anonymity. To display it in your theme, go to Import & Settings and set the Meeting/Group Contacts dropdown to "public."

= Can I run this as my main website homepage? =
Sure. Try adding this code to your theme's functions.php:

	add_action('pre_get_posts', 'tsml_front_page');
	
Also check out our [One Page Meeting List](https://github.com/code4recovery/one-page-meeting-list) theme.
	
= Can I use this plugin to list telephone meetings or other meetings without a fixed location? =
No, there's not a good way to do this at this time. All meetings currently need to have a geographic location.

Some sites have used a general geographic area, such as a city name, but this isn't a very good solution, because a map
pin will still show up for these meetings and people will try to get directions to them. 

= Can I change the URL of the meetings list? =
Yes, try setting the $tsml_slug variable in your functions.php.

	$tsml_slug = 'schedule';

To apply these changes, you must go to Settings > Permalinks and click "Save Changes"

== Screenshots ==

1. Meeting list page
1. Meeting map
1. Meeting detail page
1. Edit meeting
1. Edit location

== Changelog ==

= 3.7.2 =
* Fixed bug involving end_time for meeting.

= 3.7.1 =
* Fixed bug introduced in previous version.

= 3.7.0 = 
* Added additional support for 7th Tradition contributions.
* Added outdoor and seniors meeting types.
* Fixed bugs affecting contacts.
* Tweaked how contacts are displayed.

= 3.6.6 =
* Added TC and ONL flags for Al-Anon and other programs.

= 3.6.5 =
* Added hiding of conference phone numbers.
* Changed Temporary Closure to Location Temporary Closed.
* Changed online meeting to be accepted if dial-in only.
* Improved URL screening for csv/json imports.
* Improved front end styling for meetings.

= 3.6.4 = 
* Updated CSV import/export and template to reflect added fields.
* Added abiility to bulk add/remove Temporary Closure type.
* Add two additional online conference types.
* Updated online phone button.
* Other bug fixes.

= 3.6.3 = 
* Fixed issue with setting null for conference types.
* Fixed JSON feed not importing online conference info, and Venmo info.
* Added Skype conference type.

= 3.6.2 = 
* Changes online meeting information from group to individual meeting (Issue #82).
* Adds front end styling for online meetings.

= 3.6.1 =
* Maintenance release

= 3.6.0 =
* Added feature to include online meeting information for temporarily closed meetings.
* Added "online meeting" type.

= 3.5.4 =
* Added temporary closure styling to widget.
 
= 3.5.3 =
* Changes to front end display supporting temporary closure tag.

= 3.5.2 =
* Adding "Temporary Closure" meeting type to all programs.

= 3.5.1 =
* Compatibility for PHP < 5.3
* "Need help" button on Import & Settings page

= 3.5.0 =
* Added option for upcoming meetings widget to display message if no further meetings exist for today.
* Added size to PDF generator.
* Fixed bug with restoring meetings from trash.
* Updated logo.

= 3.4.22 =
* Fixing bug in geocode caching (Ogden)

= 3.4.21 =
* Updating how PDF displays groups (hmbrecords)
* Updating documentation regarding JSON feeds (brianw)
* Updating bug and feature request processes

= 3.4.20 =
* Restoring PHP 5.3 compatibility

= 3.4.19 =
* Updating meeting types for Al-Anon

= 3.4.18 =
* Adding two new programs

= 3.4.17 =
* Importing Google Sheets (via Puget Sound)
* Fixing search with apostrophes (Houston)
* Further attempts to fix PDF errors (Ft Worth)

= 3.4.16 =
* Further attempts to fix PDF errors (Ft Worth)

= 3.4.15 =
* Fixing PDF error (Ft Worth)

= 3.4.14 =
* Geocode (Western MA, Traverse City)

= 3.4.13 =
* Hiding PHP notices for empty locations (Ft Worth)

= 3.4.12 =
* Fixing javascript bug when meeting has no types

= 3.4.11 =
* Fixing meeting types bug introduced in earlier commit

= 3.4.10 =
* Bugfixes relating to meeting type "flag" customization (Akron)

= 3.4.9 =
* PHP 5.2 compatibility (Hanover PA)

= 3.4.8 =
* Geocodes (Bolivia)

= 3.4.7 =
* Fixing night time filter

= 3.4.6 =
* Fixing error message in upcoming meetings widget (Inland Empire)
* Including slug in export CSV (Northern IL)
* Update cache when deleting meetings (Southern IL)
* Removing mention of non-AA programs from readme (San Diego)
* Fixing code formatting in FAQ

= 3.4.5 =
* Importing fix

= 3.4.4 =
* Syntax error on PHP < 5.4 (Palm Springs)
* FNV Import Location Field (Minnesota)
* District filtering working again

= 3.4.3 =
* Fixing filter for parent regions (PA Al Anon)
* Adding 'delete all' AJAX route
* Fixing JSON Import (San Francisco)

= 3.4.2 =
* Adding Non-Binary meeting type (Los Angeles)
* Geocodes (Western Mass)

= 3.4.1 =
* 3.4 was missing a file :( 

= 3.4 =
* Major rewrite to make plugin more CPU-efficient (Ventura)
* Fixed bug where leaving a space at the end of a data source would cause an error
* Fixed bug where filters wouldn't work after switching to Google Map view (SCA)
* Added post_status to params for tsml_get_meetings() (New England SLAA)
* Some new geocode overrides
