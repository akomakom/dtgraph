<?php
/*
 * This file is part of an open-source project
 * licensed under the GPL
 *
 * Structure loosely based on horde's components
 *  (www.horde.org)
 */
    
    //This is invoked from subdirectories, 
    //so utils.php will be relative the subdir.
    //locating it 'absolutely' instead
require_once(dirname(__FILE__) . '/../' . 'utils.php');
require_once("MDB2.php");

/**
 * The DTtemp_Driver:: class implements the DB Backend for DTtemp
 *
 */
class DTtemp_Driver_sql extends DTtemp_Driver {

    /**
     * The object handle for the current database connection.
     * @var object DB $_db
     */
    var $_db;

    /**
     * Boolean indicating whether or not we're currently connected to
     * the SQL server.
     * @var boolean $_connected
     */
    var $_connected = false;

    function connect()
    {
        $this->_connect();
    }


    /**
     *  dates will be in native format
     *  yyyyMMddhhmmss
     *  
     *  Results are returned as 2 dim array
     *  with key as key and columns as the inner ass. array
     *  Inner array will contain time as mysql timestamp
     *  and unixtime keys
     *  
     *  If sensor is not supplied, all are fetched
     *
     *  @param startDate - if set, will be used as beginning of query, likewise for end
     *  @param fetchUnchanged   if false, of any number of consecutive readings with 
     *         the same value only the outermost two will be returned
     *  @param thinOut - if true, data will be thinned out by getting only every Nth result,
     *         using the algorithm set in configuration
     *  @param units - default will be used from configuration, but request may override it by setting this to 
     *                                                                          the opposite value
     *  @return an array of results or a db error on error
     *
     */
    function listEvents($sensors = null, $startDate = null, $endDate = null, $fetchUnchanged = false, $thinOut = false, $units = null)
    {
        $result = array();
        $t = time();
        global $stop_watch;
        
        global $conf;

        $count = 0;
        if (!isset($units)) {
            $units = $conf['data']['units']; //use default
        }


        //defaults
        $thinOutSql = ($conf['data']['thinOutMethod'] == "SQL");
        $thinOutCode = ($conf['data']['thinOutMethod'] == "CODE");

        if ($thinOut && $conf['data']['thinOutMethod'] == "AUTO") {
            //run a count query to decide
            //this query is the same as the main one, 
            //except the thinOutSql is set to false
            $count  = $this->countEvents($sensors, $startDate, $endDate);
            if ($count > $conf['data']['thinOutAutoSQLThreshold']) {
                //auto mode, engage SQL instead
                $thinOutSql = true;
                $thinOutCode = true;
            } else {
                //auto mode, force CODE
                $thinOutCode = true;
            }
        }


        $q = 'SELECT  *, unix_timestamp(time) unixtime';
        $q .= ($units == 'celsius') ? ', ((fahrenheit -32) *  5 / 9 ) celsius' : '';
        $q .= ' FROM ' . $this->_params['table'];
        $q .= $this->makeWhereClause($sensors, $startDate, $endDate, $thinOutSql);
        $q .= ' order by time ASC';
        //echo "Query to be run: $q";
        $stop_watch['Made Query'] = microtime();

        //echo "Running query: $q";
        /* Run the query. */
        $qr = $this->_db->query($q);
        $stop_watch['Ran Query'] = microtime();
        //echo "Got results: ".$qr->numRows();
        
        $backup = array();
        $lastReadings = array();

        if (!PEAR::isError($qr)) {
            
            $thinOutAmount = 1;
            if ($thinOutCode) {
                //setup thin out factor
                $excess =  $qr->numRows() - $conf['data']['maxThinnedOutDataPoints'];
                //echo "Excess is $excess based on ".$qr->numRows()." and ".$conf['data']['maxThinnedOutDataPoints'];

                if ($excess > 0) {
                    //we should drop every so many readings
                    $thinOutAmount = $excess / $conf['data']['maxThinnedOutDataPoints'];
                    //echo "This out is $thinOutEvery";
                }
            }
            
            //echo "thinoutevery=$thinOutEvery ... ";

            $row = true;
            $firstTime = true;
            while ( ($row = $qr->fetchRow(MDB2_FETCHMODE_ASSOC)) && !PEAR::isError($row)) {
                //echo "<br>ROW: ".print_r($row,true);

                $ser = $row['serialnumber'];
                if ($firstTime) {
                    $backup[$ser] = $row;
                    
                    if ($fetchUnchanged == false) {
                        //Always tack on first reading (if in unch mode)
                        $result[$row['dtkey']] = $row;
                    }
                    $firstTime = false;
                }
                
                //using code to determine if we should
                //skip items.
                if ($thinOutCode && $this->shouldSkipRow($ser, $thinOutAmount)) {
                    continue;
                }
                    
                if ($fetchUnchanged == false) {
                    //record only if backup is not the same as this
                    //Note this causes problems if last two items are the same -
                    //the last reading will be missing, screwing up the graph
                    $currentTemp = $row['fahrenheit'];
                    $backupTemp = isset($backup[$ser]) ? $backup[$ser]['fahrenheit'] : null;
                    //echo "CurrentTemp = $currentTemp, backupTemp = $backupTemp";
                    if ($currentTemp != $backupTemp) {
                       // echo "Adding ";
                       //add backup too so it's accurate!
                       if (isset($backup[$ser])) {
                           $result[$backup[$ser]['dtkey']] = $backup[$ser];
                       }
                       $result[$row['dtkey']] = $row;
                    }
                    ///echo "<br>";
                    //backup last readings so that we have the last data point visible
                    $lastReadings[$ser] = $row;

                } else {
                    $result[$row['dtkey']] = $row;
                }
                
                $backup[$ser] = $row;
            }
            if ($fetchUnchanged == false) {
                //now tack on the last reading backups
                while (list($key, $val) = each($lastReadings)) {
                    $result[$val['dtkey']] = $val;
                }
            }
        } else {
            //it's an error!
            $result = $qr;
        }
        $stop_watch['Post Processed Query'] = microtime();
        return $result;
    }

