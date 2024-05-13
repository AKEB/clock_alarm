<?php
require_once('lib/common.php');

$session = Session::check();

if ($session) {
	echo md5_file(constant('DB_FILE_NAME'));
}

