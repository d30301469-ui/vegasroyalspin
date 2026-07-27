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
            error_log('[cms-purge] FRONTEND_CMS_PURGE_SECRET tanımsız; uzak frontend cache purge atlandı (prefix=' . ($prefix ?? '*') . '). Frontend en fazla cache TTL süresi kadar bayat veri gösterebilir.');
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
            $body = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($body === false || $httpCode < 200 || $httpCode >= 300) {
                error_log(sprintf(
                    '[cms-purge] Uzak frontend cache purge BAŞARISIZ: %s (HTTP %d%s). Frontend bayat CMS verisi gösterebilir.',
                    $url,
                    $httpCode,
                    $curlError !== '' ? ', curl: ' . $curlError : ''
                ));
            }
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
        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__);
        $proxyDir = $base . '/storage/cache/public_api_proxy';
        $gamesDir = $base . '/storage/cache/games';
        foreach ([$proxyDir, $gamesDir] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . '/*') ?: [] as $file) {
                if (!is_string($file) || !is_file($file)) {
                    continue;
                }
                $fileBase = basename($file);
                if (!str_ends_with($fileBase, '.json') && !str_ends_with($fileBase, '.json.refresh.lock')) {
                    continue;
                }
                @unlink($file);
            }
        }

        // Site settings envelope is separate from cms/proxy caches — always purge on
        // full purge or explicit site_settings prefix so live_support_url etc. refresh.
        $shouldPurgeSiteSettings = $prefix === null
            || trim((string) $prefix) === ''
            || trim((string) $prefix) === 'site_settings';
        if ($shouldPurgeSiteSettings) {
            $siteSettingsApi = $base . '/api/SiteSettings.php';
            if (is_readable($siteSettingsApi)) {
                require_once $siteSettingsApi;
            }
            if (class_exists('ApiSiteSettings', false)) {
                try {
                    ApiSiteSettings::purgeCache();
                } catch (Throwable) {
                    // Best-effort.
                }
            } else {
                foreach ([
                    $base . '/storage/cache/site_settings_envelope.json',
                    $base . '/storage/cache/site_settings_envelope.json.refresh.lock',
                ] as $envelopeFile) {
                    if (is_file($envelopeFile)) {
                        @unlink($envelopeFile);
                    }
                }
            }
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
