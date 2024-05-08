#!/bin/sh

while [ 1 ] ;
do
  mplayer "$1" &> /dev/null &
done
