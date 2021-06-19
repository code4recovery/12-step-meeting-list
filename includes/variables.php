<?php
/*	
Don't make changes to this file! You'll have to reapply them every time you update the plugin.
if you need to customize your site, please follow the instructions on our FAQ:
👉 https://wordpress.org/plugins/12-step-meeting-list/
*/

//get the current boundaries of the coverage map
$tsml_bounds = get_option('tsml_bounds');

//get the secret cache location
if (!$tsml_cache = get_option('tsml_cache')) {
	$tsml_cache = '/tsml-cache-' . substr(str_shuffle(md5(microtime())), 0, 10) . '.json';
	update_option('tsml_cache', $tsml_cache);
}

// Define attendance options
$tsml_meeting_attendance_options = array(
	'in_person' => 'In-person',
	'online' => 'Online only',
	'hybrid' => 'In-person and online',
);

//load the set of columns that should be present in the list (not sure why this shouldn't go after plugins_loaded below)
$tsml_columns = array(
	'time' => 'Time',
	'distance' => 'Distance', 
	'name' => 'Meeting',
	'location' => 'Location',
	'address' => 'Address',
	'region' => 'Region',
	'district' => 'District',
	'types' => 'Types',
);

//list of valid conference providers (matches Meeting Guide app). set this to null in your theme if you don't want to validate
$tsml_conference_providers = array(
	'bluejeans.com' => 'Bluejeans',
	'freeconference.com' => 'Free Conference',
	'freeconferencecall.com' => 'FreeConferenceCall',
	'meet.google.com' => 'Google Hangouts',
	'gotomeet.me' => 'GoToMeeting',
	'gotomeeting.com' => 'GoToMeeting',
	'skype.com' => 'Skype',
	'webex.com' => 'WebEx',
	'zoho.com' => 'Zoho',
	'zoom.us' => 'Zoom',
);

//whether contacts are displayed publicly (defaults to no)
$tsml_contact_display = get_option('tsml_contact_display', 'private');

//define contact fields 
$tsml_contact_fields = array(
	'website' => 'url',
	'website_2' => 'url',
	'email' => 'string',
	'phone' => 'phone',
	'mailing_address' => 'string',
	'venmo' => 'string',
	'square' => 'string',
	'paypal' => 'string',
	'last_contact' => 'date',
);

//append to contacts
for ($i = 1; $i <= GROUP_CONTACT_COUNT; $i++) {
	foreach (array('name', 'email', 'phone') as $field) {
		$tsml_contact_fields['contact_' . $i . '_' . $field] = $field == 'phone' ? 'phone' : 'string';
	}
}

//empty global curl handle in case we need it
$tsml_curl_handle = null;

//load the array of URLs that we're using
$tsml_data_sources = get_option('tsml_data_sources', array());

//meeting search defaults
$tsml_defaults = array(
	'distance' => 2,
	'time' => null,
	'region' => null,
	'district' => null,
	'day' => intval(current_time('w')),
	'type' => null,
	'mode' => 'search',
	'query' => null,
	'view' => 'list',
);

//load the distance units that we're using (ie miles or kms)
$tsml_distance_units = get_option('tsml_distance_units', 'mi');

//load email addresses to send user feedback about meetings
$tsml_feedback_addresses = get_option('tsml_feedback_addresses', array());

//load whether feedback method to use is legacy or enhanced (defaults to enhanced)
$tsml_feedback_method = get_option('tsml_feedback_method', 'enhanced');

//load the API key user saved, if any
$tsml_google_maps_key = get_option('tsml_google_maps_key');

//load the geocoding method
$tsml_geocoding_method = get_option('tsml_geocoding_method', 'legacy');

