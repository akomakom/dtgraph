<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Sensor extends Model
{
    protected $table = 'digitemp_metadata';
    protected $primaryKey = 'SerialNumber';
    public $timestamps = false;
    //


    /**
     * Reads sensor metadata for given sensor.  If no sensor given, reads all and returns an array.
     * @param null $sensor
     * @return null
     */
    public static function read($sensor = null) {
        $key = "sensor_metadata${sensor}";

        $result = null;
        if (Cache::has($key)) {
            $result = Cache::get($key);
        } else {
            //using either query builder or the eloquent stuff fails as it converts SerialNumber to integer.

            if (isset($sensor)) {
                $result = DB::select('select * from digitemp_metadata where SerialNumber = ?', [$sensor]);
            } else {
                $result = DB::select('select * from digitemp_metadata');
            }

//            $result = Sensor::all();
            $expiresAt = Carbon::now()->addMinutes(config('dtgraph.cache_sensor_info_time'));
            Cache::put($key, $result, $expiresAt);
        }

        return $result;
    }

}
