<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2023
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
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