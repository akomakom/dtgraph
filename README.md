Dtgraph
-----
This is an in-progress rewrite of the ancient Dtgraph that I wrote in the early 2000s.
This implementation is using an MVC framework (laravel) and quick, dynamic graph rendering (d3).

## Done
* Graphing works, with mouse-zoom, etc.
* Smart group by data thinning depending on zoom level.

## Not Done
* No UI for managing digitemp_metadata (no admin UI to configure sensors)
* Fast zooming and panning sometimes breaks (requires reload)

# Requirements
1. PHP + Web Server
1. Composer

# Installation

### Web

NOTE: if you are on shared hosting and can't open a shell, you can run composer locally and upload the result.

These instructions are a concise summary of the general Laravel framework installation:

1. Unzip to a directory under your web root
1. Run "composer install" in the directory to get all dependencies.
   * Any errors about missing PHP extensions will need to be resolved before continuing.  Try to install these extensions via your normal package manager.
1. Make your web server aware of this app, if needed using the method of your choosing (ie for Apache: "Alias /dtgraph /dir/of/dtgraph/public", and you may need a <Directory> section to relax your permissions, depending on your overall configuration)
1. File Permissions:
   * Writable: storage/ subdirectory ("chmod -R a+w storage" or chown to your web server user)

### Backend

1. DB initialization uses artisan:
   php artisan migrate:install (see Configuration first)
   TBD: Write migration for main tables too.

1. Putting temperature data into your Database:
    * From Command Line
       * A wrapper is provided for logging temperatures from digitemp, used like this:
        **php artisan dtgraph:logdigitemp** (see config/dtgraph.php for command that is run).
        It is your responsibility to get digitemp working before you get to this step.
       * A generic command to log arbitrary data:
        **php artisan dtgraph:logtemp** (try 'php artisan help dtgraph:logtemp' for options)
    * Via HTTP
        * An API endpoint exists for adding readings via HTTP, eg:
            ```
            /dtgraph/api/add/[SENSOR_NAME]?temperature=[TEMPERATURE]&unit=C&delta_seconds=[AGE_OF_READING]
            ```
          (Only temperature is required)


# Configuration

* Database configuration should go into **.env** in the root directory (no need to change config/database.php). Use **.env.example** as a reference


# URL changes
 This may be important to you if you've built tools or monitoring around old urls:
* showlatest.php -> api/latest?format=txt
* showlatest.php?sensor=X -> api/latest/X?format=txt