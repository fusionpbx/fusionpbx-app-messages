<?php

//only allow command line
	if (defined('STDIN')) {
		//set the include path
		$conf = glob("{/usr/local/etc,/etc}/fusionpbx/config.conf", GLOB_BRACE);
		set_include_path(parse_ini_file($conf[0])['document.root']);
	}
	else {
		exit;
	}

//increase limits
	//set_time_limit(0);
	ini_set('max_execution_time',30); //seconds
	ini_set('memory_limit', '512M');

//include files
	require_once "resources/require.php";
	include "resources/classes/cache.php";
	include "resources/classes/permissions.php";

//save the arguments to variables
	$script_name = $argv[0];
	if (!empty($argv[1])) {
		parse_str($argv[1], $_GET);
	}
	//print_r($_GET);

//set the variables
	if (isset($_GET['hostname'])) {
		$hostname = urldecode($_GET['hostname']);
	}
	if (isset($_GET['debug'])) {
		$debug = $_GET['debug'];
	}
	if (isset($_GET['message_queue_uuid'])) {
		$message_queue_uuid = urldecode($_GET['message_queue_uuid']);
	}

//set the hostname if it wasn't provided
	if (!isset($hostname) && strlen($hostname) == 0) {
		$hostname = system('hostname');
	}

//log information
	if (isset($debug)) {
		//print_r($_GET);
		echo "message_queue_uuid ".$message_queue_uuid."\n";
		echo "hostname ".$hostname."\n";
	}

//get the message details to send
	$sql = "select * from v_message_queue ";
	$sql .= "where message_queue_uuid = :message_queue_uuid ";
	$sql .= "and hostname = :hostname ";
	$parameters['hostname'] = $hostname;
	$parameters['message_queue_uuid'] = $message_queue_uuid;
	$database = new database;
	$row = $database->select($sql, $parameters, 'row');
	//view_array($row, false);
	if (is_array($row)) {
		$domain_uuid = $row["domain_uuid"];
		$message_queue_uuid = $row["message_queue_uuid"];
		$user_uuid = $row["user_uuid"];
		$group_uuid = $row["group_uuid"];
		$contact_uuid = $row["contact_uuid"];
		$provider_uuid = $row["provider_uuid"];
		//$hostname = $row["hostname"];
		$message_type = $row["message_type"];
		$message_direction = $row["message_direction"];
		$message_date = $row["message_date"];
		$message_from = $row["message_from"];
		$message_to = $row["message_to"];
		$message_text = $row["message_text"];
		$message_json = $row["message_json"];
	}
	unset($parameters);

//get the provider settings
	$sql = "select provider_setting_category, provider_setting_subcategory, provider_setting_name, provider_setting_value, provider_setting_order \n";
	$sql .= "from v_provider_settings \n";
	$sql .= "where provider_uuid = :provider_uuid \n";
	$sql .= "and provider_setting_category = 'inbound' \n";
	$sql .= "and provider_setting_enabled = 'true'; \n";
	$parameters['provider_uuid'] = $provider_uuid;
	$database = new database;
	$provider_settings = $database->select($sql, $parameters, 'all');
	unset($parameters);
	//echo $sql;
	//print_r($parameters);
	//print_r(provider_settings);
	//
	//echo "\n";

//set default values
	//$http_method = 'POST';

//process the provider settings array
	foreach ($provider_settings as $row) {
		//format the phone numbers
		if ($row['provider_setting_subcategory'] == 'format') {
			if ($row['provider_setting_name'] == 'message_from') {
				$message_from = format_string($row['provider_setting_value'], $message_from);
			}
			if ($row['provider_setting_name'] == 'message_to') {
				$message_to = format_string($row['provider_setting_value'], $message_to);
			}
		}

		//get the http method
		//if ($row['provider_setting_subcategory'] == 'method') {
		//	if ($row['provider_setting_name'] == 'http_method') {
		//		$http_method = $row['provider_setting_value'];
		//	}
		//}
	}

//get the list of extensions using the user_uuid
	$sql = "select * from v_domains as d, v_extensions as e ";
	$sql .= "where extension_uuid in ( ";
	$sql .= "	select extension_uuid ";
	$sql .= "	from v_extension_users ";
	$sql .= "	where user_uuid = :user_uuid ";
	$sql .= ") ";
	$sql .= "and e.domain_uuid = d.domain_uuid ";
	$sql .= "and e.enabled = 'true' ";
	$parameters['user_uuid'] = $user_uuid;
	$database = new database;
	$extensions = $database->select($sql, $parameters, 'all');
	//view_array($extensions, false);
	unset($sql, $parameters);

//send the sip message
	if (is_array($extensions) && @sizeof($extensions) != 0) {
		//echo "\n".__line__."\n";
		//create the event socket connection
		$fp = event_socket_create($_SESSION['event_socket_ip_address'], $_SESSION['event_socket_port'], $_SESSION['event_socket_password']);

		//loop through assigned extensions
		foreach ($extensions as $extension) {
			//get variables from the array
			$domain_name = $extension['domain_name'];
			$extension = $extension['extension'];
			$number_alias = $extension['number_alias'];

			//user registered get the sip profile
			//$command = "sofia_contact ".$extension."@".$domain_name;
			//if (isset($debug)) {
			//	echo $command."\n";
			//}
			//$response = trim(event_socket_request($fp, "api ".$command));
			//if ($response != 'error/user_not_registered') {
			//	echo $extension." registered\n";
			//	$sip_profile = explode("/", $response)[1];
			//}

			//user not registered skip the rest of code in the loop
			//if ($response == 'error/user_not_registered') {
			//	echo $extension." not registered\n";
			//	continue;
			//}

			//original number with the domain name
			$message_from = $message_from .'@'.$domain_name;

			//send to the assigned extension(s)
			$message_to = $extension . '@'.$domain_name;
			//$message_to = '1005@voip.fusionpbx.com';

			//add debug info to the message
			//$message_content = $message_content . ' - ' .$message_to_orig;

			//send the SIP message  (working)
			$event = "sendevent CUSTOM\n";
			$event .= "Event-Subclass: SMS::SEND_MESSAGE\n";
			$event .= "proto: sip\n";
			$event .= "dest_proto: sip\n";
			$event .= "from: ".$message_from."\n";
			$event .= "from_full: sip:".$message_from."\n";
			$event .= "to: ".$message_to."\n";
			$event .= "subject: sip:".$message_to."\n";
			//$event .= "type: text/html\n";
			$event .= "type: text/plain\n";
			$event .= "hint: the hint\n";
			$event .= "replying: true\n";
			$event .= "sip_profile: ".$sip_profile."\n";
			$event .= "_body: ". $message_text;
			event_socket_request($fp, $event);
			unset($event);

		}
	}

//set the message status to sent
	$sql = "update v_message_queue ";
	$sql .= "set message_status = 'sent' ";
	$sql .= "where message_queue_uuid = :message_queue_uuid; ";
	$parameters['message_queue_uuid'] = $message_queue_uuid;
	//echo __line__." ".$sql."\n";
	//print_r($parameters);
	$database = new database;
	$database->execute($sql, $parameters);
	unset($parameters);

//set the last message in the cache
	$cache = new cache;
	$cache->set("messages:user:last_message:".$user_uuid, date('r'));
 
//how to use it
	// php /var/www/fusionpbx/app/messages/resources/service/message_send_inbound.php message_queue_uuid=39402652-1475-49f8-8366-7889335edd6f&hostname=voip.fusionpbx.com

?>
