<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../helpers/ChrootTestEnvironment.php';

/**
 * Unit Tests for SMB Security Configuration
 * 
 * Tests the security config builder functions in isolation:
 * - buildSecurityConfig()
 * - buildWriteListConfig()
 * - buildPrivateAccessConfig()
 * - buildPermissionConfig()
 * - buildHostAccessConfig()
 */
class SecurityConfigTest extends TestCase
{
    protected function setUp(): void
    {
        ChrootTestEnvironment::reset();
        $configDir = ChrootTestEnvironment::setup();
        if (!defined('CONFIG_BASE')) {
            define('CONFIG_BASE', $configDir);
        }
        require_once __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
    }

    // ========================================
    // buildSecurityConfig() Tests
    // ========================================

    public function testBuildSecurityConfigPublicMode(): void
    {
        $share = ['security' => 'public'];
        $config = buildSecurityConfig($share);
        
        $this->assertStringContainsString('guest ok = yes', $config);
        $this->assertStringContainsString('read only = no', $config);
        $this->assertStringNotContainsString('valid users', $config);
        $this->assertStringNotContainsString('write list', $config);
    }

    public function testBuildSecurityConfigSecureModeNoUsers(): void
    {
        $share = ['security' => 'secure'];
        $config = buildSecurityConfig($share);
        
        $this->assertStringContainsString('guest ok = yes', $config);
        $this->assertStringContainsString('read only = yes', $config);
        $this->assertStringNotContainsString('write list', $config);
    }

    public function testBuildSecurityConfigSecureModeWithWriteUsers(): void
    {
        $share = [
            'security' => 'secure',
            'user_access' => json_encode([
                'admin' => 'read-write',
                'user1' => 'read-only'
            ])
        ];
        $config = buildSecurityConfig($share);
        
        $this->assertStringContainsString('guest ok = yes', $config);
        $this->assertStringContainsString('read only = yes', $config);
        $this->assertStringContainsString('write list = admin', $config);
        $this->assertStringNotContainsString('user1', $config); // read-only users not in write list
    }

    public function testBuildSecurityConfigPrivateModeNoUsers(): void
    {
        $share = ['security' => 'private'];
        $config = buildSecurityConfig($share);
        
        $this->assertStringContainsString('guest ok = no', $config);
        $this->assertStringContainsString('read only = yes', $config);
        $this->assertStringNotContainsString('valid users', $config);
    }

    public function testBuildSecurityConfigPrivateModeWithUsers(): void
    {
        $share = [
            'security' => 'private',
            'user_access' => json_encode([
                'admin' => 'read-write',
                'reader' => 'read-only',
                'blocked' => 'no-access'
            ])
        ];
        $config = buildSecurityConfig($share);
        
        $this->assertStringContainsString('guest ok = no', $config);
        // Order may vary, check both users are present
        $this->assertMatchesRegularExpression('/valid users = .*reader/', $config);
        $this->assertMatchesRegularExpression('/valid users = .*admin/', $config);
        $this->assertStringContainsString('write list = admin', $config);
        $this->assertStringNotContainsString('blocked', $config);
    }

    public function testBuildSecurityConfigDefaultsToPublic(): void
    {
        $share = []; // No security specified
        $config = buildSecurityConfig($share);
        
        $this->assertStringContainsString('guest ok = yes', $config);
        $this->assertStringContainsString('read only = no', $config);
    }

    public function testBuildSecurityConfigHandlesArrayUserAccess(): void
    {
        // user_access can be array (already decoded) or string (JSON)
        $share = [
            'security' => 'private',
            'user_access' => ['admin' => 'read-write', 'user1' => 'read-only']
        ];
        $config = buildSecurityConfig($share);
        
        // Order may vary, check both users are present
        $this->assertMatchesRegularExpression('/valid users = .*user1/', $config);
        $this->assertMatchesRegularExpression('/valid users = .*admin/', $config);
        $this->assertStringContainsString('write list = admin', $config);
    }

