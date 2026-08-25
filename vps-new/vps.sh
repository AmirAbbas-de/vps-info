#!/bin/bash

cd /var/www/html/PHP || exit
source .venv/bin/activate
echo "=== Run started at $(date '+%Y-%m-%d %H:%M:%S') ===" >> vps.get.log
python3 vps.py >> vps.get.log 2>&1
echo "=== Run finished at $(date '+%Y-%m-%d %H:%M:%S') ===" >> vps.get.log
echo "" >> vps.get.log
