#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Remove the retired GamingSoft/GSC+ integration data from the backend database.
 *
 * Idempotent: missing tables and permission rows are ignored.
 *
 * Usage: php deploy/aapanel/remove-gamingsoft-integration.php [/path/to/project-root]
 */

$root = dirname(__DIR__, 2);
foreach (array_slice($argv, 1) as $arg) {
    if (trim($arg) !== '' && !str_starts_with($arg, '-')) {
        $root = rtrim(str_replace('\\', '/', $arg), '/');
    }
}

$bootstrapCandidates = [
    $root . '/app/Core/AdminPaths.php',
    $root . '/admin/app/Core/AdminPaths.php',
];
$bootstrapped = false;
foreach ($bootstrapCandidates as $candidate) {
    if (!is_readable($candidate)) {
        continue;
    }
    require_once $candidate;
    admin_paths_bootstrap();
    $bootstrapped = true;
    break;
}

if (!$bootstrapped) {
    fwrite(STDERR, "AdminPaths bootstrap not found under {$root}\n");
    exit(1);
}

if (!class_exists('AdminDatabase', false)) {
    require_once admin_project_path('app/Core/AdminDatabase.php');
}

$tables = [
    'gsc_wallet_logs',
    'gsc_transactions',
    'gsc_wagers',
    'gsc_sessions',
    'gsc_games',
    'gsc_products',
    'gsc_config',
];

try {
    $pdo = AdminDatabase::pdo();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
        foreach ($tables as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    $removedPermissions = 0;
    try {
        $stmt = $pdo->prepare(
            "DELETE FROM `admin_permissions`
             WHERE `page_key` LIKE 'gamingsoft-%'
                OR `page_key` LIKE 'gsc-plus-%'"
        );
        $stmt->execute();
        $removedPermissions = $stmt->rowCount();
    } catch (Throwable) {
        // The permissions table may not exist on partially installed environments.
    }

    try {
        $stmt = $pdo->prepare(
            "DELETE FROM `migrations`
             WHERE `migration` = '2026_07_28_100000_create_gsc_plus_tables.php'"
        );
        $stmt->execute();
    } catch (Throwable) {
        // The migrations table may not exist on partially installed environments.
    }

    echo 'OK: dropped GamingSoft tables (' . implode(', ', $tables) . ")\n";
    echo "OK: removed {$removedPermissions} GamingSoft permission rows\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . "\n");
    exit(1);
}