/*
unfortunately the google geocoding API is not always perfect. used by tsml_import() and admin.js
find correct coordinates with http://nominatim.openstreetmap.org/ and https://www.latlong.net/
*/
$tsml_google_overrides = array(
	'1114 Private Drive, Dixon, NM 87527, USA' => array(
		'formatted_address' => '1114 Private Drive, Dixon, NM 87527, USA',
		'city' => 'Dixon',
		'latitude' => 36.1988282,
		'longitude' => -105.88777240000002,
	),
	'14-54 31st Ave, Long Island City, NY 11106, USA' => array(
		'formatted_address' => '14-54 31st Rd, Long Island City, NY 11106, USA',
		'city' => 'Long Island City',
		'latitude' => 40.7667739,
		'longitude' => -73.9306111,
	),
	'150 Church St, Santa Cruz, CA 95060, USA' => array(
		'formatted_address' => '150 Church St, Davenport, CA 95017, USA',
		'city' => 'Davenport',
		'latitude' => 37.012380,
		'longitude' => -122.192910,
	),
	'1669 Euclid Ave, Boulder, CO 80302, USA' => array(
		'formatted_address' => '1669 Euclid Ave, Boulder, CO 80309, USA',
		'city' => 'Boulder',
		'latitude' => 40.0065706,
		'longitude' => -105.2717488,
	),
    '1845 State Hwy V, Mansfield, MO 65704, USA' => array(
        'formatted_address' => '1845 State Hwy V, Mansfield, MO 65704, USA',
        'city' => 'Mansfield',
        'latitude' => 37.114508,
        'longitude' => -92.619343,
    ),
	'185 Main St, Freeport, ME 04032, USA' => array(
		'formatted_address' => '185 Main St, Freeport, ME 04032, USA',
		'city' => 'Freeport',
		'latitude' => 43.862634,
		'longitude' => -70.100545,
	),
	'19806 Wisteria St, Castro Valley, CA 94546, USA' => array(
		'formatted_address' => '19806 Wisteria St, Castro Valley, CA 94546, USA',
		'city' => 'Castro Valley',
		'latitude' => 37.7009164,
		'longitude' => -122.08583,
	),
	'208 E 13th St, New York, NY 10003, USA' => array(
		'formatted_address' => '208 W 13th St, New York, NY 10011, USA',
		'city' => 'New York',
		'latitude' => 40.73800835,
		'longitude' => -74.0010489174602,
	),
	'20, 19100 Ventura Blvd, Tarzana, CA 91356, USA' => array(
		'formatted_address' => '19100 Ventura Blvd, Tarzana, CA 91356, USA',
		'city' => 'Tarzana',
		'latitude' => 34.17217249999999,
		'longitude' => -118.548945,
	),
	'37 Skowhegan Rd, Fairfield, ME, 04937, USA' => array(
		'formatted_address' => '37 Skowhegan Rd, Fairfield, ME 04937, USA',
		'city' => 'Fairfield',
		'latitude' => 44.597699,
		'longitude' => -69.599635,
	),
	'45 Main St, Goshen, MA 01032, USA' => array(
		'formatted_address' => '45 Main St, Goshen, MA 01032, USA',
		'city' => 'Goshen',
		'latitude' => 42.441700,
		'longitude' => -72.800560,
	),
	'457 Main St, Stoneham, MA 02180, USA' => array(
		'formatted_address' => '457 Main St, Stoneham, MA 02180, USA',
		'city' => 'Stoneham',
		'latitude' => 42.476460,
		'longitude' => -71.100517,
	),
	'4125 Cedar Run Rd, Traverse City, MI 49684, USA' => array(
		'formatted_address' => '4125 Cedar Run Rd, Traverse City, MI 49684, USA',
		'city' => 'Traverse City',
		'latitude' => 44.758770,
		'longitude' => -85.657300,
	),
	'519 Church St, Toronto, ON M4Y 2C9, Canada' => array(
		'formatted_address' => '519 Church St, Toronto, ON M4Y 2C9, Canada',
		'city' => 'Toronto',
		'latitude' => 43.666532,
		'longitude' => -79.38097,
	),
	'55-514 Hawi Rd, Waimea, HI 96743, USA' => array(
		'formatted_address' => '55-514 Hawi Rd, Hawi, HI 96743, USA',
		'city' => 'Hawi',
		'latitude' => 20.2376863,
		'longitude' => -155.830639,
	),
	'61 State St, Brewer, ME 04412, USA' => array(
		'formatted_address' => '12 Family Center Ln, Brewer, ME 04412, USA',
		'city' => 'Brewer',
		'latitude' => 44.794759,
		'longitude' => -68.761303,
	),
	'67 Rue du Couvent, Gatineau, QC J9H, Canada' => array(
		'city' => 'Gatineau',
		'formatted_address' => '67 Rue du Couvent, Gatineau, QC J9H 6A2, Canada',
		'latitude' => 45.3975067,
		'longitude' => 45.3975067,
	),
	'Advent Lutheran Church, 2504 Broadway, New York, NY 10025, USA' => array(
		'formatted_address' => '2504 Broadway, New York, NY 10025, USA',
		'city' => 'New York',
		'latitude' => 40.7926923,
		'longitude' => -73.9726924,
	),
	'Almirante Grau 443, La Paz, Bolivia' => array(
		'formatted_address' => 'Almirante Grau 443, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.50227,
		'longitude' => -68.1367,
	),
	'Antonio Jose de Sucre, La Paz, Bolivia' => array(
		'formatted_address' => 'Antonio Jose de Sucre, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.49779,
		'longitude' => -68.19614,
	),
	'Augusta, ME 04330, USA' => array(
		'formatted_address' => '1 VA Center, Augusta, ME 04330, USA',
		'city' => 'Augusta',
		'latitude' => 44.2803692,
		'longitude' => -69.7042675,
	),
	'Av. Busch, La Paz, Bolivia' => array(
		'formatted_address' => 'Av. Busch, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.50346,
		'longitude' => -68.12103,
	),
	'Av. 16 de Julio, El Alto, Bolivia' => array(
		'formatted_address' => 'Av. 16 de Julio, El Alto, Bolivia',
		'city' => 'El Alto',
		'latitude' => -16.49414,
		'longitude' => -68.1772,
	),
	'Av. 20 de Octubre 2072, La Paz, Bolivia' => array(
		'formatted_address' => 'Av. 20 de Octubre 2072, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.50705,
		'longitude' => -68.1299,
	),
	'Av. Armentia, La Paz, Bolivia' => array(
		'formatted_address' => 'Av. Armentia, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.49037,
		'longitude' => -68.13756,
	),
	'Av. Ballivián & Calle 21, La Paz, Bolivia' => array(
		'formatted_address' => 'Av. Ballivián & Calle 21, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.5393,
		'longitude' => -68.07813,
	),
	'Av. Buenos Aires, La Paz, Bolivia' => array(
		'formatted_address' => 'Av. Buenos Aires, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.50564,
		'longitude' => -68.14476,
	),
	'Av. Chacaltaya 3044, La Paz, Bolivia' => array(
		'formatted_address' => 'Av. Chacaltaya 3044, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.49958,
		'longitude' => -68.17587,
	),
	'Av. Franco Valle 18, La Paz, Bolivia' => array(
		'formatted_address' => 'Av. Franco Valle 18, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.50522,
		'longitude' => -68.16175,
	),
	'Av. Hector Ormachea & Calle 9, La Paz, Bolivia' => array(
		'formatted_address' => 'Av. Hector Ormachea & Calle 9, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.52692,
		'longitude' => -68.10809,
	),
	'Av. Illampu 850, La Paz, Bolivia' => array(
		'formatted_address' => 'Av. Illampu 850, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.49747,
		'longitude' => -68.13961,
	),
	'Av. Illimani 1850, La Paz, Bolivia' => array(
		'formatted_address' => 'Av. Illimani 1850, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.4995,
		'longitude' => -68.12671,
	),
	'Av. Mariscal Santa Cruz, La Paz, Bolivia' => array(
		'formatted_address' => 'Av. Mariscal Santa Cruz, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.49862,
		'longitude' => -68.13555,
	),
	'Av. Tiahuanacu, El Alto, Bolivia' => array(
		'formatted_address' => 'Av. Tiahuanacu, El Alto, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.51159,
		'longitude' => -68.16109,
	),
	'Av. Tito Yupanqui, La Paz, Bolivia' => array(
		'formatted_address' => 'Av. Tito Yupanqui, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.49062,
		'longitude' => -68.11752,
	),
	'Beach 94th St, Queens, NY 11693, USA' => array(
		'formatted_address' => '320 Beach 94th Street, Queens, NY 11693, US',
		'city' => 'Queens',
		'latitude' => 40.587465,
		'longitude' => -73.81683149999999,
	),
	'Calle P. Eyzaquirre, La Paz, Bolivia' => array(
		'formatted_address' => 'Calle P. Eyzaquirre, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.49433,
		'longitude' => -68.14893,
	),
	'Calle Raul Herrera, La Paz, Bolivia' => array(
		'formatted_address' => 'Calle Raul Herrera, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.50904,
		'longitude' => -68.11155,
	),
	'Calle Yanacachi, La Paz, Bolivia' => array(
		'formatted_address' => 'Calle Yanacachi, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.47803,
		'longitude' => -68.1192,
	),
	'Cañada Strongest, La Paz, Bolivia' => array(
		'formatted_address' => 'Cañada Strongest, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.50334,
		'longitude' => -68.13384,
	),
	'Chuquisaca 672, La Paz, Bolivia' => array(
		'formatted_address' => 'Chuquisaca 672, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.49314,
		'longitude' => -68.13911,
	),
	'Church of Our Lady of Guadalupe, 229 W 14th St, New York, NY 10011, USA' => array(
		'formatted_address' => '229 W 14th St, New York, NY 10011, USA',
		'city' => 'New York',
		'latitude' => 40.7393643,
		'longitude' => -74.00081270000001,
	),
	'Entre Ríos 1681, La Paz, Bolivia' => array(
		'formatted_address' => 'Entre Ríos 1681, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.49733,
		'longitude' => -68.15559,
	),
	'Farmington, ME, USA' => array(
		'formatted_address' => '111 Franklin Health Commons, Farmington, ME 04938, USA',
		'city' => 'Farmington',
		'latitude' => 44.62654999999999,
		'longitude' => -70.162092,
	),
	'Kwakiutl Totem Pole, Regina, SK S4S, Canada' => array(
		'formatted_address' => 'Kwakiutl Totem Pole, Regina, SK S4S, Canada',
		'city' => 'Regina',
		'latitude' => 50.428500,
		'longitude' => -104.612917,
	),
	'Max Paredes 836, La Paz, Bolivia' => array(
		'formatted_address' => 'Max Paredes 836, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.49792,
		'longitude' => -68.14142,
	),
	'N3155 County Rd H, Lake Geneva, WI 53147' => array(
		'formatted_address' => 'N3155 County Rd H, Lake Geneva, WI 53147, USA',
		'city' => 'Lake Geneva',
		'latitude' => 42.606220,
		'longitude' => -88.446960,
	),
	'Nueva York & Av. Eduardo Avaroa, La Paz, Bolivia' => array(
		'formatted_address' => 'Nueva York & Av. Eduardo Avaroa, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.49965,
		'longitude' => -68.15019,
	),
	'Our Lady of Good Counsel Church, 230 E 90th St, New York, NY 10128, USA' => array(
		'formatted_address' => '230 E 90th St, New York, NY 10128, USA',
		'city' => 'New York',
		'latitude' => 40.7806471,
		'longitude' => -73.9509674,
	),
	'Sagarnaga 60, La Paz, Bolivia' => array(
		'formatted_address' => 'Sagarnaga 60, La Paz, Bolivia',
		'city' => 'La Paz',
		'latitude' => -16.49674,
		'longitude' => -68.13742,
	),
	'Skowhegan Rd, Fairfield, ME, USA' => array(
		'formatted_address' => '37 Skowhegan Rd, Fairfield, ME 04937, USA',
		'city' => 'Fairfield',
		'latitude' => 44.597699,
		'longitude' => -69.599635,
	),
	'St. Catherine of Siena\'s Church, 411 E 68th St, New York, NY 10065, USA' => array(
		'formatted_address' => '411 E 68th St, New York, NY 10065, USA',
		'city' => 'New York',
		'latitude' => 40.7652978,
		'longitude' => -73.9570329,
	),
	'St. Thomas More\'s Church, 65 E 89th St, New York, NY 10128, USA' => array(
		'formatted_address' => '65 E 89th St, New York, NY 10128, USA',
		'city' => 'New York',
		'latitude' => 40.7827448,
		'longitude' => -73.9567008,
	),
	'Westlands, 1 Mead Way, Bronxville, NY 10708, USA' => array(
		'formatted_address' => '1 Mead Way, Bronxville, NY 10708, USA',
		'city' => 'Bronxville',
		'latitude' => 40.935443,
		'longitude' => -73.8437546,
	),

);

