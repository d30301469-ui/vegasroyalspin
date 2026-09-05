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

echo "=== ALL AFFILIATES (name/code search) ===\n";
foreach ($pdo->query("SELECT id, full_name, email, referral_code, status, created_at FROM affiliates ORDER BY id") as $row) {
    $blob = strtolower(json_encode($row, JSON_UNESCAPED_UNICODE));
    if (str_contains($blob, 'kiv') || str_contains($blob, 'kıv') || str_contains($blob, 'kivan')) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\n=== Azem user search ===\n";
$az = $pdo->query("SELECT id, username, email, bonus_code, referred_by_affiliate_id, referred_by_user_id, created_at FROM users WHERE username LIKE '%Azem%' OR username LIKE '%azem%'")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($az, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

echo "\n=== Karabahtim clicks by IP ===\n";
$uid = 314;
$jwt = $pdo->prepare('SELECT ip_address, created_at FROM member_jwt_tokens WHERE user_id=:u ORDER BY id ASC LIMIT 1');
$jwt->execute(['u' => $uid]);
$ipRow = $jwt->fetch(PDO::FETCH_ASSOC);
echo json_encode($ipRow, JSON_UNESCAPED_UNICODE) . "\n";
if (is_array($ipRow) && !empty($ipRow['ip_address'])) {
    $cl = $pdo->prepare('SELECT c.*, a.full_name, a.referral_code FROM affiliate_clicks c JOIN affiliates a ON a.id=c.affiliate_id WHERE c.ip_address=:ip ORDER BY c.id DESC LIMIT 10');
    $cl->execute(['ip' => $ipRow['ip_address']]);
    echo json_encode($cl->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}

echo "\n=== Delirttiler07 clicks by IP ===\n";
$uid = 240;
$jwt->execute(['u' => $uid]);
$ipRow = $jwt->fetch(PDO::FETCH_ASSOC);
echo json_encode($ipRow, JSON_UNESCAPED_UNICODE) . "\n";
if (is_array($ipRow) && !empty($ipRow['ip_address'])) {
    $cl = $pdo->prepare('SELECT c.*, a.full_name, a.referral_code FROM affiliate_clicks c JOIN affiliates a ON a.id=c.affiliate_id WHERE c.ip_address=:ip ORDER BY c.id DESC LIMIT 10');
    $cl->execute(['ip' => $ipRow['ip_address']]);
    echo json_encode($cl->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}

echo "\n=== Recent affiliate_clicks sample ===\n";
echo json_encode($pdo->query('SELECT c.id,c.referral_code,c.ip_address,c.converted,c.converted_user_id,c.created_at,a.full_name FROM affiliate_clicks c LEFT JOIN affiliates a ON a.id=c.affiliate_id ORDER BY c.id DESC LIMIT 15')->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
