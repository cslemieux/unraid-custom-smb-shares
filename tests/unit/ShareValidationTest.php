<?php
use PHPUnit\Framework\TestCase;

class ShareValidationTest extends TestCase {
    private function validateShare($data) {
        $errors = [];
        
        if (empty($data['name']) || !preg_match('/^[a-zA-Z0-9_-]+$/', $data['name'])) {
            $errors[] = 'Invalid share name';
        }
        
        if (empty($data['path']) || !preg_match('#^/mnt/#', $data['path'])) {
            $errors[] = 'Path must start with /mnt/';
        }
        
        if (isset($data['create_mask']) && !preg_match('/^[0-7]{4}$/', $data['create_mask'])) {
            $errors[] = 'Invalid create mask';
        }
        
        return empty($errors) ? true : $errors;
    }
    
    public function testValidShare() {
        $share = [
            'name' => 'test_share',
            'path' => '/mnt/user/data',
            'create_mask' => '0664'
        ];
        
        $this->assertTrue($this->validateShare($share));
    }
    
    public function testInvalidShareName() {
        $share = ['name' => 'test share!', 'path' => '/mnt/user/data'];
        $result = $this->validateShare($share);
        
        $this->assertIsArray($result);
        $this->assertContains('Invalid share name', $result);
    }
    
    public function testInvalidPath() {
        $share = ['name' => 'test', 'path' => '/home/data'];
        $result = $this->validateShare($share);
        
        $this->assertIsArray($result);
        $this->assertContains('Path must start with /mnt/', $result);
    }
    
    public function testInvalidMask() {
        $share = ['name' => 'test', 'path' => '/mnt/user/data', 'create_mask' => '999'];
        $result = $this->validateShare($share);
        
        $this->assertIsArray($result);
        $this->assertContains('Invalid create mask', $result);
    }
}
