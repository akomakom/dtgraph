<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
    <HEAD>
        <meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
        <TITLE>Mobile Temp @ <?php echo date("H:i", time()); ?></TITLE>
    </HEAD>
    <BODY>
<?php
    require_once('conf.php');
    require_once('Driver.php');
    require_once('utils.php');

    Utils::myRegisterGlobals(array('hours', 'showStats', 'toggleUnits'));

    //Setup correct driver
    $driver = &DTtemp_Driver::factory($conf['driver'], $conf['sql']);
    $driver->connect();  //must call to connect

    ////////////////////// Initializied //////////////////

    if (!isset($hours)) {
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
    if ($conf['alarms']['display']) {
        $alarms = $driver->getActiveAlarms();
        //print_r($alarms); echo "<HR>\n";

        if (isset($alarms)) {
            while (list($serial, $theseAlarms) = each($alarms)) {
                //echo "Processing serial: $serial <br>\n";
                while (list($id, $alarm) = each($theseAlarms)) {
                    echo $list[$serial]['name'].':'.$alarm['description'];
                    echo "<BR>\n";
                }
            }
        }
    }



    $units = $conf['data']['units'];

    $stats = $driver->getStats(null, time() - $duration, time(), empty($toggleUnits) ? $units : UTILS::getOtherUnit($units) );
    $precision = $conf['data']['displayPrecision'];
    //print out stats
    while (list($serial, $s) = each($list)) {
        echo $s['name'];
        echo ':';
        if (isset($showStats)) {
            echo Utils::myRound($stats[$serial]['min'], $precision);
            echo '-';
            echo Utils::myRound($stats[$serial]['max'], $precision);
            echo '~' ;
            echo Utils::myRound($stats[$serial]['avg'], $precision);
        } else {
            echo Utils::myRound($stats[$serial]['Current'], $precision);
        }
        echo '<BR>';
    }

    if (isset($showStats)) {
        echo "<A HREF=\"".$_SERVER['PHP_SELF']."\">Normal</A>";
    } else {
        echo "<FORM action=\"".$_SERVER['PHP_SELF']."\" method=\"GET\">";
        echo "Hrs<INPUT type=\"text\" name=\"hours\" size=\"3\" value=\"$hours\">";
        echo "<INPUT type=\"hidden\" name=\"showStats\" value=\"1\">";
        echo "<INPUT type=\"hidden\" name=\"toggleUnits\" value=\"$toggleUnits\">";
        echo "<INPUT type=\"submit\" value=\"Show Stats\">";
        //echo "<A HREF=\"mobile.php?showStats=1\">Statistics</A>";
        echo "</FORM>";
    }
    
        
?>

</BODY>
</HTML>

<?php
    $driver->close();
?>
