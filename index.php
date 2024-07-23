<?php

//set the include path
	$conf = glob("{/usr/local/etc,/etc}/fusionpbx/config.conf", GLOB_BRACE);
	set_include_path(parse_ini_file($conf[0])['document.root']);

//includes files
	require_once "resources/require.php";
	require_once "resources/functions.php";
	require_once "resources/pdo.php";

//debug
	if ($_SESSION['message']['debug']['boolean'] == 'true') {
		$debug = true;
	}
	else {
		$debug = false;
	}

//log file
	$log_file = '/tmp/message.log';

//write the remote address to the log
	if ($debug) {
		file_put_contents($log_file, "Remote Address: ".$_SERVER['REMOTE_ADDR']."\n", FILE_APPEND);
	}

//get the user settings
	$sql = "select provider_uuid, provider_address_cidr ";
	$sql .= "from v_provider_addresses ";
	$sql .= "where provider_address_cidr is not null ";
	$sql .= "and provider_address_enabled = true ";
	$parameters = null;
	$database = new database;
	$provider_addresses = $database->select($sql, $parameters, 'all');

//default authorized to false
	$authorized = false;

//use the ip address to get the provider uuid and determine if request is authorized
	foreach($provider_addresses as $row) {
		if (check_cidr($row['provider_address_cidr'], $_SERVER['REMOTE_ADDR'])) {
			$provider_uuid = $row['provider_uuid'];
			$authorized = true;
			break;
		}
	}

//authorization failed
	if ($authorized) {
		if ($debug) {
			file_put_contents($log_file, "authorized\n", FILE_APPEND);
			file_put_contents($log_file, "provider_uuid ".$provider_uuid."\n", FILE_APPEND);
		}
	}
	else {
		//log the failed auth attempt to the system, to be available for fail2ban.
			if ($debug) {
				file_put_contents($log_file, "unauthorized\n", FILE_APPEND);
			}
			openlog('FusionPBX', LOG_NDELAY, LOG_AUTH);
			syslog(LOG_WARNING, '['.$_SERVER['REMOTE_ADDR']."] authentication failed for ".($_GET['key'] ?? ''));
			closelog();

		//send http 403
			header("HTTP/1.0 403 Forbidden");
			echo "Forbidden\n";
			exit();
	}

//check if string is url encoded
	function is_urlencoded($string) {
		$urlencoded = preg_match('~%[0-9A-F]{2}~i', $string);
		if ($urlencoded) {
			return true;
		}
		else {
			return false;
		}
	}

//use the provider uuid to get the provider_settings
	$sql = "select provider_setting_category, provider_setting_subcategory, \n";
	$sql .= "provider_setting_name, provider_setting_value, provider_setting_order \n";
	$sql .= "from v_provider_settings \n";
	$sql .= "where provider_uuid = :provider_uuid \n";
	$sql .= "and provider_setting_enabled = 'true' \n";
	$sql .= "and provider_setting_category = 'inbound' \n";
	$parameters['provider_uuid'] = $provider_uuid;
	$database = new database;
	$provider_settings = $database->select($sql, $parameters, 'all');
	foreach ($provider_settings as $row) {
		if ($row['provider_setting_subcategory'] == 'content') {
			$setting[$row['provider_setting_name']] = $row['provider_setting_value'];
		}
	}
	unset($parameters);
	//view_array($settings, false);

//set the default content type to json
	$content_type = 'json';

//get the content location for the destinaion numer
	if (isset($setting['content_type'])) {
		$content_type = strtolower($setting['content_type']);
	}

//get the message_type options: sms, mms
	if (isset($setting['message_type'])) {
		$message_type = strtolower($setting['message_type']);
	}
	else {
		$message_type = !empty($message_media_array) && is_array($message_media_array) ? 'mms' : 'sms';
	}

//get the raw input data
	//if  ($_SERVER['CONTENT_TYPE'] == 'application/json') {
		//show the content type
		if ($debug) {
			file_put_contents($log_file, "Server CONTENT_TYPE ".$_SERVER['CONTENT_TYPE']."\n", FILE_APPEND);
			file_put_contents($log_file, "content_type ".$content_type."\n", FILE_APPEND);
		}

		//get the content
		if ($content_type == 'json') {
			$message_json = file_get_contents("php://input");
		}
		if ($content_type == 'get') {
			$message_json = json_encode($_GET);
		}
		if ($content_type == 'post') {
			$message_json = json_encode($_POST);
		}

		//write content to the logs
		if ($debug) {
			if ($content_type == 'json') {
				file_put_contents($log_file, $message_json, FILE_APPEND);
			}
		}

		//$json = json_decode($message_content);
	//}

