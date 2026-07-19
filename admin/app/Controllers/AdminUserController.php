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

        $this->view('users/detail', [
            'title' => 'Kullanıcı Detayı',
            'active' => 'datatable',
            'moduleKey' => 'users',
            'crumbs' => 'Members | Users | Detay',
            'user' => $user,
            'summary' => $this->summary($userId),
            'deposits' => $this->rows("SELECT id, method, 'megapayz' AS provider, amount, fee, status, trx, created_at, updated_at FROM megapayz_transactions WHERE user_id = :user_id AND type = 'deposit' ORDER BY id DESC LIMIT 30", $userId),
            'withdrawals' => $this->rows("SELECT id, method, 'megapayz' AS provider, amount, fee, currency, status, NULL AS admin_status, trx, created_at, updated_at FROM megapayz_transactions WHERE user_id = :user_id AND type = 'withdraw' ORDER BY id DESC LIMIT 30", $userId),
            'adjustments' => $this->rows('SELECT id, wallet, action, amount, before_balance, after_balance, note, admin_username, created_at FROM admin_balance_adjustments WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 30', $userId),
            'games' => [],
            'sportsbookCoupons' => $sportsbookCoupons,
            'bonusClaims' => $this->rows('SELECT id, bonus_name, requested_amount, status, processed_by, processed_at, created_at FROM bonus_claim_requests WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 20', $userId),
            'activeBonuses' => $this->rows('SELECT id, name, initial_amount, current_bonus_balance, wagering_requirement, wagering_target, total_bet_amount, is_complete, status, deadline, created_at FROM user_active_bonuses WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 20', $userId),
            'accountWagering' => WageringService::accountProgress(AdminDatabase::pdo(), $userId),
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
            $this->insertUserUpdateLog($user, $data);
            $this->flash('Kullanıcı bilgileri güncellendi.');
        } catch (Throwable $exception) {
            $this->flash('Kullanıcı güncellenemedi: ' . $exception->getMessage());
            $this->redirect(AdminAuth::url('/user/edit?id=' . rawurlencode((string) $userId)));
        }

        $this->redirect(AdminAuth::url('/user?id=' . rawurlencode((string) $userId)));
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
            'deposit_total' => $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE user_id = :user_id AND type = 'deposit' AND status = 'confirmed'", $userId),
            'deposit_pending' => $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE user_id = :user_id AND type = 'deposit' AND status = 'pending'", $userId),
            'withdraw_total' => $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE user_id = :user_id AND type = 'withdraw' AND status = 'confirmed'", $userId),
            'withdraw_pending' => $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions WHERE user_id = :user_id AND type = 'withdraw' AND status = 'pending'", $userId),
            'manual_add' => $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM admin_balance_adjustments WHERE user_id = :user_id AND action = 'add'", $userId),
            'manual_subtract' => $this->scalar("SELECT COALESCE(SUM(amount), 0) FROM admin_balance_adjustments WHERE user_id = :user_id AND action = 'subtract'", $userId),
        ];
    }

    private function rows(string $sql, int $userId): array
    {
        $stmt = AdminDatabase::pdo()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    private function scalar(string $sql, int $userId): float
    {
        $stmt = AdminDatabase::pdo()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return (float) $stmt->fetchColumn();
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
            $rows[$index]['processed_coupon'] = $this->couponSummaryFromContext($context, $row);
            $rows[$index]['match_result'] = $this->matchResultFromContext($context, $row);
            unset($rows[$index]['detail'], $rows[$index]['raw_payload']);
        }

        return $rows;
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

    private function translateSportsbookTxnType(string $type): string
    {
        $type = strtolower(trim($type));
        return match ($type) {
            'bet', 'promo_bet' => 'Kayıp',
            'win', 'promo_win', 'freespins_win' => 'Kazanç',
            'cancel', 'rollback' => 'İptal',
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
        if ((string) getenv('METROPOL_RUNTIME_PROVIDER_BOOTSTRAP') !== '1') {
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
