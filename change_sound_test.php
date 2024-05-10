<?php
require_once('config.php');
require_once('functions.php');
require_once('sessions.php');

$session = session_check();

if ($session) {
	$volume = intval($_POST['volume']);
	exec('nohup ./sound_check.sh sound_check.mp3 '.$volume.' &> /dev/null & ');
}