//save the http post to the log
	if ($debug) {
		if (count($_POST)) {
			file_put_contents($log_file, json_encode($_POST)."\n", FILE_APPEND);
		}
		if (count($_GET)) {
			file_put_contents($log_file, json_encode($_GET)."\n", FILE_APPEND);
		}
	}

//decode the json into array
	if ($content_type == 'json') {
		$message = json_decode($message_json, true);
	}

//ignore inbound delivery receipt - used by bulkvs
	if (isset($message['DeliveryReceipt']) && $message['DeliveryReceipt'] == 'true') {
		exit;
	}

//send print_r to the log
	//if ($debug) {
	//	file_put_contents($log_file, print_r($message, true)."\n", FILE_APPEND);
	//}

//get the content location for the destination number
	$message_to = $setting['content']['message_to'] ?? null;

//debug info
	if ($debug) {
		file_put_contents($log_file, "--------------\n", FILE_APPEND);
		file_put_contents($log_file, print_r($setting, true)."\n", FILE_APPEND);
		file_put_contents($log_file, "message_to $message_to\n", FILE_APPEND);
		file_put_contents($log_file, "--------------\n", FILE_APPEND);
	}

/*
	$setting['message_from'] => data.attributes.from
	$setting['message_to'] => data.attributes.to
	$setting['message_content'] => data.attributes.body

	$from_array = explode('.', $setting['message_from']);
	$to_array = explode('.', $setting['message_to']);
	$content_array = explode('.', $setting['message_content']);
*/

//version 3 - not working
	/*
	function get_value($array, $index) {
		$keys = explode('.', $index);
		foreach ($keys as segment) {
			if (is_array($array[$key])) {
				return get_value($array[$key], $key);
			}
			else {
				return $array[$key];
			}
		}
		//return $data;
	}
	*/

//version 2
	function get_value($data, $path) {
		$keys = explode('.', $path);
		foreach ($keys as $key) {
			$data = $data[$key];
		}
		return $data;
	}

/*
//version 1
	function get_value($data, $path) {
		$keys = explode('.', $path);
		if (count($keys) == 1) {
			return $data[$keys[0]];
		}
		if (count($keys) == 2) {
			return $data[$keys[0]][$keys[1]];
		}
		if (count($keys) == 3) {
			return $data[$keys[0]][$keys[1]][$keys[2]];
		}
		if (count($keys) == 4) {
			return $data[$key_array[0]][$keys[1]][$keys[2]][$keys[3]];
		}
		if (count($keys) == 5) {
			return $data[$keys[0]][$keys[1]][$keys[2]][$keys[3]][$keys[4]];
		}
	}
*/

/*
if (count($from_array) == 3) {
	$message_from = $message[$from_array[0]][$from_array[1]][$from_array[2]];
}
if (count($to_array) == 3) {
	$message_to = $message[$to_array[0]][$to_array[1]][$to_array[2]];
}
if (count($message_content) == 3) {
	$message_content = $message[$message_content[0]][$message_content[1]][$message_content[2]];
}
*/

//get the values from the message array using the provider settings
	if ($content_type == 'json') {
		$message_from = get_value($message, $setting['message_from']);
		$message_to = get_value($message, $setting['message_to']);
		$message_content = get_value($message, $setting['message_content']);
		$message_media_array = !empty($setting['message_media_array']) ? get_value($message, $setting['message_media_array']) : null;
	}
	if ($content_type == 'post') {
		$message_from = $_POST[$setting['message_from']];
		$message_to = $_POST[$setting['message_to']];
		$message_content = $_POST[$setting['message_content']];
		$message_media_array = !empty($setting['message_media_array']) ? $_POST[$setting['message_media_array']] : null;
	}
	if ($content_type == 'get') {
		$message_from = $_GET[$setting['message_from']];
		$message_to = $_GET[$setting['message_to']];
		$message_content = $_GET[$setting['message_content']];
		$message_media_array = !empty($setting['message_media_array']) ? $_GET[$setting['message_media_array']] : null;
	}

//message to is an array get first number in the array
	if (is_array($message_to)) {
		$message_to = $message_to['0'];
	}

//decode the content if it is encoded
	if (isset($message_content)) {
		if (is_urlencoded($message_content)) {
			$message_content = urldecode($message_content);
		}
	}

