<?php

declare(strict_types=1);

final class AdminDashboardController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('dashboard');
        $repo = new AdminTableRepository();
        $tables = $repo->tables();
        $tableCount = count($tables);
        $dateRange = $this->dateRange();
        $txWhere = $this->dateCondition('created_at', $dateRange);
        $userWhere = $this->dateCondition('created_at', $dateRange);
        $loginWhere = $this->dateCondition('COALESCE(last_login_at, updated_at, created_at)', $dateRange);
        $kycWhere = $this->dateCondition('submitted_at', $dateRange);
        $bonusClaimWhere = $this->dateCondition('created_at', $dateRange);
        $activeBonusWhere = $this->dateCondition('created_at', $dateRange);
        $adjustWhere = $this->dateCondition('created_at', $dateRange);
        $userCount = $this->scalar('SELECT COUNT(*) FROM users');
        $depositTotal = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE type = 'deposit' AND status IN ('confirmed','approved') AND {$txWhere}");
        $todayDepositTotal = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE type = 'deposit' AND status IN ('confirmed','approved') AND {$this->dateCondition('created_at', ['start' => new DateTimeImmutable('today'), 'end' => (new DateTimeImmutable('today'))->setTime(23, 59, 59)])}");
        $pendingDeposits = $this->scalar("SELECT COUNT(*) FROM megapayz_transactions WHERE type = 'deposit' AND status = 'pending'");
        $withdrawTotal = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE type = 'withdraw' AND status IN ('confirmed','approved') AND {$txWhere}");
        $pendingWithdrawals = $this->scalar("SELECT COUNT(*) FROM megapayz_transactions WHERE type = 'withdraw' AND status = 'pending'");
        $activeGames = $this->scalar('SELECT COUNT(*) FROM bgaming_games WHERE is_active = 1');
        $todayVisits = $this->scalar("SELECT COUNT(*) FROM visitor_logs WHERE {$this->dateCondition('created_at', $dateRange)}");
        $newUsersInRange = $this->scalar("SELECT COUNT(*) FROM users WHERE {$userWhere}");
        $todayUsers = $this->scalar("SELECT COUNT(*) FROM users WHERE {$this->dateCondition('created_at', ['start' => new DateTimeImmutable('today'), 'end' => (new DateTimeImmutable('today'))->setTime(23, 59, 59)])}");
        $verifiedUsers = $this->scalar('SELECT COUNT(*) FROM users WHERE is_verified = 1');
        $bannedUsers = $this->scalar('SELECT COUNT(*) FROM users WHERE banned = 1');
        $pendingKyc = $this->scalar("SELECT COUNT(*) FROM kyc_requests WHERE status = 'pending'");
        $openSupportTickets = $this->scalar("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','answered')");
        $openAmlAlerts = $this->scalar("SELECT COUNT(*) FROM aml_alerts WHERE status = 'open'");
        $openRiskAlerts = $this->scalar("SELECT COUNT(*) FROM risk_alerts WHERE status = 'open'");
        $bonusClaims = $this->scalar("SELECT COUNT(*) FROM bonus_claim_requests WHERE status IN ('pending', 'requested', 'waiting')");
        $activeBonuses = $this->scalar("SELECT COUNT(*) FROM user_active_bonuses WHERE status IN ('active', 'pending') AND {$activeBonusWhere}");
        $activePromotions = $this->scalar("SELECT COUNT(*) FROM promotions WHERE status IN ('active', 'published', '1')");
        $activeSliders = $this->scalar("SELECT COUNT(*) FROM sliders WHERE status IN ('active', 'published', '1')");
        $authSliders = $this->scalar('SELECT COUNT(*) FROM auth_sliders WHERE is_active = 1');
        $homepageSections = $this->scalar('SELECT COUNT(*) FROM homepage_sections WHERE is_active = 1');
        $openOperations = $pendingWithdrawals + $pendingDeposits + $pendingKyc + $bonusClaims + $openSupportTickets + $openAmlAlerts;
        $adjustUp = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM admin_balance_adjustments WHERE action = 'add' AND {$adjustWhere}");
        $adjustDown = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM admin_balance_adjustments WHERE action = 'subtract' AND {$adjustWhere}");
        $totalPlayerBalance = $this->scalar('SELECT COALESCE(SUM(balance), 0) FROM users');
        $totalBonusBalance = $this->scalar('SELECT COALESCE(SUM(bonus_balance), 0) FROM users');
        $loginUsers = $this->scalar("SELECT COUNT(*) FROM users WHERE {$loginWhere}");
        $activeUsers = $this->scalar('SELECT COUNT(*) FROM users WHERE COALESCE(banned, 0) = 0');

        $kpiCards = [
            ['label' => 'Toplam Yatırım', 'value' => $depositTotal, 'type' => 'money', 'count' => $this->scalar("SELECT COUNT(DISTINCT user_id) FROM megapayz_transactions WHERE type = 'deposit' AND status IN ('confirmed','approved') AND {$txWhere}"), 'status' => 'success', 'icon' => 'deposit'],
            ['label' => 'Toplam Çekim', 'value' => $withdrawTotal, 'type' => 'money', 'count' => $this->scalar("SELECT COUNT(DISTINCT user_id) FROM megapayz_transactions WHERE type = 'withdraw' AND status IN ('confirmed','approved') AND {$txWhere}"), 'status' => 'danger', 'icon' => 'withdraw'],
            ['label' => 'Hesap Düzelt (YUKARI)', 'value' => $adjustUp, 'type' => 'money', 'count' => $this->scalar("SELECT COUNT(DISTINCT user_id) FROM admin_balance_adjustments WHERE action = 'add' AND {$adjustWhere}"), 'status' => 'primary', 'icon' => 'adjust-up'],
            ['label' => 'Hesap Düzelt (AŞAĞI)', 'value' => -1 * $adjustDown, 'type' => 'money', 'count' => $this->scalar("SELECT COUNT(DISTINCT user_id) FROM admin_balance_adjustments WHERE action = 'subtract' AND {$adjustWhere}"), 'status' => 'warning', 'icon' => 'adjust-down'],
            ['label' => 'Toplam Oyuncu', 'value' => $userCount, 'type' => 'number', 'count' => $userCount, 'status' => 'purple', 'icon' => 'players'],
            ['label' => 'Yeni Kayıt Oyuncular', 'value' => $newUsersInRange, 'type' => 'number', 'count' => $newUsersInRange, 'status' => 'info', 'icon' => 'new-players'],
            ['label' => 'Giriş Yapan Kullanıcılar', 'value' => $loginUsers, 'type' => 'number', 'count' => $loginUsers, 'status' => 'purple', 'icon' => 'login-users'],
            ['label' => 'Toplam Aktif Oyuncu', 'value' => $activeUsers, 'type' => 'number', 'count' => $activeUsers, 'status' => 'success', 'icon' => 'active-players'],
            ['label' => 'Toplam Oyuncu Bakiyesi', 'value' => $totalPlayerBalance, 'type' => 'money', 'count' => $userCount, 'status' => 'info', 'icon' => 'wallet', 'wide' => true],
            ['label' => 'Bonus Miktarı', 'value' => $totalBonusBalance, 'type' => 'money', 'count' => $activeBonuses, 'status' => 'danger', 'icon' => 'bonus', 'wide' => true],
        ];

        $operationQueue = [
            ['label' => 'Bekleyen yatırım', 'value' => (int) $pendingDeposits, 'url' => '/module?key=deposits', 'class' => 'primary', 'hint' => 'Yatırım onayı bekliyor'],
            ['label' => 'Bekleyen çekim', 'value' => (int) $pendingWithdrawals, 'url' => '/module?key=withdrawals', 'class' => 'danger', 'hint' => 'Çekim onayı bekliyor'],
            ['label' => 'KYC kuyruğu', 'value' => (int) $pendingKyc, 'url' => '/kyc/review', 'class' => 'warning', 'hint' => 'Kimlik doğrulama bekliyor'],
            ['label' => 'Destek talepleri', 'value' => (int) $openSupportTickets, 'url' => '/support/tickets?status=open', 'class' => 'info', 'hint' => 'Açık destek ticket'],
            ['label' => 'AML uyarıları', 'value' => (int) $openAmlAlerts, 'url' => '/compliance/aml-alerts', 'class' => 'danger', 'hint' => 'Açık AML kaydı'],
            ['label' => 'Risk uyarıları', 'value' => (int) $openRiskAlerts, 'url' => '/compliance/risk-alerts', 'class' => 'purple', 'hint' => 'Açık risk sinyali'],
            ['label' => 'Bonus talepleri', 'value' => (int) $bonusClaims, 'url' => '/module?key=bonus-claims', 'class' => 'purple', 'hint' => 'Kampanya talebi var'],
        ];

        $financeSummary = [
            ['label' => 'Toplam yatırım', 'value' => $depositTotal, 'type' => 'money'],
            ['label' => 'Bugünkü yatırım', 'value' => $todayDepositTotal, 'type' => 'money'],
            ['label' => 'Toplam çekim', 'value' => $withdrawTotal, 'type' => 'money'],
            ['label' => 'Bekleyen işlem', 'value' => $pendingDeposits + $pendingWithdrawals, 'type' => 'number'],
        ];

        $memberSummary = [
            ['label' => 'Toplam üye', 'value' => $userCount],
            ['label' => 'Bugün kayıt', 'value' => $todayUsers],
            ['label' => 'Doğrulanmış', 'value' => $verifiedUsers],
            ['label' => 'Banlı', 'value' => $bannedUsers],
        ];

        $contentSystem = [
            ['name' => 'Promosyon', 'value' => (int) $activePromotions, 'label' => 'aktif', 'ok' => $activePromotions > 0, 'url' => '/module?key=promotions'],
            ['name' => 'Slider', 'value' => (int) $activeSliders, 'label' => 'aktif', 'ok' => $activeSliders > 0, 'url' => '/module?key=sliders'],
            ['name' => 'Auth Slider', 'value' => (int) $authSliders, 'label' => 'aktif', 'ok' => $authSliders > 0, 'url' => '/module?key=auth-sliders'],
            ['name' => 'Homepage Section', 'value' => (int) $homepageSections, 'label' => 'yayında', 'ok' => $homepageSections > 0, 'url' => '/homepage-sections'],
            ['name' => 'Aktif oyun', 'value' => (int) $activeGames, 'label' => 'oyun', 'ok' => $activeGames > 0, 'url' => '/module?key=bgaming-games'],
            ['name' => 'DB Modülü', 'value' => $tableCount, 'label' => 'tablo', 'ok' => $tableCount > 0, 'url' => '/dashboard'],
        ];

        $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'active' => 'dashboard',
            'crumbs' => 'Admin | Genel Bakış',
            'cards' => $kpiCards,
            'kpiCards' => $kpiCards,
            'tableCount' => $tableCount,
            'activeGames' => $activeGames,
            'depositTotal' => $depositTotal,
            'todayDepositTotal' => $todayDepositTotal,
            'withdrawTotal' => $withdrawTotal,
            'pendingDeposits' => $pendingDeposits,
            'pendingWithdrawals' => $pendingWithdrawals,
            'todayUsers' => $todayUsers,
            'verifiedUsers' => $verifiedUsers,
            'bannedUsers' => $bannedUsers,
            'pendingKyc' => $pendingKyc,
            'bonusClaims' => $bonusClaims,
            'activeBonuses' => $activeBonuses,
            'activePromotions' => $activePromotions,
            'activeSliders' => $activeSliders,
            'authSliders' => $authSliders,
            'homepageSections' => $homepageSections,
            'openOperations' => $openOperations,
            'operationQueue' => $operationQueue,
            'financeSummary' => $financeSummary,
            'memberSummary' => $memberSummary,
            'contentSystem' => $contentSystem,
            'selectedPeriod' => $dateRange['period'],
            'dateFrom' => $dateRange['from_date'],
            'dateTo' => $dateRange['to_date'],
            'sportStats' => $this->sportStats($dateRange),
            'casinoStats' => $this->casinoStats($dateRange),
            'bonusStats' => $this->bonusStats($dateRange),
            'depositRows' => $this->transactionRows('deposit', $dateRange),
            'withdrawRows' => $this->transactionRows('withdraw', $dateRange),
            'topCountries' => $this->topCountries(),
            'recentTransactions' => $this->recentTransactions(),
            'recentLogs' => $this->recentLogs(),
            'flash' => $this->pullFlash(),
            'quickActions' => $this->quickActions($pendingWithdrawals, $pendingKyc, $pendingDeposits, $bonusClaims),
            'healthItems' => $this->healthItems($activeGames, $activePromotions, $activeSliders, $authSliders, $homepageSections, $tableCount),
            'tasks' => [
                ['text' => 'Bekleyen çekim taleplerini kontrol et', 'badge' => (string) $pendingWithdrawals, 'class' => 'urgent'],
                ['text' => 'KYC taleplerini incele', 'badge' => (string) $pendingKyc, 'class' => 'upcoming'],
                ['text' => 'Bekleyen yatırımları kontrol et', 'badge' => (string) $pendingDeposits, 'class' => 'warn'],
                ['text' => 'Bonus taleplerini yönet', 'badge' => (string) $bonusClaims, 'class' => 'warn'],
                ['text' => 'Slider ve promosyon içeriklerini güncelle', 'badge' => 'CMS', 'class' => 'low'],
                ['text' => 'Sistem modülleri hazır', 'badge' => $tableCount . ' tablo', 'class' => 'done', 'done' => true],
            ],
        ]);
    }

    public function purgeCaches(): void
    {
        $this->requirePermission('dashboard');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        try {
            if (function_exists('metropol_notify_frontend_cms_purge')) {
                metropol_notify_frontend_cms_purge(null);
            }
            $this->flash('Tüm API önbellekleri temizlendi.');
        } catch (Throwable $throwable) {
            error_log('[AdminDashboardController] cache purge failed: ' . $throwable->getMessage());
            $this->flash('Önbellek temizleme başarısız oldu.');
        }

        $this->redirect(AdminAuth::url('/dashboard'));
    }

    private function sportStats(array $dateRange): array
    {
        $where = $this->dateCondition('created_at', $dateRange);
        $betTotal   = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM sportsbook_transactions WHERE txn_type = 'bet' AND {$where}");
        $winTotal   = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM sportsbook_transactions WHERE txn_type = 'win' AND {$where}");
        $cancelTotal = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM sportsbook_transactions WHERE txn_type = 'cancel' AND {$where}");
        $net = $betTotal - $winTotal - $cancelTotal;
        $betCount   = $this->scalar("SELECT COUNT(*) FROM sportsbook_transactions WHERE txn_type = 'bet' AND {$where}");
        $playerCount = $this->scalar("SELECT COUNT(DISTINCT user_id) FROM sportsbook_transactions WHERE {$where}");
        $rtp = $betTotal > 0 ? ($winTotal / $betTotal) * 100 : 0;

        $labels  = ['Bahis', 'Ödeme', 'İptal', 'İade', 'Net', 'Bahis Adedi', 'Oyuncu Adedi', 'RTP'];
        $formats = ['money', 'money', 'money', 'money', 'money', 'number', 'number', 'percent'];
        $values  = [$betTotal, $winTotal, $cancelTotal, 0, $net, $betCount, $playerCount, $rtp];
        $legend  = [
            ['label' => 'Bahis', 'value' => $betTotal, 'color' => '#3b82f6'],
            ['label' => 'Ödeme', 'value' => $winTotal, 'color' => '#22c55e'],
            ['label' => 'İptal', 'value' => $cancelTotal, 'color' => '#f59e0b'],
            ['label' => 'İade', 'value' => 0, 'color' => '#94a3b8'],
            ['label' => 'Net', 'value' => $net, 'color' => '#ef4444'],
        ];

        return $this->statsDataset($labels, $formats, $values, $legend) + [
            'tabs'    => ['Toplam'],
            'active_tab' => 'Toplam',
            'module_url' => '/module?key=sportsbook-transactions',
        ];
    }

    private function flash(string $message): void
    {
        $_SESSION['admin_dashboard_flash'] = $message;
    }

    private function pullFlash(): string
    {
        $message = (string) ($_SESSION['admin_dashboard_flash'] ?? '');
        unset($_SESSION['admin_dashboard_flash']);

        return $message;
    }

    private function casinoStats(array $dateRange): array
    {
        $bgamingWhere = $this->dateCondition('processed_at', $dateRange);
        $bgamingBet = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM bgaming_transactions WHERE txn_type IN ('bet','promo_bet') AND {$bgamingWhere}");
        $bgamingWin = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM bgaming_transactions WHERE txn_type IN ('win','promo_win','freespins_win') AND {$bgamingWhere}");
        $bet = $bgamingBet;
        $win = $bgamingWin;
        $net = $bet - $win;

        $bgamingBetCount = $this->scalar("SELECT COUNT(*) FROM bgaming_transactions WHERE txn_type IN ('bet','promo_bet') AND {$bgamingWhere}");
        $bgamingPlayers = $this->scalar("SELECT COUNT(DISTINCT user_id) FROM bgaming_transactions WHERE {$bgamingWhere}");
        $betCount = $bgamingBetCount;
        $playerCount = $bgamingPlayers;
        $rtp = $bet > 0 ? ($win / $bet) * 100 : 0;
        $bgamingRtp = $bgamingBet > 0 ? ($bgamingWin / $bgamingBet) * 100 : 0;

        $labels = ['Bahis', 'Ödeme', 'İptal', 'İade', 'Net', 'Bahis Adedi', 'Oyuncu Adedi', 'Kişi Başı', 'RTP'];
        $formats = ['money', 'money', 'money', 'money', 'money', 'number', 'number', 'money', 'percent'];
        $datasets = [
            'Slot' => $this->statsDataset($labels, $formats, [$bgamingBet, $bgamingWin, 0, 0, $bgamingBet - $bgamingWin, $bgamingBetCount, $bgamingPlayers, $bgamingPlayers > 0 ? $bgamingBet / $bgamingPlayers : 0, $bgamingRtp], [
                ['label' => 'Bahis', 'value' => $bgamingBet, 'color' => '#6366f1'],
                ['label' => 'Ödeme', 'value' => $bgamingWin, 'color' => '#22c55e'],
                ['label' => 'Net', 'value' => $bgamingBet - $bgamingWin, 'color' => '#3b82f6'],
            ]),
            'Sanal Spor' => $this->statsDataset($labels, $formats, [0, 0, 0, 0, 0, 0, 0, 0, 0], [
                ['label' => 'Sanal Spor', 'value' => 0, 'color' => '#22c55e'],
            ]),
            'Toplam' => $this->statsDataset($labels, $formats, [$bet, $win, 0, 0, $net, $betCount, $playerCount, $playerCount > 0 ? $bet / $playerCount : 0, $rtp], [
                ['label' => 'BGaming', 'value' => $bgamingBet, 'color' => '#6366f1'],
                ['label' => 'Net', 'value' => $net, 'color' => '#3b82f6'],
                ['label' => 'Sanal Spor', 'value' => 0, 'color' => '#22c55e'],
            ]),
        ];

        return $datasets['Toplam'] + [
            'tabs' => array_keys($datasets),
            'active_tab' => 'Toplam',
            'datasets' => $datasets,
            'module_url' => '/module?key=bgaming-transactions',
        ];
    }

    private function bonusStats(array $dateRange): array
    {
        $activeBonusWhere = $this->dateCondition('created_at', $dateRange);
        $claimWhere = $this->dateCondition('created_at', $dateRange);
        $adjustWhere = $this->dateCondition('created_at', $dateRange);
        $campaignWhere = $this->dateCondition('created_at', $dateRange);
        $depositBonus = $this->scalar("SELECT COALESCE(SUM(current_bonus_balance), 0) FROM user_active_bonuses WHERE {$activeBonusWhere} AND (LOWER(COALESCE(category, name, '')) LIKE '%deposit%' OR LOWER(COALESCE(name, '')) LIKE '%yatırım%')");
        $lossBonus = $this->scalar("SELECT COALESCE(SUM(requested_amount), 0) FROM bonus_claim_requests WHERE {$claimWhere} AND (LOWER(COALESCE(bonus_name, '')) LIKE '%loss%' OR LOWER(COALESCE(bonus_name, '')) LIKE '%kayıp%')");
        $cashBonus = $this->scalar("SELECT COALESCE(SUM(current_bonus_balance), 0) FROM user_active_bonuses WHERE {$activeBonusWhere} AND (LOWER(COALESCE(category, name, '')) LIKE '%cash%' OR LOWER(COALESCE(name, '')) LIKE '%nakit%')");
        $manualDiscount = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM admin_balance_adjustments WHERE wallet = 'bonus_balance' AND action = 'add' AND {$adjustWhere}");
        $manualFreespin = 0;
        $freespinBonus = $this->scalar("SELECT COALESCE(SUM(current_bonus_balance), 0) FROM user_active_bonuses WHERE {$activeBonusWhere} AND LOWER(COALESCE(category, name, '')) LIKE '%freespin%'");

        $labels = ['Bonus Tutarı', 'Oyuncu Adedi', 'Bonus Adedi', 'Spin Adedi', 'Aktarılan Tutar', 'Aktarılan Hesap Oyuncu', 'Aktif Bonus Adedi'];
        $formats = ['money', 'number', 'number', 'number', 'money', 'number', 'number'];
        $activePlayers = $this->scalar("SELECT COUNT(DISTINCT user_id) FROM user_active_bonuses WHERE {$activeBonusWhere}");
        $activeCount = $this->scalar("SELECT COUNT(*) FROM user_active_bonuses WHERE {$activeBonusWhere}");
        $transferPlayers = $this->scalar("SELECT COUNT(DISTINCT user_id) FROM admin_balance_adjustments WHERE wallet = 'bonus_balance' AND {$adjustWhere}");
        $totalActiveCount = $this->scalar("SELECT COUNT(*) FROM user_active_bonuses WHERE status IN ('active', 'pending') AND {$activeBonusWhere}");
        $datasets = [
            'Yatırım Bonusları' => $this->statsDataset($labels, $formats, [$depositBonus, $activePlayers, $activeCount, 0, 0, 0, $totalActiveCount], [['label' => 'Yatırım', 'value' => $depositBonus, 'color' => '#3b82f6']]),
            'Discount Bonusları' => $this->statsDataset($labels, $formats, [$lossBonus, $activePlayers, $activeCount, 0, 0, 0, $totalActiveCount], [['label' => 'Discount', 'value' => $lossBonus, 'color' => '#f59e0b']]),
            'Nakit Bonusları' => $this->statsDataset($labels, $formats, [$cashBonus, $activePlayers, $activeCount, 0, 0, 0, $totalActiveCount], [['label' => 'Nakit', 'value' => $cashBonus, 'color' => '#22c55e']]),
            'Manuel Discount' => $this->statsDataset($labels, $formats, [$manualDiscount, 0, 0, 0, $manualDiscount, $transferPlayers, $totalActiveCount], [['label' => 'Manual', 'value' => $manualDiscount, 'color' => '#8b5cf6']]),
            'Manuel Freespin' => $this->statsDataset($labels, $formats, [0, 0, 0, $manualFreespin, 0, 0, $totalActiveCount], [['label' => 'Manuel Freespin', 'value' => $manualFreespin, 'color' => '#ef4444']]),
            'Freespin Bonusları' => $this->statsDataset($labels, $formats, [$freespinBonus, $activePlayers, $activeCount, $manualFreespin, 0, 0, $totalActiveCount], [['label' => 'Freespin', 'value' => $freespinBonus, 'color' => '#06b6d4']]),
        ];

        return $datasets['Yatırım Bonusları'] + [
            'tabs' => array_keys($datasets),
            'active_tab' => 'Yatırım Bonusları',
            'datasets' => $datasets,
            'module_url' => '/module?key=active-bonuses',
        ];
    }

    private function statsDataset(array $labels, array $formats, array $values, array $legend): array
    {
        return [
            'labels' => $labels,
            'formats' => $formats,
            'values' => array_map('floatval', $values),
            'total' => array_sum(array_map('floatval', $values)),
            'legend' => $legend,
        ];
    }

    private function transactionRows(string $type, array $dateRange): array
    {
        try {
            $txWhere = $this->dateCondition('created_at', $dateRange);
            $stmt = AdminDatabase::pdo()->prepare(
                "SELECT created_at, method, username, fullname, amount, currency, status
                 FROM megapayz_transactions
                 WHERE type = :type AND {$txWhere}
                 ORDER BY created_at DESC
                 LIMIT 6"
            );
            $stmt->execute(['type' => $type]);

            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function dateRange(): array
    {
        $period = trim((string) ($_GET['period'] ?? 'month'));
        $allowed = ['yesterday', 'today', 'week', 'month', 'prev_month', 'custom'];
        if (!in_array($period, $allowed, true)) {
            $period = 'prev_month';
        }

        $today = new DateTimeImmutable('today');
        $start = $today->modify('first day of previous month');
        $end = $today->modify('last day of previous month')->setTime(23, 59, 59);

        if ($period === 'yesterday') {
            $start = $today->modify('-1 day');
            $end = $start->setTime(23, 59, 59);
        } elseif ($period === 'today') {
            $start = $today;
            $end = $today->setTime(23, 59, 59);
        } elseif ($period === 'week') {
            $start = $today->modify('monday this week');
            $end = $today->setTime(23, 59, 59);
        } elseif ($period === 'month') {
            $start = $today->modify('first day of this month');
            $end = $today->setTime(23, 59, 59);
        } elseif ($period === 'custom') {
            $from = $this->dateFromRequest('date_from');
            $to = $this->dateFromRequest('date_to');
            if ($from !== null && $to !== null) {
                $start = $from;
                $end = $to->setTime(23, 59, 59);
                if ($start > $end) {
                    [$start, $end] = [$end->setTime(0, 0), $start->setTime(23, 59, 59)];
                }
            } else {
                $period = 'month';
            }
        }

        return [
            'period' => $period,
            'start' => $start->setTime(0, 0),
            'end' => $end,
            'from_date' => $start->format('Y-m-d'),
            'to_date' => $end->format('Y-m-d'),
        ];
    }

    private function dateFromRequest(string $key): ?DateTimeImmutable
    {
        $value = trim((string) ($_GET[$key] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable ? $date : null;
    }

    private function dateCondition(string $column, array $dateRange): string
    {
        $start = $dateRange['start'] instanceof DateTimeImmutable
            ? $dateRange['start']->format('Y-m-d H:i:s')
            : date('Y-m-d 00:00:00');
        $end = $dateRange['end'] instanceof DateTimeImmutable
            ? $dateRange['end']->format('Y-m-d H:i:s')
            : date('Y-m-d 23:59:59');

        return sprintf(
            '(%s BETWEEN %s AND %s)',
            $column,
            AdminDatabase::pdo()->quote($start),
            AdminDatabase::pdo()->quote($end)
        );
    }

    private function scalar(string $sql): float
    {
        try {
            $value = AdminDatabase::pdo()->query($sql)->fetchColumn();

            return (float) $value;
        } catch (Throwable $e) {
            error_log('[AdminDashboard] scalar query failed: ' . $e->getMessage() . ' | SQL: ' . $sql);

            return 0.0;
        }
    }

    private function topCountries(): array
    {
        try {
            $stmt = AdminDatabase::pdo()->query(
                "SELECT COALESCE(NULLIF(country_name, ''), 'Bilinmeyen') AS country, COUNT(*) AS total
                 FROM visitor_logs
                 GROUP BY country
                 ORDER BY total DESC
                 LIMIT 4"
            );

            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function recentTransactions(): array
    {
        try {
            $stmt = AdminDatabase::pdo()->query(
                "SELECT CASE WHEN type = 'deposit' THEN 'Yatırım' ELSE 'Çekim' END AS kind,
                        COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.name, ''), ' ', COALESCE(u.surname, ''))), ''), t.username) AS member_name,
                        t.amount, t.status, t.created_at
                 FROM megapayz_transactions t
                 LEFT JOIN users u ON u.id = t.user_id
                 WHERE t.type IN ('deposit', 'withdraw')
                 ORDER BY t.created_at DESC
                 LIMIT 7"
            );

            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function recentLogs(): array
    {
        try {
            $stmt = AdminDatabase::pdo()->query(
                'SELECT admin_username, action, description, status, created_at
                 FROM admin_logs
                 ORDER BY created_at DESC
                 LIMIT 4'
            );

            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function quickActions(float $pendingWithdrawals, float $pendingKyc, float $pendingDeposits, float $bonusClaims): array
    {
        return [
            ['title' => 'Çekim Onayı', 'text' => 'Bekleyen çekimleri incele', 'url' => '/module?key=withdrawals', 'count' => $pendingWithdrawals, 'class' => 'danger'],
            ['title' => 'KYC Kontrol', 'text' => 'Kimlik doğrulama kuyruğu', 'url' => '/module?key=kyc', 'count' => $pendingKyc, 'class' => 'warning'],
            ['title' => 'Yatırım Takibi', 'text' => 'Pending yatırım işlemleri', 'url' => '/module?key=deposits', 'count' => $pendingDeposits, 'class' => 'primary'],
            ['title' => 'Bonus Talepleri', 'text' => 'Kampanya taleplerini yönet', 'url' => '/module?key=bonus-claims', 'count' => $bonusClaims, 'class' => 'purple'],
        ];
    }

    private function healthItems(float $activeGames, float $activePromotions, float $activeSliders, float $authSliders, float $homepageSections, int $tableCount): array
    {
        return [
            ['name' => 'Oyunlar', 'value' => (int) $activeGames, 'label' => 'aktif', 'ok' => $activeGames > 0],
            ['name' => 'Promosyonlar', 'value' => (int) $activePromotions, 'label' => 'aktif', 'ok' => $activePromotions > 0],
            ['name' => 'Sliderlar', 'value' => (int) $activeSliders, 'label' => 'canlı', 'ok' => $activeSliders > 0],
            ['name' => 'Auth sliderlar', 'value' => (int) $authSliders, 'label' => 'canlı', 'ok' => $authSliders > 0],
            ['name' => 'Homepage Section', 'value' => (int) $homepageSections, 'label' => 'yayında', 'ok' => $homepageSections > 0],
            ['name' => 'DB modülleri', 'value' => $tableCount, 'label' => 'tablo', 'ok' => $tableCount > 0],
        ];
    }
}
