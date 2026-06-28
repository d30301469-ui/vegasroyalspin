<?php
/**
 * Üye API genişletmeleri: KYC, bildirimler, destek ticket, spor meta.
 * index.php tarafından include edilir; eşleşmeyen route'lar için sessizce döner.
 */

admin_require_project_file('services/MemberKycService.php');
admin_require_project_file('services/MemberNotificationService.php');
admin_require_project_file('services/SupportTicketService.php');

// ─── KYC ───────────────────────────────────────────────────────────────────

if ($method === 'GET' && in_array($route, ['kyc/status', 'kyc/status.php'], true)) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $items = MemberKycService::listForUser($pdo, $userId);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'KYC durumu',
        'data' => ['items' => $items, 'total' => count($items)],
    ]);
}

if ($method === 'POST' && in_array($route, ['kyc/documents', 'kyc/documents.php', 'kyc/address-verification', 'kyc/source-of-funds'], true)) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $user = $memberUserById($pdo, $userId);
    $input = $memberInput($payload);
    if ($route === 'kyc/address-verification') {
        $input['document_type'] = 'address';
    } elseif ($route === 'kyc/source-of-funds') {
        $input['document_type'] = 'source_of_funds';
    }
    try {
        $result = MemberKycService::submitDocument(
            $pdo,
            $userId,
            (string) ($user['username'] ?? ''),
            $input
        );
        $memberEnvelope(201, [
            'success' => true,
            'code' => 201,
            'message' => 'KYC belgesi alındı.',
            'data' => $result,
        ]);
    } catch (InvalidArgumentException $e) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'error' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]);
    } catch (Throwable $e) {
        $memberEnvelope(500, ['success' => false, 'code' => 500, 'message' => 'KYC kaydı oluşturulamadı.']);
    }
}

// ─── Bildirimler ───────────────────────────────────────────────────────────

if ($method === 'GET' && in_array($route, ['notifications', 'notifications.php'], true)) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
    $result = MemberNotificationService::listForUser($pdo, $userId, $limit);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Bildirimler',
        'data' => $result,
    ]);
}

if ($method === 'POST' && $route === 'notifications/read-all') {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $count = MemberNotificationService::markAllRead($pdo, $userId);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Tüm bildirimler okundu.',
        'data' => ['marked' => $count],
    ]);
}

if ($method === 'POST' && preg_match('#^notifications/(\d+)/read$#', $route, $notifMatch) === 1) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $id = (int) ($notifMatch[1] ?? 0);
    $ok = MemberNotificationService::markRead($pdo, $userId, $id);
    if (!$ok) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Bildirim bulunamadı.']);
    }
    $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Bildirim okundu.', 'data' => ['id' => $id]]);
}

// ─── Destek ticket ─────────────────────────────────────────────────────────

if ($method === 'GET' && in_array($route, ['support/tickets', 'support/tickets.php'], true)) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $items = SupportTicketService::listForUser($pdo, $userId);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Destek talepleri',
        'data' => ['items' => $items, 'total' => count($items)],
    ]);
}

if ($method === 'POST' && in_array($route, ['support/tickets', 'support/tickets.php'], true)) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $user = $memberUserById($pdo, $userId);
    try {
        $ticket = SupportTicketService::create($pdo, $userId, (string) ($user['username'] ?? ''), $memberInput($payload));
        $memberEnvelope(201, [
            'success' => true,
            'code' => 201,
            'message' => 'Destek talebi oluşturuldu.',
            'data' => $ticket,
        ]);
    } catch (InvalidArgumentException $e) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'error' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]);
    }
}

if ($method === 'GET' && preg_match('#^support/tickets/(\d+)/messages$#', $route, $ticketMsgGet) === 1) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $ticketId = (int) ($ticketMsgGet[1] ?? 0);
    try {
        $messages = SupportTicketService::messages($pdo, $userId, $ticketId);
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Ticket mesajları',
            'data' => ['ticket_id' => $ticketId, 'messages' => $messages],
        ]);
    } catch (RuntimeException) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Ticket bulunamadı.']);
    }
}

if ($method === 'POST' && preg_match('#^support/tickets/(\d+)/messages$#', $route, $ticketMsgPost) === 1) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $user = $memberUserById($pdo, $userId);
    $ticketId = (int) ($ticketMsgPost[1] ?? 0);
    $input = $memberInput($payload);
    $message = trim((string) ($input['message'] ?? $input['body'] ?? ''));
    try {
        SupportTicketService::addMessage($pdo, $userId, (string) ($user['username'] ?? ''), $ticketId, $message);
        $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Mesaj gönderildi.', 'data' => ['ticket_id' => $ticketId]]);
    } catch (InvalidArgumentException $e) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => $e->getMessage()]);
    } catch (RuntimeException) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Ticket bulunamadı.']);
    }
}

// ─── Bildirim sayısı ───────────────────────────────────────────────────────

