<?php

declare(strict_types=1);

final class AdminRiskController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('deposits');
        $pdo = AdminDatabase::pdo();
        $number = static fn ($v): string => number_format((float) $v, 2, ',', '.');

        // High-risk players: multiple pending withdrawals
        $multiWithdraw = [];
        try {
            $multiWithdraw = $pdo->query(
                "SELECT t.user_id, t.username, t.fullname, COUNT(*) AS pending_count, SUM(t.amount) AS total_amount
                 FROM megapayz_transactions t
                 WHERE t.type = 'withdraw' AND t.status = 'pending'
                 GROUP BY t.user_id, t.username, t.fullname
                 HAVING COUNT(*) >= 2
                 ORDER BY total_amount DESC
                 LIMIT 20"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {}

        // High deposit volume players (top depositors)
        $highDepositors = [];
        try {
            $highDepositors = $pdo->query(
                "SELECT t.user_id, t.username, t.fullname, COUNT(*) AS tx_count, SUM(t.amount) AS total_deposited, MAX(t.amount) AS max_single
                 FROM megapayz_transactions t
                 WHERE t.type = 'deposit' AND t.status IN ('confirmed','approved')
                 GROUP BY t.user_id, t.username, t.fullname
                 ORDER BY total_deposited DESC
                 LIMIT 20"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {}

        // Frozen accounts
        $frozenAccounts = [];
        try {
            $frozenAccounts = $pdo->query(
                "SELECT id, username, name, surname, balance, bonus_balance, updated_at
                 FROM users WHERE banned = 1
                 ORDER BY updated_at DESC
                 LIMIT 20"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {}

        // KYC pending players with high balance
        $kycPendingHighBalance = [];
        try {
            $kycPendingHighBalance = $pdo->query(
                "SELECT u.id, u.username, u.name, u.surname, u.balance, k.submitted_at
                 FROM users u
                 INNER JOIN kyc_requests k ON k.user_id = u.id AND k.status = 'pending'
                 WHERE u.balance > 0
                 ORDER BY u.balance DESC
                 LIMIT 20"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {}

        $this->view('compliance/risk-analysis', [
            'title' => 'Risk Analizi',
            'active' => 'risk-analysis',
            'crumbs' => 'Uyum | Risk Analizi',
            'multiWithdraw' => $multiWithdraw,
            'highDepositors' => $highDepositors,
            'frozenAccounts' => $frozenAccounts,
            'kycPendingHighBalance' => $kycPendingHighBalance,
            'number' => $number,
        ]);
    }
}
