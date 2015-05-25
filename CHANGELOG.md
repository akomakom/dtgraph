#DTGraph Changelog

Current:
--------------
NOTE: run SQL/mysql_add_metadata_columns1.sql for this update!

NOTE: merge your conf.php for this update, a lot has changed

  * Added getreading.php for getting temperature at time X
  * using buffering to prevent ugly bad image issues when things aren't working
  * Added min/max reading limits to conf.php to avoid bogus sensor readings (eg: 185F for 1-wire network errors)
  * logger.php can now invoke alarms.php for you to eliminate the extra cronjob
  * nagios.php added for integration with nagios monitoring
  * Turned off php strict mode in conf, marked functions static
  * supporting disabled sensors better
 
0.4n
--------------
  * I'm sorry... I am not sure what changed, I lost the svn tag ;(  - could be some of the above.

0.4m
--------------
  * Fixed by-ref error when alarms present
  * Made duration output human-readable ("24 Hours" vs "86400")
  * Added a new script - logsensorstemp.sh (adding data from lm_sensors)
  * Changed wording of some confusing messages
  * Closing db driver after use
  * Added sensor=<NAME> parameter to showlatest.php

0.4b
--------------
  * Fixed MySql 4.1 incompatibilities (timestamp returned as string)
  * Increased alarm temp datatypes to float(6,3) - Mysql4.1 change
  * Fixed JPGraph 2.0 incompatibilities (Pos() method changed to SetPos())
  * Better DB error handling
  * Updated X-axis to appear on the bottom despite sub-zero readings (thanks david_a)


0.4a
--------------
  * Celsius was misspelled (celsius)
  * Deletion of alarms, metadata and readins now in admin page
  * Alarms are now shown on the graph as icons (configurable)
  * Made all code work with php set to NOTICE (used to break)
  * many small bugfixes, and some cleanup
  * there was more stuff, don't remember
  * Fixed end time absolute time interface bug 
    (some of the drop downs had no effect)
  * Configurable prefs cookie lifetime

0.3 01/08/2004
--------------
  * Reworked CODE thinOut method to work right
  * Added AUTO thinOut method
    With mode set to AUTO the graph generation
    is automagically safe - and tries to keep 
    rendering time to a minimum. 
    Default settings set for a fast machine
  * Added cookie support for saving settings
  * migrated away from needing register_globals!


0.2 09/09/2003
--------------
  * Added alarms table
  * Added alarms update/notify script
  * Added alarms display to dtgraph main
  * Many new configuration checkboxes
  * Made compliant with W3C HTML/4.01
  * Made graph image a submit
  * popups onclick for all options

0.1a 04/23/2003
--------------
  * Found a bug with the celcius mode, my bad

0.1 04/22/2003
--------------
Initial Release.
I've been working for some time prior to any release, 
and the following features are present:

  * Showing graphs with JPGraph
  * Showing stats such as min/max/avg/current temp
  * Configuration options, such as:
        stats, repeats, legend, margin, negative scale, OK range, Units
  * Auto query generation to trim graphed data when excessive
  * Adjustable displayed data interval
  * Scripts to notify of alarm conditions, etc

    There is more, I can't remember now 
