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

echo "\n== Canli lobi sorgusu (LiveCasinoQuery) ==\n";
require_once BASE_PATH . '/services/CasinoAggregatorService.php';
require_once BASE_PATH . '/services/LiveCasinoQuery.php';

// Tablo varligi + collation: UNION'i bozan collation uyusmazligi burada gorunur.
foreach (['gsc_games', 'casino_aggregator_games', 'casino_aggregator_vendors'] as $table) {
    try {
        $stmt = $pdo->prepare(
            'SELECT TABLE_COLLATION
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
             LIMIT 1'
        );
        $stmt->execute([':t' => $table]);
        $collation = $stmt->fetchColumn();
        printf("  %-28s %s\n", $table, $collation === false ? 'YOK' : 'var, collation=' . (string) $collation);
    } catch (Throwable $e) {
        printf("  %-28s probe hatasi: %s\n", $table, $e->getMessage());
    }
}

// pageFromDatabase ile ayni UNION'i calistir; istisnalar yutulmadan gosterilir.
$liveTypes = "UPPER(g.game_type) IN ('LIVE_CASINO','LIVE_CASINO_PREMIUM')";
$gscSelect = "SELECT
        CONCAT('gsc:', g.product_code, ':', g.game_code) AS game_id,
        g.game_name AS name,
        COALESCE(NULLIF(g.provider, ''), NULLIF(g.product_name, ''), CAST(g.product_code AS CHAR)) AS provider,
        CAST(g.product_code AS CHAR) AS provider_code,
        COALESCE(NULLIF(g.image_url, ''), '') AS image_url,
        CAST('' AS CHAR) AS image_fallbacks,
        g.is_featured AS is_featured,
        'gsc' AS source
     FROM gsc_games g
     WHERE g.is_active = 1 AND (g.game_code <> '_lobby' OR g.entry_type = 2) AND {$liveTypes}";

$liveMatch = class_exists('CasinoAggregatorService', false)
    ? CasinoAggregatorService::liveVendorSqlMatch('g.vendor_code')
    : '0';
$aggSelect = "SELECT
        CONCAT('aggregator:', g.vendor_code, ':', g.game_code) AS game_id,
        g.game_name AS name,
        COALESCE(NULLIF(v.vendor_name, ''), g.vendor_code) AS provider,
        g.vendor_code AS provider_code,
        COALESCE(NULLIF(g.image_url, ''), '') AS image_url,
        CAST('' AS CHAR) AS image_fallbacks,
        g.is_featured AS is_featured,
        'aggregator' AS source
     FROM casino_aggregator_games g
     INNER JOIN casino_aggregator_vendors v ON v.vendor_code = g.vendor_code
     WHERE g.is_active = 1 AND v.is_active = 1 AND (g.game_type = 2 OR {$liveMatch})";

foreach ([
    'sadece gsc' => $gscSelect,
    'sadece aggregator' => $aggSelect,
    'birlesik UNION' => '(' . $aggSelect . ') UNION ALL (' . $gscSelect . ')',
] as $label => $sql) {
    try {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM ({$sql}) AS catalog")->fetchColumn();
        printf("  %-20s %d satir\n", $label, $n);
    } catch (Throwable $e) {
        printf("  %-20s SQL HATASI: %s\n", $label, $e->getMessage());
    }
}

$result = LiveCasinoQuery::page('', [], 12, 1, '', ['force_local' => true]);
printf(
    "  LiveCasinoQuery::page -> total=%d, donen=%d, apiError=%s\n",
    (int) ($result['total'] ?? 0),
    count((array) ($result['games'] ?? [])),
    !empty($result['apiError']) ? 'true' : 'false'
);
$bySource = [];
foreach ((array) ($result['games'] ?? []) as $game) {
    $src = is_array($game) ? (string) ($game['source'] ?? '?') : '?';
    $bySource[$src] = ($bySource[$src] ?? 0) + 1;
}
echo '  source dagilimi: ' . json_encode($bySource) . "\n";

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

