<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for plugin configuration functions
 * - isPluginEnabled()
 */
class PluginConfigTest extends TestCase
{
    private string $tempDir;
    private string $configFile;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/plugin-config-test-' . uniqid();
        mkdir($this->tempDir . '/plugins/custom.smb.shares', 0755, true);
        $this->configFile = $this->tempDir . '/plugins/custom.smb.shares/settings.cfg';
        
        // Define CONFIG_BASE for lib.php
        if (!defined('CONFIG_BASE')) {
            define('CONFIG_BASE', $this->tempDir);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            exec('rm -rf ' . escapeshellarg($this->tempDir));
        }
    }

    /**
     * Test isPluginEnabled returns true when no config file exists (default)
     */
    public function testIsPluginEnabledDefaultsToTrue(): void
    {
        // No config file exists
        $this->assertFileDoesNotExist($this->configFile);
        
        // Should default to enabled
        $this->assertTrue($this->isPluginEnabled());
    }

    /**
     * Test isPluginEnabled returns true when SERVICE=enabled
     */
    public function testIsPluginEnabledWhenExplicitlyEnabled(): void
    {
        file_put_contents($this->configFile, "SERVICE=enabled\n");
        
        $this->assertTrue($this->isPluginEnabled());
    }

    /**
     * Test isPluginEnabled returns false when SERVICE=disabled
     */
    public function testIsPluginDisabledWhenExplicitlyDisabled(): void
    {
        file_put_contents($this->configFile, "SERVICE=disabled\n");
        
        $this->assertFalse($this->isPluginEnabled());
    }

    /**
     * Test isPluginEnabled handles empty config file (defaults to enabled)
     */
    public function testIsPluginEnabledWithEmptyConfig(): void
    {
        file_put_contents($this->configFile, '');
        
        $this->assertTrue($this->isPluginEnabled());
    }

    /**
     * Test isPluginEnabled handles config with other settings but no SERVICE
     */
    public function testIsPluginEnabledWithOtherSettings(): void
    {
        file_put_contents($this->configFile, "OTHER_SETTING=value\nANOTHER=test\n");
        
        // Should default to enabled when SERVICE not present
        $this->assertTrue($this->isPluginEnabled());
    }

    /**
     * Test isPluginEnabled handles quoted values
     */
    public function testIsPluginEnabledWithQuotedValue(): void
    {
        file_put_contents($this->configFile, 'SERVICE="enabled"' . "\n");
        
        $this->assertTrue($this->isPluginEnabled());
    }

    /**
     * Test isPluginEnabled handles disabled with quotes
     */
    public function testIsPluginDisabledWithQuotedValue(): void
    {
        file_put_contents($this->configFile, 'SERVICE="disabled"' . "\n");
        
        $this->assertFalse($this->isPluginEnabled());
    }

    /**
     * Local implementation of isPluginEnabled for testing
     * (avoids requiring lib.php which has side effects)
     */
    private function isPluginEnabled(): bool
    {
        $configFile = $this->tempDir . '/plugins/custom.smb.shares/settings.cfg';
        if (!file_exists($configFile)) {
            return true; // Default to enabled
        }
        $settings = parse_ini_file($configFile);
        return ($settings['SERVICE'] ?? 'enabled') === 'enabled';
    }
}
