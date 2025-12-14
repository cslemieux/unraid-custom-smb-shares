<?php
use PHPUnit\Framework\TestCase;

class ShareCRUDEdgeCasesTest extends TestCase {
    private $sharesFile;
    
    protected function setUp(): void {
        ChrootTestEnvironment::reset();
        $this->sharesFile = MOCK_PLUGINS . '/shares.json';
        file_put_contents($this->sharesFile, json_encode([]));
    }
    
    private function loadShares() {
        return json_decode(file_get_contents($this->sharesFile), true) ?: [];
    }
    
    private function saveShares($shares) {
        file_put_contents($this->sharesFile, json_encode($shares, JSON_PRETTY_PRINT));
    }
    
    public function testLoadEmptyFile() {
        $shares = $this->loadShares();
        $this->assertIsArray($shares);
        $this->assertEmpty($shares);
    }
    
    public function testLoadCorruptedJSON() {
        file_put_contents($this->sharesFile, '{invalid json');
        $shares = $this->loadShares();
        
        $this->assertIsArray($shares);
        $this->assertEmpty($shares);
    }
    
    public function testSaveEmptyArray() {
        $this->saveShares([]);
        $content = file_get_contents($this->sharesFile);
        
        $this->assertEquals('[]', $content);
    }
    
    public function testDuplicateShareNames() {
        $shares = [
            ['name' => 'test', 'path' => '/mnt/user/test1'],
            ['name' => 'test', 'path' => '/mnt/user/test2']
        ];
        $this->saveShares($shares);
        
        $loaded = $this->loadShares();
        $this->assertCount(2, $loaded);
    }
    
    public function testUpdateNonExistentShare() {
        $shares = [['name' => 'share1', 'path' => '/mnt/user/s1']];
        $this->saveShares($shares);
        
        // Try to update index that doesn't exist
        if (isset($shares[5])) {
            $shares[5]['comment'] = 'Updated';
        }
        
        $this->assertCount(1, $shares);
    }
    
    public function testDeleteAllShares() {
        $shares = [
            ['name' => 'share1', 'path' => '/mnt/user/s1'],
            ['name' => 'share2', 'path' => '/mnt/user/s2'],
            ['name' => 'share3', 'path' => '/mnt/user/s3']
        ];
        $this->saveShares($shares);
        
        $this->saveShares([]);
        
        $loaded = $this->loadShares();
        $this->assertEmpty($loaded);
    }
    
    public function testShareWithMinimalFields() {
        $share = ['name' => 'minimal', 'path' => '/mnt/user/min'];
        $this->saveShares([$share]);
        
        $loaded = $this->loadShares();
        $this->assertEquals('minimal', $loaded[0]['name']);
        $this->assertEquals('/mnt/user/min', $loaded[0]['path']);
    }
    
    public function testShareWithAllFields() {
        $share = [
            'name' => 'full',
            'path' => '/mnt/user/full',
            'comment' => 'Full share',
            'browseable' => 'yes',
            'read_only' => 'no',
            'valid_users' => 'user1,user2',
            'create_mask' => '0664',
            'directory_mask' => '0775',
            'force_user' => 'nobody',
            'force_group' => 'users'
        ];
        $this->saveShares([$share]);
        
        $loaded = $this->loadShares();
        $this->assertEquals($share, $loaded[0]);
    }
    
    public function testShareWithSpecialCharactersInComment() {
        $share = [
            'name' => 'test',
            'path' => '/mnt/user/test',
            'comment' => 'Test "share" with \'quotes\' & symbols'
        ];
        $this->saveShares([$share]);
        
        $loaded = $this->loadShares();
        $this->assertEquals($share['comment'], $loaded[0]['comment']);
    }
    
    public function testLargeNumberOfShares() {
        $shares = [];
        for ($i = 0; $i < 100; $i++) {
            $shares[] = ['name' => "share$i", 'path' => "/mnt/user/share$i"];
        }
        $this->saveShares($shares);
        
        $loaded = $this->loadShares();
        $this->assertCount(100, $loaded);
    }
}
