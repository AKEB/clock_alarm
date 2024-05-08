<?php
require_once('config.php');

echo md5_file(constant('DB_FILE_NAME'));
