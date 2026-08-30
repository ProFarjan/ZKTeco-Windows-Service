# ZKTeco Windows Service - Complete Setup Guide

## 📦 Project Structure

```
windows_services/
├── nssm.exe                    ✓ Local NSSM (no system install needed)
├── php/                        ✓ Standalone PHP (no WAMP dependency)
│   ├── php.exe
│   ├── php.ini
│   └── ext/                   (PHP extensions)
├── config.json                 ⚙️ Service configuration
├── service_worker.php          🔄 Main service worker
├── install_service.bat         📥 Install service
├── uninstall_service.bat       📤 Uninstall service
├── test_connection.php         🧪 Test device connectivity
├── test_api.php               🧪 Test API connectivity
├── check_php.bat              🔍 PHP diagnostics
├── fix_php_extensions.bat     🔧 Fix PHP issues
└── logs/                      📝 Service logs
    ├── service.log
    ├── service_stdout.log
    └── service_stderr.log
```

---

## 🚀 Quick Start (5 Steps)

### Step 1: Download PHP (if not already done)

```cmd
download_php.bat
```

This downloads PHP 8.2 to `php/` folder (standalone, no WAMP needed)

---

### Step 2: Configure Your API & Devices

Edit `config.json`:

```json
{
    "sync_interval": 300,
    "devices": [
        {
            "ip": "192.168.1.100",
            "port": 4370,
            "name": "Main Gate",
            "enabled": true
        }
    ],
    "api": {
        "enabled": true,
        "endpoint": "https://your-api.com/attendance/sync",
        "headers": {
            "Authorization": "Bearer YOUR_TOKEN"
        },
        "custom_params": {
            "company_id": "YOUR_COMPANY_ID"
        }
    }
}
```

---

### Step 3: Test Connections

**Test Device:**
```cmd
php\php.exe test_connection.php
```

**Test API:**
```cmd
php\php.exe test_api.php
```

---

### Step 4: Install Windows Service

**Right-click** → **Run as Administrator**:
```cmd
install_service.bat
```

Output:
```
[1/6] Checking prerequisites...
[OK] NSSM found at: C:\wamp64\www\windows_services\nssm.exe
[OK] PHP found at: C:\wamp64\www\windows_services\php\php.exe

[2/6] Verifying PHP installation...
[OK] PHP version: PHP 8.2.30

[3/6] Checking PHP extensions...
[OK] Required extensions loaded

[4/6] Checking existing service...
[OK] No existing service found

[5/6] Installing Windows service...
[OK] Service installed successfully

[6/6] Configuring service...
[OK] Service configured

Installation Complete! ✓
```

---

### Step 5: Start Service

```cmd
net start ZkTecoSync
```

Or the installer will ask to start automatically.

---

## ✅ What's Included (No External Dependencies)

### ✓ Local NSSM (`nssm.exe`)
- Already in project folder
- No system PATH modification needed
- No separate download required
- Version: 2.24 64-bit

### ✓ Standalone PHP (`php/`)
- Self-contained PHP installation
- No WAMP/XAMPP required
- Includes all required extensions
- Portable and isolated

### ✓ All Scripts Included
- Installation, uninstallation, testing
- Diagnostics and troubleshooting tools
- Everything runs from project folder

---

## 🎯 Features

### Service Configuration
- ✅ **5-minute sync** (configurable)
- ✅ **Multiple devices** support
- ✅ **API integration** with custom parameters
- ✅ **Field mapping** (rename fields)
- ✅ **Batch processing** (configurable size)
- ✅ **Retry logic** on failures
- ✅ **Failed request storage**
- ✅ **Auto-restart** on crashes
- ✅ **Database optional** (API-only mode)

### Data Sync
- Syncs attendance records every 5 minutes
- Supports check-in, check-out, overtime
- Multiple authentication types (fingerprint, password, card)
- Automatic device reconnection
- Error logging and monitoring

---

