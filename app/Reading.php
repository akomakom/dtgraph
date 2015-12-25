<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Reading extends Model
{
    protected $table = 'digitemp';
    protected $primaryKey = 'dtKey';
    public $timestamps = false;
    //


    public static function latest($sensor, $daysToGoBack = 1) {
        return DB::select('select SerialNumber, min(fahrenheit) min, max(fahrenheit) max, avg(fahrenheit) avg, max(time + 0) maxtime from digitemp where SerialNumber = ? and time > curdate() - ?', [$sensor, $daysToGoBack]);
    }


    private static function determinePrecision($start, $end) {

        $duration = $end - $start;
        $mode = 'normal';

        //TODO: configure thresholds

        if ($duration > 1209600) { //14 days
            $mode = 'days';
        } else if ($duration > 86400) {
            //use hours
            $mode = 'hours';
        }

        return $mode;
    }

    /**
     * Retrieves readings for the given time period
     * Precision (via group by) is automatically determined
     *
     * @param $sensor String required
     * @param $start int unix timestamp
     * @param $end int unix timestamp
     * @param $includeStats boolean  if true, max/min will also be included (only if precision mode is aggregate, ie not normal)
     */
    public static function readings($sensor, $start, $end, $includeStats = false ) {

        $key = sprintf("readings_%s_%s_%s_%s", $sensor, $start, $end, $includeStats ? "stats" : "");
        if (Cache::has($key)) {
            return Cache::get($key);
        }


        $queryExtra = $includeStats ? ', max(Fahrenheit) as max, min(Fahrenheit) as min' : '';

        $mode = self::determinePrecision($start, $end);
        switch ($mode) {
            case 'days':
                $query = "select unix_timestamp(date(time)) as time, avg(Fahrenheit) as Fahrenheit $queryExtra from digitemp where SerialNumber = ? and time BETWEEN from_unixtime(?) and from_unixtime(?) group by date(time) order by date(time)";
                break;
            case 'hours':
                // Round the time to the nearest hour
                $query = "select unix_timestamp(DATE_FORMAT(DATE_ADD(time, INTERVAL 30 MINUTE),'%Y-%m-%d %H:00:00')) as time, avg(Fahrenheit) as Fahrenheit $queryExtra from digitemp where SerialNumber = ? and time BETWEEN from_unixtime(?) and from_unixtime(?) group by SerialNumber, date(time), hour(time) order by date(time), hour(time)";
                break;
            default:
                //This mode does not include min/max (doesn't make sense)
                $query =  "select unix_timestamp(time), Fahrenheit from digitemp where SerialNumber = ? and time BETWEEN from_unixtime(?) and from_unixtime(?)  order by time";
        }

        $result = [ 'mode' => $mode, 'query' => $query, 'data' => DB::select($query, [$sensor, $start, $end]) ];


        //caching...
        $expiresAt = Carbon::now()->addMinutes(config('dtgraph.cache_new_readings_time'));
        if ( (time() - $end) / 60 > config('dtgraph.cache_old_readings_min_age')) {
            //these are entirely old readings
            $expiresAt = Carbon::now()->addMinutes(config('dtgraph.cache_old_readings_time'));
        }

        $result['expires'] = $expiresAt;

        Cache::put($key, $result, $expiresAt);

        return $result;
    }


    // group by day
    //  select date(time), SerialNumber, avg(Fahrenheit),max(Fahrenheit),min(Fahrenheit) from digitemp where time > '20151101' group by SerialNumber, date(time) order by time;

    // group by hour
    //select time, SerialNumber, avg(Fahrenheit),max(Fahrenheit),min(Fahrenheit) from digitemp where time > '20151215' group by SerialNumber, date(time), hour(time) order by time;


    /**
     * @return array of distinct sensor serial numbers for all sensors that have readings
     */
    public static function distinctSensors() {

        $key = "distinct_sensors";
        if (Cache::has($key)) {
            $result = Cache::get($key);
        } else {

            //return Reading::distinct('SerialNumber')->get();
            //return DB::table('digitemp')->select(DB::raw('distinct(SerialNumber) as result'))->value('result');
            $names = DB::select('select distinct(SerialNumber) from digitemp');

            //flatten it out into a simple array
            $result = [];
            foreach ($names as $name) {
                array_push($result, $name->SerialNumber);
            }

            $expiresAt = Carbon::now()->addMinutes(10); //TODO: unharcode
            Cache::put($key, $result, $expiresAt);
        }

        return $result;
    }
}
