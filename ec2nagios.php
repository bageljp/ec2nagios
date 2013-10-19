<?php

require_once (dirname(__FILE__) . '/aws-sdk-for-php/sdk.class.php');
require_once (dirname(__FILE__) . '/config.inc.php');

$ec2 = new AmazonEC2();

# Add EC2Nagios configuration directory to nagios.cfg
$nagios_config = @file_get_contents($nagios_config_path);
/* not auto configuration
$directory_configuration = "cfg_dir={$ec2nagios_objects_directory}";
if (strpos($nagios_config, $directory_configuration) === false) {
	$nagios_config = preg_replace('/(.*)cfg_dir=([^\n]*)/ms', '$0' . "\n\n{$directory_configuration}", $nagios_config);
	if (strpos($nagios_config, $directory_configuration) === false)
		$nagios_config .= "\n{$directory_configuration}\n";
	file_put_contents($nagios_config_path, $nagios_config);
}
*/

# Make EC2Nagios objects directory if not exists
if (!file_exists($ec2nagios_objects_directory))
	mkdir($ec2nagios_objects_directory);

# List EC2 instances and generate configuration
$ec2nagios_config = '';
$hostgroup_members = array();

foreach ($regions as $region) {

	// describe instances in the region.
	$ec2->set_region($region);
	$instances = $ec2->describe_instances();
	if (!$instances->isOK())
		continue;

	// create config
	foreach ($instances->body->reservationSet->children() as $reservationItem) {
		foreach ($reservationItem->instancesSet->children() as $instanceItem) {
			if ($instanceItem->instanceState->name != 'running')
				continue;
			$node_dns = $use_public_dns ? $instanceItem->dnsName : $instanceItem->privateIpAddress;
			$node_ip = $use_public_dns ? $instanceItem->dnsName : $instanceItem->privateIpAddress;
			$node_name = null;
			$hostgroup = null;
			foreach ($instanceItem->tagSet->item as $tag) {
				$tag_key = $tag->key->to_string();
				$tag_value = $tag->value->to_string();
				if (strcasecmp($tag_key, 'Name') === 0) {
					$node_name = $use_public_dns ? $tag_value : $tag_value . '.' . $instanceItem->privateIpAddress;
				}
				if (strcasecmp($tag_key, $ec2nagios_tag_key) === 0) {
					$hostgroup = $tag_value;
				}
			}
			if ($hostgroup) {
				$ec2nagios_config .= create_host_config($node_dns, $node_name, $node_ip, $use_host, $use_contactgroup);
				$hostgroup_members[$hostgroup][] = $node_dns;
			}
		}
	}

}

foreach ($hostgroup_members as $hostgroup => $members) {
	$ec2nagios_config .= create_hostgroup_config($hostgroup, $members);
}

copy("{$ec2nagios_objects_directory}/{$ec2nagios_config_filename}", "{$tmp_directory}/{$ec2nagios_config_filename}");
file_put_contents("{$ec2nagios_objects_directory}/{$ec2nagios_config_filename}", $ec2nagios_config);

# Create template hostgroup configuration
if ($create_service_cfg) {
	$hostgroups = array_keys($hostgroup_members);
	foreach ($hostgroups as $hostgroup) {
		$hostgroup_config_path = "{$ec2nagios_objects_directory}/service-{$hostgroup}.cfg";
		$hostgroup_config = create_hostgroup_config_template($hostgroup, $use_service);
		file_put_contents($hostgroup_config_path, $hostgroup_config);
	}
}

# Service restart
if ($service_restart) {
        $compare = array();
        $diff_list = array("{$ec2nagios_objects_directory}/{$ec2nagios_config_filename}", "{$tmp_directory}/{$ec2nagios_config_filename}");
	foreach($diff_list as $value){
	    $file = fopen($value, "r");
	    $data = fread($file, filesize($value));
	    fclose($file);
	    array_push($compare, $data);
	}
	if ($compare[0] !== $compare[1]) service_restart($program, $nagios_config_path);
}

function create_host_config($node_dns, $node_name, $node_ip, $use_host, $use_contactgroup) {

	$ec2nagios_config = <<<EOT
define host{
	use             {$use_host}
        host_name       {$node_dns}
        alias           {$node_name}
        address         {$node_ip}
        contact_groups  {$use_contactgroup}
        }

EOT;

	return $ec2nagios_config;

}

function create_hostgroup_config($hostgroup, $members) {

	$members_concat = join($members, ',');

	$ec2nagios_config = <<<EOT
define hostgroup{
        hostgroup_name  {$hostgroup}
        alias           {$hostgroup}
        members         {$members_concat}
        }

EOT;

	return $ec2nagios_config;

}

function create_hostgroup_config_template($hostgroup, $use_service) {

	$ec2nagios_config = <<<EOT
# You can edit following lines for "{$hostgroup}" hostgroup

define service{
        use                     {$use_service}
        hostgroup_name          {$hostgroup}
        service_description     PING
        check_command           check_ping!100.0,20%!500.0,60%
        }

EOT;

	return $ec2nagios_config;

}

function service_restart($program, $nagios_config_path) {
	exec("{$program} -v {$nagios_config_path}", $output, $retval);
	if ($retval === 0) exec("/etc/init.d/{$program} restart", $output, $retval);

	return $retval;
}
