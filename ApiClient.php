<?php
/**
 * API Client
 * Handles sending attendance data to external API endpoints
 */

class ApiClient
{
    private $config;
    private $logger;
    private $failedRequestsFile;

    public function __construct($config, ServiceLogger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->failedRequestsFile = __DIR__ . '/logs/failed_api_requests.json';
    }

    public function sendAttendance($attendanceData, $deviceName)
    {
        if (!$this->config['enabled']) {
            $this->logger->debug("API is disabled, skipping send");
            return true;
        }

        if (empty($attendanceData)) {
            return true;
        }

        // Apply custom parameters and field mapping
        $transformedRecords = $this->transformRecords($attendanceData);

        $payload = [
            'device_name' => $deviceName,
            'timestamp' => date('Y-m-d H:i:s'),
            'total_records' => count($transformedRecords),
            'records' => $transformedRecords
        ];

        // Merge custom params into payload
        if (!empty($this->config['custom_params'])) {
            $payload = array_merge($payload, $this->config['custom_params']);
        }

        $this->logger->info("Sending " . count($transformedRecords) . " records to API for device: $deviceName");

        $success = $this->sendRequest($payload);

        if (!$success && $this->config['save_failed_requests']) {
            $this->saveFailedRequest($payload);
        }

        return $success;
    }

    public function sendAttendanceBatch($attendanceData, $deviceName)
    {
        if (!$this->config['enabled']) {
            return true;
        }

        if (empty($attendanceData)) {
            return true;
        }

        // Apply custom parameters and field mapping
        $transformedRecords = $this->transformRecords($attendanceData);

        $batchSize = $this->config['batch_size'];
        $batches = array_chunk($transformedRecords, $batchSize);
        $successCount = 0;
        $totalBatches = count($batches);

        $this->logger->info("Sending " . count($transformedRecords) . " records in $totalBatches batch(es) to API");

        foreach ($batches as $index => $batch) {
            $batchNum = $index + 1;
            $payload = [
                'device_name' => $deviceName,
                'timestamp' => date('Y-m-d H:i:s'),
                'batch_number' => $batchNum,
                'total_batches' => $totalBatches,
                'records' => $batch
            ];

            // Merge custom params into payload
            if (!empty($this->config['custom_params'])) {
                $payload = array_merge($payload, $this->config['custom_params']);
            }

            if ($this->sendRequest($payload)) {
                $successCount++;
            } else {
                if ($this->config['save_failed_requests']) {
                    $this->saveFailedRequest($payload);
                }
            }

            // Small delay between batches to avoid overwhelming the API
            if ($batchNum < $totalBatches) {
                usleep(500000); // 0.5 seconds
            }
        }

        $this->logger->info("API batch send complete: $successCount/$totalBatches batches successful");
        return $successCount === $totalBatches;
    }

    private function sendRequest($payload)
    {
        $jsonData = json_encode($payload);
        $url = $this->config['endpoint'];
        $method = $this->config['method'];
        $timeout = $this->config['timeout'];

        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        // Set method
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        }

