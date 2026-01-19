# ZKTeco Windows Service - Setup Guide

This service continuously synchronizes attendance data from ZKTeco biometric devices to a MySQL database.

## Prerequisites

1. **NSSM (Non-Sucking Service Manager)**
   - Download from: https://nssm.cc/download
   - Extract `nssm.exe` to `C:\Windows\System32` or add to PATH
   - Choose correct version: win32 or win64

2. **PHP** (already installed with WAMP)
   - Located at: `C:\wamp64\bin\php\php7.4.33\php.exe`

3. **MySQL Database**
   - Ensure MySQL service is running
   - Create database: `attendance_db`

4. **Network Access**
   - Devices must be accessible via TCP/IP
   - Default port: 4370

## Installation Steps

### Step 1: Configure Devices

Edit `config.json` and update device information:

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

### Step 2: Configure Database

Update database settings in `config.json`:

```json
{
    "database": {
        "host": "localhost",
        "name": "attendance_db",
        "user": "root",
        "password": "your_password"
    }
}
```

### Step 3: Test Connection

Before installing service, test device connectivity:

```cmd
php test_connection.php
```

This will verify:
- Device connectivity
- Network accessibility
- Device information retrieval
- Data availability

### Step 4: Install Service

Run as **Administrator**:

```cmd
install_service.bat
```

This will:
- Install the Windows service
- Configure automatic startup
- Set up logging
- Ask if you want to start immediately

### Step 5: Verify Service

Check service status:

```cmd
sc query ZkTecoSync
```

Or use Windows Services Manager (`services.msc`)

## Service Management

### Start Service
```cmd
net start ZkTecoSync
```
or
```cmd
nssm start ZkTecoSync
```

### Stop Service
```cmd
net stop ZkTecoSync
```
or
```cmd
nssm stop ZkTecoSync
```

### Restart Service
```cmd
nssm restart ZkTecoSync
```

### Check Status
```cmd
sc query ZkTecoSync
```

### View Logs
```cmd
type logs\service.log
```

### Uninstall Service
Run as Administrator:
```cmd
uninstall_service.bat
```

## Configuration Options

### Sync Interval
```json
"sync_interval": 60  // seconds between sync cycles
```

### Clear Device After Sync
```json
"clear_device_after_sync": false  // true to clear device memory
```

### Retry Settings
```json
"max_retry_attempts": 3,
"retry_delay": 5  // seconds between retries
```

### Logging
```json
"logging": {
    "enabled": true,
    "level": "INFO",  // DEBUG, INFO, WARNING, ERROR
    "max_size_mb": 10,
    "keep_files": 7
}
```

## Database Schema

### attendance_logs
Stores all attendance records:
- `device_name`: Device identifier
- `uid`: User ID from device
- `user_id`: Employee/User ID
- `timestamp`: Check-in/out time
- `state`: Authentication method (0=Password, 1=Fingerprint, 2=Card)
- `type`: Record type (0=Check-in, 1=Check-out, 4=OT-in, 5=OT-out)

### device_users
Stores user information:
- `device_name`: Device identifier
- `uid`: User ID from device
- `user_id`: Employee/User ID
- `name`: User name
- `role`: User role (0=User, 14=Admin)
- `cardno`: Card number if applicable

### sync_status
Tracks synchronization status:
- `device_name`: Device identifier
- `last_sync`: Last successful sync time
- `last_attendance_count`: Records synced
- `error_message`: Last error if any

## Troubleshooting

### Service Won't Start

1. Check logs:
   ```cmd
   type logs\service.log
   type logs\service_stderr.log
   ```

2. Test manually:
   ```cmd
   php service_worker.php
   ```

3. Verify PHP path in `install_service.bat`

### Connection Issues

1. Test network connectivity:
   ```cmd
   ping 192.168.1.100
   ```

2. Check device IP and port in `config.json`

3. Verify firewall settings

4. Run connection test:
   ```cmd
   php test_connection.php
   ```

### Database Errors

1. Check MySQL service is running
2. Verify credentials in `config.json`
3. Ensure database exists
4. Check user permissions

### No Data Being Synced

1. Check if devices have new records
2. Verify `enabled: true` in config
3. Check sync interval setting
4. Review logs for errors

## Advanced Configuration

### Multiple Devices

Add multiple devices in `config.json`:

```json
"devices": [
    {"ip": "192.168.1.100", "port": 4370, "name": "Gate 1", "enabled": true},
    {"ip": "192.168.1.101", "port": 4370, "name": "Gate 2", "enabled": true},
    {"ip": "192.168.1.102", "port": 4370, "name": "Office", "enabled": true}
]
```

### Remote Database

```json
"database": {
    "host": "192.168.1.200",
    "port": 3306,
    "name": "attendance_db",
    "user": "remote_user",
    "password": "secure_password"
}
```

### Custom Sync Schedule

For different sync intervals per device, modify `service_worker.php`:

```php
private function shouldSync($deviceName) {
    $intervals = [
        'Main Gate' => 30,  // 30 seconds
        'Office Floor' => 120  // 2 minutes
    ];
    // Implementation...
}
```

## Monitoring

### View Live Logs
```cmd
powershell Get-Content logs\service.log -Wait -Tail 20
```

### Service Performance
Check Windows Event Viewer:
- Application and Services Logs → ZkTecoSync

### Database Monitoring
```sql
-- Check recent syncs
SELECT * FROM sync_status ORDER BY last_sync DESC;

-- Today's attendance
SELECT * FROM attendance_logs 
WHERE DATE(timestamp) = CURDATE() 
ORDER BY timestamp DESC;

-- Active users per device
SELECT device_name, COUNT(*) as user_count 
FROM device_users 
GROUP BY device_name;
```

## Security Notes

1. **Database Credentials**: Store securely, use strong passwords
2. **Network Security**: Use VLANs or firewalls for device network
3. **File Permissions**: Restrict access to config.json
4. **Regular Backups**: Backup database regularly

## Support

For issues or questions:
1. Check logs in `logs/service.log`
2. Review this README
3. Test connection with `test_connection.php`
4. Check ZKTeco device manual

## License

This service is provided as-is for integration with ZKTeco biometric devices.
