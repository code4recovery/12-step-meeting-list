=== 12 Step Meeting List ===
Contributors: aasanjose
Tags: meetings, aa, al-anon, na, 12-step, locations
Requires at least: 3.2
Tested up to: 4.3
Stable tag: 1.6.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin is designed to help 12 step programs list their meetings and locations. It standardizes addresses, and displays in a list or map.

== Description ==

This plugin was originally designed to maintain a list of AA meetings in Santa Clara County, CA. It's now in use in the following areas:

**AA**

* [Austin, TX](http://austinaa.org/meetings/)
* [District 15, Chicago, IL](http://d15aa.org/d15aa.org/?post_type=meetings)
* [East Bay, CA](http://eastbayaa.org/meetings)
* [Europe](http://alcoholics-anonymous.eu/index.php/meetings/)
* [Mesa, AZ](http://aamesaaz.org/meetings/)
* [Minneapolis, MN](http://aaminneapolis.org/meetings/)
* [Philadelphia, PA](http://www.aasepia.org/meetings/)
* [Portland, OR](http://home.pdxaa.org/meetings/)
* [Santa Clara County, CA](http://aasanjose.org/meetings)

**CoDA**

* [Los Angeles](http://www.lacoda.org/)

**NA**

* [Chinook, CA](http://chinookna.org/meetings/)

**SAA**

* [Indiana](http://indiana-saa.org/meetings/)

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
Please contact us using the information on the **Meetings > Import & Settings** page and and we will add 
it for you, so long as it is broadly applicable. Our goal is to one day create a [global database of 
meetings](https://meetingguide.org/) so some consistency will help with that.

= Why can't the meeting types be ad-hoc? =
We hope to build a universal database someday.

= Are there translations to other languages? =
Currently no, but if someone will volunteer to help with the translating, we will add it.

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

= How can I override the meeting list or detail pages? =
Copy the files from the plugin's templates directory into your theme's root directory. If you're using a 
theme from the Theme Directory, you may be better off creating a 
[Child Theme](https://codex.wordpress.org/Child_Themes). Now, you may override those pages. The 
archive-meetings.php file controls the meeting list page, single-meetings.php controls the meetings 
detail, and single-locations.php controls the location detail.

= I entered contact information into the meeting edit page but don't see it displayed on the site. =
That's right, we don't display that information by default for the sake of anonymity. To display it in your 
theme, you should follow the instructions above for overriding the meeting detail and location detail pages 
and then drop some or all of these tags in your PHP:

	<?php echo $meeting->contact_1_name?>
	<?php echo $meeting->contact_1_email?>
	<?php echo $meeting->contact_1_phone?>
	
These tags are for the meetings page, substitute `$location` for `$meeting` if you're on the locations page.

== Screenshots ==

1. Edit meeting
1. Edit location
1. Meeting detail page
1. Meeting list page
1. Meeting map

== Changelog ==

= 1.6.5 =
* Corrected spelling of 'Co-Dependents'

= 1.6.4 =
* New meeting types for CoDA
* Cleaned up template detail pages
* Answered some new FAQs
* Reordered types checkboxes on meeting edit page

= 1.6.3 =
* 'Any Day' option for time filter

= 1.6.2 =
* Filter meetings by time of day
* Two new meeting types for Tulsa (Literature, Candlelight)
* Versioning system for database upgrades
* Now implements the read-only [12 Step Meetings API](https://github.com/intergroup/api)

= 1.6.1 =
* Adding new Frequently Asked Question
* More CSS fixing for the Cannyon theme

= 1.6 =
* Fixing CSV link

= 1.5.9 =
* Adding area names / links to Readme
* Replacing template CSV with XLS (wasn't opening correctly in one area)
* CSS fix to more firmly lock down meeting list controls appearance

= 1.5.8 =
* Taking out database activation hook as it doesn't work
* Adding three new requested meeting types
* Fixing javascript geocoding bug

= 1.5.7 =
* Adding Sign Language meeting type for AA

= 1.5.6 =
* Message about iOS beta testing

= 1.5.5 =
* Adding location notes to the API

= 1.5.4 =
* Updated import instructions
* Including postal code on public address pages
* More address consistency

= 1.5.3 =
* Address lookup fix, legacy address data fix

= 1.5.2 =
* When geocoding address, also now checks if location already in database, populates form

= 1.5.1 =
* CSS fix for Divi theme (Portland and Philadelphia)

= 1.5 =
* Adding LGBTQ meeting type