    /**
     * This is a thinOut helper function
     * it keeps track of counters, etc
     * Note that this function does not account for repeats 
     * (aka fetchUnchanged)
     * and results in a slightly smaller amount of data
     * then requested.  However, usually when skipping data
     * repeats are not as common
     *
     * @return true if this row should be skipped
     *
     */
    function shouldSkipRow($ser, $thinOutAmount) {
        //this should already be an array
        global $thinOutCounters;
        $result = false;

        if (!isset($thinOutCounters)) {
            $thinOutCounters = array();
        }

        //skip entries based on thinOutAmount (too many)
        if (!isset($thinOutCounters[$ser])) {
            //put it in!
            $thinOutCounters[$ser] = 0;
        }

        //is it over 1?
        if ($thinOutAmount >= 1) {
            if ( $thinOutCounters[$ser] >= $thinOutAmount) {
                $thinOutCounters[$ser] = 0;
                //echo "thinning out ".$row['dtkey'];
                //use this value!
            } else {
                //not ready yet
                $thinOutCounters[$ser]++;
                $result = true;
            }
        } else {
            //thin out amount is < 1
            //add it to the counter instead and 
            //wait to reach 1
            if ($thinOutCounters[$ser] >= 1) {
                $thinOutCounters[$ser] = 0;
                //skip only this one!
                $result = true;
            } else {
                $thinOutCounters[$ser] += $thinOutAmount;
                //until it reaches 1...
                //and use this row
            }
        }
        return $result;
    }


    /**
     * Delete all sensor's events.
     *
     * @param string $sensor The name of the sensor to delete.
     *
     * @return mixed  True or a PEAR_Error on failure.
     */
    function deleteMetadata($sensor)
    {
        $this->_connect();

        $query = sprintf('DELETE FROM %s WHERE serialnumber = %s',
                    $this->_params['table_meta'],
                    $this->_db->quote($sensor));

        /* Log the query at a DEBUG log level. */

        $res = $this->_db->query($query);
        if (PEAR::isError($res)) {
            return $res;
        }

        return true;
    }


    /**
     * Delete all sensor's events.
     *
     * @param string $sensor The name of the sensor to delete.
     *
     * @return mixed  True or a PEAR_Error on failure.
     */
    function delete($sensor)
    {
        $this->_connect();

        $query = sprintf('DELETE FROM %s WHERE serialnumber = %s',
                    $this->_params['table'],
                    $this->_db->quote($sensor));

        $res = $this->_db->query($query);
        if (PEAR::isError($res)) {
            return $res;
        }

        return true;
    }

    /**
     * Event id is the dtkey
     */
    function deleteEvent($eventID)
    {
        $eventID = (int)$eventID;
        $query = sprintf('DELETE FROM %s WHERE dtkey = %s ',
                         $this->_params['table'],
                         $this->_db->quote($eventID));

        /* Log the query at a DEBUG log level. */

        if (PEAR::isError($res = $this->_db->query($query))) {
            return false;
        }

        return true;
    }

    /**
     *  Returns the number of events
     *  if list of sensors supplied, 
     *  then just for them (all of them)
     */
    function countEvents($sensors = null, $startDate = null, $endDate = null) {
        $result = ""; //need a default of something

        $q = 'SELECT COUNT(*) from '.$this->_params['table'];
        $q .= $this->makeWhereClause($sensors, $startDate, $endDate);

        //echo "Running count query: $q";

        /* Run the query. */
        $qr = $this->_db->query($q);

        if (!PEAR::isError($qr)) {
            $row = $qr->fetchRow();
            return array_pop($row);
        }

    }

    /**
     *  Returns the unix timestamp representing
     *  start of data
     */
    function getStartOfData($sensors = null) {
        $result = ""; //need a default of something

        $q = 'SELECT MIN(unix_timestamp(time)) from '.$this->_params['table'];
        if (isset($sensors)) {
            $q .= ' WHERE serialnumber IN ('. $this->makeInList($sensors).')';
        }

        /* Run the query. */
        $qr = $this->_db->query($q);


        if (!PEAR::isError($qr)) {
            $row = $qr->fetchRow();
            return array_pop($row);
        }
    }

