#!/bin/sh

############################################################
# Simple shell script to log sensors temperature into digitemp
# MySQL table for display with dtgraph
############################################################

#You can customize the mysql command line here,
#Add -hhostname if you need to, credentials, etc 
MYSQLCOMMAND="mysql -u dtgraph stats"

#List of distinct items to grep for in the output of sensors
#Eg: 
# SENSORLIST="CPU M/B" #Record both CPU and Motherboard temp
# These also become part of the sensor name
# which is constructed using the local hostname, eg:
# CPU_mymachine
SENSORLIST="CPU"

#Override HOSTNAME if desired (bash sets it by default)
#HOSTNAME=youhostname

for SENSOR in $SENSORLIST ; do
  TEMP=`sensors -f | grep $SENSOR | awk '{print $3}'`
  SENSORNAME=$SENSOR'_'$HOSTNAME
  echo "Inserting temp $TEMP for $SENSORNAME"

  QUERY="INSERT INTO digitemp SET SerialNumber='$SENSORNAME',Fahrenheit=$TEMP;"
  echo -e "$QUERY" | $MYSQLCOMMAND
done