        // Set headers
        $headers = [];
        foreach ($this->config['headers'] as $key => $value) {
            $headers[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Execute request
        $startTime = microtime(true);
        $response = curl_exec($ch);
        $executionTime = round(microtime(true) - $startTime, 3);

        // Get request info
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Log response
        if ($response === false) {
            $this->logger->error("API request failed: $error (Time: {$executionTime}s)");
            return false;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->logger->info("API request successful: HTTP $httpCode (Time: {$executionTime}s)");
            $this->logger->debug("API Response: " . substr($response, 0, 200));
            return true;
        } else {
            $this->logger->error("API request failed: HTTP $httpCode (Time: {$executionTime}s)");
            $this->logger->debug("API Response: " . substr($response, 0, 500));
            
            // Retry if enabled
            if ($this->config['retry_on_failure']) {
                $this->logger->info("Retrying API request...");
                sleep(2);
                return $this->retryRequest($payload);
            }
            
            return false;
        }
    }

    private function retryRequest($payload)
    {
        $jsonData = json_encode($payload);
        $url = $this->config['endpoint'];
        $method = $this->config['method'];
        $timeout = $this->config['timeout'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        }

        $headers = [];
        foreach ($this->config['headers'] as $key => $value) {
            $headers[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->logger->info("API retry successful: HTTP $httpCode");
            return true;
        }

        $this->logger->error("API retry failed: HTTP $httpCode");
        return false;
    }

    private function saveFailedRequest($payload)
    {
        $failedRequest = [
            'timestamp' => date('Y-m-d H:i:s'),
            'payload' => $payload
        ];

        $existingData = [];
        if (file_exists($this->failedRequestsFile)) {
            $content = file_get_contents($this->failedRequestsFile);
            $existingData = json_decode($content, true) ?: [];
        }

        $existingData[] = $failedRequest;

        // Keep only last 1000 failed requests
        if (count($existingData) > 1000) {
            $existingData = array_slice($existingData, -1000);
        }

        file_put_contents(
            $this->failedRequestsFile,
            json_encode($existingData, JSON_PRETTY_PRINT)
        );

        $this->logger->warning("Failed API request saved to: " . $this->failedRequestsFile);
    }

    public function retryFailedRequests()
    {
        if (!file_exists($this->failedRequestsFile)) {
            return 0;
        }

        $content = file_get_contents($this->failedRequestsFile);
        $failedRequests = json_decode($content, true);

        if (empty($failedRequests)) {
            return 0;
        }

        $this->logger->info("Retrying " . count($failedRequests) . " failed API requests...");

        $successCount = 0;
        $remainingFailed = [];

        foreach ($failedRequests as $request) {
            if ($this->sendRequest($request['payload'])) {
                $successCount++;
            } else {
                $remainingFailed[] = $request;
            }
        }

        // Update failed requests file
        if (empty($remainingFailed)) {
            unlink($this->failedRequestsFile);
            $this->logger->info("All failed requests retried successfully");
        } else {
            file_put_contents(
                $this->failedRequestsFile,
                json_encode($remainingFailed, JSON_PRETTY_PRINT)
            );
            $this->logger->info("Retry complete: $successCount succeeded, " . count($remainingFailed) . " still failed");
        }

        return $successCount;
    }

    private function transformRecords($records)
    {
        $transformed = [];
        
        foreach ($records as $record) {
            $newRecord = [];
            
            // Apply field mapping
            if (!empty($this->config['field_mapping'])) {
                foreach ($record as $key => $value) {
                    // Check if this field should be mapped
                    if (isset($this->config['field_mapping'][$key])) {
                        $newKey = $this->config['field_mapping'][$key];
                        $newRecord[$newKey] = $value;
                    } else {
                        // Keep original key if no mapping defined
                        $newRecord[$key] = $value;
                    }
                }
            } else {
                $newRecord = $record;
            }
            
            // Apply include_fields filter (whitelist)
            if (!empty($this->config['include_fields']) && is_array($this->config['include_fields'])) {
                $filtered = [];
                foreach ($this->config['include_fields'] as $field) {
                    if (isset($newRecord[$field])) {
                        $filtered[$field] = $newRecord[$field];
                    }
                }
                $newRecord = $filtered;
            }
            
            // Apply exclude_fields filter (blacklist)
            if (!empty($this->config['exclude_fields']) && is_array($this->config['exclude_fields'])) {
                foreach ($this->config['exclude_fields'] as $field) {
                    unset($newRecord[$field]);
                }
            }
            
            $transformed[] = $newRecord;
        }
        
        return $transformed;
    }

    public function testConnection()
    {
        if (!$this->config['enabled']) {
            $this->logger->warning("API is disabled");
            return false;
        }

        $testPayload = [
            'test' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => 'Connection test from ZKTeco Service'
        ];

        // Add custom params to test payload
        if (!empty($this->config['custom_params'])) {
            $testPayload = array_merge($testPayload, $this->config['custom_params']);
        }

        $this->logger->info("Testing API connection to: {$this->config['endpoint']}");
        return $this->sendRequest($testPayload);
    }
}