    /**
     * Returns an info array about each sensor:
     * (actual readings)
     * [sensor][min, max, avg, Current]
     *
     * @access public
     */
    function getStats($sensors = null, $startTime = null, $endTime = null, $units = null) {
        global $conf;
        if (!isset($units)) {
            $units = $conf['data']['units']; //use default
        }
        $result = array();

        if (isset($sensors) && is_array($sensors)) {
            //we're good
        } else { //need to get list of sensors

            $sensors= $this->listDistinctSensors();
        }

        //NOw we have a list of sensors to work with
        reset($sensors);
        while(list($num, $sensor) = each($sensors)) {
            unset($sArray);
            $sArray = array($sensor);
            $q = 'select min(fahrenheit) min, max(fahrenheit) max, avg(fahrenheit) avg';
            //using +0 to make Mysql4.1 return time as a timestamp,
            //not string.  Weird mysql change!
            $q .=',max(time + 0) maxtime FROM ' . $this->_params['table'] ;
            $q .= $this->makeWhereClause($sArray, $startTime, $endTime);
            //echo "<P>Now should run $q";
            /* Run the query. */
            $qr = $this->_db->query($q);

            if (!PEAR::isError($qr)) {
                $row = $qr->fetchRow(MDB2_FETCHMODE_ASSOC);
                if ($row && isset($row['min'])) { //if there is no data, we'll get empty results
                    //echo "Got row";
                    //print_r($row);
                    if($units == 'celsius') {
                        $row['min'] = Utils::toCelsius($row['min']);
                        $row['max'] = Utils::toCelsius($row['max']);
                        $row['avg'] = Utils::toCelsius($row['avg']);
                    }
                    $result[$sensor] = $row;


                    // Read the current temp based on our findings
                    
                    //echo $sensor;
                    //print_r($row);
                    $subQ = 'SELECT ';
                    $subQ .= ($units == 'celsius') ? ' ((fahrenheit -32) *  5 / 9 ) celsius' : 'fahrenheit';
                    $subQ .= ' from '. $this->_params['table'] .
                        " WHERE serialnumber = '$sensor' and time = ". $row['maxtime'];
                    //echo "<P>running $subQ based on: ";
                    //print_r($row);
                    //now run the current reading query
                    $qr = $this->_db->query($subQ);

                    if (!PEAR::isError($qr)) {
                        $row = $qr->fetchRow();
                        //echo "Got row";
                        //print_r($row);
                        $result[$sensor]['Current'] = $row[0];
                    } else {
                        die("Error reading current Temp using $subQ: ".__FILE__. ":".__LINE__.': '. $qr->getMessage());
                    }

                } else {
                    $result[$sensor] = array('min'=> 'no data!');
                }
            }

        }
        return $result;
    }

    /**
     * This runs the distinct query
     * which gets a list of sensors that have 
     * actual readings
     */
    function listDistinctSensors() {
        $result = array();
        $q = 'SELECT distinct serialnumber FROM '. $this->_params['table'];
        $qr = $this->_db->query($q);


        if (!PEAR::isError($qr)) {
            $row = $qr->fetchRow();
            //echo "Got row";
            //print_r($row);
            while ($row && !PEAR::isError($row)) {
            //print_r($row);
                array_push($result,$row[0]);
                $row = $qr->fetchRow();
            }
        }
        //echo "list Distinct returning: ";
        //print_r($result);
        return $result;
    }

