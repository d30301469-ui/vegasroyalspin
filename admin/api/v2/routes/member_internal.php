<?php
/** Sunucu-sunucu güvenilir üye JWT yenileme (frontend proxy → backend). */

if ($method === 'POST' && $route === 'internal/frontend-member-jwt') {
    $secret = trim((string) (getenv('FRONTEND_CMS_PURGE_SECRET') ?: ''));
    if ($secret === '' && defined('FRONTEND_CMS_PURGE_SECRET')) {
        $secret = trim((string) FRONTEND_CMS_PURGE_SECRET);
    }
    $input = $memberInput($payload);
    $userId = (int) ($input['user_id'] ?? 0);
    $trust = trim((string) ($_SERVER['HTTP_X_FRONTEND_TRUST'] ?? ''));

    if ($secret === '' || $userId <= 0 || $trust === '') {
        $memberEnvelope(403, [
            'success' => false,
            'code' => 403,
            'message' => 'Frontend trust doğrulaması başarısız.',
        ]);
    }

    $expected = hash_hmac('sha256', 'member-jwt:' . $userId, $secret);
    if (!hash_equals($expected, $trust)) {
        $memberEnvelope(403, [
            'success' => false,
            'code' => 403,
            'message' => 'Frontend trust doğrulaması başarısız.',
        ]);
    }

    $pdo = AdminDatabase::pdo();
    $user = $memberUserById($pdo, $userId);
    if (!$user) {
        $memberEnvelope(404, [
            'success' => false,
            'code' => 404,
            'message' => 'Kullanıcı bulunamadı.',
        ]);
    }

    try {
        $jwt = $memberJwtIssue($pdo, $user);
    } catch (Throwable) {
        $memberEnvelope(503, [
            'success' => false,
            'code' => 503,
            'message' => 'JWT üretilemedi. member_jwt_tokens tablosunu kontrol edin.',
        ]);
    }

    if ($jwt === '') {
        $memberEnvelope(503, [
            'success' => false,
            'code' => 503,
            'message' => 'JWT üretilemedi.',
        ]);
    }

    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Üye JWT yenilendi.',
        'data' => [
            'token' => $jwt,
            'user_id' => $userId,
        ],
    ]);
}
