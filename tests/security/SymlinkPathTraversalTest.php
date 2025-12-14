<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Security Test: Symlink Path Traversal Attack
 * 
 * Attack Vector: Attacker creates symlink in /mnt/user/ pointing to sensitive
 * system directory (e.g., /etc), then attempts to create share using symlink path.
 * 
 * Expected: Validation should resolve symlink and reject path outside /mnt/
 * 
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SymlinkPathTraversalTest extends TestCase
{
    private static $testRoot;

    public static function setUpBeforeClass(): void
    {
        // Create isolated test environment in /tmp (required for getHarnessRoot())
        self::$testRoot = '/tmp/symlink-test-' . uniqid();
        mkdir(self::$testRoot . '/usr/local/boot/config', 0755, true);
        mkdir(self::$testRoot . '/mnt/user', 0755, true);
        mkdir(self::$testRoot . '/etc', 0755, true);
        
        // Define CONFIG_BASE before loading lib.php
        define('CONFIG_BASE', self::$testRoot . '/usr/local/boot/config');
        
        // Now load lib.php
        require_once __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
    }

    public static function tearDownClass(): void
    {
        if (self::$testRoot && is_dir(self::$testRoot)) {
            exec('rm -rf ' . escapeshellarg(self::$testRoot));
        }
    }

    /**
     * Test: Symlink pointing outside /mnt/ should be rejected
     * 
     * Attack: Create symlink /mnt/user/evil -> /etc
     *         Submit share with path /mnt/user/evil
     * 
     * Expected: Validation rejects because realpath resolves to /etc
     */
    public function testSymlinkToSystemDirectoryBlocked()
    {
        // Create target directory outside /mnt/
        $systemDir = self::$testRoot . '/etc';
        file_put_contents($systemDir . '/passwd', 'root:x:0:0:root:/root:/bin/bash');

        // Create symlink in /mnt/user/ pointing to /etc
        $evilLink = self::$testRoot . '/mnt/user/evil';
        symlink($systemDir, $evilLink);

        // Attempt to create share using symlink path
        $share = [
            'name' => 'EvilShare',
            'path' => '/mnt/user/evil'  // Symlink points to /etc
        ];

        $errors = validateShare($share);

        // Attack should be blocked
        $this->assertNotEmpty($errors, 'Symlink attack should be blocked');
        $this->assertStringContainsString('Invalid path', $errors[0]);
    }

    /**
     * Test: Symlink pointing to another location in /mnt/ should be allowed
     * 
     * Valid use case: Symlink /mnt/user/link -> /mnt/disk1/data
     * 
     * Expected: Validation allows because realpath still under /mnt/
     */
    public function testSymlinkWithinMntAllowed()
    {
        // Create target directory in /mnt/disk1/
        $targetDir = self::$testRoot . '/mnt/disk1/data';
        mkdir($targetDir, 0755, true);

        // Create symlink in /mnt/user/ pointing to /mnt/disk1/data
        $validLink = self::$testRoot . '/mnt/user/validlink';
        symlink($targetDir, $validLink);

        // Create share using symlink path
        $share = [
            'name' => 'ValidLink',
            'path' => '/mnt/user/validlink'  // Points to /mnt/disk1/data
        ];

        $errors = validateShare($share);

        // Should be allowed (symlink target is still under /mnt/)
        $this->assertEmpty($errors, 'Symlink within /mnt/ should be allowed');
    }

    /**
     * Test: Relative path symlink attack should be blocked
     * 
     * Attack: Create symlink /mnt/user/evil -> ../../etc
     *         Submit share with path /mnt/user/evil
     * 
     * Expected: Validation rejects because realpath resolves outside /mnt/
     */
    public function testRelativeSymlinkAttackBlocked()
    {
        // Create symlink using relative path (goes up 2 levels from /mnt/user to test root, then to /etc)
        $evilLink = self::$testRoot . '/mnt/user/relative-evil';
        symlink('../../etc', $evilLink);

        // Attempt to create share using symlink
        $share = [
            'name' => 'RelativeEvil',
            'path' => '/mnt/user/relative-evil'
        ];

        $errors = validateShare($share);

        // Attack should be blocked
        $this->assertNotEmpty($errors, 'Relative symlink attack should be blocked');
        $this->assertStringContainsString('Invalid path', $errors[0]);
    }

    /**
     * Test: Chain of symlinks should be fully resolved
     * 
     * Attack: Create chain /mnt/user/link1 -> link2 -> /etc
     *         Submit share with path /mnt/user/link1
     * 
     * Expected: Validation resolves entire chain and rejects
     */
    public function testSymlinkChainBlocked()
    {
        // Create symlink chain
        $link2 = self::$testRoot . '/mnt/user/link2';
        symlink(self::$testRoot . '/etc', $link2);

        $link1 = self::$testRoot . '/mnt/user/link1';
        symlink($link2, $link1);

        // Attempt to create share using first link in chain
        $share = [
            'name' => 'ChainEvil',
            'path' => '/mnt/user/link1'
        ];

        $errors = validateShare($share);

        // Attack should be blocked
        $this->assertNotEmpty($errors, 'Symlink chain attack should be blocked');
        $this->assertStringContainsString('Invalid path', $errors[0]);
    }
}