    /**
     * Normally gets info about all sensors
     *  If no list supplied, gets all sensors whether or not they are described
     *
     * if sensor serialnumber array is supplied, just gets 
     * the requested ones
     *
     *  Returned structure is
     *  array[serialNumber][dataAboutit]
     *  dataAboutIt includes name, description, allowed min, max, alarms, etc
     *  (all columns of the metadata table)
     * @return db error instead on errors
     */
    function listSensors($sensors = null, $units = null) {
        $result = array();
        if (!isset($units)) {
            global $conf;
            $units = $conf['data']['units'];
        }
//        $q = 'SELECT DISTINCT b.* FROM ' . $this->_params['table']. " a , ". $this->_params['table_meta']. " b where a.serialnumber = b.serialnumber ";
        $q = 'SELECT * FROM ' . $this->_params['table_meta'] . ' b WHERE true ';

        if (isset($sensors) && is_array($sensors)) {
            $q .= ' and  b.serialnumber in (';
            $q .= $this->makeInList($sensors);
            $q .= ')';
        }

        $q .= ' and active = 1 order by b.name asc';
        //echo "Running query : $q";
            
        /* Run the query. */
        $qr = $this->_db->query($q);


        if (!PEAR::isError($qr)) {
            $row = $qr->fetchRow(MDB2_FETCHMODE_ASSOC);
            //echo "Got row";
            while ($row && !PEAR::isError($row)) {
            //print_r($row);
                if ($units == 'celsius') {
                    $row['max'] = Utils::toCelsius($row['max']);
                    $row['min'] = Utils::toCelsius($row['min']);
                }
                $result[$row['serialnumber']]= $row;

                $row = $qr->fetchRow(MDB2_FETCHMODE_ASSOC);
                if (PEAR::isError($row)) {
                    return $row;
                }
            }
        } else {
            return $qr; //error!
        }

        unset($qr); //just to clear things up

        if ($sensors == null) {
            //now, if sensor list is null, get the real list of readings from the table..
            //Cause until now the list is based on the metadata contents
            //This will add fake defaults to the resulting array for any non-described sensors
            //This way a new sensor will be displayed before it is described
            //and the array sort will remain unchanged

            $distinctList = $this->listDistinctSensors();
            while(list($num, $ser) = each($distinctList)) {
                if (!isset($result[$ser])) {
                    //echo "Adding fake entry for ".$ser;
                    $result[$ser] =
                        $this->makeFakeArray($ser);
                }
            }
        } else if (isset($sensors) && is_array($sensors)) {
            //now check for sensors that were not describecd
            //but were passed in on the list
            reset($sensors);
            while (list($num, $ser) = each($sensors)) {
                if (!isset($result[$ser])) {
                    //put a fake in there
                    //echo "calling make fake array with $ser";
                    $result[$ser] = $this->makeFakeArray($ser);
                }
            }
        }

        //echo "Done<P>Returning";
        //print_r($result);
        return $result;

    }
    
    /**
     * Automatic update/insert method
     */
    function updateMetadata(
        $serial, 
        $name, 
        $description = null, 
        $alarm = null, 
        $min = null, 
        $max = null, 
        $maxchangeAlarm = null, 
        $maxchangeTemp = null, 
        $maxchangeTime = null,
        $color = null,
        $active = 1) {

        $q = 'SELECT serialnumber from '.$this->_params['table_meta'];
        $q .= " WHERE serialnumber = '$serial'";

       // echo "Running UPDATE exists check query: $q";

        /* Run the query. */
        $qr = $this->_db->query($q);

        if (!PEAR::isError($qr)) {
            $row = $qr->fetchRow();
            //$ser =  array_pop($row);
            if (isset($row) && is_array($row)) {
                //exists
                //echo "EXISTS: ".array_pop($row);
            } else {
                //echo "DOES NOT EXIST";
                //does not exist
                //insert bare minimum, then have it update either way
                $qi = 'INSERT INTO '.$this->_params['table_meta'].' (serialnumber, Name)';
                $qi .= " VALUES ('$serial', 'BogusName')";
                $insertResult = $this->_db->query($qi);
                if (!PEAR::isError($insertResult)) {
             //       echo "INSERT SUccessfull";
                } else {
                    echo "ERROR inserting :".$insertResult;
                    return;
                }
                
            }
        } else { 
            echo "Error Checking!";
            return;
        }

        //now setup the update query using all available data
        $qu = 'UPDATE '.$this->_params['table_meta'].' SET ';
        $qu .= " name = '$name'";
        $qu .= (isset($description)) ? " , description = '$description' " : '';
        $qu .= (isset($alarm) && is_numeric($alarm)) ? " , alarm = $alarm " : '';
        $qu .= (isset($max) && is_numeric($max)) ? " , max = $max " : '';
        $qu .= (isset($min) && is_numeric($min)) ? " , min = $min " : '';
        $qu .= (isset($maxchangeAlarm) && is_numeric($maxchangeAlarm)) ? " , maxchange_alarm = $maxchangeAlarm " : '';
        $qu .= (isset($maxchangeTemp) && is_numeric($maxchangeTemp)) ? " , maxchange= $maxchangeTemp " : '';
        $qu .= (isset($maxchangeTime) && is_numeric($maxchangeTime)) ? " , maxchange_interval = $maxchangeTime " : '';
        $qu .= (isset($color)) ? " , color = '$color' " : '';
        $qu .=  " , active = '$active' ";
    
        $qu .= " WHERE serialnumber = '$serial'";
        
        //echo "Running update query: $qu";
        
        $updateResult = $this->_db->query($qu);
        if (!PEAR::isError($updateResult)) {
         //   echo " UPdate Successfull";
        } else {
            echo " <P>Update failed!<P>: $updateResult";
        }
    }

    /**
     * In the absence of metadata for a sensor,
     * Constructs a fake representation of it 
     * based on the serial number
     * Sets color to black
     */
    function makeFakeArray($serial) {
        $result = array();
        $result['serialnumber'] = $serial;
        $result['name'] = $serial;
        $result['description'] = 'This sensor has not yet been described';
        $result['color'] = 'black'; //default
        return $result;
    }