if ($method === 'GET' && in_array($route, ['notifications/count', 'notifications/unread-count'], true)) {
    $pdo    = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $count  = 0;
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM member_notifications WHERE user_id = :uid AND is_read = 0"
        );
        $stmt->execute(['uid' => $userId]);
        $count = (int) $stmt->fetchColumn();
    } catch (Throwable) {}
    $memberEnvelope(200, [
        'success' => true,
        'code'    => 200,
        'message' => 'Okunmamış bildirim sayısı',
        'data'    => ['count' => $count, 'unread_count' => $count],
    ]);
}

// ─── Destek ticket — tek görüntüleme, kapatma, açma ────────────────────────

if ($method === 'GET' && preg_match('#^support/tickets/(\d+)$#', $route, $ticketGetMatch) === 1) {
    $pdo      = AdminDatabase::pdo();
    $userId   = $memberRequireLogin();
    $ticketId = (int) ($ticketGetMatch[1] ?? 0);
    try {
        $stmt = $pdo->prepare('SELECT * FROM support_tickets WHERE id = :id AND user_id = :uid LIMIT 1');
        $stmt->execute(['id' => $ticketId, 'uid' => $userId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($ticket)) {
            $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Ticket bulunamadı.']);
        }
        $messages = SupportTicketService::messages($pdo, $userId, $ticketId);
        $memberEnvelope(200, [
            'success' => true,
            'code'    => 200,
            'message' => 'Ticket detayı',
            'data'    => ['ticket' => $ticket, 'messages' => $messages],
        ]);
    } catch (Throwable) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Ticket bulunamadı.']);
    }
}

if ($method === 'POST' && preg_match('#^support/tickets/(\d+)/close$#', $route, $ticketCloseMatch) === 1) {
    $pdo      = AdminDatabase::pdo();
    $userId   = $memberRequireLogin();
    $ticketId = (int) ($ticketCloseMatch[1] ?? 0);
    $stmt     = $pdo->prepare("SELECT id FROM support_tickets WHERE id = :id AND user_id = :uid AND status NOT IN ('closed') LIMIT 1");
    $stmt->execute(['id' => $ticketId, 'uid' => $userId]);
    if (!$stmt->fetch()) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Ticket bulunamadı veya zaten kapalı.']);
    }
    $pdo->prepare("UPDATE support_tickets SET status = 'closed', updated_at = NOW() WHERE id = :id")->execute(['id' => $ticketId]);
    $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Ticket kapatıldı.', 'data' => ['id' => $ticketId]]);
}

if ($method === 'POST' && preg_match('#^support/tickets/(\d+)/reopen$#', $route, $ticketReopenMatch) === 1) {
    $pdo      = AdminDatabase::pdo();
    $userId   = $memberRequireLogin();
    $ticketId = (int) ($ticketReopenMatch[1] ?? 0);
    $stmt     = $pdo->prepare("SELECT id FROM support_tickets WHERE id = :id AND user_id = :uid AND status = 'closed' LIMIT 1");
    $stmt->execute(['id' => $ticketId, 'uid' => $userId]);
    if (!$stmt->fetch()) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Kapalı ticket bulunamadı.']);
    }
    $pdo->prepare("UPDATE support_tickets SET status = 'open', updated_at = NOW() WHERE id = :id")->execute(['id' => $ticketId]);
    $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Ticket yeniden açıldı.', 'data' => ['id' => $ticketId]]);
}

// ─── Sadakat geçmişi + puan kullanımı ──────────────────────────────────────

