<?php
require_once('config.php');
require_once('functions.php');
$startTime = time();

addToLog("Start");
if ($fp = fopen(constant('DB_FILE_NAME'), 'r+')) {
	// Lock File
	addToLog("lock file");
	do {
		$canWrite = flock($fp, LOCK_EX);
		// If lock not obtained sleep for 0 - 100 milliseconds, to avoid collision and CPU load
		if(!$canWrite) usleep(round(rand(0, 100)*1000));
	} while ((!$canWrite) && ((microtime(TRUE)-$startTime) < 5));

	if ($canWrite) {
		addToLog("canWrite");
		$alarms = read_database();

		$week = intval(date("w")) - 1;
		$hour = intval(date("G"));
		$minute = intval(date("i"));

		foreach($alarms as $k=>$alarm) {
			if (!$alarm['status']) continue;
			// addToLog('1 '.var_export($alarm, true));
			if (isset($alarm['repeat'])) {
				if (!$alarm['repeat'][$week]) continue;
			}

			// addToLog('2 '.var_export($alarm, true));
			if ($alarm['hour'] != $hour) continue;
			if ($alarm['minute'] != $minute) continue;
			// addToLog('3 '.var_export($alarm, true));
			if (!isset($alarm['sound']) || !$alarm['sound']) $alarm['sound'] = 'example';

			if (!file_exists('sounds/'.$alarm['sound'].'.mp3')) {
				$alarm['sound'] = 'example';
			}

			if (!isset($alarm['repeat'])) {
				$alarms[$k]['status'] = false;
				write_database($alarms, true);
			}
   addToLog("play sound");
			exec('nohup ./play.sh sounds/'. $alarm['sound'] . '.mp3 '.intval($alarm['volume']).' &> /dev/null & ');

			break;
		}
  addToLog("unlock file");
		flock($fp, LOCK_UN);
	}
	fclose($fp);
}

safe_file_rewrite('get_database_hash.txt', md5_file(constant('DB_FILE_NAME')));

addToLog('Finish');
