<?php

/**
 * Test Path Validator
 * 
 * Overrides path validation to work with test directories
 * Uses a "chroot jail" approach where test paths are mapped to /mnt/
 */
class TestPathValidator
{
    private static $testRoot;
    private static $originalValidateShare;
    
    /**
     * Initialize test environment with chroot-like path mapping
     */
    public static function init($testRoot)
    {
        self::$testRoot = $testRoot;
        
        // Override validateShare function to use test paths
        if (!function_exists('validateShare')) {
            require_once __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
        }
    }
    
    /**
     * Convert test path to virtual /mnt/ path
     */
    public static function toVirtualPath($realPath)
    {
        if (strpos($realPath, self::$testRoot) === 0) {
            // Map test directory to /mnt/
            return '/mnt/' . substr($realPath, strlen(self::$testRoot) + 1);
        }
        return $realPath;
    }
    
    /**
     * Convert virtual /mnt/ path to real test path
     */
    public static function toRealPath($virtualPath)
    {
        if (strpos($virtualPath, '/mnt/') === 0) {
            return self::$testRoot . '/' . substr($virtualPath, 5);
        }
        return $virtualPath;
    }
    
    /**
     * Validate share with path translation
     */
    public static function validateShare($data)
    {
        // Translate real path to virtual path for validation
        if (isset($data['path'])) {
            $originalPath = $data['path'];
            $data['path'] = self::toVirtualPath($originalPath);
        }
        
        // Use original validation
        $errors = validateShare($data);
        
        // Translate path back
        if (isset($data['path'])) {
            $data['path'] = $originalPath;
        }
        
        return $errors;
    }
}
