Dtgraph Upgrading notes
------------------------

Note: when upgrading from 0.3 to a higher version, spelling of the 
"celsius" option has been fixed, so make sure to update your conf.php

Note: As of version 0.3, register_globals (in php.ini) is not needed for the app to work

When upgrading the following steps need to be taken:
(Unless you just want to drop everything and start from scratch)


1) replace the .php files in your doc tree
    a) Backup your conf.php (or the whole dtgraph/ web directory)
    b) Delete all files in the dtgraph/ web dir
        rm -rf /path/to/dtgraph/*
    c) Repopulate web directory from the www/ dir in the tar.gz
        cp -r www/* /path/to/dtgraph/
    d) Merge your old settings in your backup of conf.php into the new conf.php
        You can simply edit the new conf.php from scratch,
        or diff it with your backup and interactively merge 
        if you prefer - use your editor of choice.
        There are often new settings added to conf.php

2) Table changes, if any, should be made to your database.
    The scripts in SQL/ directory are always the latest,
    but re-running them would mean dropping your tables first,
    which means loosing your data. 
    To avoid this, keep an eye on this section:

    The following changes are known :
    Changed in v0.2:
    -added digitemp_alarms table (v0.2) (Run the create script)
    -expanded digitemp_metadata table. (I think v0.2) (Drop your metadata table and re-run create script)

    Changed in 0.4b: 
    -The alarm data types were not large enough, run this to increase from float(3,3):
    mysql> alter table digitemp_metadata modify column min float(6,3) default NULL;
    mysql> alter table digitemp_metadata modify column max float(6,3) default NULL;

    Changed in 0.4m:
    - The Fahrenheit field was too small and could not go above 100, run if you are upgrading from an earlier version:
    mysql YOUR_OPTIONS YOUR_DBNAME < SQL/mysql_expand_temperature_columns.sql

    When in doubt, your best bet is to drop the metadata table and create a new one.
    You'll have to set up your metadata (colors, names, descriptions, alarms) from scratch
    but you'll save yourself the trouble of working with SQL.
    If you're good with SQL, you don't need my instructions.



3) Check for scripts that changed and should no longer 
    be used (eg in cron jobs, etc)
    -version 0.2 added the alarms.php script that should be run
    via php or php-cli in favor of the old digitemp_watcher.pl ... 
    (See INSTALL)