//format the phone numbers
	foreach ($provider_settings as $row) {
		if ($row['provider_setting_subcategory'] == 'format') {
			if ($row['provider_setting_name'] == 'message_from') {
				$message_from = format_string($row['provider_setting_value'], $message_from);
			}
			if ($row['provider_setting_name'] == 'message_to') {
				$message_to = format_string($row['provider_setting_value'], $message_to);
			}
		}
	}

//debug info
	if ($debug) {
		file_put_contents($log_file, "setting.message_from: ".$setting['message_from']."\n", FILE_APPEND);
		file_put_contents($log_file, "setting.message_to: ".$setting['message_to']."\n", FILE_APPEND);
		file_put_contents($log_file, "setting.message_content: ".$setting['message_content']."\n", FILE_APPEND);
		file_put_contents($log_file, "content_type: $content_type\n", FILE_APPEND);
		file_put_contents($log_file, "provider_uuid: $provider_uuid\n", FILE_APPEND);
		file_put_contents($log_file, "from: ".$message_from."\n", FILE_APPEND);
		file_put_contents($log_file, "to: ".$message_to."\n", FILE_APPEND);
		file_put_contents($log_file, "content: ".$message_content."\n", FILE_APPEND);
		file_put_contents($log_file, "message_media_array: ".print_r($message_media_array, true)."\n", FILE_APPEND);

	}

/*
()
[data] => Array
	(
		[attributes] => Array
			(
				[status] => delivered
				[body] => Ddd
				[direction] => inbound
				[amount_nanodollars] => 4000000
				[message_encoding] => 0
				[timestamp] => 2021-05-16T06:12:59.88Z
				[to] => 12089068227
				[amount_display] => $0.0040
				[from] => 12088058985
				[is_mms] => 
				[message_callback_url] => https://voip.fusionpbx.com/app/messages/index.php
				[message_type] => longcode
			)
		[type] => message
		[id] => mdr2-c3afc962b60d11ebb748aecb682882cc
	)

)
*/

//set the hostname if it wasn't provided
	$hostname = gethostname();

//get the source phone number
	$destination_number = preg_replace('{[\D]}', '', $message_to);

//use the phone number to get the destination details
	$sql = "SELECT * FROM v_destinations ";
	$sql .= "WHERE ( ";
	$sql .= "	destination_prefix || destination_area_code || destination_number = :destination_number ";
	$sql .= "	OR destination_trunk_prefix || destination_area_code || destination_number = :destination_number ";
	$sql .= "	OR destination_prefix || destination_number = :destination_number ";
	$sql .= "	OR '+' || destination_prefix || destination_number = :destination_number ";
	$sql .= "	OR '+' || destination_prefix || destination_area_code || destination_number = :destination_number ";
	$sql .= "	OR destination_area_code || destination_number = :destination_number ";
	$sql .= "	OR destination_number = :destination_number ";
	$sql .= ") ";
	$sql .= "and provider_uuid is not null ";
	$sql .= "and destination_enabled = 'true'; ";
	$parameters['destination_number'] = $destination_number;
	if ($debug) {
		file_put_contents($log_file, "sql: ".$sql."\n", FILE_APPEND);
		file_put_contents($log_file, print_r($parameters, true)."\n", FILE_APPEND);
	}
	$database = new database;
	$row = $database->select($sql, $parameters, 'row');
	$domain_uuid = $row['domain_uuid'];
	$user_uuid = $row['user_uuid'];
	$group_uuid = $row['group_uuid'];
	if ($debug) {
		file_put_contents($log_file, print_r($row, true)."\n", FILE_APPEND);
	}
	unset($sql, $parameters, $row);

//get the contact uuid
	$sql = "select c.contact_uuid ";
	$sql .= "from v_contacts as c, v_contact_phones as p ";
	$sql .= "where p.contact_uuid = c.contact_uuid ";
	$sql .= "and p.phone_number = :phone_number ";
	$sql .= "and c.domain_uuid = :domain_uuid ";
	$parameters['phone_number'] = $destination_number;
	$parameters['domain_uuid'] = $domain_uuid;
	$database = new database;
	$contact_uuid = $database->select($sql, $parameters, 'column');
	unset($sql, $parameters);

