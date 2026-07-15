#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Üretim DB'sinde site_ayarlar tablosundaki eski MaltaBet logo URL'ini temizler.
 *
 * Kullanım (sunucuda):
 *   php deploy/aapanel/fix-logo-url.php [/path/to/vegasroyalspin]
 *
 * Opsiyonel: yeni logo URL'i argüman olarak verin
 *   php deploy/aapanel/fix-logo-url.php /path/to/root https://cdn.example.com/logo.png
 */

$root = dirname(__DIR__, 2);
$newLogoUrl = null;

foreach (array_slice($argv ?? [], 1) as $arg) {
    $arg = trim($arg);
    if ($arg === '') {
        continue;
    }
    if (str_starts_with($arg, 'http') || str_starts_with($arg, '/assets') || str_starts_with($arg, '/uploads')) {
        $newLogoUrl = $arg;
    } elseif (!str_starts_with($arg, '-')) {
        $root = rtrim(str_replace('\\', '/', $arg), '/');
    }
}

// Env yükle
$envFile = $root . '/env';
if (!is_readable($envFile)) {
    $envFile = $root . '/.env';
}
if (is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            if ($key !== '' && getenv($key) === false) {
                putenv("$key=$val");
                $_ENV[$key] = $val;
            }
        }
    }
}

// AdminDatabase yükle
$adminDbFile = $root . '/admin/app/Core/AdminDatabase.php';
if (!is_file($adminDbFile)) {
    fwrite(STDERR, "AdminDatabase bulunamadı: $adminDbFile\n");
    exit(1);
}

if (!defined('ADMIN_APP_PATH')) {
    define('ADMIN_APP_PATH', $root . '/admin/app');
}
require_once $adminDbFile;

// Bağlan
try {
    $pdo = AdminDatabase::pdo();
} catch (Throwable $e) {
    fwrite(STDERR, "DB bağlantısı başarısız: " . $e->getMessage() . "\n");
    exit(1);
}

// Mevcut değeri göster
$current = $pdo->query("SELECT id, site_adi, logo_url FROM `site_ayarlar` LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "=== Mevcut kayıtlar ===\n";
foreach ($current as $row) {
    echo "  id={$row['id']} site_adi={$row['site_adi']} logo_url=" . ($row['logo_url'] ?? 'NULL') . "\n";
}

// Eski MaltaBet logo referanslarını temizle/güncelle
$stalePatterns = [
    '%MaltaBetLogo%',
    '%MaltaBet%logo%',
    '%maltabet%logo%',
];

$updated = 0;
foreach ($stalePatterns as $pattern) {
    if ($newLogoUrl !== null) {
        // Yeni URL verilmişse güncelle
        $stmt = $pdo->prepare("UPDATE `site_ayarlar` SET `logo_url` = ? WHERE `logo_url` LIKE ?");
        $stmt->execute([$newLogoUrl, $pattern]);
    } else {
        // URL verilmemişse NULL yap (admin panelinden tekrar ayarlanacak)
        $stmt = $pdo->prepare("UPDATE `site_ayarlar` SET `logo_url` = NULL WHERE `logo_url` LIKE ?");
        $stmt->execute([$pattern]);
    }
    $updated += $stmt->rowCount();
}

// site_adi alanında da MaltaBet varsa düzelt
$stmtName = $pdo->prepare("UPDATE `site_ayarlar` SET `site_adi` = 'VegasRoyalSpin' WHERE LOWER(`site_adi`) = 'maltabet'");
$stmtName->execute();
$updatedName = $stmtName->rowCount();

echo "\n=== Sonuç ===\n";
echo "  logo_url güncellenen satır: $updated\n";
echo "  site_adi güncellenen satır: $updatedName\n";
if ($newLogoUrl !== null) {
    echo "  Yeni logo URL: $newLogoUrl\n";
} else {
    echo "  logo_url NULL yapıldı — admin panelinden logo ayarlayın.\n";
}

// Cache dosyasını sil (bir sonraki istekte taze veri çekilsin)
$cacheTargets = [
    $root . '/storage/cache/site_settings_envelope.json',
    $root . '/storage/cache/site_settings_envelope.json.refresh.lock',
    $root . '/admin/storage/cache/site_settings_envelope.json',
    $root . '/admin/storage/cache/site_settings_envelope.json.refresh.lock',
];
foreach ($cacheTargets as $cacheFile) {
    if (is_file($cacheFile)) {
        unlink($cacheFile);
        echo "  Cache temizlendi: $cacheFile\n";
    }
}

// Webroot'taki eski body.html varsa sil (hardcoded logo referansı içerir)
$bodyHtml = $root . '/body.html';
if (is_file($bodyHtml)) {
    unlink($bodyHtml);
    echo "  Silindi: $bodyHtml\n";
}

// Güncel değeri tekrar göster
$after = $pdo->query("SELECT id, site_adi, logo_url FROM `site_ayarlar` LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== Güncel kayıtlar ===\n";
foreach ($after as $row) {
    echo "  id={$row['id']} site_adi={$row['site_adi']} logo_url=" . ($row['logo_url'] ?? 'NULL') . "\n";
}

echo "\nTamamlandı.\n";
