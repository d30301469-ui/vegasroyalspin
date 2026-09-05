<?php
/** Admin internal API route tanımları — api/v2/index.php tarafından yüklenir. */
$routes = [
    ['GET', 'health', static function () use ($success): void {
        $success([
            'service' => 'admin-api-v2',
            'time' => date('c'),
            'authenticated' => AdminAuth::check(),
        ]);
    }],
    ['GET', 'auth/me', static function () use ($requireAuth, $success): void {
        $requireAuth();
        $success([
            'user' => AdminAuth::user(),
            'csrf' => AdminAuth::csrfToken(),
        ]);
    }],
    ['GET', 'dashboard/summary', static function () use ($requirePermission, $success): void {
        $requirePermission('dashboard');
        $pdo = AdminDatabase::pdo();
        MegaPayzService::bootstrap($pdo);
        $scalar = static function (PDO $pdo, string $sql): float {
            try {
                return (float) $pdo->query($sql)->fetchColumn();
            } catch (Throwable) {
                return 0.0;
            }
        };
        $success([
            'users' => (int) $scalar($pdo, 'SELECT COUNT(*) FROM users'),
            'deposits_confirmed' => $scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM megapayz_transactions WHERE type='deposit' AND status IN ('confirmed','approved','success','completed')"),
            'withdrawals_pending' => (int) $scalar($pdo, "SELECT COUNT(*) FROM megapayz_transactions WHERE type='withdraw' AND status='pending'"),
            'games_active' => (int) ($scalar($pdo, 'SELECT COUNT(*) FROM bgaming_games WHERE is_active = 1')
                + $scalar($pdo, 'SELECT COUNT(*) FROM casino_aggregator_games WHERE is_active = 1')
                + $scalar($pdo, 'SELECT COUNT(*) FROM gsc_games WHERE is_active = 1')),
            'call_requests_pending' => (int) $scalar($pdo, "SELECT COUNT(*) FROM call_me_requests WHERE status='pending'"),
            'support_tickets_open' => (int) $scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','answered')"),
            'aml_alerts_open' => ComplianceService::countOpen($pdo, 'aml_alerts'),
            'risk_alerts_open' => ComplianceService::countOpen($pdo, 'risk_alerts'),
        ]);
    }],
    ['GET', 'dashboard/revenue', static function () use ($requirePermission, $success): void {
        $requirePermission('dashboard');
        $pdo = AdminDatabase::pdo();
        MegaPayzService::bootstrap($pdo);
        $scalar = static function (string $sql) use ($pdo): float {
            try {
                return (float) $pdo->query($sql)->fetchColumn();
            } catch (Throwable) {
                return 0.0;
            }
        };
        $success([
            'deposit_total' => $scalar("SELECT COALESCE(SUM(amount),0) FROM megapayz_transactions WHERE type='deposit' AND status IN ('confirmed','success','completed')"),
            'withdraw_total' => $scalar("SELECT COALESCE(SUM(amount),0) FROM megapayz_transactions WHERE type='withdraw' AND status IN ('confirmed','success','completed')"),
            'net_revenue' => $scalar("SELECT COALESCE(SUM(CASE WHEN type='deposit' THEN amount ELSE -amount END),0) FROM megapayz_transactions WHERE status IN ('confirmed','success','completed')"),
        ], ['resource' => 'dashboard/revenue']);
    }],
    ['GET', 'dashboard/active-users', static function () use ($requirePermission, $success): void {
        $requirePermission('dashboard');
        $pdo = AdminDatabase::pdo();
        $onlineNow = 0;
        $active24h = 0;
        try {
            $onlineNow = (int) $pdo->query(
                "SELECT COUNT(DISTINCT t.user_id)
                 FROM member_jwt_tokens t
                 INNER JOIN users u ON u.id = t.user_id
                 WHERE t.revoked_at IS NULL
                   AND t.expires_at >= NOW()
                   AND COALESCE(t.last_seen_at, t.issued_at) >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                   AND COALESCE(u.banned, 0) = 0"
            )->fetchColumn();
        } catch (Throwable) {
            $onlineNow = 0;
        }
        try {
            $active24h = (int) $pdo->query(
                "SELECT COUNT(*) FROM users
                 WHERE COALESCE(banned, 0) = 0
                   AND COALESCE(last_login_at, updated_at, created_at) >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            )->fetchColumn();
        } catch (Throwable) {
            try {
                $active24h = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            } catch (Throwable) {
                $active24h = 0;
            }
        }
        $success([
            'online_users' => $onlineNow,
            'active_users' => $onlineNow,
            'active_users_24h' => $active24h,
            'window_minutes' => 10,
        ], ['resource' => 'dashboard/active-users']);
    }],
    ['GET', 'dashboard/risk-alerts', static function (array $params, array $payload) use ($requirePermission, $success, $getInput): void {
        $requirePermission('dashboard');
        ComplianceService::ensureTables(AdminDatabase::pdo());
        $page = max(1, (int) $getInput($payload, 'page', 1));
        $perPage = min(20, max(5, (int) $getInput($payload, 'per_page', 10)));
        $result = ComplianceService::listRiskAlerts(AdminDatabase::pdo(), $page, $perPage, 'open');
        $success(['items' => $result['items'], 'total' => $result['total']], ['resource' => 'dashboard/risk-alerts']);
    }],
    ['GET', 'modules', static function () use ($requireAuth, $success, $moduleMap): void {
        $requireAuth();
        $modules = $moduleMap();
        $out = [];
        foreach ($modules as $key => $module) {
            if (!is_array($module)) {
                continue;
            }
            if (!AdminAuth::can((string) $key)) {
                continue;
            }
            $out[] = [
                'key' => (string) $key,
                'title' => (string) ($module['title'] ?? $key),
                'table' => (string) ($module['table'] ?? ''),
                'columns' => array_values(array_map('strval', (array) ($module['columns'] ?? []))),
                'search_placeholder' => (string) ($module['search_placeholder'] ?? ''),
            ];
        }
        $success($out);
    }],
    ['GET', 'modules/{key}/rows', static function (array $params, array $payload) use ($requirePermission, $success, $error, $moduleMap, $repo, $getInput): void {
        $modules = $moduleMap();
        $key = (string) ($params['key'] ?? '');
        $requirePermission($key);
        if (!isset($modules[$key]) || !is_array($modules[$key])) {
            $error(404, 'Modül bulunamadı.');
        }
        $module = $modules[$key];
        $table = (string) ($module['table'] ?? '');
        if ($table === '') {
            $error(400, 'Modül tablo tanımı eksik.');
        }
        $page = max(1, (int) $getInput($payload, 'page', 1));
        $perPage = min(100, max(10, (int) $getInput($payload, 'per_page', 25)));
        $search = trim((string) $getInput($payload, 'search', ''));

        $success([
            'module' => $key,
            'table' => $table,
            'columns' => $repo->columns($table),
            'primary_key' => $repo->primaryKey($table),
            'rows' => $repo->rows($table, $page, $perPage, $search),
        ], [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $repo->countRows($table, $search),
            'search' => $search,
        ]);
    }],
    ['GET', 'users', static function (array $params, array $payload) use ($requirePermission, $success, $getInput): void {
        $requirePermission('users');
        $pdo = AdminDatabase::pdo();
        $page = max(1, (int) $getInput($payload, 'page', 1));
        $perPage = min(100, max(10, (int) $getInput($payload, 'per_page', 25)));
        $offset = ($page - 1) * $perPage;
        $search = trim((string) $getInput($payload, 'search', ''));
        $where = '';
        $bind = [];
        if ($search !== '') {
            $where = 'WHERE username LIKE :search OR email LIKE :search OR name LIKE :search OR surname LIKE :search';
            $bind['search'] = '%' . $search . '%';
        }
        $count = $pdo->prepare('SELECT COUNT(*) FROM users ' . $where);
        $count->execute($bind);
        $stmt = $pdo->prepare('SELECT id, username, email, name, surname, balance, bonus_balance, banned, is_verified, created_at FROM users ' . $where . ' ORDER BY id DESC LIMIT :limit OFFSET :offset');
        foreach ($bind as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $success(['items' => $rows, 'users' => $rows], [
            'page' => $page,
            'per_page' => $perPage,
            'total' => (int) $count->fetchColumn(),
        ]);
    }],
    ['GET', 'users/{id}', static function (array $params) use ($requirePermission, $success, $error): void {
        $requirePermission('users');
        $id = max(0, (int) ($params['id'] ?? 0));
        $pdo = AdminDatabase::pdo();
        MegaPayzService::bootstrap($pdo);
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        if (!is_array($user)) {
            $error(404, 'Kullanıcı bulunamadı.');
        }
        foreach ([
            'password', 'password_hash', 'pass', 'remember_token', 'verify_token',
            'reset_token', 'reset_password_token', 'email_verify_token',
            'two_factor_secret', '2fa_secret', 'totp_secret', 'api_token', 'api_key',
            'security_pin', 'pin_code',
        ] as $sensitive) {
            unset($user[$sensitive]);
        }
        $rows = static function (PDO $pdo, string $sql, int $id): array {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['user_id' => $id]);

            return $stmt->fetchAll();
        };
        $scalar = static function (PDO $pdo, string $sql, int $id): float {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['user_id' => $id]);

            return (float) $stmt->fetchColumn();
        };
        $success([
            'user' => $user,
            'summary' => [
                'deposit_total' => $scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM megapayz_transactions WHERE user_id=:user_id AND type='deposit' AND status IN ('confirmed','approved','success','completed')", $id),
                'withdraw_total' => $scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM megapayz_transactions WHERE user_id=:user_id AND type='withdraw' AND status IN ('confirmed','approved','success','completed')", $id),
                'manual_add' => $scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM admin_balance_adjustments WHERE user_id=:user_id AND action='add'", $id),
                'manual_subtract' => $scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM admin_balance_adjustments WHERE user_id=:user_id AND action='subtract'", $id),
            ],
            'deposits' => $rows($pdo, "SELECT id, method, 'megapayz' AS provider, amount, status, trx, created_at FROM megapayz_transactions WHERE user_id=:user_id AND type='deposit' ORDER BY id DESC LIMIT 30", $id),
            'withdrawals' => $rows($pdo, "SELECT id, method, 'megapayz' AS provider, amount, status, NULL AS admin_status, trx, created_at FROM megapayz_transactions WHERE user_id=:user_id AND type='withdraw' ORDER BY id DESC LIMIT 30", $id),
            'adjustments' => $rows($pdo, 'SELECT id, wallet, action, amount, before_balance, after_balance, note, admin_username, created_at FROM admin_balance_adjustments WHERE user_id=:user_id ORDER BY created_at DESC LIMIT 30', $id),
        ]);
    }],
    ['POST', 'users/{id}', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('users');
        $validateCsrf($payload);
        $id = max(0, (int) ($params['id'] ?? 0));
        $pdo = AdminDatabase::pdo();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $current = $stmt->fetch();
        if (!is_array($current)) {
            $error(404, 'Kullanıcı bulunamadı.');
        }

        $allowed = ['name', 'surname', 'username', 'email', 'identity_number', 'gender', 'dob', 'phone', 'city', 'country', 'is_verified', 'banned', 'is_test', 'address'];
        $data = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $payload['body'])) {
                continue;
            }
            $value = $payload['body'][$field];
            if (in_array($field, ['is_verified', 'banned', 'is_test'], true)) {
                $data[$field] = (int) ((string) $value === '1' || $value === 1 || $value === true);
            } else {
                $data[$field] = trim((string) $value);
            }
        }

        if (isset($data['email']) && filter_var((string) $data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $error(422, 'Geçerli bir email girin.');
        }
        if (isset($data['gender']) && !in_array((string) $data['gender'], ['Erkek', 'Kadın', 'Diğer'], true)) {
            $error(422, 'Geçerli bir cinsiyet seçin.');
        }
        foreach (['username', 'email'] as $uniqueField) {
            if (!isset($data[$uniqueField])) {
                continue;
            }
            $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE ' . $uniqueField . ' = :value AND id <> :id');
            $check->execute(['value' => $data[$uniqueField], 'id' => $id]);
            if ((int) $check->fetchColumn() > 0) {
                $error(422, $uniqueField . ' başka bir kullanıcıya ait.');
            }
        }

        $password = trim((string) ($payload['body']['password'] ?? ''));
        $passwordConfirmation = trim((string) ($payload['body']['password_confirmation'] ?? ''));
        if ($password !== '' || $passwordConfirmation !== '') {
            if (strlen($password) < 6) {
                $error(422, 'Şifre en az 6 karakter olmalıdır.');
            }
            if ($password !== $passwordConfirmation) {
                $error(422, 'Şifre tekrarı eşleşmiyor.');
            }
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
            $data['password_changed_at'] = date('Y-m-d H:i:s');
        }

        if ($data === []) {
            $success(['id' => $id, 'updated' => false], ['message' => 'Güncellenecek alan yok.']);
        }

        $set = [];
        foreach (array_keys($data) as $field) {
            $set[] = $field . ' = :' . $field;
        }
        $data['id'] = $id;
        $update = $pdo->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id');
        $update->execute($data);
        $success(['id' => $id, 'updated' => true]);
    }],
    ['POST', 'users/{id}/balance-adjust', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('users');
        $validateCsrf($payload);
        $id = max(0, (int) ($params['id'] ?? 0));
        $wallet = (string) ($payload['body']['wallet'] ?? 'balance');
        $action = (string) ($payload['body']['action'] ?? 'add');
        $amount = round((float) str_replace(',', '.', (string) ($payload['body']['amount'] ?? '0')), 2);
        $note = trim((string) ($payload['body']['note'] ?? ''));
        if (!in_array($wallet, ['balance', 'bonus_balance'], true) || !in_array($action, ['add', 'subtract'], true) || $amount <= 0) {
            $error(422, 'Geçersiz bakiye işlemi.');
        }

        $pdo = AdminDatabase::pdo();
        $pdo->beginTransaction();
        try {
            $userStmt = $pdo->prepare('SELECT id, username, balance, bonus_balance FROM users WHERE id = :id FOR UPDATE');
            $userStmt->execute(['id' => $id]);
            $user = $userStmt->fetch();
            if (!is_array($user)) {
                $error(404, 'Kullanıcı bulunamadı.');
            }
            $before = (float) ($user[$wallet] ?? 0);
            if ($action === 'subtract' && $before < $amount) {
                $error(422, 'Çıkarılacak tutar mevcut bakiyeden yüksek olamaz.');
            }
            $after = $action === 'add' ? $before + $amount : $before - $amount;
            $pdo->prepare('UPDATE users SET ' . $wallet . ' = :after WHERE id = :id')
                ->execute(['after' => number_format($after, 2, '.', ''), 'id' => $id]);

            $admin = AdminAuth::user();
            $pdo->prepare(
                "INSERT INTO admin_balance_adjustments
                    (user_id, username, admin_id, admin_username, wallet, action, amount, before_balance, after_balance, note, created_at)
                 VALUES
                    (:user_id, :username, :admin_id, :admin_username, :wallet, :action, :amount, :before_balance, :after_balance, :note, NOW())"
            )->execute([
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
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error(500, 'Bakiye işlemi tamamlanamadı.', ['reason' => $exception->getMessage()]);
        }

        $success([
            'id' => $id,
            'wallet' => $wallet,
            'action' => $action,
            'amount' => $amount,
        ]);
    }],
    ['POST', 'users/{id}/lock', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('users');
        $validateCsrf($payload);
        $id = max(0, (int) ($params['id'] ?? 0));
        if ($id <= 0) { $error(422, 'Geçersiz kullanıcı ID.'); }
        $pdo = AdminDatabase::pdo();
        $chk = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $chk->execute(['id' => $id]);
        if (!$chk->fetch()) { $error(404, 'Kullanıcı bulunamadı.'); }
        $pdo->prepare('UPDATE users SET banned = 1 WHERE id = :id')->execute(['id' => $id]);
        if (class_exists('MemberJwtService', false) || is_file(dirname(__DIR__, 3) . '/services/MemberJwtService.php')) {
            if (!class_exists('MemberJwtService', false)) {
                require_once dirname(__DIR__, 3) . '/services/MemberJwtService.php';
            }
            MemberJwtService::revokeAllForUser($pdo, $id);
        } else {
            try {
                $pdo->prepare('UPDATE member_jwt_tokens SET revoked_at = NOW() WHERE user_id = :id AND revoked_at IS NULL')
                    ->execute(['id' => $id]);
            } catch (Throwable) {
            }
        }
        $success(['id' => $id, 'locked' => true, 'banned' => true]);
    }],
    ['POST', 'users/{id}/unlock', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('users');
        $validateCsrf($payload);
        $id = max(0, (int) ($params['id'] ?? 0));
        if ($id <= 0) { $error(422, 'Geçersiz kullanıcı ID.'); }
        $pdo = AdminDatabase::pdo();
        $chk = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $chk->execute(['id' => $id]);
        if (!$chk->fetch()) { $error(404, 'Kullanıcı bulunamadı.'); }
        $pdo->prepare('UPDATE users SET banned = 0 WHERE id = :id')->execute(['id' => $id]);
        $success(['id' => $id, 'locked' => false, 'banned' => false]);
    }],
    ['POST', 'users/{id}/verify', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('users');
        $validateCsrf($payload);
        $id = max(0, (int) ($params['id'] ?? 0));
        if ($id <= 0) { $error(422, 'Geçersiz kullanıcı ID.'); }
        $pdo = AdminDatabase::pdo();
        $chk = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $chk->execute(['id' => $id]);
        if (!$chk->fetch()) { $error(404, 'Kullanıcı bulunamadı.'); }
        $pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = :id')->execute(['id' => $id]);
        $success(['id' => $id, 'verified' => true]);
    }],
    ['GET', 'users/{id}/transactions', static function (array $params) use ($requirePermission, $success): void {
        $requirePermission('users');
        $id = max(0, (int) ($params['id'] ?? 0));
        MegaPayzService::bootstrap(AdminDatabase::pdo());
        $deposits = MegaPayzService::history(AdminDatabase::pdo(), $id, 'deposit', ['limit' => 50]);
        $withdrawals = MegaPayzService::history(AdminDatabase::pdo(), $id, 'withdraw', ['limit' => 50]);
        $success(['deposits' => $deposits['items'] ?? [], 'withdrawals' => $withdrawals['items'] ?? []]);
    }],
    ['GET', 'users/{id}/bets', static function (array $params) use ($requirePermission, $success, $error): void {
        $requirePermission('users');
        $userId = max(0, (int) ($params['id'] ?? 0));
        if ($userId <= 0) {
            $error(422, 'Geçersiz kullanıcı ID.');
        }
        $pdo = AdminDatabase::pdo();
        $items = [];
        $push = static function (array $row) use (&$items): void {
            $items[] = $row;
        };
        try {
            $stmt = $pdo->prepare(
                "SELECT CONCAT('bgaming:', t.id) AS id,
                        COALESCE(g.title, t.game_identifier, '-') AS game_name,
                        COALESCE(NULLIF(g.provider, ''), 'BGaming') AS provider_name,
                        LOWER(COALESCE(t.txn_type, 'bet')) AS txn_type,
                        COALESCE(t.amount, 0) AS amount,
                        COALESCE(t.processed_at, t.created_at) AS created_at
                 FROM bgaming_transactions t
                 LEFT JOIN bgaming_games g ON g.identifier = t.game_identifier
                 WHERE t.user_id = :user_id ORDER BY t.id DESC LIMIT 50"
            );
            $stmt->execute(['user_id' => $userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $push($row);
            }
        } catch (Throwable) {
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT CONCAT('gsc:', t.id) AS id,
                        COALESCE(NULLIF(t.game_code, ''), CONCAT('GSC ', t.product_code), '-') AS game_name,
                        'GSC+' AS provider_name,
                        LOWER(COALESCE(NULLIF(t.action, ''), 'bet')) AS txn_type,
                        COALESCE(t.amount, 0) AS amount,
                        t.created_at
                 FROM gsc_transactions t
                 WHERE t.user_id = :user_id ORDER BY t.id DESC LIMIT 50"
            );
            $stmt->execute(['user_id' => $userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $push($row);
            }
        } catch (Throwable) {
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT CONCAT('agg:', t.id) AS id,
                        COALESCE(NULLIF(t.game_code, ''), '-') AS game_name,
                        COALESCE(NULLIF(t.vendor_code, ''), 'Aggregator') AS provider_name,
                        LOWER(COALESCE(t.txn_type, 'bet')) AS txn_type,
                        COALESCE(t.amount, 0) AS amount,
                        t.created_at
                 FROM casino_aggregator_transactions t
                 WHERE t.user_id = :user_id ORDER BY t.id DESC LIMIT 50"
            );
            $stmt->execute(['user_id' => $userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $push($row);
            }
        } catch (Throwable) {
        }
        usort($items, static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        $items = array_slice($items, 0, 100);
        $success(['items' => $items, 'user_id' => $userId, 'total' => count($items)]);
    }],
    ['GET', 'users/{id}/sessions', static function (array $params) use ($requirePermission, $success): void {
        $requirePermission('users');
        $id = max(0, (int) ($params['id'] ?? 0));
        $rows = [];
        try {
            $stmt = AdminDatabase::pdo()->prepare('SELECT id, issued_at, expires_at, revoked_at, last_seen_at, ip_address, user_agent FROM member_jwt_tokens WHERE user_id = :id ORDER BY id DESC LIMIT 50');
            $stmt->execute(['id' => $id]);
            $rows = $stmt->fetchAll();
        } catch (Throwable) {
            $rows = [];
        }
        $success(['items' => $rows, 'sessions' => $rows]);
    }],
    ['GET', 'users/{id}/notes', static function (array $params) use ($requirePermission, $success, $error): void {
        $requirePermission('users');
        $userId = max(0, (int) ($params['id'] ?? 0));
        if ($userId <= 0) { $error(422, 'Geçersiz kullanıcı ID.'); }
        $pdo  = AdminDatabase::pdo();
        $stmt = $pdo->prepare(
            'SELECT n.id, n.content, n.created_at, n.updated_at,
                    COALESCE(a.username, CAST(n.admin_id AS CHAR)) AS created_by
             FROM admin_user_notes n
             LEFT JOIN admins a ON a.id = n.admin_id
             WHERE n.user_id = :uid
             ORDER BY n.id DESC LIMIT 200'
        );
        $stmt->execute(['uid' => $userId]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $success(['items' => $notes, 'user_id' => $userId]);
    }],
    ['POST', 'users/{id}/notes', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('users');
        $validateCsrf($payload);
        $userId  = max(0, (int) ($params['id'] ?? 0));
        if ($userId <= 0) { $error(422, 'Geçersiz kullanıcı ID.'); }
        $body    = is_array($payload['body'] ?? null) ? $payload['body'] : [];
        $content = trim((string) ($body['content'] ?? ''));
        if ($content === '') { $error(422, 'Not içeriği boş olamaz.'); }
        $pdo     = AdminDatabase::pdo();
        $chk = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $chk->execute(['id' => $userId]);
        if (!$chk->fetch()) { $error(404, 'Kullanıcı bulunamadı.'); }
        $admin   = AdminAuth::user();
        $adminId = (int) ($admin['id'] ?? 0);
        $stmt = $pdo->prepare('INSERT INTO admin_user_notes (user_id, admin_id, content) VALUES (:uid, :aid, :content)');
        $stmt->execute(['uid' => $userId, 'aid' => $adminId, 'content' => $content]);
        $noteId = (int) $pdo->lastInsertId();
        // Audit log
        try {
            $pdo->prepare(
                "INSERT INTO admin_audit_logs (admin_id, admin_username, action, entity_type, entity_id, note, meta, ip_address, created_at)
                 VALUES (:aid, :auname, 'user_note_added', 'user', :uid, :note, :meta, :ip, NOW())"
            )->execute([
                'aid'    => $adminId,
                'auname' => (string) ($admin['username'] ?? ''),
                'uid'    => (string) $userId,
                'note'   => mb_substr($content, 0, 200),
                'meta'   => json_encode(['note_id' => $noteId, 'length' => mb_strlen($content)]),
                'ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable) {}
        $success(['id' => $noteId, 'user_id' => $userId, 'content' => $content, 'created' => true]);
    }],
    ['GET', 'deposits', static function (array $params, array $payload) use ($requirePermission, $success, $getInput): void {
        $requirePermission('deposits');
        $pdo = AdminDatabase::pdo();
        MegaPayzService::bootstrap($pdo);
        $page    = max(1, (int) $getInput($payload, 'page', 1));
        $perPage = min(200, max(1, (int) $getInput($payload, 'limit', 50)));
        $offset  = ($page - 1) * $perPage;
        $userId  = (int) $getInput($payload, 'user_id', 0);
        $status  = trim((string) $getInput($payload, 'status', ''));
        $from    = trim((string) $getInput($payload, 'from', ''));
        $to      = trim((string) $getInput($payload, 'to', ''));
        $where   = ["type='deposit'"];
        $bind    = [];
        if ($userId > 0)   { $where[] = 'user_id = :user_id';     $bind['user_id'] = $userId; }
        if ($status !== '') { $where[] = 'status = :status';       $bind['status']  = $status; }
        if ($from !== '')   { $where[] = 'created_at >= :from';    $bind['from']    = $from . ' 00:00:00'; }
        if ($to !== '')     { $where[] = 'created_at <= :to';      $bind['to']      = $to   . ' 23:59:59'; }
        $whereStr = 'WHERE ' . implode(' AND ', $where);
        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM megapayz_transactions $whereStr");
        $totalStmt->execute($bind);
        $total = (int) $totalStmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT * FROM megapayz_transactions $whereStr ORDER BY id DESC LIMIT :limit OFFSET :offset");
        foreach ($bind as $k => $v) { $stmt->bindValue(':' . $k, $v); }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $success(['items' => $stmt->fetchAll(), 'total' => $total], ['page' => $page, 'per_page' => $perPage]);
    }],
    ['GET', 'withdrawals', static function (array $params, array $payload) use ($requirePermission, $success, $getInput): void {
        $requirePermission('withdrawals');
        $pdo = AdminDatabase::pdo();
        MegaPayzService::bootstrap($pdo);
        $page    = max(1, (int) $getInput($payload, 'page', 1));
        $perPage = min(200, max(1, (int) $getInput($payload, 'limit', 50)));
        $offset  = ($page - 1) * $perPage;
        $userId  = (int) $getInput($payload, 'user_id', 0);
        $status  = trim((string) $getInput($payload, 'status', ''));
        $from    = trim((string) $getInput($payload, 'from', ''));
        $to      = trim((string) $getInput($payload, 'to', ''));
        $where   = ["type='withdraw'"];
        $bind    = [];
        if ($userId > 0)   { $where[] = 'user_id = :user_id';  $bind['user_id'] = $userId; }
        if ($status !== '') { $where[] = 'status = :status';    $bind['status']  = $status; }
        if ($from !== '')   { $where[] = 'created_at >= :from'; $bind['from']    = $from . ' 00:00:00'; }
        if ($to !== '')     { $where[] = 'created_at <= :to';   $bind['to']      = $to   . ' 23:59:59'; }
        $whereStr = 'WHERE ' . implode(' AND ', $where);
        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM megapayz_transactions $whereStr");
        $totalStmt->execute($bind);
        $total = (int) $totalStmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT * FROM megapayz_transactions $whereStr ORDER BY id DESC LIMIT :limit OFFSET :offset");
        foreach ($bind as $k => $v) { $stmt->bindValue(':' . $k, $v); }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $success(['items' => $stmt->fetchAll(), 'total' => $total], ['page' => $page, 'per_page' => $perPage]);
    }],
    ['POST', 'withdrawals/{id}/approve', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('withdrawals');
        $validateCsrf($payload);
        $admin = AdminAuth::user();
        $result = MegaPayzService::approveWithdraw(AdminDatabase::pdo(), (int) ($params['id'] ?? 0), (string) ($admin['username'] ?? 'Admin'));
        if (empty($result['success'])) {
            $error(422, (string) ($result['message'] ?? 'Çekim onaylanamadı.'), $result);
        }
        $success($result);
    }],
    ['POST', 'withdrawals/{id}/risk-release', static function (array $params, array $payload) use ($validateCsrf, $success, $error): void {
        if (!AdminAuth::canReleaseRiskHoldWithdraw()) {
            $error(403, 'Risk çekim onayı yalnızca yetkili süper admin tarafından verilebilir.');
        }
        $validateCsrf($payload);
        $admin = AdminAuth::user();
        $result = MegaPayzService::releaseRiskHoldWithdraw(
            AdminDatabase::pdo(),
            (int) ($params['id'] ?? 0),
            (string) ($admin['username'] ?? 'Admin'),
            (string) ($admin['email'] ?? ''),
            AdminAuth::isSuperAdmin()
        );
        if (empty($result['success'])) {
            $error(422, (string) ($result['message'] ?? 'Risk tutması serbest bırakılamadı.'), $result);
        }
        $success($result);
    }],
    ['POST', 'withdrawals/{id}/reject', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        if (!AdminAuth::can('withdrawals') && !AdminAuth::can('compliance-risk')) {
            $error(403, 'Bu işlem için yetkiniz yok.');
        }
        $validateCsrf($payload);
        $reason = trim((string) ($payload['body']['reason'] ?? ''));
        $result = MegaPayzService::rejectWithdraw(AdminDatabase::pdo(), (int) ($params['id'] ?? 0), $reason);
        if (empty($result['success'])) {
            $error(422, (string) ($result['message'] ?? 'Çekim reddedilemedi.'), $result);
        }
        $success($result);
    }],
    ['POST', 'deposits/{id}/approve', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $error): void {
        $requirePermission('deposits');
        $validateCsrf($payload);
        $error(501, 'Yatırım onayı sağlayıcı callback ile yönetilir; manuel onay desteklenmiyor.', [
            'id' => (int) ($params['id'] ?? 0),
            'status' => 'provider_managed',
        ]);
    }],
    ['POST', 'deposits/{id}/reject', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $error): void {
        $requirePermission('deposits');
        $validateCsrf($payload);
        $error(501, 'Yatırım reddi sağlayıcı callback ile yönetilir; manuel red desteklenmiyor.', [
            'id' => (int) ($params['id'] ?? 0),
            'status' => 'provider_managed',
        ]);
    }],
    ['GET', 'financial-reports', static function (array $params, array $payload) use ($requireAnyPermission, $success, $getInput): void {
        $requireAnyPermission(['deposits', 'withdrawals']);
        $pdo = AdminDatabase::pdo();
        MegaPayzService::bootstrap($pdo);
        $from    = trim((string) $getInput($payload, 'from', date('Y-m-01')));
        $to      = trim((string) $getInput($payload, 'to',   date('Y-m-d')));
        // Validate date format YYYY-MM-DD; fallback to safe defaults on invalid input.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !strtotime($from)) {
            $from = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || !strtotime($to)) {
            $to = date('Y-m-d');
        }
        if ($from > $to) { $from = $to; }
        $groupBy = in_array($getInput($payload, 'group_by', 'day'), ['day', 'week', 'month'], true)
                    ? (string) $getInput($payload, 'group_by', 'day') : 'day';
        $dateExpr = match($groupBy) {
            'week'  => "DATE_FORMAT(created_at, '%Y-%u')",
            'month' => "DATE_FORMAT(created_at, '%Y-%m')",
            default => 'DATE(created_at)',
        };
        $fromDt = $from . ' 00:00:00';
        $toDt   = $to   . ' 23:59:59';
        try {
            $sql = "SELECT
                        $dateExpr AS period,
                        SUM(CASE WHEN type='deposit' AND status IN ('confirmed','success','completed') THEN amount ELSE 0 END)  AS deposits,
                        SUM(CASE WHEN type='withdraw' AND status IN ('confirmed','success','completed') THEN amount ELSE 0 END) AS withdrawals,
                        SUM(CASE WHEN type='deposit' AND status IN ('confirmed','success','completed') THEN amount
                                 WHEN type='withdraw' AND status IN ('confirmed','success','completed') THEN -amount
                                 ELSE 0 END)                                                                                   AS net,
                        COUNT(CASE WHEN type='deposit' THEN 1 END)  AS deposit_count,
                        COUNT(CASE WHEN type='withdraw' THEN 1 END) AS withdrawal_count
                    FROM megapayz_transactions
                    WHERE created_at BETWEEN :from AND :to
                    GROUP BY period
                    ORDER BY period ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['from' => $fromDt, 'to' => $toDt]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totals = $pdo->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN type='deposit' AND status IN ('confirmed','success','completed') THEN amount ELSE 0 END), 0) AS total_deposits,
                    COALESCE(SUM(CASE WHEN type='withdraw' AND status IN ('confirmed','success','completed') THEN amount ELSE 0 END), 0) AS total_withdrawals
                 FROM megapayz_transactions WHERE created_at BETWEEN :from AND :to"
            );
            $totals->execute(['from' => $fromDt, 'to' => $toDt]);
            $summary = $totals->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $rows    = [];
            $summary = [];
        }
        $success([
            'items'   => $rows,
            'total'   => count($rows),
            'summary' => [
                'total_deposits'    => (float) ($summary['total_deposits']    ?? 0),
                'total_withdrawals' => (float) ($summary['total_withdrawals'] ?? 0),
                'net_revenue'       => (float) ($summary['total_deposits'] ?? 0) - (float) ($summary['total_withdrawals'] ?? 0),
            ],
        ], ['from' => $from, 'to' => $to, 'group_by' => $groupBy]);
    }],
    ['GET', 'risk/alerts', static function (array $params, array $payload) use ($requirePermission, $success, $getInput): void {
        $requirePermission('compliance-risk');
        ComplianceService::ensureTables(AdminDatabase::pdo());
        $page = max(1, (int) $getInput($payload, 'page', 1));
        $perPage = min(100, max(10, (int) $getInput($payload, 'per_page', 25)));
        $status = trim((string) $getInput($payload, 'status', 'open'));
        $filters = [
            'status' => $status,
            'severity' => trim((string) $getInput($payload, 'severity', '')),
            'rule' => trim((string) $getInput($payload, 'rule', '')),
            'q' => trim((string) $getInput($payload, 'q', '')),
        ];
        $result = ComplianceService::listRiskAlerts(AdminDatabase::pdo(), $page, $perPage, $status, $filters);
        $success([
            'items' => $result['items'],
            'total' => $result['total'],
            'summary' => ComplianceService::summary(AdminDatabase::pdo(), 'risk_alerts'),
            'charts' => ComplianceService::chartBundle(AdminDatabase::pdo(), 'risk_alerts'),
        ], [
            'resource' => 'risk/alerts',
            'page' => $page,
            'per_page' => $perPage,
            'status' => $status,
        ]);
    }],
    ['GET', 'risk/rules', static function () use ($requirePermission, $success): void {
        $requirePermission('dashboard');
        $success([
            'items' => [
                ['code' => 'large_withdraw', 'channel' => 'aml', 'title' => 'Yüksek tutarlı çekim'],
                ['code' => 'large_deposit', 'channel' => 'aml', 'title' => 'Yüksek tutarlı yatırım'],
                ['code' => 'rapid_deposit_withdraw', 'channel' => 'aml', 'title' => 'Hızlı yatırım sonrası çekim'],
                ['code' => 'new_account_large_withdraw', 'channel' => 'aml', 'title' => 'Yeni hesap yüksek çekim'],
                ['code' => 'withdraw_without_deposit', 'channel' => 'aml', 'title' => 'Yatırımsız çekim'],
                ['code' => 'withdraw_exceeds_deposits', 'channel' => 'aml', 'title' => 'Çekim yatırımını aşıyor'],
                ['code' => 'withdraw_without_kyc', 'channel' => 'aml', 'title' => 'KYC’siz yüksek çekim'],
                ['code' => 'deposit_structuring', 'channel' => 'aml', 'title' => 'Parçalı yatırım'],
                ['code' => 'shared_identity', 'channel' => 'aml', 'title' => 'Paylaşılan kimlik'],
                ['code' => 'period_withdraw_ratio', 'channel' => 'aml', 'title' => 'Dönemsel çekim oranı'],
                ['code' => 'multiple_pending_withdrawals', 'channel' => 'risk', 'title' => 'Çoklu bekleyen çekim'],
                ['code' => 'new_account_large_deposit', 'channel' => 'risk', 'title' => 'Yeni hesap yüksek yatırım'],
                ['code' => 'shared_phone', 'channel' => 'risk', 'title' => 'Paylaşılan telefon'],
                ['code' => 'kyc_missing_high_balance', 'channel' => 'risk', 'title' => 'KYC’siz yüksek bakiye'],
                ['code' => 'withdraw_velocity', 'channel' => 'risk', 'title' => 'Çekim hızı'],
            ],
            'total' => 15,
        ], ['resource' => 'risk/rules', 'status' => 'configured']);
    }],
    ['GET', 'compliance/kyc-queue', static function (array $params, array $payload) use ($requirePermission, $success, $getInput): void {
        $requirePermission('kyc');
        $pdo = AdminDatabase::pdo();
        $page = max(1, (int) $getInput($payload, 'page', 1));
        $perPage = min(100, max(10, (int) $getInput($payload, 'per_page', 25)));
        $status = trim((string) $getInput($payload, 'status', 'pending'));
        $offset = ($page - 1) * $perPage;
        $where = $status !== '' ? 'WHERE status = :status' : '';
        $params = $status !== '' ? ['status' => $status] : [];
        try {
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM kyc_requests ' . $where);
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();
            $sql = 'SELECT id, user_id, username, document_type, status, note, submitted_at, reviewed_at
                    FROM kyc_requests ' . $where . ' ORDER BY submitted_at DESC LIMIT :limit OFFSET :offset';
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue(':' . $k, $v);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $items = [];
            $total = 0;
        }
        $success(['items' => is_array($items) ? $items : [], 'total' => $total], [
            'resource' => 'compliance/kyc-queue',
            'page' => $page,
            'per_page' => $perPage,
            'status' => $status !== '' ? $status : null,
        ]);
    }],
    ['GET', 'compliance/aml-alerts', static function (array $params, array $payload) use ($requirePermission, $success, $getInput): void {
        $requirePermission('compliance-aml');
        ComplianceService::ensureTables(AdminDatabase::pdo());
        $page = max(1, (int) $getInput($payload, 'page', 1));
        $perPage = min(100, max(10, (int) $getInput($payload, 'per_page', 25)));
        $status = trim((string) $getInput($payload, 'status', 'open'));
        $filters = [
            'status' => $status,
            'severity' => trim((string) $getInput($payload, 'severity', '')),
            'rule' => trim((string) $getInput($payload, 'rule', '')),
            'q' => trim((string) $getInput($payload, 'q', '')),
        ];
        $result = ComplianceService::listAmlAlerts(AdminDatabase::pdo(), $page, $perPage, $status, $filters);
        $success([
            'items' => $result['items'],
            'total' => $result['total'],
            'summary' => ComplianceService::summary(AdminDatabase::pdo(), 'aml_alerts'),
            'charts' => ComplianceService::chartBundle(AdminDatabase::pdo(), 'aml_alerts'),
        ], [
            'resource' => 'compliance/aml-alerts',
            'page' => $page,
            'per_page' => $perPage,
            'status' => $status,
        ]);
    }],
    ['GET', 'compliance/audit-log', static function (array $params, array $payload) use ($requirePermission, $success, $getInput): void {
        $requirePermission('logs');
        $page = max(1, (int) $getInput($payload, 'page', 1));
        $perPage = min(100, max(10, (int) $getInput($payload, 'per_page', 25)));
        $result = AdminAuditService::listUnified(AdminDatabase::pdo(), [
            'page' => $page,
            'per_page' => $perPage,
            'q' => trim((string) $getInput($payload, 'q', '')),
            'action' => trim((string) $getInput($payload, 'action', '')),
            'admin' => trim((string) $getInput($payload, 'admin', '')),
            'source' => trim((string) $getInput($payload, 'source', '')),
        ]);
        $success([
            'items' => $result['items'],
            'total' => $result['total'],
        ], [
            'resource' => 'compliance/audit-log',
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total_pages' => $result['total_pages'],
        ]);
    }],
    ['POST', 'compliance/kyc/{id}/approve', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('kyc');
        $validateCsrf($payload);
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $error(422, 'Geçersiz KYC kaydı.');
        }
        $pdo = AdminDatabase::pdo();
        $stmt = $pdo->prepare('SELECT user_id FROM kyc_requests WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $userId = (int) $stmt->fetchColumn();
        if ($userId <= 0) {
            $error(404, 'KYC kaydı bulunamadı.');
        }
        $admin = AdminAuth::userName();
        $pdo->prepare(
            'UPDATE kyc_requests SET status = :status, reviewed_by = :reviewed_by, reviewed_at = NOW() WHERE id = :id'
        )->execute(['status' => 'approved', 'reviewed_by' => $admin, 'id' => $id]);
        $pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = :id')->execute(['id' => $userId]);
        $success(['id' => $id, 'approved' => true, 'user_id' => $userId]);
    }],
    ['POST', 'compliance/kyc/{id}/reject', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('kyc');
        $validateCsrf($payload);
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $error(422, 'Geçersiz KYC kaydı.');
        }
        $note = trim((string) ($payload['body']['note'] ?? $payload['body']['reason'] ?? ''));
        $pdo = AdminDatabase::pdo();
        $admin = AdminAuth::userName();
        $stmt = $pdo->prepare('UPDATE kyc_requests SET status = :status, note = :note, reviewed_by = :reviewed_by, reviewed_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => 'rejected', 'note' => $note, 'reviewed_by' => $admin, 'id' => $id]);
        if ($stmt->rowCount() === 0) {
            $error(404, 'KYC kaydı bulunamadı.');
        }
        $success(['id' => $id, 'rejected' => true]);
    }],
    ['POST', 'compliance/aml-alerts/{id}/resolve', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('compliance-aml');
        $validateCsrf($payload);
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $error(422, 'Geçersiz AML kaydı.');
        }
        $note = trim((string) ($payload['body']['note'] ?? ''));
        $pdo = AdminDatabase::pdo();
        $ok = ComplianceService::resolveAml($pdo, $id, AdminAuth::userName(), $note);
        if (!$ok) {
            $error(404, 'AML kaydı bulunamadı.');
        }
        AdminAuditService::write($pdo, 'aml_resolve', 'aml_alert', $id, $note !== '' ? $note : 'AML uyarısı çözüldü');
        $success(['id' => $id, 'resolved' => true]);
    }],
    ['POST', 'compliance/aml-alerts/{id}/ignore', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('compliance-aml');
        $validateCsrf($payload);
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $error(422, 'Geçersiz AML kaydı.');
        }
        $note = trim((string) ($payload['body']['note'] ?? ''));
        $pdo = AdminDatabase::pdo();
        $ok = ComplianceService::ignoreAml($pdo, $id, AdminAuth::userName(), $note);
        if (!$ok) {
            $error(404, 'AML kaydı bulunamadı.');
        }
        AdminAuditService::write($pdo, 'aml_ignore', 'aml_alert', $id, $note !== '' ? $note : 'AML uyarısı yoksayıldı');
        $success(['id' => $id, 'ignored' => true]);
    }],
    ['POST', 'compliance/risk-alerts/{id}/resolve', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('compliance-risk');
        $validateCsrf($payload);
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $error(422, 'Geçersiz risk kaydı.');
        }
        $note = trim((string) ($payload['body']['note'] ?? ''));
        $pdo = AdminDatabase::pdo();
        $ok = ComplianceService::resolveRisk($pdo, $id, AdminAuth::userName(), $note);
        if (!$ok) {
            $error(404, 'Risk kaydı bulunamadı.');
        }
        AdminAuditService::write($pdo, 'risk_resolve', 'risk_alert', $id, $note !== '' ? $note : 'Risk uyarısı çözüldü');
        $success(['id' => $id, 'resolved' => true]);
    }],
    ['POST', 'compliance/risk-alerts/{id}/ignore', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('compliance-risk');
        $validateCsrf($payload);
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $error(422, 'Geçersiz risk kaydı.');
        }
        $note = trim((string) ($payload['body']['note'] ?? ''));
        $pdo = AdminDatabase::pdo();
        $ok = ComplianceService::ignoreRisk($pdo, $id, AdminAuth::userName(), $note);
        if (!$ok) {
            $error(404, 'Risk kaydı bulunamadı.');
        }
        AdminAuditService::write($pdo, 'risk_ignore', 'risk_alert', $id, $note !== '' ? $note : 'Risk uyarısı yoksayıldı');
        $success(['id' => $id, 'ignored' => true]);
    }],
    ['POST', 'wallet/manual-adjustment', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $error): void {
        $requirePermission('users');
        $validateCsrf($payload);
        $error(410, 'Bu uç nokta kaldırıldı. POST users/{id}/balance-adjust kullanın.');
    }],
    ['GET', 'casino/providers', static function () use ($requireAnyPermission, $success): void {
        $requireAnyPermission(['bgaming-games']);
        $pdo = AdminDatabase::pdo();
        $providers = [];
        try {
            $pStmt = $pdo->query("SELECT DISTINCT provider AS provider_code, provider AS provider_name, NULL AS rtp, 1 AS is_active FROM bgaming_games WHERE provider <> '' ORDER BY provider ASC");
            $providers = $pStmt ? $pStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable) {}
        $success(['items' => $providers]);
    }],
    ['GET', 'casino/games', static function (array $params, array $payload) use ($requireAnyPermission, $success): void {
        $requireAnyPermission(['bgaming-games']);
        $pdo          = AdminDatabase::pdo();
        $bgamingGames = BgamingService::games($pdo, $payload['query'] ?? []);
        $allGames = (array) ($bgamingGames['games'] ?? $bgamingGames['items'] ?? []);
        $success(array_merge($bgamingGames, ['games' => $allGames, 'items' => $allGames,
            'total' => (int) ($bgamingGames['total'] ?? 0)]));
    }],
    ['POST', 'casino/games/{id}/enable', static function (array $params, array $payload) use ($requireAnyPermission, $validateCsrf, $success, $error): void {
        $requireAnyPermission(['bgaming-games']);
        $validateCsrf($payload);
        $gameId = trim((string) ($params['id'] ?? ''));
        if ($gameId === '') { $error(422, 'Geçersiz oyun ID.'); }
        $pdo = AdminDatabase::pdo();
        $updated = false;
        foreach (['bgaming_games'] as $table) {
            try {
                $stmt = $pdo->prepare("UPDATE `$table` SET is_active = 1 WHERE id = :id OR identifier = :gid");
                $stmt->execute(['id' => is_numeric($gameId) ? (int) $gameId : 0, 'gid' => $gameId]);
                if ($stmt->rowCount() > 0) { $updated = true; break; }
            } catch (Throwable) {}
        }
        if (!$updated) { $error(404, 'Oyun bulunamadı.'); }
        $success(['id' => $gameId, 'enabled' => true]);
    }],
    ['POST', 'casino/games/{id}/disable', static function (array $params, array $payload) use ($requireAnyPermission, $validateCsrf, $success, $error): void {
        $requireAnyPermission(['bgaming-games']);
        $validateCsrf($payload);
        $gameId = trim((string) ($params['id'] ?? ''));
        if ($gameId === '') { $error(422, 'Geçersiz oyun ID.'); }
        $pdo = AdminDatabase::pdo();
        $updated = false;
        foreach (['bgaming_games'] as $table) {
            try {
                $stmt = $pdo->prepare("UPDATE `$table` SET is_active = 0 WHERE id = :id OR identifier = :gid");
                $stmt->execute(['id' => is_numeric($gameId) ? (int) $gameId : 0, 'gid' => $gameId]);
                if ($stmt->rowCount() > 0) { $updated = true; break; }
            } catch (Throwable) {}
        }
        if (!$updated) { $error(404, 'Oyun bulunamadı.'); }
        $success(['id' => $gameId, 'enabled' => false]);
    }],
    ['POST', 'internal/jobs/sync-games', static function (array $params, array $payload) use ($requireAnyPermission, $validateCsrf, $success): void {
        $requireAnyPermission(['bgaming-games']);
        $validateCsrf($payload);
        @set_time_limit(0);
        $pdo = AdminDatabase::pdo();
        $success(['bgaming' => BgamingService::syncGames($pdo)]);
    }],
    ['POST', 'internal/jobs/sync-odds', static function (array $params, array $payload) use ($requireAuth, $validateCsrf, $error): void {
        $requireAuth();
        $validateCsrf($payload);
        $error(501, 'Odds senkronizasyonu bu panelde yok; sportsbook sağlayıcı back-office kullanılır.');
    }],
    ['POST', 'internal/jobs/recalculate-balances', static function (array $params, array $payload) use ($requireAuth, $validateCsrf, $success): void {
        $requireAuth();
        $validateCsrf($payload);
        $pdo = AdminDatabase::pdo();
        $checked = 0;
        $mismatches = 0;
        $samples = [];
        try {
            $rows = $pdo->query(
                "SELECT u.id, u.username, u.bonus_balance,
                        COALESCE((SELECT SUM(current_bonus_balance) FROM user_active_bonuses b WHERE b.user_id = u.id AND b.status = 'active'), 0) AS active_bonus_sum
                 FROM users u
                 WHERE u.bonus_balance > 0 OR EXISTS (
                    SELECT 1 FROM user_active_bonuses b WHERE b.user_id = u.id AND b.status = 'active'
                 )
                 ORDER BY u.id DESC
                 LIMIT 500"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $checked++;
                $wallet = round((float) ($row['bonus_balance'] ?? 0), 2);
                $sum = round((float) ($row['active_bonus_sum'] ?? 0), 2);
                if (abs($wallet - $sum) > 0.01) {
                    $mismatches++;
                    if (count($samples) < 20) {
                        $samples[] = [
                            'user_id' => (int) ($row['id'] ?? 0),
                            'username' => (string) ($row['username'] ?? ''),
                            'bonus_balance' => $wallet,
                            'active_bonus_sum' => $sum,
                            'delta' => round($wallet - $sum, 2),
                        ];
                    }
                }
            }
        } catch (Throwable $exception) {
            $success([
                'queued' => false,
                'checked' => $checked,
                'mismatches' => $mismatches,
                'error' => $exception->getMessage(),
            ], ['status' => 'partial']);
            return;
        }
        $success([
            'queued' => false,
            'mode' => 'audit_only',
            'checked' => $checked,
            'mismatches' => $mismatches,
            'samples' => $samples,
        ], ['status' => 'ok']);
    }],
    ['GET', 'internal/health', static function () use ($requireAuth, $success): void {
        $requireAuth();
        $success(['service' => 'admin-api-v2', 'time' => date('c'), 'status' => 'ok']);
    }],
    ['GET', 'internal/metrics', static function () use ($requireAuth, $success): void {
        $requireAuth();
        $pdo = AdminDatabase::pdo();
        $scalar = static function (string $sql) use ($pdo): float {
            try {
                return (float) $pdo->query($sql)->fetchColumn();
            } catch (Throwable) {
                return 0.0;
            }
        };
        $success([
            'metrics' => [
                'users_total' => (int) $scalar('SELECT COUNT(*) FROM users'),
                'users_active_24h' => (int) $scalar("SELECT COUNT(*) FROM users WHERE COALESCE(last_login_at, updated_at, created_at) >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"),
                'withdrawals_pending' => (int) $scalar("SELECT COUNT(*) FROM megapayz_transactions WHERE type='withdraw' AND status='pending'"),
                'deposits_pending' => (int) $scalar("SELECT COUNT(*) FROM megapayz_transactions WHERE type='deposit' AND status='pending'"),
                'kyc_pending' => (int) $scalar("SELECT COUNT(*) FROM kyc_requests WHERE status='pending'"),
                'call_requests_pending' => (int) $scalar("SELECT COUNT(*) FROM call_me_requests WHERE status='pending'"),
                'support_tickets_open' => (int) $scalar("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','answered')"),
                'aml_alerts_open' => (int) $scalar("SELECT COUNT(*) FROM aml_alerts WHERE status IN ('open','new','pending')"),
                'risk_alerts_open' => (int) $scalar("SELECT COUNT(*) FROM risk_alerts WHERE status IN ('open','new','pending')"),
                'deposit_volume_today' => $scalar("SELECT COALESCE(SUM(amount),0) FROM megapayz_transactions WHERE type='deposit' AND status IN ('confirmed','approved','success','completed') AND DATE(created_at)=CURDATE()"),
                'withdraw_volume_today' => $scalar("SELECT COALESCE(SUM(amount),0) FROM megapayz_transactions WHERE type='withdraw' AND status IN ('confirmed','approved','success','completed') AND DATE(created_at)=CURDATE()"),
            ],
            'generated_at' => date('c'),
        ], ['status' => 'ok']);
    }],
    ['GET', 'promotions', static function (array $params, array $payload) use ($requirePermission, $success, $getInput): void {
        $requirePermission('promotions');
        $pdo = AdminDatabase::pdo();
        $page = max(1, (int) $getInput($payload, 'page', 1));
        $perPage = min(100, max(10, (int) $getInput($payload, 'per_page', 25)));
        $offset = ($page - 1) * $perPage;
        try {
            $total = (int) $pdo->query('SELECT COUNT(*) FROM promotions')->fetchColumn();
            $stmt = $pdo->prepare('SELECT * FROM promotions ORDER BY sort_order ASC, id DESC LIMIT :limit OFFSET :offset');
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $items = [];
            $total = 0;
        }
        $success(['items' => is_array($items) ? $items : [], 'total' => $total], ['page' => $page, 'per_page' => $perPage]);
    }],
    ['POST', 'promotions', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('promotions');
        $validateCsrf($payload);
        $body = is_array($payload['body'] ?? null) ? $payload['body'] : [];
        $title = trim((string) ($body['title'] ?? ''));
        if ($title === '') {
            $error(422, 'title zorunludur.');
        }
        $normalizePromotionImageUrl = static function (string $imageUrl): string {
            $imageUrl = trim($imageUrl);
            if ($imageUrl === '') {
                return '';
            }

            if (preg_match('#^https?://#i', $imageUrl) === 1) {
                $host = strtolower((string) (parse_url($imageUrl, PHP_URL_HOST) ?? ''));
                if (preg_match('/^(?:icons|cms)\.casinomilyon\d+\.com$/i', $host) === 1) {
                    return $imageUrl;
                }

                $path = (string) (parse_url($imageUrl, PHP_URL_PATH) ?? '');
                if ($path !== '') {
                    $imageUrl = $path;
                }
            }

            $imageUrl = '/' . ltrim(str_replace('\\', '/', $imageUrl), '/');
            $lower = strtolower($imageUrl);
            if (str_starts_with($lower, '/storage/uploads/')) {
                return '/uploads/' . ltrim(substr($imageUrl, strlen('/storage/uploads/')), '/');
            }
            if (str_starts_with($lower, '/admin/uploads/')) {
                return '/uploads/' . ltrim(substr($imageUrl, strlen('/admin/uploads/')), '/');
            }

            return $imageUrl;
        };
        $normalizePromotionLinkUrl = static function (string $linkUrl): string {
            $linkUrl = trim($linkUrl);
            if ($linkUrl === '') {
                return '';
            }
            if (preg_match('#^(?:javascript|data):#i', $linkUrl) === 1) {
                return '';
            }
            if (preg_match('#^https?://#i', $linkUrl) === 1) {
                return $linkUrl;
            }
            if (str_starts_with($linkUrl, '//')) {
                return 'https:' . $linkUrl;
            }
            $linkUrl = str_replace('\\', '/', $linkUrl);
            if ($linkUrl[0] === '/' || $linkUrl[0] === '?') {
                return $linkUrl;
            }

            return '/' . ltrim($linkUrl, '/');
        };
        $pdo = AdminDatabase::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO promotions (title, description, type, category, status, sort_order, image_url, link_url, bonus_type, bonus_amount, bonus_rules, wagering_multiplier)
             VALUES (:title, :description, :type, :category, :status, :sort_order, :image_url, :link_url, :bonus_type, :bonus_amount, :bonus_rules, :wagering_multiplier)'
        );
        $stmt->execute([
            'title' => $title,
            'description' => trim((string) ($body['description'] ?? '')),
            'type' => trim((string) ($body['type'] ?? '')),
            'category' => trim((string) ($body['category'] ?? '')),
            'status' => trim((string) ($body['status'] ?? 'active')),
            'sort_order' => (int) ($body['sort_order'] ?? 0),
            'image_url' => $normalizePromotionImageUrl((string) ($body['image_url'] ?? '')),
            'link_url' => $normalizePromotionLinkUrl((string) ($body['link_url'] ?? '')),
            'bonus_type' => trim((string) ($body['bonus_type'] ?? '')),
            'bonus_amount' => (float) ($body['bonus_amount'] ?? 0),
            'bonus_rules' => trim((string) ($body['bonus_rules'] ?? '')),
            'wagering_multiplier' => (float) ($body['wagering_multiplier'] ?? 0),
        ]);
        $success(['id' => (int) $pdo->lastInsertId(), 'created' => true]);
    }],
    ['POST', 'promotions/{id}', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('promotions');
        $validateCsrf($payload);
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $error(422, 'Geçersiz promosyon.');
        }
        $body = is_array($payload['body'] ?? null) ? $payload['body'] : [];
        $normalizePromotionImageUrl = static function (string $imageUrl): string {
            $imageUrl = trim($imageUrl);
            if ($imageUrl === '') {
                return '';
            }

            if (preg_match('#^https?://#i', $imageUrl) === 1) {
                $host = strtolower((string) (parse_url($imageUrl, PHP_URL_HOST) ?? ''));
                if (preg_match('/^(?:icons|cms)\.casinomilyon\d+\.com$/i', $host) === 1) {
                    return $imageUrl;
                }

                $path = (string) (parse_url($imageUrl, PHP_URL_PATH) ?? '');
                if ($path !== '') {
                    $imageUrl = $path;
                }
            }

            $imageUrl = '/' . ltrim(str_replace('\\', '/', $imageUrl), '/');
            $lower = strtolower($imageUrl);
            if (str_starts_with($lower, '/storage/uploads/')) {
                return '/uploads/' . ltrim(substr($imageUrl, strlen('/storage/uploads/')), '/');
            }
            if (str_starts_with($lower, '/admin/uploads/')) {
                return '/uploads/' . ltrim(substr($imageUrl, strlen('/admin/uploads/')), '/');
            }

            return $imageUrl;
        };
        $normalizePromotionLinkUrl = static function (string $linkUrl): string {
            $linkUrl = trim($linkUrl);
            if ($linkUrl === '') {
                return '';
            }
            if (preg_match('#^(?:javascript|data):#i', $linkUrl) === 1) {
                return '';
            }
            if (preg_match('#^https?://#i', $linkUrl) === 1) {
                return $linkUrl;
            }
            if (str_starts_with($linkUrl, '//')) {
                return 'https:' . $linkUrl;
            }
            $linkUrl = str_replace('\\', '/', $linkUrl);
            if ($linkUrl[0] === '/' || $linkUrl[0] === '?') {
                return $linkUrl;
            }

            return '/' . ltrim($linkUrl, '/');
        };
        $fields = ['title', 'description', 'type', 'category', 'status', 'image_url', 'link_url', 'bonus_type', 'bonus_rules'];
        $sets = [];
        $bind = ['id' => $id];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            $sets[] = $field . ' = :' . $field;
            if ($field === 'image_url') {
                $bind[$field] = $normalizePromotionImageUrl((string) $body[$field]);
                continue;
            }
            if ($field === 'link_url') {
                $bind[$field] = $normalizePromotionLinkUrl((string) $body[$field]);
                continue;
            }
            $bind[$field] = trim((string) $body[$field]);
        }
        foreach (['sort_order' => PDO::PARAM_INT, 'bonus_amount' => null, 'wagering_multiplier' => null] as $field => $type) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            $sets[] = $field . ' = :' . $field;
            $bind[$field] = $body[$field];
        }
        if ($sets === []) {
            $error(422, 'Güncellenecek alan yok.');
        }
        $pdo = AdminDatabase::pdo();
        $pdo->prepare('UPDATE promotions SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($bind);
        $success(['id' => $id, 'updated' => true]);
    }],
    ['DELETE', 'promotions/{id}', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('promotions');
        $validateCsrf($payload);
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $error(422, 'Geçersiz promosyon ID.');
        }
        $pdo = AdminDatabase::pdo();
        $stmt = $pdo->prepare('SELECT id FROM promotions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        if (!$stmt->fetch()) {
            $error(404, 'Promosyon bulunamadı.');
        }
        $pdo->prepare('DELETE FROM promotions WHERE id = :id')->execute(['id' => $id]);
        $success(['id' => $id, 'deleted' => true]);
    }],
    ['GET', 'promotions/{id}/claims', static function (array $params, array $payload) use ($requirePermission, $success, $error, $getInput): void {
        $requirePermission('promotions');
        $promoId = (int) ($params['id'] ?? 0);
        if ($promoId <= 0) { $error(422, 'Geçersiz promosyon ID.'); }
        $pdo  = AdminDatabase::pdo();
        // Confirm promotion exists
        $chk = $pdo->prepare('SELECT id, title FROM promotions WHERE id = :id LIMIT 1');
        $chk->execute(['id' => $promoId]);
        $promo = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$promo) { $error(404, 'Promosyon bulunamadı.'); }
        $limit  = min(200, max(1, (int) ($payload['query']['limit'] ?? 50)));
        $page   = max(1, (int) ($payload['query']['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $status = trim((string) ($payload['query']['status'] ?? ''));
        $where  = ['b.promotion_id = :pid'];
        $bind   = ['pid' => $promoId];
        if ($status !== '') {
            $where[] = 'b.status = :status';
            $bind['status'] = $status;
        }
        $whereSQL = implode(' AND ', $where);
        // Total count
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM user_active_bonuses b WHERE $whereSQL");
        $cntStmt->execute($bind);
        $total = (int) $cntStmt->fetchColumn();
        // Claims with user info
        $stmt = $pdo->prepare("
            SELECT b.id, b.user_id, b.name, b.status,
                   b.initial_amount, b.current_bonus_balance,
                   b.wagering_requirement, b.total_bet_amount,
                   b.granted_at, b.deadline, b.completed_at,
                   u.username, u.email
            FROM user_active_bonuses b
            LEFT JOIN users u ON u.id = b.user_id
            WHERE $whereSQL
            ORDER BY b.id DESC
            LIMIT :lim OFFSET :off
        ");
        foreach ($bind as $k => $v) { $stmt->bindValue(':' . $k, $v); }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $success([
            'promotion_id'    => $promoId,
            'promotion_title' => $promo['title'] ?? '',
            'items'           => $claims,
            'total'           => $total,
            'page'            => $page,
            'limit'           => $limit,
        ]);
    }],
    ['POST', 'bonuses/assign', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('promotions');
        $validateCsrf($payload);
        $body       = is_array($payload['body'] ?? null) ? $payload['body'] : [];
        $userId     = (int) ($body['user_id'] ?? 0);
        $promoId    = (int) ($body['promotion_id'] ?? 0);
        $amount     = (float) ($body['amount'] ?? 0);
        $wagering   = (float) ($body['wagering_multiplier'] ?? 1);
        if ($userId <= 0)  { $error(422, 'user_id zorunludur.'); }
        if ($promoId <= 0 && $amount <= 0) { $error(422, 'promotion_id veya amount zorunludur.'); }
        $pdo    = AdminDatabase::pdo();
        $uChk = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $uChk->execute(['id' => $userId]);
        if (!$uChk->fetch()) { $error(404, 'Kullanıcı bulunamadı.'); }
        $admin  = AdminAuth::user();
        $promo  = null;
        if ($promoId > 0) {
            $s = $pdo->prepare("SELECT * FROM promotions WHERE id = :id AND status = 'active' LIMIT 1");
            $s->execute(['id' => $promoId]);
            $promo = $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $bonusAmount   = $promo ? (float) ($promo['bonus_amount'] ?? $amount) : $amount;
        $bonusName     = $promo ? (string) ($promo['title'] ?? 'Manuel Bonus') : trim((string) ($body['name'] ?? 'Manuel Bonus'));
        $wageringMult  = $promo ? (float) ($promo['wagering_multiplier'] ?? $wagering) : $wagering;
        $wageringTarget = $bonusAmount * max(1, $wageringMult);
        if (isset($body['deadline']) && trim((string) $body['deadline']) !== '') {
            $deadline = trim((string) $body['deadline']);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $deadline) || strtotime($deadline) === false) {
                $error(422, 'deadline geçerli bir tarih formatında olmalıdır (YYYY-MM-DD).');
            }
        } else {
            $deadline = date('Y-m-d H:i:s', strtotime('+30 days'));
        }
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                "INSERT INTO user_active_bonuses
                 (user_id, promotion_id, name, category, initial_amount, current_bonus_balance,
                  wagering_requirement, wagering_target, total_bet_amount, status, granted_at, deadline)
                 VALUES
                 (:user_id, :promotion_id, :name, :category, :initial_amount, :current_amount,
                  :wagering_req, :wagering_target, 0, 'active', NOW(), :deadline)"
            )->execute([
                'user_id'        => $userId,
                'promotion_id'   => $promoId > 0 ? $promoId : null,
                'name'           => $bonusName,
                'category'       => $promo ? (string) ($promo['type'] ?? 'manual') : 'manual',
                'initial_amount' => number_format($bonusAmount, 2, '.', ''),
                'current_amount' => number_format($bonusAmount, 2, '.', ''),
                'wagering_req'   => $wageringMult,
                'wagering_target'=> number_format($wageringTarget, 2, '.', ''),
                'deadline'       => $deadline,
            ]);
            $insertId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                "INSERT INTO admin_audit_logs (admin_id, admin_username, action, entity_type, entity_id, note, ip_address, created_at)
                 VALUES (:admin_id, :admin_username, 'bonus_assign', 'user', :entity_id, :note, :ip, NOW())"
            )->execute([
                'admin_id'       => (int) ($admin['id'] ?? 0),
                'admin_username' => (string) ($admin['username'] ?? 'Admin'),
                'entity_id'      => (string) $userId,
                'note'           => "Bonus atandı: $bonusName ($bonusAmount TRY)",
                'ip'             => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error(500, 'Bonus ataması başarısız.', ['reason' => $e->getMessage()]);
        }
        $success(['assigned' => true, 'bonus_id' => $insertId, 'user_id' => $userId, 'amount' => $bonusAmount]);
    }],
    ['POST', 'bonuses/revoke', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('promotions');
        $validateCsrf($payload);
        $body    = is_array($payload['body'] ?? null) ? $payload['body'] : [];
        $bonusId = (int) ($body['bonus_id'] ?? 0);
        $userId  = (int) ($body['user_id'] ?? 0);
        if ($bonusId <= 0 && $userId <= 0) { $error(422, 'bonus_id veya user_id zorunludur.'); }
        $pdo   = AdminDatabase::pdo();
        $admin = AdminAuth::user();
        $where = $bonusId > 0 ? 'id = :id' : "user_id = :user_id AND status = 'active'";
        $bind  = $bonusId > 0 ? ['id' => $bonusId] : ['user_id' => $userId];
        $stmt  = $pdo->prepare("UPDATE user_active_bonuses SET status = 'cancelled', current_bonus_balance = 0 WHERE $where");
        $stmt->execute($bind);
        $revoked = $stmt->rowCount();
        if ($revoked === 0) { $error(404, 'Aktif bonus bulunamadı.'); }
        try {
            $pdo->prepare(
                "INSERT INTO admin_audit_logs (admin_id, admin_username, action, entity_type, entity_id, note, ip_address, created_at)
                 VALUES (:admin_id, :admin_username, 'bonus_revoke', 'user', :entity_id, :note, :ip, NOW())"
            )->execute([
                'admin_id'       => (int) ($admin['id'] ?? 0),
                'admin_username' => (string) ($admin['username'] ?? 'Admin'),
                'entity_id'      => (string) ($bonusId > 0 ? $bonusId : $userId),
                'note'           => "Bonus iptal edildi. Etkilenen kayıt: $revoked",
                'ip'             => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable) {}
        $success(['revoked' => true, 'count' => $revoked]);
    }],
    ['GET', 'pages', static function (array $params, array $payload) use ($requirePermission, $success, $getInput): void {
        $requirePermission('footer-pages');
        $pdo     = AdminDatabase::pdo();
        $page    = max(1, (int) $getInput($payload, 'page', 1));
        $perPage = min(100, max(10, (int) $getInput($payload, 'per_page', 25)));
        $offset  = ($page - 1) * $perPage;
        try {
            $total = (int) $pdo->query('SELECT COUNT(*) FROM footer_pages')->fetchColumn();
            $stmt  = $pdo->prepare('SELECT id, slug, title, is_active, sort_order, created_at, updated_at FROM footer_pages ORDER BY sort_order ASC, id DESC LIMIT :limit OFFSET :offset');
            $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) { $items = []; $total = 0; }
        $success(['items' => $items, 'total' => $total], ['page' => $page, 'per_page' => $perPage]);
    }],
    ['POST', 'pages', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('footer-pages');
        $validateCsrf($payload);
        $body  = is_array($payload['body'] ?? null) ? $payload['body'] : [];
        $title = trim((string) ($body['title'] ?? ''));
        $slug  = trim((string) ($body['slug'] ?? ''));
        if ($title === '') { $error(422, 'title zorunludur.'); }
        if ($slug === '') {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title) ?? '');
            $slug = trim($slug, '-');
        }
        $pdo = AdminDatabase::pdo();
        $pdo->prepare(
            'INSERT INTO footer_pages (slug, title, content, meta_title, meta_description, is_active, sort_order, created_at, updated_at)
             VALUES (:slug, :title, :content, :meta_title, :meta_desc, :is_active, :sort_order, NOW(), NOW())'
        )->execute([
            'slug'       => $slug,
            'title'      => $title,
            'content'    => trim((string) ($body['content'] ?? '')),
            'meta_title' => trim((string) ($body['meta_title'] ?? $title)),
            'meta_desc'  => trim((string) ($body['meta_description'] ?? '')),
            'is_active'  => (int) ($body['is_active'] ?? 1),
            'sort_order' => (int) ($body['sort_order'] ?? 0),
        ]);
        $success(['id' => (int) $pdo->lastInsertId(), 'slug' => $slug, 'created' => true]);
    }],
    ['POST', 'pages/{id}', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('footer-pages');
        $validateCsrf($payload);
        $id   = (int) ($params['id'] ?? 0);
        $body = is_array($payload['body'] ?? null) ? $payload['body'] : [];
        if ($id <= 0) { $error(422, 'Geçersiz sayfa ID.'); }
        $pdo    = AdminDatabase::pdo();
        $fields = ['slug', 'title', 'content', 'meta_title', 'meta_description', 'is_active', 'sort_order'];
        $sets   = [];
        $bind   = ['id' => $id];
        foreach ($fields as $f) {
            if (!array_key_exists($f, $body)) continue;
            $sets[] = "$f = :$f";
            $bind[$f] = in_array($f, ['is_active', 'sort_order'], true) ? (int) $body[$f] : trim((string) $body[$f]);
        }
        if ($sets === []) { $error(422, 'Güncellenecek alan yok.'); }
        $sets[] = 'updated_at = NOW()';
        $pdo->prepare('UPDATE footer_pages SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($bind);
        $success(['id' => $id, 'updated' => true]);
    }],
    ['DELETE', 'pages/{id}', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('footer-pages');
        $validateCsrf($payload);
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) { $error(422, 'Geçersiz sayfa ID.'); }
        $pdo  = AdminDatabase::pdo();
        $stmt = $pdo->prepare('SELECT id FROM footer_pages WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        if (!$stmt->fetch()) { $error(404, 'Sayfa bulunamadı.'); }
        $pdo->prepare('DELETE FROM footer_pages WHERE id = :id')->execute(['id' => $id]);
        $success(['id' => $id, 'deleted' => true]);
    }],
    ['GET', 'banners', static function (array $params, array $payload) use ($requirePermission, $success, $getInput): void {
        $requirePermission('sliders');
        $pdo      = AdminDatabase::pdo();
        $page     = max(1, (int) $getInput($payload, 'page', 1));
        $perPage  = min(100, max(10, (int) $getInput($payload, 'per_page', 25)));
        $offset   = ($page - 1) * $perPage;
        $category = trim((string) $getInput($payload, 'category', ''));
        $where    = $category !== '' ? 'WHERE category = :category' : '';
        $bind     = $category !== '' ? ['category' => $category] : [];
        try {
            $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM sliders $where");
            $totalStmt->execute($bind);
            $total = (int) $totalStmt->fetchColumn();
            $stmt  = $pdo->prepare("SELECT * FROM sliders $where ORDER BY sort_order ASC, id DESC LIMIT :limit OFFSET :offset");
            foreach ($bind as $k => $v) $stmt->bindValue(":$k", $v);
            $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) { $items = []; $total = 0; }
        $success(['items' => $items, 'total' => $total], ['page' => $page, 'per_page' => $perPage]);
    }],
    ['POST', 'banners', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('sliders');
        $validateCsrf($payload);
        $body     = is_array($payload['body'] ?? null) ? $payload['body'] : [];
        $imageUrl = trim((string) ($body['image_url'] ?? $body['image'] ?? ''));
        if ($imageUrl === '') { $error(422, 'image_url zorunludur.'); }
        $pdo = AdminDatabase::pdo();
        $pdo->prepare(
            'INSERT INTO sliders (title, subtitle, description, image_url, link_url, button_text, category, surface, is_active, sort_order, starts_at, ends_at, created_at, updated_at)
             VALUES (:title, :subtitle, :description, :image_url, :link_url, :button_text, :category, :surface, :is_active, :sort_order, :starts_at, :ends_at, NOW(), NOW())'
        )->execute([
            'title'       => trim((string) ($body['title']       ?? '')),
            'subtitle'    => trim((string) ($body['subtitle']    ?? '')),
            'description' => trim((string) ($body['description'] ?? '')),
            'image_url'   => $imageUrl,
            'link_url'    => trim((string) ($body['link_url']    ?? '')),
            'button_text' => trim((string) ($body['button_text'] ?? '')),
            'category'    => trim((string) ($body['category']    ?? 'home')),
            'surface'     => trim((string) ($body['surface']     ?? 'all')),
            'is_active'   => (int) ($body['is_active']   ?? 1),
            'sort_order'  => (int) ($body['sort_order']  ?? 0),
            'starts_at'   => trim((string) ($body['starts_at']  ?? '')) ?: null,
            'ends_at'     => trim((string) ($body['ends_at']    ?? '')) ?: null,
        ]);
        $success(['id' => (int) $pdo->lastInsertId(), 'created' => true]);
        if (function_exists('notify_frontend_cms_purge')) {
            notify_frontend_cms_purge('sliders');
        }
    }],
    ['POST', 'banners/{id}', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('sliders');
        $validateCsrf($payload);
        $id   = (int) ($params['id'] ?? 0);
        $body = is_array($payload['body'] ?? null) ? $payload['body'] : [];
        if ($id <= 0) { $error(422, 'Geçersiz banner ID.'); }
        $pdo      = AdminDatabase::pdo();
        $strFields = ['title', 'subtitle', 'description', 'image_url', 'link_url', 'button_text', 'category', 'surface', 'starts_at', 'ends_at'];
        $intFields = ['is_active', 'sort_order'];
        $sets = []; $bind = ['id' => $id];
        foreach ($strFields as $f) { if (array_key_exists($f, $body)) { $sets[] = "$f = :$f"; $bind[$f] = trim((string) $body[$f]); } }
        foreach ($intFields as $f) { if (array_key_exists($f, $body)) { $sets[] = "$f = :$f"; $bind[$f] = (int) $body[$f]; } }
        if ($sets === []) { $error(422, 'Güncellenecek alan yok.'); }
        $sets[] = 'updated_at = NOW()';
        $pdo->prepare('UPDATE sliders SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($bind);
        $success(['id' => $id, 'updated' => true]);
        if (function_exists('notify_frontend_cms_purge')) {
            notify_frontend_cms_purge('sliders');
        }
    }],
    ['DELETE', 'banners/{id}', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('sliders');
        $validateCsrf($payload);
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) { $error(422, 'Geçersiz banner ID.'); }
        $pdo  = AdminDatabase::pdo();
        $stmt = $pdo->prepare('SELECT id FROM sliders WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        if (!$stmt->fetch()) { $error(404, 'Banner bulunamadı.'); }
        $pdo->prepare('DELETE FROM sliders WHERE id = :id')->execute(['id' => $id]);
        $success(['id' => $id, 'deleted' => true]);
        if (function_exists('notify_frontend_cms_purge')) {
            notify_frontend_cms_purge('sliders');
        }
    }],
    ['GET', 'call-requests', static function (array $params, array $payload) use ($requirePermission, $success, $repo, $getInput): void {
        $requirePermission('call-requests');
        $page = max(1, (int) $getInput($payload, 'page', 1));
        $perPage = min(100, max(10, (int) $getInput($payload, 'per_page', 25)));
        $search = trim((string) $getInput($payload, 'search', ''));
        $table = 'call_me_requests';
        $success([
            'table' => $table,
            'columns' => $repo->columns($table),
            'rows' => $repo->rows($table, $page, $perPage, $search),
        ], [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $repo->countRows($table, $search),
        ]);
    }],
    ['GET', 'support/tickets', static function (array $params, array $payload) use ($requirePermission, $success, $getInput): void {
        $requirePermission('support-tickets');
        SupportTicketService::ensureTables(AdminDatabase::pdo());
        $pdo = AdminDatabase::pdo();
        $page = max(1, (int) $getInput($payload, 'page', 1));
        $perPage = min(100, max(10, (int) $getInput($payload, 'per_page', 25)));
        $status = trim((string) $getInput($payload, 'status', ''));
        $offset = ($page - 1) * $perPage;
        $where = $status !== '' ? 'WHERE status = :status' : '';
        $bind = $status !== '' ? ['status' => $status] : [];
        try {
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM support_tickets ' . $where);
            $countStmt->execute($bind);
            $total = (int) $countStmt->fetchColumn();
            $sql = 'SELECT t.*, u.username, u.email FROM support_tickets t
                    LEFT JOIN users u ON u.id = t.user_id ' . $where . '
                    ORDER BY t.updated_at DESC LIMIT :limit OFFSET :offset';
            $stmt = $pdo->prepare($sql);
            foreach ($bind as $k => $v) {
                $stmt->bindValue(':' . $k, $v);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $items = [];
            $total = 0;
        }
        $success(['items' => is_array($items) ? $items : [], 'total' => $total], ['page' => $page, 'per_page' => $perPage]);
    }],
    ['GET', 'support/tickets/{id}', static function (array $params) use ($requirePermission, $success, $error): void {
        $requirePermission('support-tickets');
        SupportTicketService::ensureTables(AdminDatabase::pdo());
        $ticketId = (int) ($params['id'] ?? 0);
        if ($ticketId <= 0) { $error(422, 'Geçersiz ticket ID.'); }
        $pdo = AdminDatabase::pdo();
        $stmt = $pdo->prepare(
            'SELECT t.*, u.username, u.email FROM support_tickets t
             LEFT JOIN users u ON u.id = t.user_id WHERE t.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) { $error(404, 'Ticket bulunamadı.'); }
        $replies = [];
        try {
            $rStmt = $pdo->prepare(
                'SELECT id, sender_type, sender_name, message, created_at
                 FROM support_replies WHERE ticket_id = :id ORDER BY id ASC LIMIT 100'
            );
            $rStmt->execute(['id' => $ticketId]);
            $replies = $rStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {}
        $success(['ticket' => $ticket, 'replies' => $replies]);
    }],
    ['POST', 'support/tickets/{id}/reply', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('support-tickets');
        $validateCsrf($payload);
        $ticketId = (int) ($params['id'] ?? 0);
        $message = trim((string) ($payload['body']['message'] ?? ''));
        if ($ticketId <= 0 || $message === '') {
            $error(422, 'ticket id ve message zorunludur.');
        }
        SupportTicketService::adminReply(AdminDatabase::pdo(), $ticketId, AdminAuth::userName(), $message);
        $success(['ticket_id' => $ticketId, 'replied' => true]);
    }],
    ['POST', 'users', static function (array $params, array $payload) use ($requirePermission, $validateCsrf, $success, $error): void {
        $requirePermission('users');
        $validateCsrf($payload);
        $body = is_array($payload['body'] ?? null) ? $payload['body'] : [];
        $name = trim((string) ($body['name'] ?? ''));
        $surname = trim((string) ($body['surname'] ?? ''));
        $username = trim((string) ($body['username'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));
        $phone = preg_replace('/\D+/', '', trim((string) ($body['phone'] ?? '')));
        $password = trim((string) ($body['password'] ?? ''));
        $gender = trim((string) ($body['gender'] ?? 'Erkek'));
        $dob = trim((string) ($body['dob'] ?? ''));
        $country = strtoupper(trim((string) ($body['country'] ?? 'TR')));
        foreach (['name' => $name, 'surname' => $surname, 'username' => $username, 'email' => $email] as $f => $v) {
            if ($v === '') { $error(422, "$f zorunludur."); }
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) { $error(422, 'Geçerli bir email girin.'); }
        if (strlen($password) < 6) { $error(422, 'Şifre en az 6 karakter olmalıdır.'); }
        if (!in_array($gender, ['Erkek', 'Kadın', 'Diğer'], true)) { $error(422, 'Geçerli bir cinsiyet seçin.'); }
        if ($dob !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) !== 1) { $error(422, 'dob YYYY-MM-DD formatında olmalıdır.'); }
        $pdo = AdminDatabase::pdo();
        foreach (['username' => $username, 'email' => $email] as $f => $v) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $f = :v");
            $chk->execute(['v' => $v]);
            if ((int) $chk->fetchColumn() > 0) { $error(422, "$f başka bir kullanıcıda kayıtlı."); }
        }
        $base = preg_replace('/[^a-z0-9]/i', '', strtolower($username));
        $base = is_string($base) && $base !== '' ? substr($base, 0, 18) : 'user';
        $referralCode = null;
        for ($i = 0; $i < 6; $i++) {
            $candidate = strtoupper($base . substr(bin2hex(random_bytes(4)), 0, 8));
            $chk = $pdo->prepare('SELECT 1 FROM users WHERE referral_code = :code LIMIT 1');
            $chk->execute(['code' => $candidate]);
            if (!$chk->fetchColumn()) { $referralCode = $candidate; break; }
        }
        $pdo->prepare(
            'INSERT INTO users (name, surname, username, email, identity_number, gender, dob, phone, city, country, password, referral_code, address, is_verified, banned, is_test, password_changed_at, created_at)
             VALUES (:name, :surname, :username, :email, :identity_number, :gender, :dob, :phone, :city, :country, :password, :referral_code, :address, :is_verified, 0, :is_test, NOW(), NOW())'
        )->execute([
            'name' => $name, 'surname' => $surname, 'username' => $username, 'email' => $email,
            'identity_number' => trim((string) ($body['identity_number'] ?? '')),
            'gender' => $gender, 'dob' => $dob !== '' ? $dob : null,
            'phone' => $phone !== '' ? $phone : null,
            'city' => trim((string) ($body['city'] ?? '')),
            'country' => $country !== '' ? $country : 'TR',
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'referral_code' => $referralCode,
            'address' => trim((string) ($body['address'] ?? '')) ?: null,
            'is_verified' => (int) ($body['is_verified'] ?? 0),
            'is_test' => (int) ($body['is_test'] ?? 0),
        ]);
        $newId = (int) $pdo->lastInsertId();
        $success(['id' => $newId, 'username' => $username, 'created' => true]);
    }],
];
