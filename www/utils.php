<?php

//Variables that get serialized for cookie preferences
define('SAVED_VARS',serialize(array(
    'show_stats',
    'showLegend',
    'showMargin',
    'showMarks',
    'showBands',
    'showNegatives',
    'toggleUnits',
    'datesAbsolute',
    'showAlarms',
    
    'startHour',
    'startMinute',
    'startMonth',
    'startDay',
    'startYear',
    'endHour',
    'endMinute',
    'endMonth',
    'endDay',
    'endYear',
    
    'offset',
    'endoffset',

    'sensor'

    
    )));

//Vars that will be registered like register_globals
//from $_REQUEST
define('REGISTERED_VARS',serialize(array(
    'show_stats',
    'showLegend',
    'showMargin',
    'showMarks',
    'showBands',
    'showNegatives',
    'toggleUnits',
    'datesAbsolute',
    'showAlarms',
    
    'startHour',
    'startMinute',
    'startMonth',
    'startDay',
    'startYear',
    'endHour',
    'endMinute',
    'endMonth',
    'endDay',
    'endYear',
    
    'offset',
    'endoffset',

    'sensor',

    //in addition:
    'showRepeats',
    'showAll',
    'saveCookie',

    //for graph
    'startTime',
    'endTime',
    'units',

    //for alarm viewer
    'activeOnly',

    //For determining state
    'realform'
    
    
    )));
class Utils {


