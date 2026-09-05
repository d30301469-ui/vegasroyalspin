<?php

declare(strict_types=1);

final class AdminAffiliateController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $search = trim((string) ($_GET['search'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $perPage = 25;

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(a.full_name LIKE :search OR a.email LIKE :search2 OR a.referral_code LIKE :search3 OR a.company_name LIKE :search4)';
            $params['search'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
            $params['search3'] = "%{$search}%";
            $params['search4'] = "%{$search}%";
        }
        if ($status !== '') {
            $where[] = 'a.status = :status';
            $params['status'] = $status;
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM affiliates a {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare(
            "SELECT a.*, cp.name AS plan_name, cp.plan_type,
                    (SELECT COUNT(*) FROM affiliate_commissions WHERE affiliate_id = a.id AND status IN ('pending', 'approved')) AS pending_commissions
             FROM affiliates a
             LEFT JOIN affiliate_commission_plans cp ON a.commission_plan_id = cp.id
             {$whereClause}
             ORDER BY a.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $affiliates = $stmt->fetchAll();

        $totalPages = (int) ceil($total / $perPage);

        $text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $money = static fn (mixed $v): string => number_format((float) $v, 2, ',', '.');

        $this->view('affiliates/index', [
            'title' => 'Ortaklık Yönetimi',
            'active' => 'affiliates',
            'crumbs' => 'Ortaklık Sistemi | Ortaklar',
            'affiliates' => $affiliates,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'search' => $search,
            'status' => $status,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function detail(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $id = max(0, (int) ($_GET['id'] ?? 0));

        $stmt = $pdo->prepare(
            "SELECT a.*, cp.name AS plan_name, cp.plan_type, cp.revshare_rate, cp.cpa_amount
             FROM affiliates a
             LEFT JOIN affiliate_commission_plans cp ON a.commission_plan_id = cp.id
             WHERE a.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $affiliate = $stmt->fetch();

        if ($affiliate === false) {
            $_SESSION['admin_flash'] = 'Ortak bulunamadı.';
            $this->redirect(AdminAuth::url('/affiliates'));
        }

        // Referred users — tam liste (sayfalama; LIMIT 50 kesilmesin)
        $usersPage = max(1, (int) ($_GET['users_page'] ?? 1));
        $usersPerPage = (int) ($_GET['users_per_page'] ?? 100);
        if (!in_array($usersPerPage, [50, 100, 200, 500], true)) {
            $usersPerPage = 100;
        }
        $referredTotalCount = 0;
        try {
            $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE referred_by_affiliate_id = :id');
            $cntStmt->execute(['id' => $id]);
            $referredTotalCount = (int) $cntStmt->fetchColumn();
        } catch (Throwable) {
            $referredTotalCount = 0;
        }
        $usersTotalPages = max(1, (int) ceil($referredTotalCount / max(1, $usersPerPage)));
        if ($referredTotalCount === 0) {
            $usersTotalPages = 1;
            $usersPage = 1;
        } elseif ($usersPage > $usersTotalPages) {
            $usersPage = $usersTotalPages;
        }
        $usersOffset = ($usersPage - 1) * $usersPerPage;
        $stmt = $pdo->prepare(
            "SELECT id, username, email, name, surname, balance, created_at, last_login_at
             FROM users
             WHERE referred_by_affiliate_id = :id
             ORDER BY created_at DESC
             LIMIT {$usersPerPage} OFFSET {$usersOffset}"
        );
        $stmt->execute(['id' => $id]);
        $referredUsers = $stmt->fetchAll();
        $referredUsersMeta = [
            'page' => $usersPage,
            'per_page' => $usersPerPage,
            'total' => $referredTotalCount,
            'pages' => $usersTotalPages,
        ];

        // Commission summary — exclude cancelled/void so charts match real earnings
        $stmt = $pdo->prepare(
            "SELECT commission_type,
                    COUNT(*) AS cnt,
                    SUM(amount) AS total,
                    SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) AS paid,
                    SUM(CASE WHEN status IN ('approved','pending') THEN amount ELSE 0 END) AS open_amount
             FROM affiliate_commissions
             WHERE affiliate_id = :id
               AND status IN ('pending', 'approved', 'paid')
             GROUP BY commission_type"
        );
        $stmt->execute(['id' => $id]);
        $commissionSummary = $stmt->fetchAll();

        // Recent commissions
        $stmt = $pdo->prepare(
            "SELECT ac.*, u.username AS user_username
             FROM affiliate_commissions ac
             LEFT JOIN users u ON ac.user_id = u.id
             WHERE ac.affiliate_id = :id
             ORDER BY ac.created_at DESC LIMIT 20"
        );
        $stmt->execute(['id' => $id]);
        $recentCommissions = $stmt->fetchAll();

        // Payouts
        $stmt = $pdo->prepare(
            "SELECT * FROM affiliate_payouts WHERE affiliate_id = :id ORDER BY requested_at DESC LIMIT 20"
        );
        $stmt->execute(['id' => $id]);
        $payouts = $stmt->fetchAll();

        // Stats
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS clicks, SUM(converted) AS conversions FROM affiliate_clicks WHERE affiliate_id = :id"
        );
        $stmt->execute(['id' => $id]);
        $clickStats = $stmt->fetch();

        // Period filter for KPI + charts (aligned)
        $period = strtolower(trim((string) ($_GET['period'] ?? '30')));
        if (!in_array($period, ['7', '30', '90', 'all'], true)) {
            $period = '30';
        }
        $periodDays = $period === 'all' ? 0 : (int) $period;
        $periodFrom = $periodDays > 0
            ? (new DateTimeImmutable('today'))->modify('-' . ($periodDays - 1) . ' days')->format('Y-m-d')
            : null;
        $periodLabel = match ($period) {
            '7' => 'Son 7 gün',
            '90' => 'Son 90 gün',
            'all' => 'Tüm zamanlar',
            default => 'Son 30 gün',
        };

        $paidStatuses = "('confirmed','approved','success','completed')";
        $playerCashflow = [
            'deposits' => 0.0,
            'withdrawals' => 0.0,
            'deposit_count' => 0,
            'withdraw_count' => 0,
            'referred_total' => 0,
            'period' => $period,
            'period_label' => $periodLabel,
            'period_from' => $periodFrom,
        ];
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM users WHERE referred_by_affiliate_id = :id"
            );
            $stmt->execute(['id' => $id]);
            $playerCashflow['referred_total'] = (int) $stmt->fetchColumn();

            $cashSql = "SELECT
                    COALESCE(SUM(CASE WHEN t.type = 'deposit' THEN t.amount ELSE 0 END), 0) AS deposits,
                    COALESCE(SUM(CASE WHEN t.type = 'withdraw' THEN t.amount ELSE 0 END), 0) AS withdrawals,
                    COALESCE(SUM(CASE WHEN t.type = 'deposit' THEN 1 ELSE 0 END), 0) AS deposit_count,
                    COALESCE(SUM(CASE WHEN t.type = 'withdraw' THEN 1 ELSE 0 END), 0) AS withdraw_count
                 FROM megapayz_transactions t
                 INNER JOIN users u ON u.id = t.user_id
                 WHERE u.referred_by_affiliate_id = :id
                   AND t.status IN {$paidStatuses}
                   AND t.type IN ('deposit', 'withdraw')";
            $cashParams = ['id' => $id];
            if ($periodFrom !== null) {
                $cashSql .= ' AND t.created_at >= :from_dt';
                $cashParams['from_dt'] = $periodFrom . ' 00:00:00';
            }
            $stmt = $pdo->prepare($cashSql);
            $stmt->execute($cashParams);
            $cashRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($cashRow)) {
                $playerCashflow['deposits'] = (float) ($cashRow['deposits'] ?? 0);
                $playerCashflow['withdrawals'] = (float) ($cashRow['withdrawals'] ?? 0);
                $playerCashflow['deposit_count'] = (int) ($cashRow['deposit_count'] ?? 0);
                $playerCashflow['withdraw_count'] = (int) ($cashRow['withdraw_count'] ?? 0);
            }
        } catch (Throwable) {
            // keep zeros
        }

        $chartData = [
            'period' => $period,
            'period_label' => $periodLabel,
            'trend' => ['labels' => [], 'deposits' => [], 'withdrawals' => [], 'net' => []],
            'share' => [
                'deposits' => (float) ($playerCashflow['deposits'] ?? 0),
                'withdrawals' => (float) ($playerCashflow['withdrawals'] ?? 0),
                'net' => round((float) ($playerCashflow['deposits'] ?? 0) - (float) ($playerCashflow['withdrawals'] ?? 0), 2),
            ],
            'commissions' => ['labels' => [], 'values' => [], 'paid' => []],
            'earnings' => [
                'paid' => (float) ($affiliate['total_paid'] ?? 0),
                'balance' => (float) ($affiliate['balance'] ?? 0),
                'earned' => (float) ($affiliate['total_earned'] ?? 0),
            ],
        ];

        // Daily trend for selected period (cap all-time at last 180 days for readability)
        try {
            $trendDays = $periodDays > 0 ? $periodDays : 180;
            $fromDate = (new DateTimeImmutable('today'))->modify('-' . ($trendDays - 1) . ' days')->format('Y-m-d');
            $stmt = $pdo->prepare(
                "SELECT DATE(t.created_at) AS d,
                        COALESCE(SUM(CASE WHEN t.type = 'deposit' THEN t.amount ELSE 0 END), 0) AS deposits,
                        COALESCE(SUM(CASE WHEN t.type = 'withdraw' THEN t.amount ELSE 0 END), 0) AS withdrawals
                 FROM megapayz_transactions t
                 INNER JOIN users u ON u.id = t.user_id
                 WHERE u.referred_by_affiliate_id = :id
                   AND t.status IN {$paidStatuses}
                   AND t.type IN ('deposit', 'withdraw')
                   AND t.created_at >= :from_dt
                 GROUP BY DATE(t.created_at)
                 ORDER BY d ASC"
            );
            $stmt->execute([
                'id' => $id,
                'from_dt' => $fromDate . ' 00:00:00',
            ]);
            $byDay = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $byDay[(string) ($row['d'] ?? '')] = [
                    'deposits' => (float) ($row['deposits'] ?? 0),
                    'withdrawals' => (float) ($row['withdrawals'] ?? 0),
                ];
            }
            $cursor = new DateTimeImmutable($fromDate);
            $end = new DateTimeImmutable('today');
            while ($cursor <= $end) {
                $key = $cursor->format('Y-m-d');
                $dep = (float) ($byDay[$key]['deposits'] ?? 0);
                $wd = (float) ($byDay[$key]['withdrawals'] ?? 0);
                $chartData['trend']['labels'][] = $cursor->format('d.m');
                $chartData['trend']['deposits'][] = round($dep, 2);
                $chartData['trend']['withdrawals'][] = round($wd, 2);
                $chartData['trend']['net'][] = round($dep - $wd, 2);
                $cursor = $cursor->modify('+1 day');
            }
        } catch (Throwable) {
            // keep empty trend
        }

        foreach ($commissionSummary as $row) {
            $type = strtolower((string) ($row['commission_type'] ?? ''));
            $label = match ($type) {
                'revshare' => 'RevShare',
                'cpa' => 'CPA',
                'hybrid' => 'Hybrid',
                'manual' => 'Manuel',
                default => $type !== '' ? ucfirst($type) : 'Diğer',
            };
            $chartData['commissions']['labels'][] = $label;
            $chartData['commissions']['values'][] = round((float) ($row['total'] ?? 0), 2);
            $chartData['commissions']['paid'][] = round((float) ($row['paid'] ?? 0), 2);
        }

        $plans = $pdo->query(
            "SELECT id, name, plan_type, revshare_rate, cpa_amount, is_active, is_default
             FROM affiliate_commission_plans
             ORDER BY is_active DESC, is_default DESC, name ASC"
        )->fetchAll();

        $text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $money = static fn (mixed $v): string => number_format((float) $v, 2, ',', '.');

        $this->view('affiliates/detail', [
            'title' => 'Ortak Detayı - ' . $text($affiliate['full_name'] ?: $affiliate['email']),
            'active' => 'affiliates',
            'crumbs' => 'Ortaklık Sistemi | Ortaklar | Detay',
            'affiliate' => $affiliate,
            'plans' => $plans,
            'referredUsers' => $referredUsers,
            'referredUsersMeta' => $referredUsersMeta,
            'commissionSummary' => $commissionSummary,
            'recentCommissions' => $recentCommissions,
            'payouts' => $payouts,
            'clickStats' => $clickStats,
            'playerCashflow' => $playerCashflow,
            'chartData' => $chartData,
            'chartPeriod' => $period,
            'chartPeriodLabel' => $periodLabel,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function update(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $token = (string) ($_POST['_token'] ?? '');
        if (!AdminAuth::verifyCsrf($token)) {
            $_SESSION['admin_flash'] = 'Güvenlik tokenı geçersiz.';
            $this->redirect(AdminAuth::url('/affiliate/detail?id=' . $id));
        }

        $fields = ['full_name', 'company_name', 'email', 'phone', 'country', 'city', 'website', 'status', 'notes'];
        $sets = [];
        $params = ['id' => $id];

        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                $sets[] = "{$f} = :{$f}";
                $params[$f] = trim((string) $_POST[$f]);
            }
        }

        if (array_key_exists('commission_plan_id', $_POST)) {
            $planId = (int) $_POST['commission_plan_id'];
            if ($planId > 0) {
                $check = $pdo->prepare('SELECT id FROM affiliate_commission_plans WHERE id = :id LIMIT 1');
                $check->execute(['id' => $planId]);
                if ($check->fetchColumn() === false) {
                    $_SESSION['admin_flash'] = 'Seçilen komisyon planı bulunamadı.';
                    $this->redirect(AdminAuth::url('/affiliate/detail?id=' . $id));
                }
                $sets[] = 'commission_plan_id = :commission_plan_id';
                $params['commission_plan_id'] = $planId;
            } else {
                $sets[] = 'commission_plan_id = NULL';
            }
        }

        // Status changed to active - set approved_at + ensure a commission plan
        if (isset($_POST['status']) && $_POST['status'] === 'active') {
            $stmt = $pdo->prepare("SELECT status, commission_plan_id FROM affiliates WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $currentRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $current = (string) ($currentRow['status'] ?? '');
            if ($current === 'pending' || $current === 'rejected') {
                $sets[] = 'approved_at = NOW()';
            }
            $planAlreadySet = array_key_exists('commission_plan_id', $_POST) && (int) ($_POST['commission_plan_id'] ?? 0) > 0;
            if (!$planAlreadySet && (int) ($currentRow['commission_plan_id'] ?? 0) <= 0) {
                AffiliateCommissionEngine::ensureSchema($pdo);
                $defaultPlanId = AffiliateCommissionEngine::defaultPlanId($pdo);
                if ($defaultPlanId !== null) {
                    $sets[] = 'commission_plan_id = :auto_plan_id';
                    $params['auto_plan_id'] = $defaultPlanId;
                }
            }
        }

        if ($sets !== []) {
            $sql = "UPDATE affiliates SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $_SESSION['admin_flash'] = 'Ortak bilgileri güncellendi.';
        }

        $this->redirect(AdminAuth::url('/affiliate/detail?id=' . $id));
    }

    public function updatePaymentDetails(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $token = (string) ($_POST['_token'] ?? '');
        if (!AdminAuth::verifyCsrf($token)) {
            $_SESSION['admin_flash'] = 'Güvenlik tokenı geçersiz.';
            $this->redirect(AdminAuth::url('/affiliate/detail?id=' . $id));
        }

        $method = trim((string) ($_POST['payment_method'] ?? 'bank'));
        $details = trim((string) ($_POST['payment_details'] ?? '{}'));

        $stmt = $pdo->prepare("UPDATE affiliates SET payment_method = :method, payment_details = :details WHERE id = :id");
        $stmt->execute(['method' => $method, 'details' => $details, 'id' => $id]);

        $_SESSION['admin_flash'] = 'Ödeme bilgileri güncellendi.';
        $this->redirect(AdminAuth::url('/affiliate/detail?id=' . $id));
    }

    // --- Commission Plans ---

    public function plans(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $plans = $pdo->query("SELECT * FROM affiliate_commission_plans ORDER BY is_default DESC, is_active DESC, id ASC")->fetchAll();

        $text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $this->view('affiliates/plans', [
            'title' => 'Komisyon Planları',
            'active' => 'affiliate-plans',
            'crumbs' => 'Ortaklık Sistemi | Komisyon Planları',
            'plans' => $plans,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function storePlan(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $token = (string) ($_POST['_token'] ?? '');
        if (!AdminAuth::verifyCsrf($token)) {
            $_SESSION['admin_flash'] = 'Güvenlik tokenı geçersiz.';
            $this->redirect(AdminAuth::url('/affiliate/plans'));
        }

        $parsed = $this->parsePlanPayload($_POST);
        if ($parsed['name'] === '') {
            $_SESSION['admin_flash'] = 'Plan adı zorunludur.';
            $this->redirect(AdminAuth::url('/affiliate/plans'));
        }

        if ($parsed['is_default']) {
            $pdo->exec('UPDATE affiliate_commission_plans SET is_default = 0');
        }

        $stmt = $pdo->prepare(
            "INSERT INTO affiliate_commission_plans
             (name, plan_type, revshare_rate, cpa_amount, min_deposit, is_default, is_active, description)
             VALUES (:name, :plan_type, :revshare_rate, :cpa_amount, :min_deposit, :is_default, 1, :description)"
        );
        $stmt->execute([
            'name' => $parsed['name'],
            'plan_type' => $parsed['plan_type'],
            'revshare_rate' => $parsed['revshare_rate'],
            'cpa_amount' => $parsed['cpa_amount'],
            'min_deposit' => $parsed['min_deposit'],
            'is_default' => $parsed['is_default'],
            'description' => $parsed['description'],
        ]);

        $_SESSION['admin_flash'] = 'Komisyon planı oluşturuldu.';
        $this->redirect(AdminAuth::url('/affiliate/plans'));
    }

    public function updatePlan(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $token = (string) ($_POST['_token'] ?? '');
        if (!AdminAuth::verifyCsrf($token)) {
            $_SESSION['admin_flash'] = 'Güvenlik tokenı geçersiz.';
            $this->redirect(AdminAuth::url('/affiliate/plans'));
        }

        $id = max(0, (int) ($_POST['id'] ?? 0));
        $parsed = $this->parsePlanPayload($_POST);

        if ($parsed['name'] === '' || $id === 0) {
            $_SESSION['admin_flash'] = 'Eksik bilgi.';
            $this->redirect(AdminAuth::url('/affiliate/plans'));
        }

        $exists = $pdo->prepare('SELECT id FROM affiliate_commission_plans WHERE id = :id LIMIT 1');
        $exists->execute(['id' => $id]);
        if ($exists->fetchColumn() === false) {
            $_SESSION['admin_flash'] = 'Komisyon planı bulunamadı.';
            $this->redirect(AdminAuth::url('/affiliate/plans'));
        }

        if ($parsed['is_default']) {
            $pdo->exec('UPDATE affiliate_commission_plans SET is_default = 0');
        }

        $stmt = $pdo->prepare(
            "UPDATE affiliate_commission_plans SET name = :name, plan_type = :plan_type, revshare_rate = :revshare_rate,
             cpa_amount = :cpa_amount, min_deposit = :min_deposit, description = :description,
             is_active = :is_active, is_default = :is_default WHERE id = :id"
        );
        $stmt->execute([
            'name' => $parsed['name'],
            'plan_type' => $parsed['plan_type'],
            'revshare_rate' => $parsed['revshare_rate'],
            'cpa_amount' => $parsed['cpa_amount'],
            'min_deposit' => $parsed['min_deposit'],
            'description' => $parsed['description'],
            'is_active' => $parsed['is_active'],
            'is_default' => $parsed['is_default'],
            'id' => $id,
        ]);

        $_SESSION['admin_flash'] = 'Komisyon planı güncellendi.';
        $this->redirect(AdminAuth::url('/affiliate/plans'));
    }

    public function deletePlan(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $token = (string) ($_POST['_token'] ?? '');
        if (!AdminAuth::verifyCsrf($token)) {
            $_SESSION['admin_flash'] = 'Güvenlik tokenı geçersiz.';
            $this->redirect(AdminAuth::url('/affiliate/plans'));
        }

        $id = max(0, (int) ($_POST['id'] ?? 0));
        if ($id > 0) {
            $pdo->prepare("UPDATE affiliates SET commission_plan_id = NULL WHERE commission_plan_id = :id")->execute(['id' => $id]);
            $pdo->prepare("DELETE FROM affiliate_commission_plans WHERE id = :id")->execute(['id' => $id]);
            $_SESSION['admin_flash'] = 'Komisyon planı silindi.';
        }

        $this->redirect(AdminAuth::url('/affiliate/plans'));
    }

    // --- Payouts ---

    public function payouts(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $status = trim((string) ($_GET['status'] ?? ''));
        $perPage = 25;

        $where = [];
        $params = [];

        if ($status !== '') {
            $where[] = 'ap.status = :status';
            $params['status'] = $status;
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_payouts ap {$whereClause}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare(
            "SELECT ap.*, a.full_name AS affiliate_name, a.email AS affiliate_email, a.referral_code
             FROM affiliate_payouts ap
             LEFT JOIN affiliates a ON ap.affiliate_id = a.id
             {$whereClause}
             ORDER BY ap.requested_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $payouts = $stmt->fetchAll();

        $totalPages = (int) ceil($total / $perPage);

        $text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $money = static fn (mixed $v): string => number_format((float) $v, 2, ',', '.');

        $this->view('affiliates/payouts', [
            'title' => 'Ödeme Yönetimi',
            'active' => 'affiliate-payouts',
            'crumbs' => 'Ortaklık Sistemi | Ödemeler',
            'payouts' => $payouts,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'status' => $status,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function updatePayout(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $token = (string) ($_POST['_token'] ?? '');
        if (!AdminAuth::verifyCsrf($token)) {
            $_SESSION['admin_flash'] = 'Güvenlik tokenı geçersiz.';
            $this->redirect(AdminAuth::url('/affiliate/payouts'));
        }

        $id = max(0, (int) ($_POST['id'] ?? 0));
        $newStatus = trim((string) ($_POST['status'] ?? ''));
        $adminNotes = trim((string) ($_POST['admin_notes'] ?? ''));

        if ($id === 0 || $newStatus === '') {
            $_SESSION['admin_flash'] = 'Eksik bilgi.';
            $this->redirect(AdminAuth::url('/affiliate/payouts'));
        }

        $pdo->beginTransaction();
        try {
            // Get payout info
            $stmt = $pdo->prepare("SELECT * FROM affiliate_payouts WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $payout = $stmt->fetch();

            if ($payout === false) {
                throw new RuntimeException('Ödeme bulunamadı.');
            }

            $previousStatus = (string) ($payout['status'] ?? '');
            $method = strtolower((string) ($payout['method'] ?? ''));

            // Kripto: "İşleniyor/Onay" = MegaPayz API'ye gönder (manuel status yazma değil).
            if ($method === 'crypto'
                && in_array($newStatus, ['processing', 'approved'], true)
                && in_array($previousStatus, ['pending', 'approved'], true)
                && trim((string) ($payout['megapayz_trx'] ?? '')) === ''
            ) {
                $pdo->rollBack();

                if (!class_exists('MegaPayzService')) {
                    if (function_exists('admin_require_project_file')) {
                        admin_require_project_file('services/MegaPayzService.php');
                    } else {
                        $serviceFile = dirname(__DIR__, 2) . '/services/MegaPayzService.php';
                        if (!is_file($serviceFile)) {
                            $serviceFile = dirname(__DIR__, 3) . '/services/MegaPayzService.php';
                        }
                        if (is_file($serviceFile)) {
                            require_once $serviceFile;
                        }
                    }
                }
                if (!class_exists('MegaPayzService')) {
                    throw new RuntimeException('MegaPayz servisi yüklenemedi.');
                }

                $admin = AdminAuth::user();
                if ($adminNotes !== '') {
                    $pdo->prepare(
                        "UPDATE affiliate_payouts
                         SET admin_notes = CONCAT(IFNULL(admin_notes,''), IF(IFNULL(admin_notes,'')='','', '\n'), :notes),
                             updated_at = NOW()
                         WHERE id = :id"
                    )->execute(['notes' => $adminNotes, 'id' => $id]);
                }

                $result = MegaPayzService::approveAffiliateCryptoPayout(
                    $pdo,
                    $id,
                    (int) ($admin['id'] ?? 0),
                    (string) ($admin['username'] ?? $admin['email'] ?? '')
                );
                $_SESSION['admin_flash'] = (string) ($result['message'] ?? ($result['success'] ? 'OK' : 'MegaPayz gönderimi başarısız.'));
                $this->redirect(AdminAuth::url('/affiliate/payouts'));
            }

            // Kripto ödemeler MegaPayz callback ile tamamlanır; manuel completed yasak.
            if ($method === 'crypto' && $newStatus === 'completed') {
                throw new RuntimeException('Kripto ödemeler MegaPayz ile tamamlanır. “MegaPayz’e Gönder” kullanın veya callback bekleyin.');
            }
            if ($method === 'crypto'
                && in_array($previousStatus, ['processing'], true)
                && trim((string) ($payout['megapayz_trx'] ?? '')) !== ''
                && !in_array($newStatus, ['rejected', 'cancelled'], true)
            ) {
                throw new RuntimeException('MegaPayz işlemi devam ediyor. Durumu callback güncelleyecektir; gerekirse red/iptal kullanın.');
            }

            $stmt = $pdo->prepare(
                "UPDATE affiliate_payouts SET status = :status, admin_notes = :notes, processed_at = NOW(), processed_by = :admin_id, updated_at = NOW()
                 WHERE id = :id"
            );
            $admin = AdminAuth::user();
            $stmt->execute([
                'status' => $newStatus,
                'notes' => $adminNotes,
                'admin_id' => (int) ($admin['id'] ?? 0) ?: null,
                'id' => $id,
            ]);

            // If completed, update affiliate totals and mark covered commissions paid
            if ($newStatus === 'completed' && $previousStatus !== 'completed') {
                $stmt = $pdo->prepare(
                    "UPDATE affiliates SET total_paid = total_paid + :amount WHERE id = :affiliate_id"
                );
                $stmt->execute(['amount' => $payout['amount'], 'affiliate_id' => $payout['affiliate_id']]);

                // Mark commissions as paid
                $stmt = $pdo->prepare(
                    "UPDATE affiliate_commissions SET status = 'paid', paid_at = NOW()
                     WHERE affiliate_id = :affiliate_id AND status = 'approved' AND created_at <= :requested_at"
                );
                $stmt->execute(['affiliate_id' => $payout['affiliate_id'], 'requested_at' => $payout['requested_at']]);
            }

            if (class_exists('AffiliateService')) {
                AffiliateService::reconcileBalance($pdo, (int) $payout['affiliate_id']);
            }

            $pdo->commit();
            $_SESSION['admin_flash'] = 'Ödeme durumu güncellendi.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['admin_flash'] = 'Hata: ' . $e->getMessage();
        }

        $this->redirect(AdminAuth::url('/affiliate/payouts'));
    }

    public function approvePayoutMegaPayz(): void
    {
        $this->requirePermission('affiliates');

        $token = (string) ($_POST['_token'] ?? '');
        if (!AdminAuth::verifyCsrf($token)) {
            $_SESSION['admin_flash'] = 'Güvenlik tokenı geçersiz.';
            $this->redirect(AdminAuth::url('/affiliate/payouts'));
        }

        $id = max(0, (int) ($_POST['id'] ?? 0));
        if ($id === 0) {
            $_SESSION['admin_flash'] = 'Geçersiz ödeme talebi.';
            $this->redirect(AdminAuth::url('/affiliate/payouts'));
        }

        if (!class_exists('MegaPayzService')) {
            if (function_exists('admin_require_project_file')) {
                admin_require_project_file('services/MegaPayzService.php');
            } else {
                $serviceFile = dirname(__DIR__, 2) . '/services/MegaPayzService.php';
                if (!is_file($serviceFile)) {
                    $serviceFile = dirname(__DIR__, 3) . '/services/MegaPayzService.php';
                }
                if (is_file($serviceFile)) {
                    require_once $serviceFile;
                }
            }
        }

        if (!class_exists('MegaPayzService')) {
            $_SESSION['admin_flash'] = 'MegaPayz servisi yüklenemedi.';
            $this->redirect(AdminAuth::url('/affiliate/payouts'));
        }

        $admin = AdminAuth::user();
        $result = MegaPayzService::approveAffiliateCryptoPayout(
            AdminDatabase::pdo(),
            $id,
            (int) ($admin['id'] ?? 0),
            (string) ($admin['username'] ?? $admin['email'] ?? '')
        );
        $_SESSION['admin_flash'] = (string) ($result['message'] ?? ($result['success'] ? 'OK' : 'İşlem başarısız.'));
        $this->redirect(AdminAuth::url('/affiliate/payouts'));
    }

    // --- Commissions (manual add) ---

    public function addCommission(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $token = (string) ($_POST['_token'] ?? '');
        $affiliateId = max(0, (int) ($_POST['affiliate_id'] ?? 0));
        if (!AdminAuth::verifyCsrf($token)) {
            $_SESSION['admin_flash'] = 'Güvenlik tokenı geçersiz.';
            $this->redirect(AdminAuth::url($affiliateId > 0 ? '/affiliate/detail?id=' . $affiliateId : '/affiliates'));
        }

        $userId = max(0, (int) ($_POST['user_id'] ?? 0));
        $amount = round((float) ($_POST['amount'] ?? 0), 2);
        $description = trim((string) ($_POST['description'] ?? 'Manuel komisyon'));
        if ($description === '') {
            $description = 'Manuel komisyon';
        }

        if ($affiliateId === 0 || $amount <= 0) {
            $_SESSION['admin_flash'] = 'Geçersiz ortak veya tutar.';
            $this->redirect(AdminAuth::url('/affiliate/detail?id=' . $affiliateId));
        }

        AffiliateCommissionEngine::ensureSchema($pdo);

        // Optional player id: empty/0 must be SQL NULL (FK forbids user_id=0).
        $resolvedUserId = null;
        if ($userId > 0) {
            $check = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
            $check->execute(['id' => $userId]);
            if ($check->fetchColumn() === false) {
                $_SESSION['admin_flash'] = 'Oyuncu #' . $userId . ' bulunamadı. Boş bırakın veya geçerli bir ID girin.';
                $this->redirect(AdminAuth::url('/affiliate/detail?id=' . $affiliateId));
            }
            $resolvedUserId = $userId;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO affiliate_commissions
                    (affiliate_id, user_id, commission_type, amount, status, description, source, approved_at)
                 VALUES
                    (:affiliate_id, :user_id, 'manual', :amount, 'approved', :description, 'manual', NOW())"
            );
            $stmt->execute([
                'affiliate_id' => $affiliateId,
                'user_id' => $resolvedUserId,
                'amount' => number_format($amount, 2, '.', ''),
                'description' => $description,
            ]);

            $stmt = $pdo->prepare(
                'UPDATE affiliates
                 SET balance = balance + :balance_amount,
                     total_earned = total_earned + :earned_amount
                 WHERE id = :id'
            );
            $stmt->execute([
                'balance_amount' => number_format($amount, 2, '.', ''),
                'earned_amount' => number_format($amount, 2, '.', ''),
                'id' => $affiliateId,
            ]);

            $pdo->commit();
            if (class_exists('AffiliateService', false)) {
                AffiliateService::reconcileBalance($pdo, $affiliateId);
            }
            $_SESSION['admin_flash'] = number_format($amount, 2, ',', '.') . ' ₺ komisyon eklendi.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[AdminAffiliateController::addCommission] ' . $e->getMessage());
            $_SESSION['admin_flash'] = 'Hata: ' . $e->getMessage();
        }

        $this->redirect(AdminAuth::url('/affiliate/detail?id=' . $affiliateId));
    }

    // --- Reports ---

    public function reports(): void
    {
        $this->requirePermission('affiliate-reports');

        $pdo = AdminDatabase::pdo();
        $period = trim((string) ($_GET['period'] ?? 'month'));
        $planId = (int) ($_GET['plan_id'] ?? 0);

        // Date range
        $dateFrom = match ($period) {
            'week' => date('Y-m-d', strtotime('-7 days')),
            'month' => date('Y-m-d', strtotime('-30 days')),
            'quarter' => date('Y-m-d', strtotime('-90 days')),
            'year' => date('Y-m-d', strtotime('-365 days')),
            default => date('Y-m-d', strtotime('-30 days')),
        };

        // Top affiliates
        $wherePlan = $planId > 0 ? 'AND a.commission_plan_id = :plan_id' : '';
        $params = ['date_from' => $dateFrom];
        if ($planId > 0) {
            $params['plan_id'] = $planId;
        }

        $stmt = $pdo->prepare(
            "SELECT a.id, a.full_name, a.email, a.referral_code, cp.name AS plan_name,
                    COUNT(DISTINCT ac.id) AS commission_count,
                    COALESCE(SUM(ac.amount), 0) AS total_commission,
                    COUNT(DISTINCT u.id) AS referred_count
             FROM affiliates a
             LEFT JOIN affiliate_commissions ac ON a.id = ac.affiliate_id AND ac.created_at >= :date_from
             LEFT JOIN affiliate_commission_plans cp ON a.commission_plan_id = cp.id
             LEFT JOIN users u ON u.referred_by_affiliate_id = a.id
             WHERE a.status = 'active' {$wherePlan}
             GROUP BY a.id
             ORDER BY total_commission DESC
             LIMIT 20"
        );
        $stmt->execute($params);
        $topAffiliates = $stmt->fetchAll();

        // Summary stats
        $stmt = $pdo->prepare(
            "SELECT
                COUNT(DISTINCT a.id) AS total_active,
                COALESCE(SUM(ac.amount), 0) AS period_commission,
                COUNT(DISTINCT ac.id) AS period_transactions
             FROM affiliates a
             LEFT JOIN affiliate_commissions ac ON a.id = ac.affiliate_id AND ac.created_at >= :date_from
             WHERE a.status = 'active'"
        );
        $stmt->execute(['date_from' => $dateFrom]);
        $summary = $stmt->fetch();

        // Plans for filter
        $plans = $pdo->query("SELECT id, name FROM affiliate_commission_plans WHERE is_active = 1")->fetchAll();

        $text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $money = static fn (mixed $v): string => number_format((float) $v, 2, ',', '.');

        $this->view('affiliates/reports', [
            'title' => 'Ortaklık Raporları',
            'active' => 'affiliate-reports',
            'crumbs' => 'Ortaklık Sistemi | Raporlar',
            'topAffiliates' => $topAffiliates,
            'summary' => $summary,
            'plans' => $plans,
            'period' => $period,
            'planId' => $planId,
            'dateFrom' => $dateFrom,
            'flash' => $this->pullFlash(),
        ]);
    }

    // --- Materials ---

    public function materials(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $materials = $pdo->query("SELECT * FROM affiliate_materials ORDER BY sort_order ASC, created_at DESC")->fetchAll();

        $text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $this->view('affiliates/materials', [
            'title' => 'Pazarlama Materyalleri',
            'active' => 'affiliate-materials',
            'crumbs' => 'Ortaklık Sistemi | Materyaller',
            'materials' => $materials,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function storeMaterial(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $token = (string) ($_POST['_token'] ?? '');
        if ($token === '' || $token !== AdminAuth::csrfToken()) {
            $_SESSION['admin_flash'] = 'Güvenlik tokenı geçersiz.';
            $this->redirect(AdminAuth::url('/affiliate/materials'));
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $materialType = trim((string) ($_POST['material_type'] ?? 'banner'));
        $fileUrl = trim((string) ($_POST['file_url'] ?? ''));
        $targetUrl = trim((string) ($_POST['target_url'] ?? ''));
        $width = (int) ($_POST['width'] ?? 0);
        $height = (int) ($_POST['height'] ?? 0);

        if ($title === '') {
            $_SESSION['admin_flash'] = 'Başlık zorunludur.';
            $this->redirect(AdminAuth::url('/affiliate/materials'));
        }

        $sortOrder = (int) $pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM affiliate_materials")->fetchColumn();

        $stmt = $pdo->prepare(
            "INSERT INTO affiliate_materials (title, material_type, file_url, target_url, width, height, sort_order)
             VALUES (:title, :material_type, :file_url, :target_url, :width, :height, :sort_order)"
        );
        $stmt->execute([
            'title' => $title,
            'material_type' => $materialType,
            'file_url' => $fileUrl,
            'target_url' => $targetUrl,
            'width' => $width,
            'height' => $height,
            'sort_order' => $sortOrder,
        ]);

        $_SESSION['admin_flash'] = 'Materyal eklendi.';
        $this->redirect(AdminAuth::url('/affiliate/materials'));
    }

    public function deleteMaterial(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $token = (string) ($_POST['_token'] ?? '');
        if ($token === '' || $token !== AdminAuth::csrfToken()) {
            $_SESSION['admin_flash'] = 'Güvenlik tokenı geçersiz.';
            $this->redirect(AdminAuth::url('/affiliate/materials'));
        }

        $id = max(0, (int) ($_POST['id'] ?? 0));
        if ($id > 0) {
            $pdo->prepare("DELETE FROM affiliate_materials WHERE id = :id")->execute(['id' => $id]);
            $_SESSION['admin_flash'] = 'Materyal silindi.';
        }

        $this->redirect(AdminAuth::url('/affiliate/materials'));
    }

    // --- Helpers ---

    /**
     * @param array<string, mixed> $input
     * @return array{name:string,plan_type:string,revshare_rate:float,cpa_amount:float,min_deposit:float,description:string,is_active:int,is_default:int}
     */
    private function parsePlanPayload(array $input): array
    {
        $planType = strtolower(trim((string) ($input['plan_type'] ?? 'revshare')));
        if (!in_array($planType, ['revshare', 'cpa', 'hybrid'], true)) {
            $planType = 'revshare';
        }

        $revshareRate = $this->parseDecimal($input['revshare_rate'] ?? 0);
        $cpaAmount = $this->parseDecimal($input['cpa_amount'] ?? 0);
        $minDeposit = $this->parseDecimal($input['min_deposit'] ?? 0);

        if ($planType === 'cpa') {
            $revshareRate = 0.0;
        }
        if ($planType === 'revshare') {
            $cpaAmount = 0.0;
        }

        return [
            'name' => trim((string) ($input['name'] ?? '')),
            'plan_type' => $planType,
            'revshare_rate' => max(0.0, min(100.0, $revshareRate)),
            'cpa_amount' => max(0.0, $cpaAmount),
            'min_deposit' => max(0.0, $minDeposit),
            'description' => trim((string) ($input['description'] ?? '')),
            'is_active' => !empty($input['is_active']) ? 1 : 0,
            'is_default' => !empty($input['is_default']) ? 1 : 0,
        ];
    }

    private function parseDecimal(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }

        // TR locale: "25,5" / "1.250,50"
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', $raw);
        }

        return is_numeric($raw) ? (float) $raw : 0.0;
    }

    private function pullFlash(): string
    {
        $flash = $_SESSION['admin_flash'] ?? '';
        unset($_SESSION['admin_flash']);
        return $flash;
    }

    public function quickAction(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $action = trim((string) ($_POST['action'] ?? ''));
        $token = (string) ($_POST['_token'] ?? '');

        if (!AdminAuth::verifyCsrf($token)) {
            $_SESSION['admin_flash'] = 'Güvenlik tokenı geçersiz.';
            $this->redirect(AdminAuth::url('/affiliates'));
        }

        if ($id === 0 || !in_array($action, ['approve', 'reject'], true)) {
            $_SESSION['admin_flash'] = 'Geçersiz işlem.';
            $this->redirect(AdminAuth::url('/affiliates'));
        }

        if ($action === 'approve') {
            $stmt = $pdo->prepare("SELECT status, commission_plan_id FROM affiliates WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $currentRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $current = (string) ($currentRow['status'] ?? '');

            if ($current === 'pending' || $current === 'rejected') {
                AffiliateCommissionEngine::ensureSchema($pdo);
                $planId = (int) ($currentRow['commission_plan_id'] ?? 0);
                if ($planId <= 0) {
                    $planId = (int) (AffiliateCommissionEngine::defaultPlanId($pdo) ?? 0);
                }
                if ($planId > 0) {
                    $stmt = $pdo->prepare(
                        "UPDATE affiliates
                         SET status = 'active', approved_at = NOW(), commission_plan_id = :plan
                         WHERE id = :id"
                    );
                    $stmt->execute(['plan' => $planId, 'id' => $id]);
                } else {
                    $stmt = $pdo->prepare(
                        "UPDATE affiliates SET status = 'active', approved_at = NOW() WHERE id = :id"
                    );
                    $stmt->execute(['id' => $id]);
                }
                $_SESSION['admin_flash'] = $planId > 0
                    ? 'Ortak onaylandı ve varsayılan komisyon planı atandı.'
                    : 'Ortak onaylandı. Uyarı: aktif komisyon planı yok — plan atayın.';
            } else {
                $_SESSION['admin_flash'] = 'Ortak zaten ' . $current . ' durumunda.';
            }
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE affiliates SET status = 'rejected' WHERE id = :id AND status = 'pending'");
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['admin_flash'] = 'Ortak reddedildi.';
            } else {
                $_SESSION['admin_flash'] = 'İşlem yapılamadı. Ortak bekliyor durumunda olmayabilir.';
            }
        }

        $this->redirect(AdminAuth::url('/affiliates'));
    }

    public function recalculate(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $token = (string) ($_POST['_token'] ?? '');
        if (!AdminAuth::verifyCsrf($token) || $id <= 0) {
            $_SESSION['admin_flash'] = 'Güvenlik doğrulaması başarısız.';
            $this->redirect(AdminAuth::url($id > 0 ? '/affiliate/detail?id=' . $id : '/affiliates'));
        }

        $dateFrom = trim((string) ($_POST['date_from'] ?? ''));
        $dateTo = trim((string) ($_POST['date_to'] ?? ''));
        if ($dateFrom === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1) {
            $dateFrom = date('Y-m-d', strtotime('-1 day'));
        }
        if ($dateTo === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) !== 1) {
            $dateTo = date('Y-m-d', strtotime($dateFrom . ' +1 day'));
        }
        // Engine expects end-exclusive window.
        if ($dateTo <= $dateFrom) {
            $dateTo = date('Y-m-d', strtotime($dateFrom . ' +1 day'));
        }

        try {
            AffiliateCommissionEngine::ensureSchema($pdo);
            AffiliateCommissionEngine::assignDefaultPlanIfMissing($pdo, $id);
            $result = AffiliateCommissionEngine::processPeriod($pdo, $dateFrom, $dateTo, $id, true);
            if (class_exists('AffiliateService', false)) {
                AffiliateService::cancelDuplicateRevshareCommissions($pdo, $id);
                AffiliateService::reconcileBalance($pdo, $id);
            }
            $_SESSION['admin_flash'] = sprintf(
                'Komisyon yeniden hesaplandı (%s → %s): %d kayıt, %s ₺. Ödenmiş dönemler korunur.',
                $dateFrom,
                $dateTo,
                (int) ($result['processed'] ?? 0),
                number_format((float) ($result['total'] ?? 0), 2, ',', '.')
            );
        } catch (Throwable $e) {
            error_log('[AdminAffiliateController::recalculate] ' . $e->getMessage());
            $_SESSION['admin_flash'] = 'Yeniden hesaplama başarısız: ' . $e->getMessage();
        }

        $this->redirect(AdminAuth::url('/affiliate/detail?id=' . $id));
    }
}
