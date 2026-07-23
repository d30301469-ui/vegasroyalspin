<?php
/** Üye API modülü — index.php tarafından include edilir. */
/** @var string $method */
/** @var string $route */
/** @var mixed $payload */
/** @var callable $memberRequireLogin */
/** @var callable $memberInput */
/** @var callable $memberEnvelope */

require_once __DIR__ . '/../includes/member_bonus_helpers.php';

if ($method === 'GET' && $route === 'active_bonus.php') {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();

    // Canlı bonus bakiyesi (users.bonus_balance — oyun içi bet/win ile güncellenir)
    $liveBonusBalance = 0.0;
    try {
        $liveStmt = $pdo->prepare('SELECT bonus_balance FROM users WHERE id = :id LIMIT 1');
        $liveStmt->execute(['id' => $userId]);
        $liveBonusBalance = round((float) $liveStmt->fetchColumn(), 2);
    } catch (Throwable) {
        $liveBonusBalance = 0.0;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, name, category, initial_amount, current_bonus_balance, wagering_requirement, wagering_target, total_bet_amount, is_complete, status, granted_at, deadline
                               FROM user_active_bonuses
                               WHERE user_id = :user_id AND status = 'active'
                               ORDER BY id DESC");
        $stmt->execute(['user_id' => $userId]);
        $items = array_map(static function (array $row) use ($liveBonusBalance): array {
            $initial = (float) ($row['initial_amount'] ?? 0);
            // current_bonus_balance: her bet sonrası WageringService tarafından
            // canlı users.bonus_balance ile senkronize edilir.
            $currentBonus = (float) ($row['current_bonus_balance'] ?? $initial);
            $target = (float) ($row['wagering_target'] ?? 0);
            $bet = (float) ($row['total_bet_amount'] ?? 0);
            $progress = $target > 0 ? min(100, max(0, ($bet / $target) * 100)) : null;
            return [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'displayName' => (string) ($row['name'] ?? ''),
                'category' => (string) ($row['category'] ?? ''),
                'amount' => $initial,
                'initialAmount' => $initial,
                'currentBonusBalance' => $currentBonus,
                'liveBonusBalance' => $liveBonusBalance,
                'wageringRequirement' => (float) ($row['wagering_requirement'] ?? 0),
                'wageringRequirementLabel' => rtrim(rtrim(number_format((float) ($row['wagering_requirement'] ?? 0), 2, '.', ''), '0'), '.') . 'x',
                'wageringTarget' => $target,
                'totalBetAmount' => $bet,
                'remainingBet' => max(0, $target - $bet),
                'progress' => $progress,
                'isComplete' => (bool) ($row['is_complete'] ?? false),
                'status' => (string) ($row['status'] ?? ''),
                'grantedAt' => (string) ($row['granted_at'] ?? ''),
                'deadline' => (string) ($row['deadline'] ?? ''),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable) {
        $items = [];
    }
    $activeBonus = $items[0] ?? null;
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Aktif bonuslar',
        'data' => [
            'items' => $items,
            'hasActiveBonus' => $activeBonus !== null,
            'bonus' => $activeBonus,
            'liveBonusBalance' => $liveBonusBalance,
        ],
    ]);
}
if ($method === 'GET' && $route === 'promocodes.php') {
    $pdo = AdminDatabase::pdo();
    $now = date('Y-m-d H:i:s');
    $promocodeHasDurum = false;
    try {
        $colStmt = $pdo->query("SHOW COLUMNS FROM promocodes LIKE 'durum'");
        $promocodeHasDurum = $colStmt !== false && (bool) $colStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        $promocodeHasDurum = false;
    }
    $activeFilter = $promocodeHasDurum ? '(durum = 1 OR durum IS NULL) AND ' : '';
    try {
        $stmt = $pdo->prepare('SELECT id, kod, miktar, son_gecerlilik_tarihi, kullanim_limiti, mevcut_kullanim
                               FROM promocodes
                               WHERE ' . $activeFilter . '(
                                     son_gecerlilik_tarihi IS NULL
                                     OR son_gecerlilik_tarihi >= :now
                                     OR DATE(son_gecerlilik_tarihi) >= CURDATE()
                                 )
                               ORDER BY son_gecerlilik_tarihi ASC, id DESC');
        $stmt->execute(['now' => $now]);
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $rawRows = str_contains($e->getMessage(), '42S02') ? [] : throw $e;
    }
    $rows = array_map(static function (array $row): array {
        $limit = (int) ($row['kullanim_limiti'] ?? 0);
        $used = (int) ($row['mevcut_kullanim'] ?? 0);
        return [
            'id' => (int) ($row['id'] ?? 0),
            'kod' => (string) ($row['kod'] ?? ''),
            'code' => (string) ($row['kod'] ?? ''),
            'miktar' => (float) ($row['miktar'] ?? 0),
            'amount' => (float) ($row['miktar'] ?? 0),
            'son_gecerlilik_tarihi' => (string) ($row['son_gecerlilik_tarihi'] ?? ''),
            'expiresAt' => (string) ($row['son_gecerlilik_tarihi'] ?? ''),
            'kullanim_limiti' => $limit,
            'usageLimit' => $limit,
            'mevcut_kullanim' => $used,
            'currentUses' => $used,
            'remainingUses' => $limit > 0 ? max(0, $limit - $used) : null,
        ];
    }, $rawRows);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Promocode listesi',
        'data' => ['promocodes' => $rows, 'items' => $rows],
    ]);
}

