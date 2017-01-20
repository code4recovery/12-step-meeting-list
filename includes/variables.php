<?php
/*	
don't make changes! it'll make staying updated much harder.
for updates / questions, please contact wordpress@meetingguide.org
*/

$tsml_alerts = array();

$tsml_days	= array(
	0 => array(0=>__('Sunday', '12-step-meeting-list'), 1=>__('Monday', '12-step-meeting-list'), 2=>__('Tuesday', '12-step-meeting-list'), 3=>__('Wednesday', '12-step-meeting-list'), 4=>__('Thursday', '12-step-meeting-list'), 5=>__('Friday', '12-step-meeting-list'), 6=>__('Saturday', '12-step-meeting-list')),
	1 => array(1=>__('Monday', '12-step-meeting-list'), 2=>__('Tuesday', '12-step-meeting-list'), 3=>__('Wednesday', '12-step-meeting-list'), 4=>__('Thursday', '12-step-meeting-list'), 5=>__('Friday', '12-step-meeting-list'), 6=>__('Saturday', '12-step-meeting-list'), 0=>__('Sunday', '12-step-meeting-list')),
	2 => array(2=>__('Tuesday', '12-step-meeting-list'), 3=>__('Wednesday', '12-step-meeting-list'), 4=>__('Thursday', '12-step-meeting-list'), 5=>__('Friday', '12-step-meeting-list'), 6=>__('Saturday', '12-step-meeting-list'), 0=>__('Sunday', '12-step-meeting-list'), 1=>__('Monday', '12-step-meeting-list')),
	3 => array(3=>__('Wednesday', '12-step-meeting-list'), 4=>__('Thursday', '12-step-meeting-list'), 5=>__('Friday', '12-step-meeting-list'), 6=>__('Saturday', '12-step-meeting-list'), 0=>__('Sunday', '12-step-meeting-list'), 1=>__('Monday', '12-step-meeting-list'), 2=>__('Tuesday', '12-step-meeting-list')),
	4 => array(4=>__('Thursday', '12-step-meeting-list'), 5=>__('Friday', '12-step-meeting-list'), 6=>__('Saturday', '12-step-meeting-list'), 0=>__('Sunday', '12-step-meeting-list'), 1=>__('Monday', '12-step-meeting-list'), 2=>__('Tuesday', '12-step-meeting-list'), 3=>__('Wednesday', '12-step-meeting-list')),
	5 => array(5=>__('Friday', '12-step-meeting-list'), 6=>__('Saturday', '12-step-meeting-list'), 0=>__('Sunday', '12-step-meeting-list'), 1=>__('Monday', '12-step-meeting-list'), 2=>__('Tuesday', '12-step-meeting-list'), 3=>__('Wednesday', '12-step-meeting-list'), 4=>__('Thursday', '12-step-meeting-list')),
	6 => array(6=>__('Saturday', '12-step-meeting-list'), 0=>__('Sunday', '12-step-meeting-list'), 1=>__('Monday', '12-step-meeting-list'), 2=>__('Tuesday', '12-step-meeting-list'), 3=>__('Wednesday', '12-step-meeting-list'), 4=>__('Thursday', '12-step-meeting-list'), 5=>__('Friday', '12-step-meeting-list')),
);

$tsml_days = $tsml_days[get_option('start_of_week', 0)];

$tsml_days_order = array_keys($tsml_days); //used by tsml_meetings_sort() over and over

$tsml_distance_units = get_option('tsml_distance_units', 'mi');

$tsml_feedback_addresses = get_option('tsml_feedback_addresses', array());

$tsml_google_api_key = 'AIzaSyCC3p6PSf6iQbXi-Itwn9C24_FhkbDUkdg'; //might have to make this user-specific

