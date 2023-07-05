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
	set_time_limit(0);
	ini_set('max_execution_time', 0);
	ini_set('memory_limit', '128M');

//save the arguments to variables
	$script_name = $argv[0];
	if (!empty($argv[1])) {
		parse_str($argv[1], $_GET);
	}
	//print_r($_GET);
	//echo __line__."\n";

//set the variables
	if (isset($_GET['hostname'])) {
		$hostname = urldecode($_GET['hostname']);
	}
	if (isset($_GET['debug'])) {
		$debug = $_GET['debug'];
	}
	$debug = false;

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

//get the messages waiting in the email queue
	while (true) {

		//get the messages that are waiting to send
		$sql = "select message_queue_uuid, message_direction, hostname from v_message_queue ";
		$sql .= "where message_status = 'waiting' ";
		$sql .= "and (message_type = 'sms' or message_type = 'mms') ";
		$sql .= "and hostname = :hostname ";
		$sql .= "and message_from is not null ";
		$sql .= "and message_to is not null ";
		$sql .= "order by domain_uuid asc ";
		$sql .= "limit 300; ";
		//echo $sql."\n";
		if (isset($hostname)) {
			$parameters['hostname'] = $hostname;
		}
		else {
			$parameters['hostname'] = gethostname();
		}
		//print_r($parameters);
		$database = new database;
		$message_queue = $database->select($sql, $parameters, 'all');
		//view_array($message_queue, false);
		unset($parameters);

		//process the messages
		if (is_array($message_queue) && @sizeof($message_queue) != 0) {
			foreach($message_queue as $row) {
				//direction inbound, send SIP messages to registered phones
				if ($row['message_direction'] == 'inbound') {
					$command = "/usr/bin/php /var/www/fusionpbx/app/messages/resources/service/message_send_inbound.php ";
					$command .= "'message_queue_uuid=".$row['message_queue_uuid']."&hostname=".$row['hostname']."'";
				}

				//direction outbound, send message to the provider
				if ($row['message_direction'] == 'outbound') {
					// /usr/bin/php /var/www/fusionpbx/app/messages/resources/service/message_send_outbound.php action=send&message_queue_uuid=&hostname=voip.fusionpbx.com
					$command = "/usr/bin/php /var/www/fusionpbx/app/messages/resources/service/message_send_outbound.php ";
					$command .= "'message_queue_uuid=".$row['message_queue_uuid']."&hostname=".$row['hostname']."'";
				}

				//send the command
				if (isset($command)) {
					if (isset($debug)) {
						//run process inline to see debug info
						echo $command."\n";
						$result = system($command);
						echo $result."\n";
					}
					else {
						//starts process rapidly doesn't wait for previous process to finish (used for production)
						$handle = popen($command." > /dev/null &", 'r'); 
						echo "'$handle'; " . gettype($handle) . "\n";
						$read = fread($handle, 2096);
						echo $read;
						pclose($handle);
					}
				}
				unset($command);
			}
		}

		//pause to prevent excessive database queries
		sleep(1);
	}

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
	//echo "\n\n=============================================================================================================================================\n\n";

//get and save the output from the buffer
	//$content = ob_get_contents(); //get the output from the buffer
	//$content = str_replace("<br />", "", $content);

	//ob_end_clean(); //clean the buffer

	//fwrite($fp, $content);
	//fclose($fp);

//notes
	//echo __line__."\n";
	// if not keeping the email then need to delete it after the voicemail is emailed

//how to use this feature
	// cd /var/www/fusionpbx && /usr/bin/php /var/www/fusionpbx/app/messages/resources/service/message_queue.php

?>
