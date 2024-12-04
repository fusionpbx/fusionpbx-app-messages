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
	Portions created by the Initial Developer are Copyright (C) 2024
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (permission_exists('message_add') || permission_exists('message_edit')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//connect to the database
	$database = database::new();

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//action add or update
	if (!empty($_REQUEST["id"]) && is_uuid($_REQUEST["id"])) {
		$action = "update";
		$message_uuid = $_REQUEST["id"];
	}
	else {
		$action = "add";
	}

//get http post variables and set them to php variables
	if (!empty($_POST)) {
		$message_uuid = $_POST["message_uuid"];
		$user_uuid = $_POST["user_uuid"];
		$message_type = $_POST["message_type"];
		$message_direction = $_POST["message_direction"];
		$message_date = $_POST["message_date"];
		$message_from = $_POST["message_from"];
		$message_to = $_POST["message_to"];
		$message_text = $_POST["message_text"];
		$message_media_type = $_POST["message_media_type"];
		$message_media_url = $_POST["message_media_url"];
		$message_media_content = $_POST["message_media_content"];
		$message_json = $_POST["message_json"];
	}

//process the user data and save it to the database
	if (!empty($_POST) && empty($_POST["persistformvar"])) {

		//get the uuid from the POST
			if ($action == "update") {
				$message_uuid = $_POST["message_uuid"];
			}

		//process the http post data by submitted action
			if (!empty($_POST['action']) && isset($message_uuid) && is_uuid($message_uuid)) {
				$array[0]['checked'] = 'true';
				$array[0]['uuid'] = $message_uuid;

				switch ($_POST['action']) {
					case 'delete':
						if (permission_exists('message_delete')) {
							$obj = new messages;
							$obj->delete($array);
						}
						break;
				}

				header('Location: message_logs.php');
				exit;
			}

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: messages_log.php');
				exit;
			}

		//check for all required data
			$msg = '';
			if (empty($message_type)) { $msg .= $text['message-required']." ".$text['label-message_type']."<br>\n"; }
			if (empty($message_direction)) { $msg .= $text['message-required']." ".$text['label-message_direction']."<br>\n"; }
			if (empty($message_date)) { $msg .= $text['message-required']." ".$text['label-message_date']."<br>\n"; }
			if (empty($message_from)) { $msg .= $text['message-required']." ".$text['label-message_from']."<br>\n"; }
			if (empty($message_to)) { $msg .= $text['message-required']." ".$text['label-message_to']."<br>\n"; }
			//if (empty($message_text)) { $msg .= $text['message-required']." ".$text['label-message_text']."<br>\n"; }
			//if (empty($message_media_type)) { $msg .= $text['message-required']." ".$text['label-message_media_type']."<br>\n"; }
			//if (empty($message_media_url)) { $msg .= $text['message-required']." ".$text['label-message_media_url']."<br>\n"; }
			//if (empty($message_media_content)) { $msg .= $text['message-required']." ".$text['label-message_media_content']."<br>\n"; }
			//if (empty($message_json)) { $msg .= $text['message-required']." ".$text['label-message_json']."<br>\n"; }
			if (!empty($msg) && empty($_POST["persistformvar"])) {
				require_once "resources/header.php";
				require_once "resources/persist_form_var.php";
				echo "<div align='center'>\n";
				echo "<table><tr><td>\n";
				echo $msg."<br />";
				echo "</td></tr></table>\n";
				persistformvar($_POST);
				echo "</div>\n";
				require_once "resources/footer.php";
				return;
			}

		//add the message_uuid
			if (empty($_POST["message_uuid"]) || !is_uuid($_POST["message_uuid"])) {
				$message_uuid = uuid();
			}

		//prepare the array
			$array['messages'][0]['domain_uuid'] = $_SESSION["domain_uuid"];;
			$array['messages'][0]['user_uuid'] = $user_uuid;
			$array['messages'][0]['message_uuid'] = $message_uuid;
			$array['messages'][0]['message_type'] = $message_type;
			$array['messages'][0]['message_direction'] = $message_direction;
			$array['messages'][0]['message_date'] = $message_date;
			$array['messages'][0]['message_from'] = $message_from;
			$array['messages'][0]['message_to'] = $message_to;
			$array['messages'][0]['message_text'] = $message_text;
			$array['messages'][0]['message_uuid'] = $message_uuid;

		//save to the data
			$database->app_name = 'messages';
			$database->app_uuid = '4a20815d-042c-47c8-85df-085333e79b87';
			$database->save($array);

		//redirect the user
			if (isset($action)) {
				if ($action == "add") {
					message::add($text['message-add']);
				}
				if ($action == "update") {
					message::add($text['message-update']);
				}
				header('Location: message_edit.php?id='.$message_uuid);
				exit;
			}
	}

//pre-populate the form
	if (!empty($_GET["id"]) && empty($_POST["persistformvar"])) {
		$message_uuid = $_GET["id"];
		$sql = "select * from v_messages ";
		$sql .= "where message_uuid = :message_uuid ";
		$parameters['message_uuid'] = $message_uuid;
		$row = $database->select($sql, $parameters, 'row');
		if (!empty($row) && @sizeof($row) != 0) {
			$user_uuid = $row["user_uuid"];
			$message_type = $row["message_type"];
			$message_direction = $row["message_direction"];
			$message_date = $row["message_date"];
			$message_from = $row["message_from"];
			$message_to = $row["message_to"];
			$message_text = $row["message_text"];
			$message_media_type = $row["message_media_type"] ?? '';
			$message_media_url = $row["message_media_url"] ?? '';
			$message_media_content = $row["message_media_content"] ?? '';
			$message_json = $row["message_json"];
		}
		unset($sql, $parameters);
	}

