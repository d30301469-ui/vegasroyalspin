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

$affiliateId = 5;
$affiliateCode = 'K7493';
$userIds = [240, 314];

echo "=== K7493 CLICKS (all time count) ===\n";
$st = $pdo->prepare('SELECT COUNT(*) c, SUM(converted) conv FROM affiliate_clicks WHERE affiliate_id=:aid');
$st->execute(['aid' => $affiliateId]);
echo json_encode($st->fetch(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== K7493 clicks around user registrations ===\n";
foreach ([240 => '2026-08-26', 314 => '2026-09-01'] as $uid => $day) {
    $q = $pdo->prepare("SELECT id, ip_address, converted, converted_user_id, created_at FROM affiliate_clicks WHERE affiliate_id=:aid AND DATE(created_at)=:d ORDER BY id");
    $q->execute(['aid' => $affiliateId, 'd' => $day]);
    echo "user#$uid day $day: " . json_encode($q->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== users detail ===\n";
$in = implode(',', $userIds);
echo json_encode($pdo->query("SELECT id, username, bonus_code, referred_by_affiliate_id, referred_by_user_id, created_at FROM users WHERE id IN ($in)")->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

echo "\n=== jwt cols ===\n";
echo json_encode($pdo->query('SHOW COLUMNS FROM member_jwt_tokens')->fetchAll(PDO::FETCH_COLUMN), JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== first login ips ===\n";
foreach ($userIds as $uid) {
    $q = $pdo->prepare('SELECT * FROM member_jwt_tokens WHERE user_id=:u ORDER BY id ASC LIMIT 2');
    $q->execute(['u' => $uid]);
    echo "user#$uid: " . json_encode($q->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== admin_logs register (if any) ===\n";
foreach (['Karabahtim', 'Delirttiler07', 'Azem03'] as $un) {
    $q = $pdo->prepare("SELECT id, action, details, created_at FROM admin_logs WHERE details LIKE :p ORDER BY id DESC LIMIT 3");
    $q->execute(['p' => '%' . $un . '%']);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) echo "$un: " . json_encode($rows, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== users with bonus K7493 ===\n";
echo json_encode($pdo->query("SELECT id, username, bonus_code, referred_by_affiliate_id, created_at FROM users WHERE UPPER(TRIM(bonus_code))='K7493' OR UPPER(TRIM(referral_code))='K7493'")->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

echo "\n=== Azem fuzzy ===\n";
echo json_encode($pdo->query("SELECT id, username, referred_by_affiliate_id, created_at FROM users WHERE username LIKE '%zem%' OR username LIKE '%AZEM%'")->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
