<?php
declare(strict_types=1);
$envFile = '/www/wwwroot/vegasroyalspin.com/admin/.env';
$e = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || ($line[0] ?? '') === '#' || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $e[trim($k)] = trim($v, " \t\"'");
}
$pdo = new PDO(
    'mysql:host=' . ($e['DB_HOST'] ?? '127.0.0.1') . ';dbname=' . $e['DB_DATABASE'] . ';charset=utf8mb4',
    $e['DB_USERNAME'] ?? '',
    $e['DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$checks = [];
$checks['users_wrong_affiliate_bonus_mismatch'] = (int) $pdo->query(
    "SELECT COUNT(*) FROM users u INNER JOIN affiliates a ON UPPER(a.referral_code)=UPPER(TRIM(u.bonus_code)) AND a.status='active'
     WHERE u.bonus_code IS NOT NULL AND TRIM(u.bonus_code)<>'' AND COALESCE(u.referred_by_affiliate_id,0)<>0 AND u.referred_by_affiliate_id<>a.id"
)->fetchColumn();
$checks['referred_to_inactive_affiliate'] = (int) $pdo->query(
    "SELECT COUNT(*) FROM users u LEFT JOIN affiliates a ON a.id=u.referred_by_affiliate_id
     WHERE COALESCE(u.referred_by_affiliate_id,0)<>0 AND (a.id IS NULL OR a.status<>'active')"
)->fetchColumn();
$checks['unconverted_clicks_last30d'] = (int) $pdo->query(
    "SELECT COUNT(*) FROM affiliate_clicks WHERE converted=0 AND created_at>=DATE_SUB(NOW(), INTERVAL 30 DAY)"
)->fetchColumn();
$checks['total_approved_commissions'] = (float) $pdo->query(
    "SELECT COALESCE(SUM(amount),0) FROM affiliate_commissions WHERE status='approved'"
)->fetchColumn();
$checks['total_pending_payouts'] = (float) $pdo->query(
    "SELECT COALESCE(SUM(amount),0) FROM affiliate_payouts WHERE status IN ('pending','approved','processing')"
)->fetchColumn();
$checks['affiliates_with_balance'] = $pdo->query(
    "SELECT id, referral_code, full_name, balance FROM affiliates WHERE status='active' AND balance>0 ORDER BY balance DESC LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($checks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