    /**
     * Abort with a fatal error, displaying debug information to the
     * user.
     *
     * @access public
     *
     * @param object PEAR_Error $error  An error object with debug information.
     * @param integer $file             The file in which the error occured.
     * @param integer $line             The line on which the error occured.
     * @param optional boolean $log     Log this message via Horde::logMesage()?
     */
    static function fatal($error, $file, $line, $log = true)
    {

        $errortext = _("<b>A fatal error has occurred:</b>") . "<br /><br />\n";
        if (is_object($error) && method_exists($error, 'getMessage')) {
            $errortext .= $error->getMessage() . "<br /><br />\n";
        }
        $errortext .= sprintf(_("[line %s of %s]"), $line, $file);

        if ($log) {
            $errortext .= "<br /><br />\n";
            $errortext .= _("Details have been logged for the administrator.");
        }

        // Log the fatal error  if requested.
        if ($log) {
         //   Horde::logMessage($error, $file, $line, LOG_EMERG);
        }

        // Hardcode a small stylesheet so that this doesn't depend on
        // anything else.
        echo <<< HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "DTD/xhtml1-transitional.dtd">
<html>
<head>
<title>DTtemp :: Fatal Error</title>
<style type="text/css">
<!--
body { font-family: Geneva,Arial,Helvetica,sans-serif; font-size: 12px; background-color: #222244; color: #ffffff; }
.header { color: #ccccee; background-color: #444466; font-family: Verdana,Helvetica,sans-serif; font-size: 12px; }
-->
</style>
</head>
<body>
<table border="0" align="center" width="500" cellpadding="2" cellspacing="0">
<tr><td class="header" align="center">$errortext</td></tr>
</table>
</body>
</html>
HTML;

        exit;

    }

    /**
     * Converts now or supplied unix time
     * to mysql timestamp
     */
    static function getTimeString($date = null) {
        if (!isset($date)) {
            //set it to now
            $date = time();
        }
        return date("YmdHis", $date);
    }

    /**
     * Converts a Fahrenheit Value to Celsius
     * If not set, returns null
     */
    static function toCelsius($temp) {
        if (!isset($temp)) {
            return null;
        }
        return (($temp - 32) *  5 / 9 );
    }

    /**
     * Instead of zero that round would print,
     * this prints a question mark for undefined fields
     */
    static function myRound($value, $precision) {
        if (isset($value)) {
            return round($value, $precision);
        } else {
            return '?';
        }
    }
    
    /**
     * Borrowed from  posted comments on php.net
     * This is a useful profiling print function
     *
     * use by:
     * global $stop_watch;
     * $stop_watch['Start'] = microtime();
     * $stop_watch['Some Event'] = microtime();
     * ..... so on
     * echo_stopwatch()
     *
     * (Output goes in html comments)
     */
    static function echo_stopwatch()
    {
        global $stop_watch;

        echo "\n\n<!--\n";
        echo "\nTiming ***************************************************\n";

        $total_elapsed = 0;
        list($usec, $sec) = explode(" ",$stop_watch['Start']);
        $t_end = ((float)$usec + (float)$sec);

        foreach( $stop_watch as $key => $value )
        {
            list($usec, $sec) = explode(" ",$value);
            $t_start = ((float)$usec + (float)$sec);

            $elpased = abs($t_end - $t_start);
            $total_elapsed += $elpased;

            echo str_pad($key, 20, ' ', STR_PAD_LEFT).": ".number_format($elpased,3).' '.number_format($total_elapsed,3), 0 ;
            echo "\n";
            $t_end = $t_start;
        }

        echo "\n";
        echo str_pad("Elapsed time", 20, ' ', STR_PAD_LEFT).": ".number_format($total_elapsed,3), 0 ;
        echo "\n";
        echo "\n-->";
    }

    /**
     * using globals, figures out which 
     * method is used (relative or absolute)
     * and then calls the other getDisplayDataTimes method
     * Assumes that globals from REQUEST have been 
     * registered.
     *
     * @param defaultOffset - if nothing is passed in for startTime, 
     *      set the start time   
     *      according to the given offset (should be negative). Optional
     */
    static function getDisplayDataTimesAutomatically($defaultOffset = null) {
        global $offset, $endoffset, $startHour, $startMinute, $startMonth, $startDay, $startYear, $endHour, $endMinutor, $endMonth, $endDay, $endYear;

        $startTime = null;
        $endTime = null;
        if (isset($offset) || isset($endoffset)) {
            $startTime = time() + $offset;
            $endTime = time() + $endoffset;
        } else {
            //parse out components
            if (isset($startHour)) {
                //figuring that hour is enough to assume the others are set
                $startTime = mktime($startHour, $startMinute, 0, $startMonth, $startDay, $startYear);
                //echo "made startTime =$startTime";
            }
            if (isset($endHour)) {
                $endTime = mktime($endHour, $endMinute, 0, $endMonth, $endDay, $endYear);
                //echo "Made endTime = $endTime";
            }
        }

        return Utils::getDisplayDataTimesAbs($startTime, $endTime, $defaultOffset);
    }



    /**
     * Generates an array of all sorts of useful times
     * Works with epoch seconds
     *
     * @param startTime - unix starting time, can be null
     * @param endTime - unix ending time, can be null
     * @param defaultOffset - if startTime is null, set the start time   
     *      according to the given offset (should be negative). Optional,
     *      configuration will be used if not given
     */
    static function getDisplayDataTimesAbs(&$startTime , &$endTime, $defaultOffset = null) {
        global $conf;
        $now = time();
        if ($defaultOffset == null) {
            $defaultOffset = $conf['data']['defaultOffset'];
        }
        
        if (!isset($startTime) || $startTime == "") {
            $startTime = $now + $defaultOffset;
        }
        if (!isset($endTime) || $endTime == "") {
            $endTime = $now;
        }


        $result = array();

        $thisMorning = mktime(
                0,
                0,
                0,
                date("m", $endTime),
                date("d", $endTime),
                date("Y", $endTime));
        $requestedMorning = mktime(
                0,
                0,
                0,
                date("m", $startTime),
                date("d", $startTime),
                date("Y", $startTime));
        $thisHour = mktime(
                date("G", $endTime),
                0,
                0,
                date("m", $endTime),
                date("d", $endTime),
                date("Y", $endTime));
        $requestedHour = mktime(
                date("G", $startTime),
                0,
                0,
                date("m", $startTime),
                date("d", $startTime),
                date("Y", $startTime));
        $thisMonth = mktime(
                0,
                0,
                0,
                date("m", $endTime),
                0,
                date("Y", $endTime));
        $requestedMonth = mktime(
                0,
                0,
                0,
                date("m", $startTime),
                0,
                date("Y", $startTime));
        $thisYear = mktime(
                0,
                0,
                0,
                0,
                0,
                date("Y", $endTime));
        $requestedYear = mktime(
                0,
                0,
                0,
                0,
                0,
                date("Y", $startTime));
        $startRelative = $startTime - $requestedMorning;  //seonds since midnight of start day

        $result['thisMorning'] = $thisMorning;
        $result['requestedMorning'] = $requestedMorning;
        $result['thisHour'] = $thisHour;
        $result['requestedHour'] = $requestedHour;
        $result['thisMonth'] = $thisMonth;
        $result['requestedMonth'] = $requestedMonth;
        $result['thisYear'] = $thisYear;
        $result['requestedYear'] = $requestedYear;
        $result['startTime'] = $startTime;
        $result['startRelative'] = $startRelative;
        $result['endTime'] = $endTime;
        $result['duration'] = $endTime - $startTime;
        return $result;
    }
            

//Supporting functions
    /**
     * Used to draw a described checkbox in a table
     * takes care of everything not including surrounding TR's
     * @param cginame - the input name
     * @param value - the input value to be passed back
     * @param state - boolean, if true - makes it checked
     * @param name - the Text to show next to the checkbox
     * @param description - the text to show with onhover (if supported)
     * @param color - text color for the name (optional)
     * @param onClickWarning - if provided, shows an alert when user clicks to turn on
     */
    static function makeSingleNoTDCheckBox($cginame, $value = 1, $state = false, $name, $description, $color = null, $onClickWarning = null) {
        $result = Utils::makeCheckBoxOnly($cginame, $value,  $state,  $onClickWarning);
        $result .= Utils::makeBlock($name, $description, $color);
        $result .= "\n";

        return $result;
    }
    /**
     * Used to draw a described checkbox in a table
     * takes care of everything not including surrounding TR's
     * @param cginame - the input name
     * @param value - the input value to be passed back
     * @param state - boolean, if true - makes it checked
     * @param name - the Text to show next to the checkbox
     * @param description - the text to show with onhover (if supported)
     * @param color - text color for the name (optional)
     * @param onClickWarning - if provided, shows an alert when user clicks to turn on
     * @param inactive - this sensor is not actively logging
     */
    static function makeDoubleTDCheckBox($cginame, $value = 1, $state = false, $name, $description, $color = null, $onClickWarning = null, $inactive = false) {
        $result =  "<TD>";
        $result .= Utils::makeCheckBoxOnly($cginame, $value,  $state,  $onClickWarning);
        $result .= "</TD>\n";
        $result .= Utils::makeTD($name, $description, $color, null, $inactive);

        return $result;
    }
    
    /**
     * Generates a checkbox
     */
    static function makeCheckBoxOnly($cginame, $value = 1, $state = false, $onClickWarning = null) {
        $result = "<INPUT type='checkbox' name='$cginame' value='$value' ".($state ? " CHECKED " : "");
        if (isset($onClickWarning)) {
            $result .= " onclick=\"if (value==checked) alert('$onClickWarning')\" ";
        }
        $result .= ">";
        return $result;

    }

    /**
     * Makes an active TD segment with an onclick/onmouseover
     */
    static function makeTD($name, $description, $color = null, $imgname = null, $inactive = false) {
        $result = '<TD>';
        $result .= $inactive ? '<strike>' : '';
        $result .= Utils::makeBlock($name, $description, $color, $imgname);
        $result .= $inactive ? '</strike>' : '';
        $result .= "</TD>\n";
        return $result;
    }

    /**
     * Like makeTD but without the actual TD
     */
    static function makeBlock($name, $description, $color = null, $imgname = null) {
        $result = "<A onmouseout=\"window.status=''\"";
        $result .= " onmouseover=\"window.status='$description'\"";
        $result .= " onclick=\"alert('$name: $description')\" title='$description'>";
        if (isset($imgname)) {
            $result .= Utils::makeIMG($imgname, "$name: $description");
        } else {
            $result .= Utils::color($name, $color);
        }
        $result .= "</A>\n";
        return $result;
    }

    static function makeIMG($imgname, $alt = null) {
        return "<IMG src=\"images/$imgname\" alt=\"$alt\">";
    }

    static function makeTRCheckBox($cginame, $value = 1, $state = false, $name, $description, $color = null, $onClickWarning = null) {
        return '<TR>'.Utils::makeDoubleTDCheckBox($cginame, $value, $state, $name, $description, $color, $onClickWarning).'</TR>';
    }
    /**
     * Returns a <FONT color="$color">$text</FONT>
     */
    static function color($text, $color) {
        if (!isset($color)) {
            $result = $text;
        } else {
            $result = "<FONT color=\"$color\">$text</FONT>";
        }
        return $result;
    }

    /**
     * Returns "Celsius" given "Fahrenheit"
     * and vice versa
     */
    static function getOtherUnit($unit) {
        $unit = strtolower($unit);
        if ($unit == 'fahrenheit') {
            return 'celsius';
        } else if ($unit == 'celsius') {
            return 'fahrenheit';
        } else {
            echo "ALARM! unknown unit : $unit";
        }
    }

    /**
     * serializes the currently supported switches 
     * to a binary string 
     */
    static function makePrefsCookieValue() {
        $savedVars = unserialize(SAVED_VARS);
        reset($savedVars);
        $toSerialize = array();
        for ($i=0; $i<count($savedVars); $i++) {
            //$result .= isset($_REQUEST[$savedVars[$i]]) ? 1 : 0;
            if (isset($_REQUEST[$savedVars[$i]])) {
                //put in array
               $toSerialize[$savedVars[$i]] = $_REQUEST[$savedVars[$i]];
            }
        }
        return serialize($toSerialize);
    }

    static function parsePrefsCookieValue($value) {
        if (!isset($value)) {
            return;
        }
        $savedVars = unserialize(SAVED_VARS);
        reset($savedVars);

        $values = unserialize($value);

        if (is_array($values)) {
            for ($i=0; $i<count($savedVars); $i++) {
                //echo "setting var ".$savedVars[$i]." to ".$value[$i];
                //$_REQUEST[$savedVars[$i]] = $value[$i];
                if (isset($values[$savedVars[$i]])) {
                    //use that one
                    global $$savedVars[$i];
                    $$savedVars[$i] = $values[$savedVars[$i]];
                }
            }
        }
    }

    /**
     * Creates an associative array of graph options
     * must be called after myRegisterGlobals
     */
     static function getGraphOptions() {

        $options = array();
        //this prevents warnings if php is set to show notices, initializing everything to null
        global $showLegend, $showMargin, $showNegatives, $showBands, $showMarks, $showAll, $units;
        $options['showLegend'] = isset($showLegend) ? $showLegend : null;
        $options['showMargin'] = isset($showMargin) ? $showMargin : null;
        $options['showNegatives'] = isset($showNegatives) ? $showNegatives : null;
        $options['showBands'] = isset($showBands) ? $showBands : null;
        $options['showMarks'] = isset($showMarks) ? $showMarks : null;
        $options['showAll'] = isset($showAll) ? $showAll : null;

        global $conf;
        $options['units'] = isset($units) ? $units : $conf['data']['units'];
        
        return $options;
     }

    /**
     * Registers the vars that I've approved,
     * eliminating the need for register_globals to be 
     * on in php.ini
     * Optional array param can override the system list of vars
     */
    static function myRegisterGlobals($vars = null) {
        if (!isset($vars)) {
            $vars = unserialize(REGISTERED_VARS);
        }

        for ($i=0; $i<count($vars); $i++) {
            //echo "setting var ".$savedVars[$i]." to ".$value[$i];
            global $$vars[$i];
            if(isset($_REQUEST[$vars[$i]])) {
                //use that one
                $$vars[$i] = $_REQUEST[$vars[$i]];
                //init them to null to allay some strange php installations
            } else {
            //    echo "Setting $vars[$i] to null ";
                $$vars[$i] = null;
            }
        }

    }

    /**
     * Certain user-inteface items like showAlarms
     * and toggleUnits have configured defaults if a value
     * is not provided
     * 
     * NOTE: expected to be called AFTER myRegisterGlobals
     */
    static function setDefaultValues() {
        global $conf;
        global $realform;

        // Alarms
        global $showAlarms;

        if(!isset($realform)) { //not the result of a submit
            //        $showAlarms = $conf['alarms']['display'];
            reset ($conf['data']['defaults']);
            while (list($key, $val) = each($conf['data']['defaults'])) {
                if ($val) {
                    global $$key;
                    $$key = $val;
                }
            }
        }

        /// Units
        global $toggleUnits;
        global $units;

        $units = $conf['data']['units']; //use default
        if (isset($toggleUnits)) {
            $units = Utils::getOtherUnit($units);
        }

    }

    /**
     * Generates a human-readable representation
     * of the given duration in millis.
     * example ouput:
     * 5 Days 3 Hours 32 Min
     * 32 Seconds
     */
    static function getDurationString($durationMillis = 0) {
       if ($durationMillis < 1000) {
          //it's less than a second!
          return $durationMillis + " ms";
        }
        $duration = $durationMillis / 1000;

        $minFactor = 60;
        $hourFactor = $minFactor * 60;
        $dayFactor = $hourFactor * 24;

        $days = 0;
        $hours = 0;
        $minutes = 0;
        $millis = $durationMillis % 1000;;

        if ($duration > $dayFactor) {
          $days = $duration / $dayFactor;
          $duration = $duration - ($days * $dayFactor);
          $days = Utils::myRound($days, 2);
        }
        if ($duration > $hourFactor) {
          $hours = $duration / $hourFactor;
          $duration = $duration - ($hours * $hourFactor);
          $hours = Utils::myRound($hours, 1);
        }
        if ($duration > $minFactor) {
          $minutes = $duration / $minFactor;
          $duration = $duration - ($minutes * $minFactor);
          $minutes = Utils::myRound($minutes, 1);
        }

        $result = (($days > 0) ? $days . ' Days ' : '')
          . (($hours > 0) ? $hours . ' Hours ' : '')
          . (($minutes > 0) ? $minutes . ' Min ' : '');

        if ($days == 0 && $hours == 0) {
          $result .= $duration . ' Seconds ';
          if ($minutes == 0 && $millis != 0) {
              //only if this is under 1 Minute
            $result .= $millis . ' ms';
          }
        }
        return $result;
                  
    }

    static function checkError($dbObject, $query = null) {
        if (PEAR::isError($dbObject) || MDB2::isError($dbObject)) {
            die("DB related error: ".$dbObject . " for " . $query);
        }
    }

}
	//vim: se sw=4 ts=4 et:
?>
