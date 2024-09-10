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
	if (!permission_exists('message_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get selected number/contact
	if (isset($_GET['number']) && !empty($_GET['number'])) {
		$_SESSION['user']['contact_number'] = $_GET['number'];
	}

//build a list of groups the user is a member of to be used in a SQL in
	foreach($_SESSION['user']['groups'] as $group) {
		if (is_uuid($group['group_uuid'])) {
			$group_uuids[] =  $group['group_uuid'];
		}
	}
	$group_uuids_in = "'".implode("','", $group_uuids)."'";

//get the list of contacts
	$sql = "select *, \n";
	$sql .= "(\n";
	$sql .= "	(select effective_caller_id_name as name from v_extensions where n.number = extension limit 1)\n";
	$sql .= "	union\n";
	$sql .= "	(select concat_ws(' ', contact_name_given, contact_name_family) as name from v_contacts where contact_uuid in (select contact_uuid from v_contact_phones where n.number = phone_number) limit 1)\n";
	$sql .= ") as name,\n";
	$sql .= "(\n";
	$sql .= "	select contact_uuid from v_contact_phones where n.number = phone_number limit 1\n";
	$sql .= ") as contact_uuid,\n";
	$sql .= "(\n";
	$sql .= "	select attachment_filename from v_contact_attachments where contact_uuid in (select contact_uuid from v_contact_phones where n.number = phone_number)\n";
	$sql .= ") as contact_image_filename,\n";
	$sql .= "(\n";
	$sql .= "	select attachment_content from v_contact_attachments where contact_uuid in (select contact_uuid from v_contact_phones where n.number = phone_number)\n";
	$sql .= ") as contact_image_content,\n";
	$sql .= "(\n";
	$sql .= "	select count(*) as count from v_messages where message_read is not true and message_direction = 'inbound' and message_from = n.number\n";
	$sql .= ") as count,\n";
	$sql .= "(\n";
	$sql .= "	select message_text from v_messages\n";
	$sql .= "	where \n";
	$sql .= "	(\n";
	$sql .= "		(message_direction = 'inbound' and message_from = n.number)\n";
	$sql .= "		or \n";
	$sql .= "		(message_direction = 'outbound' and message_to = n.number)\n";
	$sql .= "	)\n";
	$sql .= "	and message_text is not null\n";
	$sql .= "	order by message_date desc limit 1\n";
	$sql .= ") as message,\n";
	$sql .= "(\n";
	$sql .= "	select message_date from v_messages \n";
	$sql .= "	where (\n";
	$sql .= "		(message_direction = 'inbound' and message_from = n.number)\n";
	$sql .= "		or \n";
	$sql .= "		(message_direction = 'outbound' and message_to = n.number)\n";
	$sql .= "	)\n";
	$sql .= "	and message_text is not null\n";
	$sql .= "	order by message_date desc limit 1\n";
	$sql .= ") as date\n";
	$sql .= "from (\n";
	$sql .= "	select number from \n";
	$sql .= "	(\n";
	$sql .= "		select distinct(message_from) as number from v_messages \n";
	$sql .= "		where domain_uuid = :domain_uuid \n";
	$sql .= "		and message_direction = 'inbound' and message_from is not null \n";
	//$sql .= "		and user_uuid = :user_uuid \n";
	$sql .= "		and ( \n";
	$sql .= "			user_uuid = :user_uuid \n";
	$sql .= "			or \n";
	$sql .= "			group_uuid in (\n";
	$sql .= "				select group_uuid from v_destinations \n";
	$sql .= "				where group_uuid in (".$group_uuids_in.") \n";
	$sql .= "				and domain_uuid = :domain_uuid \n";
	$sql .= "			) \n";
	$sql .= "		)\n";
	$sql .= "		and message_from ~'^\+?([0-9]+\.?[0-9]*|\.[0-9]+)$' \n";
	$sql .= "		union \n";
	$sql .= "		select distinct(message_to) as number from v_messages \n";
	$sql .= "		where domain_uuid = :domain_uuid \n";
	$sql .= "		and message_direction = 'outbound' and message_from is not null \n";
	//$sql .= "		and user_uuid = :user_uuid ";
	$sql .= "		and ( \n";
	$sql .= "			user_uuid = :user_uuid \n";
	$sql .= "			or \n";
	$sql .= "			group_uuid in (\n";
	$sql .= "				select group_uuid from v_destinations \n";
	$sql .= "				where group_uuid in (".$group_uuids_in.") \n";
	$sql .= "				and domain_uuid = :domain_uuid \n";
	$sql .= "			) \n";
	$sql .= "		) \n";
	$sql .= "		and message_to ~'^\+?([0-9]+\.?[0-9]*|\.[0-9]+)$' \n";
	$sql .= "	) as nested \n";
	$sql .= "	where number not in \n";
	$sql .= "	( \n";
	$sql .= "		select destination_number as number \n";
	$sql .= "		from v_destinations \n";
	$sql .= "		where destination_type = 'inbound' \n";
	$sql .= "		and domain_uuid = :domain_uuid \n";
	$sql .= "		union \n";
	$sql .= "		select (concat(destination_prefix, destination_number)) as number \n";
	$sql .= "		from v_destinations \n";
	$sql .= "		where destination_type = 'inbound' \n";
	$sql .= "		and domain_uuid = :domain_uuid \n";
	$sql .= "	) \n";
	$sql .= "	order by number asc\n";
	$sql .= ") as n\n";

	$sql .= "order by \n";
	//uncomment below to have the selected message pop up to the top of the list
	//$sql .= "case when (number = :number) then 0 end asc,\n";
	$sql .= "date desc\n";

	$parameters['domain_uuid'] = $domain_uuid;
	$parameters['user_uuid'] = $_SESSION['user']['user_uuid'];

	//uncomment below to have the selected message pop up to the top of the list
	//$parameters['number'] = $_SESSION['user']['contact_number'] ?? null;
//echo "<pre>\n";
//echo $sql;
//echo "</pre>\n";
//view_array($parameters);
	$database = new database;
	$contacts = $database->select($sql, $parameters, 'all');
	//view_array($contacts);
	unset($sql, $parameters);

//show the content
	echo "<!DOCTYPE html>\n";
	echo "<html>\n";
	echo "<head>\n";

//include icons
	echo "<link rel='stylesheet' type='text/css' href='/resources/fontawesome/css/all.min.css.php'>\n";
	echo "<script language='JavaScript' type='text/javascript' src='/resources/fontawesome/js/solid.min.js.php' defer></script>\n";

//js to load messages for clicked number
	echo "<script>\n";

	//scroll to the bottom
	echo "	function scroll_to_bottom(id) {\n";
	echo "		parent.document.getElementById(id).contentWindow.scrollTo(0, 999999);\n";
	echo "	}\n";
	echo "\n";

	//update the url
	echo "	function update_url(id, url) {\n";
	//echo "		alert('from: '+parent.document.getElementById('message_from').value);\n";
	//echo "		alert('to: '+parent.document.getElementById('message_to').value);\n";
	//echo "		alert('to: '+parent.document.getElementById('message_text').value);\n";
	echo "		parent.document.getElementById(id).onload = function() {\n";
// 	echo "			scroll_to_bottom(id);\n";
	echo "			this.contentWindow.scrollTo(0, 999999);\n";
	echo "		}\n";
	echo "		parent.document.getElementById(id).src = url;\n";
	//echo "		scroll_to_bottom(id);\n";
	echo "	}\n";
	echo "</script>\n";

//styles
	echo "<style>\n";
	echo "\n";

	echo "	body {\n";
	echo "		margin: 0 14px 0 0;\n";
	echo "		}\n";

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
	echo "\n";

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
	echo "\n";

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
	echo "\n";

	echo "	td.contact_selected {\n";
	echo "		border-right: 5px solid ".($_SESSION['theme']['message_bubble_em_border_color']['text'] ?? '#abefa0').";\n";
	echo "		}\n";
	echo "\n";

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
	echo "\n";

	echo "	.row_style0 {\n";
	echo "		border-top: 1px solid ".($_SESSION['theme']['message_bubble_em_border_color']['text'] ?? '#abefa0').";\n";
	echo "		border-bottom: 1px solid ".($_SESSION['theme']['message_bubble_em_border_color']['text'] ?? '#abefa0').";\n";
	echo "		border-radius: 4px;\n";
	echo "		background: ".($_SESSION['theme']['message_bubble_em_background_color']['text'] ?? '#daffd4').";\n";
	echo "		color: ".($_SESSION['theme']['body_text_color']['text'] ?? '#5f5f5f').";\n";
	echo "		font-family: ".($_SESSION['theme']['body_text_font']['text'] ?? 'arial').";\n";
	echo "		font-size: 12px;\n";
	echo "		text-align: left;\n";
	echo "		padding: 4px 7px;\n";
	echo "		padding-top: 8px;\n";
	echo "		cursor: pointer;\n";
	echo "		}\n";
	echo "\n";

	echo "	.row_style1 {\n";
	echo "		border-bottom: 1px solid ".($_SESSION['theme']['table_row_border_color']['text'] ?? '#c5d1e5').";\n";
	echo "		border-radius: 4px;\n";
	echo "		color: ".($_SESSION['theme']['body_text_color']['text'] ?? '#5f5f5f').";\n";
	echo "		font-family: ".($_SESSION['theme']['body_text_font']['text'] ?? 'arial').";\n";
	echo "		font-size: 12px;\n";
	echo "		text-align: left;\n";
	echo "		padding: 4px 7px;\n";
	echo "		padding-top: 8px;\n";
	echo "		cursor: pointer;\n";
	echo "		}\n";
	echo "\n";

	echo "	.contact_message {\n";
	echo "		margin-top: 5px;\n";
	echo "		padding-left: 5px;\n";
	echo "		}\n";

	echo "	@media (max-width: 121px) {\n";
	echo "		.contact_message {\n";
	echo "			display: none;\n";
	echo "			}\n";
	echo "		.contact_image {\n";
	echo "			float: none;\n";
	echo "			margin-left: 20.5%;\n";
	echo "			}\n";
	echo "		.row_style0, .row_style1 {\n";
	echo "			padding-bottom: 0px;\n";
	echo "			text-align: center;\n";
	echo "			}\n";
	echo "		}\n";

	echo "</style>\n";

//end the header and start the body
	echo "</head>\n";
	echo "<body>\n";

//contacts list
	if (!empty($contacts) && @sizeof($contacts) != 0) {
		echo "<table class='tr_hover' width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
		foreach ($contacts as $row) {
			$number = $row['number'];
			$name = $row['name'];
			$count = $row['count'];
			$message = $row['message'];
			$date = $row['date'];
			// $row['contact_image_content'];
			// $row['contact_image_filename'];
			// $row['contact_uuid'];

			//get the image file extension
			if (!empty($row['contact_image_filename'])) {
				$contact_image_extension = pathinfo($row['contact_image_filename'], PATHINFO_EXTENSION);
			}

			//set the count label
			if ($count == 0) {
				$count = '';
			}
			else {
				$count = ' ('.$count.')';
			}

			//$contact_name = format_phone($row['number']);
			$contact_name = $row['number'];
			if (!empty($row['name'])) {
				$contact_name = escape($row['name']);
			}
			if (!empty($_SESSION['user']['contact_number']) && $_SESSION['user']['contact_number'] == $number) {
				echo "<tr onclick=\"parent.document.getElementById('message_to').value=".escape($number)."; parent.document.getElementById('contacts_frame').src='messages_contacts.php?number=".urlencode($number)."'; update_url('messages_frame', 'messages_thread.php?number=".urlencode($number)."'); ".(permission_exists('contact_view') && !empty($_SESSION['message']['contact_details']['boolean']) && $_SESSION['message']['contact_details']['boolean'] == 'true' ? "parent.document.getElementById('contact_frame').src='message_contact.php?id=".$row['contact_uuid']."';" : null)."\"><td valign='top' class='row_style0 contact_selected'>\n";
				if (permission_exists('contact_view') && !empty($_SESSION['message']['contact_details']['boolean']) && $_SESSION['message']['contact_details']['boolean'] == 'true') {
					echo "<script>parent.document.getElementById('contact_frame').src='message_contact.php?destination=".urlencode($number)."&id=".$row['contact_uuid']."';</script>";
				}
				$selected = true;
			}
			else {
				echo "<tr onclick=\"parent.document.getElementById('message_to').value=".escape($number)."; parent.document.getElementById('contacts_frame').src='messages_contacts.php?number=".urlencode($number)."'; update_url('messages_frame', 'messages_thread.php?number=".urlencode($number)."'); ".(permission_exists('contact_view') && !empty($_SESSION['message']['contact_details']['boolean']) && $_SESSION['message']['contact_details']['boolean'] == 'true' ? "parent.document.getElementById('contact_frame').src='message_contact.php';" : null)."\"><td valign='top' class='row_style1'>\n"; // onclick=\"load_thread('".urlencode($number)."', '".$contact[$number]['contact_uuid']."');\"
				$selected = false;
			}

			if (!empty($row['contact_image_filename'])) {
				//echo "<img id='src_message-bubble-image-em_".$row['contact_uuid']."' style='display: none;' src='data:image/".$contact_image_extension.";base64,".$row['contact_image_content']."'>\n";
				echo "<div class='contact_image' style='width: 50px; height: 50px; float: left; padding-right: 3px;'>\n";
				echo "	<img id='src_message-bubble-image-em_".$row['contact_uuid']."' src=\"data:image/png;base64,".$row['contact_image_content']."\" style=\"width: 50px;\">\n";
				echo "</div>\n";
				//echo "<img id='contact_image_".$row['contact_uuid']."' class='contact_list_image' src='data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'>\n";
			}
			else {
				echo "<div class='contact_image' style='width: 50px; height: 50px; float: left; padding-right: 3px;'>\n";
				echo "	<i class='fas fa-".(!empty($contact_name) && !is_numeric($contact_name) ? 'user-tie' : 'user')." fa-fw fa-3x' style='margin-top: 4px; color: ".($selected ? ($_SESSION['theme']['message_bubble_em_when_color']['text'] ?? '#52b342') : '#ccc').";'></i>\n";
				echo "</div>\n";
			}

			echo "<div style='padding-left: 3px; margin-bottom: 4px;'>\n";
			echo "	<a href='view:messages' onclick='event.preventDefault();' style='text-decoration: none; color: ".($_SESSION['theme']['text_link_color']['text'] ?? '#004083').";'>\n";
			echo "		<strong>".(is_numeric($contact_name) ? format_phone($contact_name) : escape($contact_name))."</strong>".$count."<br />\n";
			echo "	</a>\n";
			echo "	<div class='contact_message'>\n";
			echo "		".(!empty($message) && strlen($message) <= 100 ? escape($message) : substr($message,0,100).'...')."<br />\n";
			echo "	</div>\n";
			echo "</div>\n";
			//if ($selected) {
			//	echo "<script>$('#contact_current_name').html(\"<a href='callto:".escape($number)."'>".escape(format_phone($number))."</a>\");</script>\n";
			//}

			echo "</td></tr>\n";
		}
		echo "</table>\n";

		//echo "<script>\n";
		//foreach ($numbers as $number) {
		//	if (is_array($_SESSION['tmp']['messages']['contact_em'][$contact[$number]['contact_uuid']]) && @sizeof($_SESSION['tmp']['messages']['contact_em'][$contact[$number]['contact_uuid']]) != 0) {
		//		echo "$('img#contact_image_".$contact[$number]['contact_uuid']."').css('backgroundImage', 'url(' + $('img#src_message-bubble-image-em_".$contact[$number]['contact_uuid']."').attr('src') + ')');\n";
		//	}
		//}
		//echo "</script>\n";
	}
	else {
		echo "<div style='padding: 15px;'><center>&middot;&middot;&middot;</center>";
	}

	echo "</body>\n";
	//echo "<center>\n";
	//echo "	<span id='contacts_refresh_state'><img src='resources/images/refresh_active.gif' style='width: 16px; height: 16px; border: none; margin-top: 3px; cursor: pointer;' onclick=\"refresh_contacts_stop();\" alt=\"".$text['label-refresh_pause']."\" title=\"".$text['label-refresh_pause']."\"></span> ";
	//echo "</center>\n";

	echo "</html>\n";
?>
