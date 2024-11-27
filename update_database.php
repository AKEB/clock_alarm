<?php
require_once('lib/common.php');

$session = Session::check();

if (!$session) {
	echo "{}";
} else {
	$action = strval($_POST['action'] ?? '');
	$index = intval($_POST['index'] ?? null);
	switch ($action) {
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
				'update_time' => time(),
				'repeat' => null,
			];
			if ($repeat) $alarm['repeat'] = (array)$_POST['repeat'];


			if ($action == 'add') {
				$alarm['status'] = true;
				$alarm['id'] = null;
				$alarm['create_time'] = time();
				$alarm['play_last_time'] = 0;
			} else {
				$alarm['id'] = $index;
				$old = \ClockAlarm::get($index);
				$alarm['status'] = true;
			}
			$alarm['id'] = \ClockAlarm::save($alarm);
			break;
		case 'delete':
			\ClockAlarm::delete(['id' => $index]);
			break;
		case 'change_status':
			$alarm = \ClockAlarm::get(['id' => $index]);
			if ($alarm) {
				$alarm['status'] = boolval($_POST['status']);
				$alarm['update_time'] = time();
				\ClockAlarm::save($alarm);
			}
			break;
		default:
			break;
	}
	$response = \ClockAlarm::getAlarmsJson();
	echo $response;
}
