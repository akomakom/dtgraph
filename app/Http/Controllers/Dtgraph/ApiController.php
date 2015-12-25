<?php namespace App\Http\Controllers\Dtgraph;

use App\Http\Controllers\Controller;
use App\Reading;
use App\Sensor;
use Illuminate\Http\Request;

class ApiController extends Controller {


    public function sensor($sensor = null) {
       return response()->json(Sensor::read($sensor),JSON_NUMERIC_CHECK);
    }

    public function sensorName() {
        return response()->json(Reading::distinctSensors());
    }


    public function reading(Request $request, $sensor) {
        return response()->json(Reading::readings($sensor, $request->input('start'), $request->input('end'), $request->input('stats', false)));
    }


    public function latest($sensor = null) {
        if ($sensor == null) {
            $sensors = Reading::distinctSensors();
            $result = array();
            foreach($sensors as $sensor) {
                $result[$sensor] =  Reading::latest($sensor);
            }
        } else {
            $result = Reading::latest($sensor);
        }
        return response()->json($result);
    }
}
