<?php
/** Üye API modülü — index.php tarafından include edilir. */

admin_require_project_file('services/WageringService.php');

if ($method === 'GET' && ($route === 'balance.php' || $route === 'account/balance')) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $user = $memberUserById($pdo, $userId);
    if (!$user) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Kullanıcı bulunamadı.']);
    }
    $mainBalance = (float) ($user['balance'] ?? $user['ana_bakiye'] ?? 0);
    $bonusBalance = (float) ($user['bonus_balance'] ?? $user['bonus_bakiye'] ?? 0);
    $totalBalance = round($mainBalance + $bonusBalance, 2);
    $formatBalance = static function (float $amount): string {
        return number_format($amount, 2, ',', '.') . ' ₺';
    };
    $wagering = WageringService::accountProgress($pdo, $userId);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Bakiye bilgisi',
        'data' => [
            'balance' => [
                'balance' => $mainBalance,
                'bonus_balance' => $bonusBalance,
                'total_balance' => $totalBalance,
                'formatted' => $formatBalance($mainBalance),
                'bonus_formatted' => $formatBalance($bonusBalance),
                'total_formatted' => $formatBalance($totalBalance),
                'currency' => 'TRY',
                'currency_symbol' => '₺',
            ],
            'amount' => $mainBalance,
            'bonus_balance' => $bonusBalance,
            'total_balance' => $totalBalance,
            'ana_bakiye' => $mainBalance,
            'bonus_bakiye' => $bonusBalance,
            'toplam_bonus' => $bonusBalance,
            'wagering' => [
                'required' => $wagering['required'],
                'progress' => $wagering['progress'],
                'remaining' => $wagering['remaining'],
                'percent' => $wagering['percent'],
                'is_complete' => $wagering['isComplete'],
                'multiplier' => $wagering['multiplier'],
                'required_formatted' => $formatBalance($wagering['required']),
                'progress_formatted' => $formatBalance($wagering['progress']),
                'remaining_formatted' => $formatBalance($wagering['remaining']),
            ],
        ],
    ]);
}

if ($method === 'GET' && in_array($route, ['loyalty.php', 'loyalty/me', 'loyalty/levels'], true)) {
    $pdo = AdminDatabase::pdo();
    admin_require_project_file('api/bootstrap.php');
    ApiLoyalty::ensureStorage($pdo);

    if ($route === 'loyalty/levels') {
        $stmt = $pdo->query(
            'SELECT code, name, min_points, cashback_rate, weekly_bonus_amount, icon_url, color_hex, sort_order
             FROM loyalty_levels
             WHERE is_active = 1
             ORDER BY min_points ASC, sort_order ASC, id ASC'
        );
        $levels = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Sadakat seviyeleri',
            'data' => [
                'levels' => array_map(static fn (array $level): array => [
                    'code' => (string) ($level['code'] ?? ''),
                    'name' => (string) ($level['name'] ?? ''),
                    'min_points' => (int) ($level['min_points'] ?? 0),
                    'cashback_rate' => (float) ($level['cashback_rate'] ?? 0),
                    'weekly_bonus_amount' => (float) ($level['weekly_bonus_amount'] ?? 0),
                    'icon_url' => (string) ($level['icon_url'] ?? ''),
                    'color_hex' => (string) ($level['color_hex'] ?? ''),
                    'sort_order' => (int) ($level['sort_order'] ?? 0),
                ], $levels),
            ],
        ]);
    }

    $userId = $memberRequireLogin();
    $loyalty = ApiLoyalty::fetchForUser($userId);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Sadakat bilgisi',
        'data' => $loyalty + ['badge' => ApiLoyalty::publicBadgeForUser($userId)],
    ]);
}

if ($method === 'GET' && in_array($route, ['freespins.php', 'me/freespins', 'profile/freespins'], true)) {
    $userId = $memberRequireLogin();
    $tab = strtolower(trim((string) ($_GET['tab'] ?? 'yeni'))) === 'aktif' ? 'aktif' : 'yeni';
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Freespin listesi',
        'data' => ['tab' => $tab, 'items' => BgamingService::memberFreespins(AdminDatabase::pdo(), $userId, $tab)],
    ]);
}

