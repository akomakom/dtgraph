<?php namespace App\Http\Controllers\Dtgraph;

use App\Http\Controllers\Controller;
use App\Reading;
use App\Sensor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Carbon\Carbon;

class ApiController extends Controller {

    const ERROR_ARGS = 1;


    public function sensor(Request $request, $sensor = null) {
        $startTime = microtime(true);
        $data = Sensor::read($sensor);
        if ($request->input('latest', false)) {
            foreach($data as $metadata) {
                $metadata->latest =  Reading::latest($metadata->SerialNumber);
            }
        }
        return $this->wrapStatus(['data' => $data], true, $startTime);
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
                Reading::readings($sensor, $request->input('start'), $request->input('end'), $request->input('mode', 'avg')),
                true,
                $startTime
            );
    }


    public function latest(Request $request, $sensor = null) {
        $startTime = microtime(true);

        if ($sensor == null) {
            $sensors = Reading::distinctSensors();
            $result = array();
            foreach($sensors as $s) {
                $result[$s] =  Reading::latest($s);
            }
        } else {
            $result = Reading::latest($sensor);
        }

        //this one supports alternate formats
        switch ($request->input('format', 'json')) {

            case 'txt':
                $response = '';
                if ($sensor != null) {
                    $response = $result->avg;
                } else {
                    foreach ($result as $name => $item) {
                        $response .= sprintf ("%s:%s\n", $name, $item->avg);
                    }
                }
                return $this->wrapStatusText($response);
            break;
            default:
                return $this->wrapStatus(['data' => $result], true, $startTime);
        }
    }
/**/
    public function add(Request $request, $sensor = null) {
        $delta = intval($request->input('delta_seconds'));
//        if (rand(0,10) > 40) {
//            return $this->wrapStatus("Faking a problem", false, null, 433);
//        }
        if ($request->input('unit') == 'C') {
            //convert to Fahrenheit
            Reading::add($sensor, $request->input('temperature') *9/5+32, $delta);

        } else {
            Reading::add($sensor, $request->input('temperature'), $delta);
        }
        // TODO: handle humidity
        if ($request->input('humidity') > 0) {
            // don't do this yet, with col size 17 it's shortened to mac and is recorded as temp
            //Reading::add("{$sensor}-humidity", $request->input('humidity'), $delta);
        }
        return $this->wrapStatus('accepted');
    }
/**/

    /**
     * @param Request $request
     * @param $sensor optionally only check this sensor
     * @param $timeframe minutes how far back to look for sensors with readings (default 7 days).
     * @param $threshold minutes how far back is stale (default 45 minutes))
     * @return void
     */
    public function healthCheck(Request $request, $sensor = null) {
        $result = Reading::staleCheck(
            $sensor,
            $request->get('timeframe', 10080),
            $request->get('threshold', 45)
        );

        if ($request->get('short', false) == true) {
            $text = [];

            foreach ($result as $num => $item) {
                if ($item->status == 'stale') {
                    $text[] = sprintf(
                        "Sensor %s (%s)",
                        $item->SerialNumber,
                        Carbon::now()->subSeconds($item->age)->diffForHumans()
                    );
                }
            }
            return count($text) > 0 ?
                $this->wrapStatusText("WARNING: Stale: " . implode(", ", $text), )
                :
                $this->wrapStatusText("OK");
        } else {
            return $this->wrapStatus($result);
        }
    }

    private function wrapStatusText($result, $code = 200) {
        return (new Response($result, $code))
            ->header('Content-Type', 'text/plain');
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
