#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * sliders.category ENUM → VARCHAR(80) veya ENUM'a bgaming ekler (backend SSH).
 * Usage: php deploy/aapanel/ensure-sliders-category.php [/path/to/bo-nexthub.site]
 */

$root = dirname(__DIR__, 2);
foreach (array_slice($argv, 1) as $arg) {
    if (trim($arg) !== '' && !str_starts_with($arg, '-')) {
        $root = rtrim(str_replace('\\', '/', $arg), '/');
    }
}

$apiSliders = $root . '/api/Sliders.php';
if (!is_readable($apiSliders)) {
    fwrite(STDERR, "api/Sliders.php not found under {$root}\n");
    exit(1);
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

require_once $apiSliders;

try {
    $pdo = AdminDatabase::pdo();
    $before = $pdo->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sliders' AND COLUMN_NAME = 'category'
         LIMIT 1"
    )->fetchColumn();
    echo 'Before: ' . (is_string($before) ? $before : '(no column)') . "\n";

    ApiSliders::ensureCategoryColumnSupportsBgaming($pdo);

    $after = $pdo->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sliders' AND COLUMN_NAME = 'category'
         LIMIT 1"
    )->fetchColumn();
    echo 'After:  ' . (is_string($after) ? $after : '(no column)') . "\n";

    if (is_string($after) && (str_contains(strtolower($after), 'varchar') || str_contains(strtolower($after), 'bgaming'))) {
        echo "OK: sliders.category accepts bgaming\n";
        exit(0);
    }

    fwrite(STDERR, "WARN: column may still reject bgaming — run migration or ALTER manually\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
