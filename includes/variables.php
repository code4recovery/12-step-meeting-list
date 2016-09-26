<?php
/*	
don't make changes! it'll make staying updated much harder.
for updates / questions, please contact wordpress@meetingguide.org
*/

//configuration constants
if (!defined('GROUP_CONTACT_COUNT')) define('GROUP_CONTACT_COUNT', 3); //number of contacts per group

$tsml_alerts = array();

$tsml_days	= array(
	0 => array(0=>__('Sunday'), 1=>__('Monday'), 2=>__('Tuesday'), 3=>__('Wednesday'), 4=>__('Thursday'), 5=>__('Friday'), 6=>__('Saturday')),
	1 => array(1=>__('Monday'), 2=>__('Tuesday'), 3=>__('Wednesday'), 4=>__('Thursday'), 5=>__('Friday'), 6=>__('Saturday'), 0=>__('Sunday')),
	2 => array(2=>__('Tuesday'), 3=>__('Wednesday'), 4=>__('Thursday'), 5=>__('Friday'), 6=>__('Saturday'), 0=>__('Sunday'), 1=>__('Monday')),
	3 => array(3=>__('Wednesday'), 4=>__('Thursday'), 5=>__('Friday'), 6=>__('Saturday'), 0=>__('Sunday'), 1=>__('Monday'), 2=>__('Tuesday')),
	4 => array(4=>__('Thursday'), 5=>__('Friday'), 6=>__('Saturday'), 0=>__('Sunday'), 1=>__('Monday'), 2=>__('Tuesday'), 3=>__('Wednesday')),
	5 => array(5=>__('Friday'), 6=>__('Saturday'), 0=>__('Sunday'), 1=>__('Monday'), 2=>__('Tuesday'), 3=>__('Wednesday'), 4=>__('Thursday')),
	6 => array(6=>__('Saturday'), 0=>__('Sunday'), 1=>__('Monday'), 2=>__('Tuesday'), 3=>__('Wednesday'), 4=>__('Thursday'), 5=>__('Friday')),
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
	'al-anon'	=> __('Al-Anon'),
	'aa'		=> __('Alcoholics Anonymous'),
	'coda'		=> __('Co-Dependents Anonymous'),
	'na'		=> __('Narcotics Anonymous'),
	'oa'		=> __('Overeaters Anonymous'),
	'sa'		=> __('Sexaholics Anonymous'),
	'saa'		=> __('Sex Addicts Anonymous'),
	'slaa'		=> __('Sex and Love Addicts Anonymous'),
);

$tsml_timestamp = microtime(true);

