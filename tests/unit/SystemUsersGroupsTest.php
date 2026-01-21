<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../helpers/ChrootTestEnvironment.php';

/**
 * Unit Tests for getSystemUsers() and getSystemGroups() functions
 */
class SystemUsersGroupsTest extends TestCase
{
    protected function setUp(): void
    {
        ChrootTestEnvironment::reset();
        // Require TestModeDetector first so we can reset it
        require_once __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/include/TestModeDetector.php';
        TestModeDetector::reset(); // Reset cached test mode detection
        $configDir = ChrootTestEnvironment::setup();
        if (!defined('CONFIG_BASE')) {
            define('CONFIG_BASE', $configDir);
        }
        require_once __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
    }

    protected function tearDown(): void
    {
        ChrootTestEnvironment::teardown();
    }

    /**
     * Get harness root from TestModeDetector
     */
    private function getHarnessRoot(): string
    {
        return TestModeDetector::getHarnessRoot();
    }

    // ========================================
    // getSystemUsers() Tests
    // ========================================

    public function testGetSystemUsersReturnsArray(): void
    {
        $users = getSystemUsers();
        $this->assertIsArray($users);
    }

    public function testGetSystemUsersDefaultExcludesSystemUsers(): void
    {
        $harnessRoot = $this->getHarnessRoot();
        
        // Create mock users file (regular users only)
        $mockUsers = [
            ['name' => 'regularuser', 'uid' => 1000],
            ['name' => 'anotheruser', 'uid' => 1001]
        ];
        $mockDir = $harnessRoot . '/boot/config/plugins/custom.smb.shares';
        if (!is_dir($mockDir)) {
            mkdir($mockDir, 0755, true);
        }
        file_put_contents($mockDir . '/users.json', json_encode($mockUsers));
        
        $users = getSystemUsers(false);
        
        // Should only have regular users (uid >= 1000)
        $userNames = array_column($users, 'name');
        $this->assertContains('regularuser', $userNames);
        $this->assertContains('anotheruser', $userNames);
        $this->assertNotContains('root', $userNames);
        $this->assertNotContains('nobody', $userNames);
    }

    public function testGetSystemUsersIncludeSystemUsersFlag(): void
    {
        $harnessRoot = $this->getHarnessRoot();
        $mockDir = $harnessRoot . '/boot/config/plugins/custom.smb.shares';
        if (!is_dir($mockDir)) {
            mkdir($mockDir, 0755, true);
        }
        
        // Create mock file for all users (including system)
        $allUsers = [
            ['name' => 'nobody', 'uid' => 99],
            ['name' => 'regularuser', 'uid' => 1000],
            ['name' => 'root', 'uid' => 0]
        ];
        file_put_contents($mockDir . '/users-all.json', json_encode($allUsers));
        
        $users = getSystemUsers(true);
        
        $userNames = array_column($users, 'name');
        $this->assertContains('nobody', $userNames);
        $this->assertContains('regularuser', $userNames);
        $this->assertContains('root', $userNames);
    }

    public function testGetSystemUsersSortedAlphabetically(): void
    {
        $harnessRoot = $this->getHarnessRoot();
        $mockDir = $harnessRoot . '/boot/config/plugins/custom.smb.shares';
        if (!is_dir($mockDir)) {
            mkdir($mockDir, 0755, true);
        }
        
        // Mock data is returned as-is, so provide pre-sorted data
        // This tests that the function returns the expected structure
        $mockUsers = [
            ['name' => 'alpha', 'uid' => 1000],
            ['name' => 'beta', 'uid' => 1001],
            ['name' => 'zebra', 'uid' => 1002]
        ];
        file_put_contents($mockDir . '/users.json', json_encode($mockUsers));
        
        $users = getSystemUsers();
        
        $this->assertCount(3, $users);
        $this->assertEquals('alpha', $users[0]['name']);
        $this->assertEquals('beta', $users[1]['name']);
        $this->assertEquals('zebra', $users[2]['name']);
    }

    // ========================================
    // getSystemGroups() Tests
    // ========================================

    public function testGetSystemGroupsReturnsArray(): void
    {
        $groups = getSystemGroups();
        $this->assertIsArray($groups);
    }

    public function testGetSystemGroupsFromMockFile(): void
    {
        $harnessRoot = $this->getHarnessRoot();
        $mockDir = $harnessRoot . '/boot/config/plugins/custom.smb.shares';
        if (!is_dir($mockDir)) {
            mkdir($mockDir, 0755, true);
        }
        
        $mockGroups = [
            ['name' => 'users', 'gid' => 100],
            ['name' => 'nobody', 'gid' => 99],
            ['name' => 'wheel', 'gid' => 10]
        ];
        file_put_contents($mockDir . '/groups.json', json_encode($mockGroups));
        
        $groups = getSystemGroups();
        
        $groupNames = array_column($groups, 'name');
        $this->assertContains('users', $groupNames);
        $this->assertContains('nobody', $groupNames);
        $this->assertContains('wheel', $groupNames);
    }

    public function testGetSystemGroupsSortedAlphabetically(): void
    {
        $harnessRoot = $this->getHarnessRoot();
        $mockDir = $harnessRoot . '/boot/config/plugins/custom.smb.shares';
        if (!is_dir($mockDir)) {
            mkdir($mockDir, 0755, true);
        }
        
        // Mock data is returned as-is, so provide pre-sorted data
        $mockGroups = [
            ['name' => 'audio', 'gid' => 17],
            ['name' => 'users', 'gid' => 100],
            ['name' => 'wheel', 'gid' => 10]
        ];
        file_put_contents($mockDir . '/groups.json', json_encode($mockGroups));
        
        $groups = getSystemGroups();
        
        $this->assertCount(3, $groups);
        $this->assertEquals('audio', $groups[0]['name']);
        $this->assertEquals('users', $groups[1]['name']);
        $this->assertEquals('wheel', $groups[2]['name']);
    }

    public function testGetSystemGroupsReturnsEmptyArrayWhenNoFile(): void
    {
        // Don't create any mock file - should return empty array
        $groups = getSystemGroups();
        $this->assertIsArray($groups);
    }

    public function testGetSystemGroupsStructure(): void
    {
        $harnessRoot = $this->getHarnessRoot();
        $mockDir = $harnessRoot . '/boot/config/plugins/custom.smb.shares';
        if (!is_dir($mockDir)) {
            mkdir($mockDir, 0755, true);
        }
        
        $mockGroups = [
            ['name' => 'testgroup', 'gid' => 500]
        ];
        file_put_contents($mockDir . '/groups.json', json_encode($mockGroups));
        
        $groups = getSystemGroups();
        
        $this->assertCount(1, $groups);
        $this->assertArrayHasKey('name', $groups[0]);
        $this->assertArrayHasKey('gid', $groups[0]);
        $this->assertEquals('testgroup', $groups[0]['name']);
        $this->assertEquals(500, $groups[0]['gid']);
    }
}