    public function testBuildSecurityConfigHandlesEmptyJsonUserAccess(): void
    {
        $share = [
            'security' => 'secure',
            'user_access' => '{}'
        ];
        $config = buildSecurityConfig($share);
        
        $this->assertStringContainsString('guest ok = yes', $config);
        $this->assertStringContainsString('read only = yes', $config);
        $this->assertStringNotContainsString('write list', $config);
    }

    // ========================================
    // buildWriteListConfig() Tests
    // ========================================

    public function testBuildWriteListConfigEmpty(): void
    {
        $config = buildWriteListConfig([]);
        $this->assertEmpty($config);
    }

    public function testBuildWriteListConfigNoWriteUsers(): void
    {
        $userAccess = [
            'user1' => 'read-only',
            'user2' => 'no-access'
        ];
        $config = buildWriteListConfig($userAccess);
        $this->assertEmpty($config);
    }

    public function testBuildWriteListConfigSingleWriteUser(): void
    {
        $userAccess = ['admin' => 'read-write'];
        $config = buildWriteListConfig($userAccess);
        
        $this->assertStringContainsString('write list = admin', $config);
    }

    public function testBuildWriteListConfigMultipleWriteUsers(): void
    {
        $userAccess = [
            'admin' => 'read-write',
            'editor' => 'read-write',
            'reader' => 'read-only'
        ];
        $config = buildWriteListConfig($userAccess);
        
        $this->assertStringContainsString('write list =', $config);
        $this->assertStringContainsString('admin', $config);
        $this->assertStringContainsString('editor', $config);
        $this->assertStringNotContainsString('reader', $config);
    }

    public function testBuildWriteListConfigSanitizesNewlines(): void
    {
        // Current sanitization removes newlines to prevent config injection
        // The injected text becomes part of the username, not a new directive
        $userAccess = ["admin\nwrite list = hacker" => 'read-write'];
        $config = buildWriteListConfig($userAccess);
        
        // Newlines should be stripped - text runs together
        // This prevents the injection from creating a new config line
        $this->assertStringNotContainsString("\n    write list = hacker", $config);
    }

    // ========================================
    // buildPrivateAccessConfig() Tests
    // ========================================

    public function testBuildPrivateAccessConfigEmpty(): void
    {
        $config = buildPrivateAccessConfig([]);
        
        $this->assertStringContainsString('read only = yes', $config);
        $this->assertStringNotContainsString('valid users', $config);
        $this->assertStringNotContainsString('write list', $config);
    }

    public function testBuildPrivateAccessConfigReadOnlyUsers(): void
    {
        $userAccess = [
            'user1' => 'read-only',
            'user2' => 'read-only'
        ];
        $config = buildPrivateAccessConfig($userAccess);
        
        $this->assertStringContainsString('valid users = user1 user2', $config);
        $this->assertStringNotContainsString('write list', $config);
        $this->assertStringContainsString('read only = yes', $config);
    }

    public function testBuildPrivateAccessConfigWriteUsers(): void
    {
        $userAccess = [
            'admin' => 'read-write',
            'editor' => 'read-write'
        ];
        $config = buildPrivateAccessConfig($userAccess);
        
        $this->assertStringContainsString('valid users = admin editor', $config);
        $this->assertStringContainsString('write list = admin editor', $config);
    }

    public function testBuildPrivateAccessConfigMixedAccess(): void
    {
        $userAccess = [
            'admin' => 'read-write',
            'reader' => 'read-only',
            'blocked' => 'no-access'
        ];
        $config = buildPrivateAccessConfig($userAccess);
        
        // Valid users includes read-only and read-write
        $this->assertStringContainsString('valid users =', $config);
        $this->assertStringContainsString('admin', $config);
        $this->assertStringContainsString('reader', $config);
        
        // Write list only includes read-write
        $this->assertStringContainsString('write list = admin', $config);
        $this->assertStringNotContainsString('blocked', $config);
    }

