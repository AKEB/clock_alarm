#!/bin/bash

pushd /var/www/clock_alarm/
/usr/bin/php ./cron_play.php
popd
