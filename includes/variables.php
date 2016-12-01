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

$tsml_feedback_addresses = get_option('tsml_feedback_addresses', array());

$tsml_google_api_key = 'AIzaSyCC3p6PSf6iQbXi-Itwn9C24_FhkbDUkdg'; //might have to make this user-specific

/*
unfortunately the google geocoding API is not perfect. used by tsml_import() and admin.js
it's useful to use the Places API to find the correct information. For example, start with the Google API
https://maps.googleapis.com/maps/api/geocode/json?key=AIzaSyCC3p6PSf6iQbXi-Itwn9C24_FhkbDUkdg&address=320%20Beach%2094th%20St,%20Rockaway%20Beach,%20NY%2011693
and then search by location name near the coordinates	
https://maps.googleapis.com/maps/api/place/nearbysearch/json?key=AIzaSyAtTOBvcG7UrGE2Cz5xYIwI_yHjWvxlN8o&location=40.5854777,-73.81639299999999&name=first+congregational+church
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
);

$tsml_nonce = plugin_basename(__FILE__);

$tsml_notification_addresses = get_option('tsml_notification_addresses', array());

$tsml_program = get_option('tsml_program', 'aa');

$tsml_programs = array(
	'al-anon'	=> __('Al-Anon', '12-step-meeting-list'),
	'aa'		=> __('Alcoholics Anonymous', '12-step-meeting-list'),
	'coda'		=> __('Co-Dependents Anonymous', '12-step-meeting-list'),
	'na'		=> __('Narcotics Anonymous', '12-step-meeting-list'),
	'oa'		=> __('Overeaters Anonymous', '12-step-meeting-list'),
	'sa'		=> __('Sexaholics Anonymous', '12-step-meeting-list'),
	'saa'		=> __('Sex Addicts Anonymous', '12-step-meeting-list'),
	'slaa'		=> __('Sex and Love Addicts Anonymous', '12-step-meeting-list'),
);

$tsml_timestamp = microtime(true);

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
		'DD'	=> __('Dual Dianosis', '12-step-meeting-list'),
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
		'SP'	=> __('Speaker Discussion', '12-step-meeting-list'),
		'SO'	=> __('Speaker Only', '12-step-meeting-list'),
		'ST'	=> __('Step Meeting', '12-step-meeting-list'),
		'D'		=> __('Topic Discussion', '12-step-meeting-list'),
		'TR'	=> __('Tradition', '12-step-meeting-list'),
		'T'		=> __('Transgender', '12-step-meeting-list'),
		'V'	=> __('Variety / Chair\'s Choice', '12-step-meeting-list'),
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