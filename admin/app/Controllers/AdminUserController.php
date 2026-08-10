<?php

declare(strict_types=1);

final class AdminUserController extends AdminController
{
    public function detail(): void
    {
        $this->requirePermission('users');
        $userId = max(0, (int) ($_GET['id'] ?? 0));
        $user = $this->user($userId);
        if ($user === null) {
            $_SESSION['admin_flash'] = 'Kullanıcı bulunamadı.';
            $this->redirect(AdminAuth::url('/module?key=users'));
        }

        $this->ensureAdjustmentTable();
        MegaPayzService::bootstrap(AdminDatabase::pdo());
        $sportsbookCoupons = $this->rows(
            "SELECT
                id,
                txn_code AS transaction_id,
                COALESCE(wager_id, '-') AS coupon_id,
                COALESCE(round_id, '-') AS round_id,
                COALESCE(vendor_code, '-') AS vendor_code,
                COALESCE(game_code, '-') AS game_code,
                txn_type,
                amount,
                before_balance,
                after_balance,
                currency,
                CASE WHEN is_finished = 1 THEN 'completed' ELSE 'active' END AS status,
                detail,
                raw_payload,
                created_at
             FROM sportsbook_transactions
             WHERE user_id = :user_id
             ORDER BY id DESC
             LIMIT 100",
            $userId
        );
        $sportsbookCoupons = $this->formatSportsbookCoupons($sportsbookCoupons);

        $accountFreeze = null;
        try {
            $freezeStmt = AdminDatabase::pdo()->prepare(
                'SELECT id, user_id, reason, frozen_by, frozen_at, created_at, updated_at
                 FROM user_account_freeze WHERE user_id = :user_id LIMIT 1'
            );
            $freezeStmt->execute(['user_id' => $userId]);
            $freezeRow = $freezeStmt->fetch(PDO::FETCH_ASSOC);
            $accountFreeze = is_array($freezeRow) ? $freezeRow : null;
        } catch (Throwable) {
            $accountFreeze = null;
        }

        $this->view('users/detail', [
            'title' => 'Kullanıcı Detayı',
            'active' => 'datatable',
            'moduleKey' => 'users',
            'crumbs' => 'Members | Users | Detay',
            'user' => $user,
            'accountFreeze' => $accountFreeze,
            'summary' => $this->summary($userId),
            'deposits' => $this->rows("SELECT id, method, 'megapayz' AS provider, amount, fee, status, trx, created_at, updated_at FROM megapayz_transactions WHERE user_id = :user_id AND type = 'deposit' ORDER BY id DESC LIMIT 30", $userId),
            'withdrawals' => $this->rows("SELECT id, method, 'megapayz' AS provider, amount, fee, currency, status, NULL AS admin_status, trx, created_at, updated_at FROM megapayz_transactions WHERE user_id = :user_id AND type = 'withdraw' ORDER BY id DESC LIMIT 30", $userId),
            'adjustments' => $this->rows('SELECT id, wallet, action, amount, before_balance, after_balance, note, admin_username, created_at FROM admin_balance_adjustments WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 30', $userId),
            'games' => $this->gameRows($userId),
            'sportsbookCoupons' => $sportsbookCoupons,
            'bonusClaims' => $this->rows('SELECT id, bonus_name, requested_amount, status, processed_by, processed_at, created_at FROM bonus_claim_requests WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 20', $userId),
            'activeBonuses' => $this->rows('SELECT id, name, initial_amount, current_bonus_balance, wagering_requirement, wagering_target, total_bet_amount, is_complete, status, deadline, created_at FROM user_active_bonuses WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 20', $userId),
            'freespins' => $this->freespinsForUser($userId),
            'accountWagering' => WageringService::accountProgress(AdminDatabase::pdo(), $userId),
            'activeWalletMode' => WageringService::activeWalletMode(AdminDatabase::pdo(), $userId),
            'notes' => $this->notesForUser($userId),
            'sessions' => $this->sessionsForUser($userId),
            'flash' => $this->pullFlash(),
        ]);
    }

    public function storeNote(): void
    {
        $this->requirePermission('users');
        $this->ensurePost();
        $userId = max(0, (int) ($_POST['user_id'] ?? 0));
        $content = trim((string) ($_POST['content'] ?? ''));
        if ($userId <= 0 || $content === '') {
            $this->flash('Not içeriği boş olamaz.');
            $this->redirect(AdminAuth::url('/user?id=' . rawurlencode((string) $userId)));
        }

        $this->ensureNotesTable();
        $admin = AdminAuth::user();
        try {
            AdminDatabase::pdo()->prepare(
                'INSERT INTO admin_user_notes (user_id, admin_id, content) VALUES (:user_id, :admin_id, :content)'
            )->execute([
                'user_id' => $userId,
                'admin_id' => (int) ($admin['id'] ?? 0) ?: null,
                'content' => $content,
            ]);
            AdminAuth::writeLog(AdminAuth::userName(), 'user_note_add', 'users', 'success', (string) $userId);
            $this->flash('Not eklendi.');
        } catch (Throwable $exception) {
            $this->flash('Not eklenemedi: ' . $exception->getMessage());
        }

        $this->redirect(AdminAuth::url('/user?id=' . rawurlencode((string) $userId)));
    }

    public function unfreeze(): void
    {
        $this->requireAuth();
        if (!AdminAuth::can('users') && !AdminAuth::can('frozen-accounts')) {
            $this->forbidden();
        }
        $this->ensurePost();

        $userId = max(0, (int) ($_POST['user_id'] ?? 0));
        $redirectTo = trim((string) ($_POST['redirect'] ?? 'frozen'));
        if ($userId <= 0) {
            $this->flash('Geçersiz kullanıcı.');
            $this->redirect(AdminAuth::url('/module?key=frozen-accounts'));
        }

        try {
            $stmt = AdminDatabase::pdo()->prepare('DELETE FROM user_account_freeze WHERE user_id = :user_id');
            $stmt->execute(['user_id' => $userId]);
            if ($stmt->rowCount() > 0) {
                AdminAuth::writeLog(AdminAuth::userName(), 'user_unfreeze', 'users', 'success', (string) $userId);
                $this->flash('Hesap dondurması kaldırıldı.');
            } else {
                $this->flash('Dondurma kaydı bulunamadı.');
            }
        } catch (Throwable $exception) {
            $this->flash('Dondurma kaldırılamadı: ' . $exception->getMessage());
        }

        if ($redirectTo === 'user') {
            $this->redirect(AdminAuth::url('/user?id=' . rawurlencode((string) $userId)));
        }
        if ($redirectTo === 'risk') {
            $this->redirect(AdminAuth::url('/compliance/risk-analysis'));
        }
        $this->redirect(AdminAuth::url('/module?key=frozen-accounts'));
    }

