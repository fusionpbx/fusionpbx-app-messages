<?php

//add fax email templates
	if ($domains_processed == 1) {

		//build the array
		$x = 0;
		$array['email_templates'][$x]['email_template_uuid'] = 'f91bbd91-46a6-4805-9fa3-e622bbb22989';
		$array['email_templates'][$x]['template_language'] = 'en-us';
		$array['email_templates'][$x]['template_category'] = 'message';
		$array['email_templates'][$x]['template_subcategory'] = 'inbound';
		$array['email_templates'][$x]['template_subject'] = "New message received from \${message_from}";
		$array['email_templates'][$x]['template_body'] = "<html>\n";
		$array['email_templates'][$x]['template_body'] .= "<body>\n";
		$array['email_templates'][$x]['template_body'] .= "Message from <a href='tel:\${message_from}'>\${message_from}</a><br><br>\n";
		$array['email_templates'][$x]['template_body'] .= "To: \${message_to}<br>\n";
		$array['email_templates'][$x]['template_body'] .= "Received: \${message_date}<br>\n";
		$array['email_templates'][$x]['template_body'] .= "Message: \${message_text}<br>\n";
		$array['email_templates'][$x]['template_body'] .= "</body>\n";
		$array['email_templates'][$x]['template_body'] .= "</html>\n";
		$array['email_templates'][$x]['template_type'] = "html";
		$array['email_templates'][$x]['template_enabled'] = "true";
		$x++;

		//build array of email template uuids
		foreach ($array['email_templates'] as $row) {
			if (is_uuid($row['email_template_uuid'])) {
				$uuids[] = $row['email_template_uuid'];
			}
		}

		//add the email templates to the database
		if (!empty($uuids)) {
			$sql = "select * from v_email_templates where ";
			foreach ($uuids as $index => $uuid) {
				$sql_where[] = "email_template_uuid = :email_template_uuid_".$index;
				$parameters['email_template_uuid_'.$index] = $uuid;
			}
			$sql .= implode(' or ', $sql_where);
			$email_templates = $database->select($sql, $parameters, 'all');
			unset($sql, $sql_where, $parameters);

			//remove templates that already exist from the array
			foreach ($array['email_templates'] as $index => $row) {
				if (is_array($email_templates) && @sizeof($email_templates) != 0) {
					foreach($email_templates as $email_template) {
						if ($row['email_template_uuid'] == $email_template['email_template_uuid']) {
							unset($array['email_templates'][$index]);
						}
					}
				}
			}
			unset($email_templates, $index);
		}

		//add the missing email templates
		if (!empty($array['email_templates'])) {
			//add the temporary permission
			$p = new permissions;
			$p->add("email_template_add", 'temp');
			$p->add("email_template_edit", 'temp');

			//save the data
			$database->app_name = 'email_templates';
			$database->app_uuid = '8173e738-2523-46d5-8943-13883befd2fd';
			$database->save($array);
			//$message = $database->message;

			//remove the temporary permission
			$p->delete("email_template_add", 'temp');
			$p->delete("email_template_edit", 'temp');
		}

		//remove the array
		unset($array);

	}

?>
