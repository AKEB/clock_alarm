#!/bin/sh

amixer -q -M set PCM $2% > /dev/null 2>&1

while [ 1 ] ;
do
    mplayer "$1" > /dev/null 2>&1
done
