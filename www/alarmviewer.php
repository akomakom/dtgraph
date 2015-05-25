<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
<HEAD>
    <meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
    <TITLE>DTTemp Alarm Viewer</TITLE>
</HEAD>
<BODY>
<?php
    require_once('conf.php');
    require_once('Driver.php');
    require_once('utils.php');

    //register globals...
    Utils::myRegisterGlobals();
    
    $driver = &DTtemp_Driver::factory($conf['driver'], $conf['sql']);
    $driver->connect();  //must call to connect

    $startOfData =  $driver->getStartOfData();
    //$times = Utils::getDisplayDataTimesAbs($startTime, $endTime);

    $times = Utils::getDisplayDataTimesAutomatically($conf['data']['alarmsDefaultOffset']);

    if (isset($activeOnly) && $activeOnly == '1') {
        $activeOnly = true;
        $alarms=$driver->getActiveAlarms($times['startTime'], $times['endTime']);
    } else {
        $activeOnly = false;
        $alarms=$driver->getAllAlarms($times['startTime'], $times['endTime']);
    }

?>
    <FORM action="<?php echo $_SERVER['PHP_SELF'] ; ?>" method="post">
    <TABLE border="1" bgcolor="#DDDDDD">
        <TR>
            <TD><input type="Submit" value="Show"></TD>
            <TD>
                <?php include 'dateSelector.php'; ?>
            </TD>
            <TD><input type="Submit" value="Show"></TD>
        </TR>
        <TR>
            <TD align="center" colspan="3">
            <?php echo Utils::makeSingleNoTDCheckBox('activeOnly', 1, $activeOnly, 'Active Only', 'Only show alarms that are still active in the list','blue'); ?>
            <?php echo Utils::makeSingleNoTDCheckBox('datesAbsolute', 1, $datesAbsolute, 'Absolute Dates', 'Use Absolute Dates/Times rather than relative', 'blue'); ?>
            </TD>
        </TR>

    </TABLE>
    </FORM>


<?php

    $list = $driver->listSensors();

    $specialVarShowClearedAlarms=true;
    $count = count($alarms);
    if ($count > 0) {
        require('alarmtable.php');
    } else {
        echo '<H2>No alarms at this time</H2> (in the selected timeframe)';
    }
?>
</BODY>
</HTML>
