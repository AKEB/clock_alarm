#!/bin/sh

amixer -q -M set PCM $2% > /dev/null 2>&1

curl -X POST --connect-timeout 5 --max-time 5 "https://smart.akeb.ru/api/webhook/clock_alarm_start"

while [ 1 ] ;
do
    mplayer "$1" > /dev/null 2>&1
done
