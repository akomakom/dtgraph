<?php
/*
 * This file is part of an open-source project
 * licensed under the GPL
 *
 */

    ob_start();
    //session_start();

    require_once('conf.php');
    require_once('Driver.php');
    require_once('utils.php');

    Utils::myRegisterGlobals();
    Utils::setDefaultValues();

    //JPGraph
    require_once($conf['jp_path']."/jpgraph.php");
    require_once($conf['jp_path']."/jpgraph_line.php");
    require_once($conf['jp_path']."/jpgraph_scatter.php");
    
    //Setup correct driver
    $driver = &DTtemp_Driver::factory($conf['driver'], $conf['sql']);
    $driver->connect();  //must call to connect
    global $colors;
    $colors = $conf['graph']['colors'];

    global $stop_watch;
    $stop_watch['Start'] = microtime();
    
    //echo "Sensor is passed in as $sensor";

    $options = Utils::getGraphOptions();
    $l = $driver->listSensors($sensor, $options['units']);

    $stop_watch['Done list sensors'] = microtime();
    
    $times = Utils::getDisplayDataTimesAbs($startTime, $endTime);

    $stop_watch['Done get Display Data TImes'] = microtime();
    
    if (isset($showRepeats) && $showRepeats == 1) {
        $showRepeats = true;
    } else {
        $showRepeats = false;
    }
    
    $d = $driver->listEvents($sensor, $times['startTime'], $times['endTime'], $showRepeats, !isOptionOn($options,'showAll'), $options['units']); 

    $availableReadingsCount = $driver->countEvents($sensor, $times['startTime'], $times['endTime']);

	if ( $showAlarms ) {
		$alarms = $driver->getAllAlarms($times['startTime'], $times['endTime']); 
	}

    $stop_watch['Done list Events'] = microtime();


    $error = ob_get_clean();

    if (PEAR::isError($l)) { //db error
        makeErrorGraph('Error listing sensors from db'. $error. "\n" .  print_r($l, true));
        return;
    } else if (PEAR::isError($d)) { //db error
        makeErrorGraph('Error reading events from db'. $error . "\n". print_r($d, true));
        return;
    } else if (PEAR::isError($availableReadingsCount)) {
        makeErrorGraph('Error getting event count from db'. "\n" . print_r($availableReadingsCount, true));
        return;
    }
    //echo "events starting at ". $times['startTime']. " ending at ".$times['endTime'];
    //echo "we have the following number of readings: " . count($d);
    //print_r($d);
    graph($d,$l, $alarms, $times, $availableReadingsCount, $options);
    $stop_watch['Done Graph'] = microtime();
    //Utils::echo_stopwatch();

        
            /** Functions **/
            /***************/

    /**
     * @param data - the return of listEvents
     * @param list   - the return of listSensors
     * @param times  - the return of getDisplayDataTimes
     * @param options - array of various graph options/params
     */
    function graph($data, $list, $alarms, $times, $availableReadingsCount, $options = array()) {
        global $colors; //for defaults if not set
        global $conf;
        global $showAlarms;

        $xdata = array();
        $ydata = array();
		$xdataAlarmsRaised = array();
		$xdataAlarmsCleared = array();
		$ydataAlarmsRaised = array();
		$ydataAlarmsCleared = array();
        $names = array();

        $negs =  (isset($options['showNegatives']) && $options['showNegatives'] == 1);

        $varPrefix = $negs ? "this" : "requested";
        $varSuffix = 'Hour';
        $varName = $varPrefix.$varSuffix; //flexible graphing...

        //Time used to generate the explanatory text on X scale

        $startTime = $times['startTime'];
        $endTime = $times['endTime'];

        $explanationTime = $negs ? $endTime : $startTime;

        //$relativeStart = ( ($negs == true) ? $times['thisHour'] : $times['requestedHour']);
        //echo "$relativeStart == ? == ".$times['thisHour'] . " OR " . $times['requestedHour'];

        //echo "using negs =" . $negs ? "true " : " false";

        $conversionFactor = 60; // seconds in an X scale unit
        $XUnits = "Minutes since ".date("g A m/d", $explanationTime);
        if ( ($endTime - $startTime) > 3600 * 24 * 28) { //one month?
            $conversionFactor = 3600 * 24 * 30.5; 
            $XUnits = "Approximate Months starting " .date("Y", $explanationTime);
            $varSuffix = 'Year';
        } else if( ($endTime - $startTime) > 3600 * 24 * 1) { //one day :)
            $conversionFactor = 3600 * 24; //days
            $XUnits = "Calendar Days starting ".date("F", $explanationTime)." 1st";
            $varSuffix = 'Month';
        } else if( ($endTime - $startTime) > 3600 * 2) { //2 hours
            $conversionFactor = 3600;
            $XUnits = "Hours since midnight ".date("m/d", $explanationTime);
            $varSuffix = 'Morning';
        }  
        
        //This start point will be the ZERO
        $relativeStart = $times[$varPrefix.$varSuffix];
/**/
        //setup arrays
        while (list($serial,$s) = each($list)) {
            $xdata[$serial] = array();
            $ydata[$serial] = array();
            $names[$serial] = $s['name'];
        }
/**/



		$alarmInfo = array();
		//First of all, pre-process alarms 
		if ( $showAlarms) {
			while(list($serial, $theseAlarms) = each($alarms)) {
				//there are alarms for this sensor, figure out how to show them
				//this is an array
				$alarmInfo[$serial] = array();
				$alarmInfo[$serial]['raised'] = array();
				$alarmInfo[$serial]['cleared'] = array();
				foreach($alarms[$serial] as $oneAlarm) {
					$timeRaised = $oneAlarm['time_raised'];
					$timeRaisedAdjusted = sprintf("%0.3f", ($timeRaised - $relativeStart)/ $conversionFactor);
					array_push($alarmInfo[$serial]['raised'], $timeRaisedAdjusted);
					
					$timeCleared = $oneAlarm['time_cleared'];
					if (isset($timeCleared)) {
						//echo "Adding time cleared: $timeCleared";
						$timeClearedAdjusted = sprintf("%0.3f", ($timeCleared - $relativeStart)/ $conversionFactor);
						array_push($alarmInfo[$serial]['cleared'], $timeClearedAdjusted);
					}

				}
				sort($alarmInfo[$serial]['raised']); //could be out of order
				sort($alarmInfo[$serial]['cleared']); //could be out of order
			}
		}




        //setup xdata and ydata
        while(list($row, $d) = each ($data)) {
            $serial = $d['serialnumber'];
            //safety, should not be needed
            if (!is_array($xdata[$serial])) {
                $xdata[$serial] = array();
                $ydata[$serial] = array();
                $names[$serial] = $serial; //it must not be described
            }

            
            $time = $d['unixtime'];
            $x = sprintf("%0.3f", ($time - $relativeStart)/ $conversionFactor);
            
            //echo "Based on $startTime - $relativeStart div by $conversionFactor, it's $x <BR>";

            //$temp = $d['fahrenheit'];
            $temp = $d[strtolower($options['units'])];
/**/
/**/
            array_push($xdata[$serial], $x);
            array_push($ydata[$serial], $temp);
            //echo "Adding point $x - $temp";

			//check for alarm matches.
			//the items in each array will be in ascending order,
			//so I can check for them one at a time 
			//(because I am going in ascending order too)
			if ($showAlarms && isset($alarmInfo) && is_array($alarmInfo[$serial])) {
				$alarmArray = &$alarmInfo[$serial]['raised'];

				if (count($alarmArray) > 0 && current($alarmArray) <= $x) {
					//echo "$serial: Comparison was true for ".current($alarmArray). " < $x .  Count=".count($alarmArray)."<br>";
					//print_r($alarmArray);
					//then we're on the right element (or just past it)
					array_push($xdataAlarmsRaised, $x);
					array_push($ydataAlarmsRaised, $temp);
					//get rid of that entry
					array_shift($alarmArray);
					//echo "Count is now ".count($alarmArray).'<br>';
				}
				$alarmArray = &$alarmInfo[$serial]['cleared'];
				if (count($alarmArray) > 0 && current($alarmArray) <= $x) {
					//then we're on the right element (or just past it)
					array_push($xdataAlarmsCleared, $x);
					array_push($ydataAlarmsCleared, $temp);
					//get rid of that entry
					array_shift($alarmArray);
				}
			}
        }

        //$graph = setupGraph($mintime,$maxtime,$mintemp, $maxtemp);
        $count = count($data);
        $graph = setupGraph($XUnits, $startTime, $count, $availableReadingsCount, $options);


        reset($list); //already walked this one
        //make plots based on arrays
        
        $graphHasData = false; //if it doesn't have any data, it will blow up

        
        $plots = array();
        while (list($serial,$s) = each($list)) {
            $color = $list[$serial]['color'];
            if (!isset($color) || $color=='') {
                //Take the default just in case
                $color = array_pop($colors);
            }
            $plots[$serial] = makePlot($xdata[$serial], $ydata[$serial], $names[$serial], $color, $options);
            if (count($xdata[$serial]) > 0 ) {
                $graphHasData = true;
            }

        
            //tossing each into its own var as it seems to be 
            //pass by ref and overwrites each time?
            if (isset($plots[$serial])) {
                /*
                echo "Adding a plot: " ;
                print_r($plot);
                echo "<P><HR>";
                */
                $graph->Add($plots[$serial]);

                $plots[$serial]->SetColor($color);

                if (isset($options['showBands']) && $options['showBands'] == 1) {
                    if (isset($list[$serial]['max'])) {
                        $max = $list[$serial]['max'];
     //                   $graph->Add(new PlotLine(HORIZONTAL,$max, $color, 1)); 
                    }
                    if (isset($list[$serial]['min'])) {
                        $min = $list[$serial]['min'];
    //                    $graph->Add(new PlotLine(HORIZONTAL,$min, $color, 1)); 
                    }

                    /******** ADD BAND ******** ? ***/

                    if (isset($min) && isset($max)) {
                        $uband=new PlotBand(HORIZONTAL,BAND_SOLID,$min,$max,"white", 1, DEPTH_BACK);
                        $uband->ShowFrame(true);
                        $uband->SetDensity(3); // % line density

                        $graph->AddBand($uband);
                        unset($uband);
                    }
                    /******* DONE ADD BAND *******/
                } //if showBands
                
            }
        }

        if (!$graphHasData) {
            setGraphToError($graph, 'There is no data in the selected timeframe. Consider expanding the range');
		} else { //plot the alarms on it
			if ( $showAlarms) {
				if (count($xdataAlarmsRaised) > 0) {
					//add the alarm plot
					$graph->Add(makeAlarmsPlot($xdataAlarmsRaised, $ydataAlarmsRaised, true));
				}
				if (count($xdataAlarmsCleared) > 0) {
					//add the alarm plot
					$graph->Add(makeAlarmsPlot($xdataAlarmsCleared, $ydataAlarmsCleared, false));
				}
			}
		}
        $graph->Stroke();
        

    }

	/**
	 * Generates a ScatterPlot based on alarm info
	 * @param $isRaised - true if this is the raised alarms plot, 
	 *	false otherwise.  There are two plots total
	 */
	function &makeAlarmsPlot($xdata, $ydata, $isRaised) {
		$plot = new ScatterPlot($ydata, $xdata);
		$plot->mark->SetType($isRaised ? MARK_UTRIANGLE : MARK_DTRIANGLE);
		$plot->mark->SetFillColor($isRaised ? 'yellow' : 'green');
		$plot->mark->SetWidth(5);
		return $plot;
	}

    /**
     * Generates an image that's predetermined to display an error
     * Puts the specified message on the graph
     * Draws the graph
     */
    function makeErrorGraph($error) {
        global $conf;
        //echo "$error";
        $graph = new Graph($conf['graph']['width'],$conf['graph']['height'],"auto");
        $xdata = array(1,2);
        $ydata = array(1,2);
        $plot = new LinePlot($xdata, $ydata);
        $graph->Add($plot);
        setGraphToError($graph, $error);
        $graph->Stroke();
    }

    /**
     * Changes the scale and adds the given message
     * to the graph.
     * it is assumed that no data is on the graph
     * @param graph - the graph to add things to
     * @param error - text message to show (in red)
     */
    function setGraphToError(&$graph, $error) {
        $txt =new Text($error);
        $txt->SetPos( 0.2,0.1);
        $txt->SetColor( "red");
        $graph->AddText( $txt);

        //override scale so it doesn't complain
        $graph->SetScale("linlin",0, 1, 0, 1);

    }


    function setupGraph($XUnits = "hours", $startTime, $count, $availableReadingsCount, $options = array()) {
        global $conf;
        $graph = new Graph($conf['graph']['width'],$conf['graph']['height'],"auto");
        $graph->SetScale("intlin");
        //$graph->SetScale("linlin",$mintemp,$maxtemp,$mintime, $maxtime);

        //$graph->SetShadow();
        $rightMargin = 20;
        if (isset($options['showMargin']) && $options['showMargin'] == 1) {
            $rightMargin = 110;
        }
        $graph->img->SetMargin(50,$rightMargin,20,40);
        $graph->SetBox(true,'black',2);
        $graph->SetColor($conf['graph']['bgcolor']);
        $graph->SetMarginColor($conf['html']['bgcolor']);


        //$graph->title->Set("Digitemp Activity");
        $graph->title->Set("Digitemp Activity starting ".date("H:i:s m/d/Y", $startTime));
        /**
        $txt =new Text("Starting ".date("H:i:s m/d/Y", $startTime));
        $txt->SetPos( 0.59,0.01);
        $txt->SetColor( "blue");
        $graph->AddText( $txt);
        **/
    //junk:
        $graph->title->SetFont(FF_FONT1,FS_BOLD);

        $graph->xgrid->Show();

        $graph->legend->Pos(0.02,0.02,"right","top");

/*
        $graph->yaxis->SetPos(0);
        $graph->yaxis->SetWeight(2);
        $graph->yaxis->SetFont(FF_FONT1,FS_BOLD);
        $graph->yaxis->SetColor('black','darkblue');
*/
        $graph->xaxis->SetWeight(2);
        $graph->xaxis->SetFont(FF_FONT1,FS_BOLD);
        $graph->xaxis->SetColor('black','darkblue');
        $graph->xaxis->SetPos('min');

        
       // echo "Setting limits to $mintime, $maxtime, $mintemp, $maxtemp";
        //$graph->SetScale("linlin",$mintemp,$maxtemp,$mintime, $maxtime);
        $graph->xaxis->title->Set("Time ($XUnits)");
        $graph->yaxis->title->Set('Temperature ('. ucfirst($options['units']) . ')');

        

        $txt2 =new Text("Measurements shown: $count/$availableReadingsCount");
        $txt2->SetPos( 0.02,0.96);
        $txt2->SetColor( "blue");
        $graph->AddText( $txt2);

        return $graph;

    }

    function makePlot($xd, $yd, $plotName, $color, $options = array()) {
//        global $colors;
//        $color = array_pop($colors);
        //echo "xdata is ".sizeof($xd);
        if (sizeof($xd) == 0) {
            return;
        }
        $plot=new LinePlot($yd, $xd);
        //The only way to not have the legend box show
        //is to not set the info at all
        if (isOptionOn($options, 'showLegend')) {
            $plot->SetLegend($plotName);
        }
        
        if (isOptionOn($options, 'showMarks')) {
            $plot->mark->SetType(MARK_DIAMOND);
            $plot->mark->Show();
            $plot->mark->SetColor($color);
        }
        //echo "<BR> Making plot for $plotName";
        //print_r ($plot);
        return $plot;
    }

    /**
     * Convenience method to check if the option has been 
     * passed in and the value is 1
     */
    function isOptionOn($options = array(), $optionName) {

        if (isset($options[$optionName]) && $options[$optionName] == 1) {
            return true;
        }
        return false;
    }

    $driver->close();

?>
