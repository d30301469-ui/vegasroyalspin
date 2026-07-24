<?php

declare(strict_types=1);

/**
 * Backend admin → split frontend CMS file-cache invalidation.
 */
final class FrontendCmsCachePurge
{
    public static function notify(?string $prefix = null): void
    {
        self::purgeLocalCaches($prefix);

        if (!function_exists('frontend_env_string')) {
            return;
        }

        $secret = trim(frontend_env_string('FRONTEND_CMS_PURGE_SECRET', ''));
        if ($secret === '') {
            return;
        }

        if (!function_exists('curl_init')) {
            return;
        }

        $targets = self::targetFrontendUrls();
        if ($targets === []) {
            return;
        }

        foreach ($targets as $frontendUrl) {
            $url = $frontendUrl . '/api/v2/internal/cms-cache-purge';
            if ($prefix !== null && trim($prefix) !== '') {
                $url .= '?' . http_build_query(['prefix' => trim($prefix)]);
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'X-CMS-Purge-Secret: ' . $secret,
                ],
            ]);
            if (defined('CURL_IPRESOLVE_V4')) {
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            }
            curl_exec($ch);
            curl_close($ch);
        }

        self::purgeLocalCaches($prefix);
    }

    private static function purgeLocalCaches(?string $prefix = null): void
    {
        $cmsRemote = (defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__)) . '/api/CmsRemote.php';
        if (!is_readable($cmsRemote)) {
            $cmsRemote = dirname(__DIR__) . '/api/CmsRemote.php';
        }
        if (is_readable($cmsRemote)) {
            require_once $cmsRemote;
        }
        if (class_exists('ApiCmsRemote', false)) {
            try {
                ApiCmsRemote::purgeCache($prefix !== null && trim($prefix) !== '' ? trim($prefix) : null);
            } catch (Throwable) {
                // Best-effort local fallback purge.
            }
        }

        // Proxy cache keys are sha1(route|query), so prefix targeting is not practical.
        // Remove all proxy cache entries to avoid stale CMS payloads after admin updates.
        $proxyDir = (defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__)) . '/storage/cache/public_api_proxy';
        if (!is_dir($proxyDir)) {
            return;
        }

        foreach (glob($proxyDir . '/*') ?: [] as $file) {
            if (!is_string($file) || !is_file($file)) {
                continue;
            }
            $base = basename($file);
            if (!str_ends_with($base, '.json') && !str_ends_with($base, '.json.refresh.lock')) {
                continue;
            }
            @unlink($file);
        }
    }

    /**
     * @return list<string>
     */
    private static function targetFrontendUrls(): array
    {
        $candidates = [
            frontend_env_string('FRONTEND_URL', ''),
            frontend_env_string('SITE_URL', ''),
            frontend_env_string('MOBILE_URL', ''),
        ];

        $targets = [];
        foreach ($candidates as $candidate) {
            $url = rtrim(trim((string) $candidate), '/');
            if ($url === '' || in_array($url, $targets, true)) {
                continue;
            }
            $targets[] = $url;
        }

        return $targets;
    }
}