    /**
     * Intended to analyze the current data
     * and put in/update records in the alarms table
     * if warranted.
     * Since a number of queries run for this, 
     * this should not be invoked too often
     * 
     * @return an array of newly raised alarms (strings)
     */
    function updateAlarms() 
    {
        $newAlarms = array();

        $sql = 'SELECT max(fahrenheit) realmax, min(fahrenheit) realmin from ';
        $sql .= $this->_params['table'].' a, ';
        $sql .= $this->_params['table_meta'].' b ' ;
        $sql .= ' where a.serialnumber = ? and a.serialnumber = b.serialnumber and time > FROM_UNIXTIME(UNIX_TIMESTAMP() - ?)'; 

        $ps = $this->_db->prepare($sql);
        
        // Step 1. Load metadata for sensors
        // note: using default units.  
        //To support celsius, need to rethink
        $metadata = $this->listSensors();

        $info = $this->getStats(null, time() - 3600 * 24 ); //go back one day so we don't grab too much data
        
        // Loop over the metadata because we only care about 
        //sensors that have it set
        while (list($serial, $s) = each($metadata)) {
            $current = $info[$serial]['Current'];
            if (!isset($current)) {
                continue;
            }

            $params = array($serial);
            $interval = $s['maxchange_interval'];
            if (isset($interval) && $interval > 0) {
                //then I'll just use that for min/max query
                //save me some time
                array_push($params, $interval);
            } else {
                array_push($params, 3600); //default amount of time
                //this isn't a problem cause then maxchange_alarm
                //shouldn't be set
            }

            //run the alarms query
            $rs = $ps->execute($params);

            $row = $rs->fetchRow(MDB2_FETCHMODE_ASSOC);
            $realmin = $row['realmin'];
            $realmax = $row['realmax'];

            //now I have everything, check if there are problems.

            echo "\n<BR>Checking sensor $serial";

            if (isset($s['alarm']) && $s['alarm'] == 1) {
                echo " maxmin :";
                //min and max absolute alarm
                
                if ($current < $s['min']) {
                    echo "yes, min";
                    //problem
                    //raise min alarm
                    $text = "Reading too low - $current is lower than ".$s['min'];
                    if($this->raiseAlarm($serial, $current, 'minmax', $text)) {
                        array_push($newAlarms, array('sensor' => $metadata[$serial]['name'] , 'text' => $text));
                    }
                } else if ($current > $s['max']) {
                    echo "yes, max";
                    //raise max alarm
                    $text = "Reading too high - $current is higher than ".$s['max'] ;
                    if ($this->raiseAlarm($serial, $current, 'minmax', $text)) {
                        array_push($newAlarms, array('sensor' => $metadata[$serial]['name'] , 'text' => $text));
                    }
                } else {
                    echo "no";
                    //unset
                    $this->clearAlarm($serial, 'minmax');
                }
            } else {
                //if someone shut off the checkbox, existing alarms
                //will remain active .... so I have to shut them off just in case
                $this->clearAlarm($serial, 'minmax');
                //unfortunately that means extra DB access...
                // but this is only called from cron anyway
            }

            if (isset($s['maxchange_alarm']) && $s['maxchange_alarm'] == 1) {
                //maxchange stuff 
                echo " Maxchange: ";
                if (($realmax - $realmin) > $s['maxchange']) {
                    echo " yes";
                    //raise maxchange alarm
                    $text = "Changing too fast: ranged between $realmin and $realmax in ".Utils::getDurationString($s['maxchange_interval'] * 1000);
                    if($this->raiseAlarm($serial, $current, 'maxchange', $text)) {
                        array_push($newAlarms, array('sensor' => $metadata[$serial]['name'] , 'text' => $text));
                    }
                } else {
                    echo " no ($realmax - $realmin) is not > ".$s['maxchange'];
                    $this->clearAlarm($serial, 'maxchange');
                }
            } else {
                //if someone shut off the checkbox, existing alarms
                //will remain active .... so I have to shut them off just in case
                $this->clearAlarm($serial, 'maxchange');
            }

        }
        return $newAlarms;
    }

    /**
     * Reads an array of alarm info
     * (3 dimensional array)
     * $array[serial][alarm_id][data] (data is fahrenheit, type, description, time_*)
     */
    function getActiveAlarms($startDate = null, $endDate = null) {
		return $this->internalGetAlarms(true, false, $startDate, $endDate);
	}

    function getInActiveAlarms($startDate = null, $endDate = null) {
		return $this->internalGetAlarms(false, true, $startDate, $endDate);

	}
    function getAllAlarms($startDate = null, $endDate = null) {
		return $this->internalGetAlarms(true, true, $startDate, $endDate);
	}

