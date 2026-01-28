<?php

return [

    /////////////////// Cache Management //////////////////
    // NOTE: cache backend is selected in .env file
    // You can expire cache if you are impatient with something like:
    //   sudo php artisan cache:clear


    // Number of minutes to cache readings read from DB
    // This setting applies to data ranges that are "old" in their entirety, with "old" determined by cache_old_readings_min_age
    // 1440 is one day
    // You can set this to 0 to disable caching of old data
    'cache_old_readings_time' => 1440,

    // Readings at least this old (minutes) are eligible to be cached for cache_old_readings_time
    // with the assumption that they are not likely to change (probably ever)
    // If your old data changes frequently, you can set both _time settings to a low value or 0, or you can
    // change this setting to a very high value to make all readings treated as new.
    'cache_old_readings_min_age' => 30,

    // How long (minutes) to cache readings that are more recent than cache_old_readings_min_age
    // You may wish to adjust this according to your logging interval, so that you see fresh data soon after it arrives.
    // You can set this to 0 to disable caching of new data
    'cache_new_readings_time' => 5,


    // How long the database operation should take to need caching (in seconds)
    // results of any operation that is faster will not be cached
    'cache_min_lookup_threshold' => 0.4,


    // Number of minutes to cache sensor info.
    // Affects when we notice new sensors appearing or old ones being deleted.
    'cache_sensor_info_time' => 60,


    /////////////// Response Data management /////////////////

    //These values reduce the number of rows returned from the database and thus to the browser
    //Since a zoomed out graph doesn't need a lot of detail, don't fetch it - instead return daily/hourly approximation.

    // When requested data range is longer than this (seconds), group resulting data by days
    // 2592000 is 30 days
    // increasing this will make responses larger and slower
    'db_threshold_days' => 2592000,

    // When requested data range is longer than this (seconds), group resulting data by hours
    // 432000 is 5 days
    'db_threshold_hours' => 432000,


    // for the "latest" api endpoint that returns the latest readings for each sensor,
    // how far back to check when looking for the latest row in the database (in seconds).
    // this setting should be larger than the reading interval, but not so large as to produce unnecessary load
    // The purpose is to find the latest reading, so going far back is not useful.
    'latest_duration' => 1800,


    'logger' => [
        'read_temps_command' => 'digitemp -q -a -o"%R %.2F" -c ~/.digitemprc',
        'valid_temp_min' => -100,
        'valid_temp_max' => 180,
    ],

    // For /add requests, if the serialnumber is mac (XX:XX:XX:XX:XX:XX), remove the colons.
    'shorten_serialnumber_if_mac' => true,

    /////////////// MQTT Configuration /////////////////
    
    'mqtt' => [
        // Enable/disable MQTT publishing
        'enabled' => env('MQTT_ENABLED', false),
        
        // MQTT broker connection settings
        'host' => env('MQTT_HOST', 'localhost'),
        'port' => env('MQTT_PORT', 1883),
        'username' => env('MQTT_USERNAME', null),
        'password' => env('MQTT_PASSWORD', null),
        'client_id' => env('MQTT_CLIENT_ID', 'dtgraph'),
        
        // Home Assistant MQTT Discovery
        // See: https://www.home-assistant.io/docs/mqtt/discovery/
        'discovery_prefix' => env('MQTT_DISCOVERY_PREFIX', 'homeassistant'),
        
        // Topic where sensor states are published
        // Format: {state_topic_prefix}/{sensor_id}/{temperature|humidity}
        'state_topic_prefix' => env('MQTT_STATE_TOPIC_PREFIX', 'dtgraph'),
        
        // Availability topic for the dtgraph service
        'availability_topic' => env('MQTT_AVAILABILITY_TOPIC', 'dtgraph/status'),
    ],
];