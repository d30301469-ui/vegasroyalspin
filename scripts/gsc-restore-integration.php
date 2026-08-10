#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Rebuild GSC+ schema, restore VGY1 staging credentials, sync products + lobby games.
 *
 * Usage:
 *   php scripts/gsc-restore-integration.php
 *   php scripts/gsc-restore-integration.php --live-only
 *   php scripts/gsc-restore-integration.php --products-only
 */

$root = dirname(__DIR__);
require_once $root . '/admin/app/Core/AdminPaths.php';
admin_paths_bootstrap();
require_once $root . '/admin/app/Core/AdminDatabase.php';
require_once $root . '/services/GscPlusService.php';

$liveOnly = in_array('--live-only', $argv, true);
$productsOnly = in_array('--products-only', $argv, true);

$pdo = AdminDatabase::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$report = [
    'started_at' => date('c'),
    'steps' => [],
];

$assertTables = static function (PDO $pdo) use (&$report): void {
    $need = ['gsc_config', 'gsc_products', 'gsc_games', 'gsc_sessions', 'gsc_transactions', 'gsc_wagers', 'gsc_wallet_logs'];
    $have = $pdo->query("SHOW TABLES LIKE 'gsc%'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $have = array_map('strval', $have);
    $missing = array_values(array_diff($need, $have));
    if ($missing !== []) {
        throw new RuntimeException('GSC tables missing after bootstrap: ' . implode(',', $missing));
    }
    $report['tables'] = $have;
};

$ref = new ReflectionClass(GscPlusService::class);

try {
    if ($ref->hasProperty('schemaBootstrapped')) {
        $prop = $ref->getProperty('schemaBootstrapped');
        $prop->setAccessible(true);
        $prop->setValue(null, false);
    }

    GscPlusService::bootstrap($pdo);
    $assertTables($pdo);
    $report['steps'][] = 'bootstrap_ok';

    GscPlusService::updateConfig($pdo, [
        'operator_code' => 'VGY1',
        'secret_key' => 'zS5CzH7U224nMVgMaghYsY',
        'operator_url' => 'https://staging.gsimw.com',
        'currency' => 'IDR',
        'language_code' => 4,
        'channel_code' => 'gscp',
        'operator_lobby_url' => 'https://vegasroyalspin.com',
        'is_active' => 1,
    ]);
    $cfg = GscPlusService::config($pdo);
    $report['config'] = [
        'operator_code' => (string) ($cfg['operator_code'] ?? ''),
        'currency' => GscPlusService::configCurrency($cfg),
        'operator_url' => (string) ($cfg['operator_url'] ?? ''),
        'is_active' => (int) ($cfg['is_active'] ?? 0),
        'secret_set' => trim((string) ($cfg['secret_key'] ?? '')) !== '',
    ];
    $report['steps'][] = 'config_ok';

    $productSync = GscPlusService::syncProducts($pdo);
    $assertTables($pdo);
    $report['products_synced'] = (int) ($productSync['count'] ?? 0);
    $report['steps'][] = 'products_ok';

    if ($productsOnly) {
        $report['finished_at'] = date('c');
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(0);
    }

    $codes = [];
    if ($liveOnly) {
        $stmt = $pdo->query(
            "SELECT DISTINCT product_code FROM gsc_products
             WHERE is_active = 1
               AND UPPER(game_type) IN ('LIVE_CASINO','LIVE_CASINO_PREMIUM')
             ORDER BY product_code"
        );
        $codes = array_map('intval', $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : []);
    } else {
        $codes = GscPlusService::stagingLiveProductCodes();
        if ($codes === []) {
            $codes = array_map('intval', GscPlusService::STAGING_PRODUCTS_BY_CURRENCY['IDR'] ?? []);
        }
        if ($codes === []) {
            $stmt = $pdo->query(
                "SELECT DISTINCT product_code FROM gsc_products
                 WHERE is_active = 1 AND UPPER(currency) = 'IDR'
                 ORDER BY product_code"
            );
            $codes = array_map('intval', $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : []);
        }
    }

    $games = 0;
    $errors = [];
    foreach ($codes as $code) {
        if ($code <= 0) {
            continue;
        }
        try {
            $exists = (bool) $pdo->query("SHOW TABLES LIKE 'gsc_games'")->fetchColumn();
            if (!$exists) {
                if ($ref->hasProperty('schemaBootstrapped')) {
                    $prop = $ref->getProperty('schemaBootstrapped');
                    $prop->setAccessible(true);
                    $prop->setValue(null, false);
                }
                GscPlusService::bootstrap($pdo);
                GscPlusService::updateConfig($pdo, [
                    'operator_code' => 'VGY1',
                    'secret_key' => 'zS5CzH7U224nMVgMaghYsY',
                    'operator_url' => 'https://staging.gsimw.com',
                    'currency' => 'IDR',
                    'language_code' => 4,
                    'channel_code' => 'gscp',
                    'operator_lobby_url' => 'https://vegasroyalspin.com',
                    'is_active' => 1,
                ]);
                GscPlusService::syncProducts($pdo);
                $errors[] = 'tables_wiped_mid_sync_recovered_before_product_' . $code;
            }
            $games += (int) GscPlusService::syncGames($pdo, $code)['count'];
        } catch (Throwable $e) {
            $errors[] = 'product ' . $code . ': ' . $e->getMessage();
        }
    }

    $assertTables($pdo);
    $report['game_products'] = count($codes);
    $report['games_synced'] = $games;
    $report['errors'] = $errors;
    $report['catalog'] = GscPlusService::catalogStatus($pdo);
    $report['steps'][] = 'games_ok';
    $report['finished_at'] = date('c');

    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($errors === [] ? 0 : 2);
} catch (Throwable $e) {
    $report['fatal'] = $e->getMessage();
    $report['finished_at'] = date('c');
    fwrite(STDERR, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}
