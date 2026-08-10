<?php
/**
 * Affiliate Commission Calculator — Cron Job
 * Run daily: php /www/wwwroot/vegasroyalspin.com/affiliate-cron.php
 *
 * Thin CLI wrapper around AffiliateCommissionEngine:
 *  - RevShare: referred cashflow net × rate
 *  - CPA: first qualifying deposit (FTD), not registration day
 *  - Hybrid: both
 *  - Auto-assigns default plan to plan-less active affiliates
 *
 * Optional args:
 *   php affiliate-cron.php [YYYY-MM-DD] [YYYY-MM-DD] [--force] [--affiliate=ID]
 *   Defaults: yesterday → today (end exclusive)
 */

declare(strict_types=1);

$startTime = microtime(true);
$log = [];

try {
    $envFile = __DIR__ . '/admin/.env';
    if (!is_file($envFile)) {
        $envFile = __DIR__ . '/.env';
    }
    $env = is_file($envFile) ? parse_ini_file($envFile) : [];
    if ($env === false || $env === []) {
        throw new RuntimeException('.env not found');
    }

    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            (string) ($env['DB_HOST'] ?? '127.0.0.1'),
            (string) ($env['DB_PORT'] ?? '3306'),
            (string) ($env['DB_DATABASE'] ?? '')
        ),
        (string) ($env['DB_USERNAME'] ?? ''),
        (string) ($env['DB_PASSWORD'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $enginePath = __DIR__ . '/admin/services/AffiliateCommissionEngine.php';
    if (!is_file($enginePath)) {
        $enginePath = __DIR__ . '/services/AffiliateCommissionEngine.php';
    }
    require_once $enginePath;

    $periodStart = date('Y-m-d', strtotime('-1 day'));
    $periodEnd = date('Y-m-d');
    $force = false;
    $onlyAffiliateId = null;

    foreach (array_slice($argv ?? [], 1) as $arg) {
        $arg = trim((string) $arg);
        if ($arg === '--force') {
            $force = true;
            continue;
        }
        if (str_starts_with($arg, '--affiliate=')) {
            $onlyAffiliateId = max(0, (int) substr($arg, 12));
            continue;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $arg) === 1) {
            if ($periodStart === date('Y-m-d', strtotime('-1 day')) && !isset($customStart)) {
                $periodStart = $arg;
                $customStart = true;
            } else {
                $periodEnd = $arg;
            }
        }
    }

    $result = AffiliateCommissionEngine::processPeriod(
        $pdo,
        $periodStart,
        $periodEnd,
        $onlyAffiliateId > 0 ? $onlyAffiliateId : null,
        $force
    );

    foreach ((array) ($result['log'] ?? []) as $line) {
        $log[] = (string) $line;
    }
    $elapsed = round(microtime(true) - $startTime, 2);
    $log[] = sprintf(
        'Done. Affiliates: %d, Commissions: %d, Total: %s ₺ in %ss',
        (int) ($result['affiliates'] ?? 0),
        (int) ($result['processed'] ?? 0),
        number_format((float) ($result['total'] ?? 0), 2, '.', ''),
        $elapsed
    );
} catch (Throwable $e) {
    $log[] = 'ERROR: ' . $e->getMessage();
}

$logDir = __DIR__ . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
$logStr = '[' . date('Y-m-d H:i:s') . '] ' . implode("\n", $log);
@file_put_contents($logDir . '/affiliate-cron.log', $logStr . "\n", FILE_APPEND);
echo $logStr . "\n";
