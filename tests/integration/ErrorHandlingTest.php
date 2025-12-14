<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../helpers/ChrootTestEnvironment.php';

class ErrorHandlingTest extends TestCase
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
    
    public function testValidShareReturnsNoErrors()
    {
        $share = [
            'name' => 'ValidShare',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'create_mask' => '0664',
            'directory_mask' => '0775'
        ];
        
        $errors = validateShare($share);
        $this->assertEmpty($errors, 'Valid share should have no errors');
    }
    
    public function testInvalidShareNameReturnsError()
    {
        $share = [
            'name' => 'Invalid Name!',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare')
        ];
        
        $errors = validateShare($share);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('share name', strtolower($errors[0]));
    }
    
    public function testInvalidPathReturnsError()
    {
        $share = [
            'name' => 'ValidName',
            'path' => '/invalid/path'
        ];
        
        $errors = validateShare($share);
        $this->assertNotEmpty($errors);
    }
    
    public function testNonexistentPathReturnsError()
    {
        $share = [
            'name' => 'ValidName',
            'path' => ChrootTestEnvironment::getMntPath('user/nonexistent')
        ];
        
        $errors = validateShare($share);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('does not exist', $errors[0]);
    }
    
    public function testInvalidCreateMaskReturnsError()
    {
        $share = [
            'name' => 'ValidName',
            'path' => ChrootTestEnvironment::getMntPath('user/testshare'),
            'create_mask' => '9999'
        ];
        
        $errors = validateShare($share);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('mask', strtolower($errors[0]));
    }
    
    public function testMultipleErrorsReturned()
    {
        $share = [
            'name' => 'Invalid Name!',
            'path' => '/invalid/path',
            'create_mask' => 'abcd'
        ];
        
        $errors = validateShare($share);
        $this->assertGreaterThanOrEqual(2, count($errors));
    }
}
