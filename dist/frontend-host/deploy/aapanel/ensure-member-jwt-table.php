#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * member_jwt_tokens tablosunu oluşturur / doğrular (backend SSH).
 * Usage: php deploy/aapanel/ensure-member-jwt-table.php [/path/to/bo-nexthub.site]
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
require_once admin_project_path('services/MemberJwtService.php');

try {
    $pdo = AdminDatabase::pdo();
    MemberJwtService::ensureTable($pdo);
    $count = (int) $pdo->query('SELECT COUNT(*) FROM member_jwt_tokens')->fetchColumn();
    echo "OK: member_jwt_tokens table ready (rows: {$count})\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
