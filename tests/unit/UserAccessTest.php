<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../helpers/ChrootTestEnvironment.php';

/**
 * Unit Tests for User Access Data Handling
 * 
 * Tests user_access field parsing, validation, and transformation
 */
class UserAccessTest extends TestCase
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
    // User Access JSON Parsing
    // ========================================

    public function testUserAccessJsonStringParsing(): void
    {
        $share = [
            'security' => 'private',
            'user_access' => '{"admin":"read-write","user1":"read-only"}'
        ];
        $config = buildSecurityConfig($share);
        
        $this->assertStringContainsString('admin', $config);
        $this->assertStringContainsString('user1', $config);
    }

    public function testUserAccessArrayParsing(): void
    {
        $share = [
            'security' => 'private',
            'user_access' => ['admin' => 'read-write', 'user1' => 'read-only']
        ];
        $config = buildSecurityConfig($share);
        
        $this->assertStringContainsString('admin', $config);
        $this->assertStringContainsString('user1', $config);
    }

    public function testUserAccessEmptyString(): void
    {
        $share = [
            'security' => 'private',
            'user_access' => ''
        ];
        $config = buildSecurityConfig($share);
        
        $this->assertStringContainsString('guest ok = no', $config);
        $this->assertStringNotContainsString('valid users', $config);
    }

    public function testUserAccessEmptyJsonObject(): void
    {
        $share = [
            'security' => 'private',
            'user_access' => '{}'
        ];
        $config = buildSecurityConfig($share);
        
        $this->assertStringContainsString('guest ok = no', $config);
        $this->assertStringNotContainsString('valid users', $config);
    }

    public function testUserAccessInvalidJson(): void
    {
        $share = [
            'security' => 'private',
            'user_access' => 'not valid json'
        ];
        $config = buildSecurityConfig($share);
        
        // Should handle gracefully - no users
        $this->assertStringContainsString('guest ok = no', $config);
        $this->assertStringNotContainsString('valid users', $config);
    }

    public function testUserAccessNull(): void
    {
        $share = [
            'security' => 'private',
            'user_access' => null
        ];
        $config = buildSecurityConfig($share);
        
        $this->assertStringContainsString('guest ok = no', $config);
        $this->assertStringNotContainsString('valid users', $config);
    }

    // ========================================
    // Access Level Values
    // ========================================

    public function testAccessLevelReadWrite(): void
    {
        $share = [
            'security' => 'private',
            'user_access' => ['admin' => 'read-write']
        ];
        $config = buildSecurityConfig($share);
        
        $this->assertStringContainsString('valid users = admin', $config);
        $this->assertStringContainsString('write list = admin', $config);
    }

    public function testAccessLevelReadOnly(): void
    {
        $share = [
            'security' => 'private',
            'user_access' => ['reader' => 'read-only']
        ];
        $config = buildSecurityConfig($share);
        
        $this->assertStringContainsString('valid users = reader', $config);
        $this->assertStringNotContainsString('write list', $config);
    }

    public function testAccessLevelNoAccess(): void
    {
        $share = [
            'security' => 'private',
            'user_access' => ['blocked' => 'no-access']
        ];
        $config = buildSecurityConfig($share);
        
        $this->assertStringNotContainsString('blocked', $config);
        $this->assertStringNotContainsString('valid users', $config);
    }

    public function testAccessLevelUnknownTreatedAsNoAccess(): void
    {
        $share = [
            'security' => 'private',
            'user_access' => ['user1' => 'unknown-value']
        ];
        $config = buildSecurityConfig($share);
        
        // Unknown values should not grant access
        $this->assertStringNotContainsString('user1', $config);
    }

    // ========================================
    // Multiple Users
    // ========================================

    public function testMultipleUsersAllReadWrite(): void
    {
        $share = [
            'security' => 'private',
            'user_access' => [
                'admin' => 'read-write',
                'editor' => 'read-write',
                'manager' => 'read-write'
            ]
        ];
        $config = buildSecurityConfig($share);
        
        $this->assertStringContainsString('valid users =', $config);
        $this->assertStringContainsString('admin', $config);
        $this->assertStringContainsString('editor', $config);
        $this->assertStringContainsString('manager', $config);
        $this->assertStringContainsString('write list =', $config);
    }

    public function testMultipleUsersAllReadOnly(): void
    {
        $share = [
            'security' => 'private',
            'user_access' => [
                'user1' => 'read-only',
                'user2' => 'read-only',
                'user3' => 'read-only'
            ]
        ];
        $config = buildSecurityConfig($share);
        
        $this->assertStringContainsString('valid users =', $config);
        $this->assertStringContainsString('user1', $config);
        $this->assertStringContainsString('user2', $config);
        $this->assertStringContainsString('user3', $config);
        $this->assertStringNotContainsString('write list', $config);
    }

    public function testMultipleUsersMixedAccess(): void
    {
        $share = [
            'security' => 'private',
            'user_access' => [
                'admin' => 'read-write',
                'reader' => 'read-only',
                'blocked' => 'no-access'
            ]
        ];
        $config = buildSecurityConfig($share);
        
        // Check valid users (read-only + read-write)
        $this->assertMatchesRegularExpression('/valid users = .*admin/', $config);
        $this->assertMatchesRegularExpression('/valid users = .*reader/', $config);
        
        // Check write list (only read-write)
        $this->assertStringContainsString('write list = admin', $config);
        $this->assertStringNotContainsString('blocked', $config);
    }

    // ========================================
    // Security Mode Interactions
    // ========================================

    public function testSecureModeIgnoresNoAccessUsers(): void
    {
        $share = [
            'security' => 'secure',
            'user_access' => [
                'admin' => 'read-write',
                'blocked' => 'no-access'
            ]
        ];
        $config = buildSecurityConfig($share);
        
        // Secure mode only uses write list
        $this->assertStringContainsString('write list = admin', $config);
        $this->assertStringNotContainsString('blocked', $config);
        $this->assertStringNotContainsString('valid users', $config);
    }

    public function testSecureModeIgnoresReadOnlyUsers(): void
    {
        $share = [
            'security' => 'secure',
            'user_access' => [
                'admin' => 'read-write',
                'reader' => 'read-only'
            ]
        ];
        $config = buildSecurityConfig($share);
        
        // In secure mode, read-only is the default for guests
        // Only write users are listed
        $this->assertStringContainsString('write list = admin', $config);
        $this->assertStringNotContainsString('reader', $config);
    }

    public function testPublicModeIgnoresUserAccess(): void
    {
        $share = [
            'security' => 'public',
            'user_access' => [
                'admin' => 'read-write',
                'reader' => 'read-only'
            ]
        ];
        $config = buildSecurityConfig($share);
        
        // Public mode ignores user access entirely
        $this->assertStringNotContainsString('valid users', $config);
        $this->assertStringNotContainsString('write list', $config);
        $this->assertStringNotContainsString('admin', $config);
    }

    // ========================================
    // Special Characters in Usernames
    // ========================================

    public function testUsernameWithSpaces(): void
    {
        $share = [
            'security' => 'private',
            'user_access' => ['john doe' => 'read-write']
        ];
        $config = buildSecurityConfig($share);
        
        // Usernames with spaces are passed through (Samba handles quoting)
        $this->assertStringContainsString('john doe', $config);
    }

    public function testUsernameWithSpecialChars(): void
    {
        $share = [
            'security' => 'private',
            'user_access' => ['admin$test' => 'read-write']
        ];
        $config = buildSecurityConfig($share);
        
        // Special chars are passed through (Samba handles them)
        $this->assertStringContainsString('admin$test', $config);
    }

    public function testUsernameInjectionAttempt(): void
    {
        $share = [
            'security' => 'private',
            'user_access' => ['admin\nwrite list = hacker' => 'read-write']
        ];
        $config = buildSecurityConfig($share);
        
        // Newlines are stripped, preventing injection from creating new config lines
        // The text runs together, not as a separate directive
        $this->assertStringNotContainsString("\n    write list = hacker", $config);
    }
}
