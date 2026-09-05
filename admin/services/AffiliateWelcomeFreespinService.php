<?php

/**
 * Thin loader — canonical implementation lives in shared/services/AffiliateWelcomeFreespinService.php
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
    throw new RuntimeException('shared/ not found for services/AffiliateWelcomeFreespinService.php');
}
require_once $__sharedRoot . '/runtime.php';
require_once $__sharedRoot . '/services/AffiliateWelcomeFreespinService.php';
