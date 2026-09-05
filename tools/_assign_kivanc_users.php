<?php
declare(strict_types=1);

/**
 * Manuel ortak ataması + komisyon geri hesaplama.
 * CLI: php tools/_assign_kivanc_users.php [--apply]
 */
$apply = in_array('--apply', $argv ?? [], true);

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

$affiliateId = 5;
$affiliateCode = 'K7493';
$usernames = ['Karabahtim', 'Delirttiler07', 'Azem03'];

$aff = $pdo->prepare('SELECT id, full_name, referral_code, status FROM affiliates WHERE id = :id LIMIT 1');
$aff->execute(['id' => $affiliateId]);
$affRow = $aff->fetch(PDO::FETCH_ASSOC);
if (!is_array($affRow) || strtolower((string) ($affRow['status'] ?? '')) !== 'active') {
    fwrite(STDERR, "Affiliate #$affiliateId aktif değil.\n");
    exit(1);
}

$in = implode(',', array_fill(0, count($usernames), '?'));
$stmt = $pdo->prepare("SELECT id, username, referred_by_affiliate_id, bonus_code, created_at FROM users WHERE username IN ($in)");
$stmt->execute($usernames);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo 'Bulunan kullanıcı: ' . count($users) . '/' . count($usernames) . ($apply ? " (APPLY)\n" : " (dry-run)\n");
$foundNames = array_map(static fn ($u) => (string) $u['username'], $users);
foreach (array_diff($usernames, $foundNames) as $missing) {
    echo "EKSIK: {$missing} — sistemde kayıt yok\n";
}

$upd = $pdo->prepare(
    'UPDATE users
     SET referred_by_affiliate_id = :aid,
         bonus_code = COALESCE(NULLIF(TRIM(bonus_code), \'\'), :code)
     WHERE id = :id AND COALESCE(referred_by_affiliate_id, 0) = 0'
);

foreach ($users as $user) {
    $uid = (int) $user['id'];
    $line = sprintf('#%d %s affiliate=%s', $uid, (string) $user['username'], (string) ($user['referred_by_affiliate_id'] ?? 'null'));
    echo $line . "\n";
    if (!$apply || $uid <= 0) {
        continue;
    }
    $upd->execute(['aid' => $affiliateId, 'code' => $affiliateCode, 'id' => $uid]);
    if ($upd->rowCount() > 0) {
        AffiliateService::attributeRegistration($pdo, $uid, $affiliateCode, '');
        echo "  -> K7493 (#{$affiliateId}) atandı\n";
    } else {
        echo "  -> zaten atanmış\n";
    }
}

if ($apply) {
    AffiliateCommissionEngine::ensureSchema($pdo);
    $result = AffiliateCommissionEngine::processPeriod($pdo, '2026-07-01', '2027-01-01', $affiliateId, true);
    echo 'Komisyon processed=' . (int) ($result['processed'] ?? 0) . ' total=' . (float) ($result['total'] ?? 0) . "\n";
    foreach (array_slice((array) ($result['log'] ?? []), 0, 20) as $logLine) {
        echo '  ' . $logLine . "\n";
    }
    AffiliateService::reconcileBalance($pdo, $affiliateId);
    echo "DONE\n";
}