if ($method === 'GET' && in_array($route, ['profile_detail.php', 'profile/detail', 'account/profile', 'account/detail', 'user/profile'], true)) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $user = $memberUserById($pdo, $userId);
    if (!$user) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Kullanıcı bulunamadı.']);
    }
    $deposits = MegaPayzService::history($pdo, $userId, 'deposit', ['limit' => 25]);
    $withdrawals = MegaPayzService::history($pdo, $userId, 'withdraw', ['limit' => 25]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Profil detayı',
        'data' => [
            'user' => $user,
            'deposits' => $deposits['items'],
            'withdrawals' => $withdrawals['items'],
        ],
    ]);
}

if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && in_array($route, ['profile_update.php', 'profile/update', 'account/update', 'user/update'], true)) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $input = $memberInput($payload);

    // Eski frontend alan adlarını da destekle (first_name, tc, birth_date vb.)
    $aliases = [
        'first_name' => 'name',
        'firstName' => 'name',
        'last_name' => 'surname',
        'lastName' => 'surname',
        'family_name' => 'surname',
        'profile_email' => 'email',
        'profile_phone' => 'phone',
        'mobile' => 'phone',
        'birth_date' => 'dob',
        'birthday' => 'dob',
        'date_of_birth' => 'dob',
        'tc' => 'identity_number',
        'tc_no' => 'identity_number',
        'identityNumber' => 'identity_number',
        'identity' => 'identity_number',
    ];
    foreach ($aliases as $from => $to) {
        if (!array_key_exists($to, $input) && array_key_exists($from, $input)) {
            $input[$to] = $input[$from];
        }
    }

    $allowed = ['name', 'surname', 'email', 'phone', 'city', 'country', 'address', 'dob', 'gender', 'identity_number'];
    $data = [];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $input)) {
            $data[$field] = trim((string) $input[$field]);
        }
    }

    if (isset($data['gender']) && $data['gender'] !== '') {
        $g = function_exists('mb_strtolower')
            ? mb_strtolower($data['gender'], 'UTF-8')
            : strtolower($data['gender']);
        $genderMap = [
            'male' => 'Erkek',
            'erkek' => 'Erkek',
            'female' => 'Kadın',
            'kadin' => 'Kadın',
            'kadın' => 'Kadın',
            'other' => 'Diğer',
            'diger' => 'Diğer',
            'diğer' => 'Diğer',
        ];
        $data['gender'] = $genderMap[$g] ?? $data['gender'];
    }

    $currentPassword = trim((string) ($input['current_password'] ?? ''));
    if ($currentPassword !== '') {
        $pwdStmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
        $pwdStmt->execute(['id' => $userId]);
        $hash = (string) $pwdStmt->fetchColumn();
        if (!$memberPasswordMatches($currentPassword, $hash)) {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Mevcut şifre hatalı.']);
        }
    }

    if (isset($data['email']) && $data['email'] !== '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Geçerli bir e-posta adresi girin.']);
    }
    if (isset($data['email'])) {
        $dup = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id <> :id');
        $dup->execute(['email' => $data['email'], 'id' => $userId]);
        if ((int) $dup->fetchColumn() > 0) {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Bu e-posta başka bir kullanıcıya ait.']);
        }
    }
    if ($data === []) {
        $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Güncellenecek alan yok.', 'data' => ['updated' => false]]);
    }
    $set = [];
    foreach (array_keys($data) as $field) {
        $set[] = $field . ' = :' . $field;
    }
    $data['id'] = $userId;
    $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id');
    $stmt->execute($data);
    $user = $memberUserById($pdo, $userId);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Profil güncellendi.',
        'data' => ['updated' => true, 'user' => $user],
    ]);
}

if ($method === 'GET' && ($route === 'deposit_history.php' || $route === 'history/deposits')) {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $history = MegaPayzService::history($pdo, $userId, 'deposit', $_GET);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Yatırım geçmişi',
        'data' => [
            'items' => $history['items'],
            'deposits' => $history['items'],
            'pagination' => $history['pagination'],
        ],
    ]);
}

