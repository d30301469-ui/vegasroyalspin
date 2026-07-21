#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Drakon `drakon_config.api_base_url` doğrulama/düzeltme (backend SSH).
 *
 * Stale/decommissioned "gator.drakonapi.tech" değeri (eski/reseller endpoint,
 * artık bağlantı kabul etmiyor) kalıcı olarak Drakon'un resmi entegrasyon
 * kılavuzundaki Base URL ile değiştirilir: https://gator.drakon.casino/api/v1
 *
 * Usage: php deploy/aapanel/fix-drakon-api-base.php [/path/to/admin.vegasroyalspin.com]
 */

const OFFICIAL_API_BASE = 'https://gator.drakon.casino/api/v1';

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

try {
    $pdo = AdminDatabase::pdo();

    $exists = (int) $pdo->query("SHOW TABLES LIKE 'drakon_config'")->rowCount();
    if ($exists === 0) {
        echo "drakon_config table does not exist yet — nothing to fix.\n";
        exit(0);
    }

    $row = $pdo->query('SELECT id, api_base_url FROM drakon_config WHERE id = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        echo "drakon_config has no row with id=1 — nothing to fix.\n";
        exit(0);
    }

    $current = trim((string) ($row['api_base_url'] ?? ''));
    $host    = parse_url($current, PHP_URL_HOST);
    if (!is_string($host) || stripos($host, 'drakonapi.tech') === false) {
        echo "api_base_url already OK ({$current}).\n";
        exit(0);
    }

    $stmt = $pdo->prepare('UPDATE drakon_config SET api_base_url = :base WHERE id = 1');
    $stmt->execute([':base' => OFFICIAL_API_BASE]);
    echo "Fixed api_base_url: {$current} -> " . OFFICIAL_API_BASE . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