//add to the messages array
	$message_uuid = uuid();
	$array['messages'][0]['message_uuid'] = $message_uuid;
	$array['messages'][0]['domain_uuid'] = $domain_uuid;
	$array['messages'][0]['provider_uuid'] = $provider_uuid;
	if (is_uuid($user_uuid)) {
		$array['messages'][0]['user_uuid'] = $user_uuid;
	}
	if (is_uuid($group_uuid)) {
		$array['messages'][0]['group_uuid'] = $group_uuid;
	}
	if (is_uuid($contact_uuid)) {
		$array['messages'][0]['contact_uuid'] = $contact_uuid;
	}
	$array['messages'][0]['message_type'] = $message_type;
	$array['messages'][0]['message_direction'] = 'inbound';
	$array['messages'][0]['message_date'] = 'now()';
	$array['messages'][0]['message_from'] = $message_from;
	$array['messages'][0]['message_to'] = $message_to;
	$array['messages'][0]['message_text'] = $message_content;
	$array['messages'][0]['message_json'] = $message_json;

//add to message queue array
	$message_queue_uuid = uuid();
	$array['message_queue'][0]['message_queue_uuid'] = $message_queue_uuid;
	$array['message_queue'][0]['domain_uuid'] = $domain_uuid;
	if (is_uuid($user_uuid)) {
		$array['message_queue'][0]['user_uuid'] = $user_uuid;
	}
	if (is_uuid($group_uuid)) {
		$array['message_queue'][0]['group_uuid'] = $group_uuid;
	}
	$array['message_queue'][0]['provider_uuid'] = $provider_uuid;
	$array['message_queue'][0]['hostname'] = $hostname;
	if (is_uuid($contact_uuid)) {
		$array['message_queue'][0]['contact_uuid'] = $contact_uuid;
	}
	$array['message_queue'][0]['message_type'] = $message_type;
	$array['message_queue'][0]['message_direction'] = 'inbound';
	$array['message_queue'][0]['message_status'] = 'waiting';
	$array['message_queue'][0]['message_date'] = 'now()';
	$array['message_queue'][0]['message_from'] = $message_from;
	$array['message_queue'][0]['message_to'] = $message_to;
	$array['message_queue'][0]['message_text'] = $message_content;
	$array['message_queue'][0]['message_json'] = $message_json;

//add the required permission
	$p = new permissions;
	$p->add("message_add", "temp");
	$p->add("message_queue_add", "temp");
	$p->add("message_media_add", "temp");

//build message media array (if necessary)
	if (is_array($message_media_array)) {
		foreach($message_media_array as $index => $media_row) {
			//get the value out of the array using dot notation
			if (isset($setting['message_media_url'])) {
				$message_media_url = get_value($media_row, $setting['message_media_url']);
			}
			if (isset($setting['message_media_type'])) {
				$message_media_type = get_value($media_row, $setting['message_media_type']);
			}

			//get the file extension
			if (isset($message_media_type)) {
				if ($message_media_type == 'image/jpg') { $message_media_type = 'jpg'; }
				if ($message_media_type == 'image/jpeg') { $message_media_type = 'jpg'; }
				if ($message_media_type == 'image/png') { $message_media_type = 'png'; }
				if ($message_media_type == 'image/gif') { $message_media_type = 'gif'; }
			}

			//get the media url
			if (!isset($message_media_url)) {
				$message_media_url = $media_row;
			}

			//get the media type from the URL
			if (!isset($message_media_type)) {
				$message_media_type = pathinfo($message_media_url, PATHINFO_EXTENSION);
			}

			//get the file name from the URL
			if (!isset($message_media_name)) {
				$message_media_name = pathinfo($message_media_url, PATHINFO_FILENAME).'.'.$message_media_type;
			}

			if ($debug) {
				file_put_contents($log_file, "media_row: ".print_r($media_row, true)."\n", FILE_APPEND);
				file_put_contents($log_file, "message_media_url: ".$message_media_url."\n", FILE_APPEND);
				file_put_contents($log_file, "message_media_name: ".$message_media_name."\n", FILE_APPEND);
				file_put_contents($log_file, "message_media_type: ".$message_media_type."\n", FILE_APPEND);
			}

			//build the array for the media
			if ($message_media_type !== 'xml' && strlen($message_media_url) > 0) {
				$array['message_media'][$index]['message_media_uuid'] = uuid();
				$array['message_media'][$index]['message_uuid'] = $message_uuid;
				$array['message_media'][$index]['domain_uuid'] = $domain_uuid;
				$array['message_media'][$index]['user_uuid'] = $user_uuid;
				$array['message_media'][$index]['message_media_type'] = $message_media_type;

				$array['message_media'][$index]['message_media_name'] = $message_media_name;
				$array['message_media'][$index]['message_media_date'] = 'now()';

				$array['message_media'][$index]['message_media_url'] = $message_media_url;
				$array['message_media'][$index]['message_media_content'] = base64_encode(file_get_contents($message_media_url));
			}
		}
	}
	else {

		//get the value out of the array using dot notation
		if (isset($setting['message_media_url'])) {
			$message_media_url = get_value($message, $setting['message_media_url']);
		}
		if (isset($setting['message_media_type'])) {
			$message_media_type = get_value($message, $setting['message_media_type']);
		}

		//get the media type from the URL
		if (!isset($message_media_type) && !empty($message_media_url)) {
			$message_media_type = pathinfo($message_media_url, PATHINFO_EXTENSION);
		}

		//get the file extension
		if (!empty($message_media_type)) {
			if ($message_media_type == 'image/jpeg') { $message_media_type = 'jpg'; }
			if ($message_media_type == 'image/png') { $message_media_type = 'png'; }
			if ($message_media_type == 'image/gif') { $message_media_type = 'gif'; }
		}

		//build the array for the media
		if (!empty($message_media_url) && strlen($message_media_url) > 0 && $message_media_type !== 'xml') {
			$index = 0;
			$array['message_media'][$index]['message_media_uuid'] = uuid();
			$array['message_media'][$index]['message_uuid'] = $message_uuid;
			$array['message_media'][$index]['domain_uuid'] = $domain_uuid;
			$array['message_media'][$index]['user_uuid'] = $user_uuid;
			$array['message_media'][$index]['message_media_type'] = $message_media_type;
			$array['message_media'][$index]['message_media_url'] = $message_media_url;
			$array['message_media'][$index]['message_media_content'] = base64_encode(file_get_contents($message_media_url));
		}
	}
	//if ($debug) {
	//	file_put_contents($log_file, print_r($array, true), FILE_APPEND);
	//}