    public function testBuildPrivateAccessConfigNoAccessUsersExcluded(): void
    {
        $userAccess = [
            'blocked1' => 'no-access',
            'blocked2' => 'no-access'
        ];
        $config = buildPrivateAccessConfig($userAccess);
        
        $this->assertStringNotContainsString('valid users', $config);
        $this->assertStringNotContainsString('write list', $config);
        $this->assertStringNotContainsString('blocked', $config);
    }

    // ========================================
    // buildPermissionConfig() Tests
    // ========================================

    public function testBuildPermissionConfigDefaults(): void
    {
        $share = [];
        $config = buildPermissionConfig($share);
        
        // force_user and force_group should NOT be in config when not set
        $this->assertStringNotContainsString('force user', $config);
        $this->assertStringNotContainsString('force group', $config);
        $this->assertStringContainsString('create mask = 0664', $config);
        $this->assertStringContainsString('directory mask = 0775', $config);
        $this->assertStringContainsString('hide dot files = yes', $config);
    }

    public function testBuildPermissionConfigCustomValues(): void
    {
        $share = [
            'force_user' => 'admin',
            'force_group' => 'staff',
            'create_mask' => '0644',
            'directory_mask' => '0755',
            'hide_dot_files' => 'no'
        ];
        $config = buildPermissionConfig($share);
        
        $this->assertStringContainsString('force user = admin', $config);
        $this->assertStringContainsString('force group = staff', $config);
        $this->assertStringContainsString('create mask = 0644', $config);
        $this->assertStringContainsString('directory mask = 0755', $config);
        $this->assertStringContainsString('hide dot files = no', $config);
    }

    public function testBuildPermissionConfigSanitizesNewlines(): void
    {
        // Current sanitization removes newlines to prevent config injection
        // The injected text becomes part of the value, not a new directive
        $share = [
            'force_user' => "admin\nwrite list = hacker",
            'create_mask' => "0777\nguest ok = yes"
        ];
        $config = buildPermissionConfig($share);
        
        // Newlines should be stripped - prevents injection from creating new config lines
        $this->assertStringNotContainsString("\n    write list = hacker", $config);
        $this->assertStringNotContainsString("\n    guest ok = yes", $config);
    }

    // ========================================
    // buildHostAccessConfig() Tests
    // ========================================

    public function testBuildHostAccessConfigEmpty(): void
    {
        $share = [];
        $config = buildHostAccessConfig($share);
        
        $this->assertEmpty($config);
    }

    public function testBuildHostAccessConfigHostsAllow(): void
    {
        $share = ['hosts_allow' => '192.168.1.0/24'];
        $config = buildHostAccessConfig($share);
        
        $this->assertStringContainsString('hosts allow = 192.168.1.0/24', $config);
        $this->assertStringNotContainsString('hosts deny', $config);
    }

    public function testBuildHostAccessConfigHostsDeny(): void
    {
        $share = ['hosts_deny' => '10.0.0.0/8'];
        $config = buildHostAccessConfig($share);
        
        $this->assertStringContainsString('hosts deny = 10.0.0.0/8', $config);
        $this->assertStringNotContainsString('hosts allow', $config);
    }

    public function testBuildHostAccessConfigBothAllowAndDeny(): void
    {
        $share = [
            'hosts_allow' => '192.168.1.0/24 192.168.2.0/24',
            'hosts_deny' => '0.0.0.0/0'
        ];
        $config = buildHostAccessConfig($share);
        
        $this->assertStringContainsString('hosts allow = 192.168.1.0/24 192.168.2.0/24', $config);
        $this->assertStringContainsString('hosts deny = 0.0.0.0/0', $config);
    }

    public function testBuildHostAccessConfigSanitizesNewlines(): void
    {
        // Current sanitization removes newlines to prevent config injection
        // The injected text becomes part of the value, not a new directive
        $share = ['hosts_allow' => "192.168.1.0/24\nguest ok = yes"];
        $config = buildHostAccessConfig($share);
        
        // Newlines should be stripped - prevents injection from creating new config lines
        $this->assertStringNotContainsString("\n    guest ok = yes", $config);
    }
}
