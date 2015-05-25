<?php
// If you need to move this file, update the below to the right relative path:
    $dtDir = dirname(__FILE__).'/../'; //may have to adjust if you move admin directory contents
    require_once($dtDir.'conf.php');
    require_once($dtDir.'Driver.php');
    require_once($dtDir.'utils.php');


    
    $driver = &DTtemp_Driver::factory($conf['driver'], $conf['sql']);
    $driver->connect();  //must call to connect

    $command = $conf['digitemp_binary'].' -q -a -o"%R %.2F" -c '.$conf['digitemp_config'];
    $reply = shell_exec($command);
    $reply_array = explode ("\n",$reply);
    foreach($reply_array as $line) { 
        if (trim($line) == '') {
            continue; //don't need blank lines
        }
        $sensor=explode(' ',$line);
        $serialnumber=$sensor[0];
        $temperature=$sensor[1];
        if ($serialnumber != '' and is_numeric($temperature)) { 
            echo "Inserting $serialnumber, $temperature\n";
            $driver->logTemp($serialnumber,$temperature);
        } else {
            echo "Invalid line from ".$conf['digitemp_binary']. ":\n$line"; 
        }
    } 

    
if ($conf['alarms']['onLogger']) { 
    require_once($dtDir.'/admin/alarms.php');
}
    
?>
