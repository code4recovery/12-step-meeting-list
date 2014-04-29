<?php

global $meetings_db_version;
$meetings_db_version = '1.0';
$installed_version = get_option('meetings_db_version');

register_activation_hook(__FILE__, 'meetings_install');
//register_activation_hook(__FILE__, 'meetings_install_data');


function meetings_install() {
	global $wpdb, $meetings_db_version, $installed_db_version;

	if ($installed_version == $meetings_db_version) return;

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	dbDelta('CREATE TABLE ' . $wpdb->prefix . 'meetings (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		location_id int(11) NOT NULL,
		name varchar(255) NOT NULL,
		time time NOT NULL,
		day enum(\'Sunday\',\'Monday\',\'Tuesday\',\'Wednesday\',\'Thursday\',\'Friday\',\'Saturday\'),
		contact_name varchar(255),
		contact_phone varchar(255),
		contact_email varchar(255),
		updated_at datetime NOT NULL,
		updated_by int(11) NOT NULL,
		notes text,
		UNIQUE KEY id (id)
	);');

	dbDelta('CREATE TABLE ' . $wpdb->prefix . 'meetings_locations (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		address1 varchar(255) NOT NULL,
		address2 varchar(255),
		region_id mediumint(9),
		city varchar(255) NOT NULL,
		state char(2) NOT NULL,
		zip varchar(5) NOT NULL,
		latitude decimal(9,6),
		longitude decimal(9,6),
		contact_name varchar(255),
		contact_phone varchar(255),
		contact_email varchar(255),
		updated_at datetime NOT NULL,
		updated_by int(11) NOT NULL,
		notes text,
		UNIQUE KEY id (id)
	);');
	
	dbDelta('CREATE TABLE ' . $wpdb->prefix . 'meetings_regions (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		updated_at datetime NOT NULL,
		updated_by int(11) NOT NULL,
		UNIQUE KEY id (id)
	);');

	dbDelta('CREATE TABLE ' . $wpdb->prefix . 'meetings_tags (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		updated_at datetime NOT NULL,
		updated_by int(11) NOT NULL,
		UNIQUE KEY id (id)
	);');

	dbDelta('CREATE TABLE ' . $wpdb->prefix . 'meetings_to_tags (
		meeting_id mediumint(9) NOT NULL,
		tag_id mediumint(9) NOT NULL
	);');

	add_option('meetings_db_version', $meetings_db_version);
}
