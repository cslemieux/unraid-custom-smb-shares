<?php
use PHPUnit\Framework\TestCase;

class ShareCRUDTest extends TestCase {
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
    
    public function testCreateShare() {
        $shares = $this->loadShares();
        $newShare = [
            'name' => 'test_share',
            'path' => '/mnt/user/test',
            'comment' => 'Test share',
            'browseable' => 'yes',
            'read_only' => 'no'
        ];
        
        $shares[] = $newShare;
        $this->saveShares($shares);
        
        $loaded = $this->loadShares();
        $this->assertCount(1, $loaded);
        $this->assertEquals('test_share', $loaded[0]['name']);
    }
    
    public function testUpdateShare() {
        $shares = [['name' => 'test', 'path' => '/mnt/user/test']];
        $this->saveShares($shares);
        
        $shares[0]['comment'] = 'Updated';
        $this->saveShares($shares);
        
        $loaded = $this->loadShares();
        $this->assertEquals('Updated', $loaded[0]['comment']);
    }
    
    public function testDeleteShare() {
        $shares = [
            ['name' => 'share1', 'path' => '/mnt/user/s1'],
            ['name' => 'share2', 'path' => '/mnt/user/s2']
        ];
        $this->saveShares($shares);
        
        unset($shares[0]);
        $shares = array_values($shares);
        $this->saveShares($shares);
        
        $loaded = $this->loadShares();
        $this->assertCount(1, $loaded);
        $this->assertEquals('share2', $loaded[0]['name']);
    }
}
