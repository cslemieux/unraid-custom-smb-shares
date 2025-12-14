<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests that all .page files include the JS files needed for functions they call.
 * 
 * This prevents issues like Edit.page calling showModalTab() without including main.js.
 */
class JsDependencyTest extends TestCase
{
    private string $pluginDir;
    private string $jsDir;

    protected function setUp(): void
    {
        $this->pluginDir = dirname(__DIR__, 2) . '/source/usr/local/emhttp/plugins/custom.smb.shares';
        $this->jsDir = $this->pluginDir . '/js';
    }

    /**
     * Map of function names to the JS files that define them.
     * Built by scanning JS files for function definitions.
     */
    private function buildFunctionToFileMap(): array
    {
        $map = [];
        $jsFiles = glob($this->jsDir . '/*.js');

        foreach ($jsFiles as $jsFile) {
            $content = file_get_contents($jsFile);
            $basename = basename($jsFile);

            // Match: window.funcName = function, function funcName(, var funcName = function
            preg_match_all('/(?:window\.(\w+)\s*=\s*function|function\s+(\w+)\s*\(|var\s+(\w+)\s*=\s*function)/', $content, $matches);

            foreach ($matches[1] as $func) {
                if ($func) {
                    $map[$func][] = $basename;
                }
            }
            foreach ($matches[2] as $func) {
                if ($func) {
                    $map[$func][] = $basename;
                }
            }
            foreach ($matches[3] as $func) {
                if ($func) {
                    $map[$func][] = $basename;
                }
            }
        }

        // Deduplicate
        foreach ($map as $func => $files) {
            $map[$func] = array_unique($files);
        }

        return $map;
    }

    /**
     * Extract JS file includes from a page file.
     */
    private function getIncludedJsFiles(string $pageContent): array
    {
        preg_match_all('/<script[^>]+src="[^"]*\/js\/([^"]+)"/', $pageContent, $matches);
        return $matches[1];
    }

    /**
     * Extract function calls from onclick handlers and inline scripts.
     */
    private function getCalledFunctions(string $pageContent): array
    {
        $functions = [];

        // Match onclick="functionName(" patterns
        preg_match_all('/onclick="(\w+)\s*\(/', $pageContent, $matches);
        $functions = array_merge($functions, $matches[1]);

        // Match function calls in inline scripts: functionName(
        preg_match_all('/<script[^>]*>.*?<\/script>/s', $pageContent, $scriptBlocks);
        foreach ($scriptBlocks[0] as $block) {
            // Skip external script includes
            if (strpos($block, 'src=') !== false) {
                continue;
            }
            preg_match_all('/\b(\w+)\s*\(/', $block, $inlineMatches);
            $functions = array_merge($functions, $inlineMatches[1]);
        }

        // Filter out common non-function patterns
        $exclude = ['if', 'for', 'while', 'switch', 'function', 'return', 'new', 'typeof', 'catch'];
        $functions = array_filter($functions, fn($f) => !in_array($f, $exclude));

        return array_unique($functions);
    }

    /**
     * Extract functions defined inline in the page (in <script> blocks).
     */
    private function getInlineDefinedFunctions(string $pageContent): array
    {
        $functions = [];

        preg_match_all('/<script[^>]*>.*?<\/script>/s', $pageContent, $scriptBlocks);
        foreach ($scriptBlocks[0] as $block) {
            // Skip external script includes
            if (strpos($block, 'src=') !== false) {
                continue;
            }
            // Match: window.funcName = function, function funcName(, var funcName = function
            preg_match_all('/(?:window\.(\w+)\s*=\s*function|function\s+(\w+)\s*\(|var\s+(\w+)\s*=\s*function)/', $block, $matches);
            foreach ($matches[1] as $func) {
                if ($func) $functions[] = $func;
            }
            foreach ($matches[2] as $func) {
                if ($func) $functions[] = $func;
            }
            foreach ($matches[3] as $func) {
                if ($func) $functions[] = $func;
            }
        }

        return array_unique($functions);
    }

    /**
     * @dataProvider pageFilesProvider
     */
    public function testPageIncludesRequiredJsFiles(string $pageFile): void
    {
        $content = file_get_contents($pageFile);
        $pageName = basename($pageFile);

        $functionMap = $this->buildFunctionToFileMap();
        $includedJs = $this->getIncludedJsFiles($content);
        $calledFunctions = $this->getCalledFunctions($content);
        $inlineFunctions = $this->getInlineDefinedFunctions($content);

        $missingIncludes = [];

        foreach ($calledFunctions as $func) {
            // Skip if function is defined inline in the page
            if (in_array($func, $inlineFunctions)) {
                continue;
            }

            // Skip if function is not in our JS files (could be jQuery, browser built-in, etc.)
            if (!isset($functionMap[$func])) {
                continue;
            }

            $requiredFiles = $functionMap[$func];

            // Check if ANY of the files that define this function are included
            $hasRequiredFile = false;
            foreach ($requiredFiles as $file) {
                if (in_array($file, $includedJs)) {
                    $hasRequiredFile = true;
                    break;
                }
            }

            if (!$hasRequiredFile) {
                $missingIncludes[$func] = implode(' or ', $requiredFiles);
            }
        }

        $this->assertEmpty(
            $missingIncludes,
            sprintf(
                "%s calls functions without including their JS files:\n%s",
                $pageName,
                implode("\n", array_map(
                    fn($func, $file) => "  - $func() requires $file",
                    array_keys($missingIncludes),
                    array_values($missingIncludes)
                ))
            )
        );
    }

    public static function pageFilesProvider(): array
    {
        $pluginDir = dirname(__DIR__, 2) . '/source/usr/local/emhttp/plugins/custom.smb.shares';
        $pages = glob($pluginDir . '/*.page');

        return array_map(fn($p) => [$p], $pages);
    }

    /**
     * Test that all JS files can be parsed (no syntax errors detectable by regex).
     */
    public function testJsFilesHaveValidStructure(): void
    {
        $jsFiles = glob($this->jsDir . '/*.js');

        foreach ($jsFiles as $jsFile) {
            $content = file_get_contents($jsFile);
            $basename = basename($jsFile);

            // Check for balanced braces (simple heuristic)
            $openBraces = substr_count($content, '{');
            $closeBraces = substr_count($content, '}');

            $this->assertEquals(
                $openBraces,
                $closeBraces,
                "$basename has unbalanced braces: $openBraces open, $closeBraces close"
            );

            // Check for balanced parentheses
            $openParens = substr_count($content, '(');
            $closeParens = substr_count($content, ')');

            $this->assertEquals(
                $openParens,
                $closeParens,
                "$basename has unbalanced parentheses: $openParens open, $closeParens close"
            );
        }
    }

    /**
     * Test that functions defined in JS files are actually used somewhere.
     * Helps identify dead code.
     */
    public function testDefinedFunctionsAreUsed(): void
    {
        $functionMap = $this->buildFunctionToFileMap();
        $allPageContent = '';

        // Collect all page content
        $pages = glob($this->pluginDir . '/*.page');
        foreach ($pages as $page) {
            $allPageContent .= file_get_contents($page);
        }

        // Collect all JS content
        $jsFiles = glob($this->jsDir . '/*.js');
        $allJsContent = '';
        foreach ($jsFiles as $jsFile) {
            $allJsContent .= file_get_contents($jsFile);
        }

        $unusedFunctions = [];

        foreach ($functionMap as $func => $file) {
            // Check if function is called in pages or other JS files
            // Look for funcName( pattern but not the definition
            $pattern = '/\b' . preg_quote($func, '/') . '\s*\(/';

            $pageMatches = preg_match_all($pattern, $allPageContent);
            $jsMatches = preg_match_all($pattern, $allJsContent);

            // Subtract 1 from JS matches for the definition itself
            $totalUsages = $pageMatches + max(0, $jsMatches - 1);

            if ($totalUsages === 0) {
                $unusedFunctions[] = "$func (defined in $file)";
            }
        }

        // This is a warning, not a failure - some functions may be called dynamically
        if (!empty($unusedFunctions)) {
            $this->markTestIncomplete(
                "Potentially unused functions (may be called dynamically):\n  - " .
                implode("\n  - ", $unusedFunctions)
            );
        }

        $this->assertTrue(true); // Pass if no unused functions
    }

    /**
     * Test that modal-related JS code doesn't use bare ID selectors.
     * 
     * This prevents the duplicate ID bug class where $('#id') finds the wrong
     * element when templates are cloned into dialogs.
     * 
     * Allowed patterns:
     * - $('.ui-dialog #id') - scoped to dialog
     * - $form.find('#id') - scoped to form
     * - $('#dialogShare') - dialog container itself
     * - $('#templateShareForm') - template container itself
     * 
     * Forbidden patterns:
     * - $('#shareName') - bare ID selector for form fields
     * - $('#basic-tab') - bare ID selector for tabs
     */
    public function testNoBarIdSelectorsInModalCode(): void
    {
        $jsFiles = glob($this->jsDir . '/*.js');
        
        // IDs that are OK to use globally (containers, not cloned elements)
        $allowedBareIds = [
            'dialogShare',
            'dialogUsers', 
            'dialogAddConfig',
            'templateShareForm',
            'templatePopupUsers',
            'templatePopupConfig',
            'samba-status',
            'reloadBtn',
            'fileTree', // dynamically created with unique suffix
        ];
        
        // Files that are exempt from this check (non-modal code)
        $exemptFiles = [
            'feedback.js', // Uses formId parameter, not modal-specific
        ];
        
        $violations = [];
        
        foreach ($jsFiles as $jsFile) {
            $basename = basename($jsFile);
            
            if (in_array($basename, $exemptFiles)) {
                continue;
            }
            
            $content = file_get_contents($jsFile);
            $lines = explode("\n", $content);
            
            foreach ($lines as $lineNum => $line) {
                // Skip comments
                if (preg_match('/^\s*\/\//', $line) || preg_match('/^\s*\*/', $line)) {
                    continue;
                }
                
                // Find bare ID selectors: $('#id') not preceded by .find( or scoped
                // Pattern: $(' or $(" followed by # and an ID, not inside .find()
                if (preg_match_all('/\$\([\'"]#([a-zA-Z][a-zA-Z0-9_-]*)[\'"]/', $line, $matches)) {
                    foreach ($matches[1] as $id) {
                        // Skip allowed IDs
                        if (in_array($id, $allowedBareIds)) {
                            continue;
                        }
                        
                        // Skip if it's inside .find() - check if preceded by .find
                        if (preg_match('/\.find\s*\(\s*[\'"]#' . preg_quote($id, '/') . '/', $line)) {
                            continue;
                        }
                        
                        // Skip if scoped to .ui-dialog
                        if (preg_match('/\.ui-dialog[^\']*#' . preg_quote($id, '/') . '/', $line)) {
                            continue;
                        }
                        
                        // Skip dynamically generated IDs (contain variable concatenation)
                        if (preg_match('/\$\([\'"]#[\'"]?\s*\+/', $line)) {
                            continue;
                        }
                        
                        $violations[] = sprintf(
                            "%s:%d - Bare ID selector \$('#%s') - use \$form.find('#%s') or data attributes instead",
                            $basename,
                            $lineNum + 1,
                            $id,
                            $id
                        );
                    }
                }
            }
        }
        
        $this->assertEmpty(
            $violations,
            "Found bare ID selectors in modal code (can cause duplicate ID bugs):\n  " .
            implode("\n  ", $violations)
        );
    }
}
