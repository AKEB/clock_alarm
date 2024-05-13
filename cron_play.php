<?php
require_once('lib/common.php');
$startTime = time();

$minuteEndTime = mktime(date('H'), date('i'), 60, date('m'), date('d'), date('Y'));

$alarms = \ClockAlarm::data();

if (!$alarms) {
	addToLog('Ошибка получения списка будильников');
	TelegramBot::sendMessage(constant('BOT_ADMIN_ID'),'[#ClockAlarm Error] Ошибка получения списка будильников');
}

$week = intval(date("w")) - 1;
$hour = intval(date("G"));
$minute = intval(date("i"));

foreach($alarms as $k=>$alarm) {
	if (!$alarm['status']) continue;
	if (isset($alarm['repeat'])) {
		if (!$alarm['repeat'][$week]) continue;
	}
	if ($alarm['hour'] != $hour) continue;
	if ($alarm['minute'] != $minute) continue;

	if ($alarm['play_last_time'] > time()) continue;

	if (!isset($alarm['sound']) || !$alarm['sound']) $alarm['sound'] = 'default';

	if (!file_exists('sounds/'.$alarm['sound'].'.mp3')) {
		$alarm['sound'] = 'default';
	}
	$param = [
		'id' => $alarm['id'],
	];

	if (!isset($alarm['repeat'])) {
		$param['status'] = false;
	}
	$param['update_time'] = time();
	$param['play_last_time'] = $minuteEndTime;
	\ClockAlarm::save($param);
	echo ('PLAY id='.$alarm['id']);
	exec('nohup ./play.sh sounds/'. $alarm['sound'] . '.mp3 '.intval($alarm['volume']).' &> /dev/null & ');
	break;
}

safe_file_rewrite('get_database_hash.txt', md5_file(constant('SQL_DB_FILE_NAME')));
