<?php

declare(strict_types=1);

// Reload Samba configuration
require_once 'include/lib.php';

header('Content-Type: application/json');

// CSRF validation is handled globally by Unraid in local_prepend.php

$result = reloadSamba();
echo json_encode($result);
