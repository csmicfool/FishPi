#!/usr/bin/python
# -*- coding: utf-8 -*-
import RPi.GPIO as GPIO
import json
import datetime
import time
import smtplib
import os
import urllib2
from pprint import pprint
import Adafruit_ADS1x15
from dateutil import parser
import sys
import cv2
import subprocess


GPIO.setwarnings(False)

import logging
logging.basicConfig(filename='/var/log/fishpi/schedule.log', level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

#set up GPIO using BCM numbering
GPIO.setmode(GPIO.BCM)
mode = GPIO.getmode()


#Sensor Functions
os.system('modprobe w1-gpio')
os.system('modprobe w1-therm')

def temp_raw(temp_sensor):
    f = open(temp_sensor, 'r')
    lines = f.readlines()
    f.close()
    return lines

def read_temp(temp_sensor):
    lines = temp_raw(temp_sensor)
    while lines[0].strip()[-3:] != 'YES':
        time.sleep(0.2)
        lines = temp_raw()

    temp_output = lines[1].find('t=')

    if temp_output != -1:
        temp_string = lines[1].strip()[temp_output+2:]
        temp_c = float(temp_string) / 1000.0
        temp_f = float(temp_c * 9.0 / 5.0 + 32.0)
        #return temp_c, temp_f
        return temp_f

def get_sunrise_data(lat,lon):
    date = datetime.date.today()
    with open('/var/www/html/state.json') as state_file:
        state = json.load(state_file)

    last_update = state['sunrise_sync']['last_update']
    if((int(time.time())-last_update)>86000):
        url = 'https://api.sunrise-sunset.org/json?lat='+lat+'&lng='+lon+'&date='+str(date)+'&formatted=0'
        srdata = json.load(urllib2.urlopen(url))
        sunrise = srdata['results']['sunrise']
        sunset = srdata['results']['sunset']
        #get sunrise/sunset time and offset for timezone [WIP]
        srp = datetime.datetime.strptime(sunrise,"%Y-%m-%dT%H:%M:%S+00:00") - datetime.timedelta(hours=4)
        ssp = datetime.datetime.strptime(sunset,"%Y-%m-%dT%H:%M:%S+00:00") - datetime.timedelta(hours=4)

        srpf = srp.strftime('%-I:%M %p')
        sspf = ssp.strftime('%-I:%M %p')

        srepoch = int(time.mktime(datetime.datetime.strptime(str(srp),"%Y-%m-%d %H:%M:%S").timetuple()))
        ssepoch = int(time.mktime(datetime.datetime.strptime(str(ssp),"%Y-%m-%d %H:%M:%S").timetuple()))

        state['sunrise_sync']['last_update'] = int(time.time())
        state['sunrise_sync']['last_state_change'] = int(time.time())
        state['sunrise_sync']['last_state_change_value']['sunset_raw'] = ssepoch
        state['sunrise_sync']['last_state_change_value']['sunset'] = sspf
        state['sunrise_sync']['last_state_change_value']['sunrise_raw'] = srepoch
        state['sunrise_sync']['last_state_change_value']['sunrise'] = srpf

        logging.info("{0} |Sunrise Sync| Sunrise: {1} | Sunset: {2}\tUPDATED".format(datetime.datetime.now(),srpf,sspf))

        with open('/var/www/html/state.json', 'w') as statefile:
            json.dump(state, statefile)

    return state['sunrise_sync']['last_state_change_value']


#General Functions
def internet_on():
    try:
        urllib2.urlopen('http://216.58.192.142', timeout=1)
        return True
    except urllib2.URLError as err: 
        return False

# Captures a single image from the camera and returns it in PIL format
def fs_tank_snapshot(webcam,tankname,tid):
    logging.info("|{0}|\tWebcam image capture starting...".format(tankname))
    # uses Fswebcam to take picture

    try:
        # led = subprocess.call("uvcdynctrl -d video"+str(tid)+" -g 'LED1 Mode'")
        logging.debug("uvcdynctrl -d video"+str(tid)+" -s 'LED1 Mode' 0")
        os.system("uvcdynctrl -d video"+str(tid)+" -s 'LED1 Mode' 0") 
    except OSError:
        logging.warning("|{0}|\tWebcam settings update failed.".format(tankname))

    try:
        logging.debug('fswebcam --font luxisr:36 -q --title "'+tankname+'" -D 1 -d '+webcam+' -r 1280x720 -S 3 --jpeg 80 --save /var/www/html/img/tanks/'+tankname.replace(" ","")+'.jpg')
        os.system('fswebcam --font luxisr:36 -q --title "'+tankname+'" -D 1 -d '+webcam+' -r 1280x720 -S 3 --jpeg 80 --save /var/www/html/img/tanks/'+tankname.replace(" ","")+'.jpg') 
    except OSError:
        logging.warning("|{0}|\tWebcam image capture failed.".format(tankname))
        return False

    logging.info("|{0}|\tWebcam image capture complete.".format(tankname))
    return True

#Startup
logging.info("---Starting FishyPi Scheduler---")
while(internet_on()==False):
    logging.info("----- Waiting 30s for network...")
    time.sleep(10)

## Email Admin to notify of startup in case of Pi Restart
def startup_email(email_to):
    email_from = "fishypi@fishypi.com"
    email_subject = "FishyPi Scheduler Startup"
    email_body = 'FishyPi Python Scheduling Task Startup'
    email_msg= "\r\n".join([
        "From: "+email_from,
        "To: "+email_to,
        "Subject: "+email_subject,
        "",
        email_body
        ])
    email_username = "myemail@gmail.com"
    email_password = "mypassword"

    email = smtplib.SMTP('smtp.gmail.com:587')
    email.ehlo()
    email.starttls()
    email.login(email_username,email_password)
    email.sendmail(email_from,email_to,email_msg)
    email.quit()
    del email
    logging.info("---Admin Notification sent to {0}---".format(email_to))

email_to = ["1234567890@tmomail.net", "admin@mytankisawesome.com"]
for to in email_to:
    startup_email(to)
## End Admin Email

#Main Loop
while True:
    with open('/var/www/html/config.json') as data_file:
        data = json.load(data_file)
    sunrise_data = get_sunrise_data(data['latitude'],data['longitude'])
    now = datetime.datetime.now()
    ticks = int(time.time())
    tid = 0
    for tank in data['tanks']:

        webcam = tank['webcam']
        snap = fs_tank_snapshot(webcam,tank['name'],tid)

        for sensor in tank['sensors']:
            with open('/var/www/html/state.json') as state_file:
                state = json.load(state_file)
            sname = tank['name']+"."+sensor['name']
            try:
                state[sname]
            except KeyError:
                state[sname] = [0]
                state[sname]['last_state_change_value'] = 0
                state[sname]['last_state_change'] = 0
                state[sname]['last_update'] = 0
 
            if(sensor['type'] == 'therm'):
                # Get Temp value
                # Get temp using 1-wire sensor
                temp_sensor = '/sys/bus/w1/devices/'+sensor['address']+'/w1_slave'
                temp = read_temp(temp_sensor)
                logging.info("|{3}|\t{2}: {0:0.1f}°F".format(temp,datetime.datetime.now(),sensor['name'],tank['name']))
                
                state[sname]['last_state_change_value'] = temp

            if(sensor['type'] == 'pressure'):
                #Under Pressure
                adc = Adafruit_ADS1x15.ADS1115(address=eval(sensor['address']), busnum=1)

                # Gain = 2/3 for reading voltages from 0 to 6.144V.
                # See table 3 in ADS1115 datasheet
                GAIN = str(sensor['gain'])

                value = [0]
                # Read ADC channel 0
                value[0] = adc.read_adc(sensor['channel'], gain=eval(GAIN))
                # Ratio of 15 bit value to max volts determines volts
                volts = value[0] / 32767.0 * 6.144
                # Tests shows linear relationship between psi & voltage:
                #psi = 50.0 * volts - 25.0
                psi = 25 * volts - 11.75
                
                # Bar conversion
                #bar = psi * 0.0689475729
                logging.info("|{3}|\t{2}: {0:0.1f} psi".format(
                    psi,datetime.datetime.now(),sensor['name'],tank['name']))
                state[sname]['last_state_change_value'] = psi

            state[sname]['last_state_change'] = int(time.time())
            state[sname]['last_update'] = int(time.time())
            with open('/var/www/html/state.json', 'w') as statefile:
                json.dump(state, statefile)
            
        for acc in tank['accessories']:
            with open('/var/www/html/state.json') as state_file:
                state = json.load(state_file)
            sname = tank['name']+"."+acc['name']
            GPIO.setmode(GPIO.BCM)
            GPIO.setup(acc['bcm_pin'], GPIO.OUT)
            pin_state = GPIO.input(acc['bcm_pin'])
            acc_default = tank['timers'][acc['schedule']]['default']
            acc_sunrise = str(tank['timers'][acc['schedule']]['sunrise'])
            acc_sunset = str(tank['timers'][acc['schedule']]['sunset'])
            acc_interval = tank['timers'][acc['schedule']]['interval']
            sr_offset = tank['timers'][acc['schedule']]['sunriseOffset']
            ss_offset = tank['timers'][acc['schedule']]['sunsetOffset']
            acc_last = state[sname]['last_state_change']
            msg = ""

            if acc['schedule'] in data['auto_daylight']:
                srupdate = False
                #get sunrise/sunset time and offset for timezone [WIP]
                srp = datetime.datetime.fromtimestamp(int(sunrise_data['sunrise_raw'])+(60*sr_offset)).strftime('%-H%M')
                ssp = datetime.datetime.fromtimestamp(int(sunrise_data['sunset_raw'])+(60*ss_offset)).strftime('%-H%M')


                if acc_sunrise != srp:
                    srupdate = True
                    data['tanks'][tid]['timers'][acc['schedule']]['sunrise'] = int(srp)
                if acc_sunset != ssp:
                    srupdate = True
                    data['tanks'][tid]['timers'][acc['schedule']]['sunset'] = int(ssp)

                if srupdate:
                    with open('/var/www/html/config.json', 'w') as data_file:
                        json.dump(data, data_file, indent=4)

                    logging.info("|{0}|\t{1}\tSunrise UPDATED - {2}|{3} {4}|{5} {6}".format(
                        tank['name'],acc['name'],acc_sunrise,acc_sunset,
                        srp,ssp,tid))

            sl = len(acc_sunrise)-2
            srmins = acc_sunrise[-2:]
            srhrs = acc_sunrise[:sl]
            srtime = datetime.time(int(srhrs),int(srmins))
            ssl = len(acc_sunset)-2
            ssmins = acc_sunset[-2:]
            sshrs = acc_sunset[:ssl]
            sstime = datetime.time(int(sshrs),int(ssmins))


            timenow = datetime.datetime.time(datetime.datetime.now())
            file_state = state[sname]['last_state_change_value']
            acc_toggle = file_state

            if(acc['automatic']==0):
                msg += "MANUAL_MODE "

            if(timenow>=srtime and timenow<=sstime):
                if(acc_interval>0 and now>=datetime.datetime.fromtimestamp(acc_last+(acc_interval*60))):
                    acc_toggle = abs(int(state[sname]['last_state_change_value'])-1) 
                if(acc_interval==0):     
                    acc_toggle = abs(acc_default-1)                 

            else:
                msg += "NIGHT_MODE "
                acc_toggle = acc_default

            if((acc_toggle!=pin_state or acc_toggle!=file_state) and acc['automatic']):
                GPIO.output(acc['bcm_pin'],acc_toggle)
                msg+= "UPDATED "
                state[sname]['last_state_change_value'] = acc_toggle
                state[sname]['last_state_change'] = int(time.time())
                state[sname]['last_update'] = int(time.time())
                with open('/var/www/html/state.json', 'w') as statefile:
                    json.dump(state, statefile)
            logging.info("|{1}|\t{2} \t{7}|{8} Read|File|Sched:{3}|{4}|{5} {6}"
                .format(datetime.datetime.now(),tank['name'],acc['name']
                    ,pin_state,file_state,acc_toggle,msg,acc_sunrise,acc_sunset))    
            time.sleep(.1)
        time.sleep(1)
        tid += 1
    logging.info("Sleeping for 10 seconds...")
    time.sleep(10)
