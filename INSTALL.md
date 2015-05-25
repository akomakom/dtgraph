# Installation instructions for dtgraph

FILES and their descriptions:
----------------
### Web Interface  (www/):
  * index.php 
        This is the main graph displayer page. All controls, and look and feel are here
  * graph.php
        index.php will refer to this to show the actual image.
  * conf(-DIST).php
        All PHP files use this for configuration values. You will find graphics,
        html, database, data, alarms, etc configuration in here
	copy conf-DIST.php to conf.php and edit to suit your needs.
  * mobile.php
        A very simple script for accessing with a limited capability device,
        such as cell phone browser, etc. Try it with a normal browser first
        See below for caveats on using this
  * getreading.php
	Think of this as a simple API integration point - get a single reading for one 
	or more sensors, given the time, ie What was the temperature at time X?
  * admin/ directory:
        The scripts here are not meant to be exposed to casual users
        but rather to the administrator only.  (and to cron jobs)
        Protecting this with an .htaccess file or some other means is advisable,
        and naturally SSL is best.
        A sample .htaccess file is provided, setup to require a 
        /var/www/.htpasswd with a password for username "dt"
        See below for files in here and what they can do.
  * admin/admin.php
        A convenient DTGraph administration tool. This helps you name and describe the 
        sensors as well as any alarms for temperature problems.
        This tool will also let you delete sensor metadata, alarms and readings.
        Note if you move this, you'll need to edit it to make sure it can find 
        the required php files in "require_once" method calls
  * admin/alarms.php
        This script is designed to update alarms.  It should be run periodically
        for the alarms table to get updated.  
  * admin/logger.php  (Inserts temperature readings) (thanks grek_pg)
        New logger script in pure PHP, replaces bin/digitemp_mysql.pl that 
        was used to insert new readings by getting them from digitemp binary
        This can also invoke admin/alarms.php for you, see conf.php
  * admin/nagios.php
        Script for nagios monitoring.  View script for info

### Offline command line scripts (These are optional utilities):
  * bin/mydigitemp.pl
        This is a convenience wrapper around digitemp binary which makes use of the
        added digitemp_metadata table to display sensor names with their readings.
        This is a command line tool and must be configured separately (edit it)
  * www/showlatest.php 
        This is similar to mydigitemp.pl in a way, but instead of running the 
        digitemp binary to query sensors, it tries to get the latest readings from DB
        and thus runs very quickly.  I use this script for my text-to-speech engine
        from command line like this:
        php /path/to/showlatest.php | grep Outside > /dev/speech
        (Outside is the name of my outside sensor)
        It can be used via the web server as well, but it's not much different from mobile.php
        It is really a copy of mobile.php without the HTML

### Database Initialization Files (SQL/):
   mysql*
        The individual schema files help you create necessary tables if you don't already
        have them.  One file per table.  Use the one(s) for which you don't have tables created.
        See the Database section below for instructions.  
        


REQUIREMENTS:
----------------------------------------------
  Web server with
  * PHP support with PEAR MDB2 modules installed (php-cli helpful)
  * MySQL (or other database with slight modifications)
  * Some means of logging temperature to MySQL (eg www/admin/logger.php is provided but you may want something different)
  * JPGRAPH (version 1.4 or later may be needed) - to produce temperature graphs
  * Perl for some of the optional utilities

**********************************************************
************ INSTALLATION PROCEDURE **********************

Step 0:  Get your temperature readings working
-----------------------------------------------
This installation assumes that you have something 
working which reads temperatures.  If you have a 
1-wire interface with one or more temp sensors, then I 
hope that you got it working and you can read temps with some application.

If you are using digitemp, use www/admin/logger.php
to insert readings into DB - run it on cron (eg: php /path/to/logger.php)
or via web if you can do that securely (wget or curl on cron)

Alternatively there is an old Perl script in bin/ subdir
"digitemp_mysql.pl" 

If you are using a data logging system of your own, you'll need to
write a script to insert data into the readings 
table (record temps). See below for database info



Step 1:  Get your database setup (very detailed)
-------------------------------------
DTGraph currently uses three tables: 

 -digitemp is the main readings table
 -digitemp_metadata is a table which describes each unique sensor (this table is managed by admin/admin.php)
 -digitemp_alarms contains alarms if you enable this functionality


Use phpMyAdmin or a DB tool of your choice to perform DB manipulations.
No DB tool? Use mysql shell with 
    mysql -u <username> -p stats 
    (read the mysql man page for details on using the mysql command line)

A) Create a database for your dtgraph tables if 
it doesn't already exist (I named mine "stats") with:

    mysqladmin create stats
	# Check www/conf.php for matching database, username, password!

B) Create TABLES
You will find the structure in the *.sql files in the SQL/ directory.
You can paste the contents to mysql or run something like:

    mysql -u <username> -p stats < mysql_create_digitemp_metadata.sql 
    mysql -u <username> -p stats < mysql_create_digitemp.sql
    mysql -u <username> -p stats < mysql_create_digitemp_alarms.sql

(You might need to change these commands to supply user/password for mysql:
    mysql -u <username> -p stats < whatever.sql
this will prompt you for the password.  You should read about mysql and managing it
if you have trouble here)

C) GRANT access to these tables to a username of your 
choosing: 

Note that there are two main purposes in accessing the tables:
a) Inserting records (from sensors, etc)
b) Reading data (to display in dtgraph, cmd-line tools, etc)
c) For simplicity, all the PHP scripts use the same MySQL user account (to read and insert), eg:

    mysql> GRANT SELECT,INSERT,UPDATE ON digitemp.* TO dt_logger@localhost
    IDENTIFIED BY 'ChangeThisPassword';


Now that your tables are setup, don't forget to modify conf.php to reflect your
choice of names for database, user/pass, host, etc


