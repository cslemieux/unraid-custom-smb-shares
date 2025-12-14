<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for user-browser.js logic (simulated in PHP)
 */
class UserBrowserJavaScriptTest extends TestCase
{
    public function testUserBrowserDataStructure(): void
    {
        $users = [
            ['name' => 'user1', 'uid' => 1000],
            ['name' => 'user2', 'uid' => 1001]
        ];
        
        $groups = [
            ['name' => 'group1', 'gid' => 1000],
            ['name' => 'group2', 'gid' => 1001]
        ];
        
        $this->assertIsArray($users);
        $this->assertIsArray($groups);
        $this->assertCount(2, $users);
        $this->assertCount(2, $groups);
    }
    
    public function testGroupPrefixing(): void
    {
        $groupName = 'users';
        $prefixed = '@' . $groupName;
        
        $this->assertEquals('@users', $prefixed);
        $this->assertStringStartsWith('@', $prefixed);
    }
    
    public function testSelectionTracking(): void
    {
        $selected = [];
        
        // Add user
        $selected[] = 'user1';
        $this->assertContains('user1', $selected);
        
        // Add group
        $selected[] = '@group1';
        $this->assertContains('@group1', $selected);
        
        // Remove user
        $key = array_search('user1', $selected);
        if ($key !== false) {
            array_splice($selected, $key, 1);
        }
        $this->assertNotContains('user1', $selected);
        $this->assertContains('@group1', $selected);
    }
    
    public function testSearchFilter(): void
    {
        $users = ['root', 'user1', 'user2', 'admin'];
        $query = 'user';
        
        $filtered = array_filter($users, function($user) use ($query) {
            return stripos($user, $query) !== false;
        });
        
        $this->assertCount(2, $filtered);
        $this->assertContains('user1', $filtered);
        $this->assertContains('user2', $filtered);
    }
    
    public function testPermissionDataFormat(): void
    {
        $data = [
            'read' => ['user1', 'user2', '@group1'],
            'write' => ['user3', '@group2']
        ];
        
        $json = json_encode($data);
        $decoded = json_decode($json, true);
        
        $this->assertEquals($data, $decoded);
        $this->assertArrayHasKey('read', $decoded);
        $this->assertArrayHasKey('write', $decoded);
        $this->assertIsArray($decoded['read']);
        $this->assertIsArray($decoded['write']);
    }
}
