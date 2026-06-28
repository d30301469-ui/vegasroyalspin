<?php

declare(strict_types=1);

final class ComplianceMonitorService
{
    public static function evaluateWithdraw(PDO $pdo, int $userId, float $amount, string $trx, string $method = ''): void
    {
        if ($userId <= 0 || $amount <= 0) {
            return;
        }
        admin_require_project_file('services/ComplianceService.php');
        ComplianceService::ensureTables($pdo);

        $largeThreshold = (float) (getenv('COMPLIANCE_AML_WITHDRAW_THRESHOLD') ?: 25000);
        if ($amount >= $largeThreshold) {
            ComplianceService::createAmlAlert($pdo, [
                'user_id' => $userId,
                'rule_code' => 'large_withdraw',
                'severity' => $amount >= $largeThreshold * 2 ? 'critical' : 'high',
                'title' => 'Yüksek tutarlı çekim talebi',
                'description' => sprintf('%.2f TRY çekim talebi (eşik: %.2f).', $amount, $largeThreshold),
                'payload' => ['trx' => $trx, 'amount' => $amount, 'method' => $method],
            ]);
        }

        $rapidMin = (float) (getenv('COMPLIANCE_AML_RAPID_WITHDRAW_MIN') ?: 5000);
        $hours = max(1, (int) (getenv('COMPLIANCE_AML_DEPOSIT_WINDOW_HOURS') ?: 24));
        if ($amount >= $rapidMin && self::hasRecentConfirmedDeposit($pdo, $userId, $hours)) {
            ComplianceService::createAmlAlert($pdo, [
                'user_id' => $userId,
                'rule_code' => 'rapid_deposit_withdraw',
                'severity' => 'high',
                'title' => 'Hızlı yatırım sonrası çekim',
                'description' => sprintf('Son %d saatte yatırım sonrası %.2f TRY çekim.', $hours, $amount),
                'payload' => ['trx' => $trx, 'amount' => $amount, 'window_hours' => $hours],
            ]);
        }

        $pendingCount = self::countPendingWithdrawals($pdo, $userId);
        if ($pendingCount >= 2) {
            ComplianceService::createRiskAlert($pdo, [
                'user_id' => $userId,
                'alert_type' => 'multiple_pending_withdrawals',
                'severity' => $pendingCount >= 3 ? 'high' : 'medium',
                'title' => 'Çoklu bekleyen çekim',
                'description' => sprintf('%d bekleyen çekim talebi.', $pendingCount),
                'payload' => ['pending_count' => $pendingCount, 'latest_trx' => $trx],
            ]);
        }
    }

    private static function hasRecentConfirmedDeposit(PDO $pdo, int $userId, int $hours): bool
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM megapayz_transactions WHERE user_id = :user_id AND type = 'deposit'
                 AND status IN ('confirmed','success','completed')
                 AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)"
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':hours', $hours, PDO::PARAM_INT);
            $stmt->execute();

            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private static function countPendingWithdrawals(PDO $pdo, int $userId): int
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM megapayz_transactions WHERE user_id = :user_id AND type = 'withdraw' AND status = 'pending'"
            );
            $stmt->execute(['user_id' => $userId]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }
}