echo "\n== Cuzdan callback trafigi (gsc_wallet_logs, son 20) ==\n";
$stmt = $pdo->query(
    'SELECT method, member_account, http_status, status_code, error_code, created_at
     FROM gsc_wallet_logs
     ORDER BY id DESC LIMIT 20'
);
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
if ($rows === []) {
    echo "  KAYIT YOK -> saglayici callback URL'sine hic istek gelmemis.\n";
} else {
    printf("  %-14s %-18s %-5s %-6s %-14s %s\n", 'method', 'member', 'http', 'code', 'error', 'tarih');
    foreach ($rows as $row) {
        printf(
            "  %-14s %-18s %-5s %-6s %-14s %s\n",
            (string) $row['method'],
            (string) ($row['member_account'] ?? '-'),
            (string) $row['http_status'],
            (string) ($row['status_code'] ?? '-'),
            (string) ($row['error_code'] ?? '-'),
            (string) $row['created_at']
        );
    }
}

echo "\n  method + error_code dagilimi (son 24 saat):\n";
$stmt = $pdo->query(
    'SELECT method, COALESCE(error_code, "-") AS err, COUNT(*) AS n
     FROM gsc_wallet_logs
     WHERE created_at >= (NOW() - INTERVAL 1 DAY)
     GROUP BY method, err
     ORDER BY n DESC'
);
foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
    printf("    %-14s %-14s %d\n", (string) $row['method'], (string) $row['err'], (int) $row['n']);
}

echo "\n== Canli launch denemesi (aktif her canli urun icin 1 oyun) ==\n";
$userStmt = $pdo->query('SELECT id, username, balance, banned FROM users WHERE banned = 0 ORDER BY id DESC LIMIT 1');
$probeUser = $userStmt ? $userStmt->fetch(PDO::FETCH_ASSOC) : false;
if (!is_array($probeUser)) {
    echo "  Test icin uygun kullanici bulunamadi.\n";
} else {
    printf(
        "  kullanici: %s (id=%d, bakiye=%s) -> member_account=%s\n\n",
        (string) $probeUser['username'],
        (int) $probeUser['id'],
        (string) $probeUser['balance'],
        GscPlusService::memberAccountFromUser($probeUser)
    );

    $stmt = $pdo->query(
        "SELECT product_code, provider, MIN(game_code) AS game_code, MIN(game_name) AS game_name
         FROM gsc_games
         WHERE is_active = 1 AND UPPER(game_type) IN ('LIVE_CASINO','LIVE_CASINO_PREMIUM')
         GROUP BY product_code, provider
         ORDER BY product_code"
    );
    foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
        $gameId = GscPlusService::buildGameId((int) $row['product_code'], (string) $row['game_code']);
        try {
            $res = GscPlusService::launch($pdo, $probeUser, ['game_id' => $gameId, 'platform' => 'WEB']);
            $ok = !empty($res['success']);
            printf(
                "  %-22s %-30s %s code=%-5s %s\n",
                (string) $row['provider'],
                $gameId,
                $ok ? 'ACILDI ' : 'HATA   ',
                (string) ($res['code'] ?? '-'),
                $ok
                    ? ('url=' . (mb_strlen((string) ($res['game_url'] ?? '')) > 0 ? 'var' : 'YOK'))
                    : mb_substr((string) ($res['message'] ?? ''), 0, 90)
            );
        } catch (Throwable $e) {
            printf("  %-22s %-30s ISTISNA %s\n", (string) $row['provider'], $gameId, $e->getMessage());
        }
    }
}

echo "\n== Son basarisiz launch'lar (gsc_sessions, son 24 saat) ==\n";
try {
    $stmt = $pdo->query(
        "SELECT product_code, game_code, member_account, error_message, created_at
         FROM gsc_sessions
         WHERE status = 'error' AND created_at >= (NOW() - INTERVAL 1 DAY)
         ORDER BY id DESC LIMIT 20"
    );
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    if ($rows === []) {
        echo "  Kayit yok.\n";
    } else {
        foreach ($rows as $row) {
            printf(
                "  %-6s %-30s %-16s %-50s %s\n",
                (string) $row['product_code'],
                (string) ($row['game_code'] ?? '-'),
                (string) $row['member_account'],
                mb_substr((string) $row['error_message'], 0, 50),
                (string) $row['created_at']
            );
        }
    }
} catch (Throwable $e) {
    echo '  Sorgu hatasi (migration henuz calismamis olabilir): ' . $e->getMessage() . "\n";
}