//save message to the database
	$database = new database;
	$database->app_name = 'messages';
	$database->app_uuid = '4a20815d-042c-47c8-85df-085333e79b87';
	$database->save($array, false);
	$result = $database->message;
	//if ($debug) {
	//	file_put_contents($log_file, print_r($result, true), FILE_APPEND);
	//}

//remove the temporary permission
	$p->delete("message_add", "temp");
	$p->delete("message_queue_add", "temp");
	$p->delete("message_media_add", "temp");

//convert the array to json
	//$array_json = json_encode($array);

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
	unset($sql, $parameters);

//send the sip message
	if (is_array($extensions) && @sizeof($extensions) != 0) {
		//create the event socket connection
		$fp = event_socket_create($_SESSION['event_socket_ip_address'], $_SESSION['event_socket_port'], $_SESSION['event_socket_password']);

		//loop through assigned extensions
		foreach ($extensions as $row) {
			//get variables from the array
			$domain_name = $row['domain_name'];
			$extension = $row['extension'];
			$number_alias = $row['number_alias'];

			//get the sip profile
			$command = "sofia_contact ".$extension."@".$domain_name;
			$response = event_socket_request($fp, "api ".$command);
			if ($response != 'error/user_not_registered') {
				$sip_profile = explode("/", $response)[1];
			}

			//send the sip messages
			//$command = "luarun app/messages/resources/send.lua ".$message["from"]."@".$domain_name." ".$extension."@".$domain_name."  '".$message["text"]."'";

			//$message_from_orig = $message_from;

			//original number with the domain name
			$message_from = $message_from .'@'.$domain_name;
			//$message_to_orig = $message_to;

			//send to the assigned extension(s)
			$message_to = $extension . '@'.$domain_name;
			//$message_to = '1005@voip.domain.com';

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
			$event .= "_body: ". $message_content;
			event_socket_request($fp, $event);
		}
	}

//set the file
	//$file = '/tmp/sms.txt';

//save the file
	//file_put_contents($file, $json);

//save the data to the file system
	//file_put_contents($file, $json."\n");
	//file_put_contents($file, $array_json."\nfrom: ".$message["from"]." to: ".$message["to"]." text: ".$message["text"]."\n$sql_test\njson: ".$json."\n".$saved_result."\n");

//send response to provider, if defined
	foreach ($provider_settings as $row) {
		if ($row['provider_setting_subcategory'] == 'response' && $row['provider_setting_name'] == 'message_content' && !empty($row['provider_setting_value'])) {
			$message_content = $row['provider_setting_value'];
			if ($debug) {
				file_put_contents($log_file, "Response...\n".$row['provider_setting_value']."\n\n", FILE_APPEND);
			}
			echo $row['provider_setting_value'];
			break;
		}
	}

?>