if ($method === 'POST' && $route === 'promocode_request.php') {
    $userId = $memberRequireLogin();
    $input = $memberInput($payload);
    $promocodeId = (int) ($input['promocode_id'] ?? $input['promocodeId'] ?? $input['id'] ?? 0);
    $userMessage = trim((string) ($input['message'] ?? $input['user_message'] ?? ''));
    if ($promocodeId <= 0) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'promocode_id zorunludur.']);
    }
    $pdo = AdminDatabase::pdo();
    $promo = $pdo->prepare('SELECT id, kod, miktar FROM promocodes WHERE id = :id LIMIT 1');
    $promo->execute(['id' => $promocodeId]);
    $row = $promo->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Promocode bulunamadı.']);
    }
    $existingPromocodeReq = $pdo->prepare("SELECT id FROM promocode_requests WHERE user_id = :user_id AND promocode_id = :promocode_id AND status IN ('pending','approved') LIMIT 1");
    $existingPromocodeReq->execute(['user_id' => $userId, 'promocode_id' => (int) ($row['id'] ?? 0)]);
    if ($existingPromocodeReq->fetch(PDO::FETCH_ASSOC)) {
        $memberEnvelope(409, ['success' => false, 'code' => 409, 'message' => 'Bu promosyon kodu için zaten talebiniz var.']);
    }
    $insert = $pdo->prepare(
        "INSERT INTO promocode_requests
        (user_id, promocode_id, promocode_code, amount, user_message, status, created_at, updated_at)
        VALUES
        (:user_id, :promocode_id, :promocode_code, :amount, :user_message, 'pending', NOW(), NOW())"
    );
    $insert->execute([
        'user_id' => $userId,
        'promocode_id' => (int) ($row['id'] ?? 0),
        'promocode_code' => (string) ($row['kod'] ?? ''),
        'amount' => number_format((float) ($row['miktar'] ?? 0), 2, '.', ''),
        'user_message' => $userMessage !== '' ? $userMessage : null,
    ]);
    $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Promocode talebi oluşturuldu.', 'data' => ['requestId' => (string) $pdo->lastInsertId()]]);
}

