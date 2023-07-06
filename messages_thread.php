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
	if (!permission_exists('message_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//santize the contact number, allow the + to support e.164 format
	if (isset($_GET['number'])) {
		$number = preg_replace("/[^\+?0-9]/", "", $_GET['number']);
		$_SESSION['user']['contact_number'] = $number;
	}

//get the number from the php session
	if (isset($_SESSION['user']['contact_number'])) {
		$number = $_SESSION['user']['contact_number'];
	}

//get the contact uuid
	$contact_uuid = !empty($_GET['contact_uuid']) && is_uuid($_GET['contact_uuid']) ? $_GET['contact_uuid'] : null;

//get the limit
	if (isset($_SESSION['message']['limit']['numeric']) && is_numeric($_SESSION['message']['limit']['numeric'])) {
		$message_limit = $_SESSION['message']['limit']['numeric'];
	}
	else {
		$message_limit = 80;
	}

//build a list of groups the user is a member of to be used in a SQL in
	foreach($_SESSION['user']['groups'] as $group) {
		if (is_uuid($group['group_uuid'])) {
			$group_uuids[] =  $group['group_uuid'];
		}
	}
	$group_uuids_in = "'".implode("','", $group_uuids)."'";

//get the list of messages
	$sql = "select ";
	$sql .= "message_uuid, ";
	$sql .= "domain_uuid, ";
	$sql .= "user_uuid, ";
	$sql .= "contact_uuid, ";
	$sql .= "message_type, ";
	$sql .= "message_direction, ";
	if ($_SESSION['domain']['time_zone']['name'] != '') {
		$sql .= "message_date at time zone :time_zone as message_date, ";
	}
	else {
		$sql .= "message_date, ";
	}
	$sql .= "message_read, ";
	$sql .= "message_from, ";
	$sql .= "message_to, ";
	$sql .= "message_text ";
	$sql .= "from v_messages ";
	$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
	$sql .= "and ( ";
	$sql .= "	user_uuid = :user_uuid ";
	$sql .= "	or ";
	$sql .= "	group_uuid in (".$group_uuids_in.")";
	$sql .= ")\n";
	//$sql .= "and message_date > NOW() - INTERVAL '3 days' ";
	$sql .= "and (message_from = :message_number or message_to = :message_number) ";
	$sql .= "order by message_date desc ";
	$sql .= "limit :message_limit ";
	if ($_SESSION['domain']['time_zone']['name'] != '') {
		$parameters['time_zone'] = $_SESSION['domain']['time_zone']['name'];
	}
	$parameters['user_uuid'] = $_SESSION['user_uuid'];
	$parameters['domain_uuid'] = $domain_uuid;
	$parameters['message_number'] = $number ?? null;
	$parameters['message_limit'] = $message_limit;
	$database = new database;
	$messages = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

	if (is_array($messages) && @sizeof($messages) != 0) {
		$messages = array_reverse($messages);

		//get media (if any)
			$sql = "select ";
			$sql .= "message_uuid, ";
			$sql .= "message_media_uuid, ";
			$sql .= "message_media_type, ";
			$sql .= "length(decode(message_media_content,'base64')) as message_media_size ";
			$sql .= "from v_message_media ";
			$sql .= "where user_uuid = :user_uuid ";
			$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
			$sql .= "and ( ";
			foreach ($messages as $index => $message) {
				$message_uuids[] = "message_uuid = :message_uuid_".$index;
				$parameters['message_uuid_'.$index] = $message['message_uuid'];
			}
			$sql .= implode(' or ', $message_uuids);
			$sql .= ") ";
			$sql .= "and message_media_type <> 'txt' ";
			$parameters['user_uuid'] = $_SESSION['user_uuid'];
			$parameters['domain_uuid'] = $domain_uuid;
			$database = new database;
			$rows = $database->select($sql, $parameters, 'all');
			unset($sql, $parameters, $index);

		//prep media array
			if (is_array($rows) && @sizeof($rows) != 0) {
				foreach ($rows as $index => $row) {
					$message_media[$row['message_uuid']][$index]['uuid'] = $row['message_media_uuid'];
					$message_media[$row['message_uuid']][$index]['type'] = $row['message_media_type'];
					$message_media[$row['message_uuid']][$index]['size'] = $row['message_media_size'];
				}
			}
	}

//flag messages as read
	$sql = "update v_messages ";
	$sql .= "set message_read = 'true' ";
	$sql .= "where user_uuid = :user_uuid ";
	$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
	$sql .= "and (message_from like :message_number or message_to like :message_number) ";
	$parameters['user_uuid'] = $_SESSION['user_uuid'];
	$parameters['domain_uuid'] = $domain_uuid;
	$parameters['message_number'] = $number ?? null;
	$database = new database;
	$database->execute($sql, $parameters);
	unset($sql, $parameters);

//css styles
	echo "<style>\n";
	echo "	.message-bubble {\n";
	echo "		display: table;\n";
	echo "		padding: 10px;\n";
	echo "		border: 1px solid;\n";
	echo "		margin-bottom: 10px;\n";
	echo "		}\n";
//$_SESSION['dashboard']['chart_text_color']['text'];
	echo "	.message-bubble-em {\n";
	echo "		margin-right: 30%;\n";
	echo "		border-radius: 0 20px 20px 20px;\n";
	echo "		border-color: ".$_SESSION['theme']['message_bubble_em_border_color']['text'].";\n";
	echo "		background-color: ".$_SESSION['theme']['message_bubble_em_background_color']['text'].";\n";
	echo "		color: ".$_SESSION['theme']['message_bubble_em_text_color']['text'].";\n";
	echo "		clear: both;\n";
	echo "		}\n";

	echo "	.message-bubble-me {\n";
	echo "		float: right;\n";
	echo "		margin-left: 30%;\n";
	echo "		border-radius: 20px 20px 0 20px;\n";
	echo "		border-color: ".$_SESSION['theme']['message_bubble_me_border_color']['text'].";\n";
	echo "		background-color: ".$_SESSION['theme']['message_bubble_me_background_color']['text'].";\n";
	echo "		color: ".$_SESSION['theme']['message_bubble_me_text_color']['text'].";\n";
	echo "		clear: both;\n";
	echo "		}\n";

	echo "	img.message-bubble-image-em {\n";
	echo "		width: 100px;\n";
	echo "		height: auto;\n";
	echo "		border-radius: 0 11px 11px 11px;\n";
	echo "		border: 1px solid #cffec7;\n";
	echo "		}\n";

	echo "	img.message-bubble-image-me {\n";
	echo "		width: 100px;\n";
	echo "		height: auto;\n";
	echo "		border-radius: 11px 11px 0 11px;\n";
	echo "		border: 1px solid #cbf0ff;\n";
	echo "		}\n";

	echo "	div.message-bubble-image-em {\n";
	echo "		float: left;\n";
	echo "		margin-right: 15px;\n";
	echo "		text-align: left;\n";
	echo "		}\n";

	echo "	div.message-bubble-image-me {\n";
	echo "		float: right;\n";
	echo "		margin-left: 15px;\n";
	echo "		text-align: right;\n";
	echo "		}\n";

	echo "	.message-text {\n";
	echo "		padding-bottom: 5px;\n";
	echo "		font-size: 90%;\n";
	echo "		}\n";

	echo "	.message-bubble-when {\n";
	echo "		font-size: 71%;\n";
	echo "		font-style: italic;\n";
	echo "		}\n";

	echo "	.message-media-link-em {\n";
	echo "		display: inline-block;\n";
	echo "		margin: 5px 10px 5px 0;\n";
	echo "		padding: 8px;\n";
	echo "		background: #cffec7;\n";
	echo "		border-radius: 7px;\n";
	echo "		text-align: center;\n";
	echo "		}\n";

	echo "	.message-media-link-me {\n";
	echo "		display: inline-block;\n";
	echo "		margin: 5px 10px 5px 0;\n";
	echo "		padding: 8px;\n";
	echo "		background: #cbf0ff;\n";
	echo "		border-radius: 7px;\n";
	echo "		text-align: center;\n";
	echo "		}\n";

	echo "</style>\n";

	//display media
	echo "<script language='JavaScript' type='text/javascript'>\n";
	echo "	function display_media(id, src) {\n";
	echo "		$('#message_media_layer').load('message_media.php?id=' + id + '&src=' + src + '&action=display', function(){\n";
	echo "			$('#message_media_layer').fadeIn(200);\n";
	echo "		});\n";
	echo "	}\n";
	echo "</script>\n";

	//message media layer
	echo "<div id='message_media_layer' style='display: none;'></div>\n";

	if (empty($refresh) || !$refresh) {
		echo "<div id='thread_messages' style='min-height: 300px; overflow: auto; padding-right: 15px;'>\n";
	}

	//output messages
		if (is_array($messages) && @sizeof($messages) != 0) {
			foreach ($messages as $message) {
				//parse from message
					if ($message['message_direction'] == 'inbound') {
						$message_from = $message['message_to'];
						$media_source = format_phone($message['message_from']);
					}
					if ($message['message_direction'] == 'outbound') {
						$message_from = $message['message_from'];
						$media_source = format_phone($message['message_to']);
					}

				//message bubble
					echo "<span class='message-bubble message-bubble-".($message['message_direction'] == 'inbound' ? 'em' : 'me')."'>";
						//contact image em
							if ($message['message_direction'] == 'inbound') {
								if (is_array($_SESSION['tmp']['messages']['contact_em'][$contact_uuid]) && @sizeof($_SESSION['tmp']['messages']['contact_em'][$contact_uuid]) != 0) {
									echo "<div class='message-bubble-image-em'>\n";
									echo "	<img class='message-bubble-image-em'><br />\n";
									echo "</div>\n";
								}
							}
						//contact image me
							else {
								if (is_array($_SESSION['tmp']['messages']['contact_me']) && @sizeof($_SESSION['tmp']['messages']['contact_me']) != 0) {
									echo "<div class='message-bubble-image-me'>\n";
									echo "	<img class='message-bubble-image-me'><br />\n";
									echo "</div>\n";
								}
							}
						echo "<div style='display: table;'>\n";
						//message
							if ($message['message_text'] != '') {
								$allowed = ['http', 'https'];
								$scheme = parse_url($message['message_text'], PHP_URL_SCHEME);
								if ($scheme === false) {
									// seriously malformed URL
									$is_url = false;
								}
								else if (!in_array($scheme, $allowed, true)) {
									// protocol not allowed, don't display the link!
									$is_url = false;
								}
								else {
									// everything OK
									$is_url = true;
								}
								if ($is_url) {
									echo "<div class='message-text'><a href='".$message['message_text']."' target='_blank'>".escape($message['message_text'])."</a></div>\n";
								}
								else {
									echo "<div class='message-text'>".str_replace("\n",'<br />',escape($message['message_text']))."</div>\n";
								}
							}
						//attachments
							if (is_array($message_media[$message['message_uuid']]) && @sizeof($message_media[$message['message_uuid']]) != 0) {
								foreach ($message_media[$message['message_uuid']] as $media) {
									if ($media['type'] != 'txt') {
										if ($media['type'] == 'jpg' || $media['type'] == 'jpeg' || $media['type'] == 'gif' || $media['type'] == 'png') {
											echo "<a href='message_media.php?id=".$media['uuid']."&src=".$media_source."&action=download' class='message-media-link-".($message['message_direction'] == 'inbound' ? 'em' : 'me')."'>";
										}
										echo "<img src='message_media.php?id=".$media['uuid']."&src=".$media_source."&action=thumbnail&width=200' style='border: none; margin-right: 10px;'><br />\n";
										//echo "<img src='resources/images/attachment.png' style='width: 16px; height: 16px; border: none; margin-right: 10px;'>";
										echo "<span style='font-size: 85%; white-space: nowrap;'>".strtoupper($media['type']).' &middot; '.strtoupper(byte_convert($media['size']))."</span>";
										echo "</a>\n";
									}
								}
								echo "<br />\n";
							}
						//message when
							echo "<span class='message-bubble-when'>".(date('m-d-Y') != format_when_local($message['message_date'],'d') ? format_when_local($message['message_date']) : format_when_local($message['message_date'],'t'))."</span>\n";
						echo "</div>\n";
					echo "</span>\n";
			}
			echo "<span id='thread_bottom'></span>\n";
		}

		echo "<script>\n";
		//set current contact
		//	echo "	$('#contact_current_number').val('".$number."');\n";
		//set bubble contact images from src images
		//	echo "	$('img.message-bubble-image-em').attr('src', $('img#src_message-bubble-image-em_".$contact_uuid."').attr('src'));\n";
		//	echo "	$('img.message-bubble-image-me').attr('src', $('img#src_message-bubble-image-me').attr('src'));\n";
		echo "</script>\n";

	if (empty($refresh) || !$refresh) {
		echo "</div>\n";
	}

?>