if ($method === 'GET' && ($route === 'withdraw_history.php' || $route === 'history/withdrawals')) {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $history = MegaPayzService::history($pdo, $userId, 'withdraw', $_GET);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Çekim geçmişi',
        'data' => [
            'items' => $history['items'],
            'withdrawals' => $history['items'],
            'pagination' => $history['pagination'],
        ],
    ]);
}

if ($method === 'GET' && ($route === 'bonus_claims_me.php' || $route === 'bonus/claims/me')) {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
    $stmt = $pdo->prepare('SELECT id, promotion_id, bonus_name, category, requested_amount, wagering_multiplier, status, created_at, processed_at FROM bonus_claim_requests WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit');
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = array_map(static function (array $row): array {
        $wagering = $row['wagering_multiplier'] ?? null;
        return [
            'id' => (int) ($row['id'] ?? 0),
            'promotionId' => (int) ($row['promotion_id'] ?? 0),
            'bonusName' => (string) ($row['bonus_name'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'requestedAmount' => (float) ($row['requested_amount'] ?? 0),
            'wageringMultiplier' => $wagering !== null ? (float) $wagering : null,
            'wageringMultiplierLabel' => $wagering !== null ? rtrim(rtrim(number_format((float) $wagering, 2, '.', ''), '0'), '.') . 'x' : null,
            'status' => (string) ($row['status'] ?? ''),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'processedAt' => (string) ($row['processed_at'] ?? ''),
            'rejectReason' => null,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Bonus taleplerim',
        'data' => [
            'items' => $rows,
            'claims' => $rows,
        ],
    ]);
}

if ($method === 'GET' && ($route === 'payment_methods.php' || $route === 'payment/methods')) {
    $items = MegaPayzService::methods(AdminDatabase::pdo());
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Ödeme yöntemleri',
        'data' => [
            'payment_methods' => $items,
            'methods' => $items,
            'currency' => 'TRY',
        ],
    ]);
}

if (in_array($method, ['GET', 'POST'], true) && ($route === 'deposit_payment.php' || $route === 'withdraw_payment.php' || $route === 'payment.php')) {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $userStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $userStmt->execute(['id' => $userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($user)) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Kullanıcı bulunamadı.']);
    }
    if ($method === 'GET') {
        $memberEnvelope(200, MegaPayzService::withdrawForm($pdo, $user));
    }
    $input = $memberInput($payload);
    $amount = round((float) str_replace(',', '.', (string) ($input['amount'] ?? '0')), 2);
    $methodKey = trim((string) ($input['payment_method_id'] ?? $input['payment_method'] ?? $input['method'] ?? 'wallet'));
    if ($amount <= 0) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Geçerli bir tutar girin.']);
    }
    if ($route === 'withdraw_payment.php') {
        $fields = is_array($input['input_fields'] ?? null) ? $input['input_fields'] : [];
        $account = trim((string) ($input['account_number'] ?? $input['account'] ?? ''));
        if ($account !== '') {
            $fields['account'] = $account;
        }
        $result = MegaPayzService::createWithdraw($pdo, $user, $methodKey, $amount, $fields);
        $memberEnvelope(!empty($result['success']) ? 200 : 422, $result);
    }
    $result = MegaPayzService::createDeposit($pdo, $user, $methodKey, $amount);
    $memberEnvelope(!empty($result['success']) ? 200 : 422, $result);
}

if ($method === 'GET' && $route === 'wallet/transactions') {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $deposits = MegaPayzService::history($pdo, $userId, 'deposit', ['limit' => 25]);
    $withdrawals = MegaPayzService::history($pdo, $userId, 'withdraw', ['limit' => 25]);
    $items = array_merge($deposits['items'] ?? [], $withdrawals['items'] ?? []);
    usort($items, static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Cüzdan işlemleri',
        'data' => [
            'items' => array_slice($items, 0, 50),
            'total' => count($items),
        ],
        'meta' => ['resource' => 'wallet/transactions'],
    ]);
}

if ($method === 'GET' && preg_match('~^wallet/transactions/([^/]+)$~', $route, $walletTxnMatch) === 1) {
    $memberRequireLogin();
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'İşlem detayı',
        'data' => [
            'id' => urldecode((string) $walletTxnMatch[1]),
            'transaction' => null,
        ],
        'meta' => ['resource' => 'wallet/transaction', 'status' => 'not_implemented'],
    ]);
}