//get the blog's language (used as a parameter when geocoding)
$tsml_language = substr(get_bloginfo('language'), 0, 2);

//alternative maps provider
$tsml_mapbox_key = get_option('tsml_mapbox_key');

//if no maps key, check to see if the events calendar plugin has one
if (empty($tsml_google_maps_key) && empty($tsml_mapbox_key)) {
	if ($tribe_options = get_option('tribe_events_calendar_options', array())) {
		if (array_key_exists('google_maps_js_api_key', $tribe_options)) {
			$tsml_google_maps_key = $tribe_options['google_maps_js_api_key'];
			update_option('tsml_google_maps_key', $tsml_google_maps_key);
		}
	}
}

//used to secure forms
$tsml_nonce = plugin_basename(__FILE__);

//load email addresses to send emails when there is a meeting change
$tsml_notification_addresses = get_option('tsml_notification_addresses', array());

//load the program setting (NA, AA, etc)
$tsml_program = get_option('tsml_program', 'aa');

//get the sharing policy
$tsml_sharing = get_option('tsml_sharing', 'restricted');

//get the sharing policy
$tsml_sharing_keys = get_option('tsml_sharing_keys', array());

//the default meetings sort order
$tsml_sort_by = 'time';

//only show the street address (not the full address) in the main meeting list
$tsml_street_only = true;

//for timing
$tsml_timestamp = microtime(true);

//these are empty now because polylang might change the language. gets set in the plugins_loaded hook
$tsml_days = $tsml_days_order = $tsml_programs = $tsml_types_in_use = $tsml_strings = null;

//string url for the meeting finder, or false for no automatic archive page
if (!isset($tsml_slug)) $tsml_slug = null;

add_action('plugins_loaded', 'tsml_define_strings');

