#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Live-safe sliders reset utility.
 * - Creates a timestamped backup table.
 * - Truncates sliders table data.
 *
 * Usage:
 *   php tools/reset_sliders_table.php [--once-lock=/abs/path/to/lockfile]
 */

$root = dirname(__DIR__);
$onceLock = '';

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--once-lock=')) {
        $onceLock = trim(substr($arg, strlen('--once-lock=')));
    }
}

if ($onceLock !== '' && is_file($onceLock)) {
    echo "SKIP: lock exists at {$onceLock}\n";
    exit(0);
}

require_once $root . '/config/env.php';
frontend_load_dotenv($root);

$bootstrapCandidates = [
    $root . '/app/Core/AdminPaths.php',
    $root . '/admin/app/Core/AdminPaths.php',
];
foreach ($bootstrapCandidates as $candidate) {
    if (!is_readable($candidate)) {
        continue;
    }
    require_once $candidate;
    admin_paths_bootstrap();
    break;
}

if (!class_exists('AdminDatabase', false)) {
    $dbPath = is_readable($root . '/app/Core/AdminDatabase.php')
        ? $root . '/app/Core/AdminDatabase.php'
        : $root . '/admin/app/Core/AdminDatabase.php';
    if (!is_readable($dbPath)) {
        fwrite(STDERR, "AdminDatabase.php not found\n");
        exit(1);
    }
    require_once $dbPath;
}

try {
    $pdo = AdminDatabase::pdo();
    $before = (int) $pdo->query('SELECT COUNT(*) FROM sliders')->fetchColumn();

    $backupTable = 'sliders_backup_' . date('Ymd_His');
    $pdo->exec("CREATE TABLE `{$backupTable}` AS SELECT * FROM `sliders`");

    $pdo->exec('TRUNCATE TABLE `sliders`');
    $after = (int) $pdo->query('SELECT COUNT(*) FROM sliders')->fetchColumn();

    echo "Backup table: {$backupTable}\n";
    echo "Rows before: {$before}\n";
    echo "Rows after: {$after}\n";

    if ($onceLock !== '') {
        $lockDir = dirname($onceLock);
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0775, true);
        }
        @file_put_contents($onceLock, date('c') . "\nbackup={$backupTable}\nrows_before={$before}\n");
        echo "Lock written: {$onceLock}\n";
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
