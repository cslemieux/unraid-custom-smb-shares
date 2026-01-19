<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../helpers/ChrootTestEnvironment.php';
require_once __DIR__ . '/../harness/SambaMock.php';

/**
 * Integration Tests for Security Settings Save/Load Cycle
 * 
 * Tests the complete flow:
 * 1. Save share with security settings
 * 2. Load share and verify settings preserved
 * 3. Generate Samba config and verify output
 * 4. Modify settings and verify changes persist
 */
class SecuritySettingsIntegrationTest extends TestCase
{
    private string $configDir;
    
    protected function setUp(): void
    {
        ChrootTestEnvironment::reset();
        $this->configDir = ChrootTestEnvironment::setup();
        
        if (!defined('CONFIG_BASE')) {
            define('CONFIG_BASE', $this->configDir);
        }
        
        SambaMock::init(ChrootTestEnvironment::getChrootDir());
        SambaMock::initScripts();
        
        ChrootTestEnvironment::mkdir('user/testshare');
        
        require_once __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
    }
    
    protected function tearDown(): void
    {
        ChrootTestEnvironment::teardown();
    }

    // ========================================
    // Public Security Mode
    // ========================================

    public function testPublicShareSaveLoadCycle(): void
    {
        $share = [
            'name' => 'PublicShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'public'
        ];
        
        // Save
        $this->assertNotFalse(saveShares([$share], $this->configDir));
        
        // Load
        $loaded = loadShares($this->configDir);
        $this->assertCount(1, $loaded);
        $this->assertEquals('public', $loaded[0]['security']);
        
        // Generate config
        $config = generateSambaConfig($loaded);
        $this->assertStringContainsString('guest ok = yes', $config);
        $this->assertStringContainsString('read only = no', $config);
    }

    // ========================================
    // Secure Security Mode
    // ========================================

    public function testSecureShareSaveLoadCycle(): void
    {
        $userAccess = ['admin' => 'read-write', 'user1' => 'read-only'];
        $share = [
            'name' => 'SecureShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'secure',
            'user_access' => json_encode($userAccess)
        ];
        
        // Save
        $this->assertNotFalse(saveShares([$share], $this->configDir));
        
        // Load
        $loaded = loadShares($this->configDir);
        $this->assertCount(1, $loaded);
        $this->assertEquals('secure', $loaded[0]['security']);
        
        // Verify user_access preserved
        $loadedAccess = json_decode($loaded[0]['user_access'], true);
        $this->assertEquals('read-write', $loadedAccess['admin']);
        $this->assertEquals('read-only', $loadedAccess['user1']);
        
        // Generate config
        $config = generateSambaConfig($loaded);
        $this->assertStringContainsString('guest ok = yes', $config);
        $this->assertStringContainsString('read only = yes', $config);
        $this->assertStringContainsString('write list = admin', $config);
    }

    public function testSecureShareWithNoWriteUsers(): void
    {
        $userAccess = ['user1' => 'read-only', 'user2' => 'read-only'];
        $share = [
            'name' => 'ReadOnlyShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'secure',
            'user_access' => json_encode($userAccess)
        ];
        
        $this->assertNotFalse(saveShares([$share], $this->configDir));
        
        $config = generateSambaConfig(loadShares($this->configDir));
        $this->assertStringContainsString('read only = yes', $config);
        $this->assertStringNotContainsString('write list', $config);
    }

    // ========================================
    // Private Security Mode
    // ========================================

    public function testPrivateShareSaveLoadCycle(): void
    {
        $userAccess = [
            'admin' => 'read-write',
            'reader' => 'read-only',
            'blocked' => 'no-access'
        ];
        $share = [
            'name' => 'PrivateShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'private',
            'user_access' => json_encode($userAccess)
        ];
        
        // Save
        $this->assertNotFalse(saveShares([$share], $this->configDir));
        
        // Load
        $loaded = loadShares($this->configDir);
        $this->assertCount(1, $loaded);
        $this->assertEquals('private', $loaded[0]['security']);
        
        // Verify user_access preserved
        $loadedAccess = json_decode($loaded[0]['user_access'], true);
        $this->assertEquals('read-write', $loadedAccess['admin']);
        $this->assertEquals('read-only', $loadedAccess['reader']);
        $this->assertEquals('no-access', $loadedAccess['blocked']);
        
        // Generate config
        $config = generateSambaConfig($loaded);
        $this->assertStringContainsString('guest ok = no', $config);
        $this->assertStringContainsString('valid users =', $config);
        $this->assertStringContainsString('admin', $config);
        $this->assertStringContainsString('reader', $config);
        $this->assertStringNotContainsString('blocked', $config);
        $this->assertStringContainsString('write list = admin', $config);
    }

