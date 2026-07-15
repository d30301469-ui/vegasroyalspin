#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Üretim DB'sinde site_ayarlar tablosundaki eski MaltaBet logo URL'ini temizler.
 *
 * Kullanım:
 *   php deploy/aapanel/fix-logo-url.php [/path/to/root] [--db-host=HOST] [--db-name=NAME] [--db-user=USER] [--db-pass=PASS]
 *
 * Örnek:
 *   php deploy/aapanel/fix-logo-url.php /www/wwwroot/vegasroyalspin.com \
 *       --db-name=sql_admin_vegasroyalspin_com \
 *       --db-user=sql_admin_vegasroyalspin_com \
 *       --db-pass=SIFRE_BURAYA
 *
 * Opsiyonel: yeni logo URL'i argüman olarak verin
 *   php deploy/aapanel/fix-logo-url.php ... https://cdn.example.com/logo.png
 */

$root       = dirname(__DIR__, 2);
$newLogoUrl = null;
$cliDb      = [];

foreach (array_slice($argv ?? [], 1) as $arg) {
    $arg = trim($arg);
    if ($arg === '') continue;

    if (preg_match('/^--db-host=(.+)$/', $arg, $m))  { $cliDb['host']     = $m[1]; continue; }
    if (preg_match('/^--db-name=(.+)$/', $arg, $m))  { $cliDb['database'] = $m[1]; continue; }
    if (preg_match('/^--db-user=(.+)$/', $arg, $m))  { $cliDb['username'] = $m[1]; continue; }
    if (preg_match('/^--db-pass=(.+)$/', $arg, $m))  { $cliDb['password'] = $m[1]; continue; }
    if (preg_match('/^--db-port=(.+)$/', $arg, $m))  { $cliDb['port']     = (int)$m[1]; continue; }

    if (str_starts_with($arg, 'http') || str_starts_with($arg, '/assets') || str_starts_with($arg, '/uploads')) {
        $newLogoUrl = $arg;
    } elseif (!str_starts_with($arg, '-')) {
        $root = rtrim(str_replace('\\', '/', $arg), '/');
    }
}

// Env dosyalarını yükle (önce admin, sonra frontend)
$envCandidates = [
    $root . '/admin/.env',
    $root . '/admin/env',
    $root . '/.env',
    $root . '/env',
];
foreach ($envCandidates as $envFile) {
    if (!is_readable($envFile)) continue;
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key); $val = trim($val);
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
        }
    }
}

// CLI argümanları env'e override et
if (isset($cliDb['host']))     { putenv("ADMIN_DB_HOST={$cliDb['host']}");         $_ENV['ADMIN_DB_HOST']     = $cliDb['host']; }
if (isset($cliDb['database'])) { putenv("ADMIN_DB_DATABASE={$cliDb['database']}"); $_ENV['ADMIN_DB_DATABASE'] = $cliDb['database']; }
if (isset($cliDb['username'])) { putenv("ADMIN_DB_USERNAME={$cliDb['username']}"); $_ENV['ADMIN_DB_USERNAME'] = $cliDb['username']; }
if (isset($cliDb['password'])) { putenv("ADMIN_DB_PASSWORD={$cliDb['password']}"); $_ENV['ADMIN_DB_PASSWORD'] = $cliDb['password']; }

// AdminDatabase yükle
$adminDbFile = $root . '/admin/app/Core/AdminDatabase.php';
if (!is_file($adminDbFile)) {
    fwrite(STDERR, "AdminDatabase bulunamadı: $adminDbFile\n");
    exit(1);
}
if (!defined('ADMIN_APP_PATH')) {
    define('ADMIN_APP_PATH', $root . '/admin/app');
}
// Config dosyası AdminDatabase'de require ediliyor, onu da yükle
$adminConfigFile = $root . '/admin/app/Config/admin.php';
if (!is_file($adminConfigFile)) {
    fwrite(STDERR, "Admin config bulunamadı: $adminConfigFile\n");
    exit(1);
}
require_once $adminDbFile;

// Bağlan
try {
    // Config dosyasını oku ve CLI override'ları uygula
    $config = require $adminConfigFile;
    $db = is_array($config['db'] ?? null) ? $config['db'] : [];
    // CLI argümanları her şeyin üstünde
    foreach ($cliDb as $k => $v) {
        $db[$k] = $v;
    }
    $pdo = AdminDatabase::connectWithParams($db);
} catch (Throwable $e) {
    fwrite(STDERR, "DB bağlantısı başarısız: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Kullanım: php {$argv[0]} /path/to/root --db-name=DBNAME --db-user=USER --db-pass=SIFRE\n");
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
    // Site settings envelope + refresh lock
    $root . '/storage/cache/site_settings_envelope.json',
    $root . '/storage/cache/site_settings_envelope.json.refresh.lock',
    $root . '/admin/storage/cache/site_settings_envelope.json',
    $root . '/admin/storage/cache/site_settings_envelope.json.refresh.lock',
    // Circuit breaker — açık kalırsa backend atlanır, stale/boş data gelir
    $root . '/storage/cache/cms_api_circuit.json',
    $root . '/admin/storage/cache/cms_api_circuit.json',
    // Backend reachability cache
    $root . '/storage/cache/backend_reachability.json',
    $root . '/admin/storage/cache/backend_reachability.json',
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
