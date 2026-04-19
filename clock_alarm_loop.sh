#!/bin/bash

set -u

APP_DIR="/var/www/clock_alarm"
INTERVAL_SECONDS="${CLOCK_ALARM_INTERVAL_SECONDS:-10}"

cd "$APP_DIR" || exit 1

while true; do
	/usr/bin/php ./cron_play.php >/dev/null 2>&1 || true
	sleep "$INTERVAL_SECONDS"
done
