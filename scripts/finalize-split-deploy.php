<?php

declare(strict_types=1);

/**
 * Pre-package cleanup: strip BOM + declare from API v2 shims that re-enter index.php.
 *
 * Usage: php scripts/finalize-split-deploy.php
 */

$root = dirname(__DIR__);
$apiRoot = $root . '/admin/api/v2';

$keepDeclare = [
    'drakon_callback.php',
    'bgaming_callback.php',
];

$declarePattern = '/^\s*declare\(strict_types=1\);\s*\R/m';
$bomPattern = '/^\xEF\xBB\xBF/';

$declareRemoved = 0;
$bomRemoved = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($apiRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $basename = $file->getFilename();
    $path = $file->getPathname();
    $content = (string) file_get_contents($path);
    $original = $content;

    if (preg_match($bomPattern, $content)) {
        $content = preg_replace($bomPattern, '', $content) ?? $content;
        $bomRemoved++;
    }

    $isShim = str_contains($content, "/index.php")
        && (str_contains($content, 'require __DIR__') || str_contains($content, 'require_once __DIR__'));
    $skipDeclare = in_array($basename, $keepDeclare, true)
        || str_contains(str_replace('\\', '/', $path), '/includes/')
        || str_contains(str_replace('\\', '/', $path), '/routes/');

    if (!$skipDeclare && ($isShim || $basename === 'index.php' || $basename === 'member_local.php' || $basename === 'internal.php')) {
        $content = preg_replace($declarePattern, '', $content, -1, $count) ?? $content;
        if ($count > 0) {
            $declareRemoved += $count;
        }
    }

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo 'Fixed: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $path) . "\n";
    }
}

echo "\nBOM fixes: {$bomRemoved}, declare lines removed: {$declareRemoved}\n";

$phpBinary = (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') ? PHP_BINARY : 'php';

passthru($phpBinary . ' ' . escapeshellarg($root . '/scripts/strip-api-v2-declare.php'), $code1);
if ($code1 !== 0) {
    exit($code1);
}

passthru($phpBinary . ' ' . escapeshellarg($root . '/scripts/verify-split-deploy.php'), $code2);
if ($code2 !== 0) {
    exit($code2);
}

echo "\nfinalize-split-deploy: OK\n";
