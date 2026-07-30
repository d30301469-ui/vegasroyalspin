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
                    (SELECT COUNT(*) FROM affiliate_commissions WHERE affiliate_id = a.id AND status = 'pending') AS pending_commissions
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

        // Referred users
        $stmt = $pdo->prepare(
            "SELECT id, username, email, name, surname, balance, created_at, last_login_at
             FROM users WHERE referred_by_affiliate_id = :id ORDER BY created_at DESC LIMIT 50"
        );
        $stmt->execute(['id' => $id]);
        $referredUsers = $stmt->fetchAll();

        // Commission summary
        $stmt = $pdo->prepare(
            "SELECT commission_type, COUNT(*) AS cnt, SUM(amount) AS total, SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) AS paid
             FROM affiliate_commissions WHERE affiliate_id = :id GROUP BY commission_type"
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

        $text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $money = static fn (mixed $v): string => number_format((float) $v, 2, ',', '.');

        $this->view('affiliates/detail', [
            'title' => 'Ortak Detayı - ' . $text($affiliate['full_name'] ?: $affiliate['email']),
            'active' => 'affiliates',
            'crumbs' => 'Ortaklık Sistemi | Ortaklar | Detay',
            'affiliate' => $affiliate,
            'referredUsers' => $referredUsers,
            'commissionSummary' => $commissionSummary,
            'recentCommissions' => $recentCommissions,
            'payouts' => $payouts,
            'clickStats' => $clickStats,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function update(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $token = (string) ($_POST['_token'] ?? '');
        if ($token === '' || $token !== AdminAuth::csrfToken()) {
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

        if (isset($_POST['commission_plan_id'])) {
            $planId = (int) $_POST['commission_plan_id'];
            if ($planId > 0) {
                $sets[] = 'commission_plan_id = :commission_plan_id';
                $params['commission_plan_id'] = $planId;
            } else {
                $sets[] = 'commission_plan_id = NULL';
            }
        }

        // Status changed to active - set approved_at
        if (isset($_POST['status']) && $_POST['status'] === 'active') {
            $stmt = $pdo->prepare("SELECT status FROM affiliates WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $current = $stmt->fetchColumn();
            if ($current === 'pending' || $current === 'rejected') {
                $sets[] = 'approved_at = NOW()';
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
        if ($token === '' || $token !== AdminAuth::csrfToken()) {
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
        if ($token === '' || $token !== AdminAuth::csrfToken()) {
            $_SESSION['admin_flash'] = 'Güvenlik tokenı geçersiz.';
            $this->redirect(AdminAuth::url('/affiliate/plans'));
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $planType = trim((string) ($_POST['plan_type'] ?? 'revshare'));
        $revshareRate = (float) ($_POST['revshare_rate'] ?? 0);
        $cpaAmount = (float) ($_POST['cpa_amount'] ?? 0);
        $minDeposit = (float) ($_POST['min_deposit'] ?? 0);
        $description = trim((string) ($_POST['description'] ?? ''));
        $isDefault = !empty($_POST['is_default']) ? 1 : 0;

        if ($name === '') {
            $_SESSION['admin_flash'] = 'Plan adı zorunludur.';
            $this->redirect(AdminAuth::url('/affiliate/plans'));
        }

        if ($isDefault) {
            $pdo->exec("UPDATE affiliate_commission_plans SET is_default = 0");
        }

        $stmt = $pdo->prepare(
            "INSERT INTO affiliate_commission_plans (name, plan_type, revshare_rate, cpa_amount, min_deposit, is_default, description)
             VALUES (:name, :plan_type, :revshare_rate, :cpa_amount, :min_deposit, :is_default, :description)"
        );
        $stmt->execute([
            'name' => $name,
            'plan_type' => $planType,
            'revshare_rate' => $revshareRate,
            'cpa_amount' => $cpaAmount,
            'min_deposit' => $minDeposit,
            'is_default' => $isDefault,
            'description' => $description,
        ]);

        $_SESSION['admin_flash'] = 'Komisyon planı oluşturuldu.';
        $this->redirect(AdminAuth::url('/affiliate/plans'));
    }

    public function updatePlan(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $token = (string) ($_POST['_token'] ?? '');
        if ($token === '' || $token !== AdminAuth::csrfToken()) {
            $_SESSION['admin_flash'] = 'Güvenlik tokenı geçersiz.';
            $this->redirect(AdminAuth::url('/affiliate/plans'));
        }

        $id = max(0, (int) ($_POST['id'] ?? 0));
        $name = trim((string) ($_POST['name'] ?? ''));
        $planType = trim((string) ($_POST['plan_type'] ?? 'revshare'));
        $revshareRate = (float) ($_POST['revshare_rate'] ?? 0);
        $cpaAmount = (float) ($_POST['cpa_amount'] ?? 0);
        $minDeposit = (float) ($_POST['min_deposit'] ?? 0);
        $description = trim((string) ($_POST['description'] ?? ''));
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        $isDefault = !empty($_POST['is_default']) ? 1 : 0;

        if ($name === '' || $id === 0) {
            $_SESSION['admin_flash'] = 'Eksik bilgi.';
            $this->redirect(AdminAuth::url('/affiliate/plans'));
        }

        if ($isDefault) {
            $pdo->exec("UPDATE affiliate_commission_plans SET is_default = 0");
        }

        $stmt = $pdo->prepare(
            "UPDATE affiliate_commission_plans SET name = :name, plan_type = :plan_type, revshare_rate = :revshare_rate,
             cpa_amount = :cpa_amount, min_deposit = :min_deposit, description = :description,
             is_active = :is_active, is_default = :is_default WHERE id = :id"
        );
        $stmt->execute([
            'name' => $name,
            'plan_type' => $planType,
            'revshare_rate' => $revshareRate,
            'cpa_amount' => $cpaAmount,
            'min_deposit' => $minDeposit,
            'description' => $description,
            'is_active' => $isActive,
            'is_default' => $isDefault,
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
        if ($token === '' || $token !== AdminAuth::csrfToken()) {
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
        if ($token === '' || $token !== AdminAuth::csrfToken()) {
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

            $stmt = $pdo->prepare(
                "UPDATE affiliate_payouts SET status = :status, admin_notes = :notes, processed_at = NOW(), updated_at = NOW()
                 WHERE id = :id"
            );
            $stmt->execute(['status' => $newStatus, 'notes' => $adminNotes, 'id' => $id]);

            // If completed, update affiliate balance
            if ($newStatus === 'completed') {
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

            // If rejected or cancelled, restore balance
            if (in_array($newStatus, ['rejected', 'cancelled'], true) && $payout['status'] === 'pending') {
                $stmt = $pdo->prepare(
                    "UPDATE affiliates SET balance = balance + :amount WHERE id = :affiliate_id"
                );
                $stmt->execute(['amount' => $payout['amount'], 'affiliate_id' => $payout['affiliate_id']]);
            }

            $pdo->commit();
            $_SESSION['admin_flash'] = 'Ödeme durumu güncellendi.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['admin_flash'] = 'Hata: ' . $e->getMessage();
        }

        $this->redirect(AdminAuth::url('/affiliate/payouts'));
    }

    // --- Commissions (manual add) ---

    public function addCommission(): void
    {
        $this->requirePermission('affiliates');

        $pdo = AdminDatabase::pdo();
        $token = (string) ($_POST['_token'] ?? '');
        if ($token === '' || $token !== AdminAuth::csrfToken()) {
            $_SESSION['admin_flash'] = 'Güvenlik tokenı geçersiz.';
            $this->redirect(AdminAuth::url('/affiliates'));
        }

        $affiliateId = max(0, (int) ($_POST['affiliate_id'] ?? 0));
        $userId = max(0, (int) ($_POST['user_id'] ?? 0));
        $amount = (float) ($_POST['amount'] ?? 0);
        $description = trim((string) ($_POST['description'] ?? 'Manuel komisyon'));

        if ($affiliateId === 0 || $amount <= 0) {
            $_SESSION['admin_flash'] = 'Geçersiz ortak veya tutar.';
            $this->redirect(AdminAuth::url('/affiliate/detail?id=' . $affiliateId));
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO affiliate_commissions (affiliate_id, user_id, commission_type, amount, status, description, source)
                 VALUES (:affiliate_id, :user_id, 'manual', :amount, 'approved', :description, 'manual')"
            );
            $stmt->execute([
                'affiliate_id' => $affiliateId,
                'user_id' => $userId ?: 0,
                'amount' => $amount,
                'description' => $description,
            ]);

            $stmt = $pdo->prepare("UPDATE affiliates SET balance = balance + :amount, total_earned = total_earned + :amount WHERE id = :id");
            $stmt->execute(['amount' => $amount, 'id' => $affiliateId]);

            $pdo->commit();
            $_SESSION['admin_flash'] = number_format($amount, 2, ',', '.') . ' ₺ komisyon eklendi.';
        } catch (Exception $e) {
            $pdo->rollBack();
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

        if ($token === '' || $token !== AdminAuth::csrfToken()) {
            $_SESSION['admin_flash'] = 'Güvenlik tokenı geçersiz.';
            $this->redirect(AdminAuth::url('/affiliates'));
        }

        if ($id === 0 || !in_array($action, ['approve', 'reject'], true)) {
            $_SESSION['admin_flash'] = 'Geçersiz işlem.';
            $this->redirect(AdminAuth::url('/affiliates'));
        }

        if ($action === 'approve') {
            $stmt = $pdo->prepare("SELECT status FROM affiliates WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $current = $stmt->fetchColumn();

            if ($current === 'pending' || $current === 'rejected') {
                $stmt = $pdo->prepare(
                    "UPDATE affiliates SET status = 'active', approved_at = NOW() WHERE id = :id"
                );
                $stmt->execute(['id' => $id]);
                $_SESSION['admin_flash'] = 'Ortak onaylandı.';
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
}
