<?php
/**
 * Test connection to ZKTeco devices
 * Run this before installing the service to verify connectivity
 * 
 * Usage: php test_connection.php
 */

require_once 'Zkteco.php';

echo "======================================\n";
echo "ZKTeco Device Connection Test\n";
echo "======================================\n\n";

// Load configuration
$configFile = __DIR__ . '/config.json';
if (!file_exists($configFile)) {
    die("ERROR: Configuration file not found: $configFile\n");
}

$config = json_decode(file_get_contents($configFile), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("ERROR: Invalid JSON in configuration file\n");
}

$devices = $config['devices'];

foreach ($devices as $device) {
    if (!$device['enabled']) {
        echo "SKIPPED: {$device['name']} (disabled in config)\n\n";
        continue;
    }

    echo "Testing device: {$device['name']}\n";
    echo "IP Address: {$device['ip']}\n";
    echo "Port: {$device['port']}\n";
    echo str_repeat("-", 40) . "\n";

    $zk = new Zkteco();
    $zk->initialize([
        'ip' => $device['ip'],
        'port' => $device['port'],
        'protocol' => isset($device['protocol']) ? $device['protocol'] : 'UDP',
        'password' => isset($device['password']) ? $device['password'] : 0
    ]);

    // Test connection
    echo "Connecting... ";
    if (!$zk->connect()) {
        echo "FAILED\n";
        echo "❌ Unable to connect to device\n";
        echo "   Check: IP address, port, network connectivity\n\n";
        continue;
    }
    echo "SUCCESS ✓\n";

    // Get device information
    try {
        echo "\nDevice Information:\n";
        
        $serial = $zk->serialNumber();
        echo "  Serial Number: " . ($serial ?: 'N/A') . "\n";
        
        $deviceName = $zk->deviceName();
        echo "  Device Name: " . ($deviceName ?: 'N/A') . "\n";
        
        $firmware = $zk->version();
        echo "  Firmware: " . ($firmware ?: 'N/A') . "\n";
        
        $platform = $zk->platform();
        echo "  Platform: " . ($platform ?: 'N/A') . "\n";
        
        $time = $zk->getTime();
        echo "  Device Time: " . ($time ?: 'N/A') . "\n";

        // Get attendance count
        echo "\nData Summary:\n";
        $attendance = $zk->getAttendance();
        echo "  Attendance Records: " . count($attendance) . "\n";
        
        if (count($attendance) > 0) {
            echo "  Latest Record: " . $attendance[0]['timestamp'] . " - User: " . $attendance[0]['id'] . "\n";
        }

        // Get users count
        $users = $zk->getUser();
        echo "  Registered Users: " . count($users) . "\n";

        echo "\n✓ All tests passed for {$device['name']}\n";

    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n";
    }

    $zk->disconnect();
    echo "\n" . str_repeat("=", 40) . "\n\n";
}

echo "Test completed!\n";
echo "\nNext steps:\n";
echo "1. Update config.json with correct device IPs\n";
echo "2. Configure database settings in config.json\n";
echo "3. Run install_service.bat as Administrator\n";
echo "4. Start the service\n";
