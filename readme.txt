=== Meetings ===
Contributors: joshreisner
Tags: meetings, aa, na, 12-step, locations
Requires at least: 3.2
Tested up to: 4.2
Stable tag: 1.0

This plugin is for maintaining database of meetings and locations. Has 
address standardization features.

== Description ==

This plugin was designed to maintain a list of meetings, grouped by address, for 
display in a list and on a map. It's currently in use at aasanjose.org/meetings.

Some notes:
* in the admin screen, it's best to use Chrome, because then the time field will be nicest
* Notes is for any non-standardized info, such as Big Book Study, or Building C
* Location should be a simple place-name, eg Queen of the Valley Hospital
* Address should only be address, no "Upstairs" or "Building C" or "Near 2nd Ave"
* You can fill in a very basic address and then when you leave that field you will see it try to standardize the address for you. it means you write "1000 trancas, napa" and it will come back with "1000 Trancas Street, Napa, CA 94558, USA"

== Installation ==
1. Upload files to your plugin folder.
2. Activate plugin.
3. Go to Settings > Permalinks and Save Changes.
4. Enter meetings.
5. The meetings archive should now be displaying data. You may also use the meetings_get() tag.

== Frequently Asked Questions ==


== Screenshots ==

1. Edit meeting
2. Edit location
3. Meeting detail page
4. Meeting list page
5. Meeting map

== Changelog ==

= 1.0 =
* Preparing for submission to the WordPress plugins directory