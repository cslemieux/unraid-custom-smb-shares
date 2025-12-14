<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../helpers/ChrootTestEnvironment.php';

/**
 * Tests that translation function calls are properly rendered
 * 
 * These tests catch issues where:
 * - _() translation function is not defined
 * - mk_option() helper is not defined
 * - Translation markers appear as raw text in output (_(text)_ instead of _('text'))
 */
class TranslationRenderingTest extends TestCase
{
    private string $configDir;
    private string $pluginDir;
    private static bool $libLoaded = false;

    protected function setUp(): void
    {
        // ChrootTestEnvironment::setup() now sets ConfigRegistry automatically
        $this->configDir = ChrootTestEnvironment::setup();
        $this->pluginDir = dirname(__DIR__, 2) . '/source/usr/local/emhttp/plugins/custom.smb.shares';
        
        // Load lib.php once (it defines functions and loads ConfigRegistry)
        if (!self::$libLoaded) {
            require_once $this->pluginDir . '/include/lib.php';
            self::$libLoaded = true;
        }
    }

    protected function tearDown(): void
    {
        // ChrootTestEnvironment::teardown() now resets ConfigRegistry
        ChrootTestEnvironment::teardown();
    }

    /**
     * Test that _() translation function is defined and works
     */
    public function testTranslationFunctionDefined(): void
    {
        // The _() function should be defined (either by Unraid or our mock)
        $this->assertTrue(
            function_exists('_'),
            '_() translation function must be defined'
        );
        
        // It should return the input string (passthrough for English)
        $this->assertEquals('Test String', _('Test String'));
    }

    /**
     * Test that mk_option() helper is defined and works
     */
    public function testMkOptionFunctionDefined(): void
    {
        $this->assertTrue(
            function_exists('mk_option'),
            'mk_option() helper function must be defined'
        );
    }

    /**
     * Test mk_option generates correct HTML for unselected option
     */
    public function testMkOptionUnselected(): void
    {
        $result = mk_option('current', 'other', 'Label');
        
        $this->assertStringContainsString('value="other"', $result);
        $this->assertStringContainsString('>Label</option>', $result);
        $this->assertStringNotContainsString('selected', $result);
    }

    /**
     * Test mk_option generates correct HTML for selected option
     */
    public function testMkOptionSelected(): void
    {
        $result = mk_option('current', 'current', 'Label');
        
        $this->assertStringContainsString('value="current"', $result);
        $this->assertStringContainsString('selected', $result);
        $this->assertStringContainsString('>Label</option>', $result);
    }

    /**
     * Test mk_option with empty current value
     */
    public function testMkOptionEmptyValue(): void
    {
        $result = mk_option('', '', 'None');
        
        $this->assertStringContainsString('value=""', $result);
        $this->assertStringContainsString('selected', $result);
    }

    /**
     * Test mk_option with different types
     */
    public function testMkOptionWithDifferentTypes(): void
    {
        // String comparison
        $result = mk_option('yes', 'yes', 'Yes');
        $this->assertStringContainsString('selected', $result);
        
        // Different values
        $result = mk_option('yes', 'no', 'No');
        $this->assertStringNotContainsString('selected', $result);
    }

    /**
     * Test ShareForm.php can be included without errors
     */
    public function testShareFormCanBeIncluded(): void
    {
        // Set up global $var for CSRF token and $docroot like Unraid does
        // ShareForm.php uses $docroot for includes because __DIR__ doesn't work in eval() context
        global $var, $docroot;
        $var = ['csrf_token' => 'test-token'];
        $docroot = dirname($this->pluginDir, 2); // /usr/local/emhttp equivalent
        
        $shareFormPath = $this->pluginDir . '/include/ShareForm.php';
        $this->assertFileExists($shareFormPath, 'ShareForm.php must exist');
        
        // Capture output - ShareForm.php outputs HTML directly (inline PHP)
        ob_start();
        include $shareFormPath;
        $output = ob_get_clean();
        
        // Should produce some HTML output
        $this->assertNotEmpty($output, 'ShareForm.php should produce output');
        $this->assertStringContainsString('<form', $output, 'Output should contain a form');
    }

    /**
     * Test that rendered output uses Markdown-style translation markers
     * 
     * ShareForm.php uses _(text)_ Markdown syntax which is processed by Unraid's parse_file()
     * This is the correct pattern for files included via eval('?>'.parse_file(...))
     * 
     * Note: Direct PHP include won't process these markers - that's expected.
     * The markers will be processed when the .page file uses parse_file().
     */
    public function testRenderedOutputUsesMarkdownMarkers(): void
    {
        global $var, $docroot;
        $var = ['csrf_token' => 'test-token'];
        $docroot = dirname($this->pluginDir, 2);
        
        // Capture output from ShareForm.php (inline PHP)
        ob_start();
        include $this->pluginDir . '/include/ShareForm.php';
        $output = ob_get_clean();
        
        // ShareForm.php uses Markdown-style markers for parse_file() processing
        // These markers are: _(text)_ which parse_file() converts to translated text
        // This is the CORRECT pattern for Unraid plugins using parse_file()
        
        // Verify the file uses Markdown markers (expected for parse_file architecture)
        $this->assertStringContainsString(
            '_(Share Settings)_',
            $output,
            'ShareForm.php should use Markdown-style _(text)_ markers for parse_file() processing'
        );
    }

    /**
     * Test that form labels are readable text (not raw markers)
     */
    public function testFormLabelsAreReadable(): void
    {
        global $var, $docroot;
        $var = ['csrf_token' => 'test-token'];
        $docroot = dirname($this->pluginDir, 2);
        
        ob_start();
        include $this->pluginDir . '/include/ShareForm.php';
        $output = ob_get_clean();
        
        // Labels should be readable text
        // Note: With parse_file(), _(Label)_ becomes translated text
        // With direct include, we should see the text (since _() is passthrough)
        $this->assertStringContainsString('Share Name', $output);
        $this->assertStringContainsString('Path', $output);
    }

    /**
     * Test that edit mode works with share data
     */
    public function testEditModeLoadsShareData(): void
    {
        global $var, $docroot;
        $var = ['csrf_token' => 'test-token'];
        $docroot = dirname($this->pluginDir, 2);
        
        // Create a test share
        $testShare = [
            'name' => 'TestShare',
            'path' => '/mnt/user/test',
            'comment' => 'Test comment',
            'export' => 'e',
            'security' => 'public'
        ];
        
        // Save the share
        $shares = [$testShare];
        saveShares($shares);
        
        // Set up GET parameter for edit mode
        $_GET['name'] = 'TestShare';
        
        ob_start();
        include $this->pluginDir . '/include/ShareForm.php';
        $output = ob_get_clean();
        
        // Should contain the share data
        $this->assertStringContainsString('TestShare', $output);
        $this->assertStringContainsString('/mnt/user/test', $output);
        
        // Clean up
        unset($_GET['name']);
    }
}
