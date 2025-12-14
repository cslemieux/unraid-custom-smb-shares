<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../helpers/ChrootTestEnvironment.php';
require_once __DIR__ . '/../harness/SambaMock.php';

/**
 * Tests for Samba verification functions
 * - verifySambaShare()
 * - ensureSambaInclude()
 * 
 * Uses explicit path parameters to avoid CONFIG_BASE constant issues.
 */
class SambaVerificationTest extends TestCase
{
    private string $configDir;
    private string $chrootDir;
    private string $configFile;
    private string $testparmPath;

    protected function setUp(): void
    {
        ChrootTestEnvironment::reset();
        $this->configDir = ChrootTestEnvironment::setup();
        $this->chrootDir = ChrootTestEnvironment::getChrootDir();

        // Define CONFIG_BASE only if not already defined
        if (!defined('CONFIG_BASE')) {
            define('CONFIG_BASE', $this->configDir);
        }

        // Initialize Samba mock and create scripts
        SambaMock::init($this->chrootDir);
        SambaMock::reload();

        // Set up paths for verifySambaShare
        $this->configFile = $this->configDir . '/plugins/custom.smb.shares/smb-extra.conf';
        $this->testparmPath = $this->chrootDir . '/usr/bin/testparm';
        @mkdir(dirname($this->configFile), 0755, true);

        // Create test directories
        ChrootTestEnvironment::mkdir('user/testshare');
        ChrootTestEnvironment::mkdir('user/share1');
        ChrootTestEnvironment::mkdir('user/share2');
        ChrootTestEnvironment::mkdir('user/share3');

        require_once __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
    }

    /**
     * Write config to the path that verifySambaShare expects
     */
    private function writeConfig(string $content): void
    {
        file_put_contents($this->configFile, $content);
    }

    /**
     * Helper to call verifySambaShare with our test paths
     */
    private function verifyShare(string $shareName, bool $shouldExist = true): bool
    {
        return verifySambaShare($shareName, $shouldExist, $this->configFile, $this->testparmPath);
    }

    // ========================================
    // verifySambaShare() Tests
    // ========================================

    /**
     * Test verifySambaShare returns true when share exists
     */
    public function testVerifySambaShareReturnsTrueWhenShareExists(): void
    {
        $shares = [
            [
                'name' => 'ExistingShare',
                'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
                'security' => 'public'
            ]
        ];

        $config = generateSambaConfig($shares);
        $this->writeConfig($config);

        $result = $this->verifyShare('ExistingShare', true);
        $this->assertTrue($result, 'verifySambaShare should return true for existing share');
    }

    /**
     * Test verifySambaShare returns false when share doesn't exist
     */
    public function testVerifySambaShareReturnsFalseWhenShareMissing(): void
    {
        $this->writeConfig('');

        $result = $this->verifyShare('NonExistentShare', true);
        $this->assertFalse($result, 'verifySambaShare should return false for missing share');
    }

    /**
     * Test verifySambaShare with shouldExist=false returns true when share is missing
     */
    public function testVerifySambaShareShouldNotExistReturnsTrueWhenMissing(): void
    {
        $this->writeConfig('');

        $result = $this->verifyShare('NonExistentShare', false);
        $this->assertTrue($result, 'verifySambaShare(shouldExist=false) should return true when share is missing');
    }

    /**
     * Test verifySambaShare with shouldExist=false returns false when share exists
     */
    public function testVerifySambaShareShouldNotExistReturnsFalseWhenExists(): void
    {
        $shares = [
            [
                'name' => 'ExistingShare',
                'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
                'security' => 'public'
            ]
        ];

        $config = generateSambaConfig($shares);
        $this->writeConfig($config);

        $result = $this->verifyShare('ExistingShare', false);
        $this->assertFalse($result, 'verifySambaShare(shouldExist=false) should return false when share exists');
    }

    /**
     * Test verifySambaShare with multiple shares
     */
    public function testVerifySambaShareWithMultipleShares(): void
    {
        $shares = [
            ['name' => 'Share1', 'path' => ChrootTestEnvironment::getMntPath('user/share1')],
            ['name' => 'Share2', 'path' => ChrootTestEnvironment::getMntPath('user/share2')],
            ['name' => 'Share3', 'path' => ChrootTestEnvironment::getMntPath('user/share3')]
        ];

        $config = generateSambaConfig($shares);
        $this->writeConfig($config);

        // All three should exist
        $this->assertTrue($this->verifyShare('Share1', true));
        $this->assertTrue($this->verifyShare('Share2', true));
        $this->assertTrue($this->verifyShare('Share3', true));

        // Non-existent should not
        $this->assertFalse($this->verifyShare('Share4', true));
    }

    /**
     * Test verifySambaShare is case-sensitive
     */
    public function testVerifySambaShareIsCaseSensitive(): void
    {
        $shares = [
            [
                'name' => 'MyShare',
                'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
                'security' => 'public'
            ]
        ];

        $config = generateSambaConfig($shares);
        $this->writeConfig($config);

        // Exact case should match
        $this->assertTrue($this->verifyShare('MyShare', true));

        // Different case should not match
        $this->assertFalse($this->verifyShare('myshare', true));
        $this->assertFalse($this->verifyShare('MYSHARE', true));
    }

    // ========================================
    // ensureSambaInclude() Tests
    // ========================================

    /**
     * Test ensureSambaInclude returns true in test mode
     */
    public function testEnsureSambaIncludeReturnsTrue(): void
    {
        $result = ensureSambaInclude();
        $this->assertTrue($result, 'ensureSambaInclude should return true in test mode');
    }

    // ========================================
    // Integration: Add share and verify
    // ========================================

    /**
     * Test full workflow: add share, reload, verify
     */
    public function testAddShareAndVerify(): void
    {
        // Start with no shares
        $this->writeConfig('');
        $this->assertFalse($this->verifyShare('NewShare', true));

        // Add a share
        $shares = [
            [
                'name' => 'NewShare',
                'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
                'comment' => 'Newly added share',
                'security' => 'public'
            ]
        ];

        $config = generateSambaConfig($shares);
        $this->writeConfig($config);

        // Reload Samba
        $result = SambaMock::reload();
        $this->assertTrue($result['success'], 'Samba reload should succeed');

        // Verify share now exists
        $this->assertTrue($this->verifyShare('NewShare', true));
    }

    /**
     * Test full workflow: remove share and verify
     */
    public function testRemoveShareAndVerify(): void
    {
        // Start with a share
        $shares = [
            [
                'name' => 'ToBeRemoved',
                'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
                'security' => 'public'
            ]
        ];

        $config = generateSambaConfig($shares);
        $this->writeConfig($config);
        $this->assertTrue($this->verifyShare('ToBeRemoved', true));

        // Remove the share
        $this->writeConfig('');

        // Reload Samba
        $result = SambaMock::reload();
        $this->assertTrue($result['success'], 'Samba reload should succeed');

        // Verify share no longer exists
        $this->assertTrue($this->verifyShare('ToBeRemoved', false));
    }
}
