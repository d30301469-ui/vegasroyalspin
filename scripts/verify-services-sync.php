<?php

declare(strict_types=1);

/**
 * Verifies root services/ matches admin/services/ (deploy drift guard).
 *
 * Usage:
 *   php scripts/verify-services-sync.php
 *   php scripts/verify-services-sync.php --json
 */

$projectRoot = dirname(__DIR__);
$jsonOutput = in_array('--json', $argv ?? [], true);

$syncDirs = [
    'services' => 'Provider + member API services',
];

$issues = [];

foreach ($syncDirs as $dir => $label) {
    $sourceDir = $projectRoot . '/' . $dir;
    $targetDir = $projectRoot . '/admin/' . $dir;

    if (!is_dir($sourceDir)) {
        $issues[] = [
            'type' => 'missing_source',
            'path' => $dir . '/',
            'message' => "Source directory missing: {$dir}/",
        ];
        continue;
    }
    if (!is_dir($targetDir)) {
        $issues[] = [
            'type' => 'missing_target',
            'path' => 'admin/' . $dir . '/',
            'message' => "Admin mirror missing: admin/{$dir}/ — run php scripts/sync-admin-bundle-into-admin.php",
        ];
        continue;
    }

    $sourceFiles = listPhpFiles($sourceDir);
    $targetFiles = listPhpFiles($targetDir);

    foreach (array_diff($sourceFiles, $targetFiles) as $relative) {
        $issues[] = [
            'type' => 'missing_in_admin',
            'path' => $dir . '/' . $relative,
            'message' => "Missing in admin mirror: admin/{$dir}/{$relative}",
        ];
    }

    foreach (array_diff($targetFiles, $sourceFiles) as $relative) {
        $issues[] = [
            'type' => 'extra_in_admin',
            'path' => 'admin/' . $dir . '/' . $relative,
            'message' => "Extra file in admin mirror (not in root): admin/{$dir}/{$relative}",
        ];
    }

    foreach (array_intersect($sourceFiles, $targetFiles) as $relative) {
        $sourcePath = $sourceDir . '/' . $relative;
        $targetPath = $targetDir . '/' . $relative;
        $sourceHash = @md5_file($sourcePath);
        $targetHash = @md5_file($targetPath);
        if ($sourceHash === false || $targetHash === false) {
            $issues[] = [
                'type' => 'unreadable',
                'path' => $dir . '/' . $relative,
                'message' => "Could not read file for hash: {$dir}/{$relative}",
            ];
            continue;
        }
        if (!hash_equals($sourceHash, $targetHash)) {
            $issues[] = [
                'type' => 'content_mismatch',
                'path' => $dir . '/' . $relative,
                'message' => "Content drift: {$dir}/{$relative} ≠ admin/{$dir}/{$relative}",
            ];
        }
    }
}

if ($jsonOutput) {
    echo json_encode([
        'ok' => $issues === [],
        'issues' => $issues,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} elseif ($issues === []) {
    echo "OK: admin/services/ is in sync with services/\n";
} else {
    fwrite(STDERR, "ERROR: services sync drift detected (" . count($issues) . " issue(s)):\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue['message'] . "\n");
    }
    fwrite(STDERR, "\nFix: php scripts/sync-admin-bundle-into-admin.php\n");
}

exit($issues === [] ? 0 : 1);

/**
 * @return list<string> paths relative to $root using forward slashes
 */
function listPhpFiles(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    $rootNorm = rtrim(str_replace('\\', '/', $root), '/');

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }
        $path = str_replace('\\', '/', $item->getPathname());
        if (!str_ends_with(strtolower($path), '.php')) {
            continue;
        }
        $relative = ltrim(substr($path, strlen($rootNorm)), '/');
        $files[] = $relative;
    }

    sort($files);

    return $files;
}