/*
unfortunately the google geocoding API is not perfect. used by tsml_import() and admin.js
it's useful to use the Places API to find the correct information. For example, start with the Google API
https://maps.googleapis.com/maps/api/geocode/json?key=AIzaSyCC3p6PSf6iQbXi-Itwn9C24_FhkbDUkdg&address=320%20Beach%2094th%20St,%20Rockaway%20Beach,%20NY%2011693
and then search by location name near the coordinates	
https://maps.googleapis.com/maps/api/place/nearbysearch/json?key=AIzaSyAtTOBvcG7UrGE2Cz5xYIwI_yHjWvxlN8o&location=40.5854777,-73.81639299999999&name=first+congregational+church
ooorrrr use this: http://www.gps-coordinates.net/
*/
$tsml_google_overrides = array(
	//first congregational church
	'Beach 94th St, Queens, NY 11693, USA' => array(
		'formatted_address'	=> '320 Beach 94th Street, Queens, NY 11693, US',
		'latitude'			=> '40.587465',
		'longitude'			=> '-73.81683149999999',
	),
	//franklin memorial hospital
	'Farmington, ME, USA' => array(
		'formatted_address'	=> '111 Franklin Health Commons, Farmington, ME 04938, US',
		'latitude'			=> '44.62654999999999',
		'longitude'			=> '-70.162092',
	),
	//maine va medical center
	'Augusta, ME 04330, USA' => array(
		'formatted_address'	=> '1 VA Center, Augusta, ME 04330, US',
		'latitude'			=> '44.2803692',
		'longitude'			=> '-69.7042675',
	),
	//toronto meeting that is showing up with zero_results
	'519 Church St, Toronto, ON M4Y 2C9, Canada' => array(
		'formatted_address'	=> '519 Church St, Toronto, ON M4Y 2C9, Canada',
		'latitude'			=> '43.666532',
		'longitude'			=> '-79.38097',
	),
	//nyc locations that for some reason include the premise name
	'Advent Lutheran Church, 2504 Broadway, New York, NY 10025, USA' => array(
		'formatted_address'	=> '2504 Broadway, New York, NY 10025, USA',
		'latitude'			=> '40.7926923',
		'longitude'			=> '-73.9726924',
	),
	'St. Thomas More\'s Church, 65 E 89th St, New York, NY 10128, USA' => array(
		'formatted_address'	=> '65 E 89th St, New York, NY 10128, USA',
		'latitude'			=> '40.7827448',
		'longitude'			=> '-73.9567008',
	),
	'St. Catherine of Siena\'s Church, 411 E 68th St, New York, NY 10065, USA' => array(
		'formatted_address'	=> '411 E 68th St, New York, NY 10065, USA',
		'latitude'			=> '40.7652978',
		'longitude'			=> '-73.9570329',
	),
	'Our Lady of Good Counsel Church, 230 E 90th St, New York, NY 10128, USA' => array(
		'formatted_address'	=> '230 E 90th St, New York, NY 10128, USA',
		'latitude'			=> '40.7806471',
		'longitude'			=> '-73.9509674',
	),
	'Church of Our Lady of Guadalupe, 229 W 14th St, New York, NY 10011, USA' => array(
		'formatted_address'	=> '229 W 14th St, New York, NY 10011, USA',
		'latitude'			=> '40.7393643',
		'longitude'			=> '-74.00081270000001',
	),
	'Westlands, 1 Mead Way, Bronxville, NY 10708, USA' => array(
		'formatted_address'	=> '1 Mead Way, Bronxville, NY 10708, USA',
		'latitude'			=> '40.935443',
		'longitude'			=> '-73.8437546',
	),
	'St. Andrew\'s Church, 20 Cardinal Hayes Pl, New York, NY 10007, USA' => array(
		'formatted_address'	=> '519 Church St, Toronto, ON M4Y 2C9, Canada',
		'latitude'			=> '40.7133468',
		'longitude'			=> '-74.0025814',
	),
	'150 Church St, Santa Cruz, CA 95060, USA' => array(
		'formatted_address'	=> '150 Church St, Davenport, CA 95017, USA',
		'latitude'			=> '37.012471',
		'longitude'			=> '-122.192971',
	),
);

$tsml_nonce = plugin_basename(__FILE__);

$tsml_notification_addresses = get_option('tsml_notification_addresses', array());

$tsml_program = get_option('tsml_program', 'aa');

$tsml_programs = array(
	'al-anon'	=> __('Al-Anon', '12-step-meeting-list'),
	'aa'			=> __('Alcoholics Anonymous', '12-step-meeting-list'),
	'coda'		=> __('Co-Dependents Anonymous', '12-step-meeting-list'),
	'na'			=> __('Narcotics Anonymous', '12-step-meeting-list'),
	'oa'			=> __('Overeaters Anonymous', '12-step-meeting-list'),
	'rca'		=> __('Recovering Couples Anonymous', '12-step-meeting-list'),
	'sa'			=> __('Sexaholics Anonymous', '12-step-meeting-list'),
	'saa'		=> __('Sex Addicts Anonymous', '12-step-meeting-list'),
	'sca'		=> __('Sexual Compulsives Anonymous', '12-step-meeting-list'),
	'slaa'		=> __('Sex and Love Addicts Anonymous', '12-step-meeting-list'),
);

