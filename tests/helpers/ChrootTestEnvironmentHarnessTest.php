<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ChrootTestEnvironment.php';

/**
 * Harness Verification Tests for ChrootTestEnvironment
 * Verifies test harness chroot structure and path handling
 */
class ChrootTestEnvironmentHarnessTest extends TestCase
{
    public function testChrootStructureCreated()
    {
        $configDir = ChrootTestEnvironment::setup();
        
        // Verify CONFIG_BASE path structure
        $this->assertStringStartsWith('/tmp/chroot-test-', $configDir);
        $this->assertStringEndsWith('/usr/local/boot/config', $configDir);
        $this->assertDirectoryExists($configDir);
        
        ChrootTestEnvironment::teardown();
    }
    
    public function testMntDirectoriesExist()
    {
        ChrootTestEnvironment::setup();
        
        $this->assertDirectoryExists(ChrootTestEnvironment::getMntPath('user'));
        $this->assertDirectoryExists(ChrootTestEnvironment::getMntPath('disk1'));
        $this->assertDirectoryExists(ChrootTestEnvironment::getMntPath('cache'));
        
        ChrootTestEnvironment::teardown();
    }
    
    public function testPathResolution()
    {
        ChrootTestEnvironment::setup();
        
        $path = ChrootTestEnvironment::getMntPath('user/testshare');
        
        // Path should contain /tmp/ (test mode detection)
        $this->assertStringContainsString('/tmp/', $path);
        
        // Path should contain /mnt/ (validation pattern)
        $this->assertStringContainsString('/mnt/', $path);
        
        // Path should end with user/testshare
        $this->assertStringEndsWith('/mnt/user/testshare', $path);
        
        ChrootTestEnvironment::teardown();
    }
    
    public function testMkdirCreatesDirectories()
    {
        ChrootTestEnvironment::setup();
        
        ChrootTestEnvironment::mkdir('user/test1');
        ChrootTestEnvironment::mkdir('user/test2/nested');
        
        $this->assertDirectoryExists(ChrootTestEnvironment::getMntPath('user/test1'));
        $this->assertDirectoryExists(ChrootTestEnvironment::getMntPath('user/test2/nested'));
        
        ChrootTestEnvironment::teardown();
    }
    
    public function testConfigBaseTriggersTestMode()
    {
        $configDir = ChrootTestEnvironment::setup();
        
        // CONFIG_BASE should contain /tmp/ to trigger test mode
        $this->assertStringContainsString('/tmp/', $configDir);
        
        // Verify test mode detection logic would work
        $testMode = strpos($configDir, '/tmp/') !== false;
        $this->assertTrue($testMode, 'CONFIG_BASE should trigger test mode detection');
        
        ChrootTestEnvironment::teardown();
    }
    
    public function testHarnessRootExtraction()
    {
        $configDir = ChrootTestEnvironment::setup();
        $harnessRoot = ChrootTestEnvironment::getChrootDir();
        
        // Harness root should be 4 levels up from CONFIG_BASE
        // CONFIG_BASE: /tmp/chroot-test-xxx/usr/local/boot/config
        // Harness root: /tmp/chroot-test-xxx
        $this->assertEquals(
            $harnessRoot,
            dirname(dirname(dirname(dirname($configDir))))
        );
        
        ChrootTestEnvironment::teardown();
    }
    
    public function testCleanupRemovesAllFiles()
    {
        $configDir = ChrootTestEnvironment::setup();
        $harnessRoot = ChrootTestEnvironment::getChrootDir();
        
        ChrootTestEnvironment::mkdir('user/cleanup-test');
        $this->assertDirectoryExists($harnessRoot);
        
        ChrootTestEnvironment::teardown();
        $this->assertDirectoryDoesNotExist($harnessRoot);
    }
}
