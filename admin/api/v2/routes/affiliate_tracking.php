<?php
/**
 * Ortaklık takip uçları — frontend (vegasroyalspin.com) tarafından sunucu-sunucu çağrılır.
 * Üye oturumu gerektirmez; yalnızca referans kodu çözümleme ve tıklama kaydı yapar.
 */

if (!isset($affiliateTrackingRoute)) {
    $affiliateTrackingRoute = strtolower(trim((string) $route, '/'));
}

if (in_array($affiliateTrackingRoute, [
    'affiliate/track-click',
    'affiliate/track_click',
    'track-click',
], true)) {
    if ($method !== 'POST') {
        $memberEnvelope(405, ['success' => false, 'code' => 405, 'message' => 'POST bekleniyor.']);
    }

    admin_require_project_file('services/AffiliateService.php');
    $input = $memberInput($payload);
    $code = (string) ($input['referral_code'] ?? $input['ref'] ?? $input['code'] ?? '');
    $tracked = AffiliateService::trackClick(AdminDatabase::pdo(), $code, [
        'landing_url' => (string) ($input['landing_url'] ?? ''),
        'ip' => (string) ($input['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '')),
        'user_agent' => (string) ($input['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')),
        'referrer' => (string) ($input['referrer'] ?? ''),
        'country' => (string) ($input['country'] ?? ''),
    ]);

    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => $tracked ? 'Tıklama kaydedildi.' : 'Tıklama kaydedilmedi.',
        'data' => ['tracked' => $tracked],
    ]);
}

if (in_array($affiliateTrackingRoute, [
    'affiliate/by-code',
    'affiliate/by_code',
], true)) {
    admin_require_project_file('services/AffiliateService.php');
    $code = (string) ($_GET['code'] ?? $_GET['ref'] ?? '');
    $resolved = AffiliateService::resolveCode(AdminDatabase::pdo(), $code);

    if ($resolved === null) {
        $memberEnvelope(404, [
            'success' => false,
            'code' => 404,
            'message' => 'Referans kodu bulunamadı.',
        ]);
    }

    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Referans kodu çözümlendi.',
        'data' => [
            'id' => $resolved['type'] === 'affiliate' ? $resolved['affiliate_id'] : 0,
            'type' => $resolved['type'],
            'affiliate_id' => $resolved['affiliate_id'],
            'user_id' => $resolved['user_id'],
            'referral_code' => $resolved['referral_code'],
            'status' => $resolved['status'],
        ],
    ]);
}

if (in_array($affiliateTrackingRoute, [
    'affiliate/resolve-referral',
    'affiliate/resolve_referral',
    'resolve-referral',
], true)) {
    admin_require_project_file('services/AffiliateService.php');
    $ip = (string) ($_GET['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''));
    $resolved = AffiliateService::resolveByIp(AdminDatabase::pdo(), $ip);

    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => $resolved !== null ? 'Referans bulundu.' : 'Referans bulunamadı.',
        'data' => $resolved !== null ? [
            'referral_code' => $resolved['referral_code'],
            'affiliate_id' => $resolved['affiliate_id'],
            'type' => $resolved['type'],
        ] : new stdClass(),
    ]);
}
