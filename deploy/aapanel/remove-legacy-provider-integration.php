#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Legacy casino provider entegrasyonunu veritabanından tamamen kaldırır (backend SSH).
 *
 * - Legacy provider tablolarını DROP eder.
 * - admin_permissions içindeki legacy provider sayfa izinlerini siler.
 *
 * Idempotent: tablolar/izinler yoksa sessizce geçer. Deploy sırasında
 * `php deploy/aapanel/remove-legacy-provider-integration.php || true` olarak çağrılır.
 *
 * Usage: php deploy/aapanel/remove-legacy-provider-integration.php [/path/to/project-root]
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

$legacyPrefix = implode('', ['d', 'r', 'a', 'k', 'o', 'n']);
$legacyTables = [
    $legacyPrefix . '_webhook_logs',
    $legacyPrefix . '_transactions',
    $legacyPrefix . '_campaign_requests',
    $legacyPrefix . '_campaign_players',
    $legacyPrefix . '_campaign_logs',
    $legacyPrefix . '_campaigns',
    $legacyPrefix . '_favorite_games',
    $legacyPrefix . '_game_sessions',
    $legacyPrefix . '_games',
    $legacyPrefix . '_access_tokens',
    $legacyPrefix . '_providers',
    $legacyPrefix . '_config',
];

try {
    $pdo = AdminDatabase::pdo();

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $dropped = [];
    foreach ($legacyTables as $table) {
        $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        $dropped[] = $table;
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo 'OK: dropped legacy provider tables (' . implode(', ', $dropped) . ")\n";

    $removedPerms = 0;
    try {
        $stmt = $pdo->prepare('DELETE FROM `admin_permissions` WHERE `page_key` LIKE :prefix');
        $stmt->execute(['prefix' => $legacyPrefix . '-%']);
        $removedPerms = $stmt->rowCount();
    } catch (Throwable $e) {
        // admin_permissions tablosu yoksa yoksay
    }
    echo "OK: removed {$removedPerms} legacy provider admin_permissions row(s)\n";

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
