<?php
ini_set('display_errors','1');
print "SCHEDULE STARTING @ ".date('c')."\n";

if(file_exists('/var/www/html/config.json') == false){
    echo "Error: config.json missing from site root.";
    exit();
}
$config = file_get_contents('/var/www/html/config.json');
$config = json_decode($config,true);

if(file_exists('/var/www/html/state.json') == false){
    $state = array();
    $fp = fopen('/var/www/html/state.json', 'w') or die("Error: Unable to create state file. Check folder and file permissions.");
    fwrite($fp, json_encode($state, JSON_PRETTY_PRINT));
    fclose($fp);
}
$state = file_get_contents('/var/www/html/state.json');
$state = json_decode($state,true);

date_default_timezone_set($config['timezone']);
$time = (int)date('Gi');
$date = date('Y-m-d');


if(isset($state['sunrise_sync']) == false){
    $state['sunrise_sync']=array();
    $state['sunrise_sync']['last_state_change']=0;
}
$sunrise_updated = false;

if(time()>$state['sunrise_sync']['last_state_change']+(60*60*6)){
    $url = 'https://api.sunrise-sunset.org/json?lat='.$config['latitude'].'&lng='.$config['longitude'].'&date='.$date.'&formatted=0';  
    if($sunrise_data = file_get_contents($url)){
        $sunrise_data = json_decode($sunrise_data,TRUE);
        $sunrise = date('Gi',strtotime($sunrise_data['results']['sunrise'])); //Converts UTC to local time
        $sunset = date('Gi',strtotime($sunrise_data['results']['sunset'])); 
        $sunrise_raw = strtotime($sunrise_data['results']['sunrise']);
        $sunset_raw = strtotime($sunrise_data['results']['sunset']);

        $state['sunrise_sync'] = array(
            "last_update"=>time(),
            "last_state_change"=>time(),
            "last_state_change_value"=>array(
                "sunrise"=>date('g:i A',strtotime($sunrise_data['results']['sunrise'])),
                "sunset"=>date('g:i A',strtotime($sunrise_data['results']['sunset'])),
                "sunrise_raw"=>$sunrise_raw,
                "sunset_raw"=>$sunset_raw
            )
        );
    }

    echo "Sunrise data updated from internet source.\n";
    $sunrise_updated = true;
}

$sunrise_raw = $state['sunrise_sync']['last_state_change_value']['sunrise_raw'];
$sunset_raw = $state['sunrise_sync']['last_state_change_value']['sunset_raw'];

//set plan(s) for automatic daylight sync in config.json
$daylight_schedules = $config['auto_daylight'];

foreach($config['tanks'] as $tankid => $tank){
	foreach($tank['accessories'] as $acc){
        if($acc['automatic']){
            $sname = $tank['name'].".".$acc['name'];
            $sched = $tank['timers'][$acc['schedule']];
            $default = $sched['default'];
            $enabled = abs($sched['default']-1);

            if(in_array($acc['schedule'], $daylight_schedules) && isset($state['sunrise_sync'])){
                $sched_sunrise = (int)date('Gi',$sunrise_raw+(int)($sched['sunriseOffset']*60));
                $sched_sunset = (int)date('Gi',$sunset_raw+(int)($sched['sunsetOffset']*60));
                if($config['tanks'][$tankid]['timers'][$acc['schedule']]['sunrise'] != $sched_sunrise){
                    $config['tanks'][$tankid]['timers'][$acc['schedule']]['sunrise'] = $sched_sunrise;
                    echo "Sunrise for ".$tank.".".$schedule." updated to ".$sched_sunrise."\n";
                    $sunrise_updated=true;
                }
                if($config['tanks'][$tankid]['timers'][$acc['schedule']]['sunset'] != $sched_sunset){
                    $config['tanks'][$tankid]['timers'][$acc['schedule']]['sunset'] = $sched_sunset;
                    echo "Sunset for ".$tank.".".$schedule." updated to ".$sched_sunrise."\n";
                    $sunrise_updated=true;
                }
            }

            if(!isset($state[$sname])){
                $state[$sname] = array(
                    "last_update"=>0,
                    "last_state_change"=>0,
                    "last_state_change_value"=>0
                );
            }
            $current_state = $state[$sname]['last_state_change_value'];

            $gpio = $acc['gpio_pin'];

            if(time()>($state[$sname]['last_update'])){
                //more than minute since last update
                echo $sname." GPIO:".$gpio."\n";
                $setmode = shell_exec("gpio mode ".$gpio." out");
                $pin_state = shell_exec("gpio read ".$gpio);
                if(!is_null($pin_state)){
                    $pin_state = rtrim($pin_state);
                    $state[$sname]['last_update'] = time();
                    $test_label = "FAIL";
                    if($pin_state==$current_state){
                        $test_label = "OK";
                    }
                    $ls = (time() - $state[$sname]['last_state_change']);
                    echo $sname." state $test_label ($pin_state,$current_state,$ls)\n";

                    if($pin_state<>$current_state){
                        shell_exec("gpio write ".$gpio." ".$current_state);
                        $state[$sname]['last_state_change'] = time();
                        echo $sname." changed pin ".$gpio." to ".$current_state." for STATE_MISMATCH.\n";
                    }
                    if(($time>=(int)$sched['sunrise'])&&($time<=(int)$sched['sunset'])){
                        $interval = $sched['interval'];
                        if(($interval>0)&&(time()>=($state[$sname]['last_state_change']+($interval*59)))){
                            $new_state = abs($pin_state-1);
                            shell_exec("gpio write ".$gpio." ".$new_state);
                            echo $sname." changed pin ".$gpio." to ".$new_state." for INTERVAL_TOGGLE.\n";
                            $state[$sname]['last_state_change_value'] = $new_state;
                            $state[$sname]['last_state_change'] = time();
                        }
                        else if(($pin_state==$default)||($current_state==$default)){
                            shell_exec("gpio write ".$gpio." ".$enabled);
                            echo $sname." changed pin ".$gpio." to ".$enabled." for SCHEDULE_ON.\n";
                            $state[$sname]['last_state_change_value'] = $enabled;
                            $state[$sname]['last_state_change'] = time();
                        }
                    }
                    else if(($time<(int)$sched['sunrise'])||($time>(int)$sched['sunset'])){
                        if(($pin_state==$enabled)||($current_state==$enabled)){
                            shell_exec("gpio write ".$gpio." ".$default);
                            echo $sname." changed pin ".$gpio." to ".$default." for SCHEDULE_OFF.\n";
                            $state[$sname]['last_state_change'] = time();
                            $state[$sname]['last_state_change_value'] = $default;
                        }
                    }
                    usleep(100000);
                }
            }  
        }
        else if(!$acc['automatic']){
            $current_state = $state[$sname]['last_state_change_value'];
            $pin_state = shell_exec("gpio read ".$gpio);
            if($pin_state<>$current_state){
                shell_exec("gpio write ".$gpio." ".$current_state);
                echo $sname." changed pin ".$gpio." to ".$current_state." for MANUAL_MODE_MISMATCH.\n";
            }
        }
    }
}

print "SCHEDULE COMPLETE @ ".date('c')."\n";
//update config with new sunrise/sunset times
if($sunrise_updated){
    $fp = fopen('/var/www/html/config.json', 'w');
    fwrite($fp, json_encode($config, JSON_PRETTY_PRINT));
    fclose($fp);   
}

$fp = fopen('/var/www/html/state.json', 'w');
fwrite($fp, json_encode($state, JSON_PRETTY_PRINT));
fclose($fp);