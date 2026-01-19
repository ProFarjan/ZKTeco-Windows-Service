<?php
/**
 * Service Logger
 * Handles logging with rotation and different log levels
 */

class ServiceLogger
{
    const LEVEL_DEBUG = 1;
    const LEVEL_INFO = 2;
    const LEVEL_WARNING = 3;
    const LEVEL_ERROR = 4;

    private $config;
    private $logFile;
    private $logLevel;

    private $levels = [
        self::LEVEL_DEBUG => 'DEBUG',
        self::LEVEL_INFO => 'INFO',
        self::LEVEL_WARNING => 'WARNING',
        self::LEVEL_ERROR => 'ERROR'
    ];

    public function __construct($config)
    {
        $this->config = $config;
        $this->logFile = $config['file'];
        $this->logLevel = $this->getLevelValue($config['level']);
        
        $this->rotateIfNeeded();
    }

    private function getLevelValue($levelName)
    {
        $mapping = [
            'DEBUG' => self::LEVEL_DEBUG,
            'INFO' => self::LEVEL_INFO,
            'WARNING' => self::LEVEL_WARNING,
            'ERROR' => self::LEVEL_ERROR
        ];

        return isset($mapping[$levelName]) ? $mapping[$levelName] : self::LEVEL_INFO;
    }

    public function debug($message)
    {
        $this->log($message, self::LEVEL_DEBUG);
    }

    public function info($message)
    {
        $this->log($message, self::LEVEL_INFO);
    }

    public function warning($message)
    {
        $this->log($message, self::LEVEL_WARNING);
    }

    public function error($message)
    {
        $this->log($message, self::LEVEL_ERROR);
    }

    private function log($message, $level)
    {
        if (!$this->config['enabled'] || $level < $this->logLevel) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $levelName = $this->levels[$level];
        $pid = getmypid();
        
        $logMessage = "[$timestamp] [$levelName] [PID:$pid] $message" . PHP_EOL;

        // Write to file
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);

        // Also output to console for debugging
        echo $logMessage;
    }

    private function rotateIfNeeded()
    {
        if (!file_exists($this->logFile)) {
            return;
        }

        $maxSizeBytes = $this->config['max_size_mb'] * 1024 * 1024;
        $fileSize = filesize($this->logFile);

        if ($fileSize >= $maxSizeBytes) {
            $this->rotate();
        }
    }

    private function rotate()
    {
        $keepFiles = $this->config['keep_files'];
        $logDir = dirname($this->logFile);
        $logName = basename($this->logFile);

        // Rotate existing backup files
        for ($i = $keepFiles - 1; $i >= 1; $i--) {
            $oldFile = "$logDir/$logName.$i";
            $newFile = "$logDir/$logName." . ($i + 1);

            if (file_exists($oldFile)) {
                if ($i == $keepFiles - 1) {
                    unlink($oldFile); // Delete oldest
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }

        // Rotate current log file
        if (file_exists($this->logFile)) {
            rename($this->logFile, "$logDir/$logName.1");
        }
    }

    public function getLogFile()
    {
        return $this->logFile;
    }
}
