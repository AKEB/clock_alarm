<?php
require_once('config.php');
require_once('functions.php');

$alarms = read_database();

$week = intval(date("w")) - 1;
$hour = intval(date("G"));
$minute = intval(date("i"));

// addToLog('Start');
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
		write_database($alarms);
	}

	shell_exec('nohup ./play.sh sounds/'. $alarm['sound'] . '.mp3 '.intval($alarm['volume']).' &> /dev/null & ');

	break;
}
// addToLog('Finish');
