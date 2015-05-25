<?php

    /**
     * One instance of an alarm
     */
    class Alarm {
        
		var $_id;
		
		//serial for which the alarm was raised
		var $_serial;
		
		//Latest temp for this alarm
		var $_fahrenheit;
		
		var $_timeRaised;
		var $_timeCleared;
		var $_timeUpdated;
		
		var $_alarmType;
		var $_description;
		
		/**
		* Constructor to parse out the array components
		* into fields
		*/
		function Alarm($props) {
		
			if (!isset($props) || !is_array($props)) {
				echo "Error: given empty alarm props";
				return;
			}
		
			$this->_id = $props['alarm_id'];
			$this->_serial  = $props['serialnumber'];
			$this->_description  = $props['description'];
			
			$this->_fahrenheit  = $props['fahrenheit'];

			$this->_timeRaised  = $props['time_raised'];
			
			if (isset($props['time_cleared'])) {
				$this->_timeCleared  = $props['time_cleared'];
			}
			
			$this->_timeUpdated = $props['time_updated'];
			$this->_alarmType  = $props['alarm_type'];
		}

		/**
		* If there is no time_cleared, then it's active
		*/
		function isActive() {
			return (!isset($this->_timeCleared));
		}
		
    }

?>
