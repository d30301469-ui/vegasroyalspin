<?php

declare(strict_types=1);

final class AdminReportController extends AdminController
{
    public function calendar(): void
    {
        $this->requirePermission('dashboard');
        $this->view('reports/calendar', [
            'title' => 'Operasyon Takvimi',
            'active' => 'reports-calendar',
            'crumbs' => 'Raporlar | Operasyon Takvimi | ' . date('F Y'),
            'events' => $this->events(),
        ]);
    }

    public function charts(): void
    {
        $this->requirePermission('dashboard');
        MegaPayzService::bootstrap(AdminDatabase::pdo());
        $this->view('reports/charts', [
            'title' => 'Grafikler',
            'active' => 'reports-charts',
            'crumbs' => 'Raporlar | Grafikler',
            'stats' => [
                'users' => $this->scalar('SELECT COUNT(*) FROM users'),
                'deposits' => $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE type = 'deposit' AND status = 'confirmed'"),
                'withdrawals' => $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE type = 'withdraw'"),
                'games' => $this->scalar('SELECT (SELECT COUNT(*) FROM drakon_games WHERE is_active = 1) + (SELECT COUNT(*) FROM bgaming_games WHERE is_active = 1)'),
                'visits' => $this->scalar('SELECT COUNT(*) FROM visitor_logs'),
            ],
            'dailyVisits' => $this->series('visitor_logs', 'created_at'),
            'dailyDeposits' => $this->amountSeries('megapayz_transactions', 'created_at', 'amount', "type = 'deposit'"),
        ]);
    }

    public function financial(): void
    {
        $this->requirePermission('deposits');
        MegaPayzService::bootstrap(AdminDatabase::pdo());

        $from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
        $to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
        $groupBy = in_array(trim((string) ($_GET['group_by'] ?? 'day')), ['day', 'week', 'month'], true)
            ? trim((string) ($_GET['group_by'] ?? 'day')) : 'day';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !strtotime($from)) {
            $from = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || !strtotime($to)) {
            $to = date('Y-m-d');
        }
        if ($from > $to) {
            $from = $to;
        }

        $dateExpr = match ($groupBy) {
            'week' => "DATE_FORMAT(created_at, '%Y-%u')",
            'month' => "DATE_FORMAT(created_at, '%Y-%m')",
            default => 'DATE(created_at)',
        };
        $fromDt = $from . ' 00:00:00';
        $toDt = $to . ' 23:59:59';
        $pdo = AdminDatabase::pdo();
        $rows = [];
        $summary = ['total_deposits' => 0, 'total_withdrawals' => 0, 'net_revenue' => 0];

        try {
            $stmt = $pdo->prepare(
                "SELECT $dateExpr AS period,
                    SUM(CASE WHEN type='deposit'  AND status IN ('confirmed','success','completed') THEN amount ELSE 0 END) AS deposits,
                    SUM(CASE WHEN type='withdraw' AND status IN ('confirmed','success','completed') THEN amount ELSE 0 END) AS withdrawals,
                    SUM(CASE WHEN type='deposit'  AND status IN ('confirmed','success','completed') THEN amount
                             WHEN type='withdraw' AND status IN ('confirmed','success','completed') THEN -amount ELSE 0 END) AS net,
                    COUNT(CASE WHEN type='deposit'  THEN 1 END) AS deposit_count,
                    COUNT(CASE WHEN type='withdraw' THEN 1 END) AS withdrawal_count
                 FROM megapayz_transactions
                 WHERE created_at BETWEEN :from AND :to
                 GROUP BY period ORDER BY period ASC"
            );
            $stmt->execute(['from' => $fromDt, 'to' => $toDt]);
            $rows = $stmt->fetchAll();

            $totStmt = $pdo->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN type='deposit'  AND status IN ('confirmed','success','completed') THEN amount ELSE 0 END), 0) AS total_deposits,
                    COALESCE(SUM(CASE WHEN type='withdraw' AND status IN ('confirmed','success','completed') THEN amount ELSE 0 END), 0) AS total_withdrawals
                 FROM megapayz_transactions WHERE created_at BETWEEN :from AND :to"
            );
            $totStmt->execute(['from' => $fromDt, 'to' => $toDt]);
            $totRow = $totStmt->fetch() ?: [];
            $summary = [
                'total_deposits' => (float) ($totRow['total_deposits'] ?? 0),
                'total_withdrawals' => (float) ($totRow['total_withdrawals'] ?? 0),
                'net_revenue' => (float) ($totRow['total_deposits'] ?? 0) - (float) ($totRow['total_withdrawals'] ?? 0),
            ];
        } catch (Throwable) {
        }

        $this->view('reports/financial', [
            'title' => 'Finansal Raporlar',
            'active' => 'reports-financial',
            'crumbs' => 'Raporlar | Finansal',
            'rows' => $rows,
            'summary' => $summary,
            'from' => $from,
            'to' => $to,
            'groupBy' => $groupBy,
        ]);
    }

    private function events(): array
    {
        $events = [];
        $queries = [
            "SELECT title, start_date AS starts_at, end_date AS ends_at, 'Promosyon' AS kind FROM promotions WHERE start_date IS NOT NULL",
            "SELECT title, start_date AS starts_at, end_date AS ends_at, 'Duyuru' AS kind FROM announcements WHERE start_date IS NOT NULL",
            "SELECT CONCAT('KYC #', id) AS title, submitted_at AS starts_at, reviewed_at AS ends_at, status AS kind FROM kyc_requests WHERE submitted_at IS NOT NULL",
            "SELECT CONCAT('Yatırım ', username) AS title, created_at AS starts_at, updated_at AS ends_at, status AS kind FROM megapayz_transactions WHERE type = 'deposit' ORDER BY id DESC LIMIT 20",
        ];
        foreach ($queries as $sql) {
            try {
                $stmt = AdminDatabase::pdo()->query($sql);
                foreach ($stmt->fetchAll() as $row) {
                    $events[] = $row;
                }
            } catch (Throwable) {
            }
        }

        return $events;
    }

    private function scalar(string $sql): float
    {
        try {
            return (float) AdminDatabase::pdo()->query($sql)->fetchColumn();
        } catch (Throwable) {
            return 0.0;
        }
    }

    private function series(string $table, string $dateColumn): array
    {
        try {
            $stmt = AdminDatabase::pdo()->query(
                'SELECT DATE(`' . $dateColumn . '`) AS day, COUNT(*) AS total FROM `' . $table . '` GROUP BY DATE(`' . $dateColumn . '`) ORDER BY day DESC LIMIT 14'
            );

            return array_reverse($stmt->fetchAll());
        } catch (Throwable) {
            return [];
        }
    }

    private function amountSeries(string $table, string $dateColumn, string $amountColumn, string $where = ''): array
    {
        try {
            $whereSql = trim($where) !== '' ? ' WHERE ' . $where : '';
            $stmt = AdminDatabase::pdo()->query(
                'SELECT DATE(`' . $dateColumn . '`) AS day, COALESCE(SUM(`' . $amountColumn . '`), 0) AS total FROM `' . $table . '`' . $whereSql . ' GROUP BY DATE(`' . $dateColumn . '`) ORDER BY day DESC LIMIT 14'
            );

            return array_reverse($stmt->fetchAll());
        } catch (Throwable) {
            return [];
        }
    }
}
