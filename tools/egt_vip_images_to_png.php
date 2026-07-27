#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * EGT VIP aggregator oyun görsellerini DB'de PNG uzantısına çevirir.
 *
 * Kullanım: php tools/egt_vip_images_to_png.php
 */

$root = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (!defined('METROPOL_ADMIN_PANEL')) {
    define('METROPOL_ADMIN_PANEL', true);
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

require_once $root . '/services/CasinoAggregatorService.php';

try {
    $pdo = AdminDatabase::pdo();
} catch (Throwable $e) {
    fwrite(STDERR, 'DB baglanti hatasi: ' . $e->getMessage() . "\n");
    fwrite(STDERR, "Not: Bu scripti DB erisimi olan hostta calistirin (admin/backend veya monorepo).\n");
    exit(1);
}

echo "DB baglandi.\n";
$result = CasinoAggregatorService::repairEgtVipImagesToPng($pdo);
echo 'EGT VIP scanned: ' . (int) ($result['scanned'] ?? 0) . "\n";
echo 'EGT VIP updated to PNG: ' . (int) ($result['updated'] ?? 0) . "\n";

$sample = $pdo->query(
    "SELECT vendor_code, game_code, image_url
     FROM casino_aggregator_games
     WHERE LOWER(REPLACE(REPLACE(vendor_code, '-', ''), '_', '')) LIKE '%egtvip%'
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

foreach (is_array($sample) ? $sample : [] as $row) {
    echo ($row['vendor_code'] ?? '') . ' / ' . ($row['game_code'] ?? '') . ' => ' . ($row['image_url'] ?? '') . "\n";
}
