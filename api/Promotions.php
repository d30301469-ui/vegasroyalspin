<?php

/**
 * GET /api/v2/promotions.php — liste (Bearer isteğe bağlı; data.viewer).
 * POST /api/v2/promotions.php — bonus talebi (Bearer zorunlu; api.md, bonus_claim ile aynı gövde).
 */
final class ApiPromotions
{
    /**
     * @return list<string>
     */
    private static function candidateBases(): array
    {
        $bases = ApiBases::forMemberApi();
        if (function_exists('frontend_is_api_only') && frontend_is_api_only() && $bases !== []) {
            return [$bases[0]];
        }

        if (defined('SITE_URL') && SITE_URL !== '') {
            $siteBase = rtrim((string) SITE_URL, '/') . '/api/v2';
            if (!in_array($siteBase, $bases, true)) {
                array_unshift($bases, $siteBase);
            }
        }

        return $bases;
    }

    /** @var list<string> */
    public const CATEGORY_SLUGS = ['sports', 'live_casino', 'slots', 'loss_bonus', 'vip'];

    /**
     * @param array{category?: string|null} $queryParams
     * @return array{
     *   promotions: list<array<string, mixed>>,
     *   total: int,
     *   category: string|null,
     *   claimPolicy: array<string, mixed>,
     *   viewer: array<string, mixed>|null,
     *   envelope_ok: bool,
     *   envelope_message: string
     * }
     */
    public static function fetch(array $queryParams = [], ?string $memberJwtPlain = null): array
    {
        $query = [];
        $cat = isset($queryParams['category']) ? trim((string) $queryParams['category']) : '';
        if ($cat !== '' && in_array($cat, self::CATEGORY_SLUGS, true)) {
            $query['category'] = $cat;
        }

        $jwt = $memberJwtPlain !== null ? trim($memberJwtPlain) : '';
        $authHeader = $jwt !== '' ? ApiMemberApi::bearerAuthorizationHeader($memberJwtPlain) : null;
        $apiOnly = function_exists('frontend_is_api_only') && frontend_is_api_only();
        $cacheKey = ApiCmsRemote::cacheKey('promotions', $query);
        if ($jwt !== '') {
            $cacheKey .= '_auth_' . substr(hash('sha256', $jwt), 0, 12);
        }
        $timeout = function_exists('frontend_cms_http_timeout') ? frontend_cms_http_timeout() : 12;

        if (function_exists('metropol_should_skip_remote_backend') && metropol_should_skip_remote_backend()) {
            if ($apiOnly) {
                $stale = ApiCmsRemote::readPayloadCache($cacheKey, ApiCmsRemote::cacheStaleMaxAge(), true);
                if (is_array($stale['result'] ?? null)) {
                    ApiCmsRemote::recordFetch($cacheKey, 'stale');

                    return $stale['result'];
                }
                ApiCmsRemote::recordFetch($cacheKey, 'skipped');
            }

            return self::emptyResult();
        }

        foreach (self::candidateBases() as $base) {
            $api = ApiClient::getWithBase($base, '/content/promotions', $query, max(12, $timeout), $authHeader);
            if ($api === null || !ApiEnvelope::isOk($api)) {
                continue;
            }
            $result = self::resultFromEnvelope($api);
            if ($apiOnly) {
                ApiCmsRemote::writePayloadCache($cacheKey, ['result' => $result], $query);
                ApiCmsRemote::recordFetch($cacheKey, 'remote');
            }

            return $result;
        }

        $parsed = ApiMemberApi::firstSuccessfulMemberPath(
            self::candidateBases(),
            MemberApiPaths::PROMOTIONS,
            static function (string $base, string $path) use ($query, $authHeader, $timeout): ?array {
                $api = ApiClient::getWithBase($base, $path, $query, max(12, $timeout), $authHeader);
                if ($api === null || !ApiEnvelope::isOk($api)) {
                    return null;
                }

                return self::resultFromEnvelope($api);
            },
            static fn (mixed $r): bool => is_array($r)
        );

        if (is_array($parsed)) {
            if ($apiOnly) {
                ApiCmsRemote::writePayloadCache($cacheKey, ['result' => $parsed], $query);
                ApiCmsRemote::recordFetch($cacheKey, 'remote');
            }

            return $parsed;
        }

        if ($apiOnly) {
            $stale = ApiCmsRemote::readPayloadCache($cacheKey, ApiCmsRemote::cacheStaleMaxAge(), true);
            if (is_array($stale['result'] ?? null)) {
                ApiCmsRemote::recordFetch($cacheKey, 'stale');

                return $stale['result'];
            }
            ApiCmsRemote::recordFetch($cacheKey, 'failed');
            if (function_exists('metropol_cms_api_mark_failure')) {
                metropol_cms_api_mark_failure();
            }
        }

        return self::emptyResult();
    }

    /**
     * @param array<string, mixed> $api
     * @return array{
     *   promotions: list<array<string, mixed>>,
     *   total: int,
     *   category: string|null,
     *   claimPolicy: array<string, mixed>,
     *   viewer: array<string, mixed>|null,
     *   envelope_ok: bool,
     *   envelope_message: string
     * }
     */
    private static function resultFromEnvelope(array $api): array
    {
        $data = ApiEnvelope::data($api);
        $list = $data['promotions'] ?? [];
        if (!is_array($list)) {
            $list = [];
        }
        $list = array_values(array_filter($list, 'is_array'));
        if (!class_exists('ApiMediaUrl', false) && is_readable(API_PATH . '/MediaUrl.php')) {
            require_once API_PATH . '/MediaUrl.php';
        }
        if (class_exists('ApiMediaUrl', false)) {
            $list = array_map(
                static fn (array $row): array => ApiMediaUrl::resolvePromotionRow($row),
                $list
            );
        }
        $claim = $data['claimPolicy'] ?? [];
        if (!is_array($claim)) {
            $claim = [];
        }
        $viewer = $data['viewer'] ?? null;
        $viewer = is_array($viewer) ? $viewer : null;
        $catOut = null;
        if (array_key_exists('category', $data)) {
            $c = $data['category'];
            $catOut = ($c === null || is_string($c)) ? $c : null;
        }

        return [
            'promotions'       => $list,
            'total'            => isset($data['total']) ? (int) $data['total'] : count($list),
            'category'         => $catOut,
            'claimPolicy'      => $claim,
            'viewer'           => $viewer,
            'envelope_ok'      => true,
            'envelope_message' => ApiEnvelope::message($api),
        ];
    }

    /**
     * POST üye promotions.php — { promotionId: int, message?: string }.
     *
     * @param array{promotionId: int, message?: string} $body
     * @return array<string, mixed>|null Ham JSON zarfı; tüm tabanlar başarısızsa null
     */
    public static function claim(string $memberJwtPlain, array $body): ?array
    {
        $payload = [
            'promotionId' => (int) ($body['promotionId'] ?? 0),
        ];
        if (!empty($body['message']) && is_string($body['message'])) {
            $m = trim($body['message']);
            if ($m !== '') {
                $payload['message'] = $m;
            }
        }

        foreach (self::candidateBases() as $base) {
            $auth = ApiMemberApi::bearerAuthorizationHeader($memberJwtPlain);
            if ($auth === null) {
                break;
            }
            $api = ApiClient::postWithBase($base, '/content/promotions', $payload, 30, $auth);
            if ($api !== null && ApiEnvelope::isOk($api)) {
                return $api;
            }
        }

        return ApiMemberApi::relayPostWithMemberJwt(
            MemberApiPaths::PROMOTIONS,
            $memberJwtPlain,
            $payload,
            30
        );
    }

    /**
     * @return array{
     *   promotions: list<array<string, mixed>>,
     *   total: int,
     *   category: null,
     *   claimPolicy: array<string, mixed>,
     *   viewer: null,
     *   envelope_ok: bool,
     *   envelope_message: string
     * }
     */
    public static function emptyResult(): array
    {
        return [
            'promotions'       => [],
            'total'            => 0,
            'category'         => null,
            'claimPolicy'      => [],
            'viewer'           => null,
            'envelope_ok'      => false,
            'envelope_message' => '',
        ];
    }
}