    public function freeze(): void
    {
        $this->requireAuth();
        if (!AdminAuth::can('users') && !AdminAuth::can('frozen-accounts')) {
            $this->forbidden();
        }
        $this->ensurePost();

        $userId = max(0, (int) ($_POST['user_id'] ?? 0));
        $reason = trim((string) ($_POST['reason'] ?? 'Admin tarafından donduruldu'));
        if ($userId <= 0) {
            $this->flash('Geçersiz kullanıcı.');
            $this->redirect(AdminAuth::url('/module?key=users'));
        }

        try {
            AdminDatabase::pdo()->prepare(
                'INSERT INTO user_account_freeze (user_id, reason, frozen_by, frozen_at)
                 VALUES (:user_id, :reason, :frozen_by, NOW())
                 ON DUPLICATE KEY UPDATE reason = VALUES(reason), frozen_by = VALUES(frozen_by), frozen_at = NOW(), updated_at = NOW()'
            )->execute([
                'user_id' => $userId,
                'reason' => $reason !== '' ? $reason : 'Admin tarafından donduruldu',
                'frozen_by' => AdminAuth::userName(),
            ]);
            AdminAuth::writeLog(AdminAuth::userName(), 'user_freeze', 'users', 'success', (string) $userId);
            $this->flash('Hesap donduruldu.');
        } catch (Throwable $exception) {
            $this->flash('Hesap dondurulamadı: ' . $exception->getMessage());
        }

        $this->redirect(AdminAuth::url('/user?id=' . rawurlencode((string) $userId)));
    }

    public function create(): void
    {
        $this->requirePermission('users');
        $data = [
            'title' => 'Oyuncu Ekle',
            'active' => 'datatable',
            'moduleKey' => 'users',
            'crumbs' => 'Members | Users | Ekle',
            'user' => [
                'country' => 'TR',
                'gender' => 'Erkek',
                'is_verified' => 1,
            ],
            'mode' => 'create',
            'flash' => $this->pullFlash(),
        ];

        $this->view('users/create', $data);
    }

