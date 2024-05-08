<?php
require_once('config.php');
require_once('functions.php');

if (constant('PASSWORD') != $_POST['password']) {
	echo "[]";
} else {
	$alarms = read_database();
	$index = $_POST['index'];
	$action = $_POST['action'];

	switch ($action) {
		case 'change_status':
			$alarms[$index]['status'] = boolval($_POST['status']);
			break;

	}
	write_database($alarms);
	echo json_encode($alarms);
}
