<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../helpers/ChrootTestEnvironment.php';

class PermissionIntegrationTest extends TestCase
{
    private $configDir;
    
    protected function setUp(): void
    {
        $this->configDir = ChrootTestEnvironment::setup();
        if (!defined('CONFIG_BASE')) {
            define('CONFIG_BASE', $this->configDir);
        }
        ChrootTestEnvironment::mkdir('user/testshare');
        
        require_once __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
    }
    
    protected function tearDown(): void
    {
        ChrootTestEnvironment::teardown();
    }
    
    public function testAddShareWithPublicSecurity(): void
    {
        $share = [
            'name' => 'TestShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'public'
        ];
        
        $shares = [$share];
        $this->assertNotFalse(saveShares($shares, $this->configDir));
        
        $config = generateSambaConfig($shares);
        $this->assertStringContainsString('guest ok = yes', $config);
        $this->assertStringContainsString('read only = no', $config);
    }
    
    public function testAddShareWithSecureSecurity(): void
    {
        $share = [
            'name' => 'TestShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'secure',
            'user_access' => json_encode(['admin' => 'read-write'])
        ];
        
        $shares = [$share];
        $this->assertNotFalse(saveShares($shares, $this->configDir));
        
        $config = generateSambaConfig($shares);
        $this->assertStringContainsString('guest ok = yes', $config);
        $this->assertStringContainsString('read only = yes', $config);
        $this->assertStringContainsString('write list = admin', $config);
    }
    
    public function testAddShareWithPrivateSecurity(): void
    {
        $share = [
            'name' => 'TestShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'private',
            'user_access' => json_encode([
                'admin' => 'read-write',
                'user1' => 'read-only'
            ])
        ];
        
        $shares = [$share];
        $this->assertNotFalse(saveShares($shares, $this->configDir));
        
        $config = generateSambaConfig($shares);
        $this->assertStringContainsString('guest ok = no', $config);
        $this->assertStringContainsString('valid users =', $config);
        $this->assertStringContainsString('write list = admin', $config);
    }
}
