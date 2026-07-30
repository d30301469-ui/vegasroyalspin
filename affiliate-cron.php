<?php
/**
 * Affiliate Commission Calculator — Cron Job
 * Run daily: php /www/wwwroot/vegasroyalspin.com/affiliate-cron.php
 *
 * Calculates:
 * 1. RevShare: % of referred users' net losses (deposits - withdrawals)
 * 2. CPA: one-time per qualifying deposit
 * 3. Hybrid: RevShare + CPA
 */

declare(strict_types=1);

$startTime = microtime(true);
$log = [];

try {
    $envFile = __DIR__ . '/admin/.env';
    $env = is_file($envFile) ? parse_ini_file($envFile) : [];
    if (empty($env)) throw new RuntimeException('.env not found');

    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};port=3306;dbname={$env['DB_DATABASE']};charset=utf8mb4",
        $env['DB_USERNAME'],
        $env['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $today = date('Y-m-d');
    $periodStart = date('Y-m-d', strtotime('-1 day')); // Yesterday
    $periodEnd = $today;

    $log[] = "Period: {$periodStart} to {$periodEnd}";

    // Get all active affiliates with their plans
    $affiliates = $pdo->query(
        "SELECT a.id, a.referral_code, a.commission_plan_id, cp.plan_type, cp.revshare_rate, cp.cpa_amount, cp.min_deposit
         FROM affiliates a
         LEFT JOIN affiliate_commission_plans cp ON a.commission_plan_id = cp.id
         WHERE a.status = 'active' AND cp.is_active = 1"
    )->fetchAll();

    $log[] = "Active affiliates: " . count($affiliates);

    $totalCommissions = 0;
    $processed = 0;

    foreach ($affiliates as $aff) {
        $affId = (int) $aff['id'];

        // Get referred users
        $users = $pdo->prepare(
            "SELECT id, username FROM users WHERE referred_by_affiliate_id = :aid"
        );
        $users->execute(['aid' => $affId]);
        $referredUsers = $users->fetchAll();

        if (empty($referredUsers)) continue;

        $userIdList = array_column($referredUsers, 'id');

        // Check if we already calculated commissions for this period (avoid duplicates)
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM affiliate_commissions
             WHERE affiliate_id = :aid AND period_start = :ps AND period_end = :pe AND source IN ('deposit', 'game_bet')"
        );
        $stmt->execute(['aid' => $affId, 'ps' => $periodStart, 'pe' => $periodEnd]);
        if ((int) $stmt->fetchColumn() > 0) {
            $log[] = "  Affiliate #{$affId}: already processed for this period, skipping.";
            continue;
        }

        $planType = $aff['plan_type'] ?? 'revshare';
        $revshareRate = (float) ($aff['revshare_rate'] ?? 0);
        $cpaAmount = (float) ($aff['cpa_amount'] ?? 0);
        $minDeposit = (float) ($aff['min_deposit'] ?? 0);

        // Build user IDs placeholder
        $placeholders = implode(',', array_fill(0, count($userIdList), '?'));

        if ($planType === 'revshare' || $planType === 'hybrid') {
            // Net gelir: depozitolar - çekimler (megapayz_transactions)
            // Deposits
            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions
                 WHERE user_id IN ({$placeholders})
                 AND type = 'deposit' AND status = 'completed'
                 AND created_at >= ? AND created_at < ?"
            );
            $params = array_merge($userIdList, [$periodStart . ' 00:00:00', $periodEnd . ' 00:00:00']);
            $stmt->execute($params);
            $totalDeposits = (float) $stmt->fetchColumn();

            // Withdrawals
            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions
                 WHERE user_id IN ({$placeholders})
                 AND type = 'withdraw' AND status = 'completed'
                 AND created_at >= ? AND created_at < ?"
            );
            $params = array_merge($userIdList, [$periodStart . ' 00:00:00', $periodEnd . ' 00:00:00']);
            $stmt->execute($params);
            $totalWithdrawals = (float) $stmt->fetchColumn();

            $netRevenue = $totalDeposits - $totalWithdrawals;

            if ($netRevenue > 0 && $revshareRate > 0) {
                $commissionAmount = round($netRevenue * ($revshareRate / 100), 2);
                if ($commissionAmount > 0) {
                    $pdo->prepare(
                        "INSERT INTO affiliate_commissions (affiliate_id, user_id, commission_type, amount, status, description, source, period_start, period_end)
                         VALUES (?, 0, 'revshare', ?, 'approved', CONCAT('RevShare %{$revshareRate} — Net: ', FORMAT(?, 2)), 'game_bet', ?, ?)"
                    )->execute([$affId, $commissionAmount, $netRevenue, $periodStart, $periodEnd]);

                    // Update balance
                    $pdo->prepare("UPDATE affiliates SET balance = balance + ?, total_earned = total_earned + ? WHERE id = ?")
                        ->execute([$commissionAmount, $commissionAmount, $affId]);

                    $totalCommissions += $commissionAmount;
                    $processed++;
                    $log[] = "  Affiliate #{$affId} RevShare: {$commissionAmount} ₺ (Deposits: {$totalDeposits}, Withdrawals: {$totalWithdrawals}, Net: {$netRevenue})";
                }
            }
        }

        if ($planType === 'cpa' || $planType === 'hybrid') {
            if ($cpaAmount > 0) {
                // Find new users in this period who deposited >= min_deposit
                $stmt = $pdo->prepare(
                    "SELECT u.id, u.username, MIN(t.amount) AS first_deposit
                     FROM users u
                     JOIN megapayz_transactions t ON u.id = t.user_id AND t.type = 'deposit' AND t.status = 'completed'
                     WHERE u.referred_by_affiliate_id = ?
                     AND u.created_at >= ? AND u.created_at < ?
                     AND t.amount >= ?
                     AND NOT EXISTS (SELECT 1 FROM affiliate_commissions ac WHERE ac.affiliate_id = ? AND ac.user_id = u.id AND ac.source = 'deposit')
                     GROUP BY u.id"
                );
                $stmt->execute([$affId, $periodStart . ' 00:00:00', $periodEnd . ' 00:00:00', $minDeposit, $affId]);
                $newDepositors = $stmt->fetchAll();

                foreach ($newDepositors as $u) {
                    $pdo->prepare(
                        "INSERT INTO affiliate_commissions (affiliate_id, user_id, commission_type, amount, status, description, source, period_start, period_end)
                         VALUES (?, ?, 'cpa', ?, 'approved', ?, 'deposit', ?, ?)"
                    )->execute([
                        $affId, $u['id'], $cpaAmount,
                        "CPA — {$u['username']} ilk depozito: " . number_format((float) $u['first_deposit'], 2),
                        $periodStart, $periodEnd
                    ]);

                    $pdo->prepare("UPDATE affiliates SET balance = balance + ?, total_earned = total_earned + ? WHERE id = ?")
                        ->execute([$cpaAmount, $cpaAmount, $affId]);

                    $totalCommissions += $cpaAmount;
                    $processed++;
                    $log[] = "  Affiliate #{$affId} CPA: {$cpaAmount} ₺ for user {$u['username']}";
                }
            }
        }
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    $log[] = "Done. Processed: {$processed} commissions, Total: " . number_format($totalCommissions, 2) . " ₺ in {$elapsed}s";

} catch (Throwable $e) {
    $log[] = 'ERROR: ' . $e->getMessage();
}

// Log output
$logStr = '[' . date('Y-m-d H:i:s') . '] ' . implode("\n", $log);
file_put_contents(__DIR__ . '/storage/logs/affiliate-cron.log', $logStr . "\n", FILE_APPEND);
echo $logStr . "\n";
