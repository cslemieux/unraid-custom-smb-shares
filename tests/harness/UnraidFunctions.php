<?php

declare(strict_types=1);

/**
 * Unraid WebGUI Function Emulation
 * 
 * These functions replicate Unraid's page rendering pipeline:
 * - parse_text(): Processes _(...)_ translation markers
 * - parse_file(): Reads file, processes translations, optionally applies Markdown
 * - Markdown(): Converts Markdown to HTML (using Parsedown library)
 * 
 * This allows our test harness to catch issues like:
 * - Using __DIR__ in files processed via eval('?>'.parse_file(...))
 * - Missing translation markers
 * - Markdown processing issues
 */

// Use Parsedown for Markdown processing (composer require erusev/parsedown)
// If not available, fall back to simple passthrough
if (!class_exists('Parsedown')) {
    $parsedownPath = dirname(__DIR__, 2) . '/vendor/erusev/parsedown/Parsedown.php';
    if (file_exists($parsedownPath)) {
        require_once $parsedownPath;
    }
}

/**
 * Translation function - returns text as-is in test environment
 * In production Unraid, this looks up translations from language files
 */
if (!function_exists('_')) {
    function _(string $text): string
    {
        return $text;
    }
}

/**
 * Process translation markers in text
 * Converts _(text)_ to translated text
 * Also handles help tags like :setting_help: and :setting_plug:
 * 
 * This replicates Unraid's parse_text() from Translations.php
 */
if (!function_exists('parse_text')) {
    function parse_text(string $text): string
    {
        // Process _(...)_ translation markers
        $text = preg_replace_callback(
            '/_\((.+?)\)_/m',
            function ($m) {
                return _($m[1]);
            },
            $text
        );
        
        // Process help tags (simplified - in production these load help content)
        // :setting_help: becomes PHP help comment
        // :setting_plug: becomes PHP conditional
        // :end becomes PHP endif
        // Note: Using concatenation to avoid parser issues with PHP tags in strings
        $phpOpen = '<' . '?php';
        $phpClose = '?' . '>';
        $text = preg_replace(
            ["/^:(.+_help):$/m", "/^:(.+_plug):$/m", "/^:end$/m"],
            ["$phpOpen /* help: $1 */ $phpClose", "$phpOpen /* plug: $1 */ if(true): $phpClose", "$phpOpen endif; $phpClose"],
            $text
        );
        
        return $text;
    }
}

/**
 * Process a file through Unraid's rendering pipeline
 * 
 * This replicates Unraid's parse_file() from Translations.php:
 * 1. Read file contents
 * 2. Process translation markers via parse_text()
 * 3. Optionally apply Markdown processing
 * 
 * @param string $file Path to file
 * @param bool $markdown Whether to apply Markdown processing (default: true)
 * @return string Processed content
 */
if (!function_exists('parse_file')) {
    function parse_file(string $file, bool $markdown = true): string
    {
        if (!file_exists($file)) {
            error_log("parse_file: File not found: $file");
            return '';
        }
        
        $content = file_get_contents($file);
        if ($content === false) {
            error_log("parse_file: Failed to read file: $file");
            return '';
        }
        
        // Process translation markers
        $content = parse_text($content);
        
        // Apply Markdown if requested
        if ($markdown) {
            $content = Markdown($content);
        }
        
        return $content;
    }
}

/**
 * Convert Markdown to HTML
 * 
 * Uses Parsedown library if available, otherwise returns content unchanged.
 * Unraid uses a custom Markdown implementation, but Parsedown is close enough
 * for testing purposes.
 * 
 * @param string $text Markdown text
 * @return string HTML output
 */
if (!function_exists('Markdown')) {
    function Markdown(string $text): string
    {
        // If Parsedown is available, use it
        if (class_exists('Parsedown')) {
            static $parsedown = null;
            if ($parsedown === null) {
                $parsedown = new Parsedown();
                $parsedown->setMarkupEscaped(false); // Allow HTML passthrough
                $parsedown->setBreaksEnabled(false);
            }
            return $parsedown->text($text);
        }
        
        // Fallback: return unchanged (not ideal but allows tests to run)
        error_log("Markdown: Parsedown not available, returning content unchanged");
        return $text;
    }
}

/**
 * Mock Unraid mk_option helper
 * Generates HTML <option> elements for select dropdowns
 */
if (!function_exists('mk_option')) {
    function mk_option($selected, $value, $text, $extra = ''): string
    {
        $sel = ($selected == $value) ? ' selected' : '';
        $extraAttr = $extra ? " $extra" : '';
        return "<option value=\"$value\"$sel$extraAttr>$text</option>";
    }
}

/**
 * Mock Unraid _var helper
 * Safe variable access with default fallback
 */
if (!function_exists('_var')) {
    function _var(&$name, $key = null, $default = '')
    {
        return is_null($key) ? ($name ?? $default) : ($name[$key] ?? $default);
    }
}
