<?php

// phpcs:disable PSR1.Files.SideEffects

/**
 * User and Group API
 * Returns system users and groups for permission management
 */

declare(strict_types=1);

header('Content-Type: application/json');

/**
 * @return array<int, array<string, mixed>>
 */
function getSystemUsers(): array
{
    $users = [];
    $passwd = file('/etc/passwd');

    if ($passwd === false) {
        return [];
    }

    foreach ($passwd as $line) {
        $parts = explode(':', trim($line));
        if (count($parts) >= 3) {
            $username = $parts[0];
            $uid = (int)$parts[2];

            // Skip system users (UID < 1000) except root and nobody
            if ($uid >= 1000 || in_array($username, ['root', 'nobody'])) {
                $users[] = [
                    'name' => $username,
                    'uid' => $uid,
                    'type' => 'user'
                ];
            }
        }
    }

    return $users;
}

/**
 * @return array<int, array<string, mixed>>
 */
function getSystemGroups(): array
{
    $groups = [];
    $groupFile = file('/etc/group');

    if ($groupFile === false) {
        return [];
    }

    foreach ($groupFile as $line) {
        $parts = explode(':', trim($line));
        if (count($parts) >= 3) {
            $groupname = $parts[0];
            $gid = (int)$parts[2];

            // Skip system groups (GID < 1000) except common ones
            if ($gid >= 1000 || in_array($groupname, ['users', 'wheel', 'disk'])) {
                $groups[] = [
                    'name' => $groupname,
                    'gid' => $gid,
                    'type' => 'group'
                ];
            }
        }
    }

    return $groups;
}

try {
    $users = getSystemUsers();
    $groups = getSystemGroups();

    echo json_encode([
        'success' => true,
        'users' => $users,
        'groups' => $groups
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load users/groups'
    ]);
}
