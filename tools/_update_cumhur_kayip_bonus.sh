#!/usr/bin/env bash
# Find Cumhur Tuğ and update loss bonus claim amount to 1500 TL
set -euo pipefail

php <<'PHP'
<?php
declare(strict_types=1);

$envCandidates = [
    '/www/wwwroot/admin.vegasroyalspin.com/.env',
    '/www/wwwroot/vegasroyalspin.com/admin/.env',
    '/www/wwwroot/vegasroyalspin.com/.env',
];
$env = [];
foreach ($envCandidates as $envFile) {
    if (!is_readable($envFile)) {
        continue;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ($v !== '' && ($v[0] === '"' || $v[0] === "'")) {
            $v = trim($v, "\"'");
        }
        $env[$k] = $v;
    }
    echo "ENV=$envFile\n";
    break;
}
if ($env === []) {
    fwrite(STDERR, "No .env found\n");
    exit(1);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '3306', $env['DB_DATABASE'] ?? ''),
    $env['DB_USERNAME'] ?? '',
    $env['DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$name = 'Cumhur Tuğ';
$targetAmount = 1500.00;

$userStmt = $pdo->prepare(
    "SELECT id, username, email, name, surname
     FROM users
     WHERE CONCAT(COALESCE(name,''), ' ', COALESCE(surname,'')) LIKE :name1
        OR name LIKE :name2
        OR surname LIKE :name3
        OR username LIKE :name4
     ORDER BY id DESC
     LIMIT 10"
);
$userStmt->execute([
    'name1' => '%Cumhur%Tuğ%',
    'name2' => '%Cumhur%',
    'name3' => '%Tuğ%',
    'name4' => '%cumhur%',
]);
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
if (!$users) {
    echo "USER_NOT_FOUND\n";
    exit(2);
}

echo "USERS=" . json_encode($users, JSON_UNESCAPED_UNICODE) . "\n";
$userId = (int) $users[0]['id'];

$claimsStmt = $pdo->prepare(
    "SELECT id, bonus_name, requested_amount, status, promotion_id, created_at
     FROM bonus_claim_requests
     WHERE user_id = :uid
     ORDER BY created_at DESC
     LIMIT 30"
);
$claimsStmt->execute(['uid' => $userId]);
$claims = $claimsStmt->fetchAll(PDO::FETCH_ASSOC);
echo "CLAIMS_BEFORE=" . json_encode($claims, JSON_UNESCAPED_UNICODE) . "\n";

$lossClaim = null;
foreach ($claims as $claim) {
    $bn = mb_strtolower((string) ($claim['bonus_name'] ?? ''), 'UTF-8');
    if (str_contains($bn, 'kayıp') || str_contains($bn, 'kayip') || str_contains($bn, 'loss') || str_contains($bn, 'iade')) {
        $lossClaim = $claim;
        break;
    }
}

if ($lossClaim === null && $claims !== []) {
    // Fallback: latest pending claim
    foreach ($claims as $claim) {
        if ((string) ($claim['status'] ?? '') === 'pending') {
            $lossClaim = $claim;
            break;
        }
    }
}

if ($lossClaim === null) {
    // Find loss promotion and create pending claim
    $promoStmt = $pdo->query(
        "SELECT id, title, category, bonus_type, bonus_amount, wagering_multiplier
         FROM promotions
         WHERE status = 'active'
           AND (LOWER(title) LIKE '%kayıp%' OR LOWER(title) LIKE '%kayip%' OR LOWER(type) = 'loss_bonus' OR LOWER(category) = 'loss_bonus')
         ORDER BY sort_order ASC, id ASC
         LIMIT 1"
    );
    $promo = $promoStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($promo)) {
        echo "NO_LOSS_PROMO\n";
        exit(3);
    }
    $ins = $pdo->prepare(
        "INSERT INTO bonus_claim_requests
         (user_id, promotion_id, bonus_name, category, promotion_type, requested_amount, wagering_multiplier, status, created_at)
         VALUES (:user_id, :promotion_id, :bonus_name, :category, :promotion_type, :requested_amount, :wagering_multiplier, 'pending', NOW())"
    );
    $ins->execute([
        'user_id' => $userId,
        'promotion_id' => (int) $promo['id'],
        'bonus_name' => (string) ($promo['title'] ?? 'Kayıp Bonusu'),
        'category' => (string) ($promo['type'] ?? 'loss_bonus'),
        'promotion_type' => (string) ($promo['bonus_type'] ?? ''),
        'requested_amount' => number_format($targetAmount, 2, '.', ''),
        'wagering_multiplier' => number_format((float) ($promo['wagering_multiplier'] ?? 1), 2, '.', ''),
    ]);
    $newId = (int) $pdo->lastInsertId();
    echo "CREATED_CLAIM id=$newId amount=$targetAmount\n";
} else {
    $claimId = (int) $lossClaim['id'];
    $upd = $pdo->prepare(
        "UPDATE bonus_claim_requests
         SET requested_amount = :amount, updated_at = NOW()
         WHERE id = :id AND user_id = :uid"
    );
    $upd->execute([
        'amount' => number_format($targetAmount, 2, '.', ''),
        'id' => $claimId,
        'uid' => $userId,
    ]);
    echo "UPDATED_CLAIM id=$claimId old=" . ($lossClaim['requested_amount'] ?? '') . " new=$targetAmount\n";
}

$claimsStmt->execute(['uid' => $userId]);
echo "CLAIMS_AFTER=" . json_encode($claimsStmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE) . "\n";
echo "DONE user_id=$userId amount=$targetAmount\n";
PHP
