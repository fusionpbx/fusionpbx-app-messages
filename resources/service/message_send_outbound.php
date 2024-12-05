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

//includes files
	require_once "resources/require.php";
	include "resources/classes/cache.php";
	include "resources/classes/permissions.php";

//connect to the database
	$database = new database;

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
	if (is_uuid($_GET['message_queue_uuid'])) {
		$message_queue_uuid = $_GET['message_queue_uuid'];
		$message_uuid = $message_queue_uuid;
	}
	if (isset($debug)) {
		print_r($_GET);
		echo "message_queue_uuid ".$message_queue_uuid."\n";
		echo "hostname ".$hostname."\n";
	}

//set the hostname if it wasn't provided
	if (!isset($hostname) && strlen($hostname) == 0) {
		$hostname = system('hostname');
	}

//get the message details to send
	$sql = "select * from v_message_queue ";
	$sql .= "where message_queue_uuid = :message_queue_uuid ";
	//$sql .= "and hostname = :hostname ";
	//$parameters['hostname'] = $hostname;
	$parameters['message_queue_uuid'] = $message_queue_uuid;
	$row = $database->select($sql, $parameters, 'row');
	if (isset($debug)) {
		view_array($row, false);
	}
	if (is_array($row)) {
		$domain_uuid = $row["domain_uuid"];
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

//get the message media
	$sql = "select * from v_message_media ";
	$sql .= "where message_uuid = :message_uuid ";
	//$sql .= "and hostname = :hostname ";
	//$parameters['hostname'] = $hostname;
	$parameters['message_uuid'] = $message_uuid;
	$database = new database;
	$message_media = $database->select($sql, $parameters, 'all');
	//view_array($message_media, false);
	unset($parameters);

//version 2
	function get_value($data, $path) {
		$keys = explode('.', $path);
		foreach ($keys as $key) {
			$data = $data[$key];
		}
		return $data;
	}

//get the values from the message array using the provider settings
	//$message_from = get_value($message, $setting['message_from']);
	//$message_to = get_value($message, $setting['message_to']);
	//$message_content = get_value($message, $setting['message_content']);

//debug info
	//if ($debug) {
	//	file_put_contents($log_file, "from: ".$message_from."\n", FILE_APPEND);
	//	file_put_contents($log_file, "to: ".$message_to."\n", FILE_APPEND);
	//	file_put_contents($log_file, "content: ".$message_content."\n", FILE_APPEND);
	//}

//build an array from dot notation
	//$setting['data.attributes.from'] = 'markjcrane@fusionpbx.com';
	//$setting['data.attributes.to'] = 'anthony@fusionpbx.com';
	function build_array($array, $path, $value) {
		$a = explode('.', $path);
		if (count($a) == 1) {
			$array[$a[0]] = $value;
		}
		if (count($a) == 2) {
			$array[$a[0]][$a[1]] = $value;
		}
		if (count($a) == 3) {
			$array[$a[0]][$a[1]][$a[2]] = $value;
		}
		if (count($a) == 4) {
			$array[$a[0]][$a[1]][$a[2]][$a[3]] = $value;
		}
		if (count($a) == 5) {
			$array[$a[0]][$a[1]][$a[2]][$a[3]][$a[4]] = $value;
		}
		return $array;
	}

//send context to the temp log
	//echo "Subject: ".$email_subject."\n";
	//echo "From: ".$email_from."\n";
	//echo "Reply-to: ".$email_from."\n";
	//echo "To: ".$email_to."\n";
	//echo "Date: ".$email_date."\n";
	//echo "Transcript: ".$array['message']."\n";
	//echo "Body: ".$email_body."\n";

//get the provider settings
	$sql = "select provider_setting_category, provider_setting_subcategory, provider_setting_type, provider_setting_name, provider_setting_value, provider_setting_order \n";
	$sql .= "from v_provider_settings \n";
	$sql .= "where provider_uuid = :provider_uuid \n";
	$sql .= "and provider_setting_category = 'outbound' \n";
	$sql .= "and provider_setting_enabled = 'true'; \n";
	$parameters['provider_uuid'] = $provider_uuid;
	$provider_settings = $database->select($sql, $parameters, 'all');
	foreach ($provider_settings as $row) {
		//set the content array
		if ($row['provider_setting_subcategory'] == 'content') {
			$content[$row['provider_setting_name']] = $row['provider_setting_value'];
		}
		
		//set the format array
		if ($row['provider_setting_subcategory'] == 'format') {
			$format[$row['provider_setting_name']] = $row['provider_setting_value'];
		}

		//build the settings array
		$setting[$row['provider_setting_name']] = $row['provider_setting_value'];

		//set the message to type
		if ($row['provider_setting_name'] == 'message_to') {
			if ($row['provider_setting_type'] == 'array') {
				$message_to_type = 'array';
			}
			else {
				$message_to_type = 'text';
			}
		}

	}
	unset($parameters);
	//echo $sql;
	//print_r($parameters);
	//print_r($provider_settings, false);
	//echo "\n";

//format the phone numbers
	if($message_type == 'mms') {
		//check if message_media formats are defined and non-empty, and if so, use those instead of default formats
		if (isset($format['message_media_message_from']) && !empty($format['message_media_message_from'])) {
			$message_from = format_string($format['message_media_message_from'], $message_from);
		} 
		elseif (isset($format['message_from'])) {
			$message_from = format_string($format['message_from'], $message_from);
		}

		if (isset($format['message_media_message_to']) && !empty($format['message_media_message_to'])) {
			$message_to = format_string($format['message_media_message_to'], $message_to);
		} 
		elseif (isset($format['message_to'])) {
			$message_to = format_string($format['message_to'], $message_to);
		}
	} 
	else {
		//default formats. If setting is defined but format string is left blank, the format_string function 
		//will return the data as is (No changes made)
		if (isset($format['message_from'])) {
			$message_from = format_string($format['message_from'], $message_from);
		}

		if (isset($format['message_to'])) {
			$message_to = format_string($format['message_to'], $message_to);
		}
	}

//set http_method
	if ($message_type == 'mms' && isset($setting['message_media_http_method']) && !empty($setting['message_media_http_method'])) {
		$http_method = strtolower($setting['message_media_http_method']);
	}
	else {
		$http_method = strtolower($setting['http_method']);
	}
	if (empty($http_method)) {
		$http_method = 'POST';
	}

//get the content location for the destination number
	if (isset($setting['type'])) {
		$content_type = strtolower($setting['type']);
	}
	
//set the content_type
	if ($message_type == 'mms' && isset($setting['message_media_content_type']) && !empty($setting['message_media_content_type'])) {
		$content_type = strtolower($setting['message_media_content_type']);
	}
	else {
		$content_type = strtolower($setting['content_type']);
	}

	if (empty($content_type)) {
		$content_type = 'post';
	}

//send information to the console
	if (isset($debug)) {
		echo "content_type $content_type\n";
		echo "message_from $message_from\n";
		echo "message_to $message_to\n";
		echo "message_text $message_text\n";
	}

	if ($message_type == 'mms' && isset($content['message_media_message_from']) && !empty($content['message_media_message_from'])) {
		$outbound_array = build_array($outbound_array ?? [], $content['message_media_message_from'], $message_from);
	}
	elseif (isset($content['message_from']) && !empty($content['message_from'])) {
		$outbound_array = build_array($outbound_array ?? [], $content['message_from'], $message_from);
	}

	if ($message_type == 'mms' && isset($content['message_media_message_to']) && !empty($content['message_media_message_to'])) {
		if ($message_to_type == 'array') {
			// message to json type: array
			$outbound_array = build_array($outbound_array ?? [], $content['message_media_message_to'], explode(",", $message_to));
		}
		else {
			// message to json type: text
			$outbound_array = build_array($outbound_array ?? [], $content['message_media_message_to'], $message_to);
		}
	}
	else {
		if ($message_to_type == 'array') {
			// message to json type: array
			$outbound_array = build_array($outbound_array ?? [], $content['message_to'], explode(",", $message_to));
		}
		else {
			// message to json type: text
			$outbound_array = build_array($outbound_array ?? [], $content['message_to'], $message_to);
		}
	}

	if ($message_type == 'mms' && isset($content['message_media_message_content']) && !empty($content['message_media_message_content'])) {
		$outbound_array = build_array($outbound_array ?? [], $content['message_media_message_content'], $message_text);
	}
	else {
		$outbound_array = build_array($outbound_array ?? [], $content['message_content'], $message_text);
	}

	foreach ($provider_settings as $row) {
		if ($row['provider_setting_subcategory'] == 'content') {
			if ($row['provider_setting_name'] == 'message_other') {
				$outbound_array = build_array($outbound_array ?? [], explode('=', $row['provider_setting_value'])[0], explode('=', $row['provider_setting_value'])[1]);
			}
			if (is_array($message_media) && @sizeof($message_media) != 0) { 
				if ($row['provider_setting_name'] == 'message_media_other') {
					$outbound_array = build_array($outbound_array ?? [], explode('=', $row['provider_setting_value'])[0], explode('=', $row['provider_setting_value'])[1]);
				}

				if ($row['provider_setting_name'] == 'message_media_url') { 
					foreach($message_media as $index => $media) {
						$outbound_array = build_array($outbound_array ?? [], $row['provider_setting_value'].".".$index, urldecode($media['message_media_url']));
					}	
				}
			}
		}
	}

//log info
	//view_array($provider_settings, false);
	//echo "value ".$value."\n";
	// view_array($outbound_array, false);
	//view_array($setting, false);
	/*
	view_array($outbound_array, false);
	exit;
	$setting['http_destination']
	*/

//convert the array into json
	if ($content_type == 'json') {
		//view_array($outbound_array, false);
		$http_content = json_encode($outbound_array);
		//echo $http_content."\n";
	}

//convert fields into a http get or post query string
	if ($content_type == 'get' || $content_type == 'post') {
		//build the query string
		$x = 0;
		$query_string = '';
		foreach ($outbound_array as $key => $value) {
			if ($x != 0) { $query_string .= '&'; }
			if (is_array($value)){
				$y = 0;
				foreach($value as $v){
					if ($y != 0) { $query_string .= '&'; }
					$query_string .= $key.'='. urlencode($v);	
					$y++;
				}
			}
			else {
				$query_string .= $key.'='. urlencode($value);
			}
			$x++;
		}

		$http_content = $query_string;
	}
//exchange variable name with their values
	$http_destination = ($message_type == 'mms' && !empty($setting['message_media_http_destination'])) ? $setting['message_media_http_destination'] : $setting['http_destination'];
	$http_destination = str_replace("\${from}", urlencode($message_from), $http_destination);
	$http_destination = str_replace("\${message_from}", urlencode($message_from), $http_destination);
	$http_destination = str_replace("\${to}", urlencode($message_to), $http_destination);
	$http_destination = str_replace("\${message_to}", urlencode($message_to), $http_destination);
	$http_destination = str_replace("\${message_text}", urlencode($message_text), $http_destination);
	$http_destination = str_replace("\${http_auth_username}", urlencode($setting['http_auth_username']), $http_destination);
	$http_destination = str_replace("\${http_auth_password}", urlencode($setting['http_auth_password']), $http_destination);
	$setting['http_destination'] = $http_destination;

//logging info
	//view_array($setting, false);
	//view_array($outbound_array, false);

//build the message array
	$array['messages'][0]['domain_uuid'] = $domain_uuid;
	$array['messages'][0]['message_uuid'] = $message_uuid;
	$array['messages'][0]['user_uuid'] = $user_uuid;
	$array['messages'][0]['group_uuid'] = $group_uuid;
	//$array['messages'][0]['contact_uuid'] = $contact_uuid;
	$array['messages'][0]['provider_uuid'] = $provider_uuid;
	$array['messages'][0]['hostname'] = $hostname;
	$array['messages'][0]['message_type'] = $message_type;
	$array['messages'][0]['message_direction'] = $message_direction;
	$array['messages'][0]['message_date'] = $message_date;
	$array['messages'][0]['message_from'] = $message_from;
	$array['messages'][0]['message_to'] = $message_to;
	$array['messages'][0]['message_text'] = $message_text;
	$array['messages'][0]['message_json'] = $http_content;

//add permissions
	$p = permissions::new();
	$p->add('message_add', 'temp');
	$p->add('message_edit', 'temp');
	$p->add('message_media_add', 'temp');
	$p->add('message_media_edit', 'temp');

//save to the database
	$database = new database;
	$database->app_name = 'messages';
	$database->app_uuid = '4a20815d-042c-47c8-85df-085333e79b87';
	$database->save($array, false);
	view_array($database->message, false);
	unset($array);

//remove any temporary permissions
	$p->delete('message_add', 'temp');
	$p->delete('message_edit', 'temp');
	$p->delete('message_media_add', 'temp');
	$p->delete('message_media_edit', 'temp');

//send the message - working
/*
	$cmd = "curl ".	$http_destination;." -X ".$setting['http_method']." ";
	if (isset($setting['http_auth_username']) && isset($setting['http_auth_password'])) {
		$cmd .= "-u ".$setting['http_auth_username'].":".$setting['http_auth_password']." ";
	}
	foreach ($provider_settings as $row) {
		if ($row['provider_setting_subcategory'] == 'header' && $row['provider_setting_name'] != 'http_content_type') {
			$cmd .= "-H '".$row['provider_setting_name'].": ".$row['provider_setting_value']."' ";
		}
	}
	if (isset($setting['http_content_type'])) {
		$cmd .= "-H 'Content-Type: ".$setting['http_content_type']."' ";
	}
	$cmd .= "-d '$http_content' ";
	echo $cmd."\n";
	$result = system($cmd);
	$message_debug = $cmd;
	//echo $result."\n";
*/

//prepare the http headers
	if (isset($setting['http_content_type'])) {
		$http_headers[] = 'Content-Type: '.$setting['http_content_type'];
	}
	foreach ($provider_settings as $row) {
		if ($row['provider_setting_subcategory'] == 'header' && $row['provider_setting_name'] != 'http_content_type') {
			$http_headers[] = $row['provider_setting_name'].": ".$row['provider_setting_value'];
		}
	}
	//print_r($provider_settings);
	//echo "headers_array\n";
	//print_r($http_headers);

	if (isset($debug)) {
		echo "http_content: ".print_r($http_content, true)."\n";
		echo "http_destination: {$setting['http_destination']} \n";
		echo "http_username: {$setting['http_auth_username']} \n";
		echo "http_token: {$setting['http_auth_password']} \n";
		echo "http_method: $http_method \n";
	}

//create the curl resource
	$ch = curl_init();

//set the curl options
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	curl_setopt($ch, CURLOPT_URL, $setting['http_destination']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	if (isset($setting['http_auth_username']) && isset($setting['http_auth_password'])) {
		curl_setopt($ch, CURLOPT_USERPWD, $setting['http_auth_username'] . ':' . $setting['http_auth_password']);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC); //set the authentication type
	}
	if (isset($http_headers) && is_array($http_headers)) {
		curl_setopt($ch, CURLOPT_HTTPHEADER, $http_headers);
	}
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($http_method));
	curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $http_content); // file_get_contents($file_path.'/'.$file_name));
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);	//The number of seconds to wait while trying to connect.
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);	//To follow any "Location: " header that the server sends as part of the HTTP header.
	curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);	//To automatically set the Referer: field in requests where it follows a Location: redirect.
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);	//The maximum number of seconds to allow cURL functions to execute.
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);	//To stop cURL from verifying the peer's certificate.
	curl_setopt($ch, CURLOPT_HEADER, 0); //hide the headers when set to 0

