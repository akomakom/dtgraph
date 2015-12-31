<?php

return [

    /////////////////// Cache Management //////////////////
    // NOTE: cache backend is selected in .env file

    // Number of minutes to cache readings read from DB
    // This setting applies to data ranges that are "old" in their entirety, with "old" determined by cache_old_readings_min_age
    // 1440 is one day
    // You can set this to 0 to disable caching of old data
    'cache_old_readings_time' => 1440,

    // Readings at least this old (minutes) are eligible to be cached for cache_old_readings_time
    // with the assumption that they are not likely to change (probably ever)
    // If your old data changes frequently, you can set both time settings to a low value or 0, or you can
    // change this setting to a very high value to make all readings treated as new.
    'cache_old_readings_min_age' => 30,

    // How long (minutes) to cache readings that are more recent than cache_old_readings_min_age
    // You may wish to adjust this according to your logging interval, so that you see fresh data soon after it arrives.
    // You can set this to 0 to disable caching of new data
    'cache_new_readings_time' => 5,


    // How long the database operation should take to need caching (in seconds)
    // any operation that's faster will not be cached
    'cache_min_lookup_threshold' => 0.4,


    /////////////// Response Data management /////////////////

    // When requested data range is longer than this (seconds), group resulting data by days
    // 1209600 is 14 days
    // increasing this will make responses larger and slower
    'db_threshold_days' => 1209600,

    // When requested data range is longer than this (seconds), group resulting data by hours
    // 86400 is one day
    'db_threshold_hours' => 86400,



    // for the "latest" api endpoint that returns the latest readings for each sensor,
    // how far back to check when looking for latest (in days).
    'latest_days_to_check' => 1,


];