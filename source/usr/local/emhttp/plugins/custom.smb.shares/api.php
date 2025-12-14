<?php

declare(strict_types=1);

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'getUsers') {
    $users = [];
    $lines = file('/etc/passwd');
    if ($lines !== false) {
        foreach ($lines as $line) {
            $parts = explode(':', $line);
            if (count($parts) >= 3 && $parts[2] >= 1000) { // Regular users
                $users[] = $parts[0];
            }
        }
    }
    // Add system users that might be useful
    array_unshift($users, 'nobody', 'root');
    echo json_encode(array_unique($users));
    exit;
}

if ($action === 'getGroups') {
    $groups = [];
    $lines = file('/etc/group');
    if ($lines !== false) {
        foreach ($lines as $line) {
            $parts = explode(':', $line);
            if (isset($parts[0])) {
                $groups[] = '@' . $parts[0];
            }
        }
    }
    echo json_encode($groups);
    exit;
}

if ($action === 'searchUsers') {
    $query = strtolower($_GET['query'] ?? '');
    if (strlen($query) < 2) {
        echo json_encode([]);
        exit;
    }

    $results = [];

    // Search users
    $lines = file('/etc/passwd');
    if ($lines !== false) {
        foreach ($lines as $line) {
            $parts = explode(':', $line);
            if (count($parts) >= 5) {
                $username = $parts[0];
                $uid = (int)$parts[2];
                $fullname = $parts[4];

                // Only Unraid users (UID >= 1000, not root)
                if ($uid >= 1000 && $username !== 'root') {
                    if (
                        strpos(strtolower($username), $query) !== false ||
                        strpos(strtolower($fullname), $query) !== false
                    ) {
                        $results[] = [
                            'name' => $username,
                            'type' => 'user',
                            'fullname' => $fullname
                        ];
                    }
                }
            }
        }
    }

    // Search groups
    $lines = file('/etc/group');
    if ($lines !== false) {
        foreach ($lines as $line) {
            $parts = explode(':', $line);
            if (count($parts) >= 4) {
                $groupname = $parts[0];
                $members = !empty($parts[3]) ? explode(',', trim($parts[3])) : [];

                if (strpos(strtolower($groupname), $query) !== false) {
                    $results[] = [
                        'name' => '@' . $groupname,
                        'type' => 'group',
                        'members' => count($members)
                    ];
                }
            }
        }
    }

    // Limit to 10 results
    echo json_encode(array_slice($results, 0, 10));
    exit;
}

if ($action === 'getShare') {
    require_once __DIR__ . '/include/lib.php';
    $index = intval($_GET['index'] ?? -1);
    $shares = loadShares();
    if (isset($shares[$index])) {
        echo json_encode($shares[$index]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Share not found']);
    }
    exit;
}

if ($action === 'exportConfig') {
    require_once '/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
    $shares = loadShares();
    echo json_encode($shares);
    exit;
}

if ($action === 'importConfig') {
    require_once '/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
    $input = file_get_contents('php://input');
    if ($input === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Failed to read input']);
        exit;
    }
    $shares = json_decode($input, true);

    if (!is_array($shares)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid configuration format']);
        exit;
    }

    // Validate each share
    foreach ($shares as $share) {
        $errors = validateShare($share);
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid share: ' . implode(', ', $errors)]);
            exit;
        }
    }

    saveShares($shares);
    echo json_encode(['success' => true, 'message' => 'Configuration imported successfully']);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
