<?phP 
    //requires $alarms array to be set!
    reset($alarms);
?>
<TABLE border="1">
<TR><TH>&nbsp;</TH>
    <TH>Sensor</TH>
    <TH>Temp</TH>
    <TH>Alarm Type</TH>
    <TH>Time Raised</TH>
    <TH>Last Update</TH>
    <?php
        if (isset($specialVarShowClearedAlarms)) {
            echo "<TH>Cleared</TH>";
        }
    ?>
    <TH>Description</TH>
</TR>
<?php
    $count = 0;
    while (list($serial, $alarm) = each ($alarms)) {
        while (list($id, $alarm) = each($alarms[$serial])) {
            $count++;
            //$text .= $s['name'].':'.$alarm['description'].'. Active since '.$alarm['time_raised'];
            //append each alarm
            $active = !isset($alarm['time_cleared']);
            echo '<TR>';
            if ($active) { 
                echo UTILS::makeTD('Alarm', 'Active!', null, 'icon-warning.gif');
            } else {
                echo '<TD>&nbsp;</TD>';
            }
            echo '<TD>'.UTILS::color($list[$serial]['name'], $list[$serial]['color']);
            echo '</TD><TD>';
            if (isset($toggleUnits)) {
                echo Utils::myRound(Utils::toCelsius($alarm['fahrenheit']),2).'C';
            } else {
                echo $alarm['fahrenheit'].'F';
            }
            echo '</TD><TD>'.$alarm['alarm_type'].'</TD><TD>'.date($conf['alarms']['dateformat'], $alarm['time_raised']).'</TD><TD>'.date($conf['alarms']['dateformat'], $alarm['time_updated']).'</TD><TD>';
            if (isset($specialVarShowClearedAlarms)) {
                if ($active) {
                    echo UTILS::color('Active!', 'red');
                } else {
                    echo date($conf['alarms']['dateformat'], $alarm['time_cleared']);
                }
                echo '</TD><TD>';
            }
            
            echo $alarm['description']."</TD></TR>\n";
        
        }
        //echo makeTD($content, $text, 'red', 'icon-warning.gif');
    }
   
?>
</TABLE>

<?php echo $count; ?> Alarms diplayed
