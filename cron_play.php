<?php
require_once('config.php');
require_once('functions.php');

$alarms = read_database();

$week = intval(date("w")) - 1;
$month = intval(date("n"));
$date = intval(date("j"));
$hour = intval(date("G"));
$minute = intval(date("i"));

// addToLog('Start');
// addToLog(var_export($alarms, true));
$save = false;
foreach($alarms as $k=>$alarm) {
	if (!$alarm['status']) continue;
	// addToLog('1 '.var_export($alarm, true));
	if (isset($alarm['repeat'])) {
		if (!$alarm['repeat'][$week]) continue;
	} else {
		if ($alarm['month'] != $month) continue;
		if ($alarm['date'] != $date) continue;
	}
	// addToLog('2 '.var_export($alarm, true));
	if ($alarm['hour'] != $hour) continue;
	if ($alarm['minute'] != $minute) continue;
	// addToLog('3 '.var_export($alarm, true));
	if (!isset($alarm['sound']) || !$alarm['sound']) $alarm['sound'] = 'example.mp3';

	shell_exec('./play.sh sounds/'. $alarm['sound'].' &> /dev/null & ');

	if (!isset($alarm['repeat'])) {
		$alarms[$k]['status'] = false;
		$save = true;
	}

	break;
}

if ($save) {
	write_database($alarms);
}

// addToLog('Finish');
