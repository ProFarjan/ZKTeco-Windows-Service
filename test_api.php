<?php
/**
 * Test API Connection
 * Tests sending data to configured API endpoint
 * 
 * Usage: php test_api.php
 */

require_once 'ServiceLogger.php';
require_once 'ApiClient.php';

echo "======================================\n";
echo "API Connection Test\n";
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

// Check if API is enabled
if (!$config['api']['enabled']) {
    die("ERROR: API is disabled in config.json. Set 'api.enabled' to true\n");
}

// Create logger
$loggerConfig = $config['logging'];
$loggerConfig['file'] = 'logs/test_api.log';
$logger = new ServiceLogger($loggerConfig);

// Create API client
$api = new ApiClient($config['api'], $logger);

echo "API Configuration:\n";
echo "  Endpoint: {$config['api']['endpoint']}\n";
echo "  Method: {$config['api']['method']}\n";
echo "  Timeout: {$config['api']['timeout']}s\n";
echo "  Batch Size: {$config['api']['batch_size']}\n";

if (!empty($config['api']['custom_params'])) {
    echo "  Custom Parameters:\n";
    foreach ($config['api']['custom_params'] as $key => $value) {
        echo "    - $key: $value\n";
    }
}

if (!empty($config['api']['field_mapping'])) {
    echo "  Field Mapping Enabled: Yes (" . count($config['api']['field_mapping']) . " fields)\n";
}

if (!empty($config['api']['include_fields'])) {
    echo "  Include Fields: " . implode(', ', $config['api']['include_fields']) . "\n";
}

if (!empty($config['api']['exclude_fields'])) {
    echo "  Exclude Fields: " . implode(', ', $config['api']['exclude_fields']) . "\n";
}

echo str_repeat("-", 40) . "\n\n";

// Test 1: Connection Test
echo "Test 1: Testing API Connection...\n";
if ($api->testConnection()) {
    echo "✓ API connection successful!\n\n";
} else {
    echo "❌ API connection failed!\n";
    echo "Check logs/test_api.log for details\n\n";
}

// Test 2: Send Sample Attendance Data
echo "Test 2: Sending Sample Attendance Data...\n";

$sampleData = [
    [
        'uid' => 1,
        'id' => 'EMP001',
        'state' => 1,
        'state_name' => 'Fingerprint',
        'timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
        'type' => 0,
        'type_name' => 'Check-in',
        'device_name' => 'Test Device',
        'synced_at' => date('Y-m-d H:i:s')
    ],
    [
        'uid' => 1,
        'id' => 'EMP001',
        'state' => 1,
        'state_name' => 'Fingerprint',
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 1,
        'type_name' => 'Check-out',
        'device_name' => 'Test Device',
        'synced_at' => date('Y-m-d H:i:s')
    ],
    [
        'uid' => 2,
        'id' => 'EMP002',
        'state' => 2,
        'state_name' => 'Card',
        'timestamp' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
        'type' => 0,
        'type_name' => 'Check-in',
        'device_name' => 'Test Device',
        'synced_at' => date('Y-m-d H:i:s')
    ]
];

echo "Sending " . count($sampleData) . " sample records...\n";

if ($api->sendAttendanceBatch($sampleData, 'Test Device')) {
    echo "✓ Sample data sent successfully!\n\n";
} else {
    echo "❌ Failed to send sample data\n";
    echo "Check logs/test_api.log for details\n\n";
}

// Test 3: Test Batch Sending (if batch size is smaller)
if ($config['api']['batch_size'] < 10) {
    echo "Test 3: Testing Batch Processing...\n";
    
    $largeSampleData = [];
    for ($i = 1; $i <= 25; $i++) {
        $largeSampleData[] = [
            'uid' => $i,
            'id' => 'EMP' . str_pad($i, 3, '0', STR_PAD_LEFT),
            'state' => rand(0, 2),
            'state_name' => ['Password', 'Fingerprint', 'Card'][rand(0, 2)],
            'timestamp' => date('Y-m-d H:i:s', strtotime("-$i minutes")),
            'type' => rand(0, 1),
            'type_name' => ['Check-in', 'Check-out'][rand(0, 1)],
            'device_name' => 'Test Device',
            'synced_at' => date('Y-m-d H:i:s')
        ];
    }
    
    echo "Sending " . count($largeSampleData) . " records in batches...\n";
    
    if ($api->sendAttendanceBatch($largeSampleData, 'Test Device')) {
        echo "✓ Batch processing successful!\n\n";
    } else {
        echo "❌ Batch processing failed\n";
        echo "Check logs/test_api.log for details\n\n";
    }
}

echo "======================================\n";
echo "Test Complete!\n";
echo "======================================\n\n";

echo "Summary:\n";
echo "- Check logs/test_api.log for detailed logs\n";
echo "- If tests passed, your API is configured correctly\n";
echo "- If tests failed, check:\n";
echo "  1. API endpoint URL is correct\n";
echo "  2. API is accessible from this server\n";
echo "  3. Authorization headers are valid\n";
echo "  4. Firewall/network allows outbound connections\n";
echo "\n";

echo "Sample cURL command to test manually:\n";
echo "curl -X POST \"{$config['api']['endpoint']}\" \\\n";
foreach ($config['api']['headers'] as $key => $value) {
    echo "  -H \"$key: $value\" \\\n";
}
echo "  -d '{\"test\":true,\"timestamp\":\"" . date('Y-m-d H:i:s') . "\"}'\n\n";
