<?php
require_once('lib/common.php');

$session = Session::check();

if (!$session) {
	echo "{}";
} else {
	echo \ClockAlarm::getAlarmsJson();
}
