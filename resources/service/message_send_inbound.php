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
	$sql = "select d.domain_name, e.extension from v_domains as d, v_extensions as e ";
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
		foreach ($extensions as $row) {
			$contacts = trim(event_socket_request(null, "api sofia_contact ".$row['extension']."@".$row['domain_name']));
			if (isset($debug)) {
				echo $row['extension']."@".$row['domain_name']." contacts: ".$contacts."\n";
			}
			if (empty($contacts) || substr($contacts, 0, 6) == 'error/') {
				continue;
			}

			//freeswitch drops a MESSAGE addressed to user@domain when the registered contact has fs_nat params, so address each contact directly
			foreach (explode(',', $contacts) as $contact) {
				[, $sip_profile, $contact_uri] = explode('/', $contact, 3);
				$from = $message_from.'@'.$row['domain_name'];

				$event = "sendevent CUSTOM\n";
				$event .= "Event-Subclass: SMS::SEND_MESSAGE\n";
				$event .= "proto: sip\n";
				$event .= "dest_proto: sip\n";
				$event .= "from: ".$from."\n";
				$event .= "from_full: sip:".$from."\n";
				$event .= "to: ".$sip_profile."/".$contact_uri."\n";
				$event .= "type: text/plain\n";
				$event .= "sip_profile: ".$sip_profile."\n";
				$event .= "_body: ".$message_text;
				event_socket_request(null, $event);
			}
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