//get the users
	$sql = "select user_uuid, username from v_users ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "and user_enabled = 'true' ";
	$sql .= "order by username asc ";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$users = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = $text['title-message'];
	require_once "resources/header.php";

//show the content
	echo "<form name='frm' id='frm' method='post'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-message']."</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','link'=>'message_logs.php']);
	if ($action == 'update' && permission_exists('message_delete')) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'name'=>'btn_delete','style'=>'margin-left: 15px;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','style'=>'margin-left: 15px;']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if ($action == 'update' && permission_exists('message_delete')) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'submit','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','name'=>'action','value'=>'delete','onclick'=>"modal_close();"])]);
	}

	echo "<div class='card'>\n";
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td width='30%' class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-username']."\n";
	echo "</td>\n";
	echo "<td width='70%' class='vtable' style='position: relative;' align='left'>\n";
	echo "	<select class='formfld' name='user_uuid'>\n";
	echo "		<option value=''></option>\n";
	if (!empty($users)) {
		foreach($users as $row) {
			echo "		<option value='".escape($row['user_uuid'])."' ".($row['user_uuid'] == $user_uuid ? "selected='selected'" : null).">".escape($row['username'])."</option>\n";
		}
	}
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-username']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-message_type']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "	<select class='formfld' name='message_type'>\n";
	echo "		<option value='sms' ".($message_type == 'sms' ? "selected='selected'" : null).">".$text['label-sms']."</option>\n";
	echo "		<option value='mms' ".($message_type == 'mms' ? "selected='selected'" : null).">".$text['label-mms']."</option>\n";
	echo "		<option value='chat' ".($message_type == 'chat' ? "selected='selected'" : null).">".$text['label-chat']."</option>\n";
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-message_type']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-message_direction']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "	<select class='formfld' name='message_direction'>\n";
	echo "		<option value='inbound' ".($message_direction == 'inbound' ? "selected='selected'" : null).">".$text['label-inbound']."</option>\n";
	echo "		<option value='outbound' ".($message_direction == 'outbound' ? "selected='selected'" : null).">".$text['label-outbound']."</option>\n";
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-message_direction']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-message_date']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "	<input class='formfld' type='text' name='message_date' maxlength='255' value=\"".escape($message_date)."\">\n";
	echo "<br />\n";
	echo $text['description-message_date']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-message_from']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "	<input class='formfld' type='text' name='message_from' maxlength='255' value=\"".escape($message_from)."\">\n";
	echo "<br />\n";
	echo $text['description-message_from']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-message_to']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "	<input class='formfld' type='text' name='message_to' maxlength='255' value=\"".escape($message_to)."\">\n";
	echo "<br />\n";
	echo $text['description-message_to']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-message_text']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "	<textarea class='formfld' style='min-width: 40%; height: 100px;' name='message_text'>".escape($message_text)."</textarea>\n";
	echo "<br />\n";
	echo $text['description-message_text']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	if (!empty($message_media_type)) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	&nbsp;\n";
		echo "</td>\n";
		echo "<td class='vtable' style='position: relative;' align='left'>\n";
		$image_source = 'data: '.mime_content_type($message_media_type).';base64,'.$message_media_content;
		echo "<img src='".$image_source."' width='100%'>";
		echo "<br />\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	if (!empty($_GET['debug']) && $_GET['debug'] == 'true') {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-message_media_type']."\n";
		echo "</td>\n";
		echo "<td class='vtable' style='position: relative;' align='left'>\n";
		echo "	<input class='formfld' type='text' name='message_media_type' maxlength='255' value=\"".escape($message_media_type)."\">\n";
		echo "<br />\n";
		echo $text['description-message_media_type']."\n";
		echo "</td>\n";
		echo "</tr>\n";

		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-message_media_url']."\n";
		echo "</td>\n";
		echo "<td class='vtable' style='position: relative;' align='left'>\n";
		echo "	<input class='formfld' type='text' name='message_media_url' maxlength='255' value=\"".escape($message_media_url)."\">\n";
		echo "<br />\n";
		echo $text['description-message_media_url']."\n";
		echo "</td>\n";
		echo "</tr>\n";

		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-message_media_content']."\n";
		echo "</td>\n";
		echo "<td class='vtable' style='position: relative;' align='left'>\n";
		echo "	<input class='formfld' type='text' name='message_media_content' maxlength='255' value=\"".escape($message_media_content)."\">\n";
		echo "<br />\n";
		echo $text['description-message_media_content']."\n";
		echo "</td>\n";
		echo "</tr>\n";

		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-message_json']."\n";
		echo "</td>\n";
		echo "<td class='vtable' style='position: relative;' align='left'>\n";
		echo "	<textarea class='formfld' style='min-width: 40%; height: 100px;' name='message_json'>".escape($message_json)."</textarea>\n";
		echo "<br />\n";
		echo $text['description-message_json']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	echo "</table>\n";
	echo "</div>\n";
	echo "<br /><br />\n";

	echo "<input type='hidden' name='message_uuid' value='".escape($message_uuid)."'>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>
