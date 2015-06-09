<?php

//define global variables

$tsml_days	= array(
	0 => array(0=>'Sunday', 1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday'),
	1 => array(1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday', 0=>'Sunday'),
	2 => array(2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday', 0=>'Sunday', 1=>'Monday'),
	3 => array(3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday', 0=>'Sunday', 1=>'Monday', 2=>'Tuesday'),
	4 => array(4=>'Thursday', 5=>'Friday', 6=>'Saturday', 0=>'Sunday', 1=>'Monday', 2=>'Tuesday', 3=>'Wednesday'),
	5 => array(5=>'Friday', 6=>'Saturday', 0=>'Sunday', 1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday'),
	6 => array(6=>'Saturday', 0=>'Sunday', 1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday'),
);

$tsml_days = $tsml_days[get_option('start_of_week', 0)];

$tsml_types = array(
	'aa' => array(
		'A'   => 'Atheist / Agnostic',
		'BE'  => 'Beginner',
		'B'   => 'Big Book',
		'CF'  => 'Child-Friendly', 
		'H'   => 'Chips', 
		'C'   => 'Closed', 
		'XT'  => 'Cross Talk Permitted', 
		'D'   => 'Discussion', 
		'FF'  => 'Fragrance Free',
		'G'   => 'Gay',
		'GR'  => 'Grapevine',
		'L'   => 'Lesbian',
		'MED' => 'Meditation',
		'M'   => 'Men Only', 
		'O'   => 'Open',
		'SM'  => 'Smoking Permitted',
		'S'   => 'Spanish',
		'SP'  => 'Speaker',
		'ST'  => 'Step Meeting',
		'TR'  => 'Tradition',
		'T'   => 'Transgender',
		'X'   => 'Wheelchair Accessible',
		'W'   => 'Women Only',
		'Y'   => 'Young People',
	),
	'al-anon' => array(
		'AC'  => 'Adult Child Focus',
		'Y'   => 'Alateen',
		'A'   => 'Atheist / Agnostic',
		'BA'  => 'Babysitter',
		'C'   => 'Closed', 
		'AA'  => 'Concurrent with AA Meeting',
		'AL'  => 'Concurrent with Alateen Meeting',
		'FF'  => 'Fragrance Free',
		'G'   => 'Gay',
		'L'   => 'Lesbian',
		'M'   => 'Men Only', 
		'O'   => 'Open',
		'S'   => 'Spanish',
		'SP'  => 'Speaker',
		'ST'  => 'Step Meeting',
		'T'   => 'Transgender',
		'X'   => 'Wheelchair Accessible',
		'W'   => 'Women Only',
	),
);

$tsml_programs = array(
	'al-anon' => 'Al-Anon',
	'aa' => 'Alcoholics Anonymous',
);

$tsml_program = get_option('tsml_program', 'aa');

$tsml_types_in_use = get_option('tsml_types_in_use', array_keys($tsml_types[$tsml_program]));
if (!is_array($tsml_types_in_use)) $tsml_types_in_use = array();

$tsml_regions = $tsml_custom = array();

$tsml_nonce = plugin_basename(__FILE__);