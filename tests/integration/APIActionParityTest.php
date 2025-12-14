<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Tests that JavaScript API calls match PHP API action handlers.
 * This catches mismatches like JS calling 'export' when PHP expects 'exportConfig'.
 */
class APIActionParityTest extends TestCase
{
    private static string $pluginDir;
    
    public static function setUpBeforeClass(): void
    {
        self::$pluginDir = __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares';
    }
    
    /**
     * Extract all action handlers from api.php
     */
    private function getApiActions(): array
    {
        $apiFile = self::$pluginDir . '/api.php';
        $content = file_get_contents($apiFile);
        
        // Match patterns like: if ($action === 'actionName')
        preg_match_all('/\$action\s*===\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
        
        return array_unique($matches[1]);
    }
    
    /**
     * Extract all API calls from JavaScript in .page files
     */
    private function getJsApiCalls(): array
    {
        $calls = [];
        
        // Check all .page files
        $pageFiles = glob(self::$pluginDir . '/*.page');
        foreach ($pageFiles as $file) {
            $content = file_get_contents($file);
            
            // Match patterns like: api.php?action=actionName
            preg_match_all('/api\.php\?action=([a-zA-Z]+)/', $content, $matches);
            foreach ($matches[1] as $action) {
                $calls[$action] = basename($file);
            }
        }
        
        return $calls;
    }
    
    /**
     * Test that all JavaScript API calls have matching PHP handlers
     */
    public function testAllJsApiCallsHaveMatchingPhpHandlers(): void
    {
        $apiActions = $this->getApiActions();
        $jsCalls = $this->getJsApiCalls();
        
        $mismatches = [];
        foreach ($jsCalls as $action => $sourceFile) {
            if (!in_array($action, $apiActions)) {
                $mismatches[] = "JS calls 'action=$action' in $sourceFile but api.php has no handler for it";
            }
        }
        
        $this->assertEmpty(
            $mismatches,
            "API action mismatches found:\n" . implode("\n", $mismatches) . 
            "\n\nAvailable API actions: " . implode(', ', $apiActions)
        );
    }
    
    /**
     * Test export action specifically
     */
    public function testExportConfigActionExists(): void
    {
        $apiActions = $this->getApiActions();
        $jsCalls = $this->getJsApiCalls();
        
        // Verify exportConfig is a valid API action
        $this->assertContains('exportConfig', $apiActions, "api.php should have 'exportConfig' handler");
        
        // If JS calls exportConfig, verify it matches
        if (isset($jsCalls['exportConfig'])) {
            $this->assertContains('exportConfig', $apiActions);
        }
    }
    
    /**
     * Test import action specifically
     */
    public function testImportConfigActionExists(): void
    {
        $apiActions = $this->getApiActions();
        
        // Verify importConfig is a valid API action
        $this->assertContains('importConfig', $apiActions, "api.php should have 'importConfig' handler");
    }
    
    /**
     * Test that exportConfig returns the expected response format for JavaScript
     */
    public function testExportConfigResponseFormat(): void
    {
        $apiFile = self::$pluginDir . '/api.php';
        $content = file_get_contents($apiFile);
        
        // Find the exportConfig handler
        $this->assertStringContainsString(
            "action === 'exportConfig'",
            $content,
            "api.php should have exportConfig handler"
        );
        
        // Verify it returns success and config keys (what JS expects)
        // The JS expects: response.success and response.config
        $this->assertMatchesRegularExpression(
            "/exportConfig.*json_encode.*'success'.*'config'/s",
            $content,
            "exportConfig should return JSON with 'success' and 'config' keys"
        );
    }
}