    public function testPrivateShareWithOnlyNoAccessUsers(): void
    {
        $userAccess = ['blocked1' => 'no-access', 'blocked2' => 'no-access'];
        $share = [
            'name' => 'NoAccessShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'private',
            'user_access' => json_encode($userAccess)
        ];
        
        $this->assertNotFalse(saveShares([$share], $this->configDir));
        
        $config = generateSambaConfig(loadShares($this->configDir));
        $this->assertStringContainsString('guest ok = no', $config);
        $this->assertStringNotContainsString('valid users', $config);
        $this->assertStringNotContainsString('blocked', $config);
    }

    // ========================================
    // Advanced Permission Settings
    // ========================================

    public function testAdvancedPermissionsSaveLoadCycle(): void
    {
        $share = [
            'name' => 'AdvancedShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'public',
            'create_mask' => '0644',
            'directory_mask' => '0755',
            'force_user' => 'admin',
            'force_group' => 'staff',
            'hide_dot_files' => 'no'
        ];
        
        // Save
        $this->assertNotFalse(saveShares([$share], $this->configDir));
        
        // Load
        $loaded = loadShares($this->configDir);
        $this->assertEquals('0644', $loaded[0]['create_mask']);
        $this->assertEquals('0755', $loaded[0]['directory_mask']);
        $this->assertEquals('admin', $loaded[0]['force_user']);
        $this->assertEquals('staff', $loaded[0]['force_group']);
        $this->assertEquals('no', $loaded[0]['hide_dot_files']);
        
        // Generate config
        $config = generateSambaConfig($loaded);
        $this->assertStringContainsString('create mask = 0644', $config);
        $this->assertStringContainsString('directory mask = 0755', $config);
        $this->assertStringContainsString('force user = admin', $config);
        $this->assertStringContainsString('force group = staff', $config);
        $this->assertStringContainsString('hide dot files = no', $config);
    }

    public function testHostAccessSettingsSaveLoadCycle(): void
    {
        $share = [
            'name' => 'RestrictedShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'public',
            'hosts_allow' => '192.168.1.0/24 192.168.2.0/24',
            'hosts_deny' => '0.0.0.0/0'
        ];
        
        // Save
        $this->assertNotFalse(saveShares([$share], $this->configDir));
        
        // Load
        $loaded = loadShares($this->configDir);
        $this->assertEquals('192.168.1.0/24 192.168.2.0/24', $loaded[0]['hosts_allow']);
        $this->assertEquals('0.0.0.0/0', $loaded[0]['hosts_deny']);
        
        // Generate config
        $config = generateSambaConfig($loaded);
        $this->assertStringContainsString('hosts allow = 192.168.1.0/24 192.168.2.0/24', $config);
        $this->assertStringContainsString('hosts deny = 0.0.0.0/0', $config);
    }

    // ========================================
    // Modify and Update
    // ========================================

    public function testModifySecurityMode(): void
    {
        // Start with public
        $share = [
            'name' => 'TestShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'public'
        ];
        $this->assertNotFalse(saveShares([$share], $this->configDir));
        
        // Change to private
        $loaded = loadShares($this->configDir);
        $loaded[0]['security'] = 'private';
        $loaded[0]['user_access'] = json_encode(['admin' => 'read-write']);
        $this->assertNotFalse(saveShares($loaded, $this->configDir));
        
        // Verify change persisted
        $reloaded = loadShares($this->configDir);
        $this->assertEquals('private', $reloaded[0]['security']);
        
        $config = generateSambaConfig($reloaded);
        $this->assertStringContainsString('guest ok = no', $config);
        $this->assertStringContainsString('valid users = admin', $config);
    }

