<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/UnraidTestHarness.php';

/**
 * Tests for config-based harness setup
 */
class UnraidTestHarnessConfigTest extends TestCase
{
    private static $harness;
    private static $testConfigFile;
    
    public static function setUpBeforeClass(): void
    {
        // Create test config file
        self::$testConfigFile = sys_get_temp_dir() . '/test-harness-config-' . uniqid() . '.json';
        
        $config = [
            'testName' => 'ConfigTest',
            'harness' => [
                'port' => 8891,
                'createTestDirs' => ['config-test-1', 'config-test-2']
            ],
            'dependencies' => [
                'scan' => false
            ]
        ];
        
        file_put_contents(self::$testConfigFile, json_encode($config, JSON_PRETTY_PRINT));
    }
    
    public function testSetupFromConfigLoadsConfig()
    {
        self::$harness = UnraidTestHarness::setupFromConfig(self::$testConfigFile);
        
        $this->assertIsArray(self::$harness);
        $this->assertArrayHasKey('url', self::$harness);
        $this->assertArrayHasKey('harness_dir', self::$harness);
    }
    
    public function testSetupFromConfigUsesCorrectPort()
    {
        $this->assertEquals('http://localhost:8891', self::$harness['url']);
    }
    
    public function testSetupFromConfigCreatesCustomDirs()
    {
        $dir = self::$harness['harness_dir'];
        
        $this->assertDirectoryExists($dir . '/mnt/user/config-test-1');
        $this->assertDirectoryExists($dir . '/mnt/user/config-test-2');
    }
    
    public function testSetupFromConfigRejectsInvalidPath()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config file not found');
        
        UnraidTestHarness::setupFromConfig('/nonexistent/config.json');
    }
    
    public function testSetupFromConfigRejectsInvalidJson()
    {
        $badConfig = sys_get_temp_dir() . '/bad-config-' . uniqid() . '.json';
        file_put_contents($badConfig, '{invalid json}');
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON');
        
        try {
            UnraidTestHarness::setupFromConfig($badConfig);
        } finally {
            unlink($badConfig);
        }
    }
    
    public static function tearDownAfterClass(): void
    {
        if (self::$harness) {
            UnraidTestHarness::teardown();
        }
        
        if (self::$testConfigFile && file_exists(self::$testConfigFile)) {
            unlink(self::$testConfigFile);
        }
    }
}
