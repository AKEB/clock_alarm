<?php
require_once('config.php');
require_once('functions.php');

$files = glob('sounds/*.mp3');
foreach ($files as $k => $file) {
	$files[$k] = basename($file, '.mp3');
}

echo json_encode($files);
