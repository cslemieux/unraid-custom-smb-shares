<?php

declare(strict_types=1);

require_once 'include/lib.php';

// Simple status check - no auth required for read-only status
header('Content-Type: text/plain');

$status = getSambaStatus();
echo $status['output'];
