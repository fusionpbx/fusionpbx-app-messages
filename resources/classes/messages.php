<?php

//define the messages classs
if (!class_exists('messages')) {
	class messages {

		/**
		 * declare private variables
		 */
		private $app_name;
		private $app_uuid;
		private $permission_prefix;
		private $list_page;
		private $table;
		private $uuid_prefix;

		/**
		 * called when the object is created
		 */
		public function __construct() {

			//assign private variables
				$this->app_name = 'messages';
				$this->app_uuid = '4a20815d-042c-47c8-85df-085333e79b87';
				$this->permission_prefix = 'message_';
				$this->list_page = 'messages_log.php';
				$this->table = 'messages';
				$this->uuid_prefix = 'message_';

		}

		/**
		 * called when there are no references to a particular object
		 * unset the variables used in the class
		 */
		public function __destruct() {
			foreach ($this as $key => $value) {
				unset($this->$key);
			}
		}

		/**
		 * delete records
		 */
		public function delete($records) {
			if (permission_exists($this->permission_prefix.'delete')) {

				//add multi-lingual support
					$language = new text;
					$text = $language->get();

				//validate the token
					$token = new token;
					if (!$token->validate($_SERVER['PHP_SELF'])) {
						message::add($text['message-invalid_token'],'negative');
						header('Location: '.$this->list_page);
						exit;
					}

				//delete multiple records
					if (is_array($records) && @sizeof($records) != 0) {

						//build the delete array
							foreach ($records as $x => $record) {
								if ($record['checked'] == 'true' && is_uuid($record['uuid'])) {
									$array[$this->table][$x][$this->uuid_prefix.'uuid'] = $record['uuid'];
									$array[$this->table][$x]['domain_uuid'] = $_SESSION['domain_uuid'];
									$array['message_media'][$x][$this->uuid_prefix.'uuid'] = $record['uuid'];
									$array['message_media'][$x]['domain_uuid'] = $_SESSION['domain_uuid'];
								}
							}

						//delete the checked rows
							if (is_array($array) && @sizeof($array) != 0) {

								//grant temporary permissions
									$p = new permissions;
									$p->add('message_media_delete', 'temp');

								//execute delete
									$database = new database;
									$database->app_name = $this->app_name;
									$database->app_uuid = $this->app_uuid;
									$database->delete($array);
									unset($array);

								//revoke temporary permissions
									$p->delete('message_media_delete', 'temp');

								//set message
									message::add($text['message-delete']);
							}
							unset($records);
					}
			}
		} //method

		/**
		 * send a message
		 */
		public function send($message_type, $message_from, $message_to, $message_text, $message_media = '') {

			//santize the data
				$message_type = 'sms';
				$message_from = preg_replace("/[^\+?0-9]/", "", $message_from);
				$message_to = preg_replace('/[^\+?0-9]/', '', $message_to);

			//error check
				if (!is_numeric($message_from) || !is_numeric($message_to)) {
					exit;
				}

			//handle media (if any)
				if (!empty($message_media) && is_array($message_media) && @sizeof($message_media) != 0) {
					// reorganize media array, ignore errored files
					$f = 0;
					foreach ($message_media['error'] as $index => $error) {
						if ($error == 0) {
							$tmp_media[$f]['uuid'] = uuid();
							$tmp_media[$f]['name'] = $message_media['name'][$index];
							$tmp_media[$f]['type'] = $message_media['type'][$index];
							$tmp_media[$f]['tmp_name'] = $message_media['tmp_name'][$index];
							$tmp_media[$f]['size'] = $message_media['size'][$index];
							$f++;
						}
					}
					$message_media = $tmp_media;
					unset($tmp_media, $f);
				}
				$message_type = is_array($message_media) && @sizeof($message_media) != 0 ? 'mms' : 'sms';

			//get the contact uuid
				//$sql = "select c.contact_uuid ";
				//$sql .= "from v_contacts as c, v_contact_phones as p ";
				//$sql .= "where p.contact_uuid = c.contact_uuid ";
				//$sql .= "and p.phone_number like :phone_number ";
				//$sql .= "and c.domain_uuid = :domain_uuid ";
				//$parameters['phone_number'] = '%'.$message_to.'%';
				//$parameters['domain_uuid'] = $domain_uuid;
				//$database = new database;
				//$contact_uuid = $database->select($sql, $parameters, 'column');
				//unset($sql, $parameters);

			//prepare message to send
				$message['to'] = $message_to;
				$message['text'] = $message_text;
				//if (is_array($message_media) && @sizeof($message_media) != 0) {
				//	$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
				//	foreach ($message_media as $index => $media) {
				//		$path = $protocol.$_SERVER['HTTP_HOST'].'/app/messages/message_media.php?id='.$media['uuid'].'&action=download&.'.strtolower(pathinfo($media['name'], PATHINFO_EXTENSION));
				//		$message['media'][] = $path;
				//	}
				//}
				$http_content = json_encode($message);

			//use the phone number to get the destination details
				$sql = "SELECT * FROM v_destinations ";
				$sql .= "WHERE ( ";
				$sql .= "	destination_prefix || destination_area_code || destination_number = :destination_number ";
				$sql .= "	OR destination_trunk_prefix || destination_area_code || destination_number = :destination_number ";
				$sql .= "	OR destination_prefix || destination_number = :destination_number ";
				$sql .= "	OR '+' || destination_prefix || destination_number = :destination_number ";
				$sql .= "	OR '+' || destination_prefix || destination_area_code || destination_number = :destination_number ";
				$sql .= "	OR destination_area_code || destination_number = :destination_number ";
				$sql .= "	OR destination_number = :destination_number ";
				$sql .= ") ";
				$sql .= "and provider_uuid is not null ";
				$sql .= "and destination_enabled = 'true'; ";
				$parameters['destination_number'] = $message_from;
				if (!empty($debug)) {
					file_put_contents($log_file, "sql: ".$sql."\n", FILE_APPEND);
					//echo $sql."\n";
					file_put_contents($log_file, print_r($parameters, true)."\n", FILE_APPEND);
				}
				$database = new database;
				$row = $database->select($sql, $parameters, 'row');
				//view_array($row, false);
				if (isset($row)) {
					$domain_uuid = $row['domain_uuid'];
					$provider_uuid = $row['provider_uuid'];
					$user_uuid = $row['user_uuid'];
					$group_uuid = $row['group_uuid']; //TF
					unset($row);
				}
				//if (!empty($debug)) {
					view_array($row,false);
					//file_put_contents($log_file, print_r($row, true)."\n", FILE_APPEND);
				//}
				unset($sql, $parameters);

			//debug
				//echo "provider_uuid: ".$provider_uuid."\n";

			//get the provider settings
				$sql = "select provider_setting_category, provider_setting_subcategory, ";
				$sql .= "provider_setting_name, provider_setting_value, provider_setting_order \n";
				$sql .= "from v_provider_settings \n";
				$sql .= "where provider_uuid = :provider_uuid \n";
				$sql .= "and provider_setting_category = 'outbound' \n";
				$sql .= "and provider_setting_enabled = 'true'; \n";
				$parameters['provider_uuid'] = $provider_uuid;
				$database = new database;
				$provider_settings = $database->select($sql, $parameters, 'all');
				unset($parameters);
				//echo $sql;
				//print_r($parameters);
				//print_r($provider_settings);
				//
				//echo "\n";

			//process the provider settings array
				foreach ($provider_settings as $row) {
					//format the phone numbers
					if ($row['provider_setting_subcategory'] == 'format') {
						if ($row['provider_setting_name'] == 'message_from') {
							$message_from = format_string($row['provider_setting_value'], $message_from);
						}
						if ($row['provider_setting_name'] == 'message_to') {
							$message_to = format_string($row['provider_setting_value'], $message_to);
						}
					}
				}

			//continue only if message from and to have a value
				if (!isset($message_from) || !isset($message_to)) {
					return false;
				}

			//add the permission
				$p = new permissions;
				$p->add('message_queue_add', 'temp');

			//build the message array
				$message_queue_uuid = uuid();
				$array['message_queue'][0]['domain_uuid'] = $_SESSION["domain_uuid"];
				$array['message_queue'][0]['message_queue_uuid'] = $message_queue_uuid;
				$array['message_queue'][0]['user_uuid'] = $_SESSION["user_uuid"];
				$array['message_queue'][0]['group_uuid'] = $group_uuid;
				//$array['message_queue'][0]['contact_uuid'] = $contact_uuid;
				$array['message_queue'][0]['provider_uuid'] = $provider_uuid;
				$array['message_queue'][0]['hostname'] = system('hostname');
				$array['message_queue'][0]['message_status'] = 'waiting';
				$array['message_queue'][0]['message_type'] = $message_type;
				$array['message_queue'][0]['message_direction'] = 'outbound';
				$array['message_queue'][0]['message_date'] = 'now()';
				$array['message_queue'][0]['message_from'] = $message_from;
				$array['message_queue'][0]['message_to'] = $message_to;
				$array['message_queue'][0]['message_text'] = $message_text;
				//view_array($array);

			//build message media array (if necessary)
				$media_exists = false;
				if (is_array($message_media) && @sizeof($message_media) != 0) {
					$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
					foreach($message_media as $index => $media) {
						//create the media path
						$media_url = $protocol.$_SERVER['HTTP_HOST'].'/app/messages/media/'.$media['uuid'].'/'.$media['name'];

						//add the media to the array
						$array['message_media'][$index]['message_media_uuid'] = $media['uuid'];
						$array['message_media'][$index]['message_uuid'] = $message_queue_uuid;
						$array['message_media'][$index]['domain_uuid'] = $_SESSION["domain_uuid"];
						$array['message_media'][$index]['user_uuid'] = $_SESSION["user_uuid"];
						$array['message_media'][$index]['message_media_name'] = $media['name'];
						$array['message_media'][$index]['message_media_type'] = strtolower(pathinfo($media['name'], PATHINFO_EXTENSION));
						$array['message_media'][$index]['message_media_date'] = 'now()';
						$array['message_media'][$index]['message_media_url'] = $media_url;
						$array['message_media'][$index]['message_media_content'] = base64_encode(file_get_contents($media['tmp_name']));
					}
					$p->add('message_media_add', 'temp');
					$media_exists = true;
				}

			//no message or media to send - do not send
				//if ($message_text == '' && !$media_exists) {
				//	return;
				//}

			//save to the data
				$database = new database;
				$database->app_name = 'messages';
				$database->app_uuid = '4a20815d-042c-47c8-85df-085333e79b87';
				$database->save($array, false);
				//$message = $database->message;
				//view_array($message, true);
				unset($array);

			//remove the permission
				$p->delete('message_queue_add', 'temp');
				$p->delete('message_media_add', 'temp');

		} //method
		
	} //class
}

?>
