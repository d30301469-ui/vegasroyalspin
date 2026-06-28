<?php

declare(strict_types=1);

/**
 * Backend admin → split frontend CMS file-cache invalidation.
 */
final class FrontendCmsCachePurge
{
    public static function notify(?string $prefix = null): void
    {
        if (!function_exists('frontend_env_string')) {
            return;
        }

        $frontendUrl = rtrim(trim(frontend_env_string('FRONTEND_URL', frontend_env_string('SITE_URL', ''))), '/');
        $secret = trim(frontend_env_string('FRONTEND_CMS_PURGE_SECRET', ''));
        if ($frontendUrl === '' || $secret === '') {
            return;
        }

        $url = $frontendUrl . '/api/v2/internal/cms-cache-purge';
        if ($prefix !== null && trim($prefix) !== '') {
            $url .= '?' . http_build_query(['prefix' => trim($prefix)]);
        }

        if (!function_exists('curl_init')) {
            return;
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
}
