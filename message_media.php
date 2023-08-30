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

//get media uuid
	$message_media_uuid = $_GET['id'];
	$action = $_GET['action'];
	if (!empty($_GET['width']) && is_numeric(str_replace('px','',$_GET['width']))) {
		$width = str_replace('px','',$_GET['width']);
	}

//get media
	if (is_uuid($message_media_uuid)) {

		$sql = "select message_media_name, message_media_type, message_media_url, message_media_content ";
		$sql .= "from v_message_media ";
		$sql .= "where message_media_uuid = :message_media_uuid ";
		if (is_uuid($_SESSION['user_uuid'])) {
			$sql .= "and user_uuid = :user_uuid ";
			$parameters['user_uuid'] = $_SESSION['user_uuid'];
		}
		$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
		$parameters['message_media_uuid'] = $message_media_uuid;
		$parameters['domain_uuid'] = $domain_uuid;
		$database = new database;
		$media = $database->select($sql, $parameters, 'row');
		unset($sql, $parameters);

		switch (strtolower($media['message_media_type'])) {
			case 'jpg': $content_type = 'image/jpeg'; break;
			case 'jpeg': $content_type = 'image/jpeg'; break;
			case 'png': $content_type = 'image/png'; break;
			case 'gif': $content_type = 'image/gif'; break;
			case 'aac': $content_type = 'audio/aac'; break;
			case 'wav': $content_type = 'audio/wav'; break;
			case 'mp3': $content_type = 'audio/mpeg'; break;
			case 'mp2': $content_type = 'video/mpeg'; break;
			case 'm4v': $content_type = 'video/mp4'; break;
			case 'pdf': $content_type = 'application/pdf'; break;
			case 'doc': $content_type = 'application/vnd.ms-word'; break;
			case 'docx': $content_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'; break;
			case 'xls': $content_type = 'application/vnd.ms-excel'; break;
			case 'xlsx': $content_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'; break;
			case 'ppt': $content_type = 'application/vnd.ms-powerpoint'; break;
			case 'pptx': $content_type = 'application/vnd.openxmlformats-officedocument.presentationml.presentation'; break;
			case 'zip': $content_type = 'application/zip'; break;
			default: $content_type = 'application/octet-stream'; break;
		}
		
		//show the image inline when the action is not set
		if (empty($action) && ($content_type == 'image/jpeg' || $content_type == 'image/png' || $content_type == 'image/gif')) {
			$action = 'download-inline';
		}

		switch ($action) {
			case 'download':
				//header("Content-type: ".$content_type."; charset=utf-8");
				//header("Content-Disposition: attachment; filename=\"".$filename."\"");

				header('Content-Description: File Transfer');
				header('Content-Type: application/octet-stream');
				header("Content-Disposition: attachment; filename=\"".(strlen($media['message_media_name']) <= 36 ? $media['message_media_name'] : $message_media_uuid.'.'.strtolower($media['message_media_type']))."\"");
				header('Content-Transfer-Encoding: binary');
				header('Expires: 0');
				header('Cache-Control: must-revalidate');
				header('Pragma: public');
				header("Content-Length: ".strlen(base64_decode($media['message_media_content'])));

				echo base64_decode($media['message_media_content']);
				break;
			case 'download-inline':
				//header("Content-type: ".$content_type."; charset=utf-8");
				//header("Content-Disposition: attachment; filename=\"".$filename."\"");
				header("Content-Type: ".$content_type);
				header("Content-Disposition: inline; filename=\"".$media['message_media_name']."\"");
				header("Content-Length: ".strlen(base64_decode($media['message_media_content'])));
				echo base64_decode($media['message_media_content']);
				break;
			case 'download-original':
				header("Content-type: ".$content_type."; charset=utf-8");
				header("Content-Disposition: attachment; filename=\"".$media['message_media_name']."\"");
				header("Content-Length: ".strlen(base64_decode($media['message_media_content'])));
				echo base64_decode($media['message_media_content']);
				break;
			case 'thumbnail':
				if ($content_type = 'image/jpeg' || $content_type = 'image/png' || $content_type = 'image/gif') {

					//get the image size
					$image_size = getimagesize('data://application/octet-stream;base64,' . $media['message_media_content']);
					$source_width = $image_size[0];
					$source_height = $image_size[1];

					//read the image
					$source_image = imagecreatefromstring(base64_decode($media['message_media_content']));

					//working
					//header('Content-Type: image/jpeg');
					//imagejpeg($source_image);

					//get the image width and height
					$source_width = imagesx($source_image);
					$source_height = imagesy($source_image);

					//calculate dimensions for the thumbmail
					$destination_width = $width;
					$destination_height = floor($source_height * ($destination_width / $source_width));

					//send content type http header
					header('Content-Type: '.$content_type);

					//create the image, resample it and then stream binary
					$destination_image = imagecreatetruecolor($destination_width, $destination_height);
					//imagealphablending($img_dest, false);
					//imagesavealpha($img_dest, true);
					if ($destination_image !== false) {
						imagecopyresampled($destination_image, $source_image, 0, 0, 0, 0, $destination_width, $destination_height, $source_width, $source_height);
						if ($content_type = 'image/jpeg') {
							imagejpeg($destination_image);
						}
						if ($content_type = 'image/png') {
							imagejpeg($destination_image);
						}
						if ($content_type = 'image/gif') {
							imagejpeg($destination_image);
						}
					}
				}

				break;
			case 'display':
				echo "	<table cellpadding='0' cellspacing='0' border='0' width='100%' height='100%'>\n";
				echo "		<tr>\n";
				echo "			<td align='center' valign='middle'>\n";
				echo "				<img src=\"data:".$content_type.";base64,".$media['message_media_content']."\" style='width: auto; max-width: 95%; height: auto; max-height: 800px; box-shadow: 0px 1px 20px #888; cursor: pointer;' onclick=\"$('#message_media_layer').fadeOut(200);\" oncontextmenu=\"window.open('message_media.php?id=".$message_media_uuid."&src=".$message_media_source."&action=download'); return false;\" title=\"Click to Close, Right-Click to Save\">\n";
				echo "			</td>\n";
				echo "		</tr>\n";
				echo "	</table>\n";
				break;
		}

	}

?>
