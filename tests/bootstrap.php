<?php
// Define PHPUNIT_TEST before any lib.php require - enables test mode path validation
define('PHPUNIT_TEST', true);

require_once __DIR__ . '/mocks/UnraidMock.php';

/**
 * Mock Unraid translation function
 * In production, this is provided by Unraid's WebGUI framework
 * For testing, we simply return the input string
 */
if (!function_exists('_')) {
    function _(string $text): string
    {
        return $text;
    }
}

/**
 * Mock Unraid mk_option helper
 * Generates HTML <option> elements for select dropdowns
 * In production, this is provided by Unraid's WebGUI framework
 */
if (!function_exists('mk_option')) {
    function mk_option($selected, $value, $text, $extra = ''): string
    {
        $sel = ($selected == $value) ? ' selected' : '';
        $extraAttr = $extra ? " $extra" : '';
        return "<option value=\"$value\"$sel$extraAttr>$text</option>";
    }
}

// Mock Unraid paths
define('MOCK_ROOT', sys_get_temp_dir() . '/unraid-mock-' . getmypid());
define('MOCK_BOOT', MOCK_ROOT . '/boot');
define('MOCK_CONFIG', MOCK_BOOT . '/config');
define('MOCK_PLUGINS', MOCK_CONFIG . '/plugins/custom.smb.shares');
define('MOCK_EMHTTP', MOCK_ROOT . '/usr/local/emhttp/plugins/custom.smb.shares');

// Setup mock filesystem
function setupMockFilesystem() {
    @mkdir(MOCK_PLUGINS, 0755, true);
    @mkdir(MOCK_EMHTTP, 0755, true);
    @mkdir(MOCK_CONFIG . '/shares', 0755, true);
    file_put_contents(MOCK_CONFIG . '/smb-extra.conf', '');
}

// Cleanup mock filesystem
function cleanupMockFilesystem() {
    exec('rm -rf ' . escapeshellarg(MOCK_ROOT));
}

register_shutdown_function('cleanupMockFilesystem');
setupMockFilesystem();
