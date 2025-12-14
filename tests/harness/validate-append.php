<?php
/**
 * Validation function for HTML/JavaScript/PHP output
 * Logs warnings to side channel file (not HTTP response)
 */

if (!function_exists('validateOutput')) {
    function validateOutput($html) {
        $warnings = [];
        
        // HTML structure validation
        if (!preg_match('/<html/i', $html)) {
            $warnings[] = "HTML: Missing <html> tag";
        }
        if (!preg_match('/<body/i', $html)) {
            $warnings[] = "HTML: Missing <body> tag";
        }
        
        // Check for balanced script tags
        $openTags = preg_match_all('/<script[^>]*>/i', $html);
        $closeTags = preg_match_all('/<\/script>/i', $html);
        if ($openTags !== $closeTags) {
            $warnings[] = "HTML: Unbalanced script tags ($openTags open, $closeTags close)";
        }
        
        // JavaScript syntax validation
        preg_match_all('/<script[^>]*>(.*?)<\/script>/is', $html, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[1] as $i => $match) {
            $jsContent = $match[0];
            $offset = $match[1];
            
            if (trim($jsContent) === '') continue;
            
            $tempFile = sys_get_temp_dir() . '/validate-js-' . uniqid() . '.js';
            file_put_contents($tempFile, $jsContent);
            
            exec("node --check " . escapeshellarg($tempFile) . " 2>&1", $output, $ret);
            
            if ($ret !== 0) {
                // Extract line and column from Node.js error
                $line = 0;
                $col = 0;
                $errorMsg = '';
                
                foreach ($output as $outputLine) {
                    // Parse line like: "/path/file.js:3"
                    if (preg_match('/:(\d+)$/', $outputLine, $lineMatch)) {
                        $line = (int)$lineMatch[1];
                    }
                    // Extract error message
                    if (strpos($outputLine, 'SyntaxError:') !== false) {
                        $errorMsg = trim(str_replace('SyntaxError:', '', $outputLine));
                    }
                }
                
                if (empty($errorMsg)) $errorMsg = 'syntax error';
                
                $location = $line > 0 ? " (line $line)" : "";
                $warnings[] = "JavaScript: Script block " . ($i + 1) . "$location - $errorMsg";
            }
            
            @unlink($tempFile);
        }
        
        // PHP syntax validation
        preg_match_all('/<\?php(.*?)\?>/is', $html, $phpMatches, PREG_OFFSET_CAPTURE);
        foreach ($phpMatches[0] as $i => $match) {
            $phpBlock = $match[0];
            $offset = $match[1];
            
            $tempFile = sys_get_temp_dir() . '/validate-php-' . uniqid() . '.php';
            file_put_contents($tempFile, $phpBlock);
            
            exec("php -l " . escapeshellarg($tempFile) . " 2>&1", $output, $ret);
            
            if ($ret !== 0) {
                // Extract line number from PHP error
                $line = 0;
                $errorMsg = '';
                
                foreach ($output as $outputLine) {
                    // Parse "on line X"
                    if (preg_match('/on line (\d+)/', $outputLine, $lineMatch)) {
                        $line = (int)$lineMatch[1];
                    }
                    // Extract error message
                    if (preg_match('/(Parse error:|syntax error[^,]*)/i', $outputLine, $errMatch)) {
                        $errorMsg = trim(preg_replace('/ in \/.*$/', '', $errMatch[0]));
                        $errorMsg = preg_replace('/^Parse error:\s*/i', '', $errorMsg);
                    }
                }
                
                if (empty($errorMsg)) $errorMsg = 'syntax error';
                
                $location = $line > 0 ? " (line $line)" : "";
                $warnings[] = "PHP: Code block " . ($i + 1) . "$location - $errorMsg";
            }
            
            @unlink($tempFile);
        }
        
        // Log warnings to side channel file
        if (!empty($warnings)) {
            $logFile = sys_get_temp_dir() . '/validation-warnings.log';
            $timestamp = date('Y-m-d H:i:s');
            $logEntry = "[$timestamp] VALIDATION WARNINGS:\n";
            foreach ($warnings as $warning) {
                $logEntry .= "  - $warning\n";
            }
            $logEntry .= "\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND);
        }
    }
}
