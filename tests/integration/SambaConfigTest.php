<?php
use PHPUnit\Framework\TestCase;

class SambaConfigTest extends TestCase {
    private function generateConfig($shares) {
        $config = '';
        foreach ($shares as $share) {
            $config .= "[{$share['name']}]\n";
            $config .= "  path = {$share['path']}\n";
            
            if (!empty($share['comment'])) {
                $config .= "  comment = {$share['comment']}\n";
            }
            
            $config .= "  browseable = " . ($share['browseable'] ?? 'yes') . "\n";
            $config .= "  read only = " . ($share['read_only'] ?? 'no') . "\n";
            $config .= "\n";
        }
        return $config;
    }
    
    public function testGenerateEmptyConfig() {
        $config = $this->generateConfig([]);
        $this->assertEquals('', $config);
    }
    
    public function testGenerateSingleShare() {
        $shares = [[
            'name' => 'test',
            'path' => '/mnt/user/test',
            'comment' => 'Test Share',
            'browseable' => 'yes',
            'read_only' => 'no'
        ]];
        
        $config = $this->generateConfig($shares);
        
        $this->assertStringContainsString('[test]', $config);
        $this->assertStringContainsString('path = /mnt/user/test', $config);
        $this->assertStringContainsString('comment = Test Share', $config);
    }
    
    public function testGenerateMultipleShares() {
        $shares = [
            ['name' => 'share1', 'path' => '/mnt/user/s1'],
            ['name' => 'share2', 'path' => '/mnt/user/s2']
        ];
        
        $config = $this->generateConfig($shares);
        
        $this->assertStringContainsString('[share1]', $config);
        $this->assertStringContainsString('[share2]', $config);
    }
    
    public function testConfigWriteAndRead() {
        $configFile = MOCK_CONFIG . '/smb-extra.conf';
        $shares = [['name' => 'test', 'path' => '/mnt/user/test']];
        
        $config = $this->generateConfig($shares);
        file_put_contents($configFile, $config);
        
        $content = file_get_contents($configFile);
        $this->assertEquals($config, $content);
    }
}
