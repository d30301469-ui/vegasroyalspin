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

try {
    $result = GscPlusService::syncProducts($pdo);
    echo "Urunler senkronlandi: {$result['count']}\n";
} catch (Throwable $e) {
    echo "Urun sync hatasi: {$e->getMessage()}\n";
}

$liveProducts = [];
try {
    $stmt = $pdo->query(
        "SELECT product_code, product_name, provider FROM gsc_products
         WHERE is_active = 1 AND UPPER(game_type) IN ('LIVE_CASINO','LIVE_CASINO_PREMIUM')
         ORDER BY product_code"
    );
    $liveProducts = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    echo "Canli urunler okunamadi: {$e->getMessage()}\n";
    exit(0);
}

if ($liveProducts === []) {
    echo "Aktif canli casino urunu bulunamadi.\n";
    exit(0);
}

$total = 0;
foreach ($liveProducts as $product) {
    $code = (int) ($product['product_code'] ?? 0);
    $label = trim((string) ($product['provider'] ?? '')) ?: trim((string) ($product['product_name'] ?? '')) ?: (string) $code;
    try {
        $result = GscPlusService::syncGames($pdo, $code);
        $total += (int) $result['count'];
        echo "  {$label} ({$code}): {$result['count']} oyun\n";
    } catch (Throwable $e) {
        echo "  {$label} ({$code}) HATA: {$e->getMessage()}\n";
    }
}

echo "Toplam senkronlanan canli oyun kaydi: {$total}\n";
