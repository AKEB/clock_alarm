<?php
require_once('config.php');
require_once('sessions.php');

$session = session_check();

if ($session) {
	echo md5_file(constant('DB_FILE_NAME'));
}

