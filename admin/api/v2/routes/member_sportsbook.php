<?php

declare(strict_types=1);

/**
 * Üye API — Sportsbook (BetBy) launch + geçmiş.
 * member_route_loader.php tarafından include edilir; $route, $method, $payload,
 * $memberRequireLogin, $memberUserById, $memberInput, $memberEnvelope kernel'den gelir.
 */

if ($method === 'POST' && in_array($route, ['sportsbook_launch.php', 'sportsbook-launch', 'sportsbook/launch'], true)) {
    $userId = $memberRequireLogin();
    $pdo    = AdminDatabase::pdo();
    $user   = $memberUserById($pdo, $userId);
    if (!is_array($user)) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Kullanıcı bulunamadı.']);
    }

    $result = SportsbookService::launch($pdo, $user, $memberInput($payload));
    $code   = (int) ($result['code'] ?? 200);

    if (!empty($result['success'])) {
        $memberEnvelope(200, [
            'success' => true,
            'code'    => 200,
            'message' => (string) ($result['message'] ?? 'Spor bahisleri başlatıldı.'),
            'data'    => is_array($result['data'] ?? null) ? $result['data'] : [],
        ]);
    }

    $memberEnvelope($code >= 400 ? $code : 422, [
        'success' => false,
        'code'    => $code,
        'message' => (string) ($result['message'] ?? 'Spor bahisleri başlatılamadı.'),
    ]);
}

if ($method === 'GET' && in_array($route, ['sportsbook_history.php', 'sportsbook/history'], true)) {
    $userId = $memberRequireLogin();
    $limit  = (int) ($_GET['limit'] ?? 50);
    $offset = (int) ($_GET['offset'] ?? 0);
    $rows   = SportsbookService::userHistory(AdminDatabase::pdo(), $userId, $limit, $offset);
    $memberEnvelope(200, [
        'success' => true,
        'code'    => 200,
        'message' => 'OK',
        'data'    => ['items' => $rows],
    ]);
}