$tsml_types = array(
	'aa' => array(
		'A'		=> __('Atheist / Agnostic'),
		'BA'	=> __('Babysitting Available'),
		'BE'	=> __('Beginner'),
		'B'		=> __('Big Book'),
		'CF'	=> __('Child-Friendly'),
		'H'		=> __('Chips'),
		'C'		=> __('Closed'),
		'CAN'	=> __('Candlelight'),
		'AL-AN'	=> __('Concurrent with Al-Anon'),
		'AL'	=> __('Concurrent with Alateen'),
		'XT'	=> __('Cross Talk Permitted'),
		'DLY'	=> __('Daily'),
		'FF'	=> __('Fragrance Free'),
		'FR'	=> __('French'),
		'G'		=> __('Gay'),
		'GR'	=> __('Grapevine'),
		'ITA'	=> __('Italian'),
		'L'		=> __('Lesbian'),
		'LIT'	=> __('Literature'),
		'LGBTQ'	=> __('LGBTQ'),
		'MED'	=> __('Meditation'),
		'M'		=> __('Men'),
		'O'		=> __('Open'),
		'POL'	=> __('Polish'),
		'POR'	=> __('Portuguese'),
		'PUN'	=> __('Punjabi'),
		'RUS'	=> __('Russian'),
		'ASL'	=> __('Sign Language'),
		'SM'	=> __('Smoking Permitted'),
		'S'		=> __('Spanish'),
		'SP'	=> __('Speaker'),
		'ST'	=> __('Step Meeting'),
		'D'		=> __('Topic Discussion'),
		'TR'	=> __('Tradition'),
		'T'		=> __('Transgender'),
		'X'		=> __('Wheelchair Accessible'),
		'W'		=> __('Women'),
		'Y'		=> __('Young People'),
	),
	'al-anon' => array(
		'AC'	=> __('Adult Child Focus'),
		'Y'		=> __('Alateen'),
		'A'		=> __('Atheist / Agnostic'),
		'BA'	=> __('Babysitting Available'),
		'BE'	=> __('Beginner'),
		'C'		=> __('Closed'),
		'AA'	=> __('Concurrent with AA Meeting'),
		'AL'	=> __('Concurrent with Alateen Meeting'),
		'FF'	=> __('Fragrance Free'),
		'G'		=> __('Gay'),
		'L'		=> __('Lesbian'),
		'M'		=> __('Men'),
		'O'		=> __('Open'),
		'S'		=> __('Spanish'),
		'SP'	=> __('Speaker'),
		'ST'	=> __('Step Meeting'),
		'T'		=> __('Transgender'),
		'X'		=> __('Wheelchair Accessible'),
		'W'		=> __('Women'),
	),
	'coda' => array(
		'A'		=> __('Atheist / Agnostic'),
		'BA'	=> __('Babysitting Available'),
		'BE'	=> __('Beginner'),
		'B'		=> __('Book Study'),
		'CF'	=> __('Child-Friendly'),
		'H'		=> __('Chips'),
		'C'		=> __('Closed'),
		'CAN'	=> __('Candlelight'),
		'AL-AN'	=> __('Concurrent with Al-Anon'),
		'AL'	=> __('Concurrent with Alateen'),
		'XT'	=> __('Cross Talk Permitted'),
		'DLY'	=> __('Daily'),
		'FF'	=> __('Fragrance Free'),
		'G'		=> __('Gay'),
		'GR'	=> __('Grapevine'),
		'L'		=> __('Lesbian'),
		'LIT'	=> __('Literature'),
		'LGBTQ'	=> __('LGBTQ'),
		'MED'	=> __('Meditation'),
		'M'		=> __('Men'),
		'O'		=> __('Open'),
		'QA'	=> __('Q & A'),
		'READ' 	=> __('Reading'),
		'SHARE'	=> __('Sharing'),
		'ASL'	=> __('Sign Language'),
		'SM'	=> __('Smoking Permitted'),
		'S'		=> __('Spanish'),
		'SP'	=> __('Speaker'),
		'ST'	=> __('Step Meeting'),
		'TEEN'	=> __('Teens'),
		'D'		=> __('Topic Discussion'),
		'TR'	=> __('Tradition'),
		'T'		=> __('Transgender'),
		'X'		=> __('Wheelchair Accessible'),
		'W'		=> __('Women'),
		'WRITE'	=> __('Writing'),
		'Y'		=> __('Young People'),
	),
	'na' => array(
		'CPT'	=> __('12 Concepts'),
		'BT'	=> __('Basic Text'),
		'BEG'	=> __('Beginner/Newcomer'),
		'CAN'	=> __('Candlelight'),
		'CW'	=> __('Children Welcome'),
		'C'		=> __('Closed'),
		'DISC'	=> __('Discussion/Participation'),
		'GL'	=> __('Gay/Lesbian'),
		'IP'	=> __('IP Study'),
		'IW'	=> __('It Works Study'),
		'JFT'	=> __('Just For Today Study'),
		'LIT'	=> __('Literature Study'),
		'LC'	=> __('Living Clean'),
		'M'		=> __('Men'),
		'MED'	=> __('Meditation'),
		'NS'	=> __('Non-Smoking'),
		'O'		=> __('Open'),
		'QA'	=> __('Questions & Answers'),
		'RA'	=> __('Restricted Access'),
		'SMOK'	=> __('Smoking'),
		'SPK'	=> __('Speaker'),
		'STEP'	=> __('Step'),
		'SWG'	=> __('Step Working Guide Study'),
		'TOP'	=> __('Topic'),
		'TRAD'	=> __('Tradition'),
		'VAR'	=> __('Format Varies'),
		'X'		=> __('Wheelchair Accessible'),
		'W'		=> __('Women'),
		'Y'		=> __('Young People'),
	),
	'oa' => array(
		'11TH'  => __('11th Step'),
		'90D'   => __('90 Day'),
		'AA12'  => __('AA 12/12'),
		'AIB'   => __('Ask-It-Basket'),
		'B'     => __('Big Book'),
		'DOC'   => __('Dignity of Choice'),
		'FT'    => __('For Today'),
		'LI'    => __('Lifeline'),
		'LIS'   => __('Lifeline Sampler'),
		'LIT'   => __('Literature Study'),
		'MAIN'  => __('Maintenance'),
		'MED'   => __('Meditation'),
		'NEWB'  => __('New Beginnings'),
		'BE'    => __('Newcomer'),
		'HOW'   => __('OA H.O.W.'),
		'OA23'  => __('OA Second and/or Third Edition'),
		'ST'    => __('OA Steps and/or Traditions Study'),
		'RELA'  => __('Relapse/12th Step Within'),
		'SSP'   => __('Seeking the Spiritual Path'),
		'SP'    => __('Speaker'),
		'SD'    => __('Speaker/Discussion'),
		'SPIR'  => __('Spirituality'),
		'TEEN'  => __('Teen Friendly'),
		'PROM'  => __('The Promises'),
		'TOOL'  => __('Tools'),
		'D'     => __('Topic'),
		'MISC'  => __('Varies'),
		'VOR'   => __('Voices of Recovery'),
		'WORK'  => __('Work Book Study'),
		'WRIT'  => __('Writing'),
	),	
	'sa' => array(
		'BE'	=> __('Beginner'),
		'B'		=> __('Book Study'),
		'C'		=> __('Closed'),
		'MED'	=> __('Meditation'),
		'M'		=> __('Men'),
		'MI'	=> __('Mixed'),
		'O'		=> __('Open'),
		'PP'	=> __('Primary Purpose'),
		'SP'	=> __('Speaker'),
		'ST'	=> __('Step Study'),
		'W'		=> __('Women'),
	),
	'saa' => array(
		'C'		=> __('Closed'),
		'M'		=> __('Men'),
		'O'		=> __('Open'),
		'ST'	=> __('Step Meeting'),
		'LGBTQ'	=> __('LGBTQ'),
		'W'		=> __('Women'),
	),
	'slaa' => array(
		'AN'	=> __('Anorexia Focus'),
		'B'		=> __('Book Study'),
		'H'		=> __('Chips'),
		'BA'	=> __('Child Care Available'),
		'C'		=> __('Closed'),
		'FF'	=> __('Fragrance Free'),
		'GC'	=> __('Getting Current'),
		'X'		=> __('Handicapped Accessible'),
		'HR'	=> __('Healthy Relationships'),
		'LIT'	=> __('Literature Reading'),
		'MED'	=> __('Meditation'),
		'M'		=> __('Men'),
		'NC'	=> __('Newcomers'),
		'O'		=> __('Open'),
		'PRI'	=> __('Prison'),
		'S'		=> __('Spanish'),
		'SP'	=> __('Speaker'),
		'ST'	=> __('Step Study'),
		'D'		=> __('Topic Discussion'),
		'TR'	=> __('Tradition Study'),
		'W'		=> __('Women'),
	),
);

$tsml_types_in_use = get_option('tsml_types_in_use', array_keys($tsml_types[$tsml_program]));
if (!is_array($tsml_types_in_use)) $tsml_types_in_use = array();