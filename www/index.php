<?php
    //session_start();


    require_once('conf.php');
    require_once('utils.php');

    Utils::myRegisterGlobals();
    Utils::setDefaultValues();

    //drop cookies if requested...
    //before any output begins
    //this will also override any globals from above
    if ($conf['prefs']['cookieenable']) {
        if (isset($_REQUEST['saveCookie'])) {
            //serialize the current settings and drop as a cookie
            $value = Utils::makePrefsCookieValue();
            //echo "cookie = $value";
            //drop the cookie for a year..
            setCookie($conf['prefs']['cookiename'], $value, time() + $conf['prefs']['duration']);

        } else if(!isset($realform)) {
            //this is an initial request (not a submit)
            //read cookies if there are any
            if (isset($_COOKIE[$conf['prefs']['cookiename']])) {
                $cookieString = $_COOKIE[$conf['prefs']['cookiename']];
                //echo "parsing cookie: $cookieString";

                //this will set variables if it can
                Utils::parsePrefsCookieValue($cookieString);
            }
        }
    }

    require_once('Driver.php');
    
    //Setup correct driver
    $driver = &DTtemp_Driver::factory($conf['driver'], $conf['sql']);
    $driver->connect();  //must call to connect


    //check if sensor list is set to all
    if (isset($_REQUEST['sensor_all'])) {
        $sensor = null;
    }


    $list = $driver->listSensors();
    
//    print_r($stats);
    $startOfData =  $driver->getStartOfData();

    if ($conf['alarms']['display']) {
        $alarms = $driver->getActiveAlarms();
    }


    //$times = Utils::getDisplayDataTimesAbs($startTime,$endTime);
    $times = Utils::getDisplayDataTimesAutomatically();
    $startTime = $times['startTime'];
    $endTime = $times['endTime'];


    if(isset($show_stats)) {
        $stats = $driver->getStats($sensor, $times['startTime'], $times['endTime'], $units);
        $stats_sorted = array();
    }


    $driver->close();
    
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
    <HEAD>
        <meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
        <TITLE>DTtemp</TITLE>
        <link rel="shortcut icon" href="images/icon-dtgraph.png" />
    </HEAD>
    <BODY bgcolor="<?php echo $conf['html']['bgcolor'] ?>">
    

    <FORM action="." method="<?php echo $conf['html']['formMethod']; ?>">
    <INPUT type="hidden" name="realform" value="1">
    <TABLE border="3">
    <TR>
            <TD>
                <INPUT type="submit" value="Show">
            </TD>
            <TD>
                <?php 
                    require "dateSelector.php";
                ?>
            </TD>
            <TD>
                <INPUT type="submit" value="Show">
            </TD>
        </TR>
        <TR> 
            <TD colspan="2">
                <?php
                    $imgurl = "graph.php?";
                    $imgurl .= "realform=true&startTime=$startTime";
                    if (isset($endTime)) {
                        $limitedEndTime = $endTime;
                        if ($endTime > time()) {
                            echo "Resetting end Date";
                            //if I don't do this,
                            //it restricts to nonexitstent (in this range)
                            //hours
                            $limitedEndTime = time();
                        }
                        $imgurl .= isset($endTime) ? "&amp;endTime=$limitedEndTime" : "";
                    }
                    $imgurl .= isset($showRepeats) ? "&amp;showRepeats=$showRepeats" : "";
                    $imgurl .= isset($showLegend) ? "&amp;showLegend=$showLegend" : "";
                    $imgurl .= isset($showMargin) ? "&amp;showMargin=$showMargin" : "";
                    $imgurl .= isset($showNegatives) ? "&amp;showNegatives=$showNegatives" : "";
                    $imgurl .= isset($showBands) ? "&amp;showBands=$showBands" : "";
                    $imgurl .= isset($showMarks) ? "&amp;showMarks=$showMarks" : "";
                    $imgurl .= isset($showAll) ? "&amp;showAll=$showAll" : "";
                    $imgurl .= isset($datesAbsolute) ? "&amp;datesAbsolute=$datesAbsolute" : "";
                    $imgurl .= isset($showAlarms) ? "&amp;showAlarms=$showAlarms" : "";
                    $imgurl .= isset($toggleUnits) ? "&amp;toggleUnits=$toggleUnits" : "";
                    if(isset($sensor) && $sensor!="all") {
                        $imgurl .= "&amp;sensor[]=";
                        $imgurl .= join("&amp;sensor[]=",$sensor);
                    }
                ?>
                <input type="image" src="<?php echo $imgurl; ?>" alt="Main Graph of Temperatures. Clicking it will refresh.  Note that selecting too much data may cause the server to time out without producing an image - do not use the All Data checkbox carelessly.">

            </TD>

            <!-- add other options here -->

            <TD valign="top"> <!-- selection - it's own table-->
            <TABLE border="1" cellpadding="0" cellspacing="0">
                <TR>
                <TH colspan="2">Sensor</TH>
                <TH>&nbsp;</TH>
                </TR>
                <TR>
                <?php
                    
                    echo UTILS::makeDoubleTDCheckBox("sensor_all", "all", false, "All",
                        "Select All and selection will clear on next redraw", "red");
                ?>
                    <TD colspan=1></TD>
                </TR>
                <?php 
                //print_r($list);
                    while (list($serial, $s) = each($list)) {
                        if (isset($show_stats) && isset($stats[$serial])) {
                            //cheating here - resorting 
                            //the Stats array on the fly
                            //Note that I'm assuming here
                            //that the list will always have all the items
                            //in the stats array... which seems to make sense
                            $stats_sorted[$serial] = $stats[$serial];
                        }
                        $description = $s['description']." [".$s['serialnumber']."]";
                        echo '<TR>';
                        echo UTILS::makeDoubleTDCheckBox("sensor[]", 
                                        $serial, 
                                        (is_array($sensor) && in_array($serial, $sensor)), 
                                        $s['name'],
                                        $description . ($s['active'] ? '' : '(Inactive)'),
                                        $s['color'],
                                        null,
                                        !$s['active']);
                        //alarm?
                        if (isset($alarms) && isset($alarms[$serial])) {
                            while (list($id, $alarm) = each($alarms[$serial])) {
                                $text .= $s['name'].':'.$alarm['description'].'. Active since '.date($conf['alarms']['dateformat'], $alarm['time_raised']);
                                //append each alarm
                            
                            }
                            echo UTILS::makeTD($content, $text, 'red', 'icon-warning.gif');

                        } else {
                            echo '<TD></TD>';
                        }
                        echo '</TR>';
                    }
                ?>
                </TABLE>
                <P>
                <!-- options table -->
                <TABLE border="1" cellpadding="0" cellspacing="0">
                    <TR>
                        <TH colspan="2">Options</TH>
                    </TR>
                    <?php
                        echo UTILS::makeTRCheckBox("show_stats",
                                            1,
                                            isset($show_stats),
                                            "Stats",
                                            "Show detailed Statistics for selected interval (below)");
                        
                        echo UTILS::makeTRCheckBox("showLegend",
                                            1,
                                            isset($showLegend),
                                            "Legend",
                                            "Show Legend box on the graph");
                        
                        echo UTILS::makeTRCheckBox("showMargin",
                                            1,
                                            isset($showMargin),
                                            "Margin",
                                            "Expand right margin for legend box");
                        
                        echo UTILS::makeTRCheckBox("showMarks",
                                            1,
                                            isset($showMarks),
                                            "Plot Marks",
                                            "Show Marks for each measurement on the graphs");
                        
                        echo UTILS::makeTRCheckBox("showBands",
                                            1,
                                            isset($showBands),
                                            "Range",
                                            "Highlight a tolerance range for each sensor for which min/max are set");
                        
                        echo UTILS::makeTRCheckBox("showNegatives",
                                            1,
                                            isset($showNegatives),
                                            "Negatives",
                                            "Allow negatives by using endTime ZERO instead of startTime");
                        
                        echo UTILS::makeTRCheckBox("toggleUnits",
                                            1,
                                            isset($toggleUnits),
                                            ucfirst(UTILS::getOtherUnit($conf['data']['units'])),
                                            'Show '.  UTILS::getOtherUnit($conf['data']['units']) . ' units for all temperatures');
                                            
                        echo UTILS::makeTRCheckBox("datesAbsolute",
                                            1,
                                            isset($datesAbsolute),
                                            'Absolute Dates',
                                            'Select date range using absolute (and fixed) terms instead of relative');
                        
                        echo UTILS::makeTRCheckBox("showRepeats",
                                            1,
                                            isset($showRepeats),
                                            "Repeats",
                                            "Show unchanged consecutive measurements on the graph",
                                            null,
                                            "Note: showing repeats is only noticeable when Plot Marks are on. Depending on readings, it may slow rendering down");
                        
                        echo UTILS::makeTRCheckBox("showAll",
                                            1,
                                            isset($showAll),
                                            "All Data",
                                            "Show All available measurements without trimming (may be extremely slow). Show Repeats still applies",
                                            null,
                                            "Warning: selecting too much data will not be guarded against! It may take too long to generate image.  Use with caution");
                        echo UTILS::makeTRCheckBox("showAlarms",
                                            1,
                                            isset($showAlarms),
                                            "Show Alarms",
                                            "Show Alarms on the graph");

                        echo UTILS::makeTRCheckBox("saveCookie",
                                            1,
                                            false,
                                            "Save Settings",
                                            "Save current selections as default on next submit (Selecting this causes a cookie to be stored in your current browser after you submit)");
                    ?>
                    <TR><TD colspan="2"><A href="alarmviewer.php">Alarm History</A></TD></TR>
                </TABLE>
            </TD>
        </TR>
    </TABLE>
    </FORM>

    <!-- show alarm notices? -->

    <?php
        if (isset($alarms) ) {
            reset($alarms);
            if (count($alarms) > 0) {
                require "alarmtable.php";
            } //else no alarms
        }

    ?>
    
    <!-- show stats table? -->
    <?php
        if (isset($show_stats)) {
            ?>
                <TABLE border="1">
                    <?php
                        //drawing horizontally so need two nested loops
                        //$keylist = array_pop($stats); //this was one way
                        //Junk is needed cause i'm reading ahead
                        $keylist= array('Current' => 'Latest' , 'min' => 'Min', 'max' => 'Max', 'avg' => 'Average' ,'junk' => 'You should never see this');
                        reset($stats_sorted);
                        unset($key);
                        while (list($keynext, $keynext_desc) = each($keylist)) {
                            echo "<TR>";
                            //echo " Running with $keynext, $valtrash";
                            if (!isset($key)) {
                                echo "<TH>Sensor</TH>";
                            } else {
                                echo "<TH>$key_desc</TH>";
                            }
                            reset($stats_sorted);
                            while(list($ser,$st) = each($stats_sorted)) {
                                if (!isset($key)) {
                                    //draw header
                                    echo "<TH>";
                                    echo Utils::color($list[$ser]['name'], $list[$ser]['color']);
                                    echo "</TH>\n";
                                } else {
                                    echo '<TD>';
                                    echo Utils::myRound($st[$key], $conf['data']['displayPrecision']);
                                    echo "</TD>\n";
                                    // draw that value
                                }

                            }
                            $key = $keynext; ///next time it will be drawn :)
                            $key_desc = $keynext_desc; ///next time it will be drawn :)
                            echo "</TR>";
                        }

                    ?>
                </TABLE>
            <?php
        }

    ?>
    <small>dtgraph v<?=$conf['version']?></small>

    </BODY>

</HTML>

<?php
  $driver->close();  
?>
