<?php

    require_once('conf.php');
    require_once('Driver.php');
    require_once('utils.php');

    Utils::myRegisterGlobals(array('time','sensor','interval', 'debug'));

    //Setup correct driver
    $driver = &DTtemp_Driver::factory($conf['driver'], $conf['sql']);
    $driver->connect();  //must call to connect

    ////////////////////// Initializied //////////////////

    //Interval is the span we check for the reading
    if (empty($interval)) {
        $interval = 1800; //half an hour
    }

    global $conf;
    //echo "getting readings for $sensor for time $time";

    if (!is_array($sensor)) {
        $sensor = array($sensor);
    }

    if (isset($debug)) {
        echo "Sensors requested: ";
        print_r($sensor);
        printf("\nRequested Date is %s with interval %s", date('r', $time), $interval);
    }

    $stats = $driver->getStats($sensor, $time - $interval, $time);
    //print_r($stats[$sensor]['Current']);
    $precision = $conf['data']['displayPrecision'];
    foreach($sensor as $s) {
        echo Utils::myRound($stats[$s]['Current'], $precision);
        echo " ";
    }
    //echo Utils::myRound($stats[$sensor]['Current'], $precision);

?>
