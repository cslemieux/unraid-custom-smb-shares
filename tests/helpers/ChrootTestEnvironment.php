<?php

require_once __DIR__ . '/../harness/SambaMock.php';
require_once __DIR__ . '/../harness/HarnessConfig.php';

/**
 * Chroot Test Environment
 * 
 * Creates Unraid-like directory structure for integration testing
 * Aligned with UnraidTestHarness structure
 */
class ChrootTestEnvironment
{
    private static $chrootDir;
    private static $lockFile;
    
    /**
     * Setup chroot environment with Unraid structure
     */
    public static function setup()
    {
        // Reuse existing environment if already set up
        if (self::$chrootDir && is_dir(self::$chrootDir) && is_dir(self::$chrootDir . '/mnt/user')) {
            return self::$chrootDir . '/usr/local/boot/config';
        }
        
        // Use /tmp directly to match UnraidTestHarness
        $baseDir = HarnessConfig::BASE_TEMP_DIR;
        
        self::$chrootDir = $baseDir . '/' . HarnessConfig::CHROOT_PREFIX . uniqid();
        
        // Create Unraid directory structure (matches UnraidTestHarness)
        mkdir(self::$chrootDir . '/usr/local/boot/config/plugins', 0755, true);
        mkdir(self::$chrootDir . '/mnt/user', 0755, true);
        mkdir(self::$chrootDir . '/mnt/disk1', 0755, true);
        mkdir(self::$chrootDir . '/mnt/cache', 0755, true);
        mkdir(self::$chrootDir . '/logs', 0755, true);
        
        // Create common test directories
        $testDirs = HarnessConfig::CHROOT_TEST_DIRS;
        foreach ($testDirs as $dir) {
            mkdir(self::$chrootDir . '/mnt/user/' . $dir, 0755, true);
        }
        
        // Initialize SambaMock
        SambaMock::init(self::$chrootDir);
        
        // Return CONFIG_BASE path (matches router.php resolution)
        return self::$chrootDir . '/usr/local/boot/config';
    }
    
    /**
     * Teardown chroot environment
     */
    public static function teardown()
    {
        if (self::$chrootDir && is_dir(self::$chrootDir)) {
            exec('rm -rf ' . escapeshellarg(self::$chrootDir));
        }
    }
    
    /**
     * Reset environment state between test classes
     * Clears shares.json and recreates standard test directories
     */
    public static function reset()
    {
        if (!self::$chrootDir || !is_dir(self::$chrootDir)) {
            return;
        }
        
        // Clear shares.json
        $sharesFile = self::$chrootDir . '/usr/local/boot/config/plugins/custom.smb.shares/shares.json';
        if (file_exists($sharesFile)) {
            file_put_contents($sharesFile, '[]');
        }
        
        // Clear smb-extra.conf
        $smbFile = self::$chrootDir . '/boot/config/plugins/custom.smb.shares/smb-extra.conf';
        if (file_exists($smbFile)) {
            file_put_contents($smbFile, '');
        }
        
        // Remove and recreate /mnt/user to ensure clean state
        $userDir = self::$chrootDir . '/mnt/user';
        if (is_dir($userDir)) {
            exec('rm -rf ' . escapeshellarg($userDir) . '/*');
        }
        
        // Recreate standard test directories
        $testDirs = HarnessConfig::CHROOT_TEST_DIRS;
        foreach ($testDirs as $dir) {
            $fullPath = self::$chrootDir . '/mnt/user/' . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }
        }
        
        // Reset SambaMock state
        SambaMock::init(self::$chrootDir);
    }
    
    /**
     * Get path within chroot /mnt/
     */
    public static function getMntPath($subpath = '')
    {
        return self::$chrootDir . '/mnt/' . ltrim($subpath, '/');
    }
    
    /**
     * Create directory in /mnt/
     */
    public static function mkdir($path)
    {
        $fullPath = self::getMntPath($path);
        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0755, true);
        }
    }
    
    /**
     * Get chroot base directory
     */
    public static function getChrootDir()
    {
        return self::$chrootDir;
    }
    
    /**
     * Create test share directory (matches UnraidTestHarness API)
     */
    public static function createShareDir($path)
    {
        $fullPath = self::$chrootDir . $path;
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }
        return $fullPath;
    }
}
