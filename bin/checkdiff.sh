#!/bin/sh

if [ $# -lt 3 ] 
then
    echo "Checks for an alarm condition where two sensors have too great a difference"
    echo "This script is a one-off, it should be integrated into the php code of the app"
    echo "It uses the front end to retrieve data via http"
    echo ""
    echo "Usage $0 SENSOR1_NAME SENSOR2_NAME MAX_DIFFERENCE [ URL_OF_SHOWLATEST ]"
    exit 1
fi

SENSOR1=$1
SENSOR2=$2
MAXDIFFERENCE=$3
URL=http://localhost/dtgraph/showlatest.php
if [ -n "$4" ] ; then URL=$4 ; fi

echo "CHecking difference between $1 and $2, maximum is $MAXDIFFERENCE. Using URL: $URL"

TEMP1=`wget -q $URL -O - | grep -v "ALARM" | grep "$SENSOR1" | cut -d":" -f2`
TEMP2=`wget -q $URL -O - | grep -v "ALARM" | grep "$SENSOR2" | cut -d":" -f2`

#echo "Temps are $TEMP1 and $TEMP2"

if [ -z "$TEMP1" ] || [ "$TEMP1" = "?" ] ; then echo "Temp1 bad: $TEMP1" ; exit 1 ; fi
if [ -z "$TEMP2" ] || [ "$TEMP2" = "?" ] ; then echo "Temp2 bad: $TEMP2" ; exit 1 ; fi

DIFF=`echo "$TEMP1 - $TEMP2" | bc | cut -d "-" -f2 | cut -d "." -f1` #can only work on integers
if [ "$DIFF" == "" ] ; then DIFF="0" ; fi #IN case it's between -1/+1
if [ $DIFF -gt $MAXDIFFERENCE ] 
then
    echo "Excessive difference ($DIFF) between $SENSOR1 ($TEMP1) and $SENSOR2 ($TEMP2)"
    #Insert ALARM action here
    exit 2
else
    echo "Tolerable difference ($DIFF) between $SENSOR1 ($TEMP1) and $SENSOR2 ($TEMP2)"
    exit 0
fi
