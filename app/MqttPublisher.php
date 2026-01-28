<?php

namespace App;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MqttPublisher
{
    private static $client = null;
    private static $discoveryPublished = [];

    /**
     * Get or create MQTT client connection
     * 
     * @return MqttClient|null
     */
    private static function getClient()
    {
        if (!config('dtgraph.mqtt.enabled', false)) {
            return null;
        }

        if (self::$client === null) {
            try {
                $host = config('dtgraph.mqtt.host', 'localhost');
                $port = config('dtgraph.mqtt.port', 1883);
                $clientId = config('dtgraph.mqtt.client_id', 'dtgraph_' . uniqid());
                
                self::$client = new MqttClient($host, $port, $clientId);
                
                $connectionSettings = (new ConnectionSettings())
                    ->setUsername(config('dtgraph.mqtt.username'))
                    ->setPassword(config('dtgraph.mqtt.password'))
                    ->setKeepAliveInterval(60)
                    ->setLastWillTopic(config('dtgraph.mqtt.availability_topic', 'dtgraph/status'))
                    ->setLastWillMessage('offline')
                    ->setLastWillQualityOfService(1);
                
                self::$client->connect($connectionSettings, true);
                
                // Publish online status
                self::$client->publish(
                    config('dtgraph.mqtt.availability_topic', 'dtgraph/status'),
                    'online',
                    1,
                    true
                );
                
                Log::info('MQTT client connected', ['host' => $host, 'port' => $port]);
            } catch (\Exception $e) {
                Log::error('Failed to connect to MQTT broker', [
                    'error' => $e->getMessage(),
                    'host' => config('dtgraph.mqtt.host'),
                    'port' => config('dtgraph.mqtt.port')
                ]);
                self::$client = null;
            }
        }
        
        return self::$client;
    }

