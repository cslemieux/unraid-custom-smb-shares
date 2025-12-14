<?php
// Mock HTTP server for testing plugin
$port = 8080;
$docRoot = __DIR__ . '/../source/usr/local/emhttp/plugins/custom.smb.shares';

// Mock Unraid paths
define('MOCK_BOOT', sys_get_temp_dir() . '/unraid-mock-server');
define('MOCK_CONFIG', MOCK_BOOT . '/config');
define('MOCK_PLUGINS', MOCK_CONFIG . '/plugins/custom.smb.shares');

// Setup mock filesystem
if (!is_dir(MOCK_PLUGINS)) {
    mkdir(MOCK_PLUGINS, 0755, true);
}
if (!file_exists(MOCK_PLUGINS . '/shares.json')) {
    file_put_contents(MOCK_PLUGINS . '/shares.json', '[]');
}
if (!file_exists(MOCK_CONFIG . '/smb-extra.conf')) {
    file_put_contents(MOCK_CONFIG . '/smb-extra.conf', '');
}

// Override paths in lib.php
$libContent = file_get_contents($docRoot . '/include/lib.php');
$libContent = str_replace('/boot/config', MOCK_CONFIG, $libContent);
file_put_contents(sys_get_temp_dir() . '/lib-mock.php', $libContent);

echo "Mock Unraid environment created at: " . MOCK_BOOT . "\n";
echo "Starting server at http://localhost:$port\n";
echo "Press Ctrl+C to stop\n\n";

// Start built-in PHP server
chdir($docRoot);
passthru("php -S localhost:$port -t " . escapeshellarg($docRoot));
