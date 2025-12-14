<?php

declare(strict_types=1);

/**
 * Structured logging for test harness
 */
class HarnessLogger
{
    private static bool $enabled = true;
    private static string $level = 'INFO';
    
    /**
     * Log info message
     */
    public static function info(string $msg): void
    {
        self::log('INFO', $msg);
    }
    
    /**
     * Log debug message
     */
    public static function debug(string $msg): void
    {
        if (self::$level === 'DEBUG') {
            self::log('DEBUG', $msg);
        }
    }
    
    /**
     * Log error message
     */
    public static function error(string $msg): void
    {
        self::log('ERROR', $msg);
    }
    
    /**
     * Log warning message
     */
    public static function warning(string $msg): void
    {
        self::log('WARNING', $msg);
    }
    
    /**
     * Internal log method
     */
    private static function log(string $level, string $msg): void
    {
        if (!self::$enabled) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        error_log("[$timestamp] [$level] $msg");
    }
    
    /**
     * Set log level
     */
    public static function setLevel(string $level): void
    {
        self::$level = $level;
    }
    
    /**
     * Enable/disable logging
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }
}
