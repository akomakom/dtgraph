--
-- Table structure for table 'digitemp_metadata'
-- 
--

CREATE TABLE digitemp_metadata (
  SerialNumber varchar(17) NOT NULL default '',
  name varchar(15) NOT NULL default '',
  description varchar(255) default NULL,
  min float(6,3) default NULL,
  max float(6,3) default NULL,
  alarm tinyint(1) unsigned NOT NULL default '0',
  maxchange float(6,3) unsigned default NULL,
  maxchange_interval int(10) unsigned NOT NULL default '3600',
  maxchange_alarm tinyint(1) unsigned NOT NULL default '0',
  color varchar(15) NOT NULL default 'black',
  active tinyint(1) unsigned NOT NULL default '1',
  PRIMARY KEY  (SerialNumber)
) ;

