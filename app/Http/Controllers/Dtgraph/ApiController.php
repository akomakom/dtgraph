<?php namespace App\Http\Controllers\Dtgraph;

use App\Http\Controllers\Controller;
use App\Reading;
use App\Sensor;
use Illuminate\Http\Request;

class ApiController extends Controller {

    const ERROR_ARGS = 1;


    public function sensor($sensor = null) {
        $startTime = microtime(true);
        return $this->wrapStatus(['data' => Sensor::read($sensor)], true, $startTime);
    }

    public function sensorName() {
        $startTime = microtime(true);
        return $this->wrapStatus(['data' => Reading::distinctSensors()], true, $startTime);
    }


    public function reading(Request $request, $sensor) {
        if (!$request->exists('start') || !$request->exists('end')) {
            return $this->makeError(self::ERROR_ARGS, 'Value required for start/end');
        }
        $startTime = microtime(true);
        return
            $this->wrapStatus(
                Reading::readings($sensor, $request->input('start'), $request->input('end'), $request->input('stats', false)),
                true,
                $startTime
            );
    }


    public function latest($sensor = null) {
        $startTime = microtime(true);

        if ($sensor == null) {
            $sensors = Reading::distinctSensors();
            $result = array();
            foreach($sensors as $sensor) {
                $result[$sensor] =  Reading::latest($sensor);
            }
        } else {
            $result = Reading::latest($sensor);
        }
        return $this->wrapStatus(['data' => $result], true, $startTime);
    }




    private function wrapStatus($result, $ok = true, $startTime = null, $code = 200) {
        if (is_array($result)) {
            $result['ok'] = $ok;
        } else {
            $result = ['data' => $result, 'ok' => $ok];
        }

        if ($startTime != null) {
            $result['time'] = microtime(true) - $startTime;
        }

        return response()->json(
            $result,
            $code,
            [],
            JSON_NUMERIC_CHECK
        );
    }

    private function makeError($errorCode, $message) {
        return $this->wrapStatus(['message' => $message, 'code' => $errorCode], false, null, 500);
    }

}
