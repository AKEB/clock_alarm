<?php
require_once('config.php');
require_once('functions.php');
require_once('sessions.php');

$session = session_check();

if (!$session) {
	echo "[]";
} else {
	if ($fp = fopen(constant('DB_FILE_NAME'), 'w')) {
		// Lock File
		do {
			$canWrite = flock($fp, LOCK_EX);
			// If lock not obtained sleep for 0 - 100 milliseconds, to avoid collision and CPU load
			if(!$canWrite) usleep(round(rand(0, 100)*1000));
		} while ((!$canWrite) && ((microtime(TRUE)-$startTime) < 5));

		if ($canWrite) {
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
			}
			$alarms = array_values($alarms);
			write_database($alarms, true);
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

			flock($fp, LOCK_UN);
		} else {
			echo "[]";
		}
		fclose($fp);
	} else {
		echo "[]";
	}
}
