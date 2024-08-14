#!/bin/bash

for FILE in $(ls -1 uploads/*/*/*.webp)
do
    if $(ffprobe "$FILE" > /dev/null 2>&1)
        then
            ffmpeg -i "$FILE" -y "${FILE%.*}.jpg" -nostats -loglevel 0
            rm -f "$FILE"
        else
            echo "$FILE"
    fi
done
