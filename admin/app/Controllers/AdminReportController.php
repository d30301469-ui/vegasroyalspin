<?php

declare(strict_types=1);

final class AdminReportController extends AdminController
{
    private string $lastQueryError = '';

    public function calendar(): void
    {
        $this->requirePermission('dashboard');

        $monthRaw = trim((string) ($_GET['month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $monthRaw) || !strtotime($monthRaw . '-01')) {
            $monthRaw = date('Y-m');
        }
        $monthStart = $monthRaw . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $monthTs = strtotime($monthStart) ?: time();
        $prevMonth = date('Y-m', strtotime('-1 month', $monthTs));
        $nextMonth = date('Y-m', strtotime('+1 month', $monthTs));

        $filter = strtolower(trim((string) ($_GET['kind'] ?? 'all')));
        $allowedKinds = ['all', 'promosyon', 'duyuru', 'kyc', 'yatirim', 'cekim'];
        if (!in_array($filter, $allowedKinds, true)) {
            $filter = 'all';
        }

        $events = $this->events($monthStart, $monthEnd);
        $counts = [
            'all' => 0,
            'promosyon' => 0,
            'duyuru' => 0,
            'kyc' => 0,
            'yatirim' => 0,
            'cekim' => 0,
        ];
        $monthEvents = [];
        foreach ($events as $event) {
            $startTs = strtotime((string) ($event['starts_at'] ?? ''));
            if ($startTs === false) {
                continue;
            }
            $endTs = strtotime((string) ($event['ends_at'] ?? ''));
            $kindKey = (string) ($event['kind_key'] ?? '');
            $startDay = date('Y-m-d', $startTs);
            $endDay = $endTs !== false ? date('Y-m-d', $endTs) : $startDay;

            // Multi-day content (promo/announcement) counts if it overlaps the month.
            $overlapsMonth = $startDay <= $monthEnd && $endDay >= $monthStart;
            $startsInMonth = $startDay >= $monthStart && $startDay <= $monthEnd;
            if (in_array($kindKey, ['promosyon', 'duyuru'], true)) {
                if (!$overlapsMonth) {
                    continue;
                }
                // Pin display day inside the visible month.
                $displayDay = max($startDay, $monthStart);
                $event['day'] = $displayDay;
                $event['starts_at'] = $displayDay . ' ' . date('H:i:s', $startTs);
            } elseif (!$startsInMonth) {
                continue;
            }

            if (isset($counts[$kindKey])) {
                $counts[$kindKey]++;
            }
            $counts['all']++;

            if ($filter !== 'all' && $kindKey !== $filter) {
                continue;
            }
            $monthEvents[] = $event;
        }

        usort(
            $monthEvents,
            static function (array $left, array $right): int {
                return strcmp((string) ($left['starts_at'] ?? ''), (string) ($right['starts_at'] ?? ''));
            }
        );

        $byDay = [];
        foreach ($monthEvents as $event) {
            $day = (string) ($event['day'] ?? '');
            if ($day === '') {
                continue;
            }
            $byDay[$day][] = $event;
        }

        $this->view('reports/calendar', [
            'title' => 'Operasyon Takvimi',
            'active' => 'reports-calendar',
            'crumbs' => 'Raporlar | Operasyon Takvimi | ' . date('F Y', $monthTs),
            'events' => $monthEvents,
            'eventsByDay' => $byDay,
            'counts' => $counts,
            'filter' => $filter,
            'month' => $monthRaw,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'monthLabel' => $this->turkishMonthLabel($monthTs),
            'today' => date('Y-m-d'),
        ]);
    }

    public function charts(): void
    {
        $this->requirePermission('dashboard');
        MegaPayzService::bootstrap(AdminDatabase::pdo());
        $this->lastQueryError = '';

        $stats = [
            'users' => $this->scalar('SELECT COUNT(*) FROM users'),
            'deposits' => $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE type = 'deposit' AND status IN ('confirmed','approved','success','completed')"),
            'withdrawals' => $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE type = 'withdraw' AND status IN ('confirmed','approved','success','completed')"),
            'games' => $this->scalar('SELECT COUNT(*) FROM bgaming_games WHERE is_active = 1')
                + $this->scalar('SELECT COUNT(*) FROM casino_aggregator_games WHERE is_active = 1')
                + $this->scalar('SELECT COUNT(*) FROM gsc_games WHERE is_active = 1'),
            'visits' => $this->scalar('SELECT COUNT(*) FROM visitor_logs'),
            'bgaming_games' => $this->scalar('SELECT COUNT(*) FROM bgaming_games WHERE is_active = 1'),
            'aggregator_games' => $this->scalar('SELECT COUNT(*) FROM casino_aggregator_games WHERE is_active = 1'),
            'gsc_games' => $this->scalar('SELECT COUNT(*) FROM gsc_games WHERE is_active = 1'),
        ];

        $dailyVisits = $this->series('visitor_logs', 'created_at');
        $dailyDeposits = $this->amountSeries(
            'megapayz_transactions',
            'created_at',
            'amount',
            "type = 'deposit' AND status IN ('confirmed','approved','success','completed')"
        );
        $dailyWithdrawals = $this->amountSeries(
            'megapayz_transactions',
            'created_at',
            'amount',
            "type = 'withdraw' AND status IN ('confirmed','approved','success','completed')"
        );
        $dailyUsers = $this->series('users', 'created_at');
        $queryError = $this->lastQueryError !== ''
            ? 'Bazı grafik verileri yüklenemedi. Şema/tabloları kontrol edin.'
            : '';

        $visitLabels = [];
        $visitValues = [];
        foreach ($dailyVisits as $row) {
            $visitLabels[] = (string) ($row['day'] ?? '');
            $visitValues[] = (int) ($row['total'] ?? 0);
        }

        $financeMap = [];
        foreach ($dailyDeposits as $row) {
            $day = (string) ($row['day'] ?? '');
            if ($day === '') {
                continue;
            }
            $financeMap[$day] = [
                'day' => $day,
                'deposits' => round((float) ($row['total'] ?? 0), 2),
                'withdrawals' => 0.0,
            ];
        }
        foreach ($dailyWithdrawals as $row) {
            $day = (string) ($row['day'] ?? '');
            if ($day === '') {
                continue;
            }
            if (!isset($financeMap[$day])) {
                $financeMap[$day] = ['day' => $day, 'deposits' => 0.0, 'withdrawals' => 0.0];
            }
            $financeMap[$day]['withdrawals'] = round((float) ($row['total'] ?? 0), 2);
        }
        ksort($financeMap);
        $financeRows = array_values($financeMap);
        $financeLabels = [];
        $financeDeposits = [];
        $financeWithdrawals = [];
        $financeNet = [];
        foreach ($financeRows as $row) {
            $financeLabels[] = (string) $row['day'];
            $financeDeposits[] = (float) $row['deposits'];
            $financeWithdrawals[] = (float) $row['withdrawals'];
            $financeNet[] = round((float) $row['deposits'] - (float) $row['withdrawals'], 2);
        }

        $userLabels = [];
        $userValues = [];
        foreach ($dailyUsers as $row) {
            $userLabels[] = (string) ($row['day'] ?? '');
            $userValues[] = (int) ($row['total'] ?? 0);
        }

        $chartData = [
            'visits' => [
                'labels' => $visitLabels,
                'data' => $visitValues,
            ],
            'finance' => [
                'labels' => $financeLabels,
                'deposits' => $financeDeposits,
                'withdrawals' => $financeWithdrawals,
                'net' => $financeNet,
            ],
            'users' => [
                'labels' => $userLabels,
                'data' => $userValues,
            ],
            'share' => [
                'labels' => ['Yatırım', 'Çekim'],
                'data' => [
                    round((float) $stats['deposits'], 2),
                    round((float) $stats['withdrawals'], 2),
                ],
                'colors' => ['rgba(34,197,94,.88)', 'rgba(239,68,68,.88)'],
            ],
            'games' => [
                'labels' => ['BGaming', 'Aggregator', 'GSC+'],
                'data' => [
                    (int) $stats['bgaming_games'],
                    (int) $stats['aggregator_games'],
                    (int) $stats['gsc_games'],
                ],
                'colors' => ['rgba(59,130,246,.88)', 'rgba(14,165,233,.88)', 'rgba(168,85,247,.88)'],
            ],
            'overview' => [
                'labels' => ['Üyeler', 'Ziyaret', 'Aktif oyun'],
                'data' => [
                    (int) $stats['users'],
                    (int) $stats['visits'],
                    (int) $stats['games'],
                ],
                'colors' => ['rgba(99,102,241,.88)', 'rgba(56,189,248,.88)', 'rgba(245,158,11,.88)'],
            ],
        ];

        $this->view('reports/charts', [
            'title' => 'Grafikler',
            'active' => 'reports-charts',
            'crumbs' => 'Raporlar | Grafikler',
            'stats' => $stats,
            'dailyVisits' => $dailyVisits,
            'dailyDeposits' => $dailyDeposits,
            'chartData' => $chartData,
            'queryError' => $queryError,
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
        $queryError = '';

        try {
            $stmt = $pdo->prepare(
                "SELECT $dateExpr AS period,
                    SUM(CASE WHEN type='deposit'  AND status IN ('confirmed','approved','success','completed') THEN amount ELSE 0 END) AS deposits,
                    SUM(CASE WHEN type='withdraw' AND status IN ('confirmed','approved','success','completed') THEN amount ELSE 0 END) AS withdrawals,
                    SUM(CASE WHEN type='deposit'  AND status IN ('confirmed','approved','success','completed') THEN amount
                             WHEN type='withdraw' AND status IN ('confirmed','approved','success','completed') THEN -amount ELSE 0 END) AS net,
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
                    COALESCE(SUM(CASE WHEN type='deposit'  AND status IN ('confirmed','approved','success','completed') THEN amount ELSE 0 END), 0) AS total_deposits,
                    COALESCE(SUM(CASE WHEN type='withdraw' AND status IN ('confirmed','approved','success','completed') THEN amount ELSE 0 END), 0) AS total_withdrawals
                 FROM megapayz_transactions WHERE created_at BETWEEN :from AND :to"
            );
            $totStmt->execute(['from' => $fromDt, 'to' => $toDt]);
            $totRow = $totStmt->fetch() ?: [];
            $summary = [
                'total_deposits' => (float) ($totRow['total_deposits'] ?? 0),
                'total_withdrawals' => (float) ($totRow['total_withdrawals'] ?? 0),
                'net_revenue' => (float) ($totRow['total_deposits'] ?? 0) - (float) ($totRow['total_withdrawals'] ?? 0),
            ];
        } catch (Throwable $exception) {
            error_log('[AdminReportController] financial query failed: ' . $exception->getMessage());
            $queryError = 'Finansal rapor verileri yüklenemedi. megapayz_transactions tablosunu kontrol edin.';
        }

        $labels = [];
        $depositSeries = [];
        $withdrawSeries = [];
        $netSeries = [];
        $depositCountSeries = [];
        $withdrawCountSeries = [];
        foreach ($rows as $row) {
            $labels[] = (string) ($row['period'] ?? '');
            $depositSeries[] = round((float) ($row['deposits'] ?? 0), 2);
            $withdrawSeries[] = round((float) ($row['withdrawals'] ?? 0), 2);
            $netSeries[] = round((float) ($row['net'] ?? 0), 2);
            $depositCountSeries[] = (int) ($row['deposit_count'] ?? 0);
            $withdrawCountSeries[] = (int) ($row['withdrawal_count'] ?? 0);
        }

        $chartData = [
            'trend' => [
                'labels' => $labels,
                'deposits' => $depositSeries,
                'withdrawals' => $withdrawSeries,
                'net' => $netSeries,
            ],
            'counts' => [
                'labels' => $labels,
                'deposits' => $depositCountSeries,
                'withdrawals' => $withdrawCountSeries,
            ],
            'share' => [
                'labels' => ['Yatırım', 'Çekim'],
                'data' => [
                    round((float) ($summary['total_deposits'] ?? 0), 2),
                    round((float) ($summary['total_withdrawals'] ?? 0), 2),
                ],
                'colors' => ['rgba(34,197,94,.88)', 'rgba(239,68,68,.88)'],
            ],
        ];

        $this->view('reports/financial', [
            'title' => 'Finansal Raporlar',
            'active' => 'reports-financial',
            'crumbs' => 'Raporlar | Finansal',
            'rows' => $rows,
            'summary' => $summary,
            'chartData' => $chartData,
            'from' => $from,
            'to' => $to,
            'groupBy' => $groupBy,
            'queryError' => $queryError,
        ]);
    }

    private function events(string $monthStart, string $monthEnd): array
    {
        MegaPayzService::bootstrap(AdminDatabase::pdo());
        $events = [];
        $rangeStart = $monthStart . ' 00:00:00';
        $rangeEnd = $monthEnd . ' 23:59:59';
        $queries = [
            [
                'sql' => "SELECT id, title, start_date AS starts_at, end_date AS ends_at
                          FROM promotions
                          WHERE start_date IS NOT NULL
                            AND start_date <= :range_end
                            AND (end_date IS NULL OR end_date >= :range_start)
                          ORDER BY start_date DESC
                          LIMIT 500",
                'kind' => 'Promosyon',
                'kind_key' => 'promosyon',
                'color' => '#8b5cf6',
                'bind' => true,
            ],
            [
                'sql' => "SELECT id, title, start_date AS starts_at, end_date AS ends_at
                          FROM announcements
                          WHERE start_date IS NOT NULL
                            AND start_date <= :range_end
                            AND (end_date IS NULL OR end_date >= :range_start)
                          ORDER BY start_date DESC
                          LIMIT 500",
                'kind' => 'Duyuru',
                'kind_key' => 'duyuru',
                'color' => '#3b82f6',
                'bind' => true,
            ],
            [
                'sql' => "SELECT id,
                                 CONCAT('KYC #', id) AS title,
                                 submitted_at AS starts_at,
                                 reviewed_at AS ends_at,
                                 status AS detail
                          FROM kyc_requests
                          WHERE submitted_at IS NOT NULL
                            AND submitted_at BETWEEN :range_start AND :range_end
                          ORDER BY submitted_at DESC
                          LIMIT 500",
                'kind' => 'KYC',
                'kind_key' => 'kyc',
                'color' => '#22c55e',
                'bind' => true,
            ],
            [
                'sql' => "SELECT id,
                                 CONCAT('Yatırım · ', COALESCE(NULLIF(username, ''), CONCAT('#', user_id))) AS title,
                                 created_at AS starts_at,
                                 updated_at AS ends_at,
                                 status,
                                 amount
                          FROM megapayz_transactions
                          WHERE type = 'deposit'
                            AND created_at BETWEEN :range_start AND :range_end
                          ORDER BY id DESC
                          LIMIT 500",
                'kind' => 'Yatırım',
                'kind_key' => 'yatirim',
                'color' => '#16a34a',
                'bind' => true,
            ],
            [
                'sql' => "SELECT id,
                                 CONCAT('Çekim · ', COALESCE(NULLIF(username, ''), CONCAT('#', user_id))) AS title,
                                 created_at AS starts_at,
                                 updated_at AS ends_at,
                                 status,
                                 amount
                          FROM megapayz_transactions
                          WHERE type = 'withdraw'
                            AND created_at BETWEEN :range_start AND :range_end
                          ORDER BY id DESC
                          LIMIT 500",
                'kind' => 'Çekim',
                'kind_key' => 'cekim',
                'color' => '#ef4444',
                'bind' => true,
            ],
        ];

        foreach ($queries as $query) {
            try {
                $stmt = AdminDatabase::pdo()->prepare($query['sql']);
                $stmt->execute([
                    'range_start' => $rangeStart,
                    'range_end' => $rangeEnd,
                ]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $startRaw = (string) ($row['starts_at'] ?? '');
                    $startTs = strtotime($startRaw);
                    if ($startTs === false) {
                        continue;
                    }
                    $endRaw = (string) ($row['ends_at'] ?? '');
                    $endTs = $endRaw !== '' ? strtotime($endRaw) : false;
                    $detail = trim((string) ($row['detail'] ?? ''));
                    if ($detail === '' && (isset($row['status']) || isset($row['amount']))) {
                        $status = trim((string) ($row['status'] ?? ''));
                        $amount = isset($row['amount']) ? '₺' . number_format((float) $row['amount'], 2, ',', '.') : '';
                        $detail = trim($status . ($status !== '' && $amount !== '' ? ' · ' : '') . $amount);
                    }
                    $events[] = [
                        'id' => (int) ($row['id'] ?? 0),
                        'title' => (string) ($row['title'] ?? 'Etkinlik'),
                        'starts_at' => date('Y-m-d H:i:s', $startTs),
                        'ends_at' => $endTs !== false ? date('Y-m-d H:i:s', $endTs) : '',
                        'day' => date('Y-m-d', $startTs),
                        'kind' => (string) $query['kind'],
                        'kind_key' => (string) $query['kind_key'],
                        'color' => (string) $query['color'],
                        'detail' => $detail,
                    ];
                }
            } catch (Throwable) {
            }
        }

        usort(
            $events,
            static function (array $left, array $right): int {
                return strcmp((string) ($right['starts_at'] ?? ''), (string) ($left['starts_at'] ?? ''));
            }
        );

        return $events;
    }

    private function turkishMonthLabel(int $timestamp): string
    {
        static $months = [
            1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
            5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
            9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
        ];
        $month = (int) date('n', $timestamp);
        $year = date('Y', $timestamp);

        return ($months[$month] ?? date('F', $timestamp)) . ' ' . $year;
    }

    private function scalar(string $sql): float
    {
        try {
            return (float) AdminDatabase::pdo()->query($sql)->fetchColumn();
        } catch (Throwable $exception) {
            error_log('[AdminReportController] scalar failed: ' . $exception->getMessage());
            $this->lastQueryError = $exception->getMessage();

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
        } catch (Throwable $exception) {
            error_log('[AdminReportController] series failed: ' . $exception->getMessage());
            $this->lastQueryError = $exception->getMessage();

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
        } catch (Throwable $exception) {
            error_log('[AdminReportController] amountSeries failed: ' . $exception->getMessage());
            $this->lastQueryError = $exception->getMessage();

            return [];
        }
    }
}
