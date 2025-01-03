<?php

/*
description - message_events service
- subcribes to get notified for each SIP Message
- sends messages to external numbers to the message_queue
- message tests
	handled messages_events service
		from 1008 to 1001 (partial loop, no message queue)
		from 1001 to 1008 (partial loop, no message queue)
		from 1008 to 2089068227 (runs through entire loop, sends to message queue)
	handled by message/index
		from 2089068227 to 1008
*/

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
	set_time_limit(0);
	ini_set('max_execution_time', 0);
	ini_set('memory_limit', '512M');

//save the arguments to variables
	$script_name = $argv[0];
	if (!empty($argv[1])) {
		parse_str($argv[1], $_GET);
	}

//set the variables
	if (isset($_GET['hostname'])) {
		$hostname = urldecode($_GET['hostname']);
	}
	if (isset($_GET['debug'])) {
		$debug = $_GET['debug'];
	}

//set the hostname if it wasn't provided
	if (!isset($hostname) || (isset($hostname) && strlen($hostname) == 0)) {
		$hostname = gethostname();
	}

//includes
	require_once "resources/require.php";
	include "resources/classes/permissions.php";

//define the process id file
	$pid_file = "/var/run/fusionpbx/".basename( $argv[0], ".php") .".pid";
	//echo "pid_file: ".$pid_file."\n";

//function to check if the process exists
	function process_exists($file = false) {

		//set the default exists to false
		$exists = false;

		//check to see if the process is running
		if (file_exists($file)) {
			$pid = file_get_contents($file);
			if (posix_getsid($pid) === false) {
				//process is not running
				$exists = false;
			}
			else {
				//process is running
				$exists = true;
			}
		}

		//return the result
		return $exists;
	}

//check to see if the process exists
	$pid_exists = process_exists($pid_file);

//prevent the process running more than once
	if ($pid_exists) {
		echo "Cannot lock pid file {$pid_file}\n";
		exit;
	}

//create the process id file if the process doesn't exist
	if (!$pid_exists) {
		//remove the old pid file
		if (file_exists($file)) {
			unlink($pid_file);
		}

		//show the details to the user
		//echo "The process id is ".getmypid()."\n";

		//save the pid file
		file_put_contents($pid_file, getmypid());
	}

//connect to event socket
	$socket = new event_socket;
	if (!$socket->connect($_SESSION['event_socket_ip_address'], $_SESSION['event_socket_port'], $_SESSION['event_socket_password'])) {
		echo "Unable to connect to event socket\n";
	}

//connect to event socket
	$fp = event_socket_create($_SESSION['event_socket_ip_address'], $_SESSION['event_socket_port'], $_SESSION['event_socket_password']);

//create the database connection
	$database = new database;

//add the permission
	$p = permissions::new();
	$p->add('message_queue_add', 'temp');

//example command
	//$cmd = "api help";
	//$result = $esl->request($cmd);
	//echo "help: ".$result."\n";

