<?php
require_once('config.php');
require_once('functions.php');

$alarms = read_database();

$week = intval(date("w")) - 1;
$month = intval(date("n"));
$date = intval(date("j"));
$hour = intval(date("G"));
$minute = intval(date("i"));


foreach($alarms as $alarm) {
	if (!$alarm['status']) continue;

	if (isset($alarm['repeat'])) {
		if (!$alarm['repeat'][$week]) continue;
	} else {
		if ($alarm['month'] != $month) continue;
		if ($alarm['date'] != $date) continue;
	}

	if ($alarm['hour'] != $hour) continue;
	if ($alarm['minute'] != $minute) continue;

	if (!isset($alarm['sound']) || !$alarm['sound']) $alarm['sound'] = 'example.mp3';
	echo './play.sh sounds/'. $alarm['sound'];
	exec('./play.sh sounds/'. $alarm['sound']);
	break;
}
