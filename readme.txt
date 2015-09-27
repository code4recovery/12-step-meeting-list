=== 12 Step Meeting List ===
Contributors: aasanjose
Tags: meetings, aa, al-anon, na, 12-step, locations
Requires at least: 3.2
Tested up to: 4.3
Stable tag: 1.5.8
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin is designed to help 12 step programs list their meetings and locations. It standardizes addresses, and displays in a list or map.

== Description ==

This plugin was originally designed to maintain a list of AA meetings in Santa Clara County, CA. It's currently in use in that capacity at <http://aasanjose.org/meetings>.

It can be used, however, to list any type of 12 step program meeting, such as Al-Anon, OA, or NA.

Some notes:

* in the admin screen, it's best to use Chrome, because then the time field will be nicest
* The Notes field is for any non-standardized meeting info, such as Basement, or Building C
* Location should be a simple place-name, eg Queen of the Valley Hospital
* Address should only be address, no "Upstairs" or "Building C" or "Near 2nd Ave"
* You can fill in a very basic address and then when you tab away from that field you will see it try to standardize the address for you. If you write "1000 trancas, napa" it will return with "1000 Trancas Street, Napa, CA 94558, USA."

== Installation ==

1. Upload files to your plugin folder.
1. Activate plugin.
1. Enter meetings.
1. The meetings archive should now be displaying data, visit the settings page to locate it. 
1. You may also use the tsml_meetings_get() function inside your template.

== Frequently Asked Questions ==

= My meeting type isn't listed! =
Please file a support request and we will add it for you, so long as it is broadly applicable.

= Why can't the meeting types be ad-hoc? =
We hope to build a universal database someday.

= Are there translations to other languages? =
Currently no, but if someone will volunteer to help with the translating, we will add it.

== Screenshots ==

1. Edit meeting
1. Edit location
1. Meeting detail page
1. Meeting list page
1. Meeting map

== Changelog ==

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

= 1.4.9 =
* Better region guessing when geocoding

= 1.4.8 =
* Bug fixes: region and grouping

= 1.4.7 =
* Fixing un-deletable auto-draft issue
