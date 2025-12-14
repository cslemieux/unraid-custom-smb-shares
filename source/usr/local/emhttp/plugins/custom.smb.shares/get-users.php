<?php

/**
 * Get system users for SMB access configuration
 * Returns users that can be assigned SMB share access
 */

declare(strict_types=1);

header('Content-Type: application/json');

try {
    $users = [];

    // Read /etc/passwd for system users
    $passwd = file('/etc/passwd', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($passwd === false) {
        throw new Exception('Cannot read /etc/passwd');
    }

    foreach ($passwd as $line) {
        $parts = explode(':', $line);
        if (count($parts) < 7) {
            continue;
        }

        $username = $parts[0];
        $uid = (int)$parts[2];

        // Include users with uid >= 1000 (regular users on Unraid)
        // These are the users created via Unraid's user management
        if ($uid >= 1000) {
            $users[] = [
                'name' => $username,
                'uid' => $uid
            ];
        }
    }

    // Sort by username
    usort($users, fn($a, $b) => strcasecmp($a['name'], $b['name']));

    echo json_encode(['success' => true, 'users' => $users]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
