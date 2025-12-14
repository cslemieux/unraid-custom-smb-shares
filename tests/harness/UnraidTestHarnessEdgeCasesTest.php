<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/UnraidTestHarness.php';

/**
 * Tests for edge cases and error conditions
 */
class UnraidTestHarnessEdgeCasesTest extends TestCase
{
    public function testTeardownCleansUpFiles()
    {
        $harness = UnraidTestHarness::setup(8893);
        $dir = $harness['harness_dir'];
        $lockFile = '/tmp/unraid-test-harness-8893.lock';
        
        $this->assertDirectoryExists($dir);
        $this->assertFileExists($lockFile);
        
        UnraidTestHarness::teardown();
        
        $this->assertDirectoryDoesNotExist($dir);
        $this->assertFileDoesNotExist($lockFile);
    }
    
    public function testCreateShareDirWithNestedPath()
    {
        $harness = UnraidTestHarness::setup(8894);
        
        $path = UnraidTestHarness::createShareDir('/mnt/user/nested/deep/path');
        
        $this->assertDirectoryExists($path);
        $this->assertStringContainsString('/mnt/user/nested/deep/path', $path);
        
        UnraidTestHarness::teardown();
    }
    
    public function testEmptyTestDirsArray()
    {
        $config = [
            'port' => 8895,
            'testDirs' => []
        ];
        
        $harness = UnraidTestHarness::setup($config);
        
        // Should still create /mnt/user directory
        $this->assertDirectoryExists($harness['harness_dir'] . '/mnt/user');
        
        UnraidTestHarness::teardown();
    }
    
    public function testPortBelowMinimum()
    {
        $this->expectException(InvalidArgumentException::class);
        UnraidTestHarness::setup(1023);
    }
    
    public function testPortAboveMaximum()
    {
        $this->expectException(InvalidArgumentException::class);
        UnraidTestHarness::setup(65536);
    }
    
    public function testGetUrlReturnsCurrentUrl()
    {
        $harness = UnraidTestHarness::setup(8896);
        $url = UnraidTestHarness::getUrl();
        $this->assertEquals('http://localhost:8896', $url);
        UnraidTestHarness::teardown();
    }
    
    public function testMultipleSetupCallsOnSamePort()
    {
        // First setup
        $harness1 = UnraidTestHarness::setup(8897);
        $dir1 = $harness1['harness_dir'];
        $this->assertDirectoryExists($dir1);
        
        // Must teardown first harness before second setup
        UnraidTestHarness::teardown();
        
        // Second setup on same port should succeed
        $harness2 = UnraidTestHarness::setup(8897);
        $dir2 = $harness2['harness_dir'];
        
        $this->assertDirectoryExists($dir2);
        $this->assertNotEquals($dir1, $dir2, 'Should create new harness directory');
        
        // First harness dir should be cleaned up by teardown
        $this->assertDirectoryDoesNotExist($dir1);
        
        UnraidTestHarness::teardown();
    }
    
    public function testPortAtMinimumBoundary()
    {
        $harness = UnraidTestHarness::setup(1024);
        $this->assertEquals('http://localhost:1024', $harness['url']);
        
        // Verify server actually started
        $response = @file_get_contents($harness['url'] . '/plugins/custom.smb.shares/status.php', false, stream_context_create([
            'http' => ['timeout' => 5]
        ]));
        $this->assertNotFalse($response, 'Server should respond on port 1024');
        
        UnraidTestHarness::teardown();
    }
    
    public function testPortAtMaximumBoundary()
    {
        $harness = UnraidTestHarness::setup(65535);
        $this->assertEquals('http://localhost:65535', $harness['url']);
        
        // Verify server actually started
        $response = @file_get_contents($harness['url'] . '/plugins/custom.smb.shares/status.php', false, stream_context_create([
            'http' => ['timeout' => 5]
        ]));
        $this->assertNotFalse($response, 'Server should respond on port 65535');
        
        UnraidTestHarness::teardown();
    }
}