//strings that must be synced between the javascript and the PHP
$tsml_strings = array(
	'email_not_sent'		=> __('Email was not sent.', '12-step-meeting-list'),
	'loc_empty'			=> __('Enter a location in the field above.', '12-step-meeting-list'),
	'loc_error'			=> __('Google could not find that location.', '12-step-meeting-list'),
	'loc_thinking'		=> __('Looking up address…', '12-step-meeting-list'),
	'geo_error'			=> __('There was an error getting your location.', '12-step-meeting-list'),
	'geo_error_browser'	=> __('Your browser does not appear to support geolocation.', '12-step-meeting-list'),
	'geo_thinking'		=> __('Finding you…', '12-step-meeting-list'),
	'groups'				=> __('Groups', '12-step-meeting-list'),
	'locations'			=> __('Locations', '12-step-meeting-list'),
	'meetings'			=> __('Meetings', '12-step-meeting-list'),
	'men'				=> __('Men', '12-step-meeting-list'),
	'no_meetings'		=> __('No meetings were found matching the selected criteria.', '12-step-meeting-list'),
	'regions'			=> __('Regions', '12-step-meeting-list'),
	'women'				=> __('Women', '12-step-meeting-list'),
);

$tsml_timestamp = microtime(true);

$tsml_type_descriptions = array(
	'aa' => array(
		'C' => __('This meeting is closed; only those who have a desire to stop drinking may attend.', '12-step-meeting-list'),
		'O' => __('This meeting is open and anyone may attend.', '12-step-meeting-list'),
	),
	'al-anon' => array(
		'C' => __('Closed Meetings are limited to members and prospective members. These are persons who feel their lives have been or are being affected by alcoholism in a family member or friend.', '12-step-meeting-list'),
		'O' => __('Open to anyone interested in the family disease of alcoholism. Some groups invite members of the professional community to hear how the Al-Anon program aids in recovery.', '12-step-meeting-list'),
	),
);

