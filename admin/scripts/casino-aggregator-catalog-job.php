<?php

declare(strict_types=1);

/**
 * CLI: rebuild or sync Casino Aggregator catalog without HTTP timeouts.
 *
 * Usage:
 *   php casino-aggregator-catalog-job.php rebuild
 *   php casino-aggregator-catalog-job.php sync-games
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

@ini_set('max_execution_time', '0');
@set_time_limit(0);
@ini_set('memory_limit', '512M');

if (!defined('APP_API_NO_SESSION')) {
    define('APP_API_NO_SESSION', true);
}

require_once dirname(__DIR__) . '/app/bootstrap_api.php';
require_once dirname(__DIR__) . '/services/CasinoAggregatorService.php';

$mode = strtolower(trim((string) ($argv[1] ?? 'rebuild')));
if (!in_array($mode, ['rebuild', 'sync-games'], true)) {
    $mode = 'rebuild';
}

$startedAt = date('c');
CasinoAggregatorService::writeCatalogJobStatus([
    'state' => 'running',
    'mode' => $mode,
    'started_at' => $startedAt,
    'finished_at' => null,
    'message' => $mode === 'sync-games'
        ? 'Oyun sync çalışıyor…'
        : 'Katalog silme + sync çalışıyor…',
    'result' => null,
    'pid' => getmypid(),
    'error' => null,
]);

try {
    $pdo = AdminDatabase::pdo();
    if ($mode === 'sync-games') {
        $result = CasinoAggregatorService::syncGames($pdo);
        foreach ([
            dirname(__DIR__) . '/../services/SlotGamesQuery.php',
            dirname(__DIR__) . '/services/SlotGamesQuery.php',
            (defined('APP_ROOT') ? rtrim((string) APP_ROOT, '/\\') . '/services/SlotGamesQuery.php' : ''),
        ] as $slotPath) {
            if ($slotPath !== '' && !class_exists('SlotGamesQuery', false) && is_file($slotPath)) {
                require_once $slotPath;
            }
        }
        if (class_exists('SlotGamesQuery', false) && method_exists('SlotGamesQuery', 'purgeCache')) {
            SlotGamesQuery::purgeCache();
        }
        $message = 'Oyun sync tamamlandı: '
            . (int) ($result['game_count'] ?? 0) . ' oyun, '
            . (int) ($result['vendor_count'] ?? 0) . ' vendor.';
    } else {
        $result = CasinoAggregatorService::rebuildCatalog($pdo);
        $message = 'Katalog sıfırdan sync edildi: '
            . (int) ($result['vendor_count'] ?? 0) . ' vendor, '
            . (int) ($result['game_count'] ?? 0) . ' oyun'
            . ' (silinen: ' . (int) ($result['vendors_deleted'] ?? 0) . ' vendor / '
            . (int) ($result['games_deleted'] ?? 0) . ' oyun).';
    }

    CasinoAggregatorService::writeCatalogJobStatus([
        'state' => 'completed',
        'mode' => $mode,
        'started_at' => $startedAt,
        'finished_at' => date('c'),
        'message' => $message,
        'result' => $result,
        'pid' => getmypid(),
        'error' => null,
    ]);
    echo $message . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    CasinoAggregatorService::writeCatalogJobStatus([
        'state' => 'failed',
        'mode' => $mode,
        'started_at' => $startedAt,
        'finished_at' => date('c'),
        'message' => 'Katalog işlemi başarısız: ' . $e->getMessage(),
        'result' => null,
        'pid' => getmypid(),
        'error' => $e->getMessage(),
    ]);
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
