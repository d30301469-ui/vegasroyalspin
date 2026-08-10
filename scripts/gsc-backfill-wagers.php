<?php

declare(strict_types=1);

/**
 * Backfill gsc_wagers from gsc_transactions (money path) when upsert was missing.
 *
 *   php scripts/gsc-backfill-wagers.php
 */

$root = dirname(__DIR__);
require_once $root . '/admin/app/Core/AdminPaths.php';
admin_paths_bootstrap();
require_once $root . '/admin/app/Core/AdminDatabase.php';

$pdo = AdminDatabase::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$rows = $pdo->query(
    "SELECT member_account, wager_code, wager_status, wager_type, round_id, product_code, game_code,
            game_type, channel_code, currency, bet_amount, valid_bet_amount, prize_amount, tip_amount,
            settled_at, action, raw_payload, UNIX_TIMESTAMP(created_at) AS created_unix
     FROM gsc_transactions
     WHERE wager_code IS NOT NULL AND wager_code <> ''
     ORDER BY id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$upserted = 0;
$stmt = $pdo->prepare(
    "INSERT INTO gsc_wagers
        (member_account, wager_code, wager_status, wager_type, round_id, product_code, game_code,
         game_type, channel_code, currency, bet_amount, valid_bet_amount, prize_amount, tip_amount,
         settled_at, wager_created_at, payload, raw_payload)
     VALUES
        (:member, :code, :status, :type, :round, :product, :game, :gtype, :channel, :cur,
         :bet, :vbet, :prize, :tip, :settled, :created, NULL, :raw)
     ON DUPLICATE KEY UPDATE
        wager_status = VALUES(wager_status),
        wager_type = COALESCE(VALUES(wager_type), wager_type),
        round_id = COALESCE(VALUES(round_id), round_id),
        product_code = COALESCE(VALUES(product_code), product_code),
        game_code = COALESCE(VALUES(game_code), game_code),
        game_type = COALESCE(VALUES(game_type), game_type),
        channel_code = COALESCE(VALUES(channel_code), channel_code),
        currency = COALESCE(VALUES(currency), currency),
        bet_amount = IF(VALUES(bet_amount) > 0, VALUES(bet_amount), bet_amount),
        valid_bet_amount = IF(VALUES(valid_bet_amount) > 0, VALUES(valid_bet_amount), valid_bet_amount),
        prize_amount = IF(VALUES(prize_amount) > 0, VALUES(prize_amount), prize_amount),
        tip_amount = IF(VALUES(tip_amount) > 0, VALUES(tip_amount), tip_amount),
        settled_at = COALESCE(VALUES(settled_at), settled_at),
        raw_payload = VALUES(raw_payload)"
);

foreach ($rows as $row) {
    $action = strtoupper(trim((string) ($row['action'] ?? '')));
    $status = trim((string) ($row['wager_status'] ?? ''));
    if ($status === '') {
        $status = match ($action) {
            'BET', 'BET_PRESERVE' => 'BET',
            'CANCEL', 'ROLLBACK', 'PRESERVE_REFUND' => 'VOID',
            'RESETTLED' => 'RESETTLED',
            default => 'SETTLED',
        };
    }
    $settled = (int) ($row['settled_at'] ?? 0);
    if ($settled > 0 && $settled < 1_000_000_000_000) {
        $settled *= 1000;
    }
    if ($settled <= 0) {
        $settled = null;
    }
    $created = (int) ($row['created_unix'] ?? 0) * 1000;
    if ($created <= 0) {
        $created = (int) round(microtime(true) * 1000);
    }

    $stmt->execute([
        ':member' => (string) $row['member_account'],
        ':code' => (string) $row['wager_code'],
        ':status' => $status,
        ':type' => $row['wager_type'] !== null && $row['wager_type'] !== '' ? (string) $row['wager_type'] : null,
        ':round' => $row['round_id'] !== null && $row['round_id'] !== '' ? (string) $row['round_id'] : null,
        ':product' => $row['product_code'] !== null ? (int) $row['product_code'] : null,
        ':game' => $row['game_code'] !== null && $row['game_code'] !== '' ? (string) $row['game_code'] : null,
        ':gtype' => $row['game_type'] !== null && $row['game_type'] !== '' ? (string) $row['game_type'] : null,
        ':channel' => $row['channel_code'] !== null && $row['channel_code'] !== '' ? (string) $row['channel_code'] : null,
        ':cur' => (string) ($row['currency'] ?? 'IDR'),
        ':bet' => (float) ($row['bet_amount'] ?? 0),
        ':vbet' => (float) ($row['valid_bet_amount'] ?? 0),
        ':prize' => (float) ($row['prize_amount'] ?? 0),
        ':tip' => (float) ($row['tip_amount'] ?? 0),
        ':settled' => $settled,
        ':created' => $created,
        ':raw' => $row['raw_payload'] ?? null,
    ]);
    $upserted++;
}

$wagers = (int) $pdo->query('SELECT COUNT(*) FROM gsc_wagers')->fetchColumn();
echo json_encode([
    'transactions_with_wager' => count($rows),
    'upsert_calls' => $upserted,
    'gsc_wagers_total' => $wagers,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
