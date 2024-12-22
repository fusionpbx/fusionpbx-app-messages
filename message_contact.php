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
	if (permission_exists('contact_view')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	if (file_exists($_SERVER["PROJECT_ROOT"]."/core/contacts/app_config.php")) {
		$text = $language->get(null, '/core/contacts');
	}
	else {
		$text = $language->get(null, '/app/contacts');
	}

//connect to the database
	$database = database::new();

//action add or update
	if (!empty($_REQUEST["id"]) && is_uuid($_REQUEST["id"])) {
		$contact_uuid = $_REQUEST["id"];
	}
	elseif (!empty($_REQUEST["destination"]) ) {
		$destination = $_REQUEST["destination"];
	}
	else {
		echo '<html><body>&nbsp;</body></html> ';
		exit;
	}

//main contact details
	$sql = "select * from v_contacts as c \n";
	$sql .= "where domain_uuid = :domain_uuid \n";
	if (!empty($destination)) {
		$sql .= "and contact_uuid in ( \n";
		$sql .= " select contact_uuid from v_contact_phones \n";
		$sql .= " where domain_uuid = :domain_uuid \n";
		$sql .= " and ( \n";
		$sql .= "  concat('+',phone_country_code, phone_number) = :destination \n";
		$sql .= "  or concat(phone_country_code, phone_number) = :destination \n";
		$sql .= "  or phone_number = :destination \n";
		$sql .= " ) \n";
		$sql .= ") \n";
		$parameters['destination'] = $destination;
	}
	if (!empty($contact_uuid)) {
		$sql .= "and contact_uuid = :contact_uuid ";
		$parameters['contact_uuid'] = $contact_uuid;
	}
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];

	$row = $database->select($sql, $parameters, 'row');
	if (!empty($row)) {
		$contact_uuid = $row["contact_uuid"];
		$contact_type = $row["contact_type"];
		$contact_organization = $row["contact_organization"];
		$contact_name_prefix = $row["contact_name_prefix"];
		$contact_name_given = $row["contact_name_given"];
		$contact_name_middle = $row["contact_name_middle"];
		$contact_name_family = $row["contact_name_family"];
		$contact_name_suffix = $row["contact_name_suffix"];
		$contact_nickname = $row["contact_nickname"];
		$contact_title = $row["contact_title"];
		$contact_category = $row["contact_category"];
		$contact_role = $row["contact_role"];
		$contact_time_zone = $row["contact_time_zone"];
		$contact_note = $row["contact_note"];
	}
	unset($sql, $parameters, $row);

//check contact permisions if this is set to enabled. default is false
	if ($_SESSION['contact']['permissions']['boolean'] == "true") {

		//get the available users for this contact
		$sql = "select * from v_users ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "order by username asc ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$users = $database->select($sql, $parameters ?? null, 'all');
		unset($sql, $parameters);

		//determine if contact assigned to a user
		if (!empty($users)) {
			foreach ($users as $user) {
				if ($user['contact_uuid'] == $contact_uuid) {
					$contact_user_uuid = $user['user_uuid'];
					break;
				}
			}
		}

		//get the assigned users that can view this contact
		$sql = "select u.username, u.user_uuid, a.contact_user_uuid from v_contacts as c, v_users as u, v_contact_users as a ";
		$sql .= "where c.contact_uuid = :contact_uuid ";
		$sql .= "and c.domain_uuid = :domain_uuid ";
		$sql .= "and u.user_uuid = a.user_uuid ";
		$sql .= "and c.contact_uuid = a.contact_uuid ";
		$sql .= "order by u.username asc ";
		$parameters['contact_uuid'] = $contact_uuid;
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$contact_users_assigned = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);

		//get the assigned groups that can view this contact
		$sql = "select g.*, cg.contact_group_uuid ";
		$sql .= "from v_groups as g, v_contact_groups as cg ";
		$sql .= "where cg.group_uuid = g.group_uuid ";
		$sql .= "and cg.domain_uuid = :domain_uuid ";
		$sql .= "and cg.contact_uuid = :contact_uuid ";
		$sql .= "and cg.group_uuid <> :group_uuid ";
		$sql .= "order by g.group_name asc ";
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['contact_uuid'] = $contact_uuid;
		$parameters['group_uuid'] = $_SESSION["user_uuid"];
		$contact_groups_assigned = $database->select($sql, $parameters, 'all');
		if (!empty($contact_groups_assigned)) {
			foreach ($contact_groups_assigned as $field) {
				$contact_groups[] = "'".$field['group_uuid']."'";
			}
		}
		unset($sql, $parameters);

		//get the available groups for this contact
		$sql = "select group_uuid, group_name from v_groups ";
		$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
		if (!empty($contact_groups)) {
			$sql .= "and group_uuid not in (".implode(',', $contact_groups).") ";
		}
		$sql .= "order by group_name asc ";
		$parameters['domain_uuid'] = $domain_uuid;
		$contact_groups_available = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters, $contact_groups);
	}

