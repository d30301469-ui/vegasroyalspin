<?php

/**
 * Thin loader — canonical implementation lives in shared/api/MediaUrl.php
 * Do not duplicate logic here; edit the shared file instead.
 */
declare(strict_types=1);

$__sharedRoot = null;
foreach ([dirname(__DIR__, 2) . '/shared', dirname(__DIR__) . '/shared'] as $__dir) {
    if (is_file($__dir . '/runtime.php')) {
        $__sharedRoot = $__dir;
        break;
    }
}
if ($__sharedRoot === null) {
    throw new RuntimeException('shared/ not found for api/MediaUrl.php (deploy shared/ next to admin or at monorepo root).');
}
require_once $__sharedRoot . '/runtime.php';
require_once $__sharedRoot . '/api/MediaUrl.php';