    /**
     * private function that actually does the work
     */
	function internalGetAlarms($active = true, $inactive = false, $startDate = null, $endDate = null) {
		$sql = 'SELECT alarm_id, serialnumber, fahrenheit, unix_timestamp(time_raised) time_raised, unix_timestamp(time_cleared) time_cleared, unix_timestamp(time_updated) time_updated, alarm_type, description from ';
        $sql .= $this->_params['table_alarms'];

        $sql .= ' where 1 ';

        if (!$active || !$inactive) {
            $sql .= ' and ';
            if ($active) {
                $sql .= 'time_cleared is null ';
            } else {
                $sql .= 'time_cleared is not null';
            }
        }

        
        if (isset($startDate)) {
            $sql .= ' AND ';
            $sql .= ' time_updated >= FROM_UNIXTIME(' . $this->_db->quote($startDate) .')';
            $andNeeded = true;
        }

        
        if (isset($endDate)) {
            $sql .= ' AND ';
            $sql .= ' time_updated <= FROM_UNIXTIME(' . $this->_db->quote($endDate). ')';
        }
          
       $sql .= ' order by time_raised desc';

       //echo "running $sql";
       $result = array();

       $rs = $this->_db->query($sql);
       if (!PEAR::isError($rs)) {
           $row = $rs->fetchRow(MDB2_FETCHMODE_ASSOC);
           //echo "Got row";
           while ($row && !PEAR::isError($row)) {
               $serial = $row['serialnumber'];
               $alarm_id = $row['alarm_id'];

               //echo "setting row";
               //print_r($row);
               if (!isset($result[$serial])) {
                   $result[$serial] = array();
               }
               $result[$serial][$alarm_id] = $row;
               $row = $rs->fetchRow(MDB2_FETCHMODE_ASSOC);
           }
       } else {
           echo "<P>DB error running query";
           print_r($rs);
       }
       return $result;
    }

    /**
     * Raises alarms (puts entry in table)
     *
     * @returns true if this was the first time it was raised
     */
    function raiseAlarm($serial, $temp, $type, $description) {
        $wasNew = false;

        //check that it's already set
        $sqlRead = 'SELECT alarm_id from ';
        $sqlRead .= $this->_params['table_alarms'];
        $sqlRead .= ' where serialnumber = ? and alarm_type = ? ';
        $sqlRead .= ' and time_cleared is null ';

        $ps = $this->_db->prepare($sqlRead);
        $rs = $ps->execute(array($serial, $type));

        if (!PEAR::isError($rs)) {
            if ($rs->numRows() > 0) {
                echo "updating alarm $type";
                //already in table, update
                $row = $rs->fetchRow(MDB2_FETCHMODE_ORDERED);
                $id = $row[0];
                

                $sqlUpdate = 'UPDATE ';
                $sqlUpdate .=  $this->_params['table_alarms'];  
                $sqlUpdate .= ' set fahrenheit = ? ' ;
                //$sqlUpdate .= ' and time_updated = null ';
                $sqlUpdate .= ' , description = ? ';
                $sqlUpdate .= ' where alarm_id = ? ';
                echo "<BR>Running $sqlUpdate (temp = $temp)";
                $ps = $this->_db->prepare($sqlUpdate);
                Utils::checkError($ps, $sqlUpdate);
                $ps->execute(array( $temp, $description, $id ));


            } else {
                echo "inserting alarm $type";
                //not yet in table, insert
                $sqlInsert = ' INSERT into ';
                $sqlInsert .=  $this->_params['table_alarms'];
                $sqlInsert .= ' (serialnumber, fahrenheit, alarm_type, time_raised, description) ';
                $sqlInsert .= ' VALUES (?, ?, ?, now(), ?)';

                $ps = $this->_db->prepare($sqlInsert);
                Utils::checkError($ps, $sqlInsert);
                $ps->execute(array($serial, $temp, $type,  $description));

                //hope all was well
                $wasNew = true;

            }
        }
        return $wasNew;

    }

    /**
     * clears all alarms with the given type
     * for given serial
     */
    function clearAlarm($serial, $type) {

        $sqlUpdate = 'UPDATE ';
        $sqlUpdate .=  $this->_params['table_alarms'];  
        $sqlUpdate .= ' set time_cleared = now() ' ;
        $sqlUpdate .= ' where serialnumber = ? and alarm_type = ? and time_cleared is NULL';

        $ps = $this->_db->prepare($sqlUpdate);
        if (PEAR::isError($ps)) {
            echo "Error preparing $sqlUpdate ". $ps->getMessage();
        }
        return $ps->execute(array($serial, $type));

    }

    /**
     * Deletes all alarms (active or not)
     * for given serial
     */
    function deleteAlarms($serial) {

        $sqlUpdate = 'DELETE from ';
        $sqlUpdate .=  $this->_params['table_alarms'];  
        $sqlUpdate .= ' where serialnumber = ?';

        $ps = $this->_db->prepare($sqlUpdate);
        return $ps->execute(array($serial));
    }
    

    /**
     * counts alarms (active or not)
     * for given serial
     */
    function countAlarms($serial) {

        $sqlUpdate = 'SELECT count(1) from ';
        $sqlUpdate .=  $this->_params['table_alarms'];  
        $sqlUpdate .= ' where serialnumber = ?';

        $ps = $this->_db->prepare($sqlUpdate);
        $qr = $ps->execute(array($serial));

        $result = 0;
        if (!PEAR::isError($qr)) {
            $row = $qr->fetchRow();
            $result =  array_pop($row);
        }
//        $qr->close();
 //       $ps->close();

        return $result;
    }
    


