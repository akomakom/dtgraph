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


    public static function latest($sensor, $daysToGoBack = null) {
        if (!isset($daysToGoBack)) {
            $daysToGoBack = config('dtgraph.latest_days_to_check', 1);
        }
        return DB::select('select SerialNumber, min(fahrenheit) min, max(fahrenheit) max, avg(fahrenheit) avg, max(time + 0) maxtime from digitemp where SerialNumber = ? and time > curdate() - ?', [$sensor, $daysToGoBack]);
    }


    private static function determinePrecision($start, $end) {

        $duration = $end - $start;
        $mode = 'normal';

        if ($duration > config('dtgraph.db_threshold_days')) {
            $mode = 'days';
        } else if ($duration > config('dtgraph.db_threshold_hours')) {
            //use hours
            $mode = 'hours';
        }

        return $mode;
    }


    /**
     * Rounds off the timestamp given to the appropriate level of precision.
     * This way cached keys will still work when the time range differences are insignificant.
     *
     * @param int $timestamp
     * @param string $precisionMode
     * @return int modified timestamp
     */
    private static function roundTimestamp($timestamp, $precisionMode) {
        $date = new \DateTime();
        $date->setTimestamp($timestamp);

        switch($precisionMode) {
            case 'days':
                $date->modify('+12 hours');
                $date->setTime(0,0,0);
                break;
            case 'hours':
                $date->modify('+30 minutes'); //for rounding to the nearest hour
                $date->setTime($date->format('H'), 0, 0);
                break;
            default:
                $date->modify('+30 seconds');
                $date->setTime($date->format('H'), $date->format('i'), 0);
                break;
        }
        return $date->getTimestamp();
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
        $mode = self::determinePrecision($start, $end);

        $key = sprintf("readings_%s_%s_%s_%s", $sensor, self::roundTimestamp($start, $mode), self::roundTimestamp($end, $mode), $includeStats ? "stats" : "");
        if (Cache::has($key)) {
            //TODO: turn cache back on
       //     return Cache::get($key);
        }


        switch ($mode) {
            case 'days':
                $queryExtra = $includeStats ? ', max, min' : '';
                $query = "select unixtime as time, Fahrenheit $queryExtra temp from digitemp_daily where SerialNumber = ? and unixtime BETWEEN ? and ? order by date";
                break;
            case 'hours':
                $queryExtra = $includeStats ? ', max(Fahrenheit) as max, min(Fahrenheit) as min' : '';
                // Round the time to the nearest hour
                $query = "select unix_timestamp(DATE_FORMAT(DATE_ADD(time, INTERVAL 30 MINUTE),'%Y-%m-%d %H:00:00')) as time, avg(Fahrenheit) as temp $queryExtra from digitemp where SerialNumber = ? and time BETWEEN from_unixtime(?) and from_unixtime(?) group by SerialNumber, date(time), hour(time) order by date(time), hour(time)";
                break;
            default:
                //This mode does not include min/max (doesn't make sense)
                $query =  "select unix_timestamp(time), Fahrenheit temp from digitemp where SerialNumber = ? and time BETWEEN from_unixtime(?) and from_unixtime(?)  order by time";
        }

        $dbTime = microtime(true);
        $result = [
            'mode' => $mode,
            'query' => $query,
            'bind' => [$sensor, $start, $end] ,
            'human' => sprintf('Start=%s, End=%s', date('r', $start), date('r', $end)),
            'data' => DB::select($query, [$sensor, $start, $end]) ];

        //should we even bother caching?
        if (microtime(true) - $dbTime > config('dtgraph.cache_min_lookup_threshold')) {
            //caching...
            $expiresAt = Carbon::now()->addMinutes(config('dtgraph.cache_new_readings_time'));
            if ((time() - $end) / 60 > config('dtgraph.cache_old_readings_min_age')) {
                //these are entirely old readings
                $expiresAt = Carbon::now()->addMinutes(config('dtgraph.cache_old_readings_time'));
            }

            $result['cache_expires'] = $expiresAt;
            $result['cache_key'] = $key;

            Cache::put($key, $result, $expiresAt);
        }

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
