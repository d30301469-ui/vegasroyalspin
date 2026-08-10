<?php
/**
 * Sadakat cashback + haftalık seviye bonusu — Cron
 *
 * Çalıştırma (her Pazartesi 03:00 önerilir):
 *   php /www/wwwroot/vegasroyalspin.com/loyalty-cron.php
 *
 * Önceki takvim haftası (Pzt–Paz) için:
 *   1) Seviye cashback_rate % × net kayıp (bahis − kazanç) → bonus_balance
 *   2) Seviye weekly_bonus_amount (haftada en az 1 bahis varsa) → bonus_balance
 *
 * Tekrar çalıştırma güvenli (period+kind unique).
 *
 * Opsiyonel force dönem:
 *   php loyalty-cron.php --from=2026-07-21 --to=2026-07-27
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
    require_once __DIR__ . '/services/LoyaltyService.php';

    $period = null;
    $fromArg = null;
    $toArg = null;
    foreach ($argv ?? [] as $arg) {
        if (str_starts_with((string) $arg, '--from=')) {
            $fromArg = substr((string) $arg, 7);
        }
        if (str_starts_with((string) $arg, '--to=')) {
            $toArg = substr((string) $arg, 5);
        }
    }
    if ($fromArg && $toArg && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromArg) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toArg)) {
        $start = new DateTimeImmutable($fromArg . ' 00:00:00', new DateTimeZone('Europe/Istanbul'));
        $endExclusive = (new DateTimeImmutable($toArg . ' 00:00:00', new DateTimeZone('Europe/Istanbul')))->modify('+1 day');
        $period = [
            'start' => $start->format('Y-m-d'),
            'end' => $toArg,
            'start_dt' => $start->format('Y-m-d H:i:s'),
            'end_dt' => $endExclusive->format('Y-m-d H:i:s'),
        ];
    } else {
        $period = LoyaltyService::previousWeekPeriod();
    }

    $log[] = sprintf(
        'Period: %s → %s (%s .. %s)',
        $period['start'],
        $period['end'],
        $period['start_dt'],
        $period['end_dt']
    );

    $summary = LoyaltyService::processWeeklyPayouts($pdo, $period);
    $log[] = 'Scanned users: ' . (int) ($summary['scanned'] ?? 0);
    $log[] = 'Cashback paid: ' . (int) ($summary['cashback_paid'] ?? 0);
    $log[] = 'Weekly bonus paid: ' . (int) ($summary['weekly_paid'] ?? 0);
    $log[] = 'Skipped: ' . (int) ($summary['skipped'] ?? 0);
    $log[] = 'Total amount: ' . number_format((float) ($summary['total_amount'] ?? 0), 2, '.', '') . ' TRY';
    foreach (($summary['errors'] ?? []) as $err) {
        $log[] = 'ERR ' . $err;
    }

    $elapsed = round(microtime(true) - $startTime, 3);
    $log[] = "Done in {$elapsed}s";
    echo implode(PHP_EOL, $log) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[loyalty-cron] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
