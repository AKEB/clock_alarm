<?php
require_once('config.php');
require_once('functions.php');


if (constant('PASSWORD') != $_POST['password']) {
	echo "[]";
} else {
	$alarms = read_database();
	foreach ($alarms as $k=>$alarm) {
		if (isset($alarm['repeat'])) {
			$alarms[$k]['repeat_text'] = alarm_get_repeat_text($alarm['repeat']);
		} else {
			$time = mktime($alarm['hour'], $alarm['minute'], 0, $alarm['month'], $alarm['date'], date("Y"));
			$alarms[$k]['repeat_text'] = alarm_get_onetime_text($time);
		}
	}
	echo json_encode($alarms);
}
