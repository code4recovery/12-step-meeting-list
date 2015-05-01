=== 12 Step Meeting List ===
Contributors: aasanjose
Tags: meetings, aa, na, 12-step, locations
Requires at least: 3.2
Tested up to: 4.2
Stable tag: 1.0.1

This plugin is for maintaining database of 12 step meetings and locations. Helps to standardize
addresses, and displays on a map.

== Description ==

This plugin was originally designed to maintain a list of AA meetings, grouped by address, for 
display in a list and on a map. It's currently in use in that capacity at <http://aasanjose.org/meetings>.

It can, however, be used to store any type of 12 step program meeting, such as Al-Anon, OA, or NA.

Some notes:
* in the admin screen, it's best to use Chrome, because then the time field will be nicest
* Notes is for any non-standardized meeting info, such as Basement, or Building C
* Location should be a simple place-name, eg Queen of the Valley Hospital
* Address should only be address, no "Upstairs" or "Building C" or "Near 2nd Ave"
* You can fill in a very basic address and then when you tab away from that field you will see it try to standardize the address for you. it means you write "1000 trancas, napa" and it will come back with "1000 Trancas Street, Napa, CA 94558, USA"

== Installation ==
1. Upload files to your plugin folder.
2. Activate plugin.
3. Enter meetings.
4. The meetings archive should now be displaying data, visit the settings page to locate it. 
5. You may also use the tsml_meetings_get() function inside your template.

== Frequently Asked Questions ==

= My meeting type isn't listed! =
Please file a support request and we will add it for you.

= Are there translations to other languages? =
Currently no, but if someone will help translate, we will add the translation.

== Screenshots ==

1. Edit meeting
2. Edit location
3. Meeting detail page
4. Meeting list page
5. Meeting map

== Changelog ==

= 1.0.1 =
* Updates requested by WordPress team

= 1.0 =
* Preparing for submission to the WordPress plugins directory