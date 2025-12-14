<?php
/**
 * Validate HTML/JavaScript output at each stage
 */

function validateHTML($html, $stage) {
    echo "\n=== Validating $stage ===\n";
    
    // Check basic structure
    $hasDoctype = stripos($html, '<!DOCTYPE') !== false;
    $hasHtml = stripos($html, '<html') !== false;
    $hasHead = stripos($html, '<head') !== false;
    $hasBody = stripos($html, '<body') !== false;
    
    echo "DOCTYPE: " . ($hasDoctype ? '✓' : '✗') . "\n";
    echo "HTML tag: " . ($hasHtml ? '✓' : '✗') . "\n";
    echo "HEAD tag: " . ($hasHead ? '✓' : '✗') . "\n";
    echo "BODY tag: " . ($hasBody ? '✓' : '✗') . "\n";
    
    // Count tags
    $scriptCount = substr_count($html, '<script>') + substr_count($html, '<script ');
    $scriptCloseCount = substr_count($html, '</script>');
    
    echo "Script tags: $scriptCount open, $scriptCloseCount close " . 
         ($scriptCount === $scriptCloseCount ? '✓' : '✗ MISMATCH') . "\n";
    
    // Check for common issues
    $hasUnclosedTags = preg_match('/<(div|span|table|tr|td)[^>]*>(?!.*<\/\1>)/s', $html);
    echo "Unclosed tags: " . ($hasUnclosedTags ? '✗ FOUND' : '✓') . "\n";
    
    return [
        'valid' => $hasDoctype && $hasHtml && $hasHead && $hasBody && ($scriptCount === $scriptCloseCount),
        'scriptCount' => $scriptCount,
        'length' => strlen($html)
    ];
}

function validateJavaScript($html, $stage) {
    echo "\n=== Validating JavaScript in $stage ===\n";
    
    // Extract all script blocks
    preg_match_all('/<script[^>]*>(.*?)<\/script>/s', $html, $matches);
    
    echo "Script blocks found: " . count($matches[1]) . "\n";
    
    foreach ($matches[1] as $i => $script) {
        echo "\n--- Script block " . ($i + 1) . " ---\n";
        echo "Length: " . strlen($script) . " chars\n";
        
        // Write to temp file for syntax check
        $tempFile = sys_get_temp_dir() . '/validate-js-' . $i . '.js';
        file_put_contents($tempFile, $script);
        
        // Use node to check syntax (if available)
        exec("node --check " . escapeshellarg($tempFile) . " 2>&1", $output, $ret);
        if ($ret === 0) {
            echo "Syntax: ✓ Valid\n";
        } else {
            echo "Syntax: ✗ INVALID\n";
            echo "Error: " . implode("\n", $output) . "\n";
            
            // Show problematic lines
            $lines = explode("\n", $script);
            foreach ($output as $error) {
                if (preg_match('/line (\d+)/', $error, $m)) {
                    $lineNum = (int)$m[1];
                    if (isset($lines[$lineNum - 1])) {
                        echo "Line $lineNum: " . trim($lines[$lineNum - 1]) . "\n";
                    }
                }
            }
        }
        
        unlink($tempFile);
        
        // Check for key functions
        $hasAddSharePopup = strpos($script, 'addSharePopup') !== false;
        $hasEditSharePopup = strpos($script, 'editSharePopup') !== false;
        $hasDeleteShare = strpos($script, 'deleteShare') !== false;
        
        echo "Contains addSharePopup: " . ($hasAddSharePopup ? '✓' : '✗') . "\n";
        echo "Contains editSharePopup: " . ($hasEditSharePopup ? '✓' : '✗') . "\n";
        echo "Contains deleteShare: " . ($hasDeleteShare ? '✓' : '✗') . "\n";
    }
}

// Main validation
if ($argc < 2) {
    echo "Usage: php validate-output.php <html-file>\n";
    exit(1);
}

$htmlFile = $argv[1];
if (!file_exists($htmlFile)) {
    echo "File not found: $htmlFile\n";
    exit(1);
}

$html = file_get_contents($htmlFile);

$htmlResult = validateHTML($html, basename($htmlFile));
validateJavaScript($html, basename($htmlFile));

echo "\n=== Summary ===\n";
echo "Overall valid: " . ($htmlResult['valid'] ? '✓' : '✗') . "\n";
echo "Total length: " . $htmlResult['length'] . " bytes\n";
