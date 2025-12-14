<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class PermissionParsingTest extends TestCase
{
    public function testParseEmptyPermissions(): void
    {
        $_POST = ['permissions' => ''];
        
        $readList = [];
        $writeList = [];
        if (!empty($_POST['permissions'])) {
            $permissions = json_decode($_POST['permissions'], true);
            if (is_array($permissions)) {
                $readList = $permissions['read'] ?? [];
                $writeList = $permissions['write'] ?? [];
            }
        }
        
        $this->assertEmpty($readList);
        $this->assertEmpty($writeList);
    }
    
    public function testParseReadOnlyPermissions(): void
    {
        $_POST = ['permissions' => json_encode([
            'read' => ['user1', 'user2', '@group1'],
            'write' => []
        ])];
        
        $permissions = json_decode($_POST['permissions'], true);
        $readList = $permissions['read'] ?? [];
        $writeList = $permissions['write'] ?? [];
        
        $this->assertEquals(['user1', 'user2', '@group1'], $readList);
        $this->assertEmpty($writeList);
    }
    
    public function testParseWriteOnlyPermissions(): void
    {
        $_POST = ['permissions' => json_encode([
            'read' => [],
            'write' => ['user1', '@group1']
        ])];
        
        $permissions = json_decode($_POST['permissions'], true);
        $readList = $permissions['read'] ?? [];
        $writeList = $permissions['write'] ?? [];
        
        $this->assertEmpty($readList);
        $this->assertEquals(['user1', '@group1'], $writeList);
    }
    
    public function testParseBothPermissions(): void
    {
        $_POST = ['permissions' => json_encode([
            'read' => ['user1', 'user2'],
            'write' => ['user3', '@group1']
        ])];
        
        $permissions = json_decode($_POST['permissions'], true);
        $readList = $permissions['read'] ?? [];
        $writeList = $permissions['write'] ?? [];
        
        $this->assertEquals(['user1', 'user2'], $readList);
        $this->assertEquals(['user3', '@group1'], $writeList);
    }
    
    public function testConvertToCommaList(): void
    {
        $readList = ['user1', 'user2', '@group1'];
        $writeList = ['user3', '@group2'];
        
        $readListStr = implode(',', $readList);
        $writeListStr = implode(',', $writeList);
        
        $this->assertEquals('user1,user2,@group1', $readListStr);
        $this->assertEquals('user3,@group2', $writeListStr);
    }
    
    public function testInvalidJSON(): void
    {
        $_POST = ['permissions' => 'invalid json'];
        
        $readList = [];
        $writeList = [];
        if (!empty($_POST['permissions'])) {
            $permissions = json_decode($_POST['permissions'], true);
            if (is_array($permissions)) {
                $readList = $permissions['read'] ?? [];
                $writeList = $permissions['write'] ?? [];
            }
        }
        
        $this->assertEmpty($readList);
        $this->assertEmpty($writeList);
    }
}
