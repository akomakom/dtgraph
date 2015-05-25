#!/usr/bin/perl -W

# DigiTemp MySQL logging script
# Copyright 2002 by Brian C. Lane <bcl@brianlane.com>
# All Rights Reserved
#
# This program is free software; you can redistribute it and/or modify it
# under the terms of the GNU General Public License as published by the Free
# Software Foundation; either version 2 of the License, or (at your option)
# any later version.
#
# This program is distributed in the hope that it will be useful, but WITHOUT
# ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
# FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
# more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA
#
# -------------------------[ HISTORY ]-------------------------------------
# 08/18/2002  Putting together this MySQL logging script for the new 
# bcl         release of DigiTemp.
#
# -------------------------------------------------------------------------
#CREATE table digitemp (
#   dtKey int(11) NOT NULL auto_increment,
#   time timestamp NOT NULL,
#   SerialNumber varchar(17) NOT NULL,
#   Fahrenheit decimal(3,2) NOT NULL,
#   PRIMARY KEY (dtKey),
#   KEY serial_key (SerialNumber),
#   KEY time_key (time)
# );

# GRANT SELECT,INSERT ON digitemp.* TO dt_logger@localhost
# IDENTIFIED BY 'VerySecretPwd34';
#
# -------------------------------------------------------------------------
use DBI;


# Database info
my $db_name     = "stats";
my $db_user     = "dtgraph";
my $db_pass     = "";

# The DigiTemp Configuration file to use
my $digitemp_rcfile = "~/.digitemprc";
my $digitemp_binary = "/usr/local/bin/digitemp";


my $debug = 0;
my $var1 = shift;
if (defined($var1) && $var1 eq 'debug') {
    $debug = 1;
    print "Debug mode on\n";
}

# Connect to the database
my $dbh = DBI->connect("dbi:mysql:$db_name","$db_user","$db_pass")
          or die "I cannot connect to dbi:mysql:$db_name as $db_user - $DBI::errstr\n";


# Gather information from DigiTemp
# Read the output from digitemp
# Output in form SerialNumber<SPACE>Temperature in Fahrenheit
open( DIGITEMP, "$digitemp_binary -q -a -o\"%R %.2F\" -c $digitemp_rcfile |" );

while( <DIGITEMP> )
{
  print "$_\n" if($debug);
  chomp;

  ($serialnumber,$temperature) = split(/ /);

  #Safety limits: erroneous data tends to be very high or very low
  #This usually happens when the sensor is having trouble (water, bad connection)
  if ($temperature < -80 || $temperature > 180) {
      print "Erroneous data for $serialnumber: $temperature ... Skipping";
      #continue;
  } else {
      $sql="INSERT INTO digitemp SET SerialNumber='$serialnumber',Fahrenheit=$temperature";
      print "SQL: $sql\n" if($debug);
      $dbh->do($sql) or die "Can't execute statement $sql because: $DBI::errstr";
  }
}

close( DIGITEMP );

$dbh->disconnect;
