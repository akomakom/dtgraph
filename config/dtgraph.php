<?php

return [
    // Number of minutes to cache readings read from DB
    // This setting applies to data ranges that are "old" in their entirety, with "old" determined by cache_old_readings_min_age
    // 1440 is one day
    'cache_old_readings_time' => 1440,

    // Readings at least this old (minutes) are eligible to be cached for cache_old_readings_time
    // with the assumption that they are not likely to change (probably ever)
    'cache_old_readings_min_age' => 30,

    // How long (minutes) to cache readings that are more recent than cache_old_readings_min_age
    // You may wish to adjust this according to your logging interval, so that you see fresh data soon after it arrives.
    // You can set this to 0 to disable caching of new data
    'cache_new_readings_time' => 5,


];