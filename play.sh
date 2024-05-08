#!/bin/sh

amixer sset PCM $2%

while [ 1 ] ;
do
  mplayer "$1" &> /dev/null &
done