    /**
     * Attempts to open a persistent connection to the SQL server.
     *
     * @return boolean True.
     */
    function _connect()
    {
        if (!$this->_connected) {

            if (!is_array($this->_params)) {
                Utils::fatal(PEAR::raiseError(_("No configuration information specified for SQL Calendar.")), __FILE__, __LINE__);
            }
            if (!isset($this->_params['phptype'])) {
                Utils::fatal(PEAR::raiseError(_("Required 'phptype' not specified in calendar configuration.")), __FILE__, __LINE__);
            }
            if (!isset($this->_params['hostspec'])) {
                Utils::fatal(PEAR::raiseError(_("Required 'hostspec' not specified in calendar configuration.")), __FILE__, __LINE__);
            }
            if (!isset($this->_params['username'])) {
                Utils::fatal(PEAR::raiseError(_("Required 'username' not specified in calendar configuration.")), __FILE__, __LINE__);
            }
            if (!isset($this->_params['password'])) {
                Utils::fatal(PEAR::raiseError(_("Required 'password' not specified in calendar configuration.")), __FILE__, __LINE__);
            }

            /* Connect to the SQL server using the supplied parameters. */
            //$this->_db = &DB::connect($this->_params, true);
            $dsn = $this->_params['phptype']."://". 
                $this->_params['username'] . ":" .
                $this->_params['password'] . "@" .
                $this->_params['hostspec'] . "/" .
                $this->_params['database'];
            //echo "Connecting to $dsn";

            // we deal with lowercase column names for MDB2:
            $options = array('field_case'=>CASE_LOWER,'persistent'=>true);
            $this->_db = &MDB2::connect($dsn, $options);
            if (PEAR::isError($this->_db)) {
                Utils::fatal($this->_db, __FILE__, __LINE__);
            }

            /* Enable the "portability" option. */
            //$this->_db->setOption('optimize', 'portability');

            $this->_connected = true;

        }

        return true;
    }

    function close()
    {
        $this->_disconnect();
        return true;
    }

    /**
     * Disconnect from the SQL server and clean up the connection.
     *
     * @return boolean true on success, false on failure.
     */
    function _disconnect()
    {
        if ($this->_connected) {
            $this->_connected = false;
            return $this->_db->disconnect();
        }

        return true;
    }



    /**
     * Private helper to make a string representation
     * of array values separated by ,'s
     *  and surrounded by parenthesis (for IN sql clause)
     * @access private
     */
    function makeInList($list) {
        if (is_array($list)) {
            return "'".join("','",$list)."'";
        }
        return "";
    }

    /**
     * Makes a generic WHERE and everything after part
     * of an sql statement
     *
     * Adds sections based on which args are not null,
     * such as "sensor IN ('s1','s2')"
     * and "time < startTime"
     * @access private
     */
    function makeWhereClause($sensors = null, $startDate = null, $endDate = null, $thinOut = false)
    {
        global $conf;
        $started = false;
        $qu = "";
        
        if (isset($sensors) || isset($startDate) || isset($endDate)) {
            $qu .= ' WHERE ';
        }
        
        if (isset($sensors) && is_array($sensors)) {
            $qu .= ' serialnumber in (';
            $qu .= $this->makeInList($sensors);
            $qu .= ')';
            $started = true;
        }
        if (isset($startDate)) {
            if ($started == true) {
                $qu .= ' AND ';
            }
            $qu .= ' time > FROM_UNIXTIME(' . $this->_db->quote($startDate) .')';
            $started = true;
        }

        
        if (isset($endDate)) {
            if ($started == true) {
                $qu .= ' AND ';
            }
            $qu .= ' time < FROM_UNIXTIME(' . $this->_db->quote($endDate). ')';
        }

        if ($thinOut) {
            //echo "Thinning out SQL";
            if (isset($sensors) && is_array($sensors)) {
                $sensorCount = count($sensors);
            } else {
                $sensorCount = count($this->listDistinctSensors());
            }

            $duration = $this->getDuration($startDate, $endDate);
            $thinOutQuery =  $this->makeThinOutQueryComponent($duration, $sensorCount);
            
            if (isset($thinOutQuery)) {
                $qu .= ' AND '.$thinOutQuery;
            }
           //echo "Made $qu";
        }
        return $qu;
    }

    function runTimingQueries() {
        global $stop_watch;
        
        $time = time() - 3600 * 24 * 365;
        
        $query = "SELECT count(*) from digitemp where time > FROM_UNIXTIME($time)";
        $stop_watch['Start'] = microtime();
        $ps = $this->_db->prepare($query);
        $stop_watch['prepared']= microtime();
        $rs = $ps->execute(array($serial, $type));
        $stop_watch['executed one year count']= microtime();

        if (!PEAR::isError($rs)) {
            if ($rs->numRows() > 0) {

               $row = $rs->fetchRow(MDB2_FETCHMODE_ASSOC);
               $stop_watch['got results']= microtime();
               echo "one year count is ";
               print_r($row);
            }
        }

        
        $stop_watch['starting timing actual read'] = microtime();
        $query = "SELECT * from digitemp where time > FROM_UNIXTIME($time)";

        $ps = $this->_db->prepare($query);
        $stop_watch['prepared read']= microtime();
        $rs = $ps->execute(array($serial, $type));
        $stop_watch['executed one year read']= microtime();


        if (!PEAR::isError($rs)) {
            if ($rs->numRows() > 0) {
               $row = $rs->fetchRow(MDB2_FETCHMODE_ASSOC);
               //echo "Got row";
               $count = 0;
               while ($row && !PEAR::isError($row)) {
                   $row = $rs->fetchRow(MDB2_FETCHMODE_ASSOC);
                   //do something with it?
                   $count++;
               }
               $stop_watch['done iterating over results']= microtime();
               echo "read $count results from db";
            }
        }



        Utils::echo_stopwatch();

    }

