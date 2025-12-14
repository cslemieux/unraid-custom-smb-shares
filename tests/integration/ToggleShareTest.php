<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../helpers/ChrootTestEnvironment.php';

/**
 * Tests for share enable/disable toggle functionality
 */
class ToggleShareTest extends TestCase
{
    private string $configDir;

    protected function setUp(): void
    {
        $this->configDir = ChrootTestEnvironment::setup();
        ConfigRegistry::setConfigBase($this->configDir);
        
        // Create plugin directory
        $pluginDir = $this->configDir . '/plugins/custom.smb.shares';
        if (!is_dir($pluginDir)) {
            mkdir($pluginDir, 0755, true);
        }
        
        // Create test shares
        $shares = [
            ['name' => 'share1', 'path' => '/mnt/user/share1', 'enabled' => true],
            ['name' => 'share2', 'path' => '/mnt/user/share2', 'enabled' => true],
            ['name' => 'share3', 'path' => '/mnt/user/share3', 'enabled' => false],
        ];
        file_put_contents($pluginDir . '/shares.json', json_encode($shares, JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        ConfigRegistry::reset();
        ChrootTestEnvironment::teardown();
    }

    public function testEnabledShareIncludedInConfig(): void
    {
        require_once dirname(__DIR__, 2) . '/source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
        
        $shares = loadShares();
        $config = generateSambaConfig($shares);
        
        $this->assertStringContainsString('[share1]', $config);
        $this->assertStringContainsString('[share2]', $config);
    }

    public function testDisabledShareExcludedFromConfig(): void
    {
        require_once dirname(__DIR__, 2) . '/source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
        
        $shares = loadShares();
        $config = generateSambaConfig($shares);
        
        $this->assertStringNotContainsString('[share3]', $config);
    }

    public function testToggleShareDisables(): void
    {
        require_once dirname(__DIR__, 2) . '/source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
        
        $shares = loadShares();
        $index = findShareIndex($shares, 'share1');
        
        $this->assertEquals(0, $index);
        $this->assertTrue($shares[$index]['enabled']);
        
        // Toggle off
        $shares[$index]['enabled'] = false;
        saveShares($shares);
        
        // Reload and verify
        $shares = loadShares();
        $this->assertFalse($shares[$index]['enabled']);
        
        // Verify config excludes it
        $config = generateSambaConfig($shares);
        $this->assertStringNotContainsString('[share1]', $config);
    }

    public function testToggleShareEnables(): void
    {
        require_once dirname(__DIR__, 2) . '/source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
        
        $shares = loadShares();
        $index = findShareIndex($shares, 'share3');
        
        $this->assertFalse($shares[$index]['enabled']);
        
        // Toggle on
        $shares[$index]['enabled'] = true;
        saveShares($shares);
        
        // Reload and verify
        $shares = loadShares();
        $this->assertTrue($shares[$index]['enabled']);
        
        // Verify config includes it
        $config = generateSambaConfig($shares);
        $this->assertStringContainsString('[share3]', $config);
    }

    public function testShareWithoutEnabledFieldDefaultsToEnabled(): void
    {
        require_once dirname(__DIR__, 2) . '/source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
        
        // Create share without enabled field
        $pluginDir = $this->configDir . '/plugins/custom.smb.shares';
        $shares = [
            ['name' => 'legacy', 'path' => '/mnt/user/legacy'],
        ];
        file_put_contents($pluginDir . '/shares.json', json_encode($shares));
        
        $shares = loadShares();
        $config = generateSambaConfig($shares);
        
        // Should be included (default enabled)
        $this->assertStringContainsString('[legacy]', $config);
    }

    public function testMultipleDisabledShares(): void
    {
        require_once dirname(__DIR__, 2) . '/source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
        
        $pluginDir = $this->configDir . '/plugins/custom.smb.shares';
        $shares = [
            ['name' => 'enabled1', 'path' => '/mnt/user/e1', 'enabled' => true],
            ['name' => 'disabled1', 'path' => '/mnt/user/d1', 'enabled' => false],
            ['name' => 'disabled2', 'path' => '/mnt/user/d2', 'enabled' => false],
            ['name' => 'enabled2', 'path' => '/mnt/user/e2', 'enabled' => true],
        ];
        file_put_contents($pluginDir . '/shares.json', json_encode($shares));
        
        $shares = loadShares();
        $config = generateSambaConfig($shares);
        
        $this->assertStringContainsString('[enabled1]', $config);
        $this->assertStringContainsString('[enabled2]', $config);
        $this->assertStringNotContainsString('[disabled1]', $config);
        $this->assertStringNotContainsString('[disabled2]', $config);
    }

    public function testDisabledSharePreservesAllFields(): void
    {
        require_once dirname(__DIR__, 2) . '/source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
        
        $pluginDir = $this->configDir . '/plugins/custom.smb.shares';
        $shares = [
            [
                'name' => 'fullshare',
                'path' => '/mnt/user/full',
                'comment' => 'Full featured share',
                'enabled' => true,
                'export' => 'et',
                'security' => 'private',
                'fruit' => 'yes',
            ],
        ];
        file_put_contents($pluginDir . '/shares.json', json_encode($shares));
        
        // Disable it
        $shares = loadShares();
        $shares[0]['enabled'] = false;
        saveShares($shares);
        
        // Reload and verify all fields preserved
        $shares = loadShares();
        $this->assertEquals('fullshare', $shares[0]['name']);
        $this->assertEquals('/mnt/user/full', $shares[0]['path']);
        $this->assertEquals('Full featured share', $shares[0]['comment']);
        $this->assertEquals('et', $shares[0]['export']);
        $this->assertEquals('private', $shares[0]['security']);
        $this->assertEquals('yes', $shares[0]['fruit']);
        $this->assertFalse($shares[0]['enabled']);
    }
}
