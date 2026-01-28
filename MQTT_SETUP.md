# MQTT Integration for DTGraph

This document describes the MQTT integration feature that allows DTGraph to publish sensor readings to an MQTT broker, with automatic Home Assistant discovery support.

## Features

- **Automatic Home Assistant Discovery**: Sensors are automatically discovered by Home Assistant via MQTT discovery protocol
- **Real-time Publishing**: Temperature and humidity readings are published to MQTT as they are received
- **Configurable Topics**: Customize MQTT topics and discovery prefix
- **Lazy Discovery**: Discovery messages are published on first reading or can be pre-published via artisan command

## Installation

### 1. Install Dependencies

The MQTT client library has already been installed via Composer:

```bash
composer require php-mqtt/client
```

### 2. Configure Environment Variables

Add the following to your `.env` file:

```env
# MQTT Configuration for Home Assistant Integration
MQTT_ENABLED=true
MQTT_HOST=localhost
MQTT_PORT=1883
MQTT_USERNAME=your_mqtt_username
MQTT_PASSWORD=your_mqtt_password
MQTT_CLIENT_ID=dtgraph
MQTT_DISCOVERY_PREFIX=homeassistant
MQTT_STATE_TOPIC_PREFIX=dtgraph
MQTT_AVAILABILITY_TOPIC=dtgraph/status
```

**Important**: After modifying `.env`, you must restart your web server or PHP-FPM for changes to take effect:

```bash
# For Apache
sudo systemctl restart apache2
# or
sudo service apache2 restart

# For Nginx with PHP-FPM
sudo systemctl restart php8.0-fpm  # adjust PHP version as needed
sudo systemctl restart nginx

# For Laravel development server (if using artisan serve)
# Just stop (Ctrl+C) and restart: php artisan serve
```

**Note**: Laravel loads `.env` variables only once when the application bootstraps. In production, configuration may be cached, so also run:

```bash
php artisan config:clear
```

### 3. Configuration Options

| Variable | Default | Description |
|----------|---------|-------------|
| `MQTT_ENABLED` | `false` | Enable/disable MQTT publishing |
| `MQTT_HOST` | `localhost` | MQTT broker hostname or IP |
| `MQTT_PORT` | `1883` | MQTT broker port |
| `MQTT_USERNAME` | `null` | MQTT authentication username |
| `MQTT_PASSWORD` | `null` | MQTT authentication password |
| `MQTT_CLIENT_ID` | `dtgraph` | MQTT client identifier |
| `MQTT_DISCOVERY_PREFIX` | `homeassistant` | Home Assistant discovery topic prefix |
| `MQTT_STATE_TOPIC_PREFIX` | `dtgraph` | Prefix for sensor state topics |
| `MQTT_AVAILABILITY_TOPIC` | `dtgraph/status` | Topic for service availability status |

## Usage

### Automatic Publishing

Once configured, MQTT publishing happens automatically when sensor readings are added via the API:

```bash
# Temperature reading
curl -X POST "http://your-server/api/add/SENSOR_ID?temperature=72.5"

# Temperature and humidity reading
curl -X POST "http://your-server/api/add/SENSOR_ID?temperature=72.5&humidity=45"
```

### Manual Discovery Publishing

To pre-publish discovery messages for all **active** sensors:

```bash
php artisan mqtt:publish-discovery
```

This command:
- Only publishes sensors marked as `active=true` in the `digitemp_metadata` table
- Skips sensors marked as `active=false`
- Useful when setting up MQTT for the first time or after Home Assistant restarts

### Removing Sensors from Home Assistant

**Clear ALL sensors and start fresh:**

```bash
php artisan mqtt:clear-all
```

This command:
- Removes ALL dtgraph sensors from Home Assistant
- Publishes empty discovery messages for every sensor
- Clears the discovery cache
- Useful for cleaning up after configuration changes
- After running, republish with: `php artisan mqtt:publish-discovery`

**Remove only inactive sensors:**

```bash
php artisan mqtt:unpublish-inactive
```

This command:
- Finds all sensors marked as `active=false` in `digitemp_metadata`
- Publishes empty discovery messages to remove them from Home Assistant
- Clears the discovery cache so they can be republished if reactivated

**Typical workflow to clean up and republish:**

```bash
# 1. Clear all old sensors
php artisan mqtt:clear-all

# 2. Wait a moment for Home Assistant to process

# 3. Republish only active sensors with correct configuration
php artisan mqtt:publish-discovery
```

**Manual Removal Steps:**

If you need to manually remove specific sensors:

1. **Via MQTT** (recommended):
   ```bash
   # Remove a specific sensor's discovery
   mosquitto_pub -h localhost -t "homeassistant/sensor/dtgraph/SENSOR_ID_temperature/config" -n -r
   mosquitto_pub -h localhost -t "homeassistant/sensor/dtgraph/SENSOR_ID_humidity/config" -n -r
   ```
   The `-n` flag sends an empty message, and `-r` makes it retained.

2. **Via Home Assistant UI**:
   - Go to **Settings → Devices & Services → MQTT**
   - Find the device/entity
   - Click the three dots → **Delete**

3. **Mark sensors as inactive in database**:
   ```sql
   UPDATE digitemp_metadata SET active = 0 WHERE SerialNumber = 'SENSOR_ID';
   ```
   Then run `php artisan mqtt:unpublish-inactive`

## MQTT Topics

### Discovery Topics

Discovery messages are published to:
```
{discovery_prefix}/sensor/dtgraph/{sensor_id}_{type}/config
```

