<?php
setlocale(LC_ALL, 'ru_RU', 'ru_RU.UTF-8', 'ru', 'russian');
date_default_timezone_set("Europe/Moscow");

function write_database($array) {
	safe_file_rewrite(constant('DB_FILE_NAME'), json_encode($array, JSON_PRETTY_PRINT));
	safe_file_rewrite('get_database_hash.txt', md5_file(constant('DB_FILE_NAME')) . '_' . microtime(true));
}

function read_database() : array {
	$data = [];
	if (file_exists(constant('DB_FILE_NAME'))) {
		$data = @file_get_contents(constant('DB_FILE_NAME'));
		if ($data) {
			$data = @json_decode($data, true, 10, JSON_INVALID_UTF8_IGNORE);
		}
	}
	return $data ?? [];
}

function safe_file_rewrite($fileName, $dataToSave) {
	if ($fp = fopen($fileName, 'w')) {
		$startTime = microtime(TRUE);
		do {
			$canWrite = flock($fp, LOCK_EX);
			// If lock not obtained sleep for 0 - 100 milliseconds, to avoid collision and CPU load
			if(!$canWrite) usleep(round(rand(0, 100)*1000));
		} while ((!$canWrite) && ((microtime(TRUE)-$startTime) < 5));
		//file was locked so now we can store information
		if ($canWrite) {
			fwrite($fp, $dataToSave);
			flock($fp, LOCK_UN);
		}
		fclose($fp);
	}
}

function alarm_get_repeat_text($repeat) : string {
	$weeks = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
	$text = [];
	for($i=0;$i<=6;$i++) {
		if (isset($repeat[$i]) && $repeat[$i]) {
			$text[] = $weeks[$i];
		}
	}
	$text = implode(', ', $text);
	if ($text == 'Пн, Вт, Ср, Чт, Пт') {
		$text = 'Каждый будний день';
	} elseif ($text == 'Сб, Вс') {
		$text = 'Выходные дни';
	}
	return $text;
}

function alarm_get_onetime_text($time) : string {
	$weeks = [1 => 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресение'];
	$months = [1=> 'Января', 'Февраля', 'Марта', 'Апреля', 'Мая', 'Июня', 'Июля', 'Августа', 'Сентября', 'Октября', 'Ноября', 'Декабря'];
	$text = [];
	$text[] = $weeks[date('w', $time)];
	$text[] = date('j', $time);
	$text[] = $months[date('n', $time)];
	$text[] = date('Y', $time);
	return implode(' ', $text);
}

function addToLog($message, $dir=null, $file=null) {
	$current_dir = 'logs/'.date('Y-m-d').'/';
	if ($dir) $current_dir = $dir;
	@mkdir($current_dir,0775,true);
	$k = intval(date('i')/30);
	$currentFile = date('Y_m_d__H_').sprintf("%02d",$k).'.log';
	if ($file) $currentFile = $file;
	$fp = fopen($current_dir.$currentFile, 'a+');
	if (!$fp) {
		error_log("ERROR!!! Ошибка открытия файла %s",$current_dir.$currentFile);
		return false;
	}
	if (flock($fp, LOCK_EX)) {
		$log = [
			date("Y-m-d H:i:s"),
			time(),
			$message,
		];
		fwrite($fp,implode(' || ', $log)."\n");
		flock($fp, LOCK_UN);
	} else {
		error_log("ERROR!!! Ошибка получения блокировки на файл %s",$current_dir.$currentFile);
		fclose($fp);
		return false;
	}
	fclose($fp);
	chmod($current_dir.$currentFile, 0664);
	return true;
}
