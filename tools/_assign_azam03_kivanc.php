<?php
declare(strict_types=1);

$apply = in_array('--apply', $argv ?? [], true);
$username = 'Azam03';
$affiliateId = 5;
$affiliateCode = 'K7493';

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

require '/www/wwwroot/vegasroyalspin.com/shared/services/AffiliateService.php';
require '/www/wwwroot/vegasroyalspin.com/shared/services/AffiliateCommissionEngine.php';

$stmt = $pdo->prepare('SELECT id, username, referred_by_affiliate_id, bonus_code, created_at FROM users WHERE username = :u LIMIT 1');
$stmt->execute(['u' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!is_array($user)) {
    echo "NOT_FOUND\n";
    exit(1);
}

echo json_encode($user, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

$uid = (int) ($user['id'] ?? 0);
if (!$apply || $uid <= 0) {
    echo ($apply ? 'APPLY' : 'DRY-RUN') . " skipped\n";
    exit(0);
}

$upd = $pdo->prepare(
    'UPDATE users
     SET referred_by_affiliate_id = :aid,
         bonus_code = COALESCE(NULLIF(TRIM(bonus_code), \'\'), :code)
     WHERE id = :id AND COALESCE(referred_by_affiliate_id, 0) = 0'
);
$upd->execute(['aid' => $affiliateId, 'code' => $affiliateCode, 'id' => $uid]);
if ($upd->rowCount() > 0) {
    AffiliateService::attributeRegistration($pdo, $uid, $affiliateCode, '');
    echo "ASSIGNED affiliate=#{$affiliateId} code={$affiliateCode}\n";
} else {
    echo "ALREADY_ASSIGNED referred_by=" . (string) ($user['referred_by_affiliate_id'] ?? '') . "\n";
}

AffiliateCommissionEngine::ensureSchema($pdo);
$result = AffiliateCommissionEngine::processPeriod($pdo, '2026-07-01', '2027-01-01', $affiliateId, true);
echo 'COMMISSION processed=' . (int) ($result['processed'] ?? 0) . ' total=' . (float) ($result['total'] ?? 0) . "\n";
AffiliateService::reconcileBalance($pdo, $affiliateId);

$stmt->execute(['u' => $username]);
echo json_encode($stmt->fetch(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
echo "OK\n";
