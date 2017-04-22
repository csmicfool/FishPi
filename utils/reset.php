<?php
ini_set('display_errors','1');

if(file_exists('../config.json') == false){
    echo "Error: config.json missing from site root.";
    exit();
}
$config = file_get_contents('../config.json');
$config = json_decode($config,true);

if(file_exists('../state.json') == false){
    $state = array();
    $fp = fopen('../state.json', 'w') or die("Error: Unable to create state file. Check folder and file permissions.");
    fwrite($fp, json_encode($state, JSON_PRETTY_PRINT));
    fclose($fp);
}
$state = file_get_contents('../state.json');
$state = json_decode($state,true);

date_default_timezone_set($config['timezone']);
$time = (int)date('Gi');

foreach($config['tanks'] as $tank){
	foreach($tank['accessories'] as $acc){
        $sname = $tank['name'].".".$acc['name'];
        $sched = $tank['timers'][$acc['schedule']];

        if(!isset($state[$sname])){
            $state[$sname] = array(
                "last_update"=>"0",
                "last_state_change"=>0,
                "last_state_change_value"=>0
            );
        }
        $current_state = $state[$sname]['last_state_change_value'];

        $gpio = $acc['gpio_pin'];
        $setmode = shell_exec("/usr/local/bin/gpio unexport ".$gpio);
    }
}