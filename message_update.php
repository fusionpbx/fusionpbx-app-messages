<?php

//set the include path
	$conf = glob("{/usr/local/etc,/etc}/fusionpbx/config.conf", GLOB_BRACE);
	set_include_path(parse_ini_file($conf[0])['document.root']);

//includes files
	require_once "resources/require.php";
	include "resources/classes/cache.php";

//get the last message in the cache
	if (is_uuid($_GET['id'])) {
		$cache = new cache;
		echo $cache->get("messages:user:last_message:".$_GET['id']);
	}

?>