Step 2: Get your logging setup
-----------------------------------
Naturally you need to log data to display it. 
By logging I mean having your current temp readings go into the
digitemp table.

If you are already logging stuff to a table with the correct structure,
then you probably skipped the above section.  

This is good and bad.
You probably have only one of the above tables. Go back and create the
digitemp_metadata and digitemp_alarms tables as well

Now to log...
If you are using digitemp, then see Step 0 of this file.

If you're not using digitemp... well you'll need to either modify that script
or write your own for logging something of your particular preference.

So now that you've gotten your working script into your crontab and you see that
entries are happily showing up in the tables, it's time to move on.

(Note: if you don't already have an interactive DB management tool, phpMyAdmin
is highly recommended - find it on freshmeat)


Step 3: Setting up DTGraph
---------------------------------------------
Copy the contents of the web (www) folder into a directory accesible by your webserver
(ie something under /var/www/html/ (or whatever your DOCROOT is)
or maybe even your user's ~/public_html)
don't forget directories, ie:

    dtgraph/> mkdir /var/www/html/dtgraph
    dtgraph/> cp -r www/* /var/www/html/dtgraph

Set permissions to allow read, and edit the conf.php file.

THIS IS IMPORTANT: edit the conf.php file! At least set jpgraph path!

To get off the ground you will need to make sure that your db settings are 
correct, including host, user/pass, db/table names, etc.

Once that's done, try to hit the url of the directory 
(this of course assumes that your webserver has index.php on the list of 
default filenames.  If you think that's not the case, try adding /index.php)

If you have problems here then most likely you don't have something setup
quite right for accessing the DB (see error message). 

Note that you should password protect the admin/ subdirectory as the script
there allows the user to modify contents of the digitemp_metadata table.
A simple .htpaccess file is there but you need to edit it to point to 
your own .htpasswd file. If you don't know how to use these, google for it.

If it's working then proceed to


Step 4: Setting up Metadata (optional but very useful)
----------------------------------------
I wrote a convenient interface for setting up data about data (metadata)
for each sensor, quickly.
Such data includes:
Name, description, color, alarms, etc

The php script is called admin.php and you can access it in your browser to 
view/update the metadata.  You can set it as well with a DB visualizing tool,
but this is a bit more targeted.  admin.php will allow one to describe any 
sensors for which there are readings stored in the digitemp table.
See warnings about protecting this script at top of this file

Access it at (your url will vary)
http://your.machine/dtgraph/admin/admin.php

Note that each sensor is assumed to have a unique serial number or id.
If your hardware lacks such id, then you should fake it in your logging 
software to make sure each separate sensor is uniquely identified and each 
data record is stored with that id.
Everything is tied to this ID in both tables and in dtgraph and all utilities.

Use of the script (admin.php) should be self explanatory but the data may not be:
alarm/min/max - those are criteria for raising alarms when temperature is out 
of bounds.  If you don't care about bounds, leave alarm checkbox off for the
sensors that don't need it.

maxchange/maxchange_alarm/maxchange_interval
these are values for raising alarms when a temperature has been too unstable,
as in it has exceeded a range of maxchage in the number of seconds equal to
maxchange_interval.  

For example, for my aquariums I don't want the temperature to change more than
1 degree over the course of one day (unsafe for the fish).  So my maxchange
alarm is set to maxchange=1, maxchange_interval=86400 (that's 3600*24).

If you do not need this, leave the checkboxes off and move along.


If you're asking yourelf "How and when are alarms raised and what happens?", read on.

Step 5: Setting up alarm watch (optional) 
---------------------------------------

    What do you have to do? Make sure that alarms.php runs on a regular basis
    What do I mean by runs?  Short answer: "php alarms.php" (command line)
    NOTE: this is now optional - see conf.php.  If you already run logger.php, 
    that takes care of it.

    This script isn't intended to show anything useful, it just determines
    if an alarm should be raised and does it.  See conf.php for email
    notifications.

    Go into the admin/ subdirectory in your web tree, and run:
    php alarms.php
    Did it work? If there are no errors but you see HTML output which looks normal,
    then you have PHP-commandline (CLI) and you should setup a cronjob to update alarms.
    Simply add something like:
    */15 * * * * cd /your/web/tree/dtgraph/admin ; php alarms.php
    to your crontab.

    Didn't work? Well if you don't want to recompile you php with command line,
    then you'll have to hit the script via the webserver...

    something like:
    lynx --source http://localhost/dtgraph/admin/alarms.php 
    can go into cron?  I guess you will need your login/pass in there too,
    see man lynx for how to pass those in.  Note that linx, wget, etc can be used instead,
    it's only important to get the php script to run. 

    This script updates the digitemp_alarms table with any alarms that are in progress,
    and dtgraph can make use of that.  

    Now, when the script runs, and a new alarm is raised, it can email you with the 
    happy news.  See conf.php for settings to turn this on and email address to send to.
    

Step 6: Mobile access
-------------------------------
If you wish to use mobile.php, note that this is a very simple script
intended to be used from cell phones or any device
with tight screen space and limited capabilities, etc

Caveats: My phone (StarTac 7868w on Verizon service) refused to 
work with anything php generated
until I shut off the encoding header part of Content-type...
that switch is in php.ini and I set it like so:

default_charset = ""

After that (and restarting apache) it was working

(Wow that was a long time ago!)

What the display means:
It either shows 
    sensorName=temp 
on each line or
    sensorName:min-max=avg 
lines (Link at bottom of page)
You can enter the number of hours to show stats for

Remember that mobiles devices like to cache things, so keep an eye on the date
printed at the top of the page (in the title) and reload as necessary.



I guess you're done if you made it this far :)

If you find inaccuracies or lack of clarity in these instructions,
do tell the author.