function tsml_define_strings() {
	global $tsml_days, $tsml_days_order, $tsml_programs, $tsml_program, $tsml_slug, $tsml_strings, $tsml_types_in_use;

    //load internationalization
    load_plugin_textdomain('12-step-meeting-list', false, '12-step-meeting-list/languages');

	//days of the week
	$tsml_days	= array(
		__('Sunday', '12-step-meeting-list'),
		__('Monday', '12-step-meeting-list'),
		__('Tuesday', '12-step-meeting-list'),
		__('Wednesday', '12-step-meeting-list'),
		__('Thursday', '12-step-meeting-list'), 
		__('Friday', '12-step-meeting-list'), 
		__('Saturday', '12-step-meeting-list'),
	);

	//adjust if the user has set the week to start on a different day
	if ($start_of_week = get_option('start_of_week', 0)) {
		$remainder = array_slice($tsml_days, $start_of_week, null, true);
		$tsml_days = $remainder + $tsml_days;
	}

	//used by tsml_meetings_sort() over and over
	$tsml_days_order = array_keys($tsml_days);
	
	//supported program names (alpha by the 'name' key)
	$tsml_programs = array(
		'aca' => array(
			'abbr' => __('ACA', '12-step-meeting-list'),
			'flags' => array('M', 'W', 'TC', 'ONL'), //for /men and /women at end of meeting name (used in tsml_format_name())
			'name' => __('Adult Children of Alcoholics', '12-step-meeting-list'),
			'type_descriptions' => array(
				'C' => __('This meeting is closed; only those who have a desire to recover from the effects of growing up in an alcoholic or otherwise dysfunctional family may attend.', '12-step-meeting-list'),
				'O' => __('This meeting is open and anyone may attend.', '12-step-meeting-list'),
			),
			'types' => array(
				'A' => __('Age Restricted 18+', '12-step-meeting-list'),
				'AV' => __('Audio / Visual', '12-step-meeting-list'),
				'B' => __('Book Study', '12-step-meeting-list'),
				'BEG' => __('Beginners', '12-step-meeting-list'),
				'C' => __('Closed', '12-step-meeting-list'),
				'D' => __('Discussion', '12-step-meeting-list'),
				'T' => __('Fellowship Text', '12-step-meeting-list'),
				'G' => __('Gay/Lesbian', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'M' => __('Men', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'O' => __('Open', '12-step-meeting-list'),
				'S' => __('Speaker', '12-step-meeting-list'),
				'SP' => __('Spanish', '12-step-meeting-list'),
				'ST' => __('Steps', '12-step-meeting-list'),
				'W' => __('Women', '12-step-meeting-list'),
				'Y' => __('Yellow Workbook Study', '12-step-meeting-list'),
			),
		),
		'al-anon' => array(
			'abbr' => __('Al-Anon', '12-step-meeting-list'),
			'flags' => array('M', 'W', 'TC', 'ONL'), //for /men and /women at end of meeting name (used in tsml_format_name())
			'name' => __('Al-Anon', '12-step-meeting-list'),
			'type_descriptions' => array(
				'C' => __('Closed Meetings are limited to members and prospective members. These are persons who feel their lives have been or are being affected by alcoholism in a family member or friend.', '12-step-meeting-list'),
				'O' => __('Open to anyone interested in the family disease of alcoholism. Some groups invite members of the professional community to hear how the Al-Anon program aids in recovery.', '12-step-meeting-list'),
			),
			'types' => array(
				'AC' => __('Adult Child Focus', '12-step-meeting-list'),
				'Y' => __('Alateen', '12-step-meeting-list'),
				'A' => __('Atheist / Agnostic', '12-step-meeting-list'),
				'BA' => __('Babysitting Available', '12-step-meeting-list'),
				'BE' => __('Beginner', '12-step-meeting-list'),
				'AA' => __('Concurrent with AA Meeting', '12-step-meeting-list'),
				'AL' => __('Concurrent with Alateen Meeting', '12-step-meeting-list'),
				'EN' => __('English', '12-step-meeting-list'),
				'O' => __('Families Friends and Observers Welcome', '12-step-meeting-list'),
				'C' => __('Families and Friends Only', '12-step-meeting-list'),
				'FF' => __('Fragrance Free', '12-step-meeting-list'),
				'G' => __('Gay', '12-step-meeting-list'),
				'L' => __('Lesbian', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'M' => __('Men', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'SM' => __('Smoking Permitted', '12-step-meeting-list'),
				'S' => __('Spanish', '12-step-meeting-list'),
				'SP' => __('Speaker', '12-step-meeting-list'),
				'ST' => __('Step Meeting', '12-step-meeting-list'),				
				'T' => __('Transgender', '12-step-meeting-list'),
				'X' => __('Wheelchair Accessible', '12-step-meeting-list'),
				'W' => __('Women', '12-step-meeting-list'),
			),
		),
		'aa' => array(
			'abbr' => __('AA', '12-step-meeting-list'),
			'flags' => array('M', 'W', 'TC', 'ONL'), //for /men and /women at end of meeting name (used in tsml_format_name())
			'name' => __('Alcoholics Anonymous', '12-step-meeting-list'),
			'type_descriptions' => array(
				'C' => __('This meeting is closed; only those who have a desire to stop drinking may attend.', '12-step-meeting-list'),
				'O' => __('This meeting is open and anyone may attend.', '12-step-meeting-list'),
				'TC' => __('This meeting is temporarily not meeting in-person.', '12-step-meeting-list'),
				'ONL' => __('Online meeting. Details below.', '12-step-meeting-list')
			),
			'types' => array(
				'11' => __('11th Step Meditation', '12-step-meeting-list'),
				'12x12' => __('12 Steps & 12 Traditions', '12-step-meeting-list'),
				'ABSI' => __('As Bill Sees It', '12-step-meeting-list'),
				'BA' => __('Babysitting Available', '12-step-meeting-list'),
				'B' => __('Big Book', '12-step-meeting-list'),
				'H' => __('Birthday', '12-step-meeting-list'),
				'BRK' => __('Breakfast', '12-step-meeting-list'),
				'CAN' => __('Candlelight', '12-step-meeting-list'),
				'CF' => __('Child-Friendly', '12-step-meeting-list'),
				'C' => __('Closed', '12-step-meeting-list'),
				'AL-AN' => __('Concurrent with Al-Anon', '12-step-meeting-list'),
				'AL' => __('Concurrent with Alateen', '12-step-meeting-list'),
				'XT' => __('Cross Talk Permitted', '12-step-meeting-list'),
				'DR' => __('Daily Reflections', '12-step-meeting-list'),
				'DB' => __('Digital Basket', '12-step-meeting-list'),
				'D' => __('Discussion', '12-step-meeting-list'),
				'DD' => __('Dual Diagnosis', '12-step-meeting-list'),
				'EN' => __('English', '12-step-meeting-list'),
				'FF' => __('Fragrance Free', '12-step-meeting-list'),
				'FR' => __('French', '12-step-meeting-list'),
				'G' => __('Gay', '12-step-meeting-list'),
				'GR' => __('Grapevine', '12-step-meeting-list'),
				'HE' => __('Hebrew', '12-step-meeting-list'),
				'NDG' => __('Indigenous', '12-step-meeting-list'),
				'ITA' => __('Italian', '12-step-meeting-list'),
				'JA' => __('Japanese', '12-step-meeting-list'),
				'KOR' => __('Korean', '12-step-meeting-list'),
				'L' => __('Lesbian', '12-step-meeting-list'),
				'LIT' => __('Literature', '12-step-meeting-list'),
				'LS' => __('Living Sober', '12-step-meeting-list'),
				'LGBTQ' => __('LGBTQ', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'MED' => __('Meditation', '12-step-meeting-list'),
				'M' => __('Men', '12-step-meeting-list'),
				'N' => __('Native American', '12-step-meeting-list'),
				'BE' => __('Newcomer', '12-step-meeting-list'),
				'NB' => __('Non-Binary', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'O' => __('Open', '12-step-meeting-list'),
				'OUT' => __('Outdoor Meeting', '12-step-meeting-list'),
				'POC' => __('People of Color', '12-step-meeting-list'),
				'POL' => __('Polish', '12-step-meeting-list'),
				'POR' => __('Portuguese', '12-step-meeting-list'),
				'P' => __('Professionals', '12-step-meeting-list'),
				'PUN' => __('Punjabi', '12-step-meeting-list'),
				'RUS' => __('Russian', '12-step-meeting-list'),
				'A' => __('Secular', '12-step-meeting-list'),
				'SEN' => __('Seniors', '12-step-meeting-list'),
				'ASL' => __('Sign Language', '12-step-meeting-list'),
				'SM' => __('Smoking Permitted', '12-step-meeting-list'),
				'S' => __('Spanish', '12-step-meeting-list'),
				'SP' => __('Speaker', '12-step-meeting-list'),
				'ST' => __('Step Meeting', '12-step-meeting-list'),				
				'TR' => __('Tradition Study', '12-step-meeting-list'),
				'T' => __('Transgender', '12-step-meeting-list'),
				'X' => __('Wheelchair Access', '12-step-meeting-list'),
				'XB' => __('Wheelchair-Accessible Bathroom', '12-step-meeting-list'),
				'W' => __('Women', '12-step-meeting-list'),
				'Y' => __('Young People', '12-step-meeting-list'),
			),
		),
		'coda' => array(
			'abbr' => __('CoDA', '12-step-meeting-list'),
			'flags' => array('M', 'W', 'TC', 'ONL'), //for /men and /women at end of meeting name (used in tsml_format_name())
			'name' => __('Co-Dependents Anonymous', '12-step-meeting-list'),
			'types' => array(
				'A' => __('Atheist / Agnostic', '12-step-meeting-list'),
				'BA' => __('Babysitting Available', '12-step-meeting-list'),
				'BE' => __('Beginner', '12-step-meeting-list'),
				'B' => __('Book Study', '12-step-meeting-list'),
				'CF' => __('Child-Friendly', '12-step-meeting-list'),
				'H' => __('Chips', '12-step-meeting-list'),
				'C' => __('Closed', '12-step-meeting-list'),
				'CAN' => __('Candlelight', '12-step-meeting-list'),
				'AL-AN' => __('Concurrent with Al-Anon', '12-step-meeting-list'),
				'AL' => __('Concurrent with Alateen', '12-step-meeting-list'),
				'XT' => __('Cross Talk Permitted', '12-step-meeting-list'),
				'DLY' => __('Daily', '12-step-meeting-list'),
				'FF' => __('Fragrance Free', '12-step-meeting-list'),
				'G' => __('Gay', '12-step-meeting-list'),
				'GR' => __('Grapevine', '12-step-meeting-list'),
				'L' => __('Lesbian', '12-step-meeting-list'),
				'LIT' => __('Literature', '12-step-meeting-list'),
				'LGBTQ' => __('LGBTQ', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'MED' => __('Meditation', '12-step-meeting-list'),
				'M' => __('Men', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'O' => __('Open', '12-step-meeting-list'),
				'QA' => __('Q & A', '12-step-meeting-list'),
				'READ' => __('Reading', '12-step-meeting-list'),
				'SHARE' => __('Sharing', '12-step-meeting-list'),
				'ASL' => __('Sign Language', '12-step-meeting-list'),
				'SM' => __('Smoking Permitted', '12-step-meeting-list'),
				'S' => __('Spanish', '12-step-meeting-list'),
				'SP' => __('Speaker', '12-step-meeting-list'),
				'ST' => __('Step Meeting', '12-step-meeting-list'),
				'TEEN' => __('Teens', '12-step-meeting-list'),				
				'D' => __('Topic Discussion', '12-step-meeting-list'),
				'TR' => __('Tradition', '12-step-meeting-list'),
				'T' => __('Transgender', '12-step-meeting-list'),
				'X' => __('Wheelchair Accessible', '12-step-meeting-list'),
				'W' => __('Women', '12-step-meeting-list'),
				'WRITE' => __('Writing', '12-step-meeting-list'),
				'Y' => __('Young People', '12-step-meeting-list'),
			),
		),
		'ca' => array(
			'abbr' => __('CA', '12-step-meeting-list'),
			'flags' => array('M', 'W', 'TC', 'ONL'), //for /men and /women at end of meeting name (used in tsml_format_name())
			'name' => __('Cocaine Anonymous', '12-step-meeting-list'),
			'type_descriptions' => array(
				'C' => __('This meeting is closed; only those who have a desire to stop using may attend.', '12-step-meeting-list'),
				'O' => __('This meeting is open and anyone may attend.', '12-step-meeting-list'),
			),
			'types' => array(
				'11' => __('11th Step Meditation', '12-step-meeting-list'),
				'12x12' => __('12 Steps & 12 Traditions', '12-step-meeting-list'),
				'ABSI' => __('As Bill Sees It', '12-step-meeting-list'),
				'A' => __('Atheist / Agnostic', '12-step-meeting-list'),
				'BA' => __('Babysitting Available', '12-step-meeting-list'),
				'B' => __('Big Book', '12-step-meeting-list'),
				'H' => __('Birthday', '12-step-meeting-list'),
				'BRK' => __('Breakfast', '12-step-meeting-list'),
				'BUS' => __('Business', '12-step-meeting-list'),
				'CF' => __('Child-Friendly', '12-step-meeting-list'),
				'C' => __('Closed', '12-step-meeting-list'),
				'CAN' => __('Candlelight', '12-step-meeting-list'),
				'AL-AN' => __('Concurrent with Al-Anon', '12-step-meeting-list'),
				'AL' => __('Concurrent with Alateen', '12-step-meeting-list'),
				'XT' => __('Cross Talk Permitted', '12-step-meeting-list'),
				'DR' => __('Daily Reflections', '12-step-meeting-list'),
				'D' => __('Discussion', '12-step-meeting-list'),
				'DD' => __('Dual Diagnosis', '12-step-meeting-list'),
				'EN' => __('English', '12-step-meeting-list'),
				'FF' => __('Fragrance Free', '12-step-meeting-list'),
				'FR' => __('French', '12-step-meeting-list'),
				'G' => __('Gay', '12-step-meeting-list'),
				'GR' => __('Grapevine', '12-step-meeting-list'),
				'ITA' => __('Italian', '12-step-meeting-list'),
				'KOR' => __('Korean', '12-step-meeting-list'),
				'L' => __('Lesbian', '12-step-meeting-list'),
				'LIT' => __('Literature', '12-step-meeting-list'),
				'LS' => __('Living Sober', '12-step-meeting-list'),
				'LGBTQ' => __('LGBTQ', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'MED' => __('Meditation', '12-step-meeting-list'),
				'M' => __('Men', '12-step-meeting-list'),
				'N' => __('Native American', '12-step-meeting-list'),
				'BE' => __('Newcomer', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'O' => __('Open', '12-step-meeting-list'),
				'POL' => __('Polish', '12-step-meeting-list'),
				'POR' => __('Portuguese', '12-step-meeting-list'),
				'PUN' => __('Punjabi', '12-step-meeting-list'),
				'RUS' => __('Russian', '12-step-meeting-list'),
				'ASL' => __('Sign Language', '12-step-meeting-list'),
				'SM' => __('Smoking Permitted', '12-step-meeting-list'),
				'S' => __('Spanish', '12-step-meeting-list'),
				'SP' => __('Speaker', '12-step-meeting-list'),
				'ST' => __('Step Meeting', '12-step-meeting-list'),			
				'TR' => __('Tradition Study', '12-step-meeting-list'),
				'T' => __('Transgender', '12-step-meeting-list'),
				'X' => __('Wheelchair Access', '12-step-meeting-list'),
				'W' => __('Women', '12-step-meeting-list'),
				'Y' => __('Young People', '12-step-meeting-list'),
			),
		),
		'cea-how' => array(
			'abbr' => __('CEA-HOW', '12-step-meeting-list'),
			'flags' => array(), //for /men and /women at end of meeting name (used in tsml_format_name())
			'name' => __('Compulsive Eaters Anonymous-HOW', '12-step-meeting-list'),
			'types' => array(
				'12x12' => __('12 Steps & 12 Traditions', '12-step-meeting-list'),
				'AACOA' => __('AA Comes of Age', '12-step-meeting-list'),
				'ABSI' => __('As Bill Sees It', '12-step-meeting-list'),
				'B' => __('Big Book', '12-step-meeting-list'),
				'BOOK' => __('Book Study', '12-step-meeting-list'),
				'CTB' => __('Came to Believe', '12-step-meeting-list'),
				'CEA-HOW' => __('CEA-HOW Concept/Tools', '12-step-meeting-list'),
				'DR' => __('Daily Reflections', '12-step-meeting-list'),
				'HJF' => __('Happy Joyous and Free', '12-step-meeting-list'),
				'LS' => __('Living Sober', '12-step-meeting-list'),
				'MAINT' => __('Maintenance', '12-step-meeting-list'),
				'MED' => __('Meditation', '12-step-meeting-list'),
				'SP' => __('Pitch/Speaker', '12-step-meeting-list'),
				'PROM' => __('Promises', '12-step-meeting-list'),
				'RANDR' => __('Relapse and Recovery', '12-step-meeting-list'),
				'ST' => __('Steps/Traditions', '12-step-meeting-list'),				
				'D' => __('Topic/Discussion', '12-step-meeting-list'),
			),
		),
		'da' => array(
			'abbr' => __('DA', '12-step-meeting-list'),
			'flags' => array('M', 'W', 'TC', 'ONL'), //for /men and /women at end of meeting name (used in tsml_format_name())
			'name' => __('Debtors Anonymous', '12-step-meeting-list'),
			'types' => array(
				'AB' => __('Abundance', '12-step-meeting-list'),
				'AR' => __('Artist', '12-step-meeting-list'),
				'B' => __('Business Owner', '12-step-meeting-list'),
				'C' => __('Closed', '12-step-meeting-list'),
				'CL' => __('Clutter', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'M' => __('Men', '12-step-meeting-list'),
				'N' => __('Numbers', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'O' => __('Open', '12-step-meeting-list'),
				'P' => __('Prosperity', '12-step-meeting-list'),
				'SP' => __('Speaker', '12-step-meeting-list'),
				'ST' => __('Step Study', '12-step-meeting-list'),				
				'TI' => __('Time', '12-step-meeting-list'),
				'TO' => __('Toolkit', '12-step-meeting-list'),
				'V' => __('Vision', '12-step-meeting-list'),
				'X' => __('Wheelchair Accessible', '12-step-meeting-list'),
				'W' => __('Women', '12-step-meeting-list'),
			),
		),
		'daa' => array(
			'abbr' => __('DAA', '12-step-meeting-list'),
			'flags' => array('M', 'W', 'TC', 'ONL'), //for /men and /women at end of meeting name (used in tsml_format_name())
			'name' => __('Drug Addicts Anonymous', '12-step-meeting-list'),
			'types' => array(
				'12x12' => __('12 Steps & 12 Traditions', '12-step-meeting-list'),
				'BA' => __('Babysitting Available', '12-step-meeting-list'),
				'B' => __('Big Book Study', '12-step-meeting-list'),
				'C' => __('Closed', '12-step-meeting-list'),
				'D' => __('Discussion', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'M' => __('Men', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'O' => __('Open', '12-step-meeting-list'),
				'ST' => __('Step Meeting', '12-step-meeting-list'),
				'SS' => __('Step Speaker', '12-step-meeting-list'),			
				'W' => __('Women', '12-step-meeting-list'),
				'YP' => __('Young People', '12-step-meeting-list'),
			),
		),
		'ga' => array(
			'abbr' => __('GA', '12-step-meeting-list'),
			'flags' => array('M', 'W', 'TC', 'ONL'), //for /men and /women at end of meeting name (used in tsml_format_name())
			'name' => __('Gamblers Anonymous', '12-step-meeting-list'),
			'type_descriptions' => array(
				'C' => __('This meeting is closed; only those who have a desire to stop gambling may attend.', '12-step-meeting-list'),
			),
			'types' => array(
				'20' => __('20 Questions/Beginner Focus', '12-step-meeting-list'),
				'BA' => __('Babysitting Available', '12-step-meeting-list'),
				'B' => __('Big Book', '12-step-meeting-list'),
				'CAN' => __('Candlelight', '12-step-meeting-list'),
				'CF' => __('Child-Friendly', '12-step-meeting-list'),
				'C' => __('Closed', '12-step-meeting-list'),
				'GAA' => __('Combined GA/Gam Anon', '12-step-meeting-list'),
				'COM' => __('Comment', '12-step-meeting-list'),
				'CRC' => __('Cross Comment', '12-step-meeting-list'),
				'DR' => __('Daily Reflections', '12-step-meeting-list'),
				'D' => __('Discussion', '12-step-meeting-list'),
				'EN' => __('English', '12-step-meeting-list'),
				'FR' => __('French', '12-step-meeting-list'),
				'GAM' => __('Gam Anon', '12-step-meeting-list'),
				'ITA' => __('Italian', '12-step-meeting-list'),
				'KOR' => __('Korean', '12-step-meeting-list'),
				'LIT' => __('Literature', '12-step-meeting-list'),
				'LGBTQ' => __('LGBTQ', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'MED' => __('Meditation', '12-step-meeting-list'),
				'M' => __('Men', '12-step-meeting-list'),
				'BE' => __('Newcomer', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'O' => __('Open', '12-step-meeting-list'),
				'PAR' => __('Parking Meters Available', '12-step-meeting-list'),
				'POL' => __('Polish', '12-step-meeting-list'),
				'POR' => __('Portuguese', '12-step-meeting-list'),
				'PUN' => __('Punjabi', '12-step-meeting-list'),
				'RUS' => __('Russian', '12-step-meeting-list'),
				'ASL' => __('Sign Language', '12-step-meeting-list'),
				'SM' => __('Smoking Permitted', '12-step-meeting-list'),
				'S' => __('Spanish', '12-step-meeting-list'),
				'SP' => __('Speaker', '12-step-meeting-list'),
				'ST' => __('Step Meeting', '12-step-meeting-list'),				
				'TOP' => __('Topic', '12-step-meeting-list'),
				'TR' => __('Tradition Study', '12-step-meeting-list'),
				'X' => __('Wheelchair Access', '12-step-meeting-list'),
				'W' => __('Women', '12-step-meeting-list'),
				'Y' => __('Young People', '12-step-meeting-list'),
			),
		),
		'ha' => array(
			'abbr' => __('HA', '12-step-meeting-list'),
			'flags' => array('M', 'W', 'TC', 'ONL'), //for /men and /women at end of meeting name (used in tsml_format_name())
			'name' => __('Heroin Anonymous', '12-step-meeting-list'),
			'types' => array(
				'CPT' => __('12 Concepts', '12-step-meeting-list'),
				'BT' => __('Basic Text', '12-step-meeting-list'),
				'BEG' => __('Beginner/Newcomer', '12-step-meeting-list'),
				'CAN' => __('Candlelight', '12-step-meeting-list'),
				'CW' => __('Children Welcome', '12-step-meeting-list'),
				'C' => __('Closed', '12-step-meeting-list'),
				'DISC' => __('Discussion/Participation', '12-step-meeting-list'),
				'GL' => __('Gay/Lesbian', '12-step-meeting-list'),
				'IP' => __('IP Study', '12-step-meeting-list'),
				'IW' => __('It Works Study', '12-step-meeting-list'),
				'JFT' => __('Just For Today Study', '12-step-meeting-list'),
				'LIT' => __('Literature Study', '12-step-meeting-list'),
				'LC' => __('Living Clean', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'M' => __('Men', '12-step-meeting-list'),
				'MED' => __('Meditation', '12-step-meeting-list'),
				'NS' => __('Non-Smoking', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'O' => __('Open', '12-step-meeting-list'),
				'QA' => __('Questions & Answers', '12-step-meeting-list'),
				'RA' => __('Restricted Access', '12-step-meeting-list'),
				'SMOK' => __('Smoking', '12-step-meeting-list'),
				'SPK' => __('Speaker', '12-step-meeting-list'),
				'STEP' => __('Step', '12-step-meeting-list'),
				'SWG' => __('Step Working Guide Study', '12-step-meeting-list'),				
				'TOP' => __('Topic', '12-step-meeting-list'),
				'TRAD' => __('Tradition', '12-step-meeting-list'),
				'VAR' => __('Format Varies', '12-step-meeting-list'),
				'X' => __('Wheelchair Accessible', '12-step-meeting-list'),
				'W' => __('Women', '12-step-meeting-list'),
				'Y' => __('Young People', '12-step-meeting-list'),
			),
		),
		'na' => array(
			'abbr' => __('NA', '12-step-meeting-list'),
			'flags' => array('M', 'W', 'TC', 'ONL'), //for /men and /women at end of meeting name (used in tsml_format_name())
			'name' => __('Narcotics Anonymous', '12-step-meeting-list'),
			'types' => array(
				'CPT' => __('12 Concepts', '12-step-meeting-list'),
				'BT' => __('Basic Text', '12-step-meeting-list'),
				'BEG' => __('Beginner/Newcomer', '12-step-meeting-list'),
				'CAN' => __('Candlelight', '12-step-meeting-list'),
				'CW' => __('Children Welcome', '12-step-meeting-list'),
				'C' => __('Closed', '12-step-meeting-list'),
				'DISC' => __('Discussion/Participation', '12-step-meeting-list'),
				'GL' => __('Gay/Lesbian', '12-step-meeting-list'),
				'IP' => __('IP Study', '12-step-meeting-list'),
				'IW' => __('It Works Study', '12-step-meeting-list'),
				'JFT' => __('Just For Today Study', '12-step-meeting-list'),
				'LIT' => __('Literature Study', '12-step-meeting-list'),
				'LC' => __('Living Clean', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'M' => __('Men', '12-step-meeting-list'),
				'MED' => __('Meditation', '12-step-meeting-list'),
				'NS' => __('Non-Smoking', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'O' => __('Open', '12-step-meeting-list'),
				'QA' => __('Questions & Answers', '12-step-meeting-list'),
				'RA' => __('Restricted Access', '12-step-meeting-list'),
				'SMOK' => __('Smoking', '12-step-meeting-list'),
				'SPK' => __('Speaker', '12-step-meeting-list'),
				'STEP' => __('Step', '12-step-meeting-list'),
				'SWG' => __('Step Working Guide Study', '12-step-meeting-list'),				
				'TOP' => __('Topic', '12-step-meeting-list'),
				'TRAD' => __('Tradition', '12-step-meeting-list'),
				'VAR' => __('Format Varies', '12-step-meeting-list'),
				'X' => __('Wheelchair Accessible', '12-step-meeting-list'),
				'W' => __('Women', '12-step-meeting-list'),
				'Y' => __('Young People', '12-step-meeting-list'),
			),
		),
		'oa' => array(
			'abbr' => __('OA', '12-step-meeting-list'),
			'flags' => array('TC', 'ONL'),
			'name' => __('Overeaters Anonymous', '12-step-meeting-list'),
			'types' => array(
				'11TH' => __('11th Step', '12-step-meeting-list'),
				'90D' => __('90 Day', '12-step-meeting-list'),
				'AA12' => __('AA 12/12', '12-step-meeting-list'),
				'AIB' => __('Ask-It-Basket', '12-step-meeting-list'),
				'B' => __('Big Book', '12-step-meeting-list'),
				'DOC' => __('Dignity of Choice', '12-step-meeting-list'),
				'FT' => __('For Today', '12-step-meeting-list'),
				'LI' => __('Lifeline', '12-step-meeting-list'),
				'LIS' => __('Lifeline Sampler', '12-step-meeting-list'),
				'LIT' => __('Literature Study', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'MAIN' => __('Maintenance', '12-step-meeting-list'),
				'MED' => __('Meditation', '12-step-meeting-list'),
				'NEWB' => __('New Beginnings', '12-step-meeting-list'),
				'BE' => __('Newcomer', '12-step-meeting-list'),
				'HOW' => __('OA H.O.W.', '12-step-meeting-list'),
				'OA23' => __('OA Second and/or Third Edition', '12-step-meeting-list'),
				'ST' => __('OA Steps and/or Traditions Study', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'RELA' => __('Relapse/12th Step Within', '12-step-meeting-list'),
				'SSP' => __('Seeking the Spiritual Path', '12-step-meeting-list'),
				'SP' => __('Speaker', '12-step-meeting-list'),
				'SD' => __('Speaker/Discussion', '12-step-meeting-list'),
				'SPIR' => __('Spirituality', '12-step-meeting-list'),
				'TEEN' => __('Teen Friendly', '12-step-meeting-list'),			
				'PROM' => __('The Promises', '12-step-meeting-list'),
				'TOOL' => __('Tools', '12-step-meeting-list'),
				'D' => __('Topic', '12-step-meeting-list'),
				'MISC' => __('Varies', '12-step-meeting-list'),
				'VOR' => __('Voices of Recovery', '12-step-meeting-list'),
				'WORK' => __('Work Book Study', '12-step-meeting-list'),
				'WRIT' => __('Writing', '12-step-meeting-list'),
			),	
		),
		'pal' => array(
			'abbr' => 'PAL',
			'flags' => array(),
			'name' => 'Parents of Addicted Loved Ones',
			'types' => array(),
		),
		'rca' => array(
			'abbr' => __('RCA', '12-step-meeting-list'),
			'flags' => array('TC', 'ONL'),
			'name' => __('Recovering Couples Anonymous', '12-step-meeting-list'),
			'types' => array(
				'C' => __('Closed', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'O' => __('Open', '12-step-meeting-list'),
				'SP' => __('Speaker', '12-step-meeting-list'),				
			),
		),
		'rd' => array(
			'abbr' => __('Recovery Dharma', '12-step-meeting-list'),
			'flags' => array('M', 'W', 'TC', 'ONL'), //for /men and /women at end of meeting name (used in tsml_format_name())
			'name' => __('Recovery Dharma', '12-step-meeting-list'),
			'type_descriptions' => array(
				'M' => __('Men’s meetings are for anyone who identifies as male.', '12-step-meeting-list'),
				'W' => __('Women’s meetings are for anyone who identifies as female.', '12-step-meeting-list'),
			),
			'types' => array(
				'BE' => __('Beginners', '12-step-meeting-list'),
				'BB' => __('Book Study', '12-step-meeting-list'),
				'CC' => __('Child Care Available', '12-step-meeting-list'),
				'C' => __('Closed', '12-step-meeting-list'),
				'DA' => __('Danish', '12-step-meeting-list'),
				'DF' => __('Dog Friendly', '12-step-meeting-list'),
				'NL' => __('Dutch', '12-step-meeting-list'),
				'8F' => __('Eightfold Path Study', '12-step-meeting-list'),
				'EN' => __('English', '12-step-meeting-list'),
				'FI' => __('Finnish', '12-step-meeting-list'),
				'FR' => __('French', '12-step-meeting-list'),
				'DE' => __('German', '12-step-meeting-list'),
				'IS' => __('Inquiry Study', '12-step-meeting-list'),
				'IW' => __('Inquiry Writing', '12-step-meeting-list'),
				'LGBTQ' => __('LGBTQ', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'M' => __('Men', '12-step-meeting-list'),
				'MI' => __('Mindfulness Practice', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'O' => __('Open', '12-step-meeting-list'),
				'PR' => __('Process Addictions', '12-step-meeting-list'),
				'ES' => __('Spanish', '12-step-meeting-list'),
				'SV' => __('Swedish', '12-step-meeting-list'),				
				'TH' => __('Thai', '12-step-meeting-list'),
				'WA' => __('Wheelchair Access', '12-step-meeting-list'),
				'W' => __('Women', '12-step-meeting-list'),
			),
		),
		'rr' => array(
			'abbr' => __('Refuge Recovery', '12-step-meeting-list'),
			'flags' => array('M', 'W', 'TC', 'ONL'), //for /men and /women at end of meeting name (used in tsml_format_name())
			'name' => __('Refuge Recovery', '12-step-meeting-list'),
			'type_descriptions' => array(
				'M' => __('Men’s meetings are for anyone who identifies as male.', '12-step-meeting-list'),
				'W' => __('Women’s meetings are for anyone who identifies as female.', '12-step-meeting-list'),
			),
			'types' => array(
				'BE' => __('Beginners', '12-step-meeting-list'),
				'BB' => __('Book Study', '12-step-meeting-list'),
				'CC' => __('Child Care Available', '12-step-meeting-list'),
				'C' => __('Closed', '12-step-meeting-list'),
				'DA' => __('Danish', '12-step-meeting-list'),
				'DF' => __('Dog Friendly', '12-step-meeting-list'),
				'NL' => __('Dutch', '12-step-meeting-list'),
				'8F' => __('Eightfold Path Study', '12-step-meeting-list'),
				'EN' => __('English', '12-step-meeting-list'),
				'FI' => __('Finnish', '12-step-meeting-list'),
				'FR' => __('French', '12-step-meeting-list'),
				'DE' => __('German', '12-step-meeting-list'),
				'IS' => __('Inventory Study', '12-step-meeting-list'),
				'IW' => __('Inventory Writing', '12-step-meeting-list'),
				'LGBTQ' => __('LGBTQ', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'M' => __('Men', '12-step-meeting-list'),
				'MI' => __('Mindfulness Practice', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'O' => __('Open', '12-step-meeting-list'),
				'PR' => __('Process Addictions', '12-step-meeting-list'),
				'ES' => __('Spanish', '12-step-meeting-list'),
				'SV' => __('Swedish', '12-step-meeting-list'),			
				'TH' => __('Thai', '12-step-meeting-list'),
				'WA' => __('Wheelchair Access', '12-step-meeting-list'),
				'W' => __('Women', '12-step-meeting-list'),
			),
		),
		'saa' => array(
			'abbr' => __('SAA', '12-step-meeting-list'),
			'flags' => array('M', 'W', 'TC', 'ONL'), //for /men and /women at end of meeting name (used in tsml_format_name())
			'name' => __('Sex Addicts Anonymous', '12-step-meeting-list'),
			'types' => array(
				'C' => __('Closed', '12-step-meeting-list'),
				'M' => __('Men', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'O' => __('Open', '12-step-meeting-list'),
				'ST' => __('Step Meeting', '12-step-meeting-list'),
				'LGBTQ' => __('LGBTQ', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),				
				'W' => __('Women', '12-step-meeting-list'),
			),
		),
		'sa' => array(
			'abbr' => __('SA', '12-step-meeting-list'),
			'flags' => array('M', 'W', 'TC', 'ONL'), //for /men and /women at end of meeting name (used in tsml_format_name())
			'name' => __('Sexaholics Anonymous', '12-step-meeting-list'),
			'types' => array(
				'BE' => __('Beginner', '12-step-meeting-list'),
				'B' => __('Book Study', '12-step-meeting-list'),
				'C' => __('Closed', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'MED' => __('Meditation', '12-step-meeting-list'),
				'M' => __('Men', '12-step-meeting-list'),
				'MI' => __('Mixed', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'O' => __('Open', '12-step-meeting-list'),
				'PP' => __('Primary Purpose', '12-step-meeting-list'),
				'SP' => __('Speaker', '12-step-meeting-list'),
				'ST' => __('Step Study', '12-step-meeting-list'),			
				'W' => __('Women', '12-step-meeting-list'),
			),
		),
		'sca' => array(
			'abbr' => __('SCA', '12-step-meeting-list'),
			'flags' => array('TC', 'ONL'),
			'name' => __('Sexual Compulsives Anonymous', '12-step-meeting-list'),
			'types' => array(
				'BE' => __('Beginner', '12-step-meeting-list'),
				'H' => __('Chip', '12-step-meeting-list'),
				'C' => __('Closed', '12-step-meeting-list'),
				'COURT' => __('Court', '12-step-meeting-list'),
				'D' => __('Discussion', '12-step-meeting-list'),
				'GL' => __('Graphic Language', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'O' => __('Open', '12-step-meeting-list'),
				'SP' => __('Speaker', '12-step-meeting-list'),
				'ST' => __('Step', '12-step-meeting-list'),				
			),
		),
		'slaa' => array(
			'abbr' => __('SLAA', '12-step-meeting-list'),
			'flags' => array('M', 'W', 'TC', 'ONL'), //for /men and /women at end of meeting name (used in tsml_format_name())
			'name' => __('Sex and Love Addicts Anonymous', '12-step-meeting-list'),
			'types' => array(
				'AN' => __('Anorexia Focus', '12-step-meeting-list'),
				'B' => __('Book Study', '12-step-meeting-list'),
				'H' => __('Chips', '12-step-meeting-list'),
				'BA' => __('Child Care Available', '12-step-meeting-list'),
				'C' => __('Closed', '12-step-meeting-list'),
				'FF' => __('Fragrance Free', '12-step-meeting-list'),
				'GC' => __('Getting Current', '12-step-meeting-list'),
				'X' => __('Handicapped Accessible', '12-step-meeting-list'),
				'HR' => __('Healthy Relationships', '12-step-meeting-list'),
				'LIT' => __('Literature Reading', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'MED' => __('Meditation', '12-step-meeting-list'),
				'M' => __('Men', '12-step-meeting-list'),
				'NC' => __('Newcomers', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'O' => __('Open', '12-step-meeting-list'),
				'PRI' => __('Prison', '12-step-meeting-list'),
				'S' => __('Spanish', '12-step-meeting-list'),
				'SP' => __('Speaker', '12-step-meeting-list'),
				'ST' => __('Step Study', '12-step-meeting-list'),
				'D' => __('Topic Discussion', '12-step-meeting-list'),			
				'TR' => __('Tradition Study', '12-step-meeting-list'),
				'W' => __('Women', '12-step-meeting-list'),
			),
		),
		'sg' => array(
			'flags' => array(),
			'name' => 'Support Groups',
			'types' => array(),
		),
		'va' => array(
			'abbr' => __('VA', '12-step-meeting-list'),
			'flags' => array('M', 'W', 'TC', 'ONL'), //for /men and /women at end of meeting name (used in tsml_format_name())
			'name' => __('Violence Anonymous', '12-step-meeting-list'),
			'types' => array(
				'12x12' => __('12 Steps & 12 Traditions', '12-step-meeting-list'),
				'C' => __('Closed', '12-step-meeting-list'),
				'BE' => __('Newcomer', '12-step-meeting-list'),
				'TC' => __('Location Temporarily Closed', '12-step-meeting-list'),
				'ONL' => __('Online Meeting', '12-step-meeting-list'),
				'O' => __('Open', '12-step-meeting-list'),				
			),
		),
	);

	//the location where the list will show up, eg https://intergroup.org/meetings
	if ($tsml_slug === null) {
		$tsml_slug = sanitize_title(__('meetings', '12-step-meeting-list'));
	}

	//strings that must be synced between the javascript and the PHP
	$tsml_strings = array(
		'data_error' => __('Got an improper response from the server, try refreshing the page.', '12-step-meeting-list'),
		'email_not_sent' => __('Email was not sent.', '12-step-meeting-list'),
		'loc_empty' => __('Enter a location in the field above.', '12-step-meeting-list'),
		'loc_error' => __('Google could not find that location.', '12-step-meeting-list'),
		'loc_thinking' => __('Looking up address…', '12-step-meeting-list'),
		'geo_error' => __('There was an error getting your location.', '12-step-meeting-list'),
		'geo_error_browser' => __('Your browser does not appear to support geolocation.', '12-step-meeting-list'),
		'geo_thinking' => __('Finding you…', '12-step-meeting-list'),
		'groups' => __('Groups', '12-step-meeting-list'),
		'locations' => __('Locations', '12-step-meeting-list'),
		'meetings' => __('Meetings', '12-step-meeting-list'),
		'men' => __('Men', '12-step-meeting-list'),
		'no_meetings' => __('No meetings were found matching the selected criteria.', '12-step-meeting-list'),
		'regions' => __('Regions', '12-step-meeting-list'),
		'women' => __('Women', '12-step-meeting-list'),
	);

	$tsml_types_in_use = get_option('tsml_types_in_use', array());
	if (!is_array($tsml_types_in_use)) $tsml_types_in_use = array();
}