    public function store(): void
    {
        $this->requirePermission('users');
        $this->ensurePost();

        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'surname' => trim((string) ($_POST['surname'] ?? '')),
            'username' => trim((string) ($_POST['username'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'identity_number' => trim((string) ($_POST['identity_number'] ?? '')),
            'gender' => trim((string) ($_POST['gender'] ?? '')),
            'dob' => trim((string) ($_POST['dob'] ?? '')),
            'phone' => preg_replace('/\D+/', '', trim((string) ($_POST['phone'] ?? ''))),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'country' => strtoupper(trim((string) ($_POST['country'] ?? 'TR'))),
            'is_verified' => isset($_POST['is_verified']) ? 1 : 0,
            'banned' => isset($_POST['banned']) ? 1 : 0,
            'is_test' => isset($_POST['is_test']) ? 1 : 0,
            'address' => trim((string) ($_POST['address'] ?? '')),
        ];

        if ($data['country'] === '') {
            $data['country'] = 'TR';
        }
        $error = $this->validateUserData(0, $data);
        if ($error !== '') {
            $this->flash($error);
            $this->redirect(AdminAuth::url('/user/create'));
        }

        $password = trim((string) ($_POST['password'] ?? ''));
        $passwordConfirmation = trim((string) ($_POST['password_confirmation'] ?? ''));
        if (strlen($password) < 6) {
            $this->flash('Şifre en az 6 karakter olmalıdır.');
            $this->redirect(AdminAuth::url('/user/create'));
        }
        if ($password !== $passwordConfirmation) {
            $this->flash('Şifre tekrarı eşleşmiyor.');
            $this->redirect(AdminAuth::url('/user/create'));
        }

        $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        $data['referral_code'] = $this->generateReferralCode($data['username']);

        try {
            $stmt = AdminDatabase::pdo()->prepare(
                'INSERT INTO users
                    (name, surname, username, email, identity_number, gender, dob, phone, city, country, password, referral_code, address, is_verified, banned, is_test, password_changed_at, created_at)
                 VALUES
                    (:name, :surname, :username, :email, :identity_number, :gender, :dob, :phone, :city, :country, :password, :referral_code, :address, :is_verified, :banned, :is_test, NOW(), NOW())'
            );
            $stmt->execute([
                'name' => $data['name'],
                'surname' => $data['surname'],
                'username' => $data['username'],
                'email' => $data['email'],
                'identity_number' => $data['identity_number'],
                'gender' => $data['gender'],
                'dob' => $data['dob'],
                'phone' => $data['phone'],
                'city' => $data['city'],
                'country' => $data['country'],
                'password' => $data['password'],
                'referral_code' => $data['referral_code'],
                'address' => $data['address'] !== '' ? $data['address'] : null,
                'is_verified' => $data['is_verified'],
                'banned' => $data['banned'],
                'is_test' => $data['is_test'],
            ]);
            $userId = (int) AdminDatabase::pdo()->lastInsertId();
            $this->flash('Oyuncu başarıyla eklendi.');
            $this->redirect(AdminAuth::url('/user?id=' . rawurlencode((string) $userId)));
        } catch (Throwable $exception) {
            $this->flash('Oyuncu eklenemedi: ' . $exception->getMessage());
            $this->redirect(AdminAuth::url('/user/create'));
        }
    }

    public function edit(): void
    {
        $this->requirePermission('users');
        $userId = max(0, (int) ($_GET['id'] ?? 0));
        $user = $this->user($userId);
        if ($user === null) {
            $this->flash('Kullanıcı bulunamadı.');
            $this->redirect(AdminAuth::url('/module?key=users'));
        }

        $data = [
            'title' => 'Kullanıcı Düzenle',
            'active' => 'datatable',
            'moduleKey' => 'users',
            'crumbs' => 'Members | Users | Düzenle',
            'user' => $user,
            'flash' => $this->pullFlash(),
        ];

        if ($this->isModalRequest()) {
            $data['isModal'] = true;
            $this->partial('users/_edit_form', $data);
            return;
        }

        $this->view('users/edit', $data);
    }

    public function update(): void
    {
        $this->requirePermission('users');
        $this->ensurePost();

        $userId = max(0, (int) ($_POST['user_id'] ?? 0));
        $user = $this->user($userId);
        if ($user === null) {
            $this->flash('Kullanıcı bulunamadı.');
            $this->redirect(AdminAuth::url('/module?key=users'));
        }

        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'surname' => trim((string) ($_POST['surname'] ?? '')),
            'username' => trim((string) ($_POST['username'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'identity_number' => trim((string) ($_POST['identity_number'] ?? '')),
            'gender' => trim((string) ($_POST['gender'] ?? '')),
            'dob' => trim((string) ($_POST['dob'] ?? '')),
            'phone' => preg_replace('/\D+/', '', trim((string) ($_POST['phone'] ?? ''))),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'country' => strtoupper(trim((string) ($_POST['country'] ?? 'TR'))),
            'is_verified' => isset($_POST['is_verified']) ? 1 : 0,
            'banned' => isset($_POST['banned']) ? 1 : 0,
            'is_test' => isset($_POST['is_test']) ? 1 : 0,
            'address' => trim((string) ($_POST['address'] ?? '')),
        ];

        if ($data['country'] === '') {
            $data['country'] = 'TR';
        }

        $error = $this->validateUserData($userId, $data);
        if ($error !== '') {
            $this->flash($error);
            $this->redirect(AdminAuth::url('/user/edit?id=' . rawurlencode((string) $userId)));
        }

        $password = trim((string) ($_POST['password'] ?? ''));
        $passwordConfirmation = trim((string) ($_POST['password_confirmation'] ?? ''));
        if ($password !== '' || $passwordConfirmation !== '') {
            if (strlen($password) < 6) {
                $this->flash('Şifre en az 6 karakter olmalıdır.');
                $this->redirect(AdminAuth::url('/user/edit?id=' . rawurlencode((string) $userId)));
            }
            if ($password !== $passwordConfirmation) {
                $this->flash('Şifre tekrarı eşleşmiyor.');
                $this->redirect(AdminAuth::url('/user/edit?id=' . rawurlencode((string) $userId)));
            }
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
            $data['password_changed_at'] = date('Y-m-d H:i:s');
        }

        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = $column . ' = :' . $column;
        }
        $data['id'] = $userId;

        try {
            $stmt = AdminDatabase::pdo()->prepare('UPDATE users SET ' . implode(', ', $assignments) . ' WHERE id = :id');
            $stmt->execute($data);
            if ((int) ($data['banned'] ?? 0) === 1 && (int) ($user['banned'] ?? 0) !== 1) {
                $this->revokeMemberSessions($userId);
            }
            $this->insertUserUpdateLog($user, $data);
            $this->flash('Kullanıcı bilgileri güncellendi.');
        } catch (Throwable $exception) {
            $this->flash('Kullanıcı güncellenemedi: ' . $exception->getMessage());
            $this->redirect(AdminAuth::url('/user/edit?id=' . rawurlencode((string) $userId)));
        }

        $this->redirect(AdminAuth::url('/user?id=' . rawurlencode((string) $userId)));
    }

    public function ban(): void
    {
        $this->requirePermission('users');
        $this->ensurePost();

        $userId = max(0, (int) ($_POST['user_id'] ?? 0));
        if ($userId <= 0) {
            $this->flash('Geçersiz kullanıcı.');
            $this->redirect(AdminAuth::url('/module?key=users'));
        }

        try {
            $pdo = AdminDatabase::pdo();
            $pdo->prepare('UPDATE users SET banned = 1 WHERE id = :id')->execute(['id' => $userId]);
            $this->revokeMemberSessions($userId);
            AdminAuth::writeLog(AdminAuth::userName(), 'user_ban', 'users', 'success', (string) $userId);
            $this->flash('Kullanıcı banlandı. Mevcut oturumları sonlandırıldı.');
        } catch (Throwable $exception) {
            $this->flash('Ban uygulanamadı: ' . $exception->getMessage());
        }

        $this->redirect(AdminAuth::url('/user?id=' . rawurlencode((string) $userId)));
    }

    public function unban(): void
    {
        $this->requirePermission('users');
        $this->ensurePost();

        $userId = max(0, (int) ($_POST['user_id'] ?? 0));
        if ($userId <= 0) {
            $this->flash('Geçersiz kullanıcı.');
            $this->redirect(AdminAuth::url('/module?key=users'));
        }

        try {
            AdminDatabase::pdo()->prepare('UPDATE users SET banned = 0 WHERE id = :id')->execute(['id' => $userId]);
            AdminAuth::writeLog(AdminAuth::userName(), 'user_unban', 'users', 'success', (string) $userId);
            $this->flash('Kullanıcı banı kaldırıldı.');
        } catch (Throwable $exception) {
            $this->flash('Ban kaldırılamadı: ' . $exception->getMessage());
        }

        $this->redirect(AdminAuth::url('/user?id=' . rawurlencode((string) $userId)));
    }

    private function revokeMemberSessions(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        try {
            $pdo = AdminDatabase::pdo();
            if (!class_exists('MemberJwtService', false)) {
                $path = BASE_PATH . '/services/MemberJwtService.php';
                if (is_file($path)) {
                    require_once $path;
                } elseif (is_file(ADMIN_APP_PATH . '/../services/MemberJwtService.php')) {
                    require_once ADMIN_APP_PATH . '/../services/MemberJwtService.php';
                }
            }
            if (class_exists('MemberJwtService', false)) {
                MemberJwtService::revokeAllForUser($pdo, $userId);
            } else {
                $pdo->prepare(
                    'UPDATE member_jwt_tokens SET revoked_at = NOW()
                     WHERE user_id = :user_id AND revoked_at IS NULL'
                )->execute(['user_id' => $userId]);
            }
        } catch (Throwable $e) {
            error_log('[AdminUserController] revokeMemberSessions failed: ' . $e->getMessage());
        }
    }

    public function balanceAdjust(): void
    {
        $this->requirePermission('users');
        $this->ensurePost();
        $this->ensureAdjustmentTable();

        $userId = max(0, (int) ($_POST['user_id'] ?? 0));
        $wallet = (string) ($_POST['wallet'] ?? 'balance');
        $action = (string) ($_POST['action'] ?? 'add');
        $amount = round((float) str_replace(',', '.', (string) ($_POST['amount'] ?? '0')), 2);
        $note = trim((string) ($_POST['note'] ?? ''));

        if (!in_array($wallet, ['balance', 'bonus_balance'], true) || !in_array($action, ['add', 'subtract'], true) || $amount <= 0) {
            $this->flash('Geçersiz bakiye işlemi.');
            $this->redirect(AdminAuth::url('/user?id=' . rawurlencode((string) $userId)));
        }

        $pdo = AdminDatabase::pdo();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id, username, balance, bonus_balance FROM users WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch();
            if (!is_array($user)) {
                throw new InvalidArgumentException('Kullanıcı bulunamadı.');
            }

            $before = (float) ($user[$wallet] ?? 0);
            if ($action === 'subtract' && $before < $amount) {
                throw new InvalidArgumentException('Çıkarılacak tutar mevcut bakiyeden yüksek olamaz.');
            }
            $after = $action === 'add' ? $before + $amount : $before - $amount;

            $update = $pdo->prepare('UPDATE users SET ' . $wallet . ' = :after WHERE id = :id');
            $update->execute(['after' => number_format($after, 2, '.', ''), 'id' => $userId]);
            $this->insertAdjustment($user, $wallet, $action, $amount, $before, $after, $note);
            $this->insertAdminLog($user, $wallet, $action, $amount, $before, $after, $note);
            $pdo->commit();

            $this->flash('Bakiye işlemi başarıyla kaydedildi.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->flash($exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Bakiye işlemi tamamlanamadı.');
        }

        $this->redirect(AdminAuth::url('/user?id=' . rawurlencode((string) $userId)));
    }

    private function user(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = AdminDatabase::pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        return is_array($user) ? $user : null;
    }

    private function summary(int $userId): array
    {
        return [
            'deposit_total' => $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE user_id = :user_id AND type = 'deposit' AND status IN ('confirmed','approved','success','completed')", $userId),
            'deposit_pending' => $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE user_id = :user_id AND type = 'deposit' AND status = 'pending'", $userId),
            'withdraw_total' => $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE user_id = :user_id AND type = 'withdraw' AND status IN ('confirmed','approved','success','completed')", $userId),
            'withdraw_pending' => $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE user_id = :user_id AND type = 'withdraw' AND status = 'pending'", $userId),
            'manual_add' => $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM admin_balance_adjustments WHERE user_id = :user_id AND action = 'add'", $userId),
            'manual_subtract' => $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM admin_balance_adjustments WHERE user_id = :user_id AND action = 'subtract'", $userId),
        ];
    }

    private function rows(string $sql, int $userId): array
    {
        try {
            $stmt = AdminDatabase::pdo()->prepare($sql);
            $stmt->execute(['user_id' => $userId]);

            return $stmt->fetchAll();
        } catch (Throwable $exception) {
            error_log('[AdminUserController] rows query failed: ' . $exception->getMessage());

            return [];
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function freespinsForUser(int $userId): array
    {
        $pdo = AdminDatabase::pdo();
        $rows = [];

        try {
            BgamingService::bootstrap($pdo);
            $stmt = $pdo->prepare(
                "SELECT
                    'BGaming' AS provider,
                    COALESCE(c.title, cp.campaign_code) AS campaign,
                    COALESCE(c.game_identifier, '-') AS game,
                    cp.freespins_total AS freespins_total,
                    cp.freespins_done AS freespins_done,
                    cp.win_amount,
                    cp.status,
                    COALESCE(cp.valid_until, c.expires_at) AS valid_until,
                    cp.created_at
                 FROM bgaming_campaign_players cp
                 INNER JOIN bgaming_campaigns c ON c.campaign_code = cp.campaign_code
                 WHERE cp.user_id = :user_id
                   AND cp.status <> 'superseded'
                   AND c.campaign_type = 'freespin'
                 ORDER BY cp.created_at DESC
                 LIMIT 100"
            );
            $stmt->execute(['user_id' => $userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $row['status'] = BgamingService::freespinStatusLabel((string) ($row['status'] ?? ''));
                $rows[] = $row;
            }
        } catch (Throwable $exception) {
            error_log('[AdminUserController] BGaming freespins could not be loaded: ' . $exception->getMessage());
        }

        foreach ($rows as $index => $row) {
            $expiresAt = (int) ($row['valid_until'] ?? 0);
            $rows[$index]['valid_until'] = $expiresAt > 0 ? date('d.m.Y H:i', $expiresAt) : '-';
        }
        usort(
            $rows,
            static fn (array $left, array $right): int => strcmp(
                (string) ($right['created_at'] ?? ''),
                (string) ($left['created_at'] ?? '')
            )
        );

        return $rows;
    }

    private function scalar(string $sql, int $userId): float
    {
        $stmt = AdminDatabase::pdo()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return (float) $stmt->fetchColumn();
    }

    private function gameRows(int $userId): array
    {
        $pdo = AdminDatabase::pdo();
        $rows = [];

        try {
            $stmt = $pdo->prepare(
                "SELECT
                    CAST(id AS CHAR) AS id,
                    COALESCE(game_name, game_code, game_id, '-') AS game_name,
                    COALESCE(provider_name, provider_code, source, '') AS provider_name,
                    '' AS image_url,
                    COALESCE(provider_txn_id, '-') AS transaction_id,
                    COALESCE(round_id, '-') AS round_id,
                    LOWER(COALESCE(txn_type, 'bet')) AS txn_type,
                    COALESCE(bet_amount, 0) AS bet_amount,
                    COALESCE(win_amount, 0) AS win_amount,
                    COALESCE(balance_after, 0) AS balance_after,
                    created_at
                 FROM games_transactions
                 WHERE user_id = :user_id
                 ORDER BY created_at DESC
                 LIMIT 100"
            );
            $stmt->execute(['user_id' => $userId]);
            $rows = array_merge($rows, $stmt->fetchAll());
        } catch (Throwable) {
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT
                    CONCAT('bgaming:', t.id) AS id,
                    COALESCE(g.title, t.game_identifier, '-') AS game_name,
                    COALESCE(NULLIF(g.provider, ''), 'BGaming') AS provider_name,
                    COALESCE(g.thumbnail_url, '') AS image_url,
                    COALESCE(NULLIF(t.casino_tx_id, ''), NULLIF(t.action_id, ''), '-') AS transaction_id,
                    COALESCE(NULLIF(t.round_id, ''), '-') AS round_id,
                    LOWER(COALESCE(t.txn_type, 'bet')) AS txn_type,
                    COALESCE(t.amount, 0) AS raw_amount,
                    COALESCE(t.after_balance, 0) AS balance_after,
                    COALESCE(t.processed_at, t.created_at) AS created_at
                 FROM bgaming_transactions t
                 LEFT JOIN bgaming_games g ON g.identifier = t.game_identifier
                 WHERE t.user_id = :user_id
                 ORDER BY t.id DESC
                 LIMIT 100"
            );
            $stmt->execute(['user_id' => $userId]);

            foreach ($stmt->fetchAll() as $row) {
                $txnType = strtolower((string) ($row['txn_type'] ?? 'bet'));
                $normalizedTxnType = match ($txnType) {
                    'win', 'promo_win', 'freespins_win' => 'win',
                    'rollback' => 'refund',
                    default => 'bet',
                };
                $amount = (float) ($row['raw_amount'] ?? 0);
                $row['txn_type'] = $normalizedTxnType;
                $row['bet_amount'] = $normalizedTxnType === 'bet' ? $amount : 0.0;
                $row['win_amount'] = $normalizedTxnType !== 'bet' ? $amount : 0.0;
                unset($row['raw_amount']);
                $rows[] = $row;
            }
        } catch (Throwable) {
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT
                    CONCAT('gsc:', t.id) AS id,
                    COALESCE(NULLIF(g.game_name, ''), NULLIF(t.game_code, ''), CONCAT('GSC ', t.product_code), '-') AS game_name,
                    COALESCE(
                        NULLIF(g.provider, ''),
                        NULLIF(g.product_name, ''),
                        CASE t.product_code
                            WHEN 1006 THEN 'Pragmatic Play'
                            WHEN 1185 THEN 'SA Gaming'
                            WHEN 1220 THEN 'Astar'
                            ELSE CONCAT('GSC+', t.product_code)
                        END
                    ) AS provider_name,
                    COALESCE(NULLIF(g.image_url, ''), '') AS image_url,
                    COALESCE(NULLIF(t.transaction_id, ''), NULLIF(t.wager_code, ''), '-') AS transaction_id,
                    COALESCE(NULLIF(t.round_id, ''), NULLIF(t.wager_code, ''), '-') AS round_id,
                    UPPER(COALESCE(NULLIF(t.action, ''), NULLIF(t.wager_status, ''), 'BET')) AS raw_action,
                    COALESCE(t.amount, 0) AS raw_amount,
                    COALESCE(t.bet_amount, 0) AS raw_bet_amount,
                    COALESCE(t.prize_amount, 0) AS raw_prize_amount,
                    COALESCE(t.after_balance, 0) AS balance_after,
                    t.created_at
                 FROM gsc_transactions t
                 LEFT JOIN gsc_games g
                    ON g.product_code = t.product_code
                   AND g.game_code = t.game_code
                   AND (
                        UPPER(TRIM(COALESCE(g.product_currency, ''))) = UPPER(TRIM(COALESCE(t.currency, '')))
                     OR g.product_currency IS NULL
                     OR TRIM(COALESCE(g.product_currency, '')) = ''
                   )
                 WHERE t.user_id = :user_id
                 ORDER BY t.id DESC
                 LIMIT 150"
            );
            $stmt->execute(['user_id' => $userId]);
            $seen = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id = (string) ($row['id'] ?? '');
                if ($id === '' || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $action = strtoupper(trim((string) ($row['raw_action'] ?? 'BET')));
                $amount = (float) ($row['raw_amount'] ?? 0);
                $betAmount = (float) ($row['raw_bet_amount'] ?? 0);
                $prizeAmount = (float) ($row['raw_prize_amount'] ?? 0);
                $normalizedTxnType = match (true) {
                    in_array($action, ['BET', 'BET_PRESERVE', 'TIP'], true) => 'bet',
                    in_array($action, ['CANCEL', 'ROLLBACK', 'VOID', 'PRESERVE_REFUND'], true) => 'refund',
                    in_array($action, ['SETTLED', 'BONUS', 'JACKPOT', 'FREEBET', 'PROMO', 'LEADERBOARD'], true) => 'win',
                    in_array($action, ['ADJUSTMENT', 'RESETTLED'], true) => ($amount < 0 ? 'bet' : 'win'),
                    default => ($amount < 0 ? 'bet' : 'win'),
                };
                if ($normalizedTxnType === 'bet') {
                    $row['bet_amount'] = $betAmount > 0 ? $betAmount : abs($amount);
                    $row['win_amount'] = 0.0;
                } elseif ($normalizedTxnType === 'refund') {
                    $row['bet_amount'] = 0.0;
                    $row['win_amount'] = abs($amount);
                } else {
                    // Keep settle stake when GSC includes bet_amount on SETTLED.
                    $row['bet_amount'] = $betAmount > 0 ? $betAmount : 0.0;
                    $row['win_amount'] = $prizeAmount > 0 ? $prizeAmount : max(0.0, $amount);
                }
                $row['txn_type'] = $normalizedTxnType;
                unset($row['raw_action'], $row['raw_amount'], $row['raw_bet_amount'], $row['raw_prize_amount']);
                $rows[] = $row;
            }
        } catch (Throwable $exception) {
            error_log('[AdminUserController] GSC game history could not be loaded: ' . $exception->getMessage());
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT
                    t.id,
                    t.txn_code,
                    t.pair_code,
                    t.wager_id,
                    t.round_id,
                    t.vendor_code,
                    t.game_code,
                    t.txn_type,
                    t.amount,
                    t.after_balance,
                    t.created_at,
                    COALESCE(NULLIF(g.game_name, ''), NULLIF(t.game_code, ''), '-') AS game_name,
                    COALESCE(g.game_type, 1) AS game_type,
                    COALESCE(NULLIF(g.image_url, ''), '') AS image_url,
                    COALESCE(NULLIF(v.vendor_name, ''), NULLIF(t.vendor_code, ''), 'Aggregator') AS provider_name
                 FROM casino_aggregator_transactions t
                 LEFT JOIN casino_aggregator_games g
                    ON g.vendor_code = t.vendor_code
                   AND g.game_code = t.game_code
                 LEFT JOIN casino_aggregator_vendors v
                    ON v.vendor_code = t.vendor_code
                 WHERE t.user_id = :user_id
                 ORDER BY t.id DESC
                 LIMIT 150"
            );
            $stmt->execute(['user_id' => $userId]);
            $canLocalize = class_exists('CasinoAggregatorService', false)
                || is_file(dirname(__DIR__, 2) . '/services/CasinoAggregatorService.php');
            if ($canLocalize && !class_exists('CasinoAggregatorService', false)) {
                require_once dirname(__DIR__, 2) . '/services/CasinoAggregatorService.php';
            }

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (class_exists('CasinoAggregatorService', false)) {
                    $built = CasinoAggregatorService::buildMemberGameHistoryRow($row);
                    $rows[] = [
                        'id' => (string) ($built['id'] ?? ('aggregator:' . (string) ($row['id'] ?? ''))),
                        'game_name' => (string) ($built['game_name'] ?? '-'),
                        'provider_name' => (string) ($built['provider_name'] ?? 'Aggregator'),
                        'image_url' => trim((string) ($row['image_url'] ?? '')),
                        'transaction_id' => (string) (($built['transaction_id'] ?? '') !== '' ? $built['transaction_id'] : '-'),
                        'round_id' => (string) (($built['round_id'] ?? '') !== '' ? $built['round_id'] : '-'),
                        'txn_type' => (string) ($built['txn_type'] ?? 'bet'),
                        'bet_amount' => (float) ($built['bet_amount'] ?? 0),
                        'win_amount' => (float) ($built['win_amount'] ?? 0),
                        'balance_after' => (float) ($built['balance_after'] ?? 0),
                        'created_at' => (string) ($built['created_at'] ?? ($row['created_at'] ?? '')),
                    ];
                    continue;
                }

                $txnType = strtolower((string) ($row['txn_type'] ?? 'bet'));
                $normalizedTxnType = match ($txnType) {
                    'win' => 'win',
                    'cancel' => 'refund',
                    default => 'bet',
                };
                $amount = abs((float) ($row['amount'] ?? 0));
                $rows[] = [
                    'id' => 'aggregator:' . (string) ($row['id'] ?? ''),
                    'game_name' => (string) ($row['game_name'] ?? '-'),
                    'provider_name' => (string) ($row['provider_name'] ?? 'Aggregator'),
                    'image_url' => trim((string) ($row['image_url'] ?? '')),
                    'transaction_id' => (string) (($row['txn_code'] ?? '') !== '' ? $row['txn_code'] : '-'),
                    'round_id' => (string) (($row['round_id'] ?? '') !== '' ? $row['round_id'] : ((string) ($row['wager_id'] ?? '') !== '' ? $row['wager_id'] : '-')),
                    'txn_type' => $normalizedTxnType,
                    'bet_amount' => $normalizedTxnType === 'bet' ? $amount : 0.0,
                    'win_amount' => $normalizedTxnType !== 'bet' ? $amount : 0.0,
                    'balance_after' => (float) ($row['after_balance'] ?? 0),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }
        } catch (Throwable $exception) {
            error_log('[AdminUserController] Aggregator game history could not be loaded: ' . $exception->getMessage());
        }

        usort($rows, static function (array $left, array $right): int {
            return strtotime((string) ($right['created_at'] ?? '')) <=> strtotime((string) ($left['created_at'] ?? ''));
        });

        return array_slice($rows, 0, 150);
    }

    private function validateUserData(int $userId, array $data): string
    {
        foreach (['name' => 'Ad', 'surname' => 'Soyad', 'username' => 'Kullanıcı adı', 'email' => 'Email', 'gender' => 'Cinsiyet', 'dob' => 'Doğum tarihi', 'phone' => 'Telefon'] as $field => $label) {
            if ((string) ($data[$field] ?? '') === '') {
                return $label . ' alanı zorunludur.';
            }
        }

        if (filter_var((string) $data['email'], FILTER_VALIDATE_EMAIL) === false) {
            return 'Geçerli bir email adresi girin.';
        }

        if (!in_array((string) $data['gender'], ['Erkek', 'Kadın', 'Diğer'], true)) {
            return 'Geçerli bir cinsiyet seçin.';
        }

        if ($userId === 0 && (string) ($data['identity_number'] ?? '') === '') {
            return 'Kimlik numarası alanı zorunludur.';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data['dob']) !== 1) {
            return 'Doğum tarihi YYYY-AA-GG formatında olmalıdır.';
        }

        foreach (['username' => 'Kullanıcı adı', 'email' => 'Email'] as $field => $label) {
            $stmt = AdminDatabase::pdo()->prepare('SELECT COUNT(*) FROM users WHERE ' . $field . ' = :value AND id <> :id');
            $stmt->execute(['value' => (string) $data[$field], 'id' => $userId]);
            if ((int) $stmt->fetchColumn() > 0) {
                return $label . ' başka bir kullanıcıda kayıtlı.';
            }
        }

        return '';
    }

    private function generateReferralCode(string $username): ?string
    {
        $base = preg_replace('/[^a-z0-9]/i', '', strtolower($username));
        $base = is_string($base) && $base !== '' ? substr($base, 0, 18) : 'user';
        for ($i = 0; $i < 6; $i++) {
            $candidate = strtoupper($base . substr(bin2hex(random_bytes(4)), 0, 8));
            $stmt = AdminDatabase::pdo()->prepare('SELECT 1 FROM users WHERE referral_code = :code LIMIT 1');
            $stmt->execute(['code' => $candidate]);
            if (!$stmt->fetchColumn()) {
                return $candidate;
            }
        }

        return null;
    }

    private function insertAdjustment(array $user, string $wallet, string $action, float $amount, float $before, float $after, string $note): void
    {
        $admin = AdminAuth::user();
        $stmt = AdminDatabase::pdo()->prepare(
            "INSERT INTO admin_balance_adjustments
                (user_id, username, admin_id, admin_username, wallet, action, amount, before_balance, after_balance, note, created_at)
             VALUES
                (:user_id, :username, :admin_id, :admin_username, :wallet, :action, :amount, :before_balance, :after_balance, :note, NOW())"
        );
        $stmt->execute([
            'user_id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'admin_id' => (int) ($admin['id'] ?? 0),
            'admin_username' => (string) ($admin['username'] ?? 'Admin'),
            'wallet' => $wallet,
            'action' => $action,
            'amount' => number_format($amount, 2, '.', ''),
            'before_balance' => number_format($before, 2, '.', ''),
            'after_balance' => number_format($after, 2, '.', ''),
            'note' => $note,
        ]);
    }

    private function insertAdminLog(array $user, string $wallet, string $action, float $amount, float $before, float $after, string $note): void
    {
        try {
            $admin = AdminAuth::user();
            $description = sprintf(
                '%s için %s bakiyesi %s %.2f. Önce: %.2f, sonra: %.2f.',
                (string) $user['username'],
                $wallet === 'bonus_balance' ? 'bonus' : 'ana',
                $action === 'add' ? 'eklendi' : 'çıkarıldı',
                $amount,
                $before,
                $after
            );
            $stmt = AdminDatabase::pdo()->prepare(
                "INSERT INTO admin_logs
                    (admin_id, admin_username, admin_role, action, entity_type, entity_id, entity_name, description, old_values, new_values, changes_summary, status, ip_address, user_agent, request_method, request_path, created_at)
                 VALUES
                    (:admin_id, :admin_username, :admin_role, :action, 'user_balance', :entity_id, :entity_name, :description, :old_values, :new_values, :changes_summary, 'success', :ip_address, :user_agent, :request_method, :request_path, NOW())"
            );
            $stmt->execute([
                'admin_id' => (int) ($admin['id'] ?? 0),
                'admin_username' => (string) ($admin['username'] ?? 'Admin'),
                'admin_role' => (string) ($admin['role'] ?? 'admin'),
                'action' => 'manual_balance_' . $action,
                'entity_id' => (int) $user['id'],
                'entity_name' => (string) $user['username'],
                'description' => $description . ($note !== '' ? ' Not: ' . $note : ''),
                'old_values' => json_encode([$wallet => $before], JSON_UNESCAPED_UNICODE),
                'new_values' => json_encode([$wallet => $after], JSON_UNESCAPED_UNICODE),
                'changes_summary' => $wallet . ': ' . number_format($before, 2, '.', '') . ' -> ' . number_format($after, 2, '.', ''),
                'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'request_method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'POST'),
                'request_path' => (string) ($_SERVER['REQUEST_URI'] ?? '/user/balance-adjust'),
            ]);
            AdminAuditService::write(
                AdminDatabase::pdo(),
                'manual_balance_' . $action,
                'user_balance',
                (int) $user['id'],
                $description . ($note !== '' ? ' Not: ' . $note : ''),
                ['wallet' => $wallet, 'amount' => $amount, 'before' => $before, 'after' => $after]
            );
        } catch (Throwable) {
        }
    }

