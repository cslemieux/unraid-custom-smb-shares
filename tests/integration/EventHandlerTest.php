<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../helpers/ChrootTestEnvironment.php';
require_once __DIR__ . '/../harness/SambaMock.php';

/**
 * Tests for event handler scripts and isPluginEnabled()
 * 
 * Uses configBase parameter to avoid CONFIG_BASE constant issues.
 */
class EventHandlerTest extends TestCase
{
    private string $configDir;
    private string $chrootDir;
    private string $pluginDir;
    private string $configFile;

    protected function setUp(): void
    {
        ChrootTestEnvironment::reset();
        $this->configDir = ChrootTestEnvironment::setup();
        $this->chrootDir = ChrootTestEnvironment::getChrootDir();
        $this->pluginDir = $this->configDir . '/plugins/custom.smb.shares';

        // Define CONFIG_BASE only if not already defined
        if (!defined('CONFIG_BASE')) {
            define('CONFIG_BASE', $this->configDir);
        }

        // Initialize Samba mock and create scripts
        SambaMock::init($this->chrootDir);
        SambaMock::reload();

        // The config file path that verifySambaShare() will use
        $this->configFile = $this->configDir . '/plugins/custom.smb.shares/smb-extra.conf';

        // Ensure plugin directory exists
        if (!is_dir($this->pluginDir)) {
            mkdir($this->pluginDir, 0755, true);
        }

        // Create test directories
        ChrootTestEnvironment::mkdir('user/eventshare');
        ChrootTestEnvironment::mkdir('user/custom');
        ChrootTestEnvironment::mkdir('user/reload');
        ChrootTestEnvironment::mkdir('user/event1');
        ChrootTestEnvironment::mkdir('user/event2');

        require_once __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
    }

    /**
     * Write config to the path that verifySambaShare expects
     */
    private function writeConfig(string $content): void
    {
        file_put_contents($this->configFile, $content);
    }

    // ========================================
    // isPluginEnabled() Integration Tests
    // ========================================

    /**
     * Test isPluginEnabled returns true when no settings file exists
     */
    public function testIsPluginEnabledDefaultsToTrue(): void
    {
        $settingsFile = $this->pluginDir . '/settings.cfg';
        if (file_exists($settingsFile)) {
            unlink($settingsFile);
        }

        // Use configBase parameter to ensure correct path
        $this->assertTrue(isPluginEnabled($this->configDir));
    }

    /**
     * Test isPluginEnabled returns true when SERVICE=enabled
     */
    public function testIsPluginEnabledWhenEnabled(): void
    {
        $settingsFile = $this->pluginDir . '/settings.cfg';
        file_put_contents($settingsFile, "SERVICE=enabled\n");

        $this->assertTrue(isPluginEnabled($this->configDir));
    }

    /**
     * Test isPluginEnabled returns false when SERVICE=disabled
     */
    public function testIsPluginDisabledWhenDisabled(): void
    {
        $settingsFile = $this->pluginDir . '/settings.cfg';
        file_put_contents($settingsFile, "SERVICE=disabled\n");

        $this->assertFalse(isPluginEnabled($this->configDir));
    }

    // ========================================
    // Event Handler Logic Tests
    // ========================================

    /**
     * Test generateSambaConfig produces valid output for event handler
     */
    public function testGenerateSambaConfigForEventHandler(): void
    {
        $shares = [
            [
                'name' => 'EventShare',
                'path' => ChrootTestEnvironment::getMntPath('user/eventshare'),
                'comment' => 'Share created by event handler',
                'security' => 'public'
            ]
        ];

        $config = generateSambaConfig($shares);

        $this->assertStringContainsString('[EventShare]', $config);
        $this->assertStringContainsString('comment = Share created by event handler', $config);
    }

    /**
     * Test shares.json loading (simulates event handler behavior)
     */
    public function testSharesJsonLoading(): void
    {
        $sharesFile = $this->pluginDir . '/shares.json';

        // Create test shares
        $shares = [
            ['name' => 'Share1', 'path' => '/mnt/user/share1'],
            ['name' => 'Share2', 'path' => '/mnt/user/share2']
        ];

        file_put_contents($sharesFile, json_encode($shares, JSON_PRETTY_PRINT));

        // Load shares (simulating event handler)
        $content = file_get_contents($sharesFile);
        $loadedShares = json_decode($content, true) ?: [];

        $this->assertCount(2, $loadedShares);
        $this->assertEquals('Share1', $loadedShares[0]['name']);
        $this->assertEquals('Share2', $loadedShares[1]['name']);
    }

