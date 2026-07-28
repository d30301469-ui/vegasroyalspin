<?php
/**
 * GSC+ entegrasyon teşhisi: agent cüzdanı (3.12), ürün listesi (3.6) ve seçilen
 * ürünlerin oyun listesi (3.4) canlı olarak sorgulanır, ardından yerel
 * gsc_products / gsc_games tabloları ile karşılaştırılır.
 *
 * Kimlik bilgileri gsc_config tablosundan okunur; script hiçbir şey yazmaz.
 *
 * Usage: php tools/gsc_diagnose.php
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/admin/app/Core/AdminDatabase.php';
require_once BASE_PATH . '/services/GscPlusService.php';

$pdo = AdminDatabase::pdo();
$cfg = GscPlusService::config($pdo);
$currency = GscPlusService::configCurrency($cfg);

echo "operator_code : " . (string) ($cfg['operator_code'] ?? '') . "\n";
echo "operator_url  : " . (string) ($cfg['operator_url'] ?? '') . "\n";
echo "currency      : {$currency}\n";
echo "is_active     : " . (int) ($cfg['is_active'] ?? 0) . "\n\n";

if (!GscPlusService::isConfigured($pdo)) {
    echo "GSC+ yapilandirilmamis veya pasif; canli sorgular atlandi.\n";
    exit(1);
}

echo "== 3.12 Agent Wallet ==\n";
$contracted = [];
try {
    $wallet = GscPlusService::agentWalletBalance($pdo);
    echo 'mod: ' . ($wallet['is_credit'] ? 'credit' : 'buy-in') . "\n";
    foreach ($wallet['currencies'] as $row) {
        $contracted[] = $row['currency'];
        printf("  %-6s %s\n", $row['currency'], number_format($row['current_balance'], 4, '.', ','));
    }
    if (!in_array($currency, $contracted, true)) {
        echo "  UYARI: yapilandirilan {$currency} agent cuzdaninda yok!\n";
    }
} catch (Throwable $e) {
    echo '  HATA: ' . $e->getMessage() . "\n";
}

echo "\n== Yerel katalog ==\n";
$status = GscPlusService::catalogStatus($pdo);
foreach ($status as $key => $value) {
    printf("  %-20s %s\n", $key, is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
}

echo "\n== gsc_products (aktif/pasif dagilimi) ==\n";
$stmt = $pdo->query(
    'SELECT currency, game_type, COUNT(*) AS n, SUM(is_active) AS active
     FROM gsc_products GROUP BY currency, game_type ORDER BY currency, game_type'
);
printf("  %-8s %-22s %6s %8s\n", 'currency', 'game_type', 'toplam', 'aktif');
foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
    printf(
        "  %-8s %-22s %6d %8d\n",
        (string) $row['currency'],
        (string) $row['game_type'],
        (int) $row['n'],
        (int) $row['active']
    );
}

echo "\n== gsc_games (aktif oyunlar / urun) ==\n";
$stmt = $pdo->query(
    "SELECT g.product_code, g.product_currency, g.game_type,
            COUNT(*) AS n, SUM(g.is_active) AS active,
            COALESCE(NULLIF(g.provider, ''), g.product_name) AS provider
     FROM gsc_games g
     GROUP BY g.product_code, g.product_currency, g.game_type, provider
     ORDER BY g.product_code"
);
printf("  %-8s %-6s %-22s %6s %8s  %s\n", 'product', 'cur', 'game_type', 'toplam', 'aktif', 'provider');
foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
    printf(
        "  %-8d %-6s %-22s %6d %8d  %s\n",
        (int) $row['product_code'],
        (string) $row['product_currency'],
        (string) $row['game_type'],
        (int) $row['n'],
        (int) $row['active'],
        (string) $row['provider']
    );
}

echo "\n== Launch cozumlemesi (aktif canli oyunlardan ornek) ==\n";
$stmt = $pdo->query(
    "SELECT product_code, game_code, game_name, support_currency, product_currency
     FROM gsc_games
     WHERE is_active = 1 AND UPPER(game_type) IN ('LIVE_CASINO','LIVE_CASINO_PREMIUM')
     ORDER BY product_code, game_code LIMIT 10"
);
foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
    $launchCurrency = strtoupper(trim((string) $row['product_currency'])) ?: $currency;
    $supports = GscPlusService::gameSupportsCurrency((string) $row['support_currency'], $launchCurrency);
    printf(
        "  %-28s support=%-14s launch_currency=%-6s %s\n",
        GscPlusService::buildGameId((int) $row['product_code'], (string) $row['game_code']),
        (string) $row['support_currency'],
        $launchCurrency,
        $supports ? 'OK' : 'DESTEKLENMIYOR'
    );
}
