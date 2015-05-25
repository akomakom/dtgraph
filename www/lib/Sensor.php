<?php
/**
 * Metadata about a sensor
 */

class Sensor {

	var $_id;
	var $_name;
	var $_description;
	
	//Presentation
	var $_color;
	
	//Alarm info
	var $_min;
	var $_max;
	var $_minmaxEnabled = false;
	
	var $_maxchangeAmount;
	var $_maxchangeDuration;
	var $_maxchangEnabled = false;
	
	/**
	* Constructor that takes an associative array 
	* coming out of db ( metadata table)
	*/
	function Sensor($properties) {
		if (!isset($properties) || !is_array($properties)) {
			echo "Bad: given wrong array";
			return;
		}
		$this->_id = $properties['SerialNumber'];
		$this->_name = $properties['name'];
		$this->_description = $properties['description'];		
		$this->_color$properties['color'];		
		
		$this->_min = $properties['min'];
		$this->_max = $properties['max'];
		$this->_minmaxEnabled = ( 1 == $properties['alarm'] );
		
		
		$this->_maxchangeAmount = $properties['maxchange'];
		$this->_maxchangeDuration = $properties['maxchange_interval'];
		$this->_maxchangEnabled = (1 == $properties['maxchange_alarm']);
	}
	

}



?>