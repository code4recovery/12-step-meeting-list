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

$tsml_days = $tsml_days[get_option('start_of_week')];

$tsml_types = array(
	'A' => 'Atheist / Agnostic',
	'B' => 'Big Book',
	'H' => 'Chips', 
	'C' => 'Closed', 
	'G' => 'Gay',
	'L' => 'Lesbian',
	'M' => 'Men Only', 
	'O' => 'Open',
	'S' => 'Spanish',
	'SP' => 'Speaker',
	'ST' => 'Step Meeting',
	'T' => 'Transgender',
	'X' => 'Wheelchair Accessible',
	'W' => 'Women Only',
	'Y' => 'Young People',
);

$tsml_regions = $tsml_custom = array();

$tsml_nonce = plugin_basename(__FILE__);