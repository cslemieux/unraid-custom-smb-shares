<?php
use PHPUnit\Framework\TestCase;

class SambaConfigEdgeCasesTest extends TestCase {
    private function generateConfig($shares) {
        $config = '';
        foreach ($shares as $share) {
            $config .= "[{$share['name']}]\n";
            $config .= "  path = {$share['path']}\n";
            
            foreach (['comment', 'valid_users', 'force_user', 'force_group'] as $field) {
                if (!empty($share[$field])) {
                    $config .= "  $field = {$share[$field]}\n";
                }
            }
            
            $config .= "  browseable = " . ($share['browseable'] ?? 'yes') . "\n";
            $config .= "  read only = " . ($share['read_only'] ?? 'no') . "\n";
            
            if (!empty($share['create_mask'])) {
                $config .= "  create mask = {$share['create_mask']}\n";
            }
            
            if (!empty($share['directory_mask'])) {
                $config .= "  directory mask = {$share['directory_mask']}\n";
            }
            
            $config .= "\n";
        }
        return $config;
    }
    
    public function testConfigWithSpecialCharactersInComment() {
        $shares = [[
            'name' => 'test',
            'path' => '/mnt/user/test',
            'comment' => 'Test & "special" chars'
        ]];
        
        $config = $this->generateConfig($shares);
        $this->assertStringContainsString('comment = Test & "special" chars', $config);
    }
    
    public function testConfigWithMultipleUsers() {
        $shares = [[
            'name' => 'test',
            'path' => '/mnt/user/test',
            'valid_users' => 'user1,user2,user3'
        ]];
        
        $config = $this->generateConfig($shares);
        $this->assertStringContainsString('valid_users = user1,user2,user3', $config);
    }
    
    public function testConfigWithForceUserGroup() {
        $shares = [[
            'name' => 'test',
            'path' => '/mnt/user/test',
            'force_user' => 'nobody',
            'force_group' => 'users'
        ]];
        
        $config = $this->generateConfig($shares);
        $this->assertStringContainsString('force_user = nobody', $config);
        $this->assertStringContainsString('force_group = users', $config);
    }
    
    public function testConfigWithAllPermissions() {
        $shares = [[
            'name' => 'test',
            'path' => '/mnt/user/test',
            'create_mask' => '0664',
            'directory_mask' => '0775'
        ]];
        
        $config = $this->generateConfig($shares);
        $this->assertStringContainsString('create mask = 0664', $config);
        $this->assertStringContainsString('directory mask = 0775', $config);
    }
    
    public function testConfigWithReadOnlyShare() {
        $shares = [[
            'name' => 'readonly',
            'path' => '/mnt/user/readonly',
            'read_only' => 'yes'
        ]];
        
        $config = $this->generateConfig($shares);
        $this->assertStringContainsString('read only = yes', $config);
    }
    
    public function testConfigWithHiddenShare() {
        $shares = [[
            'name' => 'hidden',
            'path' => '/mnt/user/hidden',
            'browseable' => 'no'
        ]];
        
        $config = $this->generateConfig($shares);
        $this->assertStringContainsString('browseable = no', $config);
    }
    
    public function testConfigWithLongPath() {
        $shares = [[
            'name' => 'test',
            'path' => '/mnt/user/very/long/path/to/share/directory/structure'
        ]];
        
        $config = $this->generateConfig($shares);
        $this->assertStringContainsString('path = /mnt/user/very/long/path/to/share/directory/structure', $config);
    }
    
    public function testConfigOrderPreserved() {
        $shares = [
            ['name' => 'share1', 'path' => '/mnt/user/s1'],
            ['name' => 'share2', 'path' => '/mnt/user/s2'],
            ['name' => 'share3', 'path' => '/mnt/user/s3']
        ];
        
        $config = $this->generateConfig($shares);
        
        $pos1 = strpos($config, '[share1]');
        $pos2 = strpos($config, '[share2]');
        $pos3 = strpos($config, '[share3]');
        
        $this->assertLessThan($pos2, $pos1);
        $this->assertLessThan($pos3, $pos2);
    }
    
    public function testConfigFilePermissions() {
        $configFile = MOCK_CONFIG . '/smb-extra.conf';
        $shares = [['name' => 'test', 'path' => '/mnt/user/test']];
        
        $config = $this->generateConfig($shares);
        file_put_contents($configFile, $config);
        
        $this->assertFileIsReadable($configFile);
    }
    
    public function testEmptyConfigCreatesEmptyFile() {
        $configFile = MOCK_CONFIG . '/smb-extra.conf';
        $config = $this->generateConfig([]);
        file_put_contents($configFile, $config);
        
        $this->assertEquals('', file_get_contents($configFile));
    }
}
