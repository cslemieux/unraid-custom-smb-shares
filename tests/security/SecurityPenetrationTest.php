<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../helpers/ChrootTestEnvironment.php';

/**
 * Security Penetration Tests
 * 
 * Tests for common vulnerabilities:
 * - Path traversal
 * - Symlink attacks
 * - XSS injection
 * - Command injection
 * - Invalid input handling
 */
class SecurityPenetrationTest extends TestCase
{
    private static $configDir;
    
    public static function setUpBeforeClass(): void
    {
        self::$configDir = ChrootTestEnvironment::setup();
        if (!defined('CONFIG_BASE')) {
            define('CONFIG_BASE', self::$configDir);
        }
        require_once __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
    }
    
    protected function setUp(): void
    {
        ChrootTestEnvironment::mkdir('user/legitimate');
    }
    
    // Path Traversal Attacks
    
    public function testPathTraversalWithDotDot()
    {
        $share = [
            'name' => 'Evil',
            'path' => '/mnt/user/../../etc/passwd'
        ];
        
        $errors = validateShare($share);
        $this->assertNotEmpty($errors, 'Path traversal should be blocked');
    }
    
    public function testPathTraversalOutsideMnt()
    {
        $share = [
            'name' => 'Evil',
            'path' => '/etc/passwd'
        ];
        
        $errors = validateShare($share);
        $this->assertNotEmpty($errors, 'Path outside /mnt/ should be blocked');
    }
    
    public function testSymlinkAttack()
    {
        // Create symlink to /etc
        $symlinkPath = ChrootTestEnvironment::getMntPath('user/evil');
        $targetPath = self::$configDir;
        
        if (!file_exists($symlinkPath)) {
            symlink($targetPath, $symlinkPath);
        }
        
        $share = [
            'name' => 'Evil',
            'path' => ChrootTestEnvironment::getMntPath('user/evil')
        ];
        
        $errors = validateShare($share);
        
        // Should fail because realpath() resolves symlink outside /mnt/
        $this->assertNotEmpty($errors, 'Symlink attack should be blocked');
        
        unlink($symlinkPath);
    }
    
    // XSS Injection Attacks
    
    public function testXSSInShareName()
    {
        $share = [
            'name' => '<script>alert("XSS")</script>',
            'path' => ChrootTestEnvironment::getMntPath('user/legitimate')
        ];
        
        $errors = validateShare($share);
        $this->assertNotEmpty($errors, 'XSS in share name should be blocked');
    }
    
    public function testXSSInComment()
    {
        $share = [
            'name' => 'Test',
            'path' => ChrootTestEnvironment::getMntPath('user/legitimate'),
            'comment' => '<script>alert("XSS")</script>'
        ];
        
        $errors = validateShare($share);
        // Comment allows any text, but output must be escaped
        // This test verifies validation doesn't crash
        $this->assertIsArray($errors);
    }
    
    // Command Injection Attacks
    
    public function testCommandInjectionInPath()
    {
        $share = [
            'name' => 'Evil',
            'path' => '/mnt/user/test; rm -rf /'
        ];
        
        $errors = validateShare($share);
        $this->assertNotEmpty($errors, 'Command injection should be blocked');
    }
    
    public function testCommandInjectionInName()
    {
        $share = [
            'name' => 'test; rm -rf /',
            'path' => ChrootTestEnvironment::getMntPath('user/legitimate')
        ];
        
        $errors = validateShare($share);
        $this->assertNotEmpty($errors, 'Command injection in name should be blocked');
    }
    
    // Invalid Input Attacks
    
    public function testNullByteInjection()
    {
        $share = [
            'name' => "test\0evil",
            'path' => ChrootTestEnvironment::getMntPath('user/legitimate')
        ];
        
        $errors = validateShare($share);
        $this->assertNotEmpty($errors, 'Null byte injection should be blocked');
    }
    
    public function testExcessivelyLongName()
    {
        $share = [
            'name' => str_repeat('A', 1000),
            'path' => ChrootTestEnvironment::getMntPath('user/legitimate')
        ];
        
        $errors = validateShare($share);
        // Should not crash, may or may not error depending on validation
        $this->assertIsArray($errors);
    }
    
    public function testInvalidPermissionMask()
    {
        $share = [
            'name' => 'Test',
            'path' => ChrootTestEnvironment::getMntPath('user/legitimate'),
            'create_mask' => '9999'
        ];
        
        $errors = validateShare($share);
        $this->assertNotEmpty($errors, 'Invalid octal mask should be blocked');
    }
    
    public function testSQLInjectionAttempt()
    {
        $share = [
            'name' => "'; DROP TABLE shares; --",
            'path' => ChrootTestEnvironment::getMntPath('user/legitimate')
        ];
        
        $errors = validateShare($share);
        $this->assertNotEmpty($errors, 'SQL injection pattern should be blocked');
    }
    
    // Edge Cases
    
    public function testEmptyPath()
    {
        $share = [
            'name' => 'Test',
            'path' => ''
        ];
        
        $errors = validateShare($share);
        $this->assertNotEmpty($errors, 'Empty path should be blocked');
    }
    
    public function testWhitespaceOnlyName()
    {
        $share = [
            'name' => '   ',
            'path' => ChrootTestEnvironment::getMntPath('user/legitimate')
        ];
        
        $errors = validateShare($share);
        $this->assertNotEmpty($errors, 'Whitespace-only name should be blocked');
    }
    
    public function testUnicodeExploits()
    {
        $share = [
            'name' => '../../etc/passwd',
            'path' => ChrootTestEnvironment::getMntPath('user/legitimate')
        ];
        
        $errors = validateShare($share);
        $this->assertNotEmpty($errors, 'Unicode path traversal should be blocked');
    }
}
