<?php

declare(strict_types=1);

final class AdminRiskController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('compliance-risk');
        $pdo = AdminDatabase::pdo();
        $number = static fn ($v): string => number_format((float) $v, 2, ',', '.');

        admin_require_project_file('services/ComplianceService.php');
        ComplianceService::ensureTables($pdo);

        $multiWithdraw = [];
        try {
            $multiWithdraw = $pdo->query(
                "SELECT t.user_id,
                        MAX(t.username) AS username,
                        MAX(t.fullname) AS fullname,
                        MAX(NULLIF(TRIM(CONCAT(COALESCE(u.name,''),' ',COALESCE(u.surname,''))), '')) AS member_name,
                        COUNT(*) AS pending_count,
                        SUM(t.amount) AS total_amount
                 FROM megapayz_transactions t
                 LEFT JOIN users u ON u.id = t.user_id
                 WHERE t.type = 'withdraw' AND t.status = 'pending'
                 GROUP BY t.user_id
                 HAVING COUNT(*) >= 2
                 ORDER BY total_amount DESC
                 LIMIT 20"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
        }

        $highDepositors = [];
        try {
            $highDepositors = $pdo->query(
                "SELECT t.user_id,
                        MAX(t.username) AS username,
                        MAX(t.fullname) AS fullname,
                        MAX(NULLIF(TRIM(CONCAT(COALESCE(u.name,''),' ',COALESCE(u.surname,''))), '')) AS member_name,
                        COUNT(*) AS tx_count,
                        SUM(t.amount) AS total_deposited,
                        MAX(t.amount) AS max_single
                 FROM megapayz_transactions t
                 LEFT JOIN users u ON u.id = t.user_id
                 WHERE t.type = 'deposit' AND t.status IN ('confirmed','approved','success','completed')
                 GROUP BY t.user_id
                 ORDER BY total_deposited DESC
                 LIMIT 20"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
        }

        $frozenAccounts = [];
        try {
            $frozenAccounts = $pdo->query(
                "SELECT u.id, u.username, u.name, u.surname, u.balance, u.bonus_balance,
                        COALESCE(f.frozen_at, f.updated_at, u.updated_at) AS updated_at
                 FROM user_account_freeze f
                 INNER JOIN users u ON u.id = f.user_id
                 ORDER BY COALESCE(f.frozen_at, f.updated_at) DESC
                 LIMIT 20"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
        }

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
        } catch (Throwable) {
        }

        $riskLevels = ['clear' => 0, 'low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
        try {
            foreach ($pdo->query(
                'SELECT level, COUNT(*) AS c FROM user_risk_scores GROUP BY level'
            )->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $lvl = strtolower((string) ($row['level'] ?? ''));
                if (isset($riskLevels[$lvl])) {
                    $riskLevels[$lvl] = (int) ($row['c'] ?? 0);
                }
            }
        } catch (Throwable) {
        }

        $amlBundle = ComplianceService::chartBundle($pdo, 'aml_alerts');
        $riskBundle = ComplianceService::chartBundle($pdo, 'risk_alerts');

        $topWithdrawLabels = [];
        $topWithdrawData = [];
        foreach (array_slice($multiWithdraw, 0, 8) as $row) {
            $topWithdrawLabels[] = (string) ($row['member_name'] ?? $row['fullname'] ?? $row['username'] ?? ('#' . ($row['user_id'] ?? '')));
            $topWithdrawData[] = round((float) ($row['total_amount'] ?? 0), 2);
        }

        $topDepositLabels = [];
        $topDepositData = [];
        foreach (array_slice($highDepositors, 0, 8) as $row) {
            $topDepositLabels[] = (string) ($row['member_name'] ?? $row['fullname'] ?? $row['username'] ?? ('#' . ($row['user_id'] ?? '')));
            $topDepositData[] = round((float) ($row['total_deposited'] ?? 0), 2);
        }

        $chartData = [
            'signals' => [
                'labels' => ['Çoklu çekim', 'Yüksek yatırım', 'Dondurulmuş', 'KYC bekleyen'],
                'data' => [
                    count($multiWithdraw),
                    count($highDepositors),
                    count($frozenAccounts),
                    count($kycPendingHighBalance),
                ],
                'colors' => ['#ef4444', '#38bdf8', '#a855f7', '#f59e0b'],
            ],
            'risk_levels' => [
                'labels' => ['clear', 'low', 'medium', 'high', 'critical'],
                'data' => array_values($riskLevels),
                'colors' => ['#94a3b8', '#38bdf8', '#eab308', '#f97316', '#ef4444'],
            ],
            'top_withdraw' => [
                'labels' => $topWithdrawLabels,
                'data' => $topWithdrawData,
            ],
            'top_deposit' => [
                'labels' => $topDepositLabels,
                'data' => $topDepositData,
            ],
            'aml_trend' => $amlBundle['trend'],
            'risk_trend' => $riskBundle['trend'],
            'aml_open' => ComplianceService::summary($pdo, 'aml_alerts'),
            'risk_open' => ComplianceService::summary($pdo, 'risk_alerts'),
        ];

        $this->view('compliance/risk-analysis', [
            'title' => 'Risk Analizi',
            'active' => 'risk-analysis',
            'crumbs' => 'Uyum | Risk Analizi',
            'multiWithdraw' => $multiWithdraw,
            'highDepositors' => $highDepositors,
            'frozenAccounts' => $frozenAccounts,
            'kycPendingHighBalance' => $kycPendingHighBalance,
            'chartData' => $chartData,
            'number' => $number,
        ]);
    }
}
