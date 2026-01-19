<?php
/**
 * Database Manager
 * Handles all database operations for the service
 */

class DatabaseManager
{
    private $config;
    private $logger;
    private $pdo;

    public function __construct($config, ServiceLogger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function initialize()
    {
        try {
            $dsn = "{$this->config['driver']}:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['name']};charset={$this->config['charset']}";
            
            $this->pdo = new PDO(
                $dsn,
                $this->config['user'],
                $this->config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );

            $this->logger->info("Database connected successfully");
            $this->createTables();
            
            return true;
        } catch (PDOException $e) {
            $this->logger->error("Database connection failed: " . $e->getMessage());
            return false;
        }
    }

    private function createTables()
    {
        $tables = [
            // Attendance logs table
            "CREATE TABLE IF NOT EXISTS attendance_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                device_name VARCHAR(100) NOT NULL,
                uid INT NOT NULL,
                user_id VARCHAR(50) NOT NULL,
                timestamp DATETIME NOT NULL,
                state TINYINT NOT NULL COMMENT '0=Password, 1=Fingerprint, 2=Card',
                type TINYINT NOT NULL COMMENT '0=Check-in, 1=Check-out, 4=OT-in, 5=OT-out',
                synced_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_device (device_name),
                INDEX idx_user (user_id),
                INDEX idx_timestamp (timestamp),
                UNIQUE KEY unique_record (device_name, uid, user_id, timestamp, type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // Users table
            "CREATE TABLE IF NOT EXISTS device_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                device_name VARCHAR(100) NOT NULL,
                uid INT NOT NULL,
                user_id VARCHAR(50) NOT NULL,
                name VARCHAR(100) NOT NULL,
                role TINYINT NOT NULL COMMENT '0=User, 14=Admin',
                password VARCHAR(50),
                cardno VARCHAR(20),
                synced_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_device (device_name),
                INDEX idx_user_id (user_id),
                UNIQUE KEY unique_user (device_name, uid, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // Sync status table
            "CREATE TABLE IF NOT EXISTS sync_status (
                id INT AUTO_INCREMENT PRIMARY KEY,
                device_name VARCHAR(100) NOT NULL UNIQUE,
                last_sync DATETIME,
                last_attendance_count INT DEFAULT 0,
                last_user_count INT DEFAULT 0,
                status VARCHAR(50) DEFAULT 'active',
                error_message TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];

        foreach ($tables as $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (PDOException $e) {
                $this->logger->error("Table creation error: " . $e->getMessage());
            }
        }

        $this->logger->info("Database tables initialized");
    }

    public function saveAttendance($record)
    {
        try {
            $sql = "INSERT INTO attendance_logs 
                    (device_name, uid, user_id, timestamp, state, type, synced_at) 
                    VALUES (:device_name, :uid, :user_id, :timestamp, :state, :type, :synced_at)
                    ON DUPLICATE KEY UPDATE synced_at = VALUES(synced_at)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':device_name' => $record['device_name'],
                ':uid' => $record['uid'],
                ':user_id' => $record['id'],
                ':timestamp' => $record['timestamp'],
                ':state' => $record['state'],
                ':type' => $record['type'],
                ':synced_at' => $record['synced_at']
            ]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->logger->error("Failed to save attendance: " . $e->getMessage());
            return false;
        }
    }

    public function saveUser($user)
    {
        try {
            $sql = "INSERT INTO device_users 
                    (device_name, uid, user_id, name, role, password, cardno, synced_at) 
                    VALUES (:device_name, :uid, :user_id, :name, :role, :password, :cardno, :synced_at)
                    ON DUPLICATE KEY UPDATE 
                        name = VALUES(name),
                        role = VALUES(role),
                        password = VALUES(password),
                        cardno = VALUES(cardno),
                        synced_at = VALUES(synced_at)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':device_name' => $user['device_name'],
                ':uid' => $user['uid'],
                ':user_id' => $user['userid'],
                ':name' => $user['name'],
                ':role' => $user['role'],
                ':password' => $user['password'],
                ':cardno' => $user['cardno'],
                ':synced_at' => $user['synced_at']
            ]);

            return true;
        } catch (PDOException $e) {
            $this->logger->error("Failed to save user: " . $e->getMessage());
            return false;
        }
    }

    public function updateSyncStatus($deviceName, $attendanceCount, $userCount = 0, $error = null)
    {
        try {
            $sql = "INSERT INTO sync_status 
                    (device_name, last_sync, last_attendance_count, last_user_count, error_message) 
                    VALUES (:device_name, NOW(), :attendance_count, :user_count, :error)
                    ON DUPLICATE KEY UPDATE 
                        last_sync = NOW(),
                        last_attendance_count = VALUES(last_attendance_count),
                        last_user_count = VALUES(last_user_count),
                        error_message = VALUES(error_message)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':device_name' => $deviceName,
                ':attendance_count' => $attendanceCount,
                ':user_count' => $userCount,
                ':error' => $error
            ]);

            return true;
        } catch (PDOException $e) {
            $this->logger->error("Failed to update sync status: " . $e->getMessage());
            return false;
        }
    }

    public function getLastSyncTime($deviceName)
    {
        try {
            $sql = "SELECT last_sync FROM sync_status WHERE device_name = :device_name";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':device_name' => $deviceName]);
            
            $result = $stmt->fetch();
            return $result ? $result['last_sync'] : null;
        } catch (PDOException $e) {
            $this->logger->error("Failed to get last sync time: " . $e->getMessage());
            return null;
        }
    }

    public function __destruct()
    {
        $this->pdo = null;
    }
}
