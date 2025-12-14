<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../helpers/ChrootTestEnvironment.php';

class PHPScriptsIntegrationTest extends TestCase
{
    private static $configDir;
    
    public static function setUpBeforeClass(): void
    {
        self::$configDir = ChrootTestEnvironment::setup();
        if (!defined('CONFIG_BASE')) define('CONFIG_BASE', self::$configDir);
        require_once __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
    }
    
    
    protected function setUp(): void
    {
        ChrootTestEnvironment::reset();
        ChrootTestEnvironment::mkdir('user/testshare');
    }
    
    public function testValidateShareFunction()
    {
        $validShare = [
            'name' => 'TestShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare')
        ];
        
        $errors = validateShare($validShare);
        if (!empty($errors)) {
            $this->fail(sprintf(
                'Valid share should pass validation. Errors: %s, CONFIG_BASE: %s, Path: %s',
                json_encode($errors),
                defined('CONFIG_BASE') ? CONFIG_BASE : 'NOT DEFINED',
                $validShare['path']
            ));
        }
        $this->assertEmpty($errors, 'Valid share should pass validation');
    }
    
    public function testLoadSharesFunction()
    {
        $sharesFile = self::$configDir . '/plugins/custom.smb.shares/shares.json';
        mkdir(dirname($sharesFile), 0755, true);
        
        $testShares = [
            ['name' => 'Share1', 'path' => ChrootTestEnvironment::getMntPath('user/share1')],
            ['name' => 'Share2', 'path' => ChrootTestEnvironment::getMntPath('user/share2')]
        ];
        
        file_put_contents($sharesFile, json_encode($testShares));
        
        $shares = loadShares(self::$configDir);
        $this->assertCount(2, $shares);
        $this->assertEquals('Share1', $shares[0]['name']);
    }
    
    public function testSaveSharesFunction()
    {
        $testShares = [
            ['name' => 'NewShare', 'path' => ChrootTestEnvironment::getMntPath('user/new')]
        ];
        
        saveShares($testShares, self::$configDir);
        
        $sharesFile = self::$configDir . '/plugins/custom.smb.shares/shares.json';
        $this->assertFileExists($sharesFile);
        $saved = json_decode(file_get_contents($sharesFile), true);
        $this->assertEquals('NewShare', $saved[0]['name']);
    }
    
    public function testGenerateSambaConfigFunction()
    {
        $shares = [
            [
                'name' => 'TestShare',
                'path' => ChrootTestEnvironment::getMntPath('user/test'),
                'comment' => 'Test',
                'browseable' => 'yes'
            ]
        ];
        
        $config = generateSambaConfig($shares);
        
        $this->assertStringContainsString('[TestShare]', $config);
        $this->assertStringContainsString('path = ', $config);
        $this->assertStringContainsString('browseable = yes', $config);
    }
}
