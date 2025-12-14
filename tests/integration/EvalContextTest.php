<?php

declare(strict_types=1);

/**
 * Tests for PHP behavior in eval() context
 * 
 * Unraid processes .page files using eval('?>'.parse_file($file))
 * This means __DIR__ resolves to the current working directory, not the file's directory.
 * 
 * This test verifies that our code uses $docroot instead of __DIR__ for includes.
 */
class EvalContextTest extends PHPUnit\Framework\TestCase
{
    private string $tempDir;
    
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/eval-context-test-' . uniqid();
        // Resolve symlinks (macOS /var -> /private/var)
        $this->tempDir = realpath(sys_get_temp_dir()) . '/eval-context-test-' . uniqid();
        mkdir($this->tempDir . '/subdir', 0755, true);
    }
    
    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            exec('rm -rf ' . escapeshellarg($this->tempDir));
        }
    }
    
    /**
     * Test that __DIR__ in eval context returns wrong directory
     * 
     * This demonstrates WHY we can't use __DIR__ in files processed via eval()
     */
    public function testDirInEvalContextReturnsWrongDirectory(): void
    {
        // Create a file that uses __DIR__
        $fileContent = '<?php return __DIR__;';
        $filePath = $this->tempDir . '/subdir/test.php';
        file_put_contents($filePath, $fileContent);
        
        // When included directly, __DIR__ returns the file's directory
        $directResult = include $filePath;
        // Resolve symlinks for comparison
        $expectedDir = realpath($this->tempDir . '/subdir');
        $this->assertEquals($expectedDir, $directResult);
        
        // When eval'd, __DIR__ returns something OTHER than the file's directory
        // (typically cwd or the calling file's directory)
        $content = file_get_contents($filePath);
        $content = str_replace('<?php', '', $content);
        $evalResult = eval($content);
        
        // This is the key insight: eval'd __DIR__ is NOT the file's directory
        // This is why Unraid uses $docroot instead of __DIR__
        $this->assertNotEquals(
            $expectedDir, 
            $evalResult,
            "__DIR__ in eval context should NOT return the original file's directory - " .
            "this is why we use \$docroot instead"
        );
    }
    
    /**
     * Test that $docroot pattern works correctly in eval context
     * 
     * This is the pattern Unraid uses and what our code should use
     */
    public function testDocrootPatternWorksInEvalContext(): void
    {
        // Create a helper file to include
        $helperContent = '<?php function helper_function() { return "helper loaded"; }';
        $helperPath = $this->tempDir . '/subdir/helper.php';
        file_put_contents($helperPath, $helperContent);
        
        // Create a file that uses $docroot pattern (like Unraid does)
        $mainContent = '<?php
$docroot = "' . $this->tempDir . '";
require_once "$docroot/subdir/helper.php";
return helper_function();
';
        $mainPath = $this->tempDir . '/main.php';
        file_put_contents($mainPath, $mainContent);
        
        // Read and eval the content (simulating Unraid's parse_file + eval)
        $content = file_get_contents($mainPath);
        $content = str_replace('<?php', '', $content);
        $result = eval($content);
        
        $this->assertEquals('helper loaded', $result);
    }
    
    /**
     * Test that __DIR__ pattern fails in eval context for includes
     * 
     * This demonstrates the bug we fixed in ShareForm.php
     */
    public function testDirPatternFailsInEvalContextForIncludes(): void
    {
        // Create a helper file
        $helperContent = '<?php function dir_helper() { return "dir helper loaded"; }';
        $helperPath = $this->tempDir . '/subdir/dir_helper.php';
        file_put_contents($helperPath, $helperContent);
        
        // Create a file that uses __DIR__ pattern (the bug we fixed)
        $mainContent = '<?php
require_once __DIR__ . "/dir_helper.php";
return dir_helper();
';
        $mainPath = $this->tempDir . '/subdir/main_with_dir.php';
        file_put_contents($mainPath, $mainContent);
        
        // When included directly, it works
        $directResult = include $mainPath;
        $this->assertEquals('dir helper loaded', $directResult);
        
        // When eval'd from a different directory, it fails
        // because __DIR__ resolves to cwd, not the file's directory
        $content = file_get_contents($mainPath);
        $content = str_replace('<?php', '', $content);
        
        // Change to a different directory to simulate Unraid's behavior
        $originalCwd = getcwd();
        chdir($this->tempDir);
        
        try {
            // This should fail because __DIR__ will be $this->tempDir, not $this->tempDir/subdir
            // and there's no dir_helper.php in $this->tempDir
            // PHP 8 throws Error instead of Warning for failed require
            $this->expectException(\Error::class);
            eval($content);
        } finally {
            chdir($originalCwd);
        }
    }
    
    /**
     * Verify ShareForm.php uses $docroot pattern, not __DIR__
     */
    public function testShareFormUsesDocrootNotDir(): void
    {
        $shareFormPath = __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/include/ShareForm.php';
        $content = file_get_contents($shareFormPath);
        
        // Should NOT contain __DIR__ for includes
        $this->assertStringNotContainsString(
            "require_once __DIR__",
            $content,
            "ShareForm.php should not use __DIR__ for includes (breaks in eval context)"
        );
        
        // Should use $docroot pattern
        $this->assertStringContainsString(
            '$docroot',
            $content,
            "ShareForm.php should use \$docroot for includes (works in eval context)"
        );
    }
}
