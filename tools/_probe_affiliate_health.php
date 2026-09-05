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

$out = [];

$out['active_affiliates'] = (int) $pdo->query("SELECT COUNT(*) FROM affiliates WHERE status='active'")->fetchColumn();
$out['active_without_plan'] = (int) $pdo->query("SELECT COUNT(*) FROM affiliates WHERE status='active' AND COALESCE(commission_plan_id,0)=0")->fetchColumn();
$out['orphan_bonus_code_match'] = (int) $pdo->query(
    "SELECT COUNT(*) FROM users u INNER JOIN affiliates a ON UPPER(a.referral_code)=UPPER(TRIM(u.bonus_code)) AND a.status='active'
     WHERE u.bonus_code IS NOT NULL AND TRIM(u.bonus_code)<>'' AND COALESCE(u.referred_by_affiliate_id,0)=0"
)->fetchColumn();
$out['duplicate_revshare_periods'] = (int) $pdo->query(
    "SELECT COUNT(*) FROM (
      SELECT affiliate_id, period_start, period_end FROM affiliate_commissions
      WHERE commission_type='revshare' AND status<>'cancelled'
      GROUP BY affiliate_id, period_start, period_end HAVING COUNT(*)>1
    ) x"
)->fetchColumn();
$out['balance_mismatch'] = (int) $pdo->query(
    "SELECT COUNT(*) FROM (
      SELECT a.id,
        ABS(a.balance - GREATEST(0,
          COALESCE((SELECT SUM(amount) FROM affiliate_commissions WHERE affiliate_id=a.id AND status='approved'),0)
          - COALESCE((SELECT SUM(amount) FROM affiliate_payouts WHERE affiliate_id=a.id AND status IN ('pending','approved','processing')),0)
        )) AS diff
      FROM affiliates a WHERE a.status='active'
    ) z WHERE diff >= 0.01"
)->fetchColumn();

$out['referred_by_affiliate'] = $pdo->query(
    "SELECT a.id, a.referral_code, a.full_name,
            COUNT(u.id) AS referred_users
     FROM affiliates a
     LEFT JOIN users u ON u.referred_by_affiliate_id=a.id
     WHERE a.status='active'
     GROUP BY a.id, a.referral_code, a.full_name
     ORDER BY referred_users DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$out['cron_last'] = $pdo->query('SELECT * FROM affiliate_period_runs ORDER BY id DESC LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
