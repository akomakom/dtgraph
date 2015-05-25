<?php

/************************************************
* nagios.php
* 
* Allows easy nagios monitoring of your sensors.
* 
* Run with no args (eg php nagios.php) to see options.
* 
* This script will honor what you configure with admin.php 
* Any alarms that are active will be reported, 
* and any sensors marked "active" that have no recent data
* will be reported as well
*
************************************************/

$dtDir = dirname(__FILE__).'/../'; //may have to adjust if you move admin directory contents
require_once($dtDir.'conf.php');
require_once($dtDir.'Driver.php');
require_once($dtDir.'utils.php');

$options = getopt("sam:");
if (count($options) < 1) {
    printUsage(true);
}

$nagios_result = 0;

$driver = &DTtemp_Driver::factory($conf['driver'], $conf['sql']);
$driver->connect();  //must call to connect
$list = $driver->listSensors();
$age = 3600 * 3;


if (isset($options['m'])) {
    $age = $options['m'] * 60;
}

$activeCount = 0;
if (isset($options['s'])) {
    //OK check for sensors with recent data
    $stats = $driver->getStats(null, time() - $age, time(), $units);
    foreach($list as $serial => $s) {
        //only show active sensors
        if ($list[$serial]['active']) {
            if (!isset($stats[$serial]['Current'])) {
                //missing data
                $nagios_result = 2;
                $text .= ' Sensor Data Missing: '.$list[$serial]['name'];
            } else {
                $activeCount++;
            }
        }
    }
}


if (isset($options['a'])) {
    //ok check for alarms (We don't care if $conf['alarms']['display'] is on, that controls if it's shown in the UI
    $alarms = $driver->getActiveAlarms();
    foreach($alarms as $serial => $theseAlarms) {
        foreach($theseAlarms as $id => $alarm) {
            $nagios_result = 2;
            $text .= ' Temperature Alarm: '.$list[$serial]['name'].':'.$alarm['description'];
        }
    }
}

if ($nagios_result == 0) {
    $text .= "$activeCount active sensors OK ";
}


if (isset($text)){
    echo $text."\n";
} 

exit($nagios_result);


function printUsage($isError = false) {
    echo "Usage:\n\t-s (Check that all active sensors have recent data)\n\t-a (Check for alarms)\n\t-m N (Check N minutes back to find valid readings - defaults to 3 hours) \nEG: php /path/to/nagios.php -s -a \n";
    if ($isError) {
        exit(3);
    }
}
