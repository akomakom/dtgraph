<?php
//  ShowLatest simply shows the latest readings,
//   and is intended for batch files using php-cli
//   It is an exact replica of mobile.php with different formatting
//   (no HTML)
//
//  Optional parameter sensor: name of requested sensor
//    If such a sensor exists, then just the latest temperature is displayed, eg: 
//          showlatest.php?sensor=Aquarium
//
//
//  Example NAGIOS command definition:
//    define command {
//      command_name    check_dtgraph_alarms
//      command_line    curl -s http://$HOSTADDRESS$/dtgraph/showlatest.php 2>/dev/null | grep ALARM && exit 2
//    }

    require_once('conf.php');
    require_once('Driver.php');
    require_once('utils.php');

    Utils::myRegisterGlobals(array('hours','sensor'));

    //Setup correct driver
    $driver = &DTtemp_Driver::factory($conf['driver'], $conf['sql']);
    $driver->connect();  //must call to connect

    ////////////////////// Initializied //////////////////

    if (empty($hours)) {
        $hours = 1; //default - one hour
    }
    $duration = 3600 * $hours; 
    if ($duration > time()) {
        $duration = time(); //safety to avoid negative startTimes
    }
    
    $list = $driver->listSensors();
    //$stats = $driver->getStats($sensor, $times['startTime'], $times['endTime']);
    global $conf;


    //read alarms, if any
    if ($conf['alarms']['display'] && empty($sensor)) {
        $alarms = $driver->getActiveAlarms();
        //print_r($alarms); echo "<HR>\n";

        if (isset($alarms)) {
            while (list($serial, $theseAlarms) = each($alarms)) {
                //echo "Processing serial: $serial <br>\n";
                while (list($id, $alarm) = each($theseAlarms)) {
                    echo 'ALARM;'. $list[$serial]['name'].':'.$alarm['description'];
                    echo "\n";
                }
            }
        }
    }




    $stats = $driver->getStats(null, time() - $duration );
    $precision = $conf['data']['displayPrecision'];
    //print out stats
    //
    while (list($serial, $s) = each($list)) {
        //print_r($stats[$serial]);
        if (!empty($sensor)) {
            if ($s['name'] == $sensor) {
                echo Utils::myRound($stats[$serial]['Current'], $precision);
            }
        } else {

            echo $s['name'];
            echo ':';
            echo Utils::myRound($stats[$serial]['Current'], $precision);
            echo "\n";
        }
    }

?>