//determine title name
	if ($contact_name_given || $contact_name_family) {
		$contact_name = $contact_name_prefix ? escape($contact_name_prefix).' ' : null;
		$contact_name .= $contact_name_given ? escape($contact_name_given).' ' : null;
		$contact_name .= $contact_name_middle ? escape($contact_name_middle).' ' : null;
		$contact_name .= $contact_name_family ? escape($contact_name_family).' ' : null;
		$contact_name .= $contact_name_suffix ? escape($contact_name_suffix).' ' : null;
	}
	else {
		$contact_name = $contact_organization;
	}

//show the content
	echo "<!DOCTYPE html>\n";
	echo "<html>\n";
	echo "<head>\n";

	echo "<meta charset='utf-8'>\n";
	echo "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>\n";
	echo "<meta http-equiv='X-UA-Compatible' content='IE=edge'>\n";
	echo "<meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no' />\n";
	echo "<meta name='robots' content='noindex, nofollow, noarchive' />\n";

	echo "<link rel='stylesheet' type='text/css' href='".PROJECT_PATH."/resources/fontawesome/css/all.min.css.php'>\n";
	echo "<link rel='stylesheet' type='text/css' href='".PROJECT_PATH."/resources/bootstrap/css/bootstrap.min.css.php'>\n";
	echo "<script language='JavaScript' type='text/javascript' src='".PROJECT_PATH."/resources/fontawesome/js/solid.min.js.php' defer></script>\n";
	echo "<script language='JavaScript' type='text/javascript' src='".PROJECT_PATH."/resources/bootstrap/js/bootstrap.min.js.php'></script>\n";

//css
	echo "<link rel='stylesheet' type='text/css' href='/themes/default/css.php'>\n";
	echo "<style>\n";
	echo "	body {\n";
	echo "		margin-right: 0;\n";
	echo "		}\n";
	echo "	div.box.contact-details {\n";
	echo "		padding: 10px !important;\n";
	echo "		}\n";
	echo "</style>\n";

//end the header and start the body
	echo "</head>\n";
	echo "<body>\n";

	echo "<div id='main_content' style='margin-top: 0; width: calc(100% - 10px); padding-right: 0;'>\n";

//show the content
	echo "<div class='action_bar' id='action_bar' style='position: relative; top: 0; width: calc(100% + 13px); margin-left: -10px; padding-right: 0; margin-bottom: 0;'>\n";
	echo "	<div class='heading'><b>".($contact_name ? $contact_name : $text['header-contact-edit'])."</b></div>\n";
	echo "	<div class='actions'>\n";
	if (!empty($contact_user_uuid) && permission_exists('user_edit') && is_uuid($contact_user_uuid)) {
		echo button::create(['type'=>'button','label'=>$text['button-user'],'icon'=>'user','collapse'=>'hide-sm-dn','link'=>'../../core/users/user_edit.php?id='.urlencode($contact_user_uuid)]);
	}
	if (permission_exists('contact_edit')) {
		if (empty($contact_uuid)) {
			echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$_SESSION['theme']['button_icon_add'],'id'=>'btn_add','style'=>'margin-left: 15px; margin-right: 0;','onclick'=>"window.open('../contacts/contact_edit.php');"]);
		}
		else {
			echo button::create(['type'=>'button','label'=>$text['button-edit'],'icon'=>$_SESSION['theme']['button_icon_edit'],'id'=>'btn_edit','style'=>'margin-left: 15px; margin-right: 0;','onclick'=>"window.open('../contacts/contact_edit.php?id=".urlencode($contact_uuid)."');"]);
		}
	}
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if ($contact_title || $contact_organization) {
		echo ($contact_title ? '<i>'.$contact_title.'</i>' : null).($contact_title && $contact_organization ? ', ' : null).($contact_organization ? '<strong>'.$contact_organization.'</strong>' : null)."\n";
	}
	else {
		echo $contact_note."\n";
	}
	echo "<br />\n";

	echo "<div class='grid' style='grid-gap: 10px; grid-template-columns: auto;'>\n";

//general info
	echo "	<div class='box contact-details'>\n";
	echo "		<div class='grid contact-details'>\n";
	echo "			<div class='box'><b class='fas fa-user fa-fw fa-md'></b></div>\n";
	echo "			<div class='box'>\n";
	echo "				<div class='grid' style='grid-template-columns: 70px auto;'>\n";
		//nickname
			if ($contact_nickname) {
				echo "<div class='box contact-details-label'>".$text['label-contact_nickname']."</div>\n";
				echo "<div class='box'>\"".escape($contact_nickname)."\"</div>\n";
			}
		//name
			if ($contact_name_given) {
				echo "<div class='box contact-details-label'>".$text['label-name']."</div>\n";
				echo "<div class='box'>".escape($contact_name_given).(!empty($contact_name_family) ? ' '.escape($contact_name_family) : null)."</div>\n";
			}
		//contact type
			if ($contact_type) {
				echo "<div class='box contact-details-label'>".$text['label-contact_type']."</div>\n";
				echo "<div class='box'>";
				if (!empty($_SESSION["contact"]["type"])) {
					sort($_SESSION["contact"]["type"]);
					foreach ($_SESSION["contact"]["type"] as $type) {
						if ($contact_type == $type) {
							echo escape($type);
						}
					}
				}
				else if ($text['option-contact_type_'.$contact_type]) {
					echo $text['option-contact_type_'.$contact_type];
				}
				else {
					echo escape($contact_type);
				}
				echo "</div>\n";
			}
		//category
			if ($contact_category) {
				echo "<div class='box contact-details-label'>".$text['label-contact_category']."</div>\n";
				echo "<div class='box'>";
				if (!empty($_SESSION["contact"]["category"])) {
					sort($_SESSION["contact"]["category"]);
					foreach ($_SESSION["contact"]["category"] as $category) {
						if ($contact_category == $category) {
							echo escape($category);
							break;
						}
					}
				}
				else {
					echo escape($contact_category);
				}
				echo "</div>\n";
			}
		//role
			if ($contact_role) {
				echo "<div class='box contact-details-label'>".$text['label-contact_role']."</div>\n";
				echo "<div class='box'>";
				if (!empty($_SESSION["contact"]["role"])) {
					sort($_SESSION["contact"]["role"]);
					foreach ($_SESSION["contact"]["role"] as $role) {
						if ($contact_role == $role) {
							echo escape($role);
							break;
						}
					}
				}
				else {
					echo escape($contact_role);
				}
				echo "</div>\n";
			}
		//time_zone
			if ($contact_time_zone) {
				echo "<div class='box contact-details-label'>".$text['label-contact_time_zone']."</div>\n";
				echo "<div class='box'>";
				echo $contact_time_zone."<br>\n";
				echo "</div>\n";
			}
		//users (viewing contact)
			if (permission_exists('contact_user_view') && !empty($contact_users_assigned)) {
				echo "<div class='box contact-details-label'>".$text['label-users']."</div>\n";
				echo "<div class='box'>";
				foreach ($contact_users_assigned as $field) {
					echo escape($field['username'])."<br>\n";
				}
				echo "</div>\n";
			}
		//groups (viewing contact)
			if (permission_exists('contact_group_view') && !empty($contact_groups_assigned)) {
				echo "<div class='box contact-details-label'>".$text['label-groups']."</div>\n";
				echo "<div class='box'>";
				foreach ($contact_groups_assigned as $field) {
					echo escape($field['group_name'])."<br>\n";
				}
				echo "</div>\n";
			}
	echo "				</div>\n";
	echo "			</div>\n";
	echo "		</div>\n";
	echo "	</div>\n";

//numbers
	if (permission_exists('contact_phone_view')) {
		echo "	<div class='box contact-details'>\n";
		echo "		<div class='grid contact-details'>\n";
		echo "			<div class='box' title=\"".$text['label-phone_numbers']."\"><b class='fas fa-hashtag fa-fw fa-lg'></b></div>\n";
		echo "			<div class='box'>\n";
		require 'core/contacts/contact_phones_view.php';
		echo "			</div>\n";
		echo "		</div>\n";
		echo "	</div>\n";
	}

