<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\MqttPublisher;

class MqttUnpublishInactive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:unpublish-inactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove inactive sensors from Home Assistant by unpublishing their MQTT discovery messages';

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

        $this->info('Unpublishing inactive sensors from Home Assistant...');

        try {
            MqttPublisher::unpublishInactiveSensors();
            $this->info('Successfully unpublished inactive sensors.');
            $this->info('Note: It may take a moment for Home Assistant to remove the entities.');
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to unpublish inactive sensors: ' . $e->getMessage());
            return 1;
        }
    }
}
