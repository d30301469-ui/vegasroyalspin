<?php
/**
 * AML / Risk batch tarama — Cron
 *
 * Öneri (her saat):
 *   0 * * * * /usr/bin/php /www/wwwroot/vegasroyalspin.com/compliance-cron.php >> .../storage/logs/compliance-cron.out 2>&1
 *
 * Çalıştırır:
 *  - shared identity / phone
 *  - KYC’siz yüksek bakiye
 *  - çekim hızı
 *  - dönemsel çekim/yatırım oranı
 *  - aktif üyelerde risk skoru yenileme
 */

declare(strict_types=1);

$startTime = microtime(true);
$log = [];

try {
    $envFile = __DIR__ . '/admin/.env';
    $env = is_file($envFile) ? parse_ini_file($envFile) : [];
    if (empty($env)) {
        throw new RuntimeException('.env not found');
    }

    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};port=3306;dbname={$env['DB_DATABASE']};charset=utf8mb4",
        $env['DB_USERNAME'],
        $env['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    date_default_timezone_set('Europe/Istanbul');
    require_once __DIR__ . '/services/ComplianceMonitorService.php';

    $summary = ComplianceMonitorService::runBatchScan($pdo);
    $log[] = 'Scanned users: ' . (int) ($summary['scanned'] ?? 0);
    $log[] = 'AML alerts created: ' . (int) ($summary['aml'] ?? 0);
    $log[] = 'Risk alerts created: ' . (int) ($summary['risk'] ?? 0);
    $log[] = 'Scores refreshed: ' . (int) ($summary['scored'] ?? 0);
    foreach (($summary['errors'] ?? []) as $err) {
        $log[] = 'ERR ' . $err;
    }

    $elapsed = round(microtime(true) - $startTime, 3);
    $log[] = "Done in {$elapsed}s";
    echo implode(PHP_EOL, $log) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[compliance-cron] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
