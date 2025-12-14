<?php
use PHPUnit\Framework\TestCase;

/**
 * CSRF Verification Tests
 * 
 * Verifies that our plugin correctly relies on Unraid's global CSRF validation
 * instead of implementing its own
 */
class CSRFVerificationTest extends TestCase
{
    public function testLibDoesNotHaveValidateCSRFFunction()
    {
        $libFile = __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
        $content = file_get_contents($libFile);
        
        $this->assertStringNotContainsString('function validateCSRF', $content,
            'lib.php should not have validateCSRF function - Unraid handles CSRF globally');
    }
    
    public function testLibDoesNotCheckSessionCSRF()
    {
        $libFile = __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
        $content = file_get_contents($libFile);
        
        $this->assertStringNotContainsString('$_SESSION[\'csrf_token\']', $content,
            'lib.php should not check $_SESSION for CSRF - Unraid uses $var[\'csrf_token\']');
    }
    
    public function testAddPhpDoesNotCallValidateCSRF()
    {
        $file = __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/add.php';
        $content = file_get_contents($file);
        
        $this->assertStringNotContainsString('validateCSRF()', $content,
            'add.php should not call validateCSRF() - Unraid validates in local_prepend.php');
    }
    
    public function testUpdatePhpDoesNotCallValidateCSRF()
    {
        $file = __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/update.php';
        $content = file_get_contents($file);
        
        $this->assertStringNotContainsString('validateCSRF()', $content,
            'update.php should not call validateCSRF()');
    }
    
    public function testDeletePhpDoesNotCallValidateCSRF()
    {
        $file = __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/delete.php';
        $content = file_get_contents($file);
        
        $this->assertStringNotContainsString('validateCSRF()', $content,
            'delete.php should not call validateCSRF()');
    }
    
    public function testReloadPhpDoesNotCallValidateCSRF()
    {
        $file = __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/reload.php';
        $content = file_get_contents($file);
        
        $this->assertStringNotContainsString('validateCSRF()', $content,
            'reload.php should not call validateCSRF()');
    }
    
    public function testEndpointsHaveCorrectCSRFComments()
    {
        $endpoints = ['add.php', 'update.php', 'delete.php', 'reload.php'];
        
        foreach ($endpoints as $endpoint) {
            $file = __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/' . $endpoint;
            $content = file_get_contents($file);
            
            $this->assertStringContainsString('CSRF validation is handled globally', $content,
                "$endpoint should have comment explaining Unraid handles CSRF");
                
            $this->assertStringContainsString('local_prepend.php', $content,
                "$endpoint should reference local_prepend.php where CSRF is validated");
        }
    }
    
    public function testNoManualCSRFValidationInAnyFile()
    {
        $pluginDir = __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares';
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pluginDir)
        );
        
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                
                $this->assertStringNotContainsString('validateCSRF()', $content,
                    $file->getFilename() . ' should not call validateCSRF()');
            }
        }
    }
}
