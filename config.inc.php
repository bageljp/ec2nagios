<?php

CFCredentials::set(array(
	'development' => array(
		'key' => 'key',
		'secret' => 'secret-key',
		'default_cache_config' => '',
		'certificate_authority' => false
	),
	'@default' => 'development'
));

$program = 'icinga';
$nagios_config_path = "/etc/{$program}/{$program}.cfg";
$ec2nagios_objects_directory = "/etc/{$program}/conf.d/ec2nagios";
$ec2nagios_config_filename = 'ec2nagios.cfg';
$ec2nagios_tag_key = 'EC2Nagios';
$tmp_directory = "/tmp";

$regions = array(
	AmazonEC2::REGION_US_E1,
	AmazonEC2::REGION_US_W1,
	AmazonEC2::REGION_US_W2,
	AmazonEC2::REGION_EU_W1,
	AmazonEC2::REGION_APAC_SE1,
	AmazonEC2::REGION_APAC_NE1,
	AmazonEC2::REGION_US_GOV1,
	AmazonEC2::REGION_SA_E1,
);

$use_public_dns = false;
$use_host = 'linux-server';
$use_service = 'local-service';
$use_contactgroup = 'admins';
$create_service_cfg = false;
$service_restart = true;
