<?php
require_once('config.php');
require_once('functions.php');

if (constant('PASSWORD') != $_POST['password']) {
	echo "[]";
} else {
	$alarms = (array)read_database();
	$index = intval($_POST['index'] ?? null);
	$action = strval($_POST['action'] ?? '');

	switch ($action) {
		case 'change_status':
			$alarms[$index]['status'] = boolval($_POST['status']);
			break;
		case 'delete':
			unset($alarms[$index]);
			break;
		case 'add':
		case 'edit':
			$repeat = false;
			foreach ($_POST['repeat'] as $k=>$v) {
				$_POST['repeat'][$k] = $v == 'false' || !$v ? false : true;
				if ($_POST['repeat'][$k]) $repeat = true;
			}
			$alarm = [
				'hour' => intval($_POST['hour']),
				'minute' => intval($_POST['minute']),
				'sound' => strval($_POST['sound']),
				'volume' => intval($_POST['volume']),
				'status' => true,
			];
			if ($repeat) $alarm['repeat'] = (array)$_POST['repeat'];

			if ($action == 'add') {
				$alarm['status'] = true;
				$alarms[] = $alarm;
			} else {
				$alarm['status'] = $alarms[$index]['status'];
				$alarms[$index] = $alarm;
			}
			break;
		case 'change_sound_test':
			$volume = intval($_POST['volume']);
			exec('nohup ./sound_check.sh sound_check.mp3 '.$volume.' &> /dev/null & ');
			break;
	}
	$alarms = array_values($alarms);
	write_database($alarms);

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
