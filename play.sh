#!/bin/sh

while [ 1 ] ;
do
  mplayer "$1" > /tmp/output 2>%1
done
