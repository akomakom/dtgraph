<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\MqttPublisher;

class MqttPublishDiscovery extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:publish-discovery';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish MQTT discovery messages for all sensors to Home Assistant';

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

        $this->info('Publishing MQTT discovery messages for all sensors...');
        
        try {
            MqttPublisher::publishAllDiscoveries();
            $this->info('Successfully published MQTT discovery messages.');
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to publish MQTT discovery messages: ' . $e->getMessage());
            return 1;
        }
    }
}