    /**
     * Publish Home Assistant discovery message for a sensor
     * 
     * @param string $sensorId The sensor serial number
     * @param string $sensorType 'temperature' or 'humidity'
     * @return bool
     */
    public static function publishDiscovery($sensorId, $sensorType = 'temperature')
    {
        $client = self::getClient();
        if ($client === null) {
            return false;
        }

        // Check if we've already published discovery for this sensor
        $cacheKey = "mqtt_discovery_{$sensorId}_{$sensorType}";
        if (Cache::has($cacheKey)) {
            Log::debug('Discovery already cached, skipping', [
                'sensor' => $sensorId,
                'type' => $sensorType,
                'cacheKey' => $cacheKey
            ]);
            return true;
        }

        try {
            // Get sensor metadata
            $sensorMetadata = Sensor::read($sensorId);
            $sensorName = 'Unknown Sensor';
            $sensorDescription = null;
            
            if (!empty($sensorMetadata) && is_array($sensorMetadata) && count($sensorMetadata) > 0) {
                $metadata = $sensorMetadata[0];
                $sensorName = $metadata->name ?? $sensorId;
                $sensorDescription = $metadata->description ?? null;
            }

            // Build discovery topic
            $discoveryPrefix = config('dtgraph.mqtt.discovery_prefix', 'homeassistant');
            $component = 'sensor';
            $nodeId = 'dtgraph';
            // Sanitize sensor ID for MQTT topic (replace colons with underscores)
            $sanitizedId = str_replace(':', '_', $sensorId);
            $objectId = $sanitizedId . '_' . $sensorType;
            
            $discoveryTopic = "{$discoveryPrefix}/{$component}/{$nodeId}/{$objectId}/config";
            
            // Build state topic - use original sensor ID with colons
            $stateTopic = config('dtgraph.mqtt.state_topic_prefix', 'dtgraph') . "/{$sensorId}/{$sensorType}";
            
            // Build discovery payload
            $payload = [
                'name' => $sensorName . ' ' . ucfirst($sensorType),
                'unique_id' => "{$nodeId}_{$objectId}",
                'state_topic' => $stateTopic,
                'availability_topic' => config('dtgraph.mqtt.availability_topic', 'dtgraph/status'),
                'device' => [
                    'identifiers' => [$sensorId],
                    'name' => $sensorName,
                    'manufacturer' => 'DTGraph',
                    'model' => 'Temperature Sensor',
                ],
            ];

            // Add sensor-specific configuration
            if ($sensorType === 'temperature') {
                $payload['device_class'] = 'temperature';
                $payload['unit_of_measurement'] = '°F';
                $payload['state_class'] = 'measurement';
            } elseif ($sensorType === 'humidity') {
                $payload['device_class'] = 'humidity';
                $payload['unit_of_measurement'] = '%';
                $payload['state_class'] = 'measurement';
            }

            // Note: 'description' is not a valid field in Home Assistant's device schema
            // The description from metadata is not included in the discovery payload

            // Publish discovery message
            $client->publish(
                $discoveryTopic,
                json_encode($payload),
                1,
                true // retain
            );

            // Cache that we've published discovery for this sensor
            Cache::put($cacheKey, true, now()->addDays(7));
            
            Log::info('Published MQTT discovery', [
                'sensor' => $sensorId,
                'type' => $sensorType,
                'topic' => $discoveryTopic
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to publish MQTT discovery', [
                'sensor' => $sensorId,
                'type' => $sensorType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Publish sensor reading to MQTT
     * 
     * @param string $sensorId The sensor serial number
     * @param float $value The reading value
     * @param string $type 'temperature' or 'humidity'
     * @return bool
     */
    public static function publishReading($sensorId, $value, $type = 'temperature')
    {
        $client = self::getClient();
        if ($client === null) {
            return false;
        }

        try {
            // Ensure discovery has been published first
            self::publishDiscovery($sensorId, $type);

            // Build state topic
            $stateTopic = config('dtgraph.mqtt.state_topic_prefix', 'dtgraph') . "/{$sensorId}/{$type}";
            
            // Publish the reading
            $client->publish(
                $stateTopic,
                (string) round($value, 2),
                0,
                false
            );

            Log::debug('Published MQTT reading', [
                'sensor' => $sensorId,
                'type' => $type,
                'value' => $value,
                'topic' => $stateTopic
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to publish MQTT reading', [
                'sensor' => $sensorId,
                'type' => $type,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Publish temperature reading
     * 
     * @param string $sensorId
     * @param float $temperature Temperature in Fahrenheit
     * @return bool
     */
    public static function publishTemperature($sensorId, $temperature)
    {
        return self::publishReading($sensorId, $temperature, 'temperature');
    }

    /**
     * Publish humidity reading
     * 
     * @param string $sensorId
     * @param float $humidity Humidity percentage
     * @return bool
     */
    public static function publishHumidity($sensorId, $humidity)
    {
        return self::publishReading($sensorId, $humidity, 'humidity');
    }


    /**
     * Disconnect MQTT client
     */
    public static function disconnect()
    {
        if (self::$client !== null) {
            try {
                self::$client->disconnect();
                Log::info('MQTT client disconnected');
            } catch (\Exception $e) {
                Log::error('Error disconnecting MQTT client', ['error' => $e->getMessage()]);
            }
            self::$client = null;
        }
    }

    /**
     * Publish discovery for all active sensors
     * Only publishes sensors marked as active in digitemp_metadata
     *
     * @param bool $includeInactive If true, publish all sensors regardless of active status
     * @return void
     */
    public static function publishAllDiscoveries($includeInactive = false)
    {
        if (!config('dtgraph.mqtt.enabled', false)) {
            return;
        }

        try {
            $sensors = Reading::distinctSensors();
            $publishedCount = 0;
            $skippedCount = 0;
            
            foreach ($sensors as $sensorId) {
                // Check if sensor has metadata and is active
                $hasMetadata = false;
                $isActive = false;
                
                try {
                    $metadata = Sensor::read($sensorId);
                    if (!empty($metadata) && is_array($metadata) && count($metadata) > 0) {
                        $hasMetadata = true;
                        $isActive = $metadata[0]->active ?? false;
                    }
                } catch (\Exception $e) {
                    // If metadata lookup fails, skip this sensor
                    Log::debug('No metadata found for sensor, skipping', [
                        'sensor' => $sensorId
                    ]);
                }
                
                // Skip sensors without metadata or inactive sensors
                if (!$hasMetadata || (!$isActive && !$includeInactive)) {
                    $skippedCount++;
                    Log::debug('Skipping sensor', [
                        'sensor' => $sensorId,
                        'hasMetadata' => $hasMetadata,
                        'isActive' => $isActive
                    ]);
                    continue;
                }
                
                // Check if this is a humidity sensor (ends with -H)
                if (preg_match('/-H$/', $sensorId)) {
                    // This is a humidity sensor - remove -H suffix
                    $baseSensorId = preg_replace('/-H$/', '', $sensorId);
                    
                    // If base sensor ID is a shortened MAC (12 hex chars, no colons),
                    // add colons back to match the temperature sensor's ID format
                    if (strlen($baseSensorId) == 12 && ctype_xdigit($baseSensorId)) {
                        $baseSensorId = strtoupper(implode(':', str_split($baseSensorId, 2)));
                    }
                    
                    Log::debug('Publishing humidity sensor', [
                        'original' => $sensorId,
                        'base' => $baseSensorId
                    ]);
                    
                    // Publish humidity using the same sensor ID as temperature
                    // This way both entities share the same device and metadata
                    self::publishDiscovery($baseSensorId, 'humidity');
                    $publishedCount++;
                } else {
                    // This is a temperature sensor
                    self::publishDiscovery($sensorId, 'temperature');
                    $publishedCount++;
                }
            }
            
            Log::info('Published MQTT discovery for active sensors', [
                'published' => $publishedCount,
                'skipped' => $skippedCount,
                'total' => count($sensors)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to publish all MQTT discoveries', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove (unpublish) a sensor from Home Assistant by publishing empty discovery
     *
     * @param string $sensorId The sensor serial number
     * @param string $sensorType 'temperature' or 'humidity'
     * @return bool
     */
    public static function unpublishDiscovery($sensorId, $sensorType = 'temperature')
    {
        $client = self::getClient();
        if ($client === null) {
            return false;
        }

        try {
            // Build discovery topic
            $discoveryPrefix = config('dtgraph.mqtt.discovery_prefix', 'homeassistant');
            $component = 'sensor';
            $nodeId = 'dtgraph';
            // Sanitize sensor ID for MQTT topic (replace colons with underscores)
            $sanitizedId = str_replace(':', '_', $sensorId);
            $objectId = $sanitizedId . '_' . $sensorType;
            
            $discoveryTopic = "{$discoveryPrefix}/{$component}/{$nodeId}/{$objectId}/config";
            
            // Publish empty payload to remove the sensor
            $client->publish(
                $discoveryTopic,
                '',
                1,
                true // retain
            );

            // Clear the cache so it can be republished later if needed
            $cacheKey = "mqtt_discovery_{$sensorId}_{$sensorType}";
            Cache::forget($cacheKey);
            
            Log::info('Unpublished MQTT discovery', [
                'sensor' => $sensorId,
                'type' => $sensorType,
                'topic' => $discoveryTopic
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to unpublish MQTT discovery', [
                'sensor' => $sensorId,
                'type' => $sensorType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Unpublish all inactive sensors from Home Assistant
     *
     * @return void
     */
    public static function unpublishInactiveSensors()
    {
        if (!config('dtgraph.mqtt.enabled', false)) {
            return;
        }

        try {
            $sensors = Reading::distinctSensors();
            $unpublishedCount = 0;
            
            foreach ($sensors as $sensorId) {
                // Check if sensor is inactive in metadata
                $isActive = true;
                
                try {
                    $metadata = Sensor::read($sensorId);
                    if (!empty($metadata) && is_array($metadata) && count($metadata) > 0) {
                        $isActive = $metadata[0]->active ?? true;
                    }
                } catch (\Exception $e) {
                    // If metadata lookup fails, skip
                    continue;
                }
                
                // Only unpublish if explicitly marked as inactive
                if (!$isActive) {
                    // Check if this is a humidity sensor
                    if (preg_match('/-H$/', $sensorId)) {
                        $baseSensorId = preg_replace('/-H$/', '', $sensorId);
                        self::unpublishDiscovery($baseSensorId, 'humidity');
                    } else {
                        self::unpublishDiscovery($sensorId, 'temperature');
                    }
                    $unpublishedCount++;
                }
            }
            
            Log::info('Unpublished inactive sensors from MQTT', ['count' => $unpublishedCount]);
        } catch (\Exception $e) {
            Log::error('Failed to unpublish inactive sensors', ['error' => $e->getMessage()]);
        }
    }
}