$tsml_types = array(
	'aa' => array(
		'A'		=> __('Atheist / Agnostic', '12-step-meeting-list'),
		'BA'	=> __('Babysitting Available', '12-step-meeting-list'),
		'BE'	=> __('Beginner', '12-step-meeting-list'),
		'B'		=> __('Big Book', '12-step-meeting-list'),
		'CF'	=> __('Child-Friendly', '12-step-meeting-list'),
		'H'		=> __('Chips', '12-step-meeting-list'),
		'C'		=> __('Closed', '12-step-meeting-list'),
		'CAN'	=> __('Candlelight', '12-step-meeting-list'),
		'AL-AN'	=> __('Concurrent with Al-Anon', '12-step-meeting-list'),
		'AL'	=> __('Concurrent with Alateen', '12-step-meeting-list'),
		'XT'	=> __('Cross Talk Permitted', '12-step-meeting-list'),
		'DLY'	=> __('Daily', '12-step-meeting-list'),
		'DD'	=> __('Dual Diagnosis', '12-step-meeting-list'),
		'FF'	=> __('Fragrance Free', '12-step-meeting-list'),
		'FR'	=> __('French', '12-step-meeting-list'),
		'G'		=> __('Gay', '12-step-meeting-list'),
		'GR'	=> __('Grapevine', '12-step-meeting-list'),
		'ITA'	=> __('Italian', '12-step-meeting-list'),
		'L'		=> __('Lesbian', '12-step-meeting-list'),
		'LIT'	=> __('Literature', '12-step-meeting-list'),
		'LGBTQ'	=> __('LGBTQ', '12-step-meeting-list'),
		'MED'	=> __('Meditation', '12-step-meeting-list'),
		'M'		=> __('Men', '12-step-meeting-list'),
		'O'		=> __('Open', '12-step-meeting-list'),
		'POL'	=> __('Polish', '12-step-meeting-list'),
		'POR'	=> __('Portuguese', '12-step-meeting-list'),
		'PUN'	=> __('Punjabi', '12-step-meeting-list'),
		'RUS'	=> __('Russian', '12-step-meeting-list'),
		'ASL'	=> __('Sign Language', '12-step-meeting-list'),
		'SM'	=> __('Smoking Permitted', '12-step-meeting-list'),
		'S'		=> __('Spanish', '12-step-meeting-list'),
		'SP'	=> __('Speaker', '12-step-meeting-list'),
		'ST'	=> __('Step Meeting', '12-step-meeting-list'),
		'D'		=> __('Topic Discussion', '12-step-meeting-list'),
		'TR'	=> __('Tradition', '12-step-meeting-list'),
		'T'		=> __('Transgender', '12-step-meeting-list'),
		'X'		=> __('Wheelchair Accessible', '12-step-meeting-list'),
		'W'		=> __('Women', '12-step-meeting-list'),
		'Y'		=> __('Young People', '12-step-meeting-list'),
	),
	'al-anon' => array(
		'AC'	=> __('Adult Child Focus', '12-step-meeting-list'),
		'Y'		=> __('Alateen', '12-step-meeting-list'),
		'A'		=> __('Atheist / Agnostic', '12-step-meeting-list'),
		'BA'	=> __('Babysitting Available', '12-step-meeting-list'),
		'BE'	=> __('Beginner', '12-step-meeting-list'),
		'C'		=> __('Closed', '12-step-meeting-list'),
		'AA'	=> __('Concurrent with AA Meeting', '12-step-meeting-list'),
		'AL'	=> __('Concurrent with Alateen Meeting', '12-step-meeting-list'),
		'FF'	=> __('Fragrance Free', '12-step-meeting-list'),
		'G'		=> __('Gay', '12-step-meeting-list'),
		'L'		=> __('Lesbian', '12-step-meeting-list'),
		'M'		=> __('Men', '12-step-meeting-list'),
		'O'		=> __('Open', '12-step-meeting-list'),
		'S'		=> __('Spanish', '12-step-meeting-list'),
		'SP'	=> __('Speaker', '12-step-meeting-list'),
		'ST'	=> __('Step Meeting', '12-step-meeting-list'),
		'T'		=> __('Transgender', '12-step-meeting-list'),
		'X'		=> __('Wheelchair Accessible', '12-step-meeting-list'),
		'W'		=> __('Women', '12-step-meeting-list'),
	),
	'coda' => array(
		'A'		=> __('Atheist / Agnostic', '12-step-meeting-list'),
		'BA'	=> __('Babysitting Available', '12-step-meeting-list'),
		'BE'	=> __('Beginner', '12-step-meeting-list'),
		'B'		=> __('Book Study', '12-step-meeting-list'),
		'CF'	=> __('Child-Friendly', '12-step-meeting-list'),
		'H'		=> __('Chips', '12-step-meeting-list'),
		'C'		=> __('Closed', '12-step-meeting-list'),
		'CAN'	=> __('Candlelight', '12-step-meeting-list'),
		'AL-AN'	=> __('Concurrent with Al-Anon', '12-step-meeting-list'),
		'AL'	=> __('Concurrent with Alateen', '12-step-meeting-list'),
		'XT'	=> __('Cross Talk Permitted', '12-step-meeting-list'),
		'DLY'	=> __('Daily', '12-step-meeting-list'),
		'FF'	=> __('Fragrance Free', '12-step-meeting-list'),
		'G'		=> __('Gay', '12-step-meeting-list'),
		'GR'	=> __('Grapevine', '12-step-meeting-list'),
		'L'		=> __('Lesbian', '12-step-meeting-list'),
		'LIT'	=> __('Literature', '12-step-meeting-list'),
		'LGBTQ'	=> __('LGBTQ', '12-step-meeting-list'),
		'MED'	=> __('Meditation', '12-step-meeting-list'),
		'M'		=> __('Men', '12-step-meeting-list'),
		'O'		=> __('Open', '12-step-meeting-list'),
		'QA'	=> __('Q & A', '12-step-meeting-list'),
		'READ' 	=> __('Reading', '12-step-meeting-list'),
		'SHARE'	=> __('Sharing', '12-step-meeting-list'),
		'ASL'	=> __('Sign Language', '12-step-meeting-list'),
		'SM'	=> __('Smoking Permitted', '12-step-meeting-list'),
		'S'		=> __('Spanish', '12-step-meeting-list'),
		'SP'	=> __('Speaker', '12-step-meeting-list'),
		'ST'	=> __('Step Meeting', '12-step-meeting-list'),
		'TEEN'	=> __('Teens', '12-step-meeting-list'),
		'D'		=> __('Topic Discussion', '12-step-meeting-list'),
		'TR'	=> __('Tradition', '12-step-meeting-list'),
		'T'		=> __('Transgender', '12-step-meeting-list'),
		'X'		=> __('Wheelchair Accessible', '12-step-meeting-list'),
		'W'		=> __('Women', '12-step-meeting-list'),
		'WRITE'	=> __('Writing', '12-step-meeting-list'),
		'Y'		=> __('Young People', '12-step-meeting-list'),
	),
	'na' => array(
		'CPT'	=> __('12 Concepts', '12-step-meeting-list'),
		'BT'	=> __('Basic Text', '12-step-meeting-list'),
		'BEG'	=> __('Beginner/Newcomer', '12-step-meeting-list'),
		'CAN'	=> __('Candlelight', '12-step-meeting-list'),
		'CW'	=> __('Children Welcome', '12-step-meeting-list'),
		'C'		=> __('Closed', '12-step-meeting-list'),
		'DISC'	=> __('Discussion/Participation', '12-step-meeting-list'),
		'GL'	=> __('Gay/Lesbian', '12-step-meeting-list'),
		'IP'	=> __('IP Study', '12-step-meeting-list'),
		'IW'	=> __('It Works Study', '12-step-meeting-list'),
		'JFT'	=> __('Just For Today Study', '12-step-meeting-list'),
		'LIT'	=> __('Literature Study', '12-step-meeting-list'),
		'LC'	=> __('Living Clean', '12-step-meeting-list'),
		'M'		=> __('Men', '12-step-meeting-list'),
		'MED'	=> __('Meditation', '12-step-meeting-list'),
		'NS'	=> __('Non-Smoking', '12-step-meeting-list'),
		'O'		=> __('Open', '12-step-meeting-list'),
		'QA'	=> __('Questions & Answers', '12-step-meeting-list'),
		'RA'	=> __('Restricted Access', '12-step-meeting-list'),
		'SMOK'	=> __('Smoking', '12-step-meeting-list'),
		'SPK'	=> __('Speaker', '12-step-meeting-list'),
		'STEP'	=> __('Step', '12-step-meeting-list'),
		'SWG'	=> __('Step Working Guide Study', '12-step-meeting-list'),
		'TOP'	=> __('Topic', '12-step-meeting-list'),
		'TRAD'	=> __('Tradition', '12-step-meeting-list'),
		'VAR'	=> __('Format Varies', '12-step-meeting-list'),
		'X'		=> __('Wheelchair Accessible', '12-step-meeting-list'),
		'W'		=> __('Women', '12-step-meeting-list'),
		'Y'		=> __('Young People', '12-step-meeting-list'),
	),
	'oa' => array(
		'11TH'  => __('11th Step', '12-step-meeting-list'),
		'90D'   => __('90 Day', '12-step-meeting-list'),
		'AA12'  => __('AA 12/12', '12-step-meeting-list'),
		'AIB'   => __('Ask-It-Basket', '12-step-meeting-list'),
		'B'     => __('Big Book', '12-step-meeting-list'),
		'DOC'   => __('Dignity of Choice', '12-step-meeting-list'),
		'FT'    => __('For Today', '12-step-meeting-list'),
		'LI'    => __('Lifeline', '12-step-meeting-list'),
		'LIS'   => __('Lifeline Sampler', '12-step-meeting-list'),
		'LIT'   => __('Literature Study', '12-step-meeting-list'),
		'MAIN'  => __('Maintenance', '12-step-meeting-list'),
		'MED'   => __('Meditation', '12-step-meeting-list'),
		'NEWB'  => __('New Beginnings', '12-step-meeting-list'),
		'BE'    => __('Newcomer', '12-step-meeting-list'),
		'HOW'   => __('OA H.O.W.', '12-step-meeting-list'),
		'OA23'  => __('OA Second and/or Third Edition', '12-step-meeting-list'),
		'ST'    => __('OA Steps and/or Traditions Study', '12-step-meeting-list'),
		'RELA'  => __('Relapse/12th Step Within', '12-step-meeting-list'),
		'SSP'   => __('Seeking the Spiritual Path', '12-step-meeting-list'),
		'SP'    => __('Speaker', '12-step-meeting-list'),
		'SD'    => __('Speaker/Discussion', '12-step-meeting-list'),
		'SPIR'  => __('Spirituality', '12-step-meeting-list'),
		'TEEN'  => __('Teen Friendly', '12-step-meeting-list'),
		'PROM'  => __('The Promises', '12-step-meeting-list'),
		'TOOL'  => __('Tools', '12-step-meeting-list'),
		'D'     => __('Topic', '12-step-meeting-list'),
		'MISC'  => __('Varies', '12-step-meeting-list'),
		'VOR'   => __('Voices of Recovery', '12-step-meeting-list'),
		'WORK'  => __('Work Book Study', '12-step-meeting-list'),
		'WRIT'  => __('Writing', '12-step-meeting-list'),
	),	
	'rca' => array(
		'C'		=> __('Closed', '12-step-meeting-list'),
		'O'		=> __('Open', '12-step-meeting-list'),
		'SP'	=> __('Speaker', '12-step-meeting-list'),
	),
	'sa' => array(
		'BE'	=> __('Beginner', '12-step-meeting-list'),
		'B'		=> __('Book Study', '12-step-meeting-list'),
		'C'		=> __('Closed', '12-step-meeting-list'),
		'MED'	=> __('Meditation', '12-step-meeting-list'),
		'M'		=> __('Men', '12-step-meeting-list'),
		'MI'	=> __('Mixed', '12-step-meeting-list'),
		'O'		=> __('Open', '12-step-meeting-list'),
		'PP'	=> __('Primary Purpose', '12-step-meeting-list'),
		'SP'	=> __('Speaker', '12-step-meeting-list'),
		'ST'	=> __('Step Study', '12-step-meeting-list'),
		'W'		=> __('Women', '12-step-meeting-list'),
	),
	'sca' => array(
		'BE'	=> __('Beginner', '12-step-meeting-list'),
		'H'		=> __('Chip', '12-step-meeting-list'),
		'C'		=> __('Closed', '12-step-meeting-list'),
		'COURT'	=> __('Court', '12-step-meeting-list'),
		'D'     => __('Discussion', '12-step-meeting-list'),
		'GL'     => __('Graphic Language', '12-step-meeting-list'),
		'O'		=> __('Open', '12-step-meeting-list'),
		'SP'	=> __('Speaker', '12-step-meeting-list'),
		'ST'	=> __('Step', '12-step-meeting-list'),
	),
	'saa' => array(
		'C'		=> __('Closed', '12-step-meeting-list'),
		'M'		=> __('Men', '12-step-meeting-list'),
		'O'		=> __('Open', '12-step-meeting-list'),
		'ST'	=> __('Step Meeting', '12-step-meeting-list'),
		'LGBTQ'	=> __('LGBTQ', '12-step-meeting-list'),
		'W'		=> __('Women', '12-step-meeting-list'),
	),
	'slaa' => array(
		'AN'	=> __('Anorexia Focus', '12-step-meeting-list'),
		'B'		=> __('Book Study', '12-step-meeting-list'),
		'H'		=> __('Chips', '12-step-meeting-list'),
		'BA'	=> __('Child Care Available', '12-step-meeting-list'),
		'C'		=> __('Closed', '12-step-meeting-list'),
		'FF'	=> __('Fragrance Free', '12-step-meeting-list'),
		'GC'	=> __('Getting Current', '12-step-meeting-list'),
		'X'		=> __('Handicapped Accessible', '12-step-meeting-list'),
		'HR'	=> __('Healthy Relationships', '12-step-meeting-list'),
		'LIT'	=> __('Literature Reading', '12-step-meeting-list'),
		'MED'	=> __('Meditation', '12-step-meeting-list'),
		'M'		=> __('Men', '12-step-meeting-list'),
		'NC'	=> __('Newcomers', '12-step-meeting-list'),
		'O'		=> __('Open', '12-step-meeting-list'),
		'PRI'	=> __('Prison', '12-step-meeting-list'),
		'S'		=> __('Spanish', '12-step-meeting-list'),
		'SP'	=> __('Speaker', '12-step-meeting-list'),
		'ST'	=> __('Step Study', '12-step-meeting-list'),
		'D'		=> __('Topic Discussion', '12-step-meeting-list'),
		'TR'	=> __('Tradition Study', '12-step-meeting-list'),
		'W'		=> __('Women', '12-step-meeting-list'),
	),
);

$tsml_types_in_use = get_option('tsml_types_in_use', array_keys($tsml_types[$tsml_program]));
if (!is_array($tsml_types_in_use)) $tsml_types_in_use = array();