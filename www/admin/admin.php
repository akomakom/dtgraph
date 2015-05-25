<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
    <HEAD>
        <meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
        <link rel="shortcut icon" href="images/icon-dtgraph.png" />
        <TITLE>Admin DTGraph Metadata</TITLE>
        <style>
            td.delete { text-align:center; background: #FF9999; }
        </style>
    </HEAD>
    <BODY>
        <!-- DRAW existing table -->
        <FORM action="<?php echo $PHP_SELF ?>" method="POST">
        <?php
            $dtDir = dirname(__FILE__).'/../'; //may have to adjust if you move admin directory contents

            require_once($dtDir.'conf.php');
            //require_once($dtDir.'utils.php');
            require_once($dtDir.'Driver.php');

            require_once($dtDir.'utils.php');
            Utils::myRegisterGlobals(array('delete', 'deleteMetadata', 'deleteAlarms', 'total', 'update'));
            
            $driver = &DTtemp_Driver::factory($conf['driver'], $conf['sql']);
            $driver->connect();
            
            //$driver->updateMetadata('2322', 'testing', "junky, ignore", 1, 23, 34, 1, 78.3, 600, 'brown');

            //First, read submitted info to see if there is anything to do.
            //check if a delete is pressed:

            $hadAction = false;

            if (isset($delete)) {
                $hadAction = true;
                $result = $driver->delete($delete);
                if ($result == true) {
                    echo "Success: Deleted all readings for $delete";
                    echo "&nbsp; <SMALL>Note: you can delete metadata if this sensor is permanently gone</SMALL>";
                } else {
                    echo "Failed to delete readings : $result";
                }
            }
            if (isset($deleteMetadata)) {
                $hadAction = true;
                $result = $driver->deleteMetadata($deleteMetadata);
                if ($result == true) {
                    echo "Success: Deleted metadata for $deleteMetadata";
                    echo "&nbsp; <SMALL>Note: Sensor will always show if there are any readings for it</SMALL>";
                } else {
                    echo "Failed to delete metadata: $result";
                }
            }

            if (isset($deleteAlarms)) {
                $hadAction = true;
                $result = $driver->deleteAlarms($deleteAlarms);
                if ($result == true) {
                    echo "Success: Deleted all alarm history for  $deleteAlarms";
                    echo "&nbsp; <SMALL>Note: if alarms are enabled for this sensor, new alarms can still be raised.</SMALL>";
                } else {
                    echo "Failed to delete alarm history: $result";
                }
            }

            if (isset($total) && isset($update)) {
                $hadAction = true;
                $counter = 0;
                    echo "Committing changes... ";
                while ($counter < $total) {
                    $thisser = "SerialNumber$counter";
                    //echo "Need to process: ".$$thisser;
                    echo "$counter ";
                    $thisname = "name$counter";
                    $thisdesc = "description$counter";
                    $thiscolor = "color$counter";
                    $thisalarm = "alarm$counter";
                    $thismin   = "min$counter";
                    $thismax   = "max$counter";
                    $thismaxchange_alarm   = "maxchange_alarm$counter";
                    $thismaxchange   = "maxchange$counter";
                    $thismaxchange_int   = "maxchange_interval$counter";
                    $thisactive   = "active$counter";
                    
                    $counter++;

                    $driver->updateMetadata(
                        $_REQUEST[$thisser],
                        $_REQUEST[$thisname],
                        $_REQUEST[$thisdesc],
                        ($_REQUEST[$thisalarm] == '1') ? 1 : 0,
                        $_REQUEST[$thismin],
                        $_REQUEST[$thismax],
                        ($_REQUEST[$thismaxchange_alarm] == 1) ? 1 : 0,
                        $_REQUEST[$thismaxchange],
                        $_REQUEST[$thismaxchange_int],
                        $_REQUEST[$thiscolor],
                        $_REQUEST[$thisactive]);
                }
                unset($counter);
            }

            if ($hadAction) {
                echo "<P><A HREF=\"".$_SERVER['PHP_SELF']."\">Refresh View</A>";
            }
            
        ?>


        <TABLE border="1" cellpadding="0" cellspacing="0">
            <?php
            $sensors = $driver->listSensors();

            $counter = 0;
            $alarmCounter = 0;
            $readingCounter = 0;
            
            $colNames = array('SerialNumber', 'name','description','color','Active','Range Alarm','Min','Max','Max Change Alarm','Max Change Amount','Max Change Inteval (seconds)','Readings', 'Metadata', 'Alarm History');
            echo makeTableHeader($colNames);

            while(list($sensor, $data) = each($sensors)) {
                //echo "$sensor";
                echo "<TR>";
                echo "<TD bgcolor='#EEEEEE'>$sensor ";
                echo makeHiddenField("SerialNumber$counter", $sensor); //must have hidden field in a TD
                echo "</TD>";
                echo '<TD>'.makeInputField("name$counter", $data['name']).'</TD>';
                //echo '<TD>'.makeInputField("description$counter", $data['description'], 40).'</TD>';
                echo '<TD>'.makeTextArea("description$counter", $data['description']).'</TD>';
                echo '<TD bgcolor='.$data['color'].'>*'.makeInputField("color$counter", $data['color']).'</TD>';
                echo '<TD>'.makeCheckBox("active$counter", $data['active']).'</TD>';
                echo '<TD>'.makeCheckBox("alarm$counter", $data['alarm']).'</TD>';
                echo '<TD>'.makeInputField("min$counter", $data['min'],5).'</TD>';
                echo '<TD>'.makeInputField("max$counter", $data['max'],5).'</TD>';
                    
                echo '<TD>'.makeCheckBox("maxchange_alarm$counter", $data['maxchange_alarm']).'</TD>';
                echo '<TD>'.makeInputField("maxchange$counter", $data['maxchange'], 5).'</TD>';
                echo '<TD>'.makeInputField("maxchange_interval$counter", $data['maxchange_interval'], 6).'</TD>';


                $readingCount = $driver->countEvents(array($sensor));
                $readingCounter += $readingCount;
                echo '<TD class="delete">'.makeDelete($sensor, $readingCount).'</TD>';

                echo '<TD class="delete">'.makeDeleteMetadata($sensor).'</TD>';

                $alarmCount = $driver->countAlarms($sensor);
                $alarmCounter += $alarmCount;
                echo '<TD class="delete">'.makeDeleteAlarms($sensor, $alarmCount).'</TD>';
                echo '</TR>';
                $counter++;
            }
            
        ?>
        <TR>
            <TD>
                <INPUT type="submit" name="update" value="Update (Write Changes)">
            </TD>
            <TD colspan="99" align="right">
                Readings: <?=$readingCounter?>
                Alarms: <?=$alarmCounter?>
            </TD>
        </TR>
                
        </TABLE>
        <INPUT type="hidden" name="total" value="<?php echo $counter; ?>">
        <BR>
        Note: What you see above are sensors that have readings or metadata. To add entries, get readings.  This eliminates serial number mismatch issues
        <BR>
        Delete Warning:  No confirmation is requested when clicking the delete buttons. 
        <BR>
        Note: Delete Metadata button removes the metadata entry for the given sensor (everything you see above) but not its readings.
        </FORM>

    </BODY>
</HTML>

<?php


    function makeTableHeader($colnames = array()) {
        $result = "<TR bgcolor='#DDDDDD'>";
        while(list($index, $name) = each($colnames)) {
            $result .= "<TH>$name</TH>\n";
        }
        $result .= "</TR>";
        return $result;
    }

    function makeInputField($name, $value, $size="15") {
        return "<INPUT type='text' name='$name' value='$value' size='$size'>\n";
    }

    function makeTextArea($name, $value, $width = 25, $height = 2) {
        return "<TEXTAREA name='$name' rows='$height' cols='$width'>$value</TEXTAREA>\n";
    }

    function makeCheckBox($name, $state = 0) {
        return "<INPUT type='checkbox' name='$name' value='1'".(($state == 1) ? ' CHECKED ' : '').">\n";
    }

    function makeHiddenField($name, $value) {
        return "<INPUT type='hidden' name='$name' value='$value'>\n";
    }

    function makeDeleteMetadata($serial) {
        return "<A href='admin.php?deleteMetadata=$serial'>Delete</A>";
    }

    function makeDeleteAlarms($serial, $count = '?') {
        $result = "($count) ";
        if ($count > 0) {
            $result .=  "<A href='admin.php?deleteAlarms=$serial'>Delete</A>";
        }
        return $result;
    }

    function makeDelete($serial, $count = '?') {
        $result = "($count) ";
        if ($count > 0) {
            $result .=  "<A href='admin.php?delete=$serial'>Delete</A>";
        }
        return $result;
    }
?>
        