    private function insertUserUpdateLog(array $oldUser, array $newData): void
    {
        try {
            $admin = AdminAuth::user();
            $safeNewData = $newData;
            unset($safeNewData['password'], $safeNewData['password_changed_at'], $safeNewData['id']);
            $safeOldData = [];
            foreach (array_keys($safeNewData) as $column) {
                $safeOldData[$column] = $oldUser[$column] ?? null;
            }

            $stmt = AdminDatabase::pdo()->prepare(
                "INSERT INTO admin_logs
                    (admin_id, admin_username, admin_role, action, entity_type, entity_id, entity_name, description, old_values, new_values, changes_summary, status, ip_address, user_agent, request_method, request_path, created_at)
                 VALUES
                    (:admin_id, :admin_username, :admin_role, 'user_update', 'users', :entity_id, :entity_name, :description, :old_values, :new_values, :changes_summary, 'success', :ip_address, :user_agent, :request_method, :request_path, NOW())"
            );
            $stmt->execute([
                'admin_id' => (int) ($admin['id'] ?? 0),
                'admin_username' => (string) ($admin['username'] ?? 'Admin'),
                'admin_role' => (string) ($admin['role'] ?? 'admin'),
                'entity_id' => (int) ($oldUser['id'] ?? 0),
                'entity_name' => (string) ($safeNewData['username'] ?? $oldUser['username'] ?? ''),
                'description' => 'Kullanıcı profil bilgileri admin tarafından güncellendi.',
                'old_values' => json_encode($safeOldData, JSON_UNESCAPED_UNICODE),
                'new_values' => json_encode($safeNewData, JSON_UNESCAPED_UNICODE),
                'changes_summary' => 'users profile update',
                'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'request_method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'POST'),
                'request_path' => (string) ($_SERVER['REQUEST_URI'] ?? '/user/update'),
            ]);
            AdminAuditService::write(
                AdminDatabase::pdo(),
                'user_update',
                'users',
                (int) ($oldUser['id'] ?? 0),
                'Kullanıcı profil bilgileri admin tarafından güncellendi.',
                ['fields' => array_keys($safeNewData)]
            );
        } catch (Throwable) {
        }
    }

    private function notesForUser(int $userId): array
    {
        $this->ensureNotesTable();
        try {
            $stmt = AdminDatabase::pdo()->prepare(
                'SELECT n.id, n.content, n.created_at,
                        COALESCE(a.username, CAST(n.admin_id AS CHAR)) AS created_by
                 FROM admin_user_notes n
                 LEFT JOIN admins a ON a.id = n.admin_id
                 WHERE n.user_id = :user_id
                 ORDER BY n.id DESC LIMIT 100'
            );
            $stmt->execute(['user_id' => $userId]);

            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function sessionsForUser(int $userId): array
    {
        try {
            $stmt = AdminDatabase::pdo()->prepare(
                'SELECT id, issued_at, expires_at, revoked_at, last_seen_at, ip_address, user_agent
                 FROM member_jwt_tokens WHERE user_id = :user_id ORDER BY id DESC LIMIT 30'
            );
            $stmt->execute(['user_id' => $userId]);

            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function formatSportsbookCoupons(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $payload = $this->decodeJsonArray($row['raw_payload'] ?? null);
            $detailData = $this->decodeJsonArray($row['detail'] ?? null);
            if ($detailData === [] && isset($payload['detail'])) {
                $detailData = $this->decodeJsonArray($payload['detail']);
            }

            $context = array_merge($payload, ['detail' => $detailData]);
            $rows[$index]['amount'] = $this->resolveSportsbookDisplayAmount($row, $context);
            $rows[$index]['processed_coupon'] = $this->couponSummaryFromContext($context, $row);
            $rows[$index]['match_result'] = $this->matchResultFromContext($context, $row);
            unset($rows[$index]['detail'], $rows[$index]['raw_payload']);
        }

        return $rows;
    }

    private function resolveSportsbookDisplayAmount(array $row, array $context): float
    {
        $storedAmount = (float) ($row['amount'] ?? 0);
        $txnType = strtolower(trim((string) ($row['txn_type'] ?? '')));
        if (abs($storedAmount) > 0.000001) {
            return $storedAmount;
        }

        // Some provider settlements do not move wallet balance but still include
        // payout/refund in detail/raw payload; use those as display fallback.
        if ($txnType === 'win') {
            $winAmount = $this->extractFirstNumeric($context, [
                'win_amount',
                'winAmount',
                'get_amount',
                'getAmount',
                'payout',
                'payout_amount',
                'payoutAmount',
                'settlement_amount',
                'settlementAmount',
                'credit_amount',
                'creditAmount',
                'return_amount',
                'returnAmount',
                'paid_amount',
                'paidAmount',
            ]);
            if ($winAmount !== null) {
                return abs($winAmount);
            }
        }

        if ($txnType === 'cancel') {
            $refundAmount = $this->extractFirstNumeric($context, [
                'refund_amount',
                'refundAmount',
                'cancel_amount',
                'cancelAmount',
                'rollback_amount',
                'rollbackAmount',
                'return_amount',
                'returnAmount',
                'stake',
                'stake_amount',
                'stakeAmount',
                'bet_amount',
                'betAmount',
            ]);
            if ($refundAmount !== null) {
                return abs($refundAmount);
            }
        }

        return $storedAmount;
    }

    private function decodeJsonArray(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw)) {
            return [];
        }
        $trimmed = trim($raw);
        if ($trimmed === '' || $trimmed === 'null') {
            return [];
        }
        $decoded = json_decode($trimmed, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function couponSummaryFromContext(array $context, array $row): string
    {
        $detail = is_array($context['detail'] ?? null) ? $context['detail'] : [];
        $legs = [];
        foreach (['selections', 'legs', 'bets', 'items', 'events', 'markets'] as $key) {
            $candidate = $detail[$key] ?? $context[$key] ?? null;
            if (is_array($candidate) && isset($candidate[0]) && is_array($candidate[0])) {
                $legs = $candidate;
                break;
            }
        }

        $segments = [];
        foreach (array_slice($legs, 0, 5) as $leg) {
            $event = trim((string) ($leg['eventName'] ?? $leg['event_name'] ?? $leg['match'] ?? $leg['fixture'] ?? ''));
            $market = trim((string) ($leg['marketName'] ?? $leg['market'] ?? ''));
            $pick = trim((string) ($leg['selectionName'] ?? $leg['selection'] ?? $leg['outcome'] ?? $leg['pick'] ?? ''));
            $odds = trim((string) ($leg['odds'] ?? $leg['price'] ?? ''));

            $line = $event;
            if ($market !== '') {
                $line .= ($line !== '' ? ' | ' : '') . $market;
            }
            if ($pick !== '') {
                $line .= ($line !== '' ? ' | ' : '') . $pick;
            }
            if ($odds !== '') {
                $line .= ($line !== '' ? ' | ' : '') . 'Odd: ' . $odds;
            }
            if ($line !== '') {
                $segments[] = $line;
            }
        }

        if ($segments !== []) {
            $summary = implode(' || ', $segments);
            if (count($legs) > 5) {
                $summary .= ' || ...';
            }
            return $summary;
        }

        $couponId = (string) ($row['coupon_id'] ?? '-');
        $txnType = $this->translateSportsbookTxnType((string) ($row['txn_type'] ?? ''));
        $amount = number_format((float) ($row['amount'] ?? 0), 2, '.', '');
        $currency = strtoupper((string) ($row['currency'] ?? 'TRY'));
        return 'Kupon: ' . $couponId . ' | Hareket: ' . $txnType . ' | Tutar: ' . $amount . ' ' . $currency;
    }

    private function matchResultFromContext(array $context, array $row): string
    {
        $keys = ['match_result', 'matchResult', 'event_result', 'eventResult', 'result', 'score', 'final_score', 'finalScore'];
        $result = $this->extractFirstScalar($context, $keys);
        if ($result !== '') {
            return $result;
        }

        $homeScore = $this->extractFirstScalar($context, ['home_score', 'homeScore']);
        $awayScore = $this->extractFirstScalar($context, ['away_score', 'awayScore']);
        if ($homeScore !== '' || $awayScore !== '') {
            return trim($homeScore) . ' - ' . trim($awayScore);
        }

        if ((string) ($row['status'] ?? '') === 'completed') {
            return 'Tamamlandi';
        }

        return '-';
    }

    private function extractFirstScalar(mixed $data, array $keys): string
    {
        if (!is_array($data)) {
            if (is_scalar($data)) {
                return trim((string) $data);
            }
            return '';
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && is_scalar($data[$key])) {
                $value = trim((string) $data[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $found = $this->extractFirstScalar($value, $keys);
                if ($found !== '') {
                    return $found;
                }
            }
        }

        return '';
    }

    private function extractFirstNumeric(mixed $data, array $keys): ?float
    {
        if (is_array($data)) {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $data)) {
                    continue;
                }
                $value = $this->toNumeric($data[$key]);
                if ($value !== null) {
                    return $value;
                }
            }

            foreach ($data as $value) {
                $nested = $this->extractFirstNumeric($value, $keys);
                if ($nested !== null) {
                    return $nested;
                }
            }

            return null;
        }

        return $this->toNumeric($data);
    }

    private function toNumeric(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace([',', ' '], ['.', ''], $normalized);
        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function translateSportsbookTxnType(string $type): string
    {
        $type = strtolower(trim($type));
        return match ($type) {
            'bet', 'promo_bet' => 'Bahis',
            'win', 'promo_win', 'freespins_win' => 'Kazanç',
            'cancel', 'rollback' => 'İade',
            default => $type !== '' ? ucfirst($type) : '-',
        };
    }

    private function ensureNotesTable(): void
    {
        try {
            AdminDatabase::pdo()->exec(
                "CREATE TABLE IF NOT EXISTS admin_user_notes (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    admin_id INT UNSIGNED NULL,
                    content TEXT NOT NULL,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_admin_user_notes_user (user_id),
                    KEY idx_admin_user_notes_admin (admin_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable) {
        }
    }

    private function ensureAdjustmentTable(): void
    {
        if ((string) getenv('APP_RUNTIME_PROVIDER_BOOTSTRAP') !== '1') {
            return;
        }

        AdminDatabase::pdo()->exec(
            "CREATE TABLE IF NOT EXISTS admin_balance_adjustments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                username VARCHAR(50) NOT NULL,
                admin_id INT NULL,
                admin_username VARCHAR(100) NULL,
                wallet ENUM('balance','bonus_balance') NOT NULL DEFAULT 'balance',
                action ENUM('add','subtract') NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                before_balance DECIMAL(12,2) NOT NULL,
                after_balance DECIMAL(12,2) NOT NULL,
                note VARCHAR(500) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_admin_balance_adjustments_user (user_id),
                KEY idx_admin_balance_adjustments_admin (admin_id),
                KEY idx_admin_balance_adjustments_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function ensurePost(): void
    {
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }
    }

    private function flash(string $message): void
    {
        $_SESSION['admin_flash'] = $message;
    }

    private function isModalRequest(): bool
    {
        return (string) ($_GET['modal'] ?? '') === '1'
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    private function pullFlash(): string
    {
        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        return $message;
    }
}
