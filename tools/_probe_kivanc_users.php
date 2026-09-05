<?php
declare(strict_types=1);

$usernames = ['Karabahtim', 'Azem03', 'Delirttiler07'];
$affiliateSearch = 'kivanc';

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

echo "=== KIVANC AFFILIATE ===\n";
$aff = $pdo->prepare("SELECT id, full_name, email, referral_code, status, user_id, created_at FROM affiliates WHERE LOWER(full_name) LIKE :q OR LOWER(referral_code) LIKE :q2 OR LOWER(email) LIKE :q3");
$aff->execute(['q' => '%' . strtolower($affiliateSearch) . '%', 'q2' => '%' . strtolower($affiliateSearch) . '%', 'q3' => '%' . strtolower($affiliateSearch) . '%']);
$affRows = $aff->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($affRows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

$affiliateId = (int) ($affRows[0]['id'] ?? 0);
$affiliateCode = (string) ($affRows[0]['referral_code'] ?? '');

echo "\n=== USERS ===\n";
$in = implode(',', array_fill(0, count($usernames), '?'));
$stmt = $pdo->prepare("SELECT id, username, email, bonus_code, referral_code, referred_by_affiliate_id, referred_by_user_id, created_at FROM users WHERE username IN ($in)");
$stmt->execute($usernames);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

$userIds = array_map(static fn ($u) => (int) $u['id'], $users);
if ($userIds !== []) {
    $inIds = implode(',', array_fill(0, count($userIds), '?'));
    echo "\n=== DEPOSITS ===\n";
    $dep = $pdo->prepare("SELECT id, user_id, amount, status, method, created_at FROM megapayz_transactions WHERE user_id IN ($inIds) AND type='deposit' ORDER BY id");
    $dep->execute($userIds);
    echo json_encode($dep->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

    echo "\n=== AFFILIATE COMMISSIONS ===\n";
    $com = $pdo->prepare("SELECT * FROM affiliate_commissions WHERE user_id IN ($inIds) ORDER BY id");
    $com->execute($userIds);
    echo json_encode($com->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}

if ($affiliateId > 0) {
    echo "\n=== RECENT CLICKS FOR KIVANC (last 30) ===\n";
    $cl = $pdo->prepare('SELECT id, referral_code, ip_address, converted, converted_user_id, created_at FROM affiliate_clicks WHERE affiliate_id = :aid ORDER BY id DESC LIMIT 30');
    $cl->execute(['aid' => $affiliateId]);
    echo json_encode($cl->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

    foreach ($users as $u) {
        $uid = (int) $u['id'];
        echo "\n=== IP CLICK MATCH user {$u['username']} (#$uid) ===\n";
        // find admin logs / jwt registration ip if any
        $jwt = $pdo->prepare('SELECT ip_address, user_agent, created_at FROM member_jwt_tokens WHERE user_id = :uid ORDER BY id ASC LIMIT 3');
        $jwt->execute(['uid' => $uid]);
        echo "first_jwt: " . json_encode($jwt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\n=== BONUS_CODE MATCH ORPHANS (kivanc code) ===\n";
if ($affiliateCode !== '') {
    $orph = $pdo->prepare("SELECT id, username, bonus_code, referred_by_affiliate_id FROM users WHERE username IN ($in) OR (UPPER(TRIM(bonus_code)) = UPPER(:code) AND COALESCE(referred_by_affiliate_id,0)=0)");
    $orph->execute(array_merge($usernames, ['code' => $affiliateCode]));
    echo json_encode($orph->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}
