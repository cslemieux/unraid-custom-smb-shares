<?php

declare(strict_types=1);

require_once __DIR__ . '/HarnessConfig.php';

/**
 * Process management utilities for test harness
 */
class ProcessManager
{
    /**
     * Kill any process running on specified port
     * 
     * @param int $port Port number
     * @return void
     */
    public static function killOnPort(int $port): void
    {
        $output = [];
        exec("lsof -ti :$port 2>/dev/null", $output);
        
        foreach ($output as $pid) {
            self::killProcess((int)trim($pid));
        }
    }
    
    /**
     * Kill process by PID with graceful fallback to force
     * 
     * @param int $pid Process ID
     * @return bool True if process was killed
     */
    public static function killProcess(int $pid): bool
    {
        if ($pid <= 0 || !posix_kill($pid, 0)) {
            return false; // Process doesn't exist
        }
        
        // Try graceful termination
        posix_kill($pid, SIGTERM);
        
        // Wait for process to die
        $maxWait = (int)(HarnessConfig::PROCESS_KILL_TIMEOUT_MS / HarnessConfig::PROCESS_KILL_WAIT_MS);
        for ($i = 0; $i < $maxWait; $i++) {
            if (!posix_kill($pid, 0)) {
                return true; // Process died
            }
            usleep(HarnessConfig::getProcessKillWait());
        }
        
        // Force kill if still alive
        if (posix_kill($pid, 0)) {
            posix_kill($pid, SIGKILL);
            usleep(HarnessConfig::getProcessKillWait());
        }
        
        return !posix_kill($pid, 0);
    }
    
    /**
     * Check if process is running
     * 
     * @param int $pid Process ID
     * @return bool True if running
     */
    public static function isRunning(int $pid): bool
    {
        return $pid > 0 && posix_kill($pid, 0);
    }
}
