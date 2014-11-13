<?php

//define global variables

$days	= array(
	'Sunday', 
	'Monday', 
	'Tuesday', 
	'Wednesday', 
	'Thursday', 
	'Friday', 
	'Saturday'
);

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