<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../helpers/ChrootTestEnvironment.php';

/**
 * Performance Benchmarks
 * 
 * Targets:
 * - Page load < 3s
 * - AJAX response < 1s
 * - Memory usage < 10MB
 */
class PerformanceBenchmark extends TestCase
{
    private static $configDir;
    
    public static function setUpBeforeClass(): void
    {
        self::$configDir = ChrootTestEnvironment::setup();
        if (!defined('CONFIG_BASE')) {
            define('CONFIG_BASE', self::$configDir);
        }
        require_once __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
        
        // Create test shares
        for ($i = 1; $i <= 100; $i++) {
            ChrootTestEnvironment::mkdir("user/share$i");
        }
    }
    
    public function testValidationPerformance()
    {
        $share = [
            'name' => 'PerfTest',
            'path' => ChrootTestEnvironment::getMntPath('user/share1')
        ];
        
        $start = microtime(true);
        $errors = validateShare($share);
        $duration = microtime(true) - $start;
        
        $this->assertLessThan(0.01, $duration, 'Validation should complete in < 10ms');
        $this->assertEmpty($errors);
    }
    
    public function testLoadSharesPerformance()
    {
        // Create 100 shares
        $shares = [];
        for ($i = 1; $i <= 100; $i++) {
            $shares[] = [
                'name' => "Share$i",
                'path' => ChrootTestEnvironment::getMntPath("user/share$i")
            ];
        }
        saveShares($shares, self::$configDir);
        
        $start = microtime(true);
        $loaded = loadShares(self::$configDir);
        $duration = microtime(true) - $start;
        
        $this->assertLessThan(0.1, $duration, 'Loading 100 shares should complete in < 100ms');
        $this->assertCount(100, $loaded);
    }
    
    public function testSaveSharesPerformance()
    {
        $shares = [];
        for ($i = 1; $i <= 100; $i++) {
            $shares[] = [
                'name' => "Share$i",
                'path' => ChrootTestEnvironment::getMntPath("user/share$i")
            ];
        }
        
        $start = microtime(true);
        saveShares($shares, self::$configDir);
        $duration = microtime(true) - $start;
        
        $this->assertLessThan(0.1, $duration, 'Saving 100 shares should complete in < 100ms');
    }
    
    public function testGenerateConfigPerformance()
    {
        $shares = [];
        for ($i = 1; $i <= 100; $i++) {
            $shares[] = [
                'name' => "Share$i",
                'path' => ChrootTestEnvironment::getMntPath("user/share$i"),
                'comment' => "Test share $i",
                'browseable' => 'yes',
                'read_only' => 'no'
            ];
        }
        
        $start = microtime(true);
        $config = generateSambaConfig($shares);
        $duration = microtime(true) - $start;
        
        $this->assertLessThan(0.05, $duration, 'Generating config for 100 shares should complete in < 50ms');
        $this->assertStringContainsString('[Share1]', $config);
        $this->assertStringContainsString('[Share100]', $config);
    }
    
    public function testMemoryUsage()
    {
        $memStart = memory_get_usage();
        
        // Load 100 shares
        $shares = [];
        for ($i = 1; $i <= 100; $i++) {
            $shares[] = [
                'name' => "Share$i",
                'path' => ChrootTestEnvironment::getMntPath("user/share$i"),
                'comment' => "Test share $i"
            ];
        }
        saveShares($shares, self::$configDir);
        $loaded = loadShares(self::$configDir);
        $config = generateSambaConfig($loaded);
        
        $memEnd = memory_get_usage();
        $memUsed = ($memEnd - $memStart) / 1024 / 1024; // MB
        
        $this->assertLessThan(5, $memUsed, 'Memory usage should be < 5MB for 100 shares');
    }
}
