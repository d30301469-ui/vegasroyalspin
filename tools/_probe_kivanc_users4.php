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
foreach (['185.43.229.221', '178.243.205.220'] as $ip) {
    $st = $pdo->prepare('SELECT id, affiliate_id, referral_code, converted, converted_user_id, created_at FROM affiliate_clicks WHERE ip_address=:ip AND affiliate_id=5 ORDER BY id DESC LIMIT 8');
    $st->execute(['ip' => $ip]);
    echo $ip . "\n" . json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
}
echo "Azem users:\n";
echo json_encode($pdo->query("SELECT id, username, referred_by_affiliate_id, created_at FROM users WHERE username LIKE '%Azem%' OR username LIKE '%azem%' OR username LIKE '%AZEM%' OR username LIKE '%zem03%'")->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
