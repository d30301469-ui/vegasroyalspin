<?php

declare(strict_types=1);

/**
 * Ensures project api/ mirror matches admin/api/ for CMS-critical files.
 *
 * Usage: php scripts/verify-api-sync.php
 * Exit 0 = in sync, 1 = drift detected.
 */

$projectRoot = dirname(__DIR__);
$apiRoot = $projectRoot . '/api';
$adminApiRoot = $projectRoot . '/admin/api';

if (!is_dir($apiRoot) || !is_dir($adminApiRoot)) {
    fwrite(STDERR, "api/ or admin/api/ missing.\n");
    exit(1);
}

$criticalFiles = [
    'CmsRemote.php',
    'Footer.php',
    'MobileMenu.php',
    'HomepageSections.php',
    'Sliders.php',
    'SiteSettings.php',
    'MediaUrl.php',
    'Client.php',
    'Bases.php',
    'AuthSliders.php',
    'Promotions.php',
    'bootstrap.php',
];

$drift = [];

foreach ($criticalFiles as $file) {
    $source = $apiRoot . '/' . $file;
    $target = $adminApiRoot . '/' . $file;
    if (!is_readable($source)) {
        $drift[] = "missing source: api/{$file}";
        continue;
    }
    if (!is_readable($target)) {
        $drift[] = "missing mirror: admin/api/{$file}";
        continue;
    }
    $sourceHash = md5_file($source);
    $targetHash = md5_file($target);
    if ($sourceHash !== $targetHash) {
        $drift[] = "drift: api/{$file} != admin/api/{$file}";
    }
}

if ($drift !== []) {
    fwrite(STDERR, "API mirror drift detected:\n");
    foreach ($drift as $line) {
        fwrite(STDERR, "  - {$line}\n");
    }
    fwrite(STDERR, "Run: php scripts/sync-admin-bundle-into-admin.php\n");
    exit(1);
}

echo "API mirror OK (" . count($criticalFiles) . " critical files).\n";
exit(0);