if ($method === 'GET' && in_array($route, ['loyalty/history', 'loyalty/points-history'], true)) {
    $pdo    = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $limit  = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
    $rows   = [];
    try {
        $stmt = $pdo->prepare(
            "SELECT id, points, type AS action, note AS description, source AS reference_type, reference_id, created_at
             FROM loyalty_point_transactions
             WHERE user_id = :uid
             ORDER BY id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
    $memberEnvelope(200, [
        'success' => true,
        'code'    => 200,
        'message' => 'Sadakat puanı geçmişi',
        'data'    => ['items' => $rows, 'total' => count($rows)],
    ]);
}

if ($method === 'POST' && in_array($route, ['loyalty/redeem', 'loyalty/redeem-points'], true)) {
    $pdo    = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $input  = $memberInput($payload);
    $points = (int) ($input['points'] ?? 0);
    if ($points <= 0) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Geçerli bir puan miktarı giriniz.']);
    }
    try {
        $stmt = $pdo->prepare('SELECT COALESCE(redeemable_points, 0) FROM user_loyalty_accounts WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $available = (int) $stmt->fetchColumn();
        if ($available < $points) {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => "Yetersiz puan. Mevcut: $available"]);
        }
        // 100 puan = 1 TRY (konfigüre edilebilir)
        $bonusAmount = round($points / 100, 2);
        $pdo->beginTransaction();
        $pdo->prepare(
            "INSERT INTO loyalty_point_transactions (user_id, type, points, note, created_at)
             VALUES (:uid, 'redeem', :pts, :note, NOW())"
        )->execute(['uid' => $userId, 'pts' => -$points, 'note' => "$points puan kullanıldı ($bonusAmount TRY bonus)"]);
        // Kullanıcının redeemable_points bakiyesini düş
        $pdo->prepare(
            "UPDATE user_loyalty_accounts SET redeemable_points = GREATEST(0, redeemable_points - :pts) WHERE user_id = :uid"
        )->execute(['pts' => $points, 'uid' => $userId]);
        $pdo->prepare(
            "INSERT INTO user_active_bonuses
             (user_id, name, category, initial_amount, current_bonus_balance, wagering_requirement, wagering_target, total_bet_amount, status, granted_at, deadline)
             VALUES (:uid, 'Sadakat Bonusu', 'loyalty', :amt, :amt, 1, :amt, 0, 'active', NOW(), :dl)"
        )->execute(['uid' => $userId, 'amt' => number_format($bonusAmount, 2, '.', ''), 'dl' => date('Y-m-d H:i:s', strtotime('+7 days'))]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $memberEnvelope(500, ['success' => false, 'code' => 500, 'message' => 'Puan kullanımı başarısız.']);
    }
    $memberEnvelope(200, [
        'success' => true,
        'code'    => 200,
        'message' => "$points puan kullanıldı, $bonusAmount TRY bonus hesabınıza eklendi.",
        'data'    => ['points_used' => $points, 'bonus_amount' => $bonusAmount],
    ]);
}

// ─── Spor meta (liste endpoint'leri) ───────────────────────────────────────

if ($method === 'GET' && in_array($route, ['sports.php', 'sports', 'sports/meta', 'sports_events.php', 'sports_events', 'sports_leagues.php', 'sports_leagues', 'sports_markets.php', 'sports_markets'], true)) {
    $configured = trim((string) getenv('OKKO_SPORTS_API_KEY')) !== ''
        && trim((string) getenv('OKKO_SPORTS_API_SECRET')) !== '';
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => $configured ? 'Spor servisi yapılandırıldı.' : 'Spor servisi henüz yapılandırılmadı.',
        'data' => [
            'items' => [],
            'provider' => 'okko',
            'configured' => $configured,
            'launch_route' => '/api/v2/sports/launch',
        ],
        'meta' => ['resource' => $route, 'status' => $configured ? 'ready' : 'not_configured'],
    ]);
}

// ─── Sorumlu oyun (REST alias → me/limits) ─────────────────────────────────

if (in_array($method, ['POST', 'PATCH', 'PUT'], true) && $route === 'responsible-gaming/limits') {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $updated = MemberAccountService::updateLimits($pdo, $userId, $memberInput($payload));
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Sorumlu oyun limitleri güncellendi.',
        'data' => ['limits' => $updated],
    ]);
}

if ($method === 'POST' && $route === 'responsible-gaming/cool-off') {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $input = $memberInput($payload);
    $days = max(1, min(365, (int) ($input['days'] ?? 7)));
    $until = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
    $updated = MemberAccountService::updateLimits($pdo, $userId, ['cool_off_until' => $until]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Cool-off başlatıldı.',
        'data' => ['limits' => $updated, 'cool_off_until' => $until],
    ]);
}

if ($method === 'POST' && $route === 'responsible-gaming/self-exclusion') {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $input = $memberInput($payload);
    $months = max(1, min(60, (int) ($input['months'] ?? 6)));
    $until = date('Y-m-d H:i:s', strtotime('+' . $months . ' months'));
    $updated = MemberAccountService::updateLimits($pdo, $userId, ['self_exclusion_until' => $until]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Kendini dışlama kaydedildi.',
        'data' => ['limits' => $updated, 'self_exclusion_until' => $until],
    ]);
}

if ($method === 'GET' && $route === 'responsible-gaming/activity') {
    $memberRequireLogin();
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Sorumlu oyun aktivitesi',
        'data' => [
            'items' => [],
            'activity' => [],
            'total' => 0,
        ],
        'meta' => ['resource' => 'responsible-gaming/activity', 'status' => 'empty'],
    ]);
}

if ($route === 'support/live-chat/token' && in_array($method, ['GET', 'POST'], true)) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberJwtOptionalUserId($pdo);
    $supportUrl = defined('LIVE_SUPPORT_URL') ? (string) LIVE_SUPPORT_URL : '';
    if ($supportUrl === '') {
        $supportUrl = trim((string) getenv('LIVE_SUPPORT_URL'));
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Canlı destek bağlantısı hazır.',
        'data' => [
            'token' => null,
            'provider' => 'live-chat',
            'url' => $supportUrl,
            'authenticated' => ($userId ?? 0) > 0,
            'user_id' => ($userId ?? 0) > 0 ? (int) $userId : null,
        ],
    ]);
}