//emails
	if (permission_exists('contact_email_view')) {
		echo "	<div class='box contact-details'>\n";
		echo "		<div class='grid contact-details'>\n";
		echo "			<div class='box' title=\"".$text['label-emails']."\"><b class='fas fa-envelope fa-fw fa-lg'></b></div>\n";
		echo "			<div class='box'>\n";
		require 'core/contacts/contact_emails_view.php';
		echo "			</div>\n";
		echo "		</div>\n";
		echo "	</div>\n";
	}

//addresses
	if (permission_exists('contact_address_view')) {
		echo "	<div class='box contact-details'>\n";
		echo "		<div class='grid contact-details'>\n";
		echo "			<div class='box' title=\"".$text['label-addresses']."\"><b class='fas fa-map-marker-alt fa-fw fa-lg'></b></div>\n";
		echo "			<div class='box'>\n";
		require 'core/contacts/contact_addresses_view.php';
		echo "			</div>\n";
		echo "		</div>\n";
		echo "	</div>\n";
	}

//urls
	if (permission_exists('contact_url_view')) {
		echo "	<div class='box contact-details'>\n";
		echo "		<div class='grid contact-details'>\n";
		echo "			<div class='box' title=\"".$text['label-urls']."\"><b class='fas fa-link fa-fw fa-lg'></b></div>\n";
		echo "			<div class='box'>\n";
		require "core/contacts/contact_urls_view.php";
		echo "			</div>\n";
		echo "		</div>\n";
		echo "	</div>\n";
	}

//relations
	if (permission_exists('contact_relation_view')) {
		echo "	<div class='box contact-details'>\n";
		echo "		<div class='grid contact-details'>\n";
		echo "			<div class='box' title=\"".$text['header-contact_relations']."\"><b class='fas fa-project-diagram fa-fw fa-lg'></b></div>\n";
		echo "			<div class='box'>\n";
		require "core/contacts/contact_relations_view.php";
		echo "			</div>\n";
		echo "		</div>\n";
		echo "	</div>\n";
	}

//attachments
	if (permission_exists('contact_attachment_view')) {
		echo "	<div class='box contact-details'>\n";
		echo "		<div class='grid contact-details'>\n";
		echo "			<div class='box' title=\"".$text['label-attachments']."\"><b class='fas fa-paperclip fa-fw fa-lg'></b></div>\n";
		echo "			<div class='box'>\n";
		require "core/contacts/contact_attachments_view.php";
		echo "			</div>\n";
		echo "		</div>\n";
		echo "	</div>\n";
	}

//times
	if (permission_exists('contact_time_view')) {
		echo "	<div class='box contact-details'>\n";
		echo "		<div class='grid contact-details'>\n";
		echo "			<div class='box' title=\"".$text['header_contact_times']."\"><b class='fas fa-clock fa-fw fa-lg'></b></div>\n";
		echo "			<div class='box'>\n";
		require "core/contacts/contact_times_view.php";
		echo "			</div>\n";
		echo "		</div>\n";
		echo "	</div>\n";
	}

//extensions
	if (permission_exists('contact_extension_view')) {
		echo "	<div class='box contact-details'>\n";
		echo "		<div class='grid contact-details'>\n";
		echo "			<div class='box' title=\"".$text['label-contact_extensions']."\"><b class='fas fa-fax fa-fw fa-lg'></b></div>\n";
		echo "			<div class='box'>\n";
		require "core/contacts/contact_extensions_view.php";
		echo "			</div>\n";
		echo "		</div>\n";
		echo "	</div>\n";
	}

//notes
	if (permission_exists('contact_note_view')) {
		echo "	<div class='box contact-details'>\n";
		echo "		<div class='grid contact-details'>\n";
		echo "			<div class='box' title=\"".$text['label-contact_notes']."\"><b class='fas fa-sticky-note fa-fw fa-lg'></b></div>\n";
		echo "			<div class='box'>\n";
		require "core/contacts/contact_notes_view.php";
		echo "			</div>\n";
		echo "		</div>\n";
		echo "	</div>\n";
	}

	echo "</div>\n";
echo "</body>\n";
echo "</html>\n";

?>
