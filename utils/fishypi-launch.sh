#!/bin/sh
# launcher.sh

cd /var/www/html/utils
python schedule.py >> /var/log/fishpi/schedule.log

