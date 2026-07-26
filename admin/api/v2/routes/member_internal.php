<?php
/** Sunucu-sunucu güvenilir üye JWT yenileme (frontend proxy → backend). */

if ($method === 'POST' && $route === 'internal/frontend-member-jwt') {
    // Rotasyon güvenliği: özel trust secret'ı ve (geçiş dönemi için) eski
    // CMS purge secret'ı aday olarak denenir. İki taraf da
    // FRONTEND_MEMBER_TRUST_SECRET'a geçince fallback kaldırılabilir.
    $secretCandidates = [];
    foreach ([
        trim((string) (getenv('FRONTEND_MEMBER_TRUST_SECRET') ?: '')),
        trim((string) (getenv('FRONTEND_CMS_PURGE_SECRET') ?: '')),
        defined('FRONTEND_CMS_PURGE_SECRET') ? trim((string) FRONTEND_CMS_PURGE_SECRET) : '',
    ] as $candidate) {
        if ($candidate !== '' && !in_array($candidate, $secretCandidates, true)) {
            $secretCandidates[] = $candidate;
        }
    }
    $input = $memberInput($payload);
    $userId = (int) ($input['user_id'] ?? 0);
    $trust = trim((string) ($_SERVER['HTTP_X_FRONTEND_TRUST'] ?? ''));

    if ($secretCandidates === [] || $userId <= 0 || $trust === '') {
        $memberEnvelope(403, [
            'success' => false,
            'code' => 403,
            'message' => 'Frontend trust doğrulaması başarısız.',
        ]);
    }

    $trustValid = false;
    foreach ($secretCandidates as $candidate) {
        if (hash_equals(hash_hmac('sha256', 'member-jwt:' . $userId, $candidate), $trust)) {
            $trustValid = true;
            break;
        }
    }
    if (!$trustValid) {
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
