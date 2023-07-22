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

//missing application - app/providers is required 
	if (!file_exists($_SERVER["PROJECT_ROOT"].'/app/providers/app_config.php')) {
		$document['title'] = $text['title-messages'];
		require_once "resources/header.php";
		echo "<br /><br />\n";

		echo "<div>\n";
		echo "	<div class='heading' style=\"text-align: center;\"><b>Missing Application</b></div>\n";
		echo "	<div style=\"text-align: center;\">\n";
		echo "	<br />\n";
		echo "	This feature requires the seperate <strong>Providers</strong> app to be installed.<br />\n";
		echo "	Please install it using the <strong>Application Manager</strong>.\n";
		echo "	</div>\n";
		echo "	<div style='clear: both;'></div>\n";
		echo "</div>\n";

		echo "<br /><br /><br /><br /><br /><br /><br /><br /><br /><br />\n";
		echo "<br /><br /><br /><br /><br /><br /><br /><br /><br /><br />\n";
		echo "<br /><br /><br /><br /><br /><br /><br /><br /><br /><br />\n";
		echo "<br /><br /><br /><br /><br /><br /><br /><br /><br /><br />\n";
		echo "<br /><br /><br /><br /><br /><br /><br /><br /><br /><br />\n";

		require_once "resources/footer.php";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get the message from
	$sql = "select destination_number from v_destinations ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "and user_uuid = :user_uuid ";
	$sql .= "and destination_type_text = 1 ";
	$sql .= "and destination_enabled = 'true' ";
	$sql .= "order by destination_number asc ";
	$parameters['domain_uuid'] = $domain_uuid;
	$parameters['user_uuid'] = $_SESSION['user']['user_uuid'];
	$database = new database;
	$rows = $database->select($sql, $parameters, 'all');
	if (is_array($rows) && @sizeof($rows)) {
		foreach ($rows as $row) {
		
			$destinations[] = $row['destination_number'];
		}
	}
	unset($sql, $parameters, $rows, $row);
	$message_from = $destinations[0] ?? null;
	//view_array($destinations);

//get the message to
	if (isset($_SESSION['user']['contact_number']) && !empty($_SESSION['user']['contact_number'])) {
		$message_to = $_SESSION['user']['contact_number'];
	}

//get self (primary contact attachment) image
	/*
	if (!is_array($_SESSION['tmp']['messages']['contact_me'])) {
		$sql = "select attachment_filename as filename, attachment_content as image ";
		$sql .= "from v_contact_attachments ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "and contact_uuid = :contact_uuid ";
		$sql .= "and attachment_primary = 1 ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$parameters['contact_uuid'] = $_SESSION['user']['contact_uuid'];
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		$_SESSION['tmp']['messages']['contact_me'] = $row;
		unset($sql, $parameters, $row);
	}
	*/

//get the cache
	$cache = new cache;
	$message_update = $cache->get("messages:user:last_message:".$_SESSION['user']['user_uuid']);

//additional includes
	$document['title'] = $text['title-messages'];
	require_once "resources/header.php";

//add audio
	echo "<audio id='message_audio' >\n";
	echo "	<source src=\"".$_SESSION['message']['notify_sound']['text']."\" type='audio/mpeg'>\n";
	echo "</audio>\n";
	echo "<script language='JavaScript' type='text/javascript'>\n";
	echo "	var audio = document.getElementById('message_audio');\n";
	echo "	function message_notify(){\n";
	echo "		audio.play();\n";
	echo "	}\n";
	echo "</script>\n";

//additional sound notes
	//https://notificationsounds.com/notification-sounds/clearly-602
	//echo "	<source src=\"http://soundbible.com/grab.php?id=1682&type=mp3\" type=\"audio/mpeg\">\n";
	//https://soundbible.com/search.php?q=beep
	//https://freesound.org/search/?q=notify&page=2#sound

//check for updates with ajax
	echo "<script language='JavaScript' type='text/javascript'>\n";
	echo "\n";
	echo "function check_updates() {\n";
	echo "	var xhttp = new XMLHttpRequest();\n";
	echo "	xhttp.open('GET', 'message_update.php?id=".$_SESSION['user']['user_uuid']."');\n";
	echo "	xhttp.send();\n";
	echo "	xhttp.onreadystatechange = function() {\n";
	//echo "		var time_now = date_now.getTime();\n";
	//echo "		var time_diff = time_now - date_now;\n";
	//echo "		var seconds_elapsed = Math.floor ( time_diff / 1000 );\n";
	//echo "		if (this.readyState == 4 && this.status == 200 && seconds_elapsed > 2) {\n";
	echo "		if (this.readyState == 4 && this.status == 200) {\n";
	echo "			if (this.responseText != document.getElementById('message_update').value) {\n";
	//echo "				alert('update ajax: '+this.responseText+ ' input: '+document.getElementById('message_update').value);\n";
	echo "				document.getElementById('message_update').value = this.responseText;\n";
	echo "				message_to = document.getElementById('message_to').value;\n";
	echo "				contacts_url = 'messages_contacts.php';\n";
	echo "				document.getElementById('contacts_frame').src = contacts_url;\n";
	echo "				document.getElementById('contacts_frame').onload = function() {\n";
	echo "					scroll_to_bottom('messages_frame');\n";
	echo "				}\n";
	echo "				messages_url = 'messages_thread.php?number='+message_to;\n";
	echo "				document.getElementById('messages_frame').src = messages_url;\n";
	echo "				document.getElementById('messages_frame').onload = function() {\n";
	echo "					scroll_to_bottom('messages_frame');\n";
	echo "				}\n";
	echo "				message_notify();\n";
	echo "			}\n";
	echo "		}\n";
	echo "	};\n";
	echo "}\n";
	echo "setInterval(check_updates, 1000);\n";
	echo "\n";
	echo "</script>\n";

//body onload
	echo "<body onload=\"scroll_to_bottom('messages_frame');\">\n";

//resize thread window on window resize
	echo "<script language='JavaScript' type='text/javascript'>\n";
	echo "	$(document).ready(function() {\n";
	echo "		$(window).on('resizeEnd', function() {\n";
	echo "			$('div#thread_messages').animate({ 'max-height': $(window).height() - 480 }, 200);\n";
	echo "		});\n";
	echo " 	});\n";
	echo "</script>\n";

//styles
	echo "<style>\n";
	echo "\n";

	echo "	#message_new_layer {\n";
	echo "		z-index: 999999;\n";
	echo "		position: absolute;\n";
	echo "		left: 0px;\n";
	echo "		top: 0px;\n";
	echo "		right: 0px;\n";
	echo "		bottom: 0px;\n";
	echo "		text-align: center;\n";
	echo "		vertical-align: middle;\n";
	echo "		}\n";

	echo "	#message_new_container {\n";
	echo "		display: block;\n";
	echo "		background-color: #fff;\n";
	echo "		padding: 20px 30px;\n";
	if (http_user_agent('mobile')) {
		echo "	margin: 0;\n";
	}
	else {
		echo "	margin: auto 30%;\n";
	}
	echo "		text-align: left;\n";
	echo "		-webkit-box-shadow: 0px 1px 20px #888;\n";
	echo "		-moz-box-shadow: 0px 1px 20px #888;\n";
	echo "		box-shadow: 0px 1px 20px #888;\n";
	echo "		}\n";

	echo "	#message_media_layer {\n";
	echo "		z-index: 999999;\n";
	echo "		position: absolute;\n";
	echo "		left: 0px;\n";
	echo "		top: 0px;\n";
	echo "		right: 0px;\n";
	echo "		bottom: 0px;\n";
	echo "		text-align: center;\n";
	echo "		vertical-align: middle;\n";
	echo "		}\n";

	echo "	td.contact_selected {\n";
	echo "		border-right: 5px solid ".($_SESSION['theme']['table_row_border_color']['text'] ?? '#c5d1e5').";\n";
	echo "		}\n";

	echo "	.contact_list_image {\n";
	echo "		float: left;\n";
	echo "		width: 75px;\n";
	echo "		height: 75px;\n";
	echo "		margin: 3px 8px 3px 2px;\n";
	echo "		border: 1px solid ".($_SESSION['theme']['table_row_border_color']['text'] ?? '#c5d1e5').";\n";
	echo "		background-repeat: no-repeat;\n";
	echo "		background-size: cover;\n";
	echo "		background-position: center center;\n";
	echo "		border-radius: 11px;\n";
	echo "		}\n";

	echo "	div.container {\n";
	echo "		width: 100%;\n";
	echo "		height: 75vh;\n";
	echo "		max-width: 100%;\n";
	//echo "	max-height: 100%;\n";
	//echo "	border:1px solid black;\n";
	echo "  	display: grid;\n";
	echo "		padding-left: 0;\n";
	echo "		padding-right: 0;\n";

	//flex
	//echo "	flex: 1;\n";
	//echo "	display: flex;\n";
	//echo "	flex-direction: column;\n";
	//echo "	justify-content: space-between;\n";

	//echo "	display: inline-grid;\n";
	if (!empty($_SESSION['message']['contact_details']['boolean']) && $_SESSION['message']['contact_details']['boolean'] == 'true') {
		echo "	grid-template-columns: minmax(120px, 1fr) minmax(200px, 3fr) 1fr;\n";
	}
	else {
		echo "	grid-template-columns: minmax(120px, 1fr) minmax(200px, 4fr);\n";
	}
	echo "		grid-template-rows: 1fr;\n";
	echo "		gap: 10px 10px;\n";
	//echo "  	grid-auto-flow: row;\n";

	echo "		grid-template-areas:\n";
	//echo "	\"contacts header header\"\n";
	echo "		\"contacts messages details\"\n";
	echo "		\"contacts send details\"\n";
	//echo "	width: 100%;\n";
	//echo "	height: 100%;\n";
	echo "		}\n";

	echo "	div.contacts {\n";
	echo "		grid-area: contacts;\n";
	//echo "	min-width: 120px;\n";
	//echo "	height: 100%;\n";
	echo "		background: ".($_SESSION['theme']['form_table_field_background_color']['text'] ?? '#fff').";\n";
	echo "		border-right: 1px solid #ccc;\n";
	echo "	}\n";

	echo "	div.messages {\n";
	echo "		grid-area: messages;\n";
// 	echo "		border-top: 1px dashed #ccc;\n";
// 	echo "		border-bottom: 1px dashed #ccc;\n";
	echo "		}\n";

	echo "	div.send {\n";
	echo "		grid-area: send;\n";
	echo "		margin: 4px;\n";
	echo "		}\n";

	echo "	div.details {\n";
	echo "		min-width: 330px;\n";
	echo "		grid-area: details;\n";
	echo "		border-left: 1px solid #ccc;\n";
	echo "		}\n";

	echo "	@media (max-width: 992px) {\n";
	echo "		div.container {\n";
	echo "			grid-template-columns: 120px minmax(200px, 3fr);\n";
	echo "			}\n";
	echo "	}\n";

	echo "</style>\n";

//cache self (primary contact attachment) image
	if (!empty($_SESSION['tmp']) && is_array($_SESSION['tmp']['messages']['contact_me']) && !empty($_SESSION['tmp']['messages']['contact_me'])) {
		$attachment_type = strtolower(pathinfo($_SESSION['tmp']['messages']['contact_me']['filename'], PATHINFO_EXTENSION));
		echo "<img id='src_message-bubble-image-me' style='display: none;' src='data:image/".$attachment_type.";base64,".$_SESSION['tmp']['messages']['contact_me']['image']."'>\n";
	}

//new message layer
	if (permission_exists('message_add')) {
		echo "<iframe name=\"message_new_frame\" style=\"display: none; width: 0px; height: 0px;\"></iframe>\n";
		echo "<div id='message_new_layer' style='display: none;'>\n";
		echo "	<table cellpadding='0' cellspacing='0' border='0' width='100%' height='100%'>\n";
		echo "		<tr>\n";
		echo "			<td align='center' valign='middle'>\n";
		echo "				<form id='message_new' method='post' enctype='multipart/form-data' action='message_send.php' target='message_new_frame'>\n";
		echo "				<span id='message_new_container'>\n";
		echo "					<b>".$text['label-new_message']."</b><br /><br />\n";
		echo "					<table width='100%'>\n";
		echo "						<tr>\n";
		echo "							<td class='vncell'>".$text['label-message_from']."</td>\n";
		echo "							<td class='vtable'>\n";
		if (!empty($destinations) && is_array($destinations)) {
			echo "							<select class='formfld' name='message_from' id='message_new_from' onchange=\"$('#message_new_to').focus();\">\n";
			foreach ($destinations as $destination) {
				echo "							<option value='".$destination."'>".format_phone($destination)."</option>\n";
			}
			echo "							</select>\n";
		}
		else {
			echo "							<input type='text' class='formfld' name='message_from' id='message_new_from'>\n";
		}
		echo "							</td>\n";
		echo "						</tr>\n";
		echo "						<tr>\n";
		echo "							<td class='vncell'>".$text['label-message_to']."</td>\n";
		echo "							<td class='vtable'>\n";
		echo "								<input type='text' class='formfld' name='message_to' id='message_new_to'>\n";
		echo "							</td>\n";
		echo "						</tr>\n";
		echo "						<tr>\n";
		echo "							<td class='vncell'>".$text['label-message_text']."</td>\n";
		echo "							<td class='vtable'>\n";
		echo "								<textarea class='formfld' style='width: 100%; height: 80px;' name='message_text' name='message_new_text'></textarea>\n";
		echo "							</td>\n";
		echo "						</tr>\n";
		echo "						<tr>\n";
		echo "							<td class='vncell'>".$text['label-message_media']."</td>\n";
		echo "							<td class='vtable'>\n";
		echo "								<input type='file' class='formfld' multiple='multiple' name='message_media[]' id='message_new_media'>\n";
		echo "							</td>\n";
		echo "						</tr>\n";
		echo "					</table>\n";
		echo "					<center style='margin-top: 15px;'>\n";
		echo button::create(['type'=>'reset','label'=>$text['button-clear'],'icon'=>$_SESSION['theme']['button_icon_reset'],'style'=>'float: left;','onclick'=>"document.getElementById('message_new').reset();"]);
		echo button::create(['type'=>'button','label'=>$text['button-close'],'icon'=>$_SESSION['theme']['button_icon_cancel'],'onclick'=>"$('#message_new_layer').fadeOut(200);"]);
		echo button::create(['type'=>'submit','label'=>$text['button-send'],'icon'=>'paper-plane','style'=>'float: right;','onclick'=>"document.getElementById('message_new').submit();document.getElementById('message_new_layer').style.display='none'"]);
		//echo button::create(['type'=>'reset','label'=>$text['button-clear'],'icon'=>$_SESSION['theme']['button_icon_reset'],'style'=>'float: left;','onclick'=>"$('#message_new').reset();"]);
		//echo "						<input type='reset' class='btn' style='float: left; margin-top: 15px;' value='".$text['button-clear']."' onclick=\"$('#message_new').reset();\">\n";
		//echo "						<input type='button' class='btn' style='margin-top: 15px;' value='".$text['button-close']."' onclick=\"$('#message_new_layer').fadeOut(200);\">\n";
		//echo "						<input type='submit' class='btn' style='float: right; margin-top: 15px;' value='".$text['button-send']."'>\n";
		echo "					</center>\n";
		echo "				</span>\n";
		echo "				</form>\n";
		echo "			</td>\n";
		echo "		</tr>\n";
		echo "	</table>\n";
		echo "</div>\n";
	}

//message media layer
	echo "<div id='message_media_layer' style='display: none;'></div>\n";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-messages']."</b></div>\n";
	echo "	<div class='actions'>\n";
	if (permission_exists('message_add')) {
		echo button::create(['type'=>'button','label'=>$text['label-new_message'],'icon'=>$_SESSION['theme']['button_icon_add'],'id'=>'btn_add','onclick'=>"document.getElementById('message_new').reset(); $('#message_new_layer').fadeIn(200); unload_thread();"]);
		//echo button::create(['type'=>'button','label'=>$text['label-new_message'],'icon'=>$_SESSION['theme']['button_icon_add'],'id'=>'btn_add','onclick'=>"document.getElementById('message_new').reset();$('#message_new_layer').fadeIn(200); unload_thread();"]);
	}
	echo button::create(['type'=>'button','label'=>$text['label-log'],'icon'=>'list','link'=>'message_logs.php']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<div class='container'>\n";
	echo "	<div class='contacts'>\n";
	echo "		<iframe id='contacts_frame' style='width: 100%; height: 100%;' src='messages_contacts.php' frameborder='0'></iframe>\n";
	echo "	</div>\n";
	echo "	<div class='messages'>\n";
	echo "		<iframe id='messages_frame' class='myautoscroll' style='width: 100%; height: 100%;' src='messages_thread.php' frameborder='0'></iframe>\n";
	echo "	</div>\n";

	if (permission_exists('message_add')) {
		echo "	<div class='send'>\n";
			//output input form
			//echo "<iframe name=\"message_reply_frame\" style=\"display: none; width: 0px; height: 0px;\"></iframe>\n";
			//echo "<form id='message_reply' method='post' onsubmit='document.getElementById(\"message_text\").value = \"\";' enctype='multipart/form-data' action='message_send.php' target='message_reply_frame'>\n";
			echo "<form id='message_reply' method='post' onsubmit='' enctype='multipart/form-data' action='message_send.php'>\n";
			echo "<input type='hidden' name='message_update' id='message_update' value='".$message_update."'>\n";

			//echo "<div>\n";
			//echo "	To <div id='div_message_to'>".escape($message_to)."</div>\n";
			echo "	<input type='hidden' class='formfld' name='message_to' id='message_to' value='".urlencode($message_to ?? '')."'>\n";
			//echo "</div>\n";

			echo "<textarea class='formfld' id='message_text' name='message_text' style='width: 100%; min-height: 55px; resize: vertical; padding: 5px 8px; margin: 3px 0 10px 0;' placeholder=\"".$text['description-enter_response']."\"></textarea>";
			//echo "<input type='input' class='formfld' name='message_text' id='message_text' style='width: 90%; max-width: 100%;'>\n";
// 			echo "<table cellpadding='0' cellspacing='0' border='0' width='100%' style='margin-top: 5px;'>\n";
// 			echo "	<tr>\n";
// 			echo "		<td class='attachment_image' style='width: 20px;'><img src='resources/images/attachment.png' style='min-width: 20px; height: 20px; border: none; padding-right: 5px;'></td>\n";
// 			echo "		<td>\n";
			echo "<input type='file' class='formfld' style='max-width: 170px;' multiple='multiple' name='message_media[]' id='message_new_media'>\n";
// 			echo "		</td>\n";
// 			echo "		<td style='text-align: right;'>\n";
			echo button::create(['type'=>'submit','label'=>$text['button-send'],'title'=>$text['label-ctrl_enter'],'icon'=>'paper-plane','class'=>'default d-none d-sm-inline-block','style'=>'float: right; margin-left: 10px; margin-right: 0;']);
			if (!empty($destinations) && count($destinations) > 1) {
				echo "<select class='formfld' name='message_from' id='message_from' style='float: right; padding: 0 5px 0 8px; margin-bottom: 10px;'>\n";
				echo "	<option value='' disabled='disabled'>".$text['label-message_from']."...</option>\n";
				foreach ($destinations as $destination) {
					echo "<option value='".$destination."'>".format_phone($destination)."</option>\n";
				}
				echo "</select>\n";
			}
			else {
				//echo "	From \n";
				echo "<input type='hidden' class='formfld' name='message_from' id='message_from' value='".urlencode($message_from ?? '')."'>\n";
			}
// 			echo "		</td>\n";
// 			echo "	</tr>\n";
// 			echo "</table>\n";
			echo button::create(['type'=>'submit','label'=>$text['button-send'],'title'=>$text['label-ctrl_enter'],'icon'=>'paper-plane','class'=>'default d-block d-sm-none','style'=>'width: 100%; margin-left: 0;']);
			//echo "<table cellpadding='0' cellspacing='0' border='0' width='100%' style='margin-top: 15px;'>\n";
			//echo "	<tr>\n";
			//echo "		<td align='left' width='50%'>";
			//echo button::create(['label'=>$text['button-clear'],'icon'=>$_SESSION['theme']['button_icon_reset'],'type'=>'reset','onclick'=>"$('#message_text').focus();"]);
			//echo "		</td>\n";
			//echo "		<td align='center'><span id='thread_refresh_state'><img src='resources/images/refresh_active.gif' style='width: 16px; height: 16px; border: none; cursor: pointer;' onclick=\"refresh_thread_stop('".$number."','".$contact_uuid."');\" alt=\"".$text['label-refresh_pause']."\" title=\"".$text['label-refresh_pause']."\"></span></td>\n";
			//echo "		<td align='right' width='50%'>";
			//echo button::create(['type'=>'submit','label'=>$text['button-send'],'title'=>$text['label-ctrl_enter'],'icon'=>'paper-plane']);
			//echo "		</td>\n";
			//echo "	</tr>\n";
			//echo "</table>\n";
			echo "</form>\n";

			//js to load messages for clicked number
			echo "<script>\n";

			//scroll to the bottom
			echo "	function scroll_to_bottom(id) {\n";
			echo "		document.getElementById(id).contentWindow.scrollTo(0, 999999);\n";
			echo "	}\n";
			echo "\n";

			//update the url
			echo "	function update_url(id, url) {\n";
			echo "		document.getElementById(id).src = url;\n";
			echo "	}\n";

			//define form submit function
			echo "	$('#message_reply').submit(function(event) {\n";
			echo "		event.preventDefault();\n";
			echo "		$.ajax({\n";
			echo "			url: $(this).attr('action'),\n";
			echo "			type: $(this).attr('method'),\n";
			echo "			data: new FormData(this),\n";
			echo "			processData: false,\n";
			echo "			contentType: false,\n";
			echo "			cache: false,\n";
			echo "			success: function(){\n";
			echo "					document.getElementById('message_reply').reset();\n";
			if (!http_user_agent('mobile')) {
				echo "				if ($('#message_new_layer').is(':hidden')) {\n";
				echo "					$('#message_text').focus();\n";
				echo "				}\n";
			}

			//refresh the message thread
			//echo "					setTimeout(function() {\n";
			//echo "						refresh_thread()\n";
			//echo "					}, 1000);\n";
			//echo "				refresh_thread('".$number."', '".$contact_uuid."', 'true');\n";

			echo "				}\n";
			echo "		});\n";
			echo "	});\n";
			//enable ctrl+enter to send
			echo "	$('#message_text').keydown(function (event) {\n";
			echo "		if ((event.keyCode == 10 || event.keyCode == 13) && event.ctrlKey) {\n";
			echo "			$('#message_compose').submit();\n";
			echo "		}\n";
			echo "	});\n";

			echo "</script>\n";
		echo "	</div>\n"; //send
	}
	if (!empty($_SESSION['message']['contact_details']['boolean']) && $_SESSION['message']['contact_details']['boolean'] == 'true') {
		echo "	<div class='details d-none d-md-block'>\n";
		if (permission_exists('contact_view')) {
			echo "	<iframe id='contact_frame' style='width: 100%; height: 100%;' src='message_contact.php' frameborder='0'></iframe>\n";
		}
		echo "	</div>\n";
	}
	echo "</div>\n"; //container

/*
	echo "<script>\n";
	echo "	function refresh_thread() {\n";
	echo "		message_to = parent.document.getElementById('message_to').value;\n";
	echo "		update_url('messages_frame', 'messages_thread.php?number='+message_to);\n";
	echo "	}\n";

	echo "</script>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "	<tr>\n";
	echo "		<th width='30%'>".$text['label-contacts']."</th>\n";
	echo "		<th style='white-space: nowrap;'><nobr>".$text['label-messages']."<nobr></th>\n";
	echo "		<th width='70%' style='text-align: right; font-weight: normal;' id='contact_current_name'>\n";
	echo "			<iframe id=\"frame\" src=\"/app/messages/messages_contacts.php\" frameborder=\"0\"></iframe>\n";
	echo "		</th>\n";
	echo "	</tr>\n";
	echo "	<tr>\n";
	echo "		<td id='contacts' valign='top'><center>&middot;&middot;&middot;</center></td>\n";
	echo "		<td id='thread' colspan='2' valign='top' style='border-left: 1px solid #c5d1e5; padding: 15px 0 15px 15px;'><center>&middot;&middot;&middot;</center></td>\n";
	echo "	</tr>\n";
	echo "</table>\n";
	echo "<input type='hidden' id='contact_current_number' value=''>\n";

//js to load messages for clicked number
	echo "<script>\n";

	/*
	$refresh_contacts = is_numeric($_SESSION['message']['refresh_contacts']['numeric']) && $_SESSION['message']['refresh_contacts']['numeric'] > 0 ? $_SESSION['message']['refresh_contacts']['numeric'] : 10; //default (seconds)
	$refresh_thread = is_numeric($_SESSION['message']['refresh_thread']['numeric']) && $_SESSION['message']['refresh_thread']['numeric'] > 0 ? $_SESSION['message']['refresh_thread']['numeric'] : 5; //default (seconds)
	echo "	var contacts_refresh = ".($refresh_contacts * 1000).";\n";
	echo "	var thread_refresh = ".($refresh_thread * 1000).";\n";
	echo "	var timer_contacts;\n";
	echo "	var timer_thread;\n";

	echo "	function refresh_contacts() {\n";
	echo "		clearTimeout(timer_contacts);\n";
	echo "		$('#contacts').load('messages_contacts.php?sel=' + $('#contact_current_number').val(), function(){\n";
	echo "			timer_contacts = setTimeout(refresh_contacts, contacts_refresh);\n";
	echo "		});\n";
	echo "	}\n";

	echo "	function load_thread(number, contact_uuid) {\n";
	echo "		clearTimeout(timer_thread);\n";
	echo "		$('#thread').load('messages_thread.php?number=' + encodeURIComponent(number) + '&contact_uuid=' + encodeURIComponent(contact_uuid), function(){\n";
	echo "			$('div#thread_messages').animate({ 'max-height': $(window).height() - 470 }, 200, function() {\n";
	echo "				$('#thread_messages').scrollTop(Number.MAX_SAFE_INTEGER);\n"; //chrome
	echo "				$('span#thread_bottom')[0].scrollIntoView(true);\n"; //others
						//note: the order of the above two lines matters!
	if (!http_user_agent('mobile')) {
		echo "			if ($('#message_new_layer').is(':hidden')) {\n";
		echo "				$('#message_text').focus();\n";
		echo "			}\n";
	}
	echo "				refresh_contacts();\n";
	echo "				timer_thread = setTimeout(refresh_thread_start, thread_refresh, number, contact_uuid);\n";
	echo "			});\n";
	echo "		});\n";
	echo "	}\n";

	echo "	function unload_thread() {\n";
	echo "		clearTimeout(timer_thread);\n";
	echo "		$('#thread').html('<center>&middot;&middot;&middot;</center>');\n";
	echo "		$('#contact_current_number').val('');\n";
	echo "		$('#contact_current_name').html('');\n";
	echo "		refresh_contacts();\n";
	echo "	}\n";

	echo "	function refresh_thread(number, contact_uuid, onsent) {\n";
	echo "		$('#thread_messages').load('messages_thread.php?refresh=true&number=' + encodeURIComponent(number) + '&contact_uuid=' + encodeURIComponent(contact_uuid), function(){\n";
	echo "			$('div#thread_messages').animate({ 'max-height': $(window).height() - 470 }, 200, function() {\n";
	echo "				$('#thread_messages').scrollTop(Number.MAX_SAFE_INTEGER);\n"; //chrome
	echo "				$('span#thread_bottom')[0].scrollIntoView(true);\n"; //others
						//note: the order of the above two lines matters!
	if (!http_user_agent('mobile')) {
		echo "				if ($('#message_new_layer').is(':hidden')) {\n";
		echo "			$('#message_text').focus();\n";
		echo "			}\n";
	}
	echo "				if (onsent != 'true') {\n";
	echo "					timer_thread = setTimeout(refresh_thread, thread_refresh, number, contact_uuid);\n";
	echo "				}\n";
	echo "			});\n";
	echo "		});\n";
	echo "	}\n";

//refresh controls
	echo "	function refresh_contacts_stop() {\n";
	echo "		clearTimeout(timer_contacts);\n";
	echo "		document.getElementById('contacts_refresh_state').innerHTML = \"<img src='resources/images/refresh_paused.png' style='width: 16px; height: 16px; border: none; margin-top: 1px; cursor: pointer;' onclick='refresh_contacts_start();' alt='".$text['label-refresh_enable']."' title='".$text['label-refresh_enable']."'>\";\n";
	echo "	}\n";

	echo "	function refresh_contacts_start() {\n";
	echo "		if (document.getElementById('contacts_refresh_state')) {\n";
	echo "			document.getElementById('contacts_refresh_state').innerHTML = \"<img src='resources/images/refresh_active.gif' style='width: 16px; height: 16px; border: none; margin-top: 3px; cursor: pointer;' onclick='refresh_contacts_stop();' alt='".$text['label-refresh_pause']."' title='".$text['label-refresh_pause']."'>\";\n";
	echo "			refresh_contacts();\n";
	echo "		}\n";
	echo "	}\n";

	echo "	function refresh_thread_stop(number, contact_uuid) {\n";
	echo "		clearTimeout(timer_thread);\n";
	?>			document.getElementById('thread_refresh_state').innerHTML = "<img src='resources/images/refresh_paused.png' style='width: 16px; height: 16px; border: none; margin-top: 3px; cursor: pointer;' onclick=\"refresh_thread_start('" + number + "', '" + contact_uuid + "');\" alt=\"<?php echo $text['label-refresh_enable']; ?>\" title=\"<?php echo $text['label-refresh_enable']; ?>\">";<?php
	echo "	}\n";

	echo "	function refresh_thread_start(number, contact_uuid) {\n";
	echo "		if (document.getElementById('thread_refresh_state')) {\n";
	?>				document.getElementById('thread_refresh_state').innerHTML = "<img src='resources/images/refresh_active.gif' style='width: 16px; height: 16px; border: none; margin-top: 3px; cursor: pointer;' onclick=\"refresh_thread_stop('" + number + "', '" + contact_uuid + "');\" alt=\"<?php echo $text['label-refresh_pause']; ?>\" title=\"<?php echo $text['label-refresh_pause']; ?>\">";<?php
	echo "			refresh_thread(number, contact_uuid);\n";
	echo "		}\n";
	echo "	}\n";

//define form submit function
	if (permission_exists('message_add')) {
		echo "	$('#message_new').submit(function(event) {\n";
		echo "		event.preventDefault();\n";
		echo "		$.ajax({\n";
		echo "			url: $(this).attr('action'),\n";
		echo "			type: $(this).attr('method'),\n";
		echo "			data: new FormData(this),\n";
		echo "			processData: false,\n";
		echo "			contentType: false,\n";
		echo "			cache: false,\n";
		echo "			success: function(){\n";
		echo "				if ($.isNumeric($('#message_new_to').val())) {\n";
		echo "					$('#contact_current_number').val($('#message_new_to').val());\n";
		echo "					load_thread($('#message_new_to').val());\n";
		echo "				}\n";
		echo "				$('#message_new_layer').fadeOut(400);\n";
		echo "				document.getElementById('message_new').reset();\n";
		echo "				refresh_contacts();\n";
		echo "			}\n";
		echo "		});\n";
		echo "	});\n";
	}
*/

//include the footer
	require_once "resources/footer.php";

?>