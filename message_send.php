<?php

//set the include path
	$conf = glob("{/usr/local/etc,/etc}/fusionpbx/config.conf", GLOB_BRACE);
	set_include_path(parse_ini_file($conf[0])['document.root']);

//includes files
	require_once "resources/require.php";

//check permissions
	require_once "resources/check_auth.php";
	if (!permission_exists('message_add') && !permission_exists('message_edit')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get http post variables and set them to php variables
	if (is_array($_REQUEST)) {
		//$message_from = $_REQUEST["message_from"];
		//$message_to = $_REQUEST["message_to"];
		$message_from = urldecode($_REQUEST["message_from"]);
		$message_to = urldecode($_REQUEST["message_to"]);
		$message_text = $_REQUEST["message_text"];
		$message_media = $_FILES["message_media"];
	}

//translate the %2b to a '+' and its already a plus preserve the + rather than converting it into a space
	//if (isset($message_from)) {
	//	$message_from = str_replace('%2b', '+', $message_from);
	//}
	//if (isset($message_to)) {
	//	$message_to = str_replace('%2b', '+', $message_to);
	//}

//send the message
	if (count($_REQUEST) > 0 && strlen($_REQUEST["persistformvar"]) == 0) {
		$message = new messages;
		$message->send('sms', $message_from, $message_to, $message_text, $message_media);
	}

//URL to send
	// https://voip.fusionpbx.com/app/messages/message_send.php?message_from=12083334444&message_to=12083330000&message_text=bbb

?>
