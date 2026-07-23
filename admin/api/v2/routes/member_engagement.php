<?php
/** Üye API modülü — index.php tarafından include edilir. */
/** @var string $method */
/** @var string $route */
/** @var mixed $payload */
/** @var callable $memberInput */
/** @var callable $memberEnvelope */
/** @var callable $memberJwtOptionalUserId */
/** @var callable $memberUserById */
/** @var callable $memberRequireLogin */

require_once __DIR__ . '/../includes/member_bonus_helpers.php';

if (!class_exists('PromotionMediaGuard', false) && defined('ADMIN_APP_PATH')) {
    $guardFile = rtrim((string) ADMIN_APP_PATH, '/\\') . '/Core/PromotionMediaGuard.php';
    if (is_file($guardFile)) {
        require_once $guardFile;
    }
}

if (($route === 'call_me_request.php' || $route === 'call-me-request') && in_array($method, ['GET', 'POST'], true)) {
    $pdo = AdminDatabase::pdo();
    $callerUserId = null;
    $callerUsername = '';
    $resolvedId = $memberJwtOptionalUserId($pdo);
    if (($resolvedId ?? 0) > 0) {
        $callerUserId = $resolvedId;
        $caller = $memberUserById($pdo, $resolvedId);
        $callerUsername = is_array($caller) ? (string) ($caller['username'] ?? '') : '';
    }
    if ($method === 'GET') {
        // Kişisel veri (ad/telefon) içeren talep listesi yalnızca yetkili yönetim
        // ekranlarından erişilebilir; üye JWT uçtan listeleme kapalıdır.
        $memberEnvelope(405, [
            'success' => false,
            'code' => 405,
            'message' => 'Bu uç yalnızca aranma talebi oluşturmak için kullanılabilir.',
        ]);
    }
    $input = $memberInput($payload);
    $fullName = trim((string) ($input['full_name'] ?? $input['name'] ?? ''));
    $phone = trim((string) ($input['phone'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $preferredTime = trim((string) ($input['preferred_time'] ?? $input['preferredTime'] ?? ''));
    $message = trim((string) ($input['message'] ?? ''));
    if ($fullName === '' || $phone === '') {
        $memberEnvelope(422, [
            'success' => false,
            'code' => 422,
            'message' => 'Ad soyad ve telefon zorunludur.',
        ]);
    }
    $insert = $pdo->prepare(
        'INSERT INTO call_me_requests
        (user_id, full_name, username, phone, email, preferred_time, message, status, ip_address, user_agent, created_at, updated_at)
        VALUES
        (:user_id, :full_name, :username, :phone, :email, :preferred_time, :message, :status, :ip_address, :user_agent, NOW(), NOW())'
    );
    $insert->execute([
        'user_id' => $callerUserId,
        'full_name' => $fullName,
        'username' => $callerUsername,
        'phone' => $phone,
        'email' => $email !== '' ? $email : null,
        'preferred_time' => $preferredTime !== '' ? $preferredTime : null,
        'message' => $message !== '' ? $message : null,
        'status' => 'pending',
        'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
    ]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Aranma talebiniz alınmıştır.',
        'data' => ['id' => (int) $pdo->lastInsertId()],
    ]);
}

if (($route === 'promotions.php' || $route === 'content/promotions') && in_array($method, ['GET', 'POST'], true)) {
    $pdo = AdminDatabase::pdo();
    // Şema kolonlarını (link_url, category, genişletilmiş image_url) ve promosyon
    // görsellerini (admin/upload/bonuses -> /uploads/promotions/) otomatik onarır.
    // Canlı ortamda da manuel migration gerekmeden ilk istekte kendi kendine kurulur.
    try {
        PromotionMediaGuard::bootstrap();
    } catch (Throwable) {
        // Guard hatası isteği asla kesmemeli.
    }
    $viewerUserId = $memberJwtOptionalUserId($pdo);
    if ($method === 'GET') {
        $category = trim((string) ($_GET['category'] ?? ''));
        $now = date('Y-m-d H:i:s');
        $where = ["status = 'active'", '(start_date IS NULL OR start_date <= :now_start)', '(end_date IS NULL OR end_date >= :now_end)'];
        $params = ['now_start' => $now, 'now_end' => $now];
        if ($category !== '') {
            $where[] = 'type = :category';
            $params['category'] = $category;
        }
        $sql = 'SELECT ' . memberPromotionsSelectColumnsV2() . '
                FROM promotions WHERE ' . implode(' AND ', $where) . ' ORDER BY sort_order ASC, id DESC';
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $promoRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $promoRows = str_contains($e->getMessage(), '42S02') ? [] : throw $e;
        }
        $promotions = [];
        foreach ($promoRows as $row) {
            $rawImage = trim((string) ($row['image_url'] ?? ''));
            $resolvedForDisplay = class_exists('PromotionMediaGuard', false)
                ? PromotionMediaGuard::resolveDisplayImageUrl($rawImage, (string) ($row['title'] ?? ''))
                : $rawImage;

            $resolvedImageUrl = class_exists('ApiMediaUrl', false)
                    ? ApiMediaUrl::resolve($resolvedForDisplay)
                    : $resolvedForDisplay;
            $bonusRules = $row['bonus_rules'] ?? null;
            if (is_string($bonusRules) && trim($bonusRules) !== '') {
                $decodedRules = json_decode($bonusRules, true);
                $bonusRules = is_array($decodedRules) ? $decodedRules : $bonusRules;
            }

            $promotions[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'long_description' => (string) ($row['long_description'] ?? ''),
                'category' => (string) ($row['type'] ?? ''),
                'terms' => (string) ($row['terms'] ?? ''),
                'image_url' => $resolvedImageUrl,
                'link_url' => (string) ($row['link_url'] ?? ''),
                'general_rules' => (string) ($row['general_rules'] ?? ''),
                'bonus_type' => (string) ($row['bonus_type'] ?? ''),
                'bonus_amount' => (float) ($row['bonus_amount'] ?? 0),
                'bonus_rules' => $bonusRules,
                'wagering_multiplier' => (float) ($row['wagering_multiplier'] ?? 0),
            ];
        }
        $hasConfirmedDeposit = $viewerUserId > 0 ? memberHasConfirmedDepositV2($pdo, (int) $viewerUserId) : false;
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Promosyonlar başarıyla alındı',
            'data' => [
                'category' => $category !== '' ? $category : null,
                'total' => count($promotions),
                'promotions' => $promotions,
                'claimPolicy' => [
                    'requiresConfirmedDeposit' => true,
                    'depositRequiredMessage' => 'Bu bonustan faydalanabilmeniz için yatırım yapmanız gerekmektedir.',
                ],
                'viewer' => ['hasConfirmedDeposit' => $hasConfirmedDeposit],
            ],
        ]);
    }

    $userId = $memberRequireLogin();
    $input = $memberInput($payload);
    $promotionId = (int) ($input['promotionId'] ?? $input['promotion_id'] ?? 0);
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
    if ($promotionId <= 0) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'promotionId zorunludur.']);
    }
    $promotionStmt = $pdo->prepare("SELECT " . memberPromotionsSelectColumnsV2() . " FROM promotions WHERE id = :id AND status = 'active' LIMIT 1");
    $promotionStmt->execute(['id' => $promotionId]);
    $promotion = $promotionStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($promotion)) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Promosyon bulunamadı.']);
    }
    $existing = $pdo->prepare("SELECT id FROM bonus_claim_requests WHERE user_id = :user_id AND promotion_id = :promotion_id AND status = 'pending' LIMIT 1");
    $existing->execute(['user_id' => $userId, 'promotion_id' => $promotionId]);
    $existingRow = $existing->fetch(PDO::FETCH_ASSOC);
    $replacedPending = false;
    if (is_array($existingRow)) {
        $pdo->prepare('DELETE FROM bonus_claim_requests WHERE id = :id')->execute(['id' => (int) $existingRow['id']]);
        $replacedPending = true;
    }
    $requestedAmount = memberPromotionResolveClaimAmountV2($pdo, $userId, $promotion);
    if ($requestedAmount <= 0) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Promosyon bonus tutarı hesaplanamadı. Lütfen yönetici ile iletişime geçin.']);
    }
    $wagering = round((float) ($promotion['wagering_multiplier'] ?? 1), 2);
    $userMessage = trim((string) ($input['message'] ?? ''));
    $insertClaim = $pdo->prepare(
        "INSERT INTO bonus_claim_requests
        (user_id, promotion_id, bonus_name, category, promotion_type, requested_amount, wagering_multiplier, user_message, status, created_at)
        VALUES
        (:user_id, :promotion_id, :bonus_name, :category, :promotion_type, :requested_amount, :wagering_multiplier, :user_message, 'pending', NOW())"
    );
    $insertClaim->execute([
        'user_id' => $userId,
        'promotion_id' => (int) ($promotion['id'] ?? 0),
        'bonus_name' => (string) ($promotion['title'] ?? ''),
        'category' => (string) ($promotion['type'] ?? ''),
        'promotion_type' => (string) ($promotion['bonus_type'] ?? ''),
        'requested_amount' => number_format($requestedAmount, 2, '.', ''),
        'wagering_multiplier' => number_format($wagering, 2, '.', ''),
        'user_message' => $userMessage !== '' ? $userMessage : null,
    ]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Bonus talebi oluşturuldu',
        'data' => [
            'requestId' => (string) $pdo->lastInsertId(),
            'requestedAmount' => $requestedAmount,
            'message' => 'Bonus talebiniz alındı, incelenmeyi bekliyor.',
            'replacedPending' => $replacedPending,
        ],
    ]);
}