//execute the curl with the options
	$http_content = curl_exec($ch);
	echo $http_content."\n";

//return the error
	if (curl_errno($ch)) {
		$message_debug = curl_error($ch);
	}
	else {
		$message_debug = '';
	}

//close the curl resource
	curl_close($ch);

//working
/*
	$cmd = "curl https://api.flowroute.com/v2.1/messages -X POST ";
	$cmd .= "-u ".$setting['http_auth_username'].":".$setting['http_auth_password']." ";
	$cmd .= "-H 'Content-Type: application/vnd.api+json' ";
	$cmd .= "-d '$http_content' ";
	echo $cmd."\n";
	echo system($cmd);
*/

/*
//add permissions
	$p = permissions::new();
	$p->add('message_queue_add', 'temp');
	$p->add('message_queue_update', 'temp');

//build the message array
	$array['message_queue'][0]['domain_uuid'] = $domain_uuid;
	$array['message_queue'][0]['message_queue_uuid'] = $message_queue_uuid;
	$array['message_queue'][0]['message_status'] = 'sent';
	$array['message_queue'][0]['message_json'] = $http_content;
	$array['message_queue'][0]['message_debug'] = $message_debug;

//save to the data
	$database = new database;
	$database->app_name = 'messages';
	$database->app_uuid = '4a20815d-042c-47c8-85df-085333e79b87';
	$database->save($array, false);
	view_array($database->message, false);
	unset($array);

//remove any temporary permissions
	$p->delete('message_queue_add', 'temp');
	$p->delete('message_queue_update', 'temp');
*/