    /**
     * Test empty shares.json handling
     */
    public function testEmptySharesJsonHandling(): void
    {
        $sharesFile = $this->pluginDir . '/shares.json';

        // Empty file
        file_put_contents($sharesFile, '');

        $content = file_get_contents($sharesFile);
        $loadedShares = json_decode($content, true) ?: [];

        $this->assertIsArray($loadedShares);
        $this->assertEmpty($loadedShares);
    }

    /**
     * Test missing shares.json handling
     */
    public function testMissingSharesJsonHandling(): void
    {
        $sharesFile = $this->pluginDir . '/shares.json';

        // Ensure file doesn't exist
        if (file_exists($sharesFile)) {
            unlink($sharesFile);
        }

        // Simulate event handler logic
        $shares = [];
        if (file_exists($sharesFile)) {
            $content = file_get_contents($sharesFile);
            $shares = json_decode($content, true) ?: [];
        }

        $this->assertIsArray($shares);
        $this->assertEmpty($shares);
    }

    /**
     * Test smb-custom.conf generation
     */
    public function testSmbCustomConfGeneration(): void
    {
        $configFile = $this->pluginDir . '/smb-custom.conf';

        $shares = [
            [
                'name' => 'CustomShare',
                'path' => ChrootTestEnvironment::getMntPath('user/custom'),
                'security' => 'private'
            ]
        ];

        $config = generateSambaConfig($shares);
        file_put_contents($configFile, $config);

        $this->assertFileExists($configFile);
        $content = file_get_contents($configFile);
        $this->assertStringContainsString('[CustomShare]', $content);
    }

    /**
     * Test reloadSamba is called successfully after config generation
     */
    public function testReloadSambaAfterConfigGeneration(): void
    {
        // Generate config
        $shares = [
            [
                'name' => 'ReloadTestShare',
                'path' => ChrootTestEnvironment::getMntPath('user/reload'),
                'security' => 'public'
            ]
        ];

        $config = generateSambaConfig($shares);
        $this->writeConfig($config);

        // Reload Samba using SambaMock
        $result = SambaMock::reload();

        $this->assertTrue($result['success'], 'Samba reload should succeed: ' . ($result['output'] ?? ''));
    }

    // ========================================
    // Full Event Handler Simulation
    // ========================================

    /**
     * Test complete event handler workflow simulation
     */
    public function testEventHandlerWorkflowSimulation(): void
    {
        // 1. Check plugin is enabled
        $settingsFile = $this->pluginDir . '/settings.cfg';
        file_put_contents($settingsFile, "SERVICE=enabled\n");
        $this->assertTrue(isPluginEnabled($this->configDir));

        // 2. Load shares from JSON
        $sharesFile = $this->pluginDir . '/shares.json';
        $shares = [
            [
                'name' => 'EventShare1',
                'path' => ChrootTestEnvironment::getMntPath('user/event1'),
                'comment' => 'First event share',
                'security' => 'public'
            ],
            [
                'name' => 'EventShare2',
                'path' => ChrootTestEnvironment::getMntPath('user/event2'),
                'comment' => 'Second event share',
                'security' => 'private'
            ]
        ];
        file_put_contents($sharesFile, json_encode($shares, JSON_PRETTY_PRINT));

        // 3. Generate Samba config
        $loadedShares = json_decode(file_get_contents($sharesFile), true);
        $config = generateSambaConfig($loadedShares);

        // 4. Write config file
        $configFile = $this->pluginDir . '/smb-custom.conf';
        file_put_contents($configFile, $config);

        // 5. Write to SambaMock's config file for verification
        SambaMock::writeConfig($config);

        // 6. Reload Samba using SambaMock
        $result = SambaMock::reload();
        $this->assertTrue($result['success']);

        // 7. Verify shares exist using SambaMock
        $foundShares = SambaMock::getShares();
        $this->assertContains('EventShare1', $foundShares);
        $this->assertContains('EventShare2', $foundShares);
    }

    /**
     * Test event handler skips when plugin is disabled
     */
    public function testEventHandlerSkipsWhenDisabled(): void
    {
        // Disable plugin
        $settingsFile = $this->pluginDir . '/settings.cfg';
        file_put_contents($settingsFile, "SERVICE=disabled\n");

        // Plugin should be disabled
        $this->assertFalse(isPluginEnabled($this->configDir));
    }
}
