<?php

//define global variables

$days	= array(
	0 => array(0=>'Sunday', 1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday'),
	1 => array(1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday', 0=>'Sunday'),
	2 => array(2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday', 0=>'Sunday', 1=>'Monday'),
	3 => array(3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday', 0=>'Sunday', 1=>'Monday', 2=>'Tuesday'),
	4 => array(4=>'Thursday', 5=>'Friday', 6=>'Saturday', 0=>'Sunday', 1=>'Monday', 2=>'Tuesday', 3=>'Wednesday'),
	5 => array(5=>'Friday', 6=>'Saturday', 0=>'Sunday', 1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday'),
	6 => array(6=>'Saturday', 0=>'Sunday', 1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday'),
);
$days = $days[get_option('start_of_week')];

$types = array(
	'H'=>'Chips', 
	'C'=>'Closed', 
	'G'=>'Gay',
	'L'=>'Lesbian',
	'M'=>'Men Only', 
	'O'=>'Open',
	'S'=>'Spanish',
	'T'=>'Transgender',
	'X'=>'Wheelchair Accessible',
	'W'=>'Women Only',
	'Y'=>'Young People',
);

$regions = $custom = array();

$nonce = plugin_basename(__FILE__);