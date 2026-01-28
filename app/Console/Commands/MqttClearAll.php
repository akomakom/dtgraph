<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\MqttPublisher;
use App\Reading;

class MqttClearAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:clear-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove ALL sensors from Home Assistant by clearing all MQTT discovery messages';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!config('dtgraph.mqtt.enabled', false)) {
            $this->error('MQTT is not enabled. Set MQTT_ENABLED=true in your .env file.');
            return 1;
        }

        if (!$this->confirm('This will remove ALL dtgraph sensors from Home Assistant. Continue?')) {
            $this->info('Cancelled.');
            return 0;
        }

        $this->info('Clearing all MQTT discovery messages...');

        try {
            $sensors = Reading::distinctSensors();
            $clearedCount = 0;

            foreach ($sensors as $sensorId) {
                // Check if this is a humidity sensor (ends with -H)
                if (preg_match('/-H$/', $sensorId)) {
                    // This is a humidity sensor
                    $baseSensorId = preg_replace('/-H$/', '', $sensorId);
                    MqttPublisher::unpublishDiscovery($baseSensorId, 'humidity');
                } else {
                    // This is a temperature sensor
                    MqttPublisher::unpublishDiscovery($sensorId, 'temperature');
                }
                $clearedCount++;
            }

            $this->info("Successfully cleared {$clearedCount} sensors from Home Assistant.");
            $this->info('Note: It may take a moment for Home Assistant to remove the entities.');
            $this->info('You can now run: php artisan mqtt:publish-discovery');
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to clear sensors: ' . $e->getMessage());
            return 1;
        }
    }
}
