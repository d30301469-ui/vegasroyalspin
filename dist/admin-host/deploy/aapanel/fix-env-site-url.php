#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Fix SITE_URL / FRONTEND_URL in .env when they incorrectly include /api/v2 path.
 * Usage: php deploy/aapanel/fix-env-site-url.php [/path/to/site]
 */

$root = isset($argv[1]) && trim((string) $argv[1]) !== ''
    ? rtrim(str_replace('\\', '/', (string) $argv[1]), '/')
    : dirname(__DIR__, 2);

$envFile = $root . '/.env';
if (!is_readable($envFile)) {
    fwrite(STDERR, "No .env at {$envFile}\n");
    exit(1);
}

require_once dirname(__DIR__, 2) . '/app/Services/FrontendInstaller.php';

$lines = file($envFile, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    fwrite(STDERR, "Cannot read .env\n");
    exit(1);
}

$changed = false;
$out = [];
foreach ($lines as $line) {
    $trimmed = trim((string) $line);
    if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
        $out[] = $line;
        continue;
    }
    [$key, $value] = explode('=', $trimmed, 2);
    $key = trim($key);
    $value = trim($value, " \t\"'");
    if (!in_array($key, ['SITE_URL', 'FRONTEND_URL', 'FRONTEND_FALLBACK_URL'], true)) {
        $out[] = $line;
        continue;
    }
    $normalized = FrontendInstaller::normalizeSiteOrigin($value);
    if ($normalized !== $value) {
        echo "Fix {$key}: {$value} → {$normalized}\n";
        $changed = true;
        $out[] = $key . '=' . $normalized;
    } else {
        $out[] = $line;
    }
}

if (!$changed) {
    echo "SITE_URL / FRONTEND_URL already OK.\n";
    exit(0);
}

copy($envFile, $envFile . '.bak.' . date('YmdHis'));
file_put_contents($envFile, implode("\n", $out) . "\n");
echo "Updated .env (backup created).\n";
