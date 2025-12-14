<?php

declare(strict_types=1);

/**
 * Centralized configuration for test harness
 * 
 * All magic numbers and configuration values in one place
 */
class HarnessConfig
{
    // Server configuration
    public const DEFAULT_PORT = 8888;
    public const PORT_MIN = 1024;
    public const PORT_MAX = 65535;
    
    // Timing configuration (milliseconds)
    public const SERVER_STARTUP_TIMEOUT_MS = 2000;
    public const SERVER_POLL_INTERVAL_MS = 100;
    public const PROCESS_KILL_WAIT_MS = 100;
    public const PROCESS_KILL_TIMEOUT_MS = 2000;
    
    // UI timing (milliseconds)
    public const MODAL_ANIMATION_MS = 300;
    public const PAGE_READY_BUFFER_MS = 300;
    public const AJAX_TIMEOUT_MS = 10000;
    
    // Retry configuration
    public const MAX_CLICK_RETRIES = 3;
    public const CLICK_RETRY_DELAY_MS = 500;
    public const MAX_MODAL_CLOSE_ATTEMPTS = 5;
    public const MODAL_CLOSE_RETRY_MS = 200;
    
    // Directory configuration
    public const BASE_TEMP_DIR = '/tmp';
    public const HARNESS_PREFIX = 'unraid-test-harness-';
    public const CHROOT_PREFIX = 'chroot-test-';
    
    // Test data
    public const DEFAULT_TEST_DIRS = ['test1', 'test2', 'test3', 'EditTest', 'DeleteTest'];
    public const CHROOT_TEST_DIRS = ['test1', 'test2', 'test3', 'testshare'];
    
    // Logging
    public const LOG_AJAX_TRACE = true;
    public const LOG_ROUTER_DEBUG = true;
    
    /**
     * Get server startup timeout in seconds
     */
    public static function getServerStartupTimeout(): int
    {
        return (int)(self::SERVER_STARTUP_TIMEOUT_MS / 1000);
    }
    
    /**
     * Get server poll interval in microseconds
     */
    public static function getServerPollInterval(): int
    {
        return self::SERVER_POLL_INTERVAL_MS * 1000;
    }
    
    /**
     * Get process kill wait in microseconds
     */
    public static function getProcessKillWait(): int
    {
        return self::PROCESS_KILL_WAIT_MS * 1000;
    }
    
    /**
     * Get modal animation delay in microseconds
     */
    public static function getModalAnimationDelay(): int
    {
        return self::MODAL_ANIMATION_MS * 1000;
    }
    
    /**
     * Get page ready buffer in microseconds
     */
    public static function getPageReadyBuffer(): int
    {
        return self::PAGE_READY_BUFFER_MS * 1000;
    }
    
    /**
     * Validate port number
     */
    public static function isValidPort(int $port): bool
    {
        return $port >= self::PORT_MIN && $port <= self::PORT_MAX;
    }
}