if ($method === 'POST' && $route === 'bonus_use_code.php') {
    $userId = $memberRequireLogin();
    $input = $memberInput($payload);
    $code = trim((string) ($input['kod'] ?? $input['code'] ?? $input['promocode'] ?? ''));
    if ($code === '') {
        $memberEnvelope(422, ['success' => false, 'status' => 'error', 'code' => 422, 'message' => 'Promosyon kodu zorunludur.', 'mesaj' => 'Promosyon kodu zorunludur.']);
    }
    $pdo = AdminDatabase::pdo();
    $now = date('Y-m-d H:i:s');
    $promocodeHasDurum = false;
    try {
        $colStmt = $pdo->query("SHOW COLUMNS FROM promocodes LIKE 'durum'");
        $promocodeHasDurum = $colStmt !== false && (bool) $colStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        $promocodeHasDurum = false;
    }
    $activeFilter = $promocodeHasDurum ? ' AND (durum = 1 OR durum IS NULL)' : '';
    $promo = $pdo->prepare('SELECT id, kod, miktar, kullanim_limiti, mevcut_kullanim, son_gecerlilik_tarihi
                            FROM promocodes
                            WHERE LOWER(TRIM(kod)) = LOWER(TRIM(:kod))'
                            . $activeFilter .
                            ' AND (
                                  son_gecerlilik_tarihi IS NULL
                                  OR son_gecerlilik_tarihi >= :now
                                  OR DATE(son_gecerlilik_tarihi) >= CURDATE()
                              )
                            LIMIT 1');
    $promo->execute(['kod' => $code, 'now' => $now]);
    $row = $promo->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        $memberEnvelope(404, ['success' => false, 'status' => 'error', 'code' => 404, 'message' => 'Promosyon kodu bulunamadı veya süresi dolmuş.', 'mesaj' => 'Promosyon kodu bulunamadı veya süresi dolmuş.']);
    }
    $limit = (int) ($row['kullanim_limiti'] ?? 0);
    $used = (int) ($row['mevcut_kullanim'] ?? 0);
    if ($limit > 0 && $used >= $limit) {
        $memberEnvelope(422, ['success' => false, 'status' => 'error', 'code' => 422, 'message' => 'Promosyon kodu kullanım limiti dolmuş.', 'mesaj' => 'Promosyon kodu kullanım limiti dolmuş.']);
    }
    $exists = $pdo->prepare("SELECT id FROM promocode_requests WHERE user_id = :user_id AND promocode_id = :promocode_id AND status IN ('pending','approved') LIMIT 1");
    $exists->execute(['user_id' => $userId, 'promocode_id' => (int) $row['id']]);
    if ($exists->fetch(PDO::FETCH_ASSOC)) {
        $memberEnvelope(409, ['success' => false, 'status' => 'error', 'code' => 409, 'message' => 'Bu promosyon kodu için zaten talebiniz var.', 'mesaj' => 'Bu promosyon kodu için zaten talebiniz var.']);
    }
    $insert = $pdo->prepare(
        "INSERT INTO promocode_requests
        (user_id, promocode_id, promocode_code, amount, user_message, status, created_at, updated_at)
        VALUES
        (:user_id, :promocode_id, :promocode_code, :amount, :user_message, 'pending', NOW(), NOW())"
    );
    $insert->execute([
        'user_id' => $userId,
        'promocode_id' => (int) $row['id'],
        'promocode_code' => (string) $row['kod'],
        'amount' => number_format((float) ($row['miktar'] ?? 0), 2, '.', ''),
        'user_message' => 'Site promosyon kodu kullanımı',
    ]);
    $memberEnvelope(200, [
        'success' => true,
        'status' => 'success',
        'code' => 200,
        'message' => 'Promosyon kodu talebiniz alındı.',
        'mesaj' => 'Promosyon kodu talebiniz alındı.',
        'data' => ['requestId' => (string) $pdo->lastInsertId()],
    ]);
}

if ($method === 'GET' && $route === 'referrals.php') {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $stmt = $pdo->prepare('SELECT id, username, email, name, surname, referral_code FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($user)) {
        $memberEnvelope(404, ['success' => false, 'status' => 'error', 'code' => 404, 'message' => 'Kullanıcı bulunamadı.']);
    }
    $referralCode = trim((string) ($user['referral_code'] ?? ''));
    if ($referralCode === '') {
        $base = preg_replace('/[^a-z0-9]/i', '', strtolower((string) ($user['username'] ?? 'user')));
        $base = is_string($base) && $base !== '' ? substr($base, 0, 18) : 'user';
        for ($i = 0; $i < 6; $i++) {
            $candidate = strtoupper($base . substr(bin2hex(random_bytes(4)), 0, 8));
            $check = $pdo->prepare('SELECT 1 FROM users WHERE referral_code = :code LIMIT 1');
            $check->execute(['code' => $candidate]);
            if (!$check->fetchColumn()) {
                $referralCode = $candidate;
                break;
            }
        }
        if ($referralCode !== '') {
            $pdo->prepare('UPDATE users SET referral_code = :code WHERE id = :id')->execute(['code' => $referralCode, 'id' => $userId]);
        }
    }
    $referredUsers = [];
    try {
        $refStmt = $pdo->prepare('SELECT id, name AS first_name, surname, username, email, created_at
                                  FROM users
                                  WHERE referred_by_affiliate_id = :user_id
                                  ORDER BY created_at DESC
                                  LIMIT 100');
        $refStmt->execute(['user_id' => $userId]);
        $referredUsers = $refStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        $referredUsers = [];
    }
    $totalReferred = count($referredUsers);
    try {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE referred_by_affiliate_id = :user_id');
        $countStmt->execute(['user_id' => $userId]);
        $totalReferred = (int) $countStmt->fetchColumn();
    } catch (Throwable) {
    }
    $shareBase = trim((string) (getenv('FRONTEND_BASE_URL') ?: getenv('SITE_URL') ?: ''));
    if ($shareBase === '') {
        if (!function_exists('deploy_domain')) {
            $deployDomains = dirname(__DIR__, 3) . '/config/deploy_domains.php';
            if (is_file($deployDomains)) {
                require_once $deployDomains;
            }
        }
        $shareBase = function_exists('deploy_domain') ? deploy_domain('frontend_url') : 'https://vegasroyalspin.com';
    }
    $shareLink = rtrim($shareBase, '/') . '/register?ref=' . rawurlencode($referralCode);
    $memberEnvelope(200, [
        'success' => true,
        'status' => 'success',
        'code' => 200,
        'message' => 'Referans bilgileri',
        'referral_code' => $referralCode,
        'referred_users' => $referredUsers,
        'data' => [
            'referral_code' => $referralCode,
            'share_link' => $shareLink,
            'total_referred' => $totalReferred,
            'referred_users' => $referredUsers,
        ],
    ]);
}

if ($method === 'GET' && ($route === 'affiliate/summary' || $route === 'affiliate.php')) {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    $stmt = $pdo->prepare('SELECT referral_code FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $referralCode = trim((string) $stmt->fetchColumn());
    $totalReferred = 0;
    try {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE referred_by_affiliate_id = :user_id');
        $countStmt->execute(['user_id' => $userId]);
        $totalReferred = (int) $countStmt->fetchColumn();
    } catch (Throwable) {
    }
    $shareBase = trim((string) (getenv('FRONTEND_BASE_URL') ?: getenv('FRONTEND_URL') ?: getenv('SITE_URL') ?: (function_exists('deploy_domain') ? deploy_domain('frontend_url') : 'https://vegasroyalspin.com')));
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Affiliate özeti',
        'data' => [
            'referral_code' => $referralCode,
            'share_link' => rtrim($shareBase, '/') . '/register?ref=' . rawurlencode($referralCode),
            'total_referred' => $totalReferred,
            'program' => ['enabled' => true, 'payout_model' => 'registration'],
        ],
    ]);
}