Example:
```
homeassistant/sensor/dtgraph/28FF123456789ABC_temperature/config
homeassistant/sensor/dtgraph/28FF123456789ABC_humidity/config
```

### State Topics

Sensor readings are published to:
```
{state_topic_prefix}/{sensor_id}/{type}
```

Example:
```
dtgraph/28FF123456789ABC/temperature
dtgraph/28FF123456789ABC/humidity
```

### Availability Topic

Service availability is published to:
```
{availability_topic}
```

Example:
```
dtgraph/status
```

Payload: `online` or `offline`

## Home Assistant Integration

### Automatic Discovery

When MQTT is enabled and configured, sensors will automatically appear in Home Assistant under:

**Configuration → Integrations → MQTT**

Each sensor will show:
- Device name (from sensor metadata)
- Temperature entity (if temperature readings exist)
- Humidity entity (if humidity readings exist)

### Discovery Payload Example

```json
{
  "name": "Living Room Temperature",
  "unique_id": "dtgraph_28FF123456789ABC_temperature",
  "state_topic": "dtgraph/28FF123456789ABC/temperature",
  "availability_topic": "dtgraph/status",
  "device_class": "temperature",
  "unit_of_measurement": "°F",
  "state_class": "measurement",
  "device": {
    "identifiers": ["28FF123456789ABC"],
    "name": "Living Room",
    "manufacturer": "DTGraph",
    "model": "Temperature Sensor"
  }
}
```

## Troubleshooting

### Viewing Debug Logs

MQTT operations are logged to Laravel's standard log file. The log level determines what you see:

**Log Locations:**
- Default: `storage/logs/laravel.log`
- Daily rotation: `storage/logs/laravel-YYYY-MM-DD.log`

**View logs in real-time:**
```bash
# Follow the log file
tail -f storage/logs/laravel.log

# Filter for MQTT-related entries only
tail -f storage/logs/laravel.log | grep -i mqtt

# View last 100 lines
tail -n 100 storage/logs/laravel.log
```

**Log Levels:**

The `MqttPublisher` class uses different log levels:
- `Log::debug()` - Detailed operation info (each reading published)
- `Log::info()` - Important events (connections, discovery published)
- `Log::error()` - Failures and errors

**Enable Debug Logging:**

To see `Log::debug()` messages, ensure your `.env` has:
```env
LOG_LEVEL=debug
```

Default is `debug`, but production environments often use `info` or higher. After changing `LOG_LEVEL`, restart your web server/PHP-FPM.

**What Gets Logged:**

```
# Connection events
[INFO] MQTT client connected {"host":"localhost","port":1883}

# Discovery publishing
[INFO] Published MQTT discovery {"sensor":"28FF123456789ABC","type":"temperature","topic":"homeassistant/sensor/..."}

# Each reading (debug level)
[DEBUG] Published MQTT reading {"sensor":"28FF123456789ABC","type":"temperature","value":72.5,"topic":"dtgraph/28FF123456789ABC/temperature"}

# Errors
[ERROR] Failed to connect to MQTT broker {"error":"Connection refused","host":"localhost","port":1883}
[ERROR] Failed to publish MQTT reading {"sensor":"...","type":"temperature","value":72.5,"error":"..."}
```

### Check MQTT Connection

1. Verify MQTT broker is running and accessible
2. Check credentials are correct
3. Review Laravel logs for MQTT connection errors:
   ```bash
   tail -f storage/logs/laravel.log | grep -i mqtt
   ```

### Test MQTT Publishing

Use an MQTT client to subscribe to topics:

```bash
# Subscribe to all dtgraph topics
mosquitto_sub -h localhost -t "dtgraph/#" -v

# Subscribe to Home Assistant discovery
mosquitto_sub -h localhost -t "homeassistant/sensor/dtgraph/#" -v
```

### Common Issues

**Sensors not appearing in Home Assistant:**
- Ensure `MQTT_ENABLED=true` in `.env`
- Check MQTT broker is running
- Verify Home Assistant MQTT integration is configured
- Run `php artisan mqtt:publish-discovery` to force discovery

**Connection refused:**
- Check `MQTT_HOST` and `MQTT_PORT` are correct
- Verify firewall allows connection to MQTT broker
- Check MQTT broker logs

**Authentication failed:**
- Verify `MQTT_USERNAME` and `MQTT_PASSWORD` are correct
- Check MQTT broker user permissions

## Architecture

### MqttPublisher Model

The `App\MqttPublisher` class handles all MQTT operations:

- `publishTemperature($sensorId, $temperature)` - Publish temperature reading
- `publishHumidity($sensorId, $humidity)` - Publish humidity reading
- `publishDiscovery($sensorId, $type)` - Publish discovery message
- `publishAllDiscoveries()` - Publish discovery for all sensors

### Integration Points

1. **ApiController::add()** - Publishes readings when new data is received
2. **Artisan Command** - `mqtt:publish-discovery` for manual discovery publishing
3. **Caching** - Discovery messages are cached for 7 days to avoid republishing

## Performance Considerations

- Discovery messages are cached and only published once per sensor
- MQTT connection is reused across multiple publishes
- Publishing is non-blocking and won't slow down API responses
- Failed MQTT publishes are logged but don't affect API functionality

## Security

- Use strong MQTT credentials
- Consider using TLS/SSL for MQTT connections (requires broker configuration)
- Restrict MQTT user permissions to only necessary topics
- Keep MQTT broker on internal network or use VPN

## Future Enhancements

Potential improvements:
- TLS/SSL support
- QoS configuration options
- Retained message configuration
- MQTT connection pooling
- Batch publishing for multiple sensors
- WebSocket MQTT support