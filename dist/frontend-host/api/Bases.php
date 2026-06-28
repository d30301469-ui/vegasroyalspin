<?php

/**
 * Üye / içerik API için denenecek base URL sırası.
 */
final class ApiBases
{
    /**
     * @return list<string>
     */
    public static function forMemberApi(): array
    {
        self::ensureBackendClient();

        if (function_exists('frontend_is_api_only') && frontend_is_api_only()) {
            $outbound = class_exists('BackendApiClient', false)
                ? BackendApiClient::effectiveOutboundMainBaseUrl()
                : '';
            if ($outbound !== '') {
                return [$outbound];
            }
        }

        $out      = [];
        $main     = defined('API_BACKEND_MAIN_BASE_URL') ? rtrim((string) API_BACKEND_MAIN_BASE_URL, '/') : '';
        $slide    = defined('API_BACKEND_SLIDER_BASE_URL') ? rtrim((string) API_BACKEND_SLIDER_BASE_URL, '/') : '';
        $adminApi = '';
        if (defined('BACKEND_API_BASE_URL') && trim((string) BACKEND_API_BASE_URL) !== '') {
            $adminApi = rtrim((string) BACKEND_API_BASE_URL, '/');
        } elseif (defined('API_BACKEND_FALLBACK_BASE_URL') && trim((string) API_BACKEND_FALLBACK_BASE_URL) !== '') {
            $adminApi = rtrim((string) API_BACKEND_FALLBACK_BASE_URL, '/');
        } elseif (function_exists('frontend_default_member_api_base_url')) {
            $adminApi = rtrim(frontend_default_member_api_base_url(), '/');
        }

        if ($main !== '') {
            $out[] = $main;
        }
        if ($slide !== '' && !in_array($slide, $out, true)) {
            $out[] = $slide;
        }
        if ($adminApi !== '' && !in_array($adminApi, $out, true)) {
            $out[] = $adminApi;
        }

        if ($out === [] && class_exists('BackendApiClient', false)) {
            $fallback = BackendApiClient::effectiveOutboundMainBaseUrl();
            if ($fallback !== '') {
                $out[] = $fallback;
            }
        }

        return $out;
    }

    /**
     * Üye oyun favorileri: MAIN/SLIDER/SITE sırasına ek olarak GAMES tabanı (dolu ve yinelenmiyorsa) eklenir.
     *
     * @return list<string>
     */
    public static function forMemberApiWithGames(): array
    {
        $bases = self::forMemberApi();
        $games = defined('API_BACKEND_GAMES_BASE_URL') ? rtrim((string) API_BACKEND_GAMES_BASE_URL, '/') : '';
        if ($games === '') {
            return $bases;
        }
        if (in_array($games, $bases, true)) {
            return $bases;
        }
        if (isset($bases[0]) && $bases[0] !== '') {
            array_splice($bases, 1, 0, [$games]);
        } else {
            $bases[] = $games;
        }

        return $bases;
    }

    private static function ensureBackendClient(): void
    {
        if (class_exists('BackendApiClient', false)) {
            return;
        }

        $path = (defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__)) . '/services/BackendApiClient.php';
        if (is_readable($path)) {
            require_once $path;
        }
    }
}