//set the message status to sent
	$sql = "update v_message_queue ";
	$sql .= "set message_status = 'sent', ";
	$sql .= "message_json = :message_json, ";
	$sql .= "message_debug = :message_debug ";
	$sql .= "where message_queue_uuid = :message_queue_uuid; ";
	$parameters['message_queue_uuid'] = $message_queue_uuid;
	$parameters['message_json'] = $http_content;
	$parameters['message_debug'] = $message_debug;
	$database->execute($sql, $parameters);
	unset($parameters);

//set the last message in the cache
	$cache = new cache;
	$cache->set("messages:user:last_message:".$user_uuid, date('r'));

//save the output
	//$fp = fopen(sys_get_temp_dir()."/messages.log", "a");

//prepare the output buffers
	//ob_end_clean();
	//ob_start();

//message divider for log file
	//echo "\n\n====================================================\n\n";

//get and save the output from the buffer
	//$content = ob_get_contents(); //get the output from the buffer
	//$content = str_replace("<br />", "", $content);

	//ob_end_clean(); //clean the buffer

	//fwrite($fp, $content);
	//fclose($fp);

//how to use it
	// php /var/www/fusionpbx/app/messages/resources/service/message_send_outbound.php message_queue_uuid=39402652-1475-49f8-8366-7889335edd6f&hostname=voip.fusionpbx.com

?>