    public function testModifyUserAccess(): void
    {
        // Start with one user
        $share = [
            'name' => 'TestShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'private',
            'user_access' => json_encode(['admin' => 'read-write'])
        ];
        $this->assertNotFalse(saveShares([$share], $this->configDir));
        
        // Add another user
        $loaded = loadShares($this->configDir);
        $userAccess = json_decode($loaded[0]['user_access'], true);
        $userAccess['editor'] = 'read-write';
        $userAccess['reader'] = 'read-only';
        $loaded[0]['user_access'] = json_encode($userAccess);
        $this->assertNotFalse(saveShares($loaded, $this->configDir));
        
        // Verify changes
        $reloaded = loadShares($this->configDir);
        $finalAccess = json_decode($reloaded[0]['user_access'], true);
        $this->assertEquals('read-write', $finalAccess['admin']);
        $this->assertEquals('read-write', $finalAccess['editor']);
        $this->assertEquals('read-only', $finalAccess['reader']);
        
        $config = generateSambaConfig($reloaded);
        $this->assertStringContainsString('admin', $config);
        $this->assertStringContainsString('editor', $config);
        $this->assertStringContainsString('reader', $config);
    }

    public function testChangeUserAccessLevel(): void
    {
        // Start with read-only
        $share = [
            'name' => 'TestShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'private',
            'user_access' => json_encode(['user1' => 'read-only'])
        ];
        $this->assertNotFalse(saveShares([$share], $this->configDir));
        
        // Change to read-write
        $loaded = loadShares($this->configDir);
        $userAccess = json_decode($loaded[0]['user_access'], true);
        $userAccess['user1'] = 'read-write';
        $loaded[0]['user_access'] = json_encode($userAccess);
        $this->assertNotFalse(saveShares($loaded, $this->configDir));
        
        // Verify change
        $config = generateSambaConfig(loadShares($this->configDir));
        $this->assertStringContainsString('write list = user1', $config);
    }

    // ========================================
    // Multiple Shares
    // ========================================

    public function testMultipleSharesWithDifferentSecurityModes(): void
    {
        $shares = [
            [
                'name' => 'PublicShare',
                'path' => ChrootTestEnvironment::getMntPath('user/public'),
                'security' => 'public'
            ],
            [
                'name' => 'SecureShare',
                'path' => ChrootTestEnvironment::getMntPath('user/secure'),
                'security' => 'secure',
                'user_access' => json_encode(['admin' => 'read-write'])
            ],
            [
                'name' => 'PrivateShare',
                'path' => ChrootTestEnvironment::getMntPath('user/private'),
                'security' => 'private',
                'user_access' => json_encode(['admin' => 'read-write', 'user1' => 'read-only'])
            ]
        ];
        
        ChrootTestEnvironment::mkdir('user/public');
        ChrootTestEnvironment::mkdir('user/secure');
        ChrootTestEnvironment::mkdir('user/private');
        
        $this->assertNotFalse(saveShares($shares, $this->configDir));
        
        $loaded = loadShares($this->configDir);
        $this->assertCount(3, $loaded);
        
        $config = generateSambaConfig($loaded);
        
        // Public share
        $this->assertStringContainsString('[PublicShare]', $config);
        
        // Secure share
        $this->assertStringContainsString('[SecureShare]', $config);
        
        // Private share
        $this->assertStringContainsString('[PrivateShare]', $config);
        $this->assertStringContainsString('valid users =', $config);
    }

    // ========================================
    // Samba Config Validation
    // ========================================

    public function testGeneratedConfigIsValidSamba(): void
    {
        $share = [
            'name' => 'ValidShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'private',
            'user_access' => json_encode(['admin' => 'read-write']),
            'create_mask' => '0664',
            'directory_mask' => '0775',
            'force_user' => 'nobody',
            'force_group' => 'users'
        ];
        
        $this->assertNotFalse(saveShares([$share], $this->configDir));
        
        $config = generateSambaConfig(loadShares($this->configDir));
        SambaMock::writeConfig($config);
        
        $result = SambaMock::validateConfig();
        $this->assertTrue($result['valid'], 'Generated config should be valid Samba syntax');
    }
}
