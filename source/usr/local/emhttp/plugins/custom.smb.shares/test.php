<?php

declare(strict_types=1);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'status') {
        exec('/etc/rc.d/rc.samba status 2>&1', $output, $return);
        echo implode("\n", $output);
        exit;
    }

    if ($action === 'reload') {
        exec('/etc/rc.d/rc.samba reload 2>&1', $output, $return);
        if ($return === 0) {
            echo "Samba reloaded successfully";
        } else {
            http_response_code(500);
            echo "Failed to reload Samba: " . implode("\n", $output);
        }
        exit;
    }

    if ($action === 'getShare') {
        require_once '/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
        $index = intval($_GET['index'] ?? -1);
        $shares = loadShares();
        if (isset($shares[$index])) {
            header('Content-Type: application/json');
            echo json_encode($shares[$index]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Share not found']);
        }
        exit;
    }
}

require_once '/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
$shares = loadShares();
?>
<!DOCTYPE html>
<html>
<head><title>Test Page</title></head>
<body>
<h1>Custom SMB Shares Test</h1>
<pre><?php print_r($shares); ?></pre>
</body>
</html>
