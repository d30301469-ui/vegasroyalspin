<?php

declare(strict_types=1);

/**
 * Global public API entry — tek dispatch standardı (frontend + monorepo).
 *
 * Tüm /api/* istekleri: allowlist → proxy (API-only) veya admin route modülleri (local PDO).
 */
final class PublicApiV2Dispatcher
{
    public static function dispatch(string $route): void
    {
        $route = trim($route, '/');
        if (self::tryDispatchCmsCachePurge($route)) {
            return;
        }
        if ($route === '' || !self::isAllowed($route)) {
            self::json(404, [
                'success' => false,
                'ok' => false,
                'code' => 404,
                'message' => 'Public API route not found.',
            ]);
        }

        if (function_exists('frontend_database_allowed') && !frontend_database_allowed()) {
            require_once BASE_PATH . '/services/BackendMemberApiProxy.php';
            try {
                BackendMemberApiProxy::forward($route);
            } catch (Throwable $proxyException) {
                error_log('[BackendMemberApiProxy] ' . $proxyException->getMessage());
                self::json(502, [
                    'success' => false,
                    'ok' => false,
                    'code' => 502,
                    'message' => 'API proxy hatası.',
                    'meta' => [
                        'reason' => $proxyException->getMessage(),
                        'route' => $route,
                    ],
                ]);
            }
        }

        if (!class_exists(\App\Core\Response::class, false)) {
            require_once BASE_PATH . '/app/bootstrap.php';
        }

        \App\Services\Api\PublicMemberApiDispatcher::dispatch($route);
    }

    private static function tryDispatchCmsCachePurge(string $route): bool
    {
        $normalized = strtolower(trim($route, '/'));
        if (!in_array($normalized, ['internal/cms-cache-purge', 'internal/cms_cache_purge.php'], true)) {
            return false;
        }

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            self::json(405, [
                'success' => false,
                'ok' => false,
                'code' => 405,
                'message' => 'CMS cache purge requires POST.',
            ]);
        }

        if (!function_exists('frontend_env_string')) {
            require_once BASE_PATH . '/config/env.php';
        }

        $secret = trim(frontend_env_string('FRONTEND_CMS_PURGE_SECRET', ''));
        $provided = trim((string) ($_SERVER['HTTP_X_CMS_PURGE_SECRET'] ?? ''));
        if ($secret === '' || $provided === '' || !hash_equals($secret, $provided)) {
            self::json(403, [
                'success' => false,
                'ok' => false,
                'code' => 403,
                'message' => 'CMS cache purge forbidden.',
            ]);
        }

        require_once BASE_PATH . '/api/CmsRemote.php';
        if (function_exists('metropol_backend_api_mark_success')) {
            metropol_backend_api_mark_success();
        }

        $prefix = trim((string) ($_GET['prefix'] ?? ''));
        $removed = ApiCmsRemote::purgeCache($prefix !== '' ? $prefix : null);

        // Site ayarları zarfı storage/cache/ kökünde tutulur (cms/ altında değil),
        // bu yüzden ApiCmsRemote::purgeCache onu temizlemez. Branding/logo
        // güncellemesinde (boş prefix = tam purge veya site_settings) bu zarfı da
        // düşür ki yeni logo API'den anında çekilsin.
        if ($prefix === '' || stripos($prefix, 'site_settings') !== false || stripos($prefix, 'site-settings') !== false) {
            require_once BASE_PATH . '/api/SiteSettings.php';
            if (method_exists('ApiSiteSettings', 'purgeCache')) {
                ApiSiteSettings::purgeCache();
            }
        }

        self::json(200, [
            'success' => true,
            'ok' => true,
            'code' => 200,
            'message' => 'CMS cache purged.',
            'data' => [
                'removed' => $removed,
                'prefix' => $prefix !== '' ? $prefix : null,
            ],
        ]);
    }

    private static function isAllowed(string $route): bool
    {
        self::ensureDispatcherLoaded();

        return \App\Services\Api\PublicMemberApiDispatcher::isAllowed($route);
    }

    private static function ensureDispatcherLoaded(): void
    {
        if (class_exists(\App\Services\Api\PublicMemberApiDispatcher::class, false)) {
            return;
        }

        $path = (defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__))
            . '/app/Services/Api/PublicMemberApiDispatcher.php';
        if (!is_readable($path)) {
            self::json(503, [
                'success' => false,
                'ok' => false,
                'code' => 503,
                'message' => 'Public API dispatcher is not available on this host.',
            ]);
        }

        require_once $path;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function json(int $status, array $payload): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