if ($method === 'POST' && $route === 'bonus_claim.php') {
    $userId = $memberRequireLogin();
    $input = $memberInput($payload);
    $promotionId = (int) ($input['promotionId'] ?? $input['promotion_id'] ?? 0);
    $pdo = AdminDatabase::pdo();
    $depositRequiredMessage = 'Bu bonustan faydalanabilmeniz için yatırım yapmanız gerekmektedir.';

    if (!memberHasConfirmedDepositV2($pdo, $userId)) {
        $memberEnvelope(403, [
            'success' => false,
            'code' => 403,
            'message' => $depositRequiredMessage,
            'data' => [
                'claimPolicy' => [
                    'requiresConfirmedDeposit' => true,
                    'depositRequiredMessage' => $depositRequiredMessage,
                ],
                'viewer' => ['hasConfirmedDeposit' => false],
            ],
        ]);
    }
    $promo = null;
    if ($promotionId > 0) {
        $promotion = $pdo->prepare("SELECT " . memberPromotionsSelectColumnsV2() . " FROM promotions WHERE id = :id AND status = 'active' LIMIT 1");
        $promotion->execute(['id' => $promotionId]);
        $promo = $promotion->fetch(PDO::FETCH_ASSOC);
    } else {
        $title = trim((string) ($input['bonusTitle'] ?? $input['bonusTuru'] ?? $input['title'] ?? ''));
        if ($title === '') {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'promotionId veya bonusTitle zorunludur.']);
        }
        $normalizeTitle = static function (string $value): string {
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $value = str_replace(['İ', 'I', 'ı'], ['i', 'i', 'i'], $value);
            $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
            return preg_replace('/[^a-z0-9%]+/u', '', $value) ?: '';
        };
        $wantedTitle = $normalizeTitle($title);
        $promotion = $pdo->query("SELECT " . memberPromotionsSelectColumnsV2() . " FROM promotions WHERE status = 'active' ORDER BY sort_order ASC, id ASC");
        foreach ($promotion->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($wantedTitle !== '' && $normalizeTitle((string) ($row['title'] ?? '')) === $wantedTitle) {
                $promo = $row;
                break;
            }
        }
    }
    if (!is_array($promo)) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Promosyon bulunamadı.']);
    }
    $claimPromotionId = (int) ($promo['id'] ?? 0);
    if ($claimPromotionId > 0) {
        $existingClaim = $pdo->prepare("SELECT id FROM bonus_claim_requests WHERE user_id = :user_id AND promotion_id = :promotion_id AND status = 'pending' LIMIT 1");
        $existingClaim->execute(['user_id' => $userId, 'promotion_id' => $claimPromotionId]);
        $existingClaimRow = $existingClaim->fetch(PDO::FETCH_ASSOC);
        if (is_array($existingClaimRow)) {
            $pdo->prepare('DELETE FROM bonus_claim_requests WHERE id = :id')->execute(['id' => (int) $existingClaimRow['id']]);
        }
    }
    $insert = $pdo->prepare(
        "INSERT INTO bonus_claim_requests
        (user_id, promotion_id, bonus_name, category, promotion_type, requested_amount, wagering_multiplier, user_message, status, created_at)
        VALUES
        (:user_id, :promotion_id, :bonus_name, :category, :promotion_type, :requested_amount, :wagering_multiplier, :user_message, 'pending', NOW())"
    );
    $requestedAmount = memberPromotionResolveClaimAmountV2($pdo, $userId, $promo);
    if ($requestedAmount <= 0) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Promosyon bonus tutarı hesaplanamadı. Lütfen yönetici ile iletişime geçin.']);
    }
    $insert->execute([
        'user_id' => $userId,
        'promotion_id' => $claimPromotionId,
        'bonus_name' => (string) ($promo['title'] ?? ''),
        'category' => (string) ($promo['type'] ?? ''),
        'promotion_type' => (string) ($promo['bonus_type'] ?? ''),
        'requested_amount' => number_format($requestedAmount, 2, '.', ''),
        'wagering_multiplier' => number_format((float) ($promo['wagering_multiplier'] ?? 1), 2, '.', ''),
        'user_message' => trim((string) ($input['message'] ?? '')) ?: null,
    ]);
    $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Bonus talebi oluşturuldu', 'data' => ['requestId' => (string) $pdo->lastInsertId()]]);
}
