#!/bin/sh

pkill play.sh
pkill mplayer

curl -X POST --connect-timeout 5 --max-time 5 "https://smart.akeb.ru/api/webhook/clock_alarm_stop"
