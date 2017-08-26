<?php
ini_set('display_errors','1');

if(file_exists('/var/www/html/config.json') == false){
    header("HTTP/1.1 500 Internal Server Error");
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

$sunrise_sync = false;
if(isset($state['sunrise_sync'])){
    $astro = $state['sunrise_sync'];
    $sunrise = $astro['last_state_change_value']['sunrise'];
    $sunset = $astro['last_state_change_value']['sunset'];
    $sunrise_raw = $astro['last_state_change_value']['sunrise_raw'];
    $sunset_raw = $astro['last_state_change_value']['sunset_raw'];
    $sunrise_sync = true;
}

date_default_timezone_set($config['timezone']);
$time = (int)date('Gi');
$date = date('Y-m-d');

$chronts = filemtime('/var/log/fishpi/schedule.log');
$chronrunning = true;
if((time()-$chronts)>120){
    $chronrunning = false;
}

?><!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="refresh" content="30"/>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>FishPi</title>

    <!-- Bootstrap Core CSS -->
    <link href="../vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- MetisMenu CSS -->
    <link href="../vendor/metisMenu/metisMenu.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="../dist/css/sb-admin-2.css" rel="stylesheet">
    <link href="../dist/css/custom.css" rel="stylesheet">

    <!-- Custom Fonts -->
    <!-- <link href="../vendor/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css"> -->

    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
        <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->

    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="57x57" href="img/apple-icon-57x57.png">
	<link rel="apple-touch-icon" sizes="60x60" href="img/apple-icon-60x60.png">
	<link rel="apple-touch-icon" sizes="72x72" href="img/apple-icon-72x72.png">
	<link rel="apple-touch-icon" sizes="76x76" href="img/apple-icon-76x76.png">
	<link rel="apple-touch-icon" sizes="114x114" href="img/apple-icon-114x114.png">
	<link rel="apple-touch-icon" sizes="120x120" href="img/apple-icon-120x120.png">
	<link rel="apple-touch-icon" sizes="144x144" href="img/apple-icon-144x144.png">
	<link rel="apple-touch-icon" sizes="152x152" href="img/apple-icon-152x152.png">
	<link rel="apple-touch-icon" sizes="180x180" href="img/apple-icon-180x180.png">
	<link rel="icon" type="image/png" sizes="192x192"  href="img/android-icon-192x192.png">
	<link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="96x96" href="img/favicon-96x96.png">
	<link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
	<link rel="manifest" href="/manifest.json">
	<meta name="msapplication-TileColor" content="#ffffff">
	<meta name="msapplication-TileImage" content="img/ms-icon-144x144.png">
	<meta name="theme-color" content="#ffffff">

</head>

<body>
    
        <div id="page-wrapper">
        <?php
        foreach($config['tanks'] as $tankid => $tank){
        ?>
            <div class="row">
                <div class="col-lg-12">
                    <h2><?php echo $tank['name']; ?></h2>
                    <h5>
                        <?php echo $tank['size']." ".$tank['units']; ?>
                        <?php
                            foreach($tank['sensors'] as $sensor){
                                $sname = $tank['name'].".".$sensor['name'];
                                if($sensor['type']=='therm'){
                                    $class = "fa fa-thermometer";
                                }
                                if($sensor['type']=='pressure'){
                                    $class = "fa fa-tachometer";
                                }
                                echo " | ".$sensor['name'].": <i class='".$class."' aria-hidden='true'></i> ".round($state[$sname]['last_state_change_value'],1).$sensor['unit'];
                            }
                        ?>
                    </h5>
                </div>  
                <!-- /.col-lg-12 -->
            </div>
            <!-- /.row -->
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <img class="img-responsive" src="/img/tanks/<?php echo str_replace(" ","",$tank['name']); ?>.jpg" alt="<?php echo $tank['name']; ?>">    
                    <br/>
                </div>
            <?php
                foreach($tank['accessories'] as $accid => $acc){
                    $sname = $tank['name'].".".$acc['name'];
                    $sched = $tank['timers'][$acc['schedule']];
                    $default = $sched['default'];
                    $gpio = $acc['gpio_pin'];
                    $auto = $acc['automatic'];
                    $auto_toggle = "fa-toggle-on";
                    $auto_toggle_state = "false";
                    if($auto){
                        $auto_toggle = "fa-toggle-off";
                        $auto_toggle_state = "true";
                    }

                    $sstate = "Off";
                    $state_class = "panel-danger";
                    $state_toggle = "fa-toggle-off";

                    if(!isset($state[$sname])){
                        $state[$sname] = array(
                            "last_update"=>"0",
                            "last_state_change"=>0,
                            "last_state_change_value"=>1
                        );
                    }
                    $current_state = $state[$sname]['last_state_change_value'];
                    $acc_toggle_state = "false";


                    if($state[$sname]['last_state_change_value']){
                        $sstate = "On";
                        $state_class = "panel-success";
                        $state_toggle = "fa-toggle-on";
                        $acc_toggle_state = "true";

                    }
                    $last_state_change = $state[$sname]['last_state_change'];

                    $srlen=(strlen($sched['sunrise'])-2); 
                    $ss=date("Y-m-d").' '.substr($sched['sunrise'],0,$srlen).':'.substr($sched['sunrise'],-2);
                    $sunrisef=date("g:i a",strtotime($ss));
                    $sslen=(strlen($sched['sunset'])-2); 
                    $sr=date("Y-m-d").' '.substr($sched['sunset'],0,$sslen).':'.substr($sched['sunset'],-2);
                    $sunsetf=date("g:i a",strtotime($sr));
                ?>
                <div class="col-lg-3 col-md-6">
                    <div class="panel <?php echo $state_class; ?>" id="acc-panel-<?php echo $gpio ?>">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-xs-3">
                                    <i class="fa <?php echo $state_toggle; ?> fa-5x"></i>
                                    <p id="acc-warn-<?php echo $gpio ?>" class="text-center bg-warning" <?php if($auto){?>style="display:none"<?php } ?>>*Manual*</p>
                                </div>
                                <div class="col-xs-9 text-right">
                                    <div class="huge text-nowrap"><?php echo $acc['name']; ?></div>
                                    <div><?php echo $sstate." since ".date("H:i M j, Y",$last_state_change); ?></div>
                                    <div><i class="fa fa-sun-o" aria-hidden="true"></i> <?php echo $sunrisef; ?> | <i class="fa fa-moon-o" aria-hidden="true"></i> <?php echo $sunsetf; ?> | Outlet #<?php echo $acc['outlet']; ?></div>
                                </div>
                            </div>
                        </div>
                        <a href="#control-<?php echo $gpio ?>" data-toggle="collapse">
                            <div class="panel-footer">
                                <span class="pull-left">View Details</span>
                                <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                                <div class="clearfix"></div>
                            </div>
                        </a>
                        <div id="control-<?php echo $gpio ?>" class="collapse panel-body panel-active">
                            <div class="row">
                                <div class="lead col-xs-5 text-center">
                                    <div>
                                        Automatic Mode
                                    </div>
                                </div>
                                <div class="col-xs-2">
                                    <a onClick="toggle_manual(<?php echo $gpio ?>,'<?php echo $sname; ?>',<?php echo $tankid; ?>,<?php echo $accid; ?>);"><i data-togglestate="<?php echo $auto_toggle_state ?>" id="manual-toggle-<?php echo $gpio ?>" class="fa <?php echo $auto_toggle; ?> fa-2x"></i></a>
                                </div>
                                <div class="lead col-xs-5 text-center">
                                    <div>
                                        Manual Control
                                    </div>
                                </div>
                            </div>
                            <div id="manual-options-<?php echo $gpio ?>" class="row">
                                <div class="lead col-xs-5 text-center">
                                    <div>
                                        Off
                                    </div>
                                </div>
                                <div class="col-xs-2">
                                    <a onClick="toggle_state(<?php echo $gpio ?>,'<?php echo $sname; ?>',<?php echo $tankid; ?>,<?php echo $accid; ?>);" class="manual-toggle-status-vis-<?php echo $gpio ?>" <?php if($auto){ ?>style="display:none"<?php } ?>>
                                        <i data-togglestate="<?php echo $acc_toggle_state ?>" id="manual-toggle-status-<?php echo $gpio ?>" class="fa <?php echo $state_toggle; ?> fa-2x manual-toggle-status-icon-<?php echo $gpio ?>"></i>
                                    </a>
                                    <i class="fa <?php echo $state_toggle; ?> fa-2x manual-toggle-status-icon-<?php echo $gpio ?> manual-toggle-status-vis-<?php echo $gpio ?>" <?php if(!$auto){ ?>style="display:none"<?php } ?>></i>
                                </div>
                                <div class="lead col-xs-5 text-center">
                                    <div>
                                        On
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                }
            ?>
            </div>
            <hr>
        <?php
        }
        ?>
        	<div class="row">
        		<?php if($sunrise_sync){ ?>
        		<div class="col-lg-12">
                    <h5 class="pull-left" title="<?php echo 'last updated '.date("H:i M j, Y",$astro['last_state_change']); ?>">
                    	<i class='fa fa-sun-o'></i> <?php echo $sunrise ?> | <i class='fa fa-moon-o'></i> <?php echo $sunset ?>
                    </h5>
                    <h5 class="pull-right">
                    	<i class='fa fa-globe'></i> <?php echo round((($sunset_raw-$sunrise_raw)/(60*60)),2); ?> Hours of Daylight<!--  | SysLoad: <?php $load=sys_getloadavg(); echo $load[0]; ?> -->
                    </h5>
        		</div>
        		<?php } ?>
        	</div>
        </div>
        <!-- /#page-wrapper -->

    </div>
    <!-- /#wrapper -->

    <!-- jQuery -->
    <script src="../vendor/jquery/jquery.min.js"></script>

    <!-- Bootstrap Core JavaScript -->
    <script src="../vendor/bootstrap/js/bootstrap.min.js"></script>

    <!-- Metis Menu Plugin JavaScript -->
    <script src="../vendor/metisMenu/metisMenu.min.js"></script>

    <!-- Custom Theme JavaScript -->
    <script src="../dist/js/sb-admin-2.js"></script>
    <script src="../js/jquery.idle.js"></script>
    <script src="https://use.fontawesome.com/742f4585af.js"></script>
    <script type="text/javascript">
      function toggle_manual(gpio,sname,tank,acc){
        var togstate = $('#manual-toggle-'+gpio).data('togglestate');
        $('#manual-toggle-'+gpio).data('togglestate',!togstate);
        $('#manual-toggle-'+gpio).removeClass('fa-toggle-on fa-toggle-off');
        $('.manual-toggle-status-vis-'+gpio).toggle();
        $('#acc-warn-'+gpio).toggle();
        if(togstate){
            $('#manual-toggle-'+gpio).addClass('fa-toggle-on');
            $.post("../utils/post_handler.php",{"action":"manual_mode","value":1,"gpio":gpio,"state":sname,"tank":tank,"acc":acc})
        }
        else{
            $('#manual-toggle-'+gpio).addClass('fa-toggle-off');
            $.post("../utils/post_handler.php",{"action":"manual_mode","value":0,"gpio":gpio,"state":sname,"tank":tank,"acc":acc})
        }
      }
      function toggle_state(gpio,sname,tank,acc){
        var togstate = $('#manual-toggle-status-'+gpio).data('togglestate');
        $('#manual-toggle-status-'+gpio).data('togglestate',!togstate);
        $('.manual-toggle-status-icon-'+gpio).removeClass('fa-toggle-on fa-toggle-off');
        $('#acc-panel-'+gpio).removeClass('panel-success panel-danger');
        if(!togstate){
            $('.manual-toggle-status-icon-'+gpio).addClass('fa-toggle-on');
            $('#acc-panel-'+gpio).addClass('panel-success');
            $.post("../utils/post_handler.php",{"action":"manual_state","value":1,"gpio":gpio,"state":sname,"tank":tank,"acc":acc})
        }
        else{
            $('.manual-toggle-status-icon-'+gpio).addClass('fa-toggle-off');
            $('#acc-panel-'+gpio).addClass('panel-danger');
            $.post("../utils/post_handler.php",{"action":"manual_state","value":0,"gpio":gpio,"state":sname,"tank":tank,"acc":acc})
        }
      }
      $(document).ready(function(){
        $(document).idle(
            function(){
                location = ''
            },
            { after: 5000 }
        );
      });
      <?php if(!$chronrunning){ ?>
      alert('Chron is not running!');   
      <?php } ?>
    </script>

</body>

</html>