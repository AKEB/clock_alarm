<?php
setlocale(LC_ALL, 'ru_RU', 'ru_RU.UTF-8', 'ru', 'russian');
date_default_timezone_set("Europe/Moscow");




function write_database($array) {
	safe_file_rewrite(constant('DB_FILE_NAME'), json_encode($array, JSON_PRETTY_PRINT));
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
