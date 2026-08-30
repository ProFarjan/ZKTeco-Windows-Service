<?php
/**
 * ZKTeco Windows Service Worker
 * Continuously syncs attendance data from ZKTeco devices to database
 *
 * Usage: php service_worker.php
 */

// Suppress deprecation warnings when running as service
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_time_limit(0);

require_once 'Zkteco.php';
require_once 'ServiceLogger.php';
require_once 'DatabaseManager.php';
require_once 'ApiClient.php';

class ZkTecoServiceWorker
{
    private $config;
    private $logger;
    private $db;
    private $api;
    private $running = true;
    private $lastSyncTimes = [];
    private $lastApiSyncTimes = [];

    // Results of commands executed this cycle, keyed by device name —
    // reported on the NEXT cycle's API call rather than a second
    // round-trip in the same cycle. See executeDeviceCommand().
    private $pendingCommandResults = [];

    public function __construct()
    {
        $this->loadConfig();
        $this->logger = new ServiceLogger($this->config['logging']);
        $this->db = new DatabaseManager($this->config['database'], $this->logger);
        $this->api = new ApiClient($this->config['api'], $this->logger);

        // Handle graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
        }
    }

    private function loadConfig()
    {
        $configFile = __DIR__ . '/config.json';

        if (!file_exists($configFile)) {
            die("Configuration file not found: $configFile\n");
        }

        $this->config = json_decode(file_get_contents($configFile), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            die("Invalid JSON in configuration file: " . json_last_error_msg() . "\n");
        }

        // Create logs directory if not exists
        $logDir = dirname($this->config['logging']['file']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public function run()
    {
        $this->logger->info("=== ZKTeco Service Worker Started ===");
        $this->logger->info("Monitoring " . count($this->config['devices']) . " device(s)");
        $this->logger->info("Sync Interval: " . $this->config['sync_interval'] . " seconds (Every 5 minutes)");
        $this->logger->info("API Enabled: " . ($this->config['api']['enabled'] ? 'Yes' : 'No'));
        $this->logger->info("Database Enabled: " . ($this->config['database']['enabled'] ? 'Yes' : 'No'));

        // Initialize database if enabled
        if ($this->config['database']['enabled']) {
            if (!$this->db->initialize()) {
                $this->logger->error("Failed to initialize database. Exiting...");
                return;
            }
        }

        // Test API connection if enabled
        if ($this->config['api']['enabled']) {
            $this->logger->info("Testing API connection...");
            $this->api->testConnection();
        }

        while ($this->running) {
            $startTime = microtime(true);

            $this->processDevices();

            $executionTime = microtime(true) - $startTime;
            $sleepTime = max(1, $this->config['sync_interval'] - $executionTime);

            $this->logger->debug("Cycle completed in " . round($executionTime, 2) . "s. Sleeping for " . round($sleepTime, 2) . "s");

            // Sleep in small intervals to allow signal handling
            $this->sleep($sleepTime);

            // Handle signals if pcntl is available
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }

        $this->logger->info("=== ZKTeco Service Worker Stopped ===");
    }

    private function processDevices()
    {
        foreach ($this->config['devices'] as $device) {
            if (!$device['enabled']) {
                continue;
            }

            try {
                $this->syncDevice($device);
            } catch (Exception $e) {
                $this->logger->error("Device [{$device['name']}]: " . $e->getMessage());
            }
        }
    }

    private function syncDevice($deviceConfig)
    {
        $deviceName = $deviceConfig['name'];
        $this->logger->info("Syncing device: $deviceName ({$deviceConfig['ip']})");

        $zk = new Zkteco();
        $zk->initialize([
            'ip' => $deviceConfig['ip'],
            'port' => $deviceConfig['port']
        ]);

        // Connect with retry logic
        if (!$this->connectWithRetry($zk, $deviceName)) {
            $this->logger->error("Device [$deviceName]: Failed to connect after {$this->config['max_retry_attempts']} attempts");
            return;
        }

        try {
            // Get device information
            $deviceInfo = $this->getDeviceInfo($zk);
            $this->logger->debug("Device [$deviceName]: " . json_encode($deviceInfo));

            // Sync attendance records (also pushes/pulls commands via the
            // same API call — see syncAttendance())
            $attendanceCount = $this->syncAttendance($zk, $deviceName);

            // Sync users (optional, less frequent, only if database is enabled)
            if ($this->config['database']['enabled'] && $this->shouldSyncUsers($deviceName)) {
                $userCount = $this->syncUsers($zk, $deviceName);
                $this->logger->info("Device [$deviceName]: Synced $userCount user(s)");
            }

            $this->lastSyncTimes[$deviceName] = time();
            $this->logger->info("Device [$deviceName]: Successfully synced $attendanceCount attendance record(s)");

        } catch (Exception $e) {
            $this->logger->error("Device [$deviceName]: Sync error - " . $e->getMessage());
        } finally {
            $zk->disconnect();
        }
    }

    private function connectWithRetry($zk, $deviceName)
    {
        $maxAttempts = $this->config['max_retry_attempts'];
        $retryDelay = $this->config['retry_delay'];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($zk->connect()) {
                if ($attempt > 1) {
                    $this->logger->info("Device [$deviceName]: Connected on attempt $attempt");
                }
                return true;
            }

            if ($attempt < $maxAttempts) {
                $this->logger->warning("Device [$deviceName]: Connection attempt $attempt failed, retrying in {$retryDelay}s...");
                sleep($retryDelay);
            }
        }

        return false;
    }

    private function getDeviceInfo($zk)
    {
        return [
            'serial' => $zk->serialNumber(),
            'device_name' => $zk->deviceName(),
            'firmware' => $zk->version(),
            'platform' => $zk->platform()
        ];
    }

    private function syncAttendance($zk, $deviceName)
    {
        $attendance = $zk->getAttendance();

        $enrichedRecords = [];
        if (!empty($attendance)) {
            foreach ($attendance as $record) {
                $record['device_name'] = $deviceName;
                $record['synced_at'] = date('Y-m-d H:i:s');
                $record['state_name'] = ZkUtil::getAttState($record['state']);
                $record['type_name'] = ZkUtil::getAttType($record['type']);
                $enrichedRecords[] = $record;
            }
        } else {
            $this->logger->debug("Device [$deviceName]: No new attendance records");
        }

        $saved = 0;

        // Save to database if enabled
        if ($this->config['database']['enabled'] && !empty($enrichedRecords)) {
            foreach ($enrichedRecords as $record) {
                if ($this->db->saveAttendance($record)) {
                    $saved++;
                }
            }
            $this->logger->info("Device [$deviceName]: Saved $saved records to database");
        }

        // Send to API if enabled — always makes at least one call (even
        // with zero records) so pending command results get delivered and
        // any newly-queued commands get picked up, not just when there
        // happens to be fresh attendance. A backend that doesn't return
        // "commands" is unaffected by this — see ApiClient::sendAttendanceBatch().
        if ($this->config['api']['enabled']) {
            $commandResults = isset($this->pendingCommandResults[$deviceName])
                ? $this->pendingCommandResults[$deviceName]
                : [];
            $this->pendingCommandResults[$deviceName] = [];

            $apiResult = $this->api->sendAttendanceBatch($enrichedRecords, $deviceName, $commandResults);

            if (!empty($apiResult['success'])) {
                if (!empty($enrichedRecords)) {
                    $this->logger->info("Device [$deviceName]: Sent " . count($enrichedRecords) . " records to API");
                }
                $this->lastApiSyncTimes[$deviceName] = time();

                if (!empty($apiResult['commands'])) {
                    $this->logger->info("Device [$deviceName]: received " . count($apiResult['commands']) . " command(s)");

                    foreach ($apiResult['commands'] as $command) {
                        $result = $this->executeDeviceCommand($zk, $command);
                        $result['id'] = $command['id'];
                        $this->pendingCommandResults[$deviceName][] = $result;

                        $this->logger->info(
                            "Device [$deviceName]: command #{$command['id']} ({$command['type']}) " .
                            ($result['success'] ? 'succeeded' : ('failed: ' . $result['message']))
                        );
                    }
                }
            } else {
                if (!empty($enrichedRecords)) {
                    $this->logger->error("Device [$deviceName]: Failed to send records to API");
                }
                // Put back whatever results we were trying to deliver so
                // they're retried next cycle instead of silently lost.
                $this->pendingCommandResults[$deviceName] = $commandResults;
            }
        }

        // Clear device memory if configured and data was successfully processed
        if ($this->config['clear_device_after_sync'] && (($saved > 0 && $this->config['database']['enabled']) || ($this->config['api']['enabled']))) {
            $zk->clearAttendance();
            $this->logger->info("Device [$deviceName]: Cleared attendance memory");
        }

        return count($enrichedRecords);
    }

    /**
     * Executes one device-management command received from the API
     * (create_user/update_user/delete_user/list_users) using the same
     * Zkteco connection already open for this sync cycle. Any project's
     * backend can trigger this simply by returning a "commands" array in
     * its API response — nothing device-specific needs to change per
     * project, they all go through this one path.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    private function executeDeviceCommand($zk, array $command)
    {
        $type = $command['type'];
        $payload = !empty($command['payload']) ? $command['payload'] : [];

        try {
            switch ($type) {
                case 'list_users':
                    $users = $zk->getUser();
                    return [
                        'success' => true,
                        'message' => count($users) . ' user(s) found.',
                        'data' => ['users' => array_values($users)],
                    ];

                case 'delete_user':
                    if (empty($payload['user_id'])) {
                        return ['success' => false, 'message' => 'Missing user_id in command payload.'];
                    }

                    $uid = $this->resolveUid($zk, $payload['user_id']);

                    if (!$uid) {
                        return ['success' => false, 'message' => 'User not found on device: ' . $payload['user_id']];
                    }

                    $ok = $zk->removeUser($uid);

                    return $ok !== false
                        ? ['success' => true, 'message' => 'User deleted from device.']
                        : ['success' => false, 'message' => 'Device rejected the delete request.'];

                case 'create_user':
                case 'update_user':
                    if (empty($payload['user_id'])) {
                        return ['success' => false, 'message' => 'Missing user_id in command payload.'];
                    }

                    $uid = $this->resolveUid($zk, $payload['user_id']);

                    if (!$uid) {
                        if ($type === 'update_user') {
                            return ['success' => false, 'message' => 'User not found on device: ' . $payload['user_id']];
                        }
                        $uid = $this->nextAvailableUid($zk);
                    }

                    $name = isset($payload['name']) ? substr($payload['name'], 0, 24) : $payload['user_id'];
                    $password = isset($payload['password']) ? substr($payload['password'], 0, 8) : '';
                    $role = isset($payload['role']) ? (int)$payload['role'] : ZkUtil::LEVEL_USER;
                    $cardNo = isset($payload['card_no']) ? (int)$payload['card_no'] : 0;

                    $ok = $zk->setUser($uid, $payload['user_id'], $name, $password, $role, $cardNo);
                    $verb = $type === 'create_user' ? 'created' : 'updated';

                    return $ok !== false
                        ? ['success' => true, 'message' => "User $verb.", 'data' => ['uid' => $uid]]
                        : ['success' => false, 'message' => "Device rejected the $type request."];

                default:
                    return ['success' => false, 'message' => 'Unknown command type: ' . $type];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function resolveUid($zk, $userId)
    {
        $users = $zk->getUser();

        foreach ($users as $u) {
            if ((string)$u['userid'] === (string)$userId) {
                return $u['uid'];
            }
        }

        return null;
    }

    private function nextAvailableUid($zk)
    {
        $users = $zk->getUser();
        $max = 0;

        foreach ($users as $u) {
            if ($u['uid'] > $max) {
                $max = $u['uid'];
            }
        }

        return $max + 1;
    }

    private function syncUsers($zk, $deviceName)
    {
        $users = $zk->getUser();

        if (empty($users)) {
            return 0;
        }

        $saved = 0;
        foreach ($users as $user) {
            $user['device_name'] = $deviceName;
            $user['synced_at'] = date('Y-m-d H:i:s');

            if ($this->db->saveUser($user)) {
                $saved++;
            }
        }

        return $saved;
    }

    private function shouldSyncUsers($deviceName)
    {
        // Sync users every 10 minutes (or 10 cycles if sync_interval is 60s)
        $lastSync = isset($this->lastSyncTimes[$deviceName]) ? $this->lastSyncTimes[$deviceName] : 0;
        return (time() - $lastSync) >= 600;
    }

    private function sleep($seconds)
    {
        $endTime = time() + $seconds;

        while (time() < $endTime && $this->running) {
            sleep(1);
        }
    }

    public function shutdown()
    {
        $this->logger->info("Shutdown signal received. Stopping gracefully...");
        $this->running = false;
    }
}

// Start the service worker
try {
    $worker = new ZkTecoServiceWorker();
    $worker->run();
} catch (Exception $e) {
    echo "Fatal error: - service_worker.php:437" . $e->getMessage() . "\n";
    exit(1);
}
