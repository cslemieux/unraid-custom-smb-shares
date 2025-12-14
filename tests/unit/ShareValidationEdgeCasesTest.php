<?php
use PHPUnit\Framework\TestCase;

class ShareValidationEdgeCasesTest extends TestCase {
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
        
        if (isset($data['directory_mask']) && !preg_match('/^[0-7]{4}$/', $data['directory_mask'])) {
            $errors[] = 'Invalid directory mask';
        }
        
        return empty($errors) ? true : $errors;
    }
    
    public function testEmptyShareName() {
        $share = ['name' => '', 'path' => '/mnt/user/data'];
        $result = $this->validateShare($share);
        
        $this->assertIsArray($result);
        $this->assertContains('Invalid share name', $result);
    }
    
    public function testShareNameWithSpaces() {
        $share = ['name' => 'my share', 'path' => '/mnt/user/data'];
        $result = $this->validateShare($share);
        
        $this->assertIsArray($result);
    }
    
    public function testShareNameWithSpecialChars() {
        $share = ['name' => 'share@#$', 'path' => '/mnt/user/data'];
        $result = $this->validateShare($share);
        
        $this->assertIsArray($result);
    }
    
    public function testValidShareNameWithUnderscore() {
        $share = ['name' => 'my_share', 'path' => '/mnt/user/data'];
        $this->assertTrue($this->validateShare($share));
    }
    
    public function testValidShareNameWithHyphen() {
        $share = ['name' => 'my-share', 'path' => '/mnt/user/data'];
        $this->assertTrue($this->validateShare($share));
    }
    
    public function testEmptyPath() {
        $share = ['name' => 'test', 'path' => ''];
        $result = $this->validateShare($share);
        
        $this->assertIsArray($result);
    }
    
    public function testRelativePath() {
        $share = ['name' => 'test', 'path' => 'relative/path'];
        $result = $this->validateShare($share);
        
        $this->assertIsArray($result);
    }
    
    public function testPathWithoutMnt() {
        $share = ['name' => 'test', 'path' => '/home/user/data'];
        $result = $this->validateShare($share);
        
        $this->assertIsArray($result);
    }
    
    public function testValidMntUserPath() {
        $share = ['name' => 'test', 'path' => '/mnt/user/appdata'];
        $this->assertTrue($this->validateShare($share));
    }
    
    public function testValidMntDiskPath() {
        $share = ['name' => 'test', 'path' => '/mnt/disk1/data'];
        $this->assertTrue($this->validateShare($share));
    }
    
    public function testValidMntCachePath() {
        $share = ['name' => 'test', 'path' => '/mnt/cache/downloads'];
        $this->assertTrue($this->validateShare($share));
    }
    
    public function testMaskWithLetters() {
        $share = ['name' => 'test', 'path' => '/mnt/user/data', 'create_mask' => '066a'];
        $result = $this->validateShare($share);
        
        $this->assertIsArray($result);
    }
    
    public function testMaskTooShort() {
        $share = ['name' => 'test', 'path' => '/mnt/user/data', 'create_mask' => '066'];
        $result = $this->validateShare($share);
        
        $this->assertIsArray($result);
    }
    
    public function testMaskTooLong() {
        $share = ['name' => 'test', 'path' => '/mnt/user/data', 'create_mask' => '06644'];
        $result = $this->validateShare($share);
        
        $this->assertIsArray($result);
    }
    
    public function testMaskWithInvalidOctal() {
        $share = ['name' => 'test', 'path' => '/mnt/user/data', 'create_mask' => '0888'];
        $result = $this->validateShare($share);
        
        $this->assertIsArray($result);
    }
    
    public function testValidDirectoryMask() {
        $share = ['name' => 'test', 'path' => '/mnt/user/data', 'directory_mask' => '0775'];
        $this->assertTrue($this->validateShare($share));
    }
    
    public function testInvalidDirectoryMask() {
        $share = ['name' => 'test', 'path' => '/mnt/user/data', 'directory_mask' => '999'];
        $result = $this->validateShare($share);
        
        $this->assertIsArray($result);
    }
    
    public function testMultipleErrors() {
        $share = ['name' => 'bad name!', 'path' => '/home/data', 'create_mask' => '999'];
        $result = $this->validateShare($share);
        
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(2, count($result));
    }
}
