<?php
/**
 * Idempotent: GSC+ ürün listesini ve CANLI CASINO oyun listelerini senkronlar.
 *
 * Deploy sonrası çalıştırılır. gsc_config aktif değilse veya DB erişilemiyorsa
 * deploy'u kırmadan sessizce çıkar. Aktif canlı oyun varsa ve son sync 12
 * saatten yeni ise atlanır; `--force` ile zorlanabilir.
 *
 * Usage: php tools/sync_gsc_live_games_v1.php [--force]
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/admin/app/Core/AdminDatabase.php';
require_once BASE_PATH . '/services/GscPlusService.php';

$force = in_array('--force', $argv ?? [], true);

try {
    $pdo = AdminDatabase::pdo();
} catch (Throwable $e) {
    echo "DB baglantisi kurulamadi, sync atlandi: {$e->getMessage()}\n";
    exit(0);
}

try {
    if (!GscPlusService::isConfigured($pdo)) {
        echo "GSC+ yapilandirilmamis veya pasif, sync atlandi.\n";
        exit(0);
    }
} catch (Throwable $e) {
    echo "GSC+ config okunamadi, sync atlandi: {$e->getMessage()}\n";
    exit(0);
}

$liveCount = 0;
$syncedAt = '';
try {
    $liveCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM gsc_games
         WHERE is_active = 1 AND UPPER(game_type) IN ('LIVE_CASINO','LIVE_CASINO_PREMIUM')"
    )->fetchColumn();
    $syncedAt = (string) $pdo->query('SELECT games_synced_at FROM gsc_config WHERE id = 1')->fetchColumn();
} catch (Throwable) {
}

$fresh = $syncedAt !== '' && (time() - (int) strtotime($syncedAt)) < 12 * 3600;
if (!$force && $liveCount > 0 && $fresh) {
    echo "Guncel: {$liveCount} aktif canli oyun (son sync: {$syncedAt}), atlandi.\n";
    exit(0);
}

$result = GscPlusService::syncLiveCasinoCatalog($pdo);
echo "Urunler: {$result['products']}, canli urun: {$result['live_products']}, oyun kaydi: {$result['games']}\n";
foreach ($result['errors'] as $error) {
    echo "  HATA: {$error}\n";
}