//loop through the switch events
	//read the events
	//$cmd = "event plain ALL";
	//$cmd = "event xml ALL";
	$cmd = "event json MESSAGE";
	//$cmd = "event json CUSTOM";
	$result = $socket->request($cmd);
	while ($socket) {

		//read events from socket
		$response = $socket->read_event();

		//decode the event into an array
		$array = json_decode($response['$'], true);

		//initialize variable(s)
		$user_uuid = '';
		$message_type = 'sms';

		//set variables from the event array
		$event_name = $array['Event-Name'];
		$event_type = $array['event_type'];
		$event_subclass = $array['Event-Subclass'];
		$from = $array['from'];
		$from_array = explode("@", $from);
		$from_user = $array['from_user'];
		$from_host = $array['from_host'];
		$to_user = $array['to_user'];
		$to_host = $array['to_host'];
		$from_sip_ip = $array['from_sip_ip'];
		$message_content = $array['_body'];
		$to = $array['to_user'];

		//if the message is from an external number don't relay the message
		$from_command = "user_exists id ".$from_user." ".$from_host;
		$from_response = event_socket_request($fp, "api ".$from_command);

		$to_command = "user_exists id ".$to_user." ".$to_host;
		$to_response = event_socket_request($fp, "api ".$to_command);

		if ($debug) {
			echo "from command: ".$from_command."\n";
			echo "from response: ".$from_response."\n\n";
			echo "to command: ".$to_command."\n";
			echo "to response: ".$to_response."\n\n";
		}

		//from and to are both local then don't send to the message queue
		if ($from_response == 'true' && $to_response == 'true') {
			usleep(10000);
			continue;
		}

		//get the from user's external number
		$command = "user_data ".$from_user."@".$from_host." var outbound_caller_id_number";
		//echo $command."\n";
		$source_number = event_socket_request($fp, "api ".$command);

		/*
		[from] => 1005@voip.fusionpbx.com
		[from_user] => 1005
		[from_host] => voip.fusionpbx.com
		[to_user] => 12088058985
		[to_host] => voip.fusionpbx.com
		[from_sip_ip] => 96.18.173.64
		[from_sip_port] => 14395
		[to] => 12088058985@voip.fusionpbx.com
		[subject] => SIMPLE MESSAGE
		[context] => public
		[type] => text/plain
		[from_full] => <sip:1005@voip.fusionpbx.com>;tag=6952509
		[sip_profile] => internal
		[dest_proto] => sip
		[max_forwards] => 70
		[DP_MATCH] => 12088058985@voip.fusionpbx.com
		[Nonblocking-Delivery] => true
		[Content-Length] => 4
		[_body] => nova
		*/

		//reconnect to the database
		if (!$database) {
			$database = new database;
		}

		//get the provider uuid - needed for sending the message
		$sql = "SELECT * FROM v_destinations \n";
		$sql .= "WHERE ( \n";
		$sql .= "	destination_prefix || destination_area_code || destination_number = :source_number \n";
		$sql .= "	OR destination_trunk_prefix || destination_area_code || destination_number = :source_number \n";
		$sql .= "	OR destination_prefix || destination_number = :source_number \n";
		$sql .= "	OR '+' || destination_prefix || destination_number = :source_number \n";
		$sql .= "	OR '+' || destination_prefix || destination_area_code || destination_number = :source_number \n";
		$sql .= "	OR destination_area_code || destination_number = :source_number \n";
		$sql .= "	OR destination_number = :source_number \n";
		$sql .= ") \n";
		$parameters['source_number'] = $source_number;
		$row = $database->select($sql, $parameters, 'row');
		//view_array($row, false);
		if (empty($row)) { continue; }
		$domain_uuid = $row["domain_uuid"];
		$provider_uuid = $row["provider_uuid"];
		$destination_prefix = $row["destination_prefix"];
		$destination_number = $row["destination_number"];
		unset($parameters, $row);

		//get the provider settings
		$sql = "select provider_setting_category, provider_setting_subcategory, ";
		$sql .= "provider_setting_name, provider_setting_value, provider_setting_order \n";
		$sql .= "from v_provider_settings \n";
		$sql .= "where provider_uuid = :provider_uuid \n";
		$sql .= "and provider_setting_category = 'outbound' \n";
		$sql .= "and provider_setting_subcategory = 'format' \n";
		$sql .= "and provider_setting_enabled = 'true'; \n";
		$parameters['provider_uuid'] = $provider_uuid;
		$provider_settings = $database->select($sql, $parameters, 'all');
		if (isset($_GET['debug'])) {
			echo $sql;
			print_r($parameters);
			print_r($provider_settings);
			echo "\n";
		}
		unset($parameters);

		//process the provider settings array
		foreach ($provider_settings as $row) {
			//format the phone numbers
			if ($row['provider_setting_name'] == 'message_from') {
				$from = format_string($row['provider_setting_value'], $destination_number);
			}
			if ($row['provider_setting_name'] == 'message_to') {
				$to = format_string($row['provider_setting_value'], $to);
			}
		}

		//if any of these are empty then skip the rest of the current loop
		if (empty($from) || empty($to) || empty($message_content)) {
			usleep(10000);
			continue;
		}

		//get the user_uuid - needed for sending the message
		$sql = "select user_uuid from v_extension_users \n";
		$sql .= "where extension_uuid in ( \n";
		$sql .= "	select extension_uuid \n";
		$sql .= "	from v_extensions \n";
		$sql .= "	where extension = :extension \n";
		$sql .= "	and domain_uuid = :domain_uuid\n";
		$sql .= ");\n";
		$parameters['extension'] = $from_user;
		$parameters['domain_uuid'] = $domain_uuid;
		$row = $database->select($sql, $parameters, 'row');
		//view_array($row, false);
		if (!empty($row)) {
			$user_uuid = $row["user_uuid"];
		}
		unset($parameters);

		//send the message using the message queue
		$array['message_queue'][0]['domain_uuid'] = $domain_uuid;
		$array['message_queue'][0]['message_queue_uuid'] = uuid();
		$array['message_queue'][0]['user_uuid'] = $user_uuid;
		//$array['message_queue'][0]['contact_uuid'] = $contact_uuid;
		$array['message_queue'][0]['provider_uuid'] = $provider_uuid;
		$array['message_queue'][0]['hostname'] = system('hostname');
		$array['message_queue'][0]['message_status'] = 'waiting';
		$array['message_queue'][0]['message_type'] = $message_type;
		$array['message_queue'][0]['message_direction'] = 'outbound';
		$array['message_queue'][0]['message_date'] = 'now()';
		$array['message_queue'][0]['message_from'] = $from;
		$array['message_queue'][0]['message_to'] = $to;
		$array['message_queue'][0]['message_text'] = $message_content;
		//view_array($array);

		//build message media array (if necessary)
		//$p = permissions::new();
		//if (is_array($message_media) && @sizeof($message_media) != 0) {
		//	foreach($message_media as $index => $media) {
		//		$array['message_media'][$index]['message_media_uuid'] = $media['uuid'];
		//		$array['message_media'][$index]['message_uuid'] = $message_uuid;
		//		$array['message_media'][$index]['domain_uuid'] = $_SESSION["domain_uuid"];
		//		$array['message_media'][$index]['user_uuid'] = $_SESSION["user_uuid"];
		//		$array['message_media'][$index]['message_media_type'] = strtolower(pathinfo($media['name'], PATHINFO_EXTENSION));
		//		$array['message_media'][$index]['message_media_url'] = $media['name'];
		//		$array['message_media'][$index]['message_media_content'] = base64_encode(file_get_contents($media['tmp_name']));
		//	}
		//	$p->add('message_media_add', 'temp');
		//	$message_media_exists = true;
		//}

		//save to the data
		$database->app_name = 'messages';
		$database->app_uuid = '4a20815d-042c-47c8-85df-085333e79b87';
		$message = $database->save($array, false);
		if ($debug) {
			print_r($message);
		}
		unset($array);

		// current memory
		//$memory_usage = memory_get_usage();

		// peak memory
		//$memory_peak = memory_get_peak_usage();

		//echo 'Current memory: ' . round($memory_usage / 1024) . " KB\n";
		//echo 'Peak memory: ' . round($memory_peak / 1024) . " KB\n\n";

		//slow down the loop to reduce cpu usage
		usleep(10000);
	}

//remove the permission
	$p->delete('message_queue_add', 'temp');

//remove the old pid file
	if (file_exists($file)) {
		unlink($pid_file);
	}

//save output to
	//$fp = fopen(sys_get_temp_dir()."/mailer-app.log", "a");

//prepare the output buffers
	//ob_end_clean();
	//ob_start();

//message divider for log file
	//echo "\n\n=========================================================================\n\n";

//get and save the output from the buffer
	//$content = ob_get_contents(); //get the output from the buffer
	//$content = str_replace("<br />", "", $content);

	//ob_end_clean(); //clean the buffer

	//fwrite($fp, $content);
	//fclose($fp);

//to do list
	// destination_number -> digits length the provider requires
	// local extension to extension messages
	// cross tenant messages on same server
	// cross server messages off the local server
	// test multiple registrations for the same extension
	// maybe merge into one server rather than multiple
	// use a queue for inbound messages don't send unless the SIP user is registered
	// group messages to multiple users
	// limit number of messages to the provider per second per source number
	// add media support for sending photos

//how to use this feature
	// cd /var/www/fusionpbx && /usr/bin/php /var/www/fusionpbx/app/messages/resources/service/message_events.php >> /dev/null 2>&1 &

?>
