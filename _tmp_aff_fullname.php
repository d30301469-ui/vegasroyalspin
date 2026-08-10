<?php

declare(strict_types=1);

require __DIR__ . '/admin/app/bootstrap.php';

$pdo = AdminDatabase::pdo();

$aff = $pdo->query('SELECT id, full_name, email, user_id, payment_method, payment_details FROM affiliates WHERE id = 5')->fetch(PDO::FETCH_ASSOC);
echo "AFF\n" . json_encode($aff, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

$userId = (int) ($aff['user_id'] ?? 0);
if ($userId > 0) {
    $u = $pdo->prepare('SELECT id, username, name, surname FROM users WHERE id = :id');
    $u->execute(['id' => $userId]);
    echo "USER\n" . json_encode($u->fetch(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}

$rows = $pdo->query(
    "SELECT id, trx, status, fullname, username,
            LEFT(COALESCE(request_payload,''), 700) AS rp,
            LEFT(COALESCE(response_payload,''), 500) AS resp,
            LEFT(COALESCE(failure_message,''), 200) AS fm
     FROM megapayz_transactions
     WHERE id IN (26,27,28,29)
        OR (affiliate_payout_id = 5)
        OR (trx = 'A20260807180146BC54A2AA46')
     ORDER BY id DESC"
)->fetchAll(PDO::FETCH_ASSOC);
echo "TX\n" . json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

$ok = $pdo->query(
    "SELECT id, trx, fullname, username, status
     FROM megapayz_transactions
     WHERE type='withdraw' AND status IN ('approved','completed','confirmed','success')
     ORDER BY id DESC LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);
echo "OK_WITHDRAW\n" . json_encode($ok, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
