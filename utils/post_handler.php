<?php
ini_set('display_errors','1');
if(isset($_POST['action'])==FALSE){
    header('HTTP/1.1 400 Bad Request', true, 400);
    exit();
}

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

$updated = false;

//Case: Change Manual <-> Auto Mode
if($_POST['action']=='manual_mode'){
    $sname = $_POST['state'];
    $manual_mode = $_POST['value'];
    $tank = $_POST['tank'];
    $acc = $_POST['acc'];
    $acc_state = $state[$sname];

    if($manual_mode){
        $config['tanks'][$tank]['accessories'][$acc]['automatic'] = 0;
        $updated = 1;
    }
    else if(!$manual_mode){
        $config['tanks'][$tank]['accessories'][$acc]['automatic'] = 1;
        $updated = 1;
    }
}

//Case: Change State On <-> Off
if($_POST['action']=='manual_state'){
    $sname = $_POST['state'];
    $manual_state = $_POST['value'];
    $tank = $_POST['tank'];
    $acc = $_POST['acc'];
    $acc_state = $state[$sname];
    $gpio = $_POST['gpio'];

    $sched = $config['tanks'][$tank]['accessories'][$acc]['schedule'];

    $state[$sname] = array(
        "last_update" => time(),
        "last_state_change"=>time(),
        "last_state_change_value"=>$manual_state
    );
    $setmode = shell_exec("gpio mode ".$gpio." out");
    shell_exec("gpio write ".$gpio." ".$manual_state);

    $updated = 1;
}


//update config with new sunrise/sunset times
if($updated){
    $fp = fopen('/var/www/html/config.json', 'w');
    fwrite($fp, json_encode($config, JSON_PRETTY_PRINT));
    fclose($fp);  

    $fp = fopen('/var/www/html/state.json', 'w');
    fwrite($fp, json_encode($state, JSON_PRETTY_PRINT));
    fclose($fp); 
}