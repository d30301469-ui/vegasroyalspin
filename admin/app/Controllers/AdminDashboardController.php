<?php

declare(strict_types=1);

final class AdminDashboardController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('dashboard');
        $dateRange = $this->dateRange();
        $payload = $this->buildDashboardPayload($dateRange, true);

        $this->view('dashboard/index', array_merge($payload, [
            'title' => 'Dashboard',
            'active' => 'dashboard',
            'crumbs' => 'Admin | Genel Bakış',
            'flash' => $this->pullFlash(),
            'liveUrl' => AdminAuth::url('/dashboard/live'),
        ]));
    }

    public function live(): void
    {
        $this->requirePermission('dashboard');
        $dateRange = $this->dateRange();
        $payload = $this->buildDashboardPayload($dateRange, false);

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo json_encode([
            'ok' => true,
            'period' => $dateRange['period'],
            'date_from' => $dateRange['from_date'],
            'date_to' => $dateRange['to_date'],
            'data' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardPayload(array $dateRange, bool $includeHeavy = true): array
    {
        $repo = new AdminTableRepository();
        $tables = $repo->tables();
        $tableCount = count($tables);
        $txWhere = $this->dateCondition('created_at', $dateRange);
        $userWhere = $this->dateCondition('created_at', $dateRange);
        $loginWhere = $this->dateCondition('COALESCE(last_login_at, updated_at, created_at)', $dateRange);
        $activeBonusWhere = $this->dateCondition('created_at', $dateRange);
        $adjustWhere = $this->dateCondition('created_at', $dateRange);
        $paidStatusSql = $this->paidStatusSql();
        $userCount = $this->scalar('SELECT COUNT(*) FROM users');
        $depositTotal = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE type = 'deposit' AND {$paidStatusSql} AND {$txWhere}");
        $todayDepositTotal = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE type = 'deposit' AND {$paidStatusSql} AND {$this->dateCondition('created_at', ['start' => new DateTimeImmutable('today'), 'end' => (new DateTimeImmutable('today'))->setTime(23, 59, 59)])}");
        $pendingDeposits = $this->scalar("SELECT COUNT(*) FROM megapayz_transactions WHERE type = 'deposit' AND status IN ('pending','processing')");
        $withdrawTotal = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE type = 'withdraw' AND {$paidStatusSql} AND {$txWhere}");
        $pendingWithdrawals = $this->scalar("SELECT COUNT(*) FROM megapayz_transactions WHERE type = 'withdraw' AND status IN ('pending','processing')");
        $activeGames = $this->scalar('SELECT COUNT(*) FROM bgaming_games WHERE is_active = 1')
            + $this->scalar('SELECT COUNT(*) FROM casino_aggregator_games WHERE is_active = 1')
            + $this->scalar("SELECT COUNT(*) FROM gsc_games WHERE is_active = 1");
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
        $nonBannedUsers = $this->scalar('SELECT COUNT(*) FROM users WHERE COALESCE(banned, 0) = 0');
        $onlineUsers = $this->onlineUsersCount();
        $onlineUserRows = $this->onlineUserRows();
        $activeUsers24h = $this->scalar(
            "SELECT COUNT(*) FROM users
             WHERE COALESCE(banned, 0) = 0
               AND COALESCE(last_login_at, updated_at, created_at) >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        $depositPlayers = $this->scalar("SELECT COUNT(DISTINCT user_id) FROM megapayz_transactions WHERE type = 'deposit' AND {$paidStatusSql} AND {$txWhere}");
        $withdrawPlayers = $this->scalar("SELECT COUNT(DISTINCT user_id) FROM megapayz_transactions WHERE type = 'withdraw' AND {$paidStatusSql} AND {$txWhere}");
        $adjustUpPlayers = $this->scalar("SELECT COUNT(DISTINCT user_id) FROM admin_balance_adjustments WHERE action = 'add' AND {$adjustWhere}");
        $adjustDownPlayers = $this->scalar("SELECT COUNT(DISTINCT user_id) FROM admin_balance_adjustments WHERE action = 'subtract' AND {$adjustWhere}");

        // clip-hero primary KPI order (10 cards)
        $kpiCards = [
            ['key' => 'deposit', 'label' => 'Toplam Yatırım', 'value' => $depositTotal, 'type' => 'money', 'count' => $depositPlayers, 'status' => 'success', 'icon' => 'deposit'],
            ['key' => 'withdraw', 'label' => 'Toplam Çekim', 'value' => $withdrawTotal, 'type' => 'money', 'count' => $withdrawPlayers, 'status' => 'danger', 'icon' => 'withdraw'],
            ['key' => 'adjust_up', 'label' => 'Hesap Düzelt (YUKARI)', 'value' => $adjustUp, 'type' => 'money', 'count' => $adjustUpPlayers, 'status' => 'primary', 'icon' => 'adjust-up'],
            ['key' => 'adjust_down', 'label' => 'Hesap Düzelt (AŞAĞI)', 'value' => -1 * $adjustDown, 'type' => 'money', 'count' => $adjustDownPlayers, 'status' => 'warning', 'icon' => 'adjust-down'],
            ['key' => 'players', 'label' => 'Toplam Oyuncu', 'value' => $userCount, 'type' => 'number', 'count' => $userCount, 'status' => 'purple', 'icon' => 'players'],
            ['key' => 'new_players', 'label' => 'Yeni Kayıt Oyuncular', 'value' => $newUsersInRange, 'type' => 'number', 'count' => $newUsersInRange, 'status' => 'info', 'icon' => 'new-players'],
            ['key' => 'login_users', 'label' => 'Giriş Yapan Kullanıcı', 'value' => $loginUsers, 'type' => 'number', 'count' => $loginUsers, 'status' => 'purple', 'icon' => 'login-users'],
            ['key' => 'active_players', 'label' => 'Çevrimiçi Kullanıcı', 'value' => $onlineUsers, 'type' => 'number', 'count' => $onlineUsers, 'count_label' => 'Son 10 dk', 'status' => 'success', 'icon' => 'active-players'],
            ['key' => 'wallet', 'label' => 'Toplam Oyuncu Bakiyesi', 'value' => $totalPlayerBalance, 'type' => 'money', 'count' => $userCount, 'status' => 'info', 'icon' => 'wallet'],
            ['key' => 'bonus', 'label' => 'Bonus Miktarı', 'value' => $totalBonusBalance, 'type' => 'money', 'count' => $activeBonuses, 'status' => 'danger', 'icon' => 'bonus'],
        ];

        $affUserWhere = $this->dateCondition('u.created_at', $dateRange);
        $affTxWhere = $this->dateCondition('t.created_at', $dateRange);
        $affPayoutWhere = $this->dateCondition('ap.requested_at', $dateRange);
        $affPaidSql = "t.status IN ('confirmed','approved','success','completed')";
        $affiliateRegs = $this->scalar(
            "SELECT COUNT(*) FROM users u
             WHERE COALESCE(u.referred_by_affiliate_id, 0) > 0 AND {$affUserWhere}"
        );
        $affiliateDepositTotal = $this->scalar(
            "SELECT COALESCE(SUM(t.amount), 0)
             FROM megapayz_transactions t
             INNER JOIN users u ON u.id = t.user_id
             WHERE COALESCE(u.referred_by_affiliate_id, 0) > 0
               AND t.type = 'deposit'
               AND {$affPaidSql}
               AND {$affTxWhere}"
        );
        $affiliateDepositUsers = $this->scalar(
            "SELECT COUNT(DISTINCT t.user_id)
             FROM megapayz_transactions t
             INNER JOIN users u ON u.id = t.user_id
             WHERE COALESCE(u.referred_by_affiliate_id, 0) > 0
               AND t.type = 'deposit'
               AND {$affPaidSql}
               AND {$affTxWhere}"
        );
        // Ortak (affiliate) komisyon çekimleri — yalnızca tamamlanan (onaylı) ödemeler.
        $affiliateWithdrawTotal = $this->scalar(
            "SELECT COALESCE(SUM(ap.amount), 0)
             FROM affiliate_payouts ap
             WHERE ap.status = 'completed'
               AND {$affPayoutWhere}"
        );
        $affiliateWithdrawCount = $this->scalar(
            "SELECT COUNT(*)
             FROM affiliate_payouts ap
             WHERE ap.status = 'completed'
               AND {$affPayoutWhere}"
        );
        $pendingAffiliatePayouts = $this->scalar(
            "SELECT COUNT(*) FROM affiliate_payouts
             WHERE status IN ('pending', 'approved')"
        );
        $openOperations += (int) $pendingAffiliatePayouts;

        $affiliateCards = [
            ['key' => 'aff_regs', 'label' => 'Affiliate Kayıt', 'value' => $affiliateRegs, 'type' => 'number', 'count' => $affiliateRegs, 'count_label' => 'Kayıt', 'status' => 'purple', 'icon' => 'new-players'],
            ['key' => 'aff_deposit', 'label' => 'Affiliate Yatırım', 'value' => $affiliateDepositTotal, 'type' => 'money', 'count' => $affiliateDepositUsers, 'count_label' => 'Oyuncu', 'status' => 'success', 'icon' => 'deposit'],
            ['key' => 'aff_withdraw', 'label' => 'Affiliate Çekim', 'value' => $affiliateWithdrawTotal, 'type' => 'money', 'count' => $affiliateWithdrawCount, 'count_label' => 'Talep', 'status' => 'danger', 'icon' => 'withdraw'],
        ];

        $operationQueue = [
            ['key' => 'pending_deposits', 'label' => 'Bekleyen yatırım', 'value' => (int) $pendingDeposits, 'url' => '/module?key=deposits', 'class' => 'primary', 'hint' => 'Yatırım onayı bekliyor'],
            ['key' => 'pending_withdrawals', 'label' => 'Bekleyen çekim', 'value' => (int) $pendingWithdrawals, 'url' => '/module?key=withdrawals', 'class' => 'danger', 'hint' => 'Çekim onayı bekliyor'],
            ['key' => 'pending_affiliate_payouts', 'label' => 'Affiliate ödeme talebi', 'value' => (int) $pendingAffiliatePayouts, 'url' => '/affiliate/payouts', 'class' => 'warning', 'hint' => 'Ortak komisyon çekimi bekliyor'],
            ['key' => 'pending_kyc', 'label' => 'KYC kuyruğu', 'value' => (int) $pendingKyc, 'url' => '/kyc/review', 'class' => 'warning', 'hint' => 'Kimlik doğrulama bekliyor'],
            ['key' => 'support', 'label' => 'Destek talepleri', 'value' => (int) $openSupportTickets, 'url' => '/support/tickets?status=open', 'class' => 'info', 'hint' => 'Açık destek ticket'],
            ['key' => 'aml', 'label' => 'AML uyarıları', 'value' => (int) $openAmlAlerts, 'url' => '/compliance/aml-alerts', 'class' => 'danger', 'hint' => 'Açık AML kaydı'],
            ['key' => 'risk', 'label' => 'Risk uyarıları', 'value' => (int) $openRiskAlerts, 'url' => '/compliance/risk-alerts', 'class' => 'purple', 'hint' => 'Açık risk sinyali'],
            ['key' => 'bonus_claims', 'label' => 'Bonus talepleri', 'value' => (int) $bonusClaims, 'url' => '/module?key=bonus-claims', 'class' => 'purple', 'hint' => 'Kampanya talebi var'],
        ];

        $financeSummary = [
            ['label' => 'Toplam yatırım', 'value' => $depositTotal, 'type' => 'money'],
            ['label' => 'Bugünkü yatırım', 'value' => $todayDepositTotal, 'type' => 'money'],
            ['label' => 'Toplam çekim', 'value' => $withdrawTotal, 'type' => 'money'],
            ['label' => 'Bekleyen işlem', 'value' => $pendingDeposits + $pendingWithdrawals, 'type' => 'number'],
        ];

        $memberSummary = [
            ['label' => 'Toplam üye', 'value' => $userCount],
            ['label' => 'Çevrimiçi', 'value' => $onlineUsers],
            ['label' => 'Aktif (24s)', 'value' => $activeUsers24h],
            ['label' => 'Banlı olmayan', 'value' => $nonBannedUsers],
            ['label' => 'Bugün kayıt', 'value' => $todayUsers],
            ['label' => 'Doğrulanmış', 'value' => $verifiedUsers],
            ['label' => 'Banlı', 'value' => $bannedUsers],
        ];

        $contentSystem = [
            ['name' => 'Promosyon', 'value' => (int) $activePromotions, 'label' => 'aktif', 'ok' => $activePromotions > 0, 'url' => '/promotions'],
            ['name' => 'Slider', 'value' => (int) $activeSliders, 'label' => 'aktif', 'ok' => $activeSliders > 0, 'url' => '/module?key=sliders'],
            ['name' => 'Auth Slider', 'value' => (int) $authSliders, 'label' => 'aktif', 'ok' => $authSliders > 0, 'url' => '/module?key=auth-sliders'],
            ['name' => 'Homepage Section', 'value' => (int) $homepageSections, 'label' => 'yayında', 'ok' => $homepageSections > 0, 'url' => '/homepage-sections'],
            ['name' => 'Aktif oyun', 'value' => (int) $activeGames, 'label' => 'oyun', 'ok' => $activeGames > 0, 'url' => '/module?key=bgaming-games'],
            ['name' => 'DB Modülü', 'value' => $tableCount, 'label' => 'tablo', 'ok' => $tableCount > 0, 'url' => '/dashboard'],
        ];

        $quickActions = $this->quickActions($pendingWithdrawals, $pendingKyc, $pendingDeposits, $bonusClaims);

        $payload = [
            'generated_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
            'cards' => $kpiCards,
            'kpiCards' => $kpiCards,
            'affiliateCards' => $affiliateCards,
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
            'onlineUsers' => $onlineUsers,
            'onlineUserRows' => $onlineUserRows,
            'activeUsers24h' => $activeUsers24h,
            'quickActions' => $quickActions,
            'healthItems' => $this->healthItems($activeGames, $activePromotions, $activeSliders, $authSliders, $homepageSections, $tableCount),
            'tasks' => [
                ['text' => 'Bekleyen çekim taleplerini kontrol et', 'badge' => (string) $pendingWithdrawals, 'class' => 'urgent'],
                ['text' => 'KYC taleplerini incele', 'badge' => (string) $pendingKyc, 'class' => 'upcoming'],
                ['text' => 'Bekleyen yatırımları kontrol et', 'badge' => (string) $pendingDeposits, 'class' => 'warn'],
                ['text' => 'Bonus taleplerini yönet', 'badge' => (string) $bonusClaims, 'class' => 'warn'],
                ['text' => 'Slider ve promosyon içeriklerini güncelle', 'badge' => 'CMS', 'class' => 'low'],
                ['text' => 'Sistem modülleri hazır', 'badge' => $tableCount . ' tablo', 'class' => 'done', 'done' => true],
            ],
        ];

        if ($includeHeavy) {
            $payload['topCountries'] = $this->topCountries();
            $payload['visitorMap'] = $this->visitorMapData($dateRange);
            $payload['recentTransactions'] = $this->recentTransactions();
            $payload['recentLogs'] = $this->recentLogs();
        }

        return $payload;
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
            if (function_exists('notify_frontend_cms_purge')) {
                notify_frontend_cms_purge(null);
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
        $bgamingBetCount = $this->scalar("SELECT COUNT(*) FROM bgaming_transactions WHERE txn_type IN ('bet','promo_bet') AND {$bgamingWhere}");
        $bgamingPlayers = $this->scalar("SELECT COUNT(DISTINCT user_id) FROM bgaming_transactions WHERE {$bgamingWhere}");
        $bgamingRtp = $bgamingBet > 0 ? ($bgamingWin / $bgamingBet) * 100 : 0;
        $bgamingNet = $bgamingBet - $bgamingWin;

        $aggWhere = $this->dateCondition('created_at', $dateRange);
        $aggBet = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM casino_aggregator_transactions WHERE txn_type = 'bet' AND {$aggWhere}");
        $aggWin = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM casino_aggregator_transactions WHERE txn_type = 'win' AND {$aggWhere}");
        $aggCancel = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM casino_aggregator_transactions WHERE txn_type = 'cancel' AND {$aggWhere}");
        $aggBetCount = $this->scalar("SELECT COUNT(*) FROM casino_aggregator_transactions WHERE txn_type = 'bet' AND {$aggWhere}");
        $aggPlayers = $this->scalar("SELECT COUNT(DISTINCT user_id) FROM casino_aggregator_transactions WHERE {$aggWhere}");
        $aggNet = $aggBet - $aggWin - $aggCancel;
        $aggRtp = $aggBet > 0 ? ($aggWin / $aggBet) * 100 : 0;

        // GSC+ live casino (and premium) wallet ledger — previously omitted from dashboard.
        $gscWhere = $this->dateCondition('created_at', $dateRange);
        $gscBet = $this->scalar("SELECT COALESCE(SUM(bet_amount), 0) FROM gsc_transactions WHERE UPPER(action) = 'BET' AND {$gscWhere}");
        $gscWin = $this->scalar(
            "SELECT COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0)
             FROM gsc_transactions
             WHERE UPPER(action) IN ('SETTLED','BONUS','JACKPOT','FREEBET','ADJUSTMENT') AND {$gscWhere}"
        );
        $gscCancel = $this->scalar(
            "SELECT COALESCE(SUM(ABS(amount)), 0)
             FROM gsc_transactions
             WHERE UPPER(action) IN ('CANCEL','ROLLBACK') AND {$gscWhere}"
        );
        $gscBetCount = $this->scalar("SELECT COUNT(*) FROM gsc_transactions WHERE UPPER(action) = 'BET' AND {$gscWhere}");
        $gscPlayers = $this->scalar("SELECT COUNT(DISTINCT user_id) FROM gsc_transactions WHERE {$gscWhere}");
        $gscNet = $gscBet - $gscWin - $gscCancel;
        $gscRtp = $gscBet > 0 ? ($gscWin / $gscBet) * 100 : 0;

        $bet = $bgamingBet + $aggBet + $gscBet;
        $win = $bgamingWin + $aggWin + $gscWin;
        $cancel = $aggCancel + $gscCancel;
        $net = $bet - $win - $cancel;
        $betCount = $bgamingBetCount + $aggBetCount + $gscBetCount;
        $playerCount = $this->scalar(
            "SELECT COUNT(DISTINCT user_id) FROM (
                SELECT user_id FROM bgaming_transactions WHERE {$bgamingWhere}
                UNION
                SELECT user_id FROM casino_aggregator_transactions WHERE {$aggWhere}
                UNION
                SELECT user_id FROM gsc_transactions WHERE {$gscWhere}
            ) AS casino_players"
        );
        $rtp = $bet > 0 ? ($win / $bet) * 100 : 0;

        $labels = ['Bahis', 'Ödeme', 'İptal', 'İade', 'Net', 'Bahis Adedi', 'Oyuncu Adedi', 'Kişi Başı', 'RTP'];
        $formats = ['money', 'money', 'money', 'money', 'money', 'number', 'number', 'money', 'percent'];
        $datasets = [
            'Slot' => $this->statsDataset($labels, $formats, [$bgamingBet, $bgamingWin, 0, 0, $bgamingNet, $bgamingBetCount, $bgamingPlayers, $bgamingPlayers > 0 ? $bgamingBet / $bgamingPlayers : 0, $bgamingRtp], [
                ['label' => 'Bahis', 'value' => $bgamingBet, 'color' => '#6366f1'],
                ['label' => 'Ödeme', 'value' => $bgamingWin, 'color' => '#22c55e'],
                ['label' => 'Net', 'value' => $bgamingNet, 'color' => '#3b82f6'],
            ]),
            'Aggregator' => $this->statsDataset($labels, $formats, [$aggBet, $aggWin, $aggCancel, 0, $aggNet, $aggBetCount, $aggPlayers, $aggPlayers > 0 ? $aggBet / $aggPlayers : 0, $aggRtp], [
                ['label' => 'Bahis', 'value' => $aggBet, 'color' => '#8b5cf6'],
                ['label' => 'Ödeme', 'value' => $aggWin, 'color' => '#22c55e'],
                ['label' => 'İptal', 'value' => $aggCancel, 'color' => '#f59e0b'],
                ['label' => 'Net', 'value' => $aggNet, 'color' => '#3b82f6'],
            ]),
            'Live Casino' => $this->statsDataset($labels, $formats, [$gscBet, $gscWin, $gscCancel, 0, $gscNet, $gscBetCount, $gscPlayers, $gscPlayers > 0 ? $gscBet / $gscPlayers : 0, $gscRtp], [
                ['label' => 'Bahis', 'value' => $gscBet, 'color' => '#ef4444'],
                ['label' => 'Ödeme', 'value' => $gscWin, 'color' => '#22c55e'],
                ['label' => 'İptal', 'value' => $gscCancel, 'color' => '#f59e0b'],
                ['label' => 'Net', 'value' => $gscNet, 'color' => '#3b82f6'],
            ]),
            'Toplam' => $this->statsDataset($labels, $formats, [$bet, $win, $cancel, 0, $net, $betCount, $playerCount, $playerCount > 0 ? $bet / $playerCount : 0, $rtp], [
                ['label' => 'BGaming', 'value' => $bgamingBet, 'color' => '#6366f1'],
                ['label' => 'Aggregator', 'value' => $aggBet, 'color' => '#8b5cf6'],
                ['label' => 'Live Casino', 'value' => $gscBet, 'color' => '#ef4444'],
                ['label' => 'Net', 'value' => $net, 'color' => '#3b82f6'],
            ]),
        ];

        return $datasets['Toplam'] + [
            'tabs' => array_keys($datasets),
            'active_tab' => 'Toplam',
            'datasets' => $datasets,
            'module_url' => '/module?key=casino-aggregator-transactions',
        ];
    }

    private function bonusStats(array $dateRange): array
    {
        $activeBonusWhere = $this->dateCondition('created_at', $dateRange);
        $claimWhere       = $this->dateCondition('created_at', $dateRange);
        $adjustWhere      = $this->dateCondition('created_at', $dateRange);

        $depositBonus    = $this->scalar("SELECT COALESCE(SUM(current_bonus_balance), 0) FROM user_active_bonuses WHERE {$activeBonusWhere} AND (LOWER(COALESCE(category, name, '')) LIKE '%deposit%' OR LOWER(COALESCE(name, '')) LIKE '%yatırım%')");
        $lossBonus       = $this->scalar("SELECT COALESCE(SUM(requested_amount), 0) FROM bonus_claim_requests WHERE {$claimWhere} AND (LOWER(COALESCE(bonus_name, '')) LIKE '%loss%' OR LOWER(COALESCE(bonus_name, '')) LIKE '%kayıp%')");
        $cashBonus       = $this->scalar("SELECT COALESCE(SUM(current_bonus_balance), 0) FROM user_active_bonuses WHERE {$activeBonusWhere} AND (LOWER(COALESCE(category, name, '')) LIKE '%cash%' OR LOWER(COALESCE(name, '')) LIKE '%nakit%')");
        $manualDiscount  = $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM admin_balance_adjustments WHERE wallet = 'bonus_balance' AND action = 'add' AND {$adjustWhere}");
        $freespinBonus   = $this->scalar("SELECT COALESCE(SUM(current_bonus_balance), 0) FROM user_active_bonuses WHERE {$activeBonusWhere} AND LOWER(COALESCE(category, name, '')) LIKE '%freespin%'");
        $activePlayers   = $this->scalar("SELECT COUNT(DISTINCT user_id) FROM user_active_bonuses WHERE {$activeBonusWhere}");
        $activeCount     = $this->scalar("SELECT COUNT(*) FROM user_active_bonuses WHERE {$activeBonusWhere}");
        $transferPlayers = $this->scalar("SELECT COUNT(DISTINCT user_id) FROM admin_balance_adjustments WHERE wallet = 'bonus_balance' AND {$adjustWhere}");
        $totalActiveCount = $this->scalar("SELECT COUNT(*) FROM user_active_bonuses WHERE status IN ('active', 'pending') AND {$activeBonusWhere}");

        $totalBonus = $depositBonus + $lossBonus + $cashBonus + $manualDiscount + $freespinBonus;

        // Multi-segment donut legend: one segment per bonus category
        $legend = [
            ['label' => 'Yatırım',     'value' => $depositBonus,   'color' => '#3b82f6'],
            ['label' => 'Discount',    'value' => $lossBonus,      'color' => '#f59e0b'],
            ['label' => 'Nakit',       'value' => $cashBonus,      'color' => '#22c55e'],
            ['label' => 'Manual',      'value' => $manualDiscount, 'color' => '#8b5cf6'],
            ['label' => 'Freespin',    'value' => $freespinBonus,  'color' => '#06b6d4'],
        ];

        $labels  = ['Toplam Bonus', 'Oyuncu Adedi', 'Bonus Adedi', 'Manuel Aktarım', 'Aktarılan Oyuncu', 'Aktif Bonus'];
        $formats = ['money', 'number', 'number', 'money', 'number', 'number'];
        $values  = [$totalBonus, $activePlayers, $activeCount, $manualDiscount, $transferPlayers, $totalActiveCount];

        return $this->statsDataset($labels, $formats, $values, $legend) + [
            'tabs'       => ['Genel'],
            'active_tab' => 'Genel',
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

    /**
     * Site-wide online members: valid JWT with recent heartbeat (session-heartbeat.js ~3 dk).
     */
    private function onlineUsersCount(): float
    {
        return $this->scalar(
            "SELECT COUNT(DISTINCT t.user_id)
             FROM member_jwt_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.revoked_at IS NULL
               AND t.expires_at >= NOW()
               AND COALESCE(t.last_seen_at, t.issued_at) >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
               AND COALESCE(u.banned, 0) = 0"
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function onlineUserRows(): array
    {
        try {
            $stmt = AdminDatabase::pdo()->query(
                "SELECT u.id AS user_id,
                        u.username,
                        COALESCE(u.balance, 0) AS balance,
                        COALESCE(u.bonus_balance, 0) AS bonus_balance,
                        MAX(COALESCE(t.last_seen_at, t.issued_at)) AS last_seen_at
                 FROM member_jwt_tokens t
                 INNER JOIN users u ON u.id = t.user_id
                 WHERE t.revoked_at IS NULL
                   AND t.expires_at >= NOW()
                   AND COALESCE(t.last_seen_at, t.issued_at) >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                   AND COALESCE(u.banned, 0) = 0
                 GROUP BY u.id, u.username, u.balance, u.bonus_balance
                 ORDER BY last_seen_at DESC
                 LIMIT 40"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!is_array($rows)) {
                return [];
            }

            return array_map(static function (array $row): array {
                $userId = (int) ($row['user_id'] ?? 0);

                return [
                    'user_id' => $userId,
                    'username' => (string) ($row['username'] ?? ''),
                    'balance' => (float) ($row['balance'] ?? 0),
                    'bonus_balance' => (float) ($row['bonus_balance'] ?? 0),
                    'last_seen_at' => (string) ($row['last_seen_at'] ?? ''),
                    'url' => $userId > 0 ? AdminAuth::url('/user?id=' . $userId) : '',
                ];
            }, $rows);
        } catch (Throwable) {
            return [];
        }
    }

    private function dateRange(): array
    {
        $period = trim((string) ($_GET['period'] ?? 'all'));
        $allowed = ['all', 'yesterday', 'today', 'week', 'month', 'prev_month', 'custom'];
        if (!in_array($period, $allowed, true)) {
            $period = 'all';
        }

        $today = new DateTimeImmutable('today');
        $start = new DateTimeImmutable('2020-01-01');
        $end = $today->setTime(23, 59, 59);

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
        } elseif ($period === 'prev_month') {
            $start = $today->modify('first day of previous month');
            $end = $today->modify('last day of previous month')->setTime(23, 59, 59);
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
                $period = 'all';
                $start = new DateTimeImmutable('2020-01-01');
                $end = $today->setTime(23, 59, 59);
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

    private function paidStatusSql(): string
    {
        return "status IN ('confirmed','approved','success','completed')";
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
                "SELECT COALESCE(NULLIF(country_name, ''), 'Bilinmeyen') AS country,
                        COALESCE(country_code, '') AS country_code,
                        COUNT(*) AS total,
                        COALESCE(AVG(NULLIF(lat, 0)), 0) AS lat,
                        COALESCE(AVG(NULLIF(lon, 0)), 0) AS lon
                 FROM visitor_logs
                 WHERE country_name IS NOT NULL AND country_name != ''
                 GROUP BY country, country_code
                 ORDER BY total DESC
                 LIMIT 80"
            );

            $merged = VisitorCountryNormalizer::mergeCountryRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total');

            return array_slice($merged, 0, 12);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Dashboard dünya haritası için GeoIP noktaları.
     *
     * @return array{points: list<array<string,mixed>>, total: int, countries: int}
     */
    private function visitorMapData(array $dateRange): array
    {
        $points = [];
        $total = 0;
        $countries = 0;
        $where = $this->dateCondition('created_at', $dateRange);

        try {
            $pdo = AdminDatabase::pdo();
            $total = (int) $pdo->query("SELECT COUNT(*) FROM visitor_logs WHERE {$where}")->fetchColumn();

            $countryRows = $pdo->query(
                "SELECT COALESCE(NULLIF(country_name, ''), 'Bilinmeyen') AS label,
                        COALESCE(country_code, '') AS country_code,
                        COUNT(*) AS visitors,
                        COALESCE(AVG(NULLIF(lat, 0)), 0) AS lat,
                        COALESCE(AVG(NULLIF(lon, 0)), 0) AS lon
                 FROM visitor_logs
                 WHERE {$where} AND country_name IS NOT NULL AND country_name != ''
                 GROUP BY label, country_code
                 ORDER BY visitors DESC
                 LIMIT 120"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $mergedCountries = VisitorCountryNormalizer::mergeCountryRows($countryRows, 'visitors');
            $countries = count($mergedCountries);

            foreach ($mergedCountries as $row) {
                $lat = (float) ($row['lat'] ?? 0);
                $lon = (float) ($row['lon'] ?? 0);
                if (abs($lat) < 0.0001 && abs($lon) < 0.0001) {
                    continue;
                }
                $points[] = [
                    'type' => 'country',
                    'label' => (string) ($row['country'] ?? 'Ülke'),
                    'visitors' => (int) ($row['visitors'] ?? 0),
                    'lat' => $lat,
                    'lon' => $lon,
                ];
            }

            $recent = $pdo->query(
                "SELECT COALESCE(NULLIF(city, ''), country_name, 'Ziyaret') AS label,
                        country_name,
                        country_code,
                        ip_address,
                        lat,
                        lon,
                        created_at
                 FROM visitor_logs
                 WHERE {$where}
                   AND ABS(COALESCE(lat, 0)) > 0.0001
                   AND ABS(COALESCE(lon, 0)) > 0.0001
                 ORDER BY created_at DESC
                 LIMIT 60"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($recent as $row) {
                $countryLabel = VisitorCountryNormalizer::canonicalName(
                    (string) ($row['country_code'] ?? ''),
                    (string) ($row['country_name'] ?? '')
                );
                $points[] = [
                    'type' => 'visit',
                    'label' => trim((string) ($row['label'] ?? 'Ziyaret')),
                    'visitors' => 1,
                    'lat' => (float) ($row['lat'] ?? 0),
                    'lon' => (float) ($row['lon'] ?? 0),
                    'ip' => (string) ($row['ip_address'] ?? ''),
                    'at' => (string) ($row['created_at'] ?? ''),
                    'country' => $countryLabel,
                ];
            }
        } catch (Throwable $exception) {
            error_log('[AdminDashboardController] visitorMapData failed: ' . $exception->getMessage());
        }

        return [
            'points' => $points,
            'total' => $total,
            'countries' => $countries,
        ];
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
