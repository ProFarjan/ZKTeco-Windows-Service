# ZKTeco Service - API Integration Guide

## Overview

This service now sends attendance data to an external API endpoint every **5 minutes** (300 seconds). It works standalone without WAMP dependency.

## Features

✅ **Standalone PHP** - No WAMP/XAMPP required  
✅ **API Integration** - Push data to any REST API  
✅ **Batch Processing** - Sends data in configurable batches  
✅ **Retry Logic** - Automatic retry on failures  
✅ **Failed Request Storage** - Saves failed requests for later retry  
✅ **Database Optional** - Can work with API only (no database)  
✅ **5 Minute Sync** - Configurable sync interval  

---

## Installation (No WAMP Dependency)

### Step 1: Download Standalone PHP

Run as **Administrator**:

```cmd
download_php.bat
```

This will:
- Download PHP 8.2.15 (standalone)
- Extract to `php/` folder
- Enable required extensions (curl, sockets, openssl, etc.)
- No WAMP/XAMPP needed

**OR** Download manually:
1. Go to: https://windows.php.net/download/
2. Download PHP 8.2+ (Thread Safe, x64)
3. Extract to `php/` folder in this directory

### Step 2: Configure API Endpoint

Edit `config.json`:

```json
{
    "sync_interval": 300,
    "api": {
        "enabled": true,
        "endpoint": "https://your-api-domain.com/api/attendance/sync",
        "method": "POST",
        "timeout": 30,
        "headers": {
            "Content-Type": "application/json",
            "Authorization": "Bearer YOUR_API_TOKEN",
            "X-API-Key": "your-secret-key"
        },
        "batch_size": 100,
        "retry_on_failure": true,
        "save_failed_requests": true
    },
    "database": {
        "enabled": false
    }
}
```

**Configuration Options:**

- **`sync_interval`**: Seconds between syncs (300 = 5 minutes)
- **`endpoint`**: Your API URL
- **`method`**: HTTP method (POST, PUT, PATCH)
- **`timeout`**: Request timeout in seconds
- **`headers`**: Custom headers (Authorization, API keys, etc.)
- **`batch_size`**: Records per batch (prevents large payloads)
- **`retry_on_failure`**: Auto-retry failed requests
- **`save_failed_requests`**: Save failed requests to file for manual retry
- **`database.enabled`**: Set to `false` to disable database (API only)

### Step 3: Configure Devices

Update device IPs in `config.json`:

```json
{
    "devices": [
        {
            "ip": "192.168.1.100",
            "port": 4370,
            "name": "Main Gate",
            "enabled": true
        }
    ]
}
```

### Step 4: Test API Connection

```cmd
php\php.exe test_api.php
```

This will:
- Test API connectivity
- Send sample data
- Verify authentication
- Check response codes

### Step 5: Test Device Connection

```cmd
php\php.exe test_connection.php
```

### Step 6: Install Service

Download NSSM from: https://nssm.cc/download

Run as **Administrator**:

```cmd
install_service.bat
```

The script will automatically use standalone PHP from `php\php.exe`

---

## API Request Format

### Request Payload

The service sends data in this format:

```json
{
    "device_name": "Main Gate",
    "timestamp": "2026-01-19 05:30:00",
    "batch_number": 1,
    "total_batches": 1,
    "records": [
        {
            "uid": 1,
            "id": "EMP001",
            "user_id": "EMP001",
            "state": 1,
            "state_name": "Fingerprint",
            "timestamp": "2026-01-19 08:15:32",
            "type": 0,
            "type_name": "Check-in",
            "device_name": "Main Gate",
            "synced_at": "2026-01-19 08:20:00"
        }
    ]
}
```

### Field Descriptions

| Field | Type | Description |
|-------|------|-------------|
| `device_name` | string | Device identifier |
| `timestamp` | datetime | Sync timestamp |
| `batch_number` | int | Current batch number |
| `total_batches` | int | Total batches in this sync |
| `records` | array | Attendance records |
| `uid` | int | User ID from device |
| `id` | string | Employee/User ID |
| `state` | int | Auth method (0=Password, 1=Fingerprint, 2=Card) |
| `state_name` | string | Human-readable auth method |
| `timestamp` | datetime | Actual check-in/out time |
| `type` | int | Type (0=Check-in, 1=Check-out, 4=OT-in, 5=OT-out) |
| `type_name` | string | Human-readable type |
| `synced_at` | datetime | When synced to API |

### Expected Response

Your API should respond with HTTP status:

- **200-299**: Success (data received)
- **400**: Bad request (invalid data)
- **401**: Unauthorized (check headers)
- **500**: Server error (will retry if enabled)

---

## Example API Endpoint (PHP)

Here's a sample API endpoint to receive the data:

```php
<?php
// api/attendance/sync.php

header('Content-Type: application/json');

// Validate authorization
$headers = getallheaders();
if (!isset($headers['Authorization']) || $headers['Authorization'] !== 'Bearer YOUR_API_TOKEN') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate data
if (!isset($data['records']) || !is_array($data['records'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data format']);
    exit;
}

// Process records
$deviceName = $data['device_name'] ?? 'Unknown';
$records = $data['records'];

// Save to your database
try {
    $pdo = new PDO('mysql:host=localhost;dbname=attendance', 'user', 'pass');
    
    foreach ($records as $record) {
        $sql = "INSERT INTO attendance_logs 
                (device_name, user_id, timestamp, state, type) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE synced_at = NOW()";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $record['device_name'],
            $record['id'],
            $record['timestamp'],
            $record['state'],
            $record['type']
        ]);
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Received ' . count($records) . ' records',
        'device' => $deviceName,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
```

---

## Testing

### Test API Connection

```cmd
php php\php.exe test_api.php
```

### Test Device Connection

```cmd
php php\php.exe test_connection.php
```

### Test Service Manually

```cmd
php php\php.exe service_worker.php
```

Press `Ctrl+C` to stop

### Test with cURL

```bash
curl -X POST "https://your-api-domain.com/api/attendance/sync" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -d '{"test":true,"timestamp":"2026-01-19 05:30:00"}'
```

---

## Service Management

### Start Service
```cmd
net start ZkTecoSync
```

### Stop Service
```cmd
net stop ZkTecoSync
```

### Restart Service
```cmd
nssm restart ZkTecoSync
```

### View Logs
```cmd
type logs\service.log
```

### View Failed Requests
```cmd
type logs\failed_api_requests.json
```

---

## Failed Request Handling

If API requests fail, they are saved to:
```
logs/failed_api_requests.json
```

The service will automatically retry these on the next cycle.

### Manual Retry

You can manually retry failed requests:

```php
<?php
require_once 'ServiceLogger.php';
require_once 'ApiClient.php';

$config = json_decode(file_get_contents('config.json'), true);
$logger = new ServiceLogger($config['logging']);
$api = new ApiClient($config['api'], $logger);

$retriedCount = $api->retryFailedRequests();
echo "Retried $retriedCount requests\n";
```

---

## Troubleshooting

### API Connection Fails

1. Test endpoint manually:
   ```cmd
   curl -v https://your-api-domain.com/api/attendance/sync
   ```

2. Check firewall allows outbound HTTPS

3. Verify SSL certificates:
   ```json
   "timeout": 60
   ```

4. Check logs:
   ```cmd
   type logs\service.log
   ```

### PHP Not Found

If using standalone PHP:

```cmd
REM Verify PHP installation
php\php.exe -v

REM Enable required extensions in php\php.ini
extension=curl
extension=openssl
extension=sockets
```

### Service Won't Start

1. Check PHP path in `install_service.bat`
2. Run manually to see errors:
   ```cmd
   php\php.exe service_worker.php
   ```
3. Check logs:
   ```cmd
   type logs\service_stderr.log
   ```

---

## Configuration Examples

### API Only (No Database)

```json
{
    "api": {
        "enabled": true,
        "endpoint": "https://api.example.com/attendance"
    },
    "database": {
        "enabled": false
    }
}
```

### API + Database (Both)

```json
{
    "api": {
        "enabled": true,
        "endpoint": "https://api.example.com/attendance"
    },
    "database": {
        "enabled": true,
        "host": "localhost",
        "name": "attendance_db"
    }
}
```

### Database Only (No API)

```json
{
    "api": {
        "enabled": false
    },
    "database": {
        "enabled": true
    }
}
```

---

## Performance Tips

1. **Batch Size**: Adjust based on API limits
   ```json
   "batch_size": 50
   ```

2. **Timeout**: Increase for slow connections
   ```json
   "timeout": 60
   ```

3. **Sync Interval**: Change as needed
   ```json
   "sync_interval": 180
   ```

4. **Clear Device Memory**: After successful sync
   ```json
   "clear_device_after_sync": true
   ```

---

## Support

For issues:
1. Check `logs/service.log`
2. Run `test_api.php` to test API
3. Run `test_connection.php` to test devices
4. Review this documentation

---

## License

This service is provided as-is for ZKTeco device integration.
