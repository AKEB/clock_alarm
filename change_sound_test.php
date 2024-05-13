<?php
require_once('lib/common.php');

$session = Session::check();

if ($session) {
	$volume = intval($_POST['volume']);
	exec('nohup ./sound_check.sh sound_check.mp3 '.$volume.' &> /dev/null & ');
}
