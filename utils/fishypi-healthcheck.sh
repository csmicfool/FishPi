#!/bin/bash
ps=$(ps aux | grep -F schedule.py | grep -Fv grep)
now=$(date)

if [ -z "$ps" ]
then
      printf "%s FishyPi Scheduler is down, restarting..\n" "$now"
      echo "sh /var/www/html/utils/fishypi-launch.sh &" | at now
else
      printf "%s FishyPi Scheduler is up!\n" "$now"
      exit 0
fi

