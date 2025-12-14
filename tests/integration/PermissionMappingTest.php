<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once 'tests/helpers/ChrootTestEnvironment.php';

class PermissionMappingTest extends TestCase
{
    private $configDir;

    protected function setUp(): void
    {
        ChrootTestEnvironment::reset();
        $this->configDir = ChrootTestEnvironment::setup();
        if (!defined('CONFIG_BASE')) {
            define('CONFIG_BASE', $this->configDir);
        }
        ChrootTestEnvironment::mkdir('user/testshare');
    }

    public function testPublicSecurityMode()
    {
        require_once 'source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';

        $share = [
            'name' => 'PublicShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'public'
        ];

        $config = generateSambaConfig([$share]);

        $this->assertStringContainsString('guest ok = yes', $config);
        $this->assertStringContainsString('read only = no', $config);
    }

    public function testSecureSecurityMode()
    {
        require_once 'source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';

        $share = [
            'name' => 'SecureShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'secure',
            'user_access' => json_encode(['admin' => 'read-write', 'user1' => 'read-only'])
        ];

        $config = generateSambaConfig([$share]);

        $this->assertStringContainsString('guest ok = yes', $config);
        $this->assertStringContainsString('read only = yes', $config);
        $this->assertStringContainsString('write list = admin', $config);
    }

    public function testPrivateSecurityMode()
    {
        require_once 'source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';

        $share = [
            'name' => 'PrivateShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'private',
            'user_access' => json_encode(['admin' => 'read-write', 'user1' => 'read-only'])
        ];

        $config = generateSambaConfig([$share]);

        $this->assertStringContainsString('guest ok = no', $config);
        $this->assertStringContainsString('valid users =', $config);
        $this->assertStringContainsString('admin', $config);
        $this->assertStringContainsString('user1', $config);
        $this->assertStringContainsString('write list = admin', $config);
    }

    public function testPrivateModeNoAccessUsersExcluded()
    {
        require_once 'source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';

        $share = [
            'name' => 'PrivateShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'security' => 'private',
            'user_access' => json_encode([
                'admin' => 'read-write',
                'user1' => 'read-only',
                'blocked' => 'no-access'
            ])
        ];

        $config = generateSambaConfig([$share]);

        // blocked user should not be in valid users
        $this->assertStringContainsString('valid users =', $config);
        $this->assertStringContainsString('admin', $config);
        $this->assertStringContainsString('user1', $config);
        // Check blocked is not in valid users line
        preg_match('/valid users = (.*)/', $config, $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringNotContainsString('blocked', $matches[1]);
    }
}
