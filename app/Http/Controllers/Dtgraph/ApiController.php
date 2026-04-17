<?php namespace App\Http\Controllers\Dtgraph;

use App\Http\Controllers\Controller;
use App\Reading;
use App\Sensor;
use App\MqttPublisher;
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
        
        // Keep original sensor ID for MQTT (before shortening for database)
        $originalSensor = $sensor;
        
        $temperature = null;
        if ($request->input('unit') == 'C') {
            //convert to Fahrenheit
            $temperature = $request->input('temperature') * 9/5 + 32;
            Reading::add($sensor, $temperature, $delta);
        } else {
            $temperature = $request->input('temperature');
            Reading::add($sensor, $temperature, $delta);
        }

        // Shorten sensor ID for database storage (MAC addresses)
        if (config('dtgraph.shorten_serialnumber_if_mac', false)
            && preg_match("/^..:..:..:..:..:..$/", $sensor)) {
            $sensor = preg_replace("/:/", "", $sensor);
        }

        // Only publish to MQTT if this is a current reading (delta=0)
        // Historical/deferred readings should not update MQTT state
        if ($delta == 0) {
            // Publish temperature to MQTT using original sensor ID
            if ($temperature !== null) {
                MqttPublisher::publishTemperature($originalSensor, $temperature);
            }

            // Handle humidity
            if ($request->input('humidity') > 0) {
                $humidity = $request->input('humidity');
                // Publish humidity to MQTT using original sensor ID
                MqttPublisher::publishHumidity($originalSensor, $humidity);
            }
        }

        // Always save humidity to database (even for historical readings)
        if ($request->input('humidity') > 0) {
            $humidity = $request->input('humidity');
            Reading::add("{$sensor}-H", $humidity, $delta);
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

        // Build this either way:
        $text = [];
        foreach ($result as $num => $item) {
            if ($item->status == 'stale') {
                $text[] = sprintf(
                    "Sensor %s (%s)",
                    $item->name ?: $item->SerialNumber,
                    Carbon::now()->subSeconds($item->age)->diffForHumans()
                );
            }
        }

        if ($request->get('short', false) == true) {
            return count($text) > 0 ?
                $this->wrapStatusText("WARNING: Stale: " . implode(", ", $text), 503)
                :
                $this->wrapStatusText("OK");
        } else {
            return $this->wrapStatus($result, count($text) == 0 , null, count($text) > 0 ? 503 : 200);
        }
    }

    private function wrapStatusText($result, $code = 200) {
        return (new Response($result, $code))
            ->header('Content-Type', 'text/plain');
    }

    private function wrapStatus(
        $result, 
        $ok = true, 
        $startTime = null,
        $code = 200
    ) {
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
