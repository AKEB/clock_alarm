<?php
require_once('config.php');
require_once('functions.php');
require_once('sessions.php');

$session = session_check();

if (!$session) {
	echo "[]";
} else {
	$alarms = read_database();
	foreach ($alarms as $k=>$alarm) {
		if (isset($alarm['repeat'])) {
			$alarms[$k]['repeat_text'] = alarm_get_repeat_text($alarm['repeat']);
		} else {
			$time = mktime($alarm['hour'], $alarm['minute'], 0, date('m'), date('d'), date("Y"));
			if ($time < time()) $time += 86400;
			$alarms[$k]['repeat_text'] = alarm_get_onetime_text($time);
		}
	}
	echo json_encode($alarms);
}