    /**
     * Basically subtracts the two but 
     * accounts for unset fields 
     */
    function getDuration($startTime, $endTime) {
        if (!isset($startTime)) {
            $startTime = $this->getStartOfData();
        }
        if (!isset($endTime)) {
            $endTime = time();
        }
        return $endTime - $startTime;
    }
    
    /**
     * compares the anticipated number of datapoints
     * to the max from configuration,
     * and returns a factor. If it's >1, thinning out
     * by that much is needed
     * This is useful for the SQL thinOut method
     */
    function calculateThinOutFactor($duration,$numSensors) {
        global $conf;
            
        //base on collectionInterval and maxThinnedOutDataPoints
        //try to make this more exact
        
        $interval =  $conf['data']['collectionInterval'];
        if (!isset($interval)) {
        
            Utils::fatal(PEAR::raiseError(_("Please set the collectionInterval in configuration")), __FILE__, __LINE__);
        }
        //estimating raw point count
        $rawPoints = $numSensors * ($duration / 60) / $interval ;
        $maxPoints = $conf['data']['maxThinnedOutDataPoints'];


        //now break it up into big categories of hours, days, etc and then 
        //ponder making it relatively precise within those bounds 

        $thinOutFactor = $rawPoints / $maxPoints;

        return $thinOutFactor;

    }

    /**
     * This function sets up the thin out part of the 
     * where clause (without the leading AND)
     * based on the configuration parameters 
     * and the duration which should be the seconds 
     * in the requested interval
     * 
     * if none needed, returns null
     */
    function makeThinOutQueryComponent($duration, $numSensors) {
        global $conf;
        $result = null;
        if ($numSensors == 0) {
            return $result;
        }

        $thinOutFactor = $this->calculateThinOutFactor($duration,$numSensors);
            
        if ($thinOutFactor < 1) {
            return $result; //no thinning out needed
        }
        
        $interval =  $conf['data']['collectionInterval'];


        //now break it up into big categories of hours, days, etc and then 
        //ponder making it relatively precise within those bounds 


        //first, restrict minutes 
        $needAnd = false;
        $readingsPerHour = 60 / $interval;

        $alreadyThinned = 1; //this is a factor
        //echo "Numsensors = $numSensors";
        if ($readingsPerHour > 1) {
            //then we thin out readings per hour, possibly to as little as one per
            

            $desiredReadingsPerHour = $readingsPerHour / $thinOutFactor;
            if ($desiredReadingsPerHour < 1) {
                $desiredReadingsPerHour = 1;
            }
            $result .= ' extract(minute from time) < '. (($interval * $desiredReadingsPerHour) ); 
            //so I will only get the first part of an hour unfortunately
            //instead of an even distribution
            $needAnd = true;
            $alreadyThinned = $readingsPerHour / $desiredReadingsPerHour;
        }

        if ($readingsPerHour < $thinOutFactor) { 
            $factor = ($interval > 60) ? (60 / $interval) : 1; 
            //then we need to thin out more, hours I guess
            $readingsPerDay = 24 / $factor; //at this point it's once per hour or less!

            //but we already thinned something out...
            $desiredReadingsPerDay = $readingsPerDay / $thinOutFactor * $alreadyThinned;
            if ($desiredReadingsPerDay < 1) {
                $desiredReadingsPerDay = 1;
            }
            //echo "Doing days based on: $alreadyThinned / $readingsPerDay / $thinOutFactor = $desiredReadingsPerDay , that's for $numSensors sensors ";
            $result .= $needAnd ? ' AND ' : '';
            //correction in case interval is over one hour
            $result .= ' extract(hour from time) < '. ($desiredReadingsPerDay * $factor) ;
        }

        return $result;
        
    }

    /** 
     * Inserts the given temperature
     */
    function logTemp($serial,$temperature) {
        global $conf;

        if ($temperature < $conf['data']['loggerValidTempMin'] || $temperature > $conf['data']['loggerValidTempMax']) {
            return;
        }

        $sql="INSERT INTO digitemp SET SerialNumber=?,Fahrenheit=?";
        $ps = $this->_db->prepare($sql);
        if (PEAR::isError($ps)) {
            echo "Prep error: $sql";
            return;
        }
        $ps->execute(array($serial,$temperature));

        $ps->free();

    }

}
?>