## 📋 Service Management

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
nssm.exe restart ZkTecoSync
```

### Check Status
```cmd
sc query ZkTecoSync
```

### View Logs
```cmd
type logs\service.log
```

### Monitor in Real-Time
```cmd
powershell Get-Content logs\service.log -Wait -Tail 20
```

### Uninstall Service
```cmd
uninstall_service.bat
```

---

## 🔧 Troubleshooting

### Problem: PHP Extension Warnings

**Solution:**
```cmd
fix_php_extensions.bat
```

This fixes:
- Wrong extension paths
- Missing Visual C++ libraries
- Incorrect php.ini settings

---

### Problem: NSSM Not Found

**Check:**
```cmd
dir nssm.exe
```

If missing, download from: https://nssm.cc/download
Extract to project folder.

---

### Problem: Service Won't Start

**Check:**
1. Test manually:
   ```cmd
   php\php.exe service_worker.php
   ```

2. Check logs:
   ```cmd
   type logs\service_stderr.log
   ```

3. Verify config:
   ```cmd
   php\php.exe -r "print_r(json_decode(file_get_contents('config.json')));"
   ```

---

### Problem: Can't Connect to Device

**Test connection:**
```cmd
php\php.exe test_connection.php
```

**Check:**
- Device IP address correct
- Network connectivity (can you ping the device?)
- Firewall not blocking port 4370
- Device is powered on

---

### Problem: API Requests Failing

**Test API:**
```cmd
php\php.exe test_api.php
```

**Check:**
- API endpoint URL correct
- Authorization headers valid
- Network allows outbound HTTPS
- API is accessible

---

## 📊 Monitoring

### Service Status
```cmd
sc query ZkTecoSync
```

### View Latest Logs
```cmd
type logs\service.log
```

### Live Log Monitoring
```cmd
powershell Get-Content logs\service.log -Wait -Tail 20
```

### Check Failed API Requests
```cmd
type logs\failed_api_requests.json
```

### Windows Services Manager
```cmd
services.msc
```
Look for: **ZKTeco Attendance Sync Service**

---

## 🗂️ Configuration Files

### `config.json` - Main Configuration
- Device IPs and settings
- API endpoint and authentication
- Sync interval (5 minutes = 300 seconds)
- Custom parameters and field mapping
- Database settings (optional)

### `php\php.ini` - PHP Configuration
- Extension settings
- Memory limits
- Timeouts

---

## 🔄 Device-Management Commands (built into the `api` block)

Any backend behind `api.endpoint` can optionally also manage device users —
this is a core capability, not a separate mode, so it works for every
project using this codebase with **zero extra config**. No new fields, no
separate toggle.

Each sync cycle, the existing attendance push (`ApiClient::sendAttendanceBatch()`)
already:
1. POSTs attendance for the device to `api.endpoint` (an empty batch if
   there's nothing new — this heartbeat is what lets step 2 below happen
   even on a quiet day with no attendance).
2. Also sends `command_results` for anything executed last cycle, if any.
3. Reads the JSON response — if it contains a top-level `commands` array,
   the worker executes each one against the device this cycle
   (`create_user`/`update_user`/`delete_user`/`list_users`, via the
   existing `Zkteco::setUser()/removeUser()/getUser()`) and reports results
   on the *next* cycle's push (step 2 above).

A backend that never returns `commands` (like this deployment's hospital
POS endpoint) simply never triggers any of this — it's a no-op, and nothing
about the existing attendance-only flow changes.

**Request/response shape**, for a backend that wants to opt in:

```json
// POST to api.endpoint
{
  "device_name": "Main Gate",
  "records": [ { "...": "attendance record after field_mapping" } ],
  "command_results": [
    { "id": 5, "success": true, "message": "User created.", "data": { "uid": 12 } }
  ]
}
```
```json
// Response
{
  "success": true,
  "commands": [
    { "id": 6, "type": "delete_user", "payload": { "user_id": "1042" } }
  ]
}
```

Command `type` is one of `list_users` / `create_user` / `update_user` /
`delete_user`. For create/update, `payload` may include `name`, `password`,
`role`, `card_no` alongside `user_id`.

---

## 📁 Log Files

### `logs/service.log`
Main application log with:
- Service start/stop events
- Device sync activities
- API request/response logs
- Errors and warnings

### `logs/service_stdout.log`
Standard output from PHP

### `logs/service_stderr.log`
Error output from PHP (helpful for crashes)

### `logs/failed_api_requests.json`
Failed API requests for manual retry

---

## 🔄 Updating Configuration

1. Edit `config.json`
2. Restart service:
   ```cmd
   nssm.exe restart ZkTecoSync
   ```

No need to reinstall service for config changes!

---

## 🆘 Support & Documentation

### Available Documentation
- `README_SERVICE.md` - General service documentation
- `README_API.md` - API integration guide
- `README_CUSTOM_API.md` - Custom parameters guide
- `README_PHP_FIX.md` - PHP troubleshooting
- `config_examples.json` - Configuration examples

### Diagnostic Tools
```cmd
check_php.bat              # PHP diagnostics
test_connection.php        # Test device connectivity
test_api.php              # Test API connectivity
fix_php_extensions.bat    # Fix PHP issues
```

---

## 🎯 Deployment Checklist

- [ ] NSSM exists in project folder (`nssm.exe`)
- [ ] PHP downloaded and configured (`php/`)
- [ ] No PHP warnings (`php\php.exe -v`)
- [ ] Config file updated (`config.json`)
- [ ] Device connection tested (`test_connection.php`)
- [ ] API connection tested (`test_api.php`)
- [ ] Service installed (`install_service.bat`)
- [ ] Service running (`sc query ZkTecoSync`)
- [ ] Logs showing activity (`logs/service.log`)

---

## 💡 Tips

1. **Portable Setup**: Copy entire folder to another machine - it just works!
2. **No Admin for Updates**: Only service install/uninstall needs admin
3. **Test Before Service**: Always test manually first
4. **Monitor Logs**: Use real-time log viewing during setup
5. **Backup Config**: Keep a copy of `config.json`

---

## 📝 Notes

- Service runs as **Local System** account
- Automatically starts on Windows boot
- Auto-restarts on crashes (5-second delay)
- No external dependencies (fully portable)
- All paths are relative (easy to move/copy)

---

## ✨ Version

- **PHP**: 8.2.30
- **NSSM**: 2.24 64-bit
- **Service**: ZkTecoSync v1.0

---

## 📞 Quick Commands Reference

```cmd
# Installation
install_service.bat

# Service Control
net start ZkTecoSync
net stop ZkTecoSync
nssm.exe restart ZkTecoSync

# Testing
php\php.exe test_connection.php
php\php.exe test_api.php
check_php.bat

# Monitoring
type logs\service.log
powershell Get-Content logs\service.log -Wait

# Troubleshooting
fix_php_extensions.bat
sc query ZkTecoSync

# Uninstall
uninstall_service.bat
```

---

**Everything runs from this folder - no system modifications needed!** 🎉
