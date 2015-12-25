<?php namespace App\Http\Controllers\Dtgraph;

use App\Http\Controllers\Controller;
use App\Reading;
use App\Sensor;
use Illuminate\Http\Request;

class ApiController extends Controller {


    public function sensor($sensor = null) {
//        return Yaml::dump(Sensor::read(), 2,4,  true, true);
       return json_encode(Sensor::read($sensor),JSON_NUMERIC_CHECK);
//        return print_r(Sensor::read(), true);
    }

    public function sensorName() {
        return json_encode(Reading::distinctSensors());
    }


    public function reading(Request $request, $sensor) {
        echo json_encode(Reading::readings($sensor, $request->input('start'), $request->input('end'), $request->input('stats', false)));
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
        return json_encode($result);
    }
}
