#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Yarım kalmış frontend kurulumunu onarır (.env eksik anahtarlar + loopback).
 *
 * Usage:
 *   php deploy/aapanel/repair-frontend-install.php [/path/to/vegasroyalspin.com]
 *   php deploy/aapanel/repair-frontend-install.php --jwt="BACKEND_ILE_AYNI_SECRET" [/path]
 *   php deploy/aapanel/repair-frontend-install.php --reset-lock [/path]
 */

$root = dirname(__DIR__, 2);
$jwtOverride = '';
$resetLock = false;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--jwt=')) {
        $jwtOverride = trim(substr($arg, 6), " \t\"'");
        continue;
    }
    if ($arg === '--reset-lock') {
        $resetLock = true;
        continue;
    }
    if (trim($arg) !== '' && !str_starts_with($arg, '-')) {
        $root = rtrim(str_replace('\\', '/', $arg), '/');
    }
}

$fixScript = $root . '/deploy/aapanel/fix-frontend-env.php';
if (!is_file($fixScript)) {
    fwrite(STDERR, "Missing {$fixScript}\n");
    exit(1);
}

passthru(PHP_BINARY . ' ' . escapeshellarg($fixScript) . ' ' . escapeshellarg($root), $fixCode);
if ($fixCode !== 0) {
    exit($fixCode);
}

$envFile = $root . '/.env';
$lines = is_readable($envFile) ? file($envFile, FILE_IGNORE_NEW_LINES) : false;
if (!is_array($lines)) {
    fwrite(STDERR, "Cannot read .env\n");
    exit(1);
}

if ($jwtOverride !== '') {
    $patched = false;
    $out = [];
    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed !== '' && !str_starts_with($trimmed, '#') && str_starts_with($trimmed, 'MEMBER_JWT_SECRET=')) {
            $out[] = 'MEMBER_JWT_SECRET=' . $jwtOverride;
            $patched = true;
            echo "Set MEMBER_JWT_SECRET from --jwt\n";
            continue;
        }
        $out[] = $line;
    }
    if (!$patched) {
        $out[] = 'MEMBER_JWT_SECRET=' . $jwtOverride;
        echo "Added MEMBER_JWT_SECRET from --jwt\n";
    }
    copy($envFile, $envFile . '.bak.' . date('YmdHis'));
    file_put_contents($envFile, implode("\n", $out) . "\n");
    $lines = $out;
}

$cloudflareScript = $root . '/deploy/aapanel/fix-cloudflare-env.php';
if (is_file($cloudflareScript)) {
    passthru(PHP_BINARY . ' ' . escapeshellarg($cloudflareScript) . ' ' . escapeshellarg($root), $cfCode);
}

if ($resetLock) {
    $lock = $root . '/storage/install.lock';
    if (is_file($lock)) {
        unlink($lock);
        echo "Removed storage/install.lock — /install acilabilir\n";
    }
}

require_once $root . '/config/env.php';
if (is_readable($root . '/app/Core/FrontendInstallGate.php')) {
    require_once $root . '/app/Core/FrontendInstallGate.php';
    frontend_load_dotenv($root);
    $jwt = frontend_env_string('MEMBER_JWT_SECRET');
    if (!FrontendInstallGate::isValidSecret($jwt)) {
        fwrite(STDERR, "\nMEMBER_JWT_SECRET hala gecersiz.\n");
        fwrite(STDERR, "Backend'den kopyalayin:\n");
        fwrite(STDERR, "  grep MEMBER_JWT_SECRET /www/wwwroot/bo-nexthub.site/.env\n");
        fwrite(STDERR, "Sonra:\n");
        fwrite(STDERR, "  php deploy/aapanel/repair-frontend-install.php --jwt=\"DEGER\" {$root}\n");
        exit(1);
    }
    if (!is_file($root . '/storage/install.lock')) {
        FrontendInstallGate::writeLock($root, ['repaired' => true]);
        echo "Wrote storage/install.lock\n";
    }
}

echo "\nRepair OK. Test: curl -sS https://vegasroyalspin.com/health.php?quick=1\n";
