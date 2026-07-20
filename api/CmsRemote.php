<?php

declare(strict_types=1);

/**
 * CMS remote reads with bounded latency, file cache, and fetch-source telemetry.
 */
final class ApiCmsRemote
{
    /** @var array<string, string> cacheKey => source (remote|cache|stale|skipped|failed|default) */
    private static array $fetchLog = [];

    public static function adminDatabaseFile(): string
    {
        // Standalone admin deploy (bo-nexthub.site): app/Core/AdminDatabase.php
        if (defined('ADMIN_APP_PATH')) {
            $panelDb = rtrim((string) ADMIN_APP_PATH, '/\\') . '/Core/AdminDatabase.php';
            if (is_file($panelDb)) {
                return $panelDb;
            }
        }

        $base = defined('BASE_PATH') ? rtrim((string) BASE_PATH, '/\\') : dirname(__DIR__);

        $standalone = $base . '/app/Core/AdminDatabase.php';
        if (is_file($standalone)) {
            return $standalone;
        }

        return $base . '/admin/app/Core/AdminDatabase.php';
    }

    public static function canUseLocalDatabase(): bool
    {
        if (defined('METROPOL_ADMIN_PANEL') && METROPOL_ADMIN_PANEL) {
            if (class_exists('AdminDatabase', false)) {
                return true;
            }
            if (is_file(self::adminDatabaseFile())) {
                return true;
            }
        }

        if (function_exists('frontend_database_allowed') && !frontend_database_allowed()) {
            return false;
        }

        return is_file(self::adminDatabaseFile());
    }

    public static function pdo(): PDO
    {
        if (!self::canUseLocalDatabase()) {
            throw new RuntimeException('Direct database access is disabled on API-only frontend hosts.');
        }

        // Admin panel always uses AdminDatabase (split-deploy backend host).
        if (defined('METROPOL_ADMIN_PANEL') && METROPOL_ADMIN_PANEL && class_exists('AdminDatabase', false)) {
            return AdminDatabase::pdo();
        }

        if (!defined('ADMIN_APP_PATH')) {
            define('ADMIN_APP_PATH', dirname(self::adminDatabaseFile(), 2));
        }
        if (!class_exists('AdminDatabase', false)) {
            require_once self::adminDatabaseFile();
        }

        return AdminDatabase::pdo();
    }

    public static function remoteTimeout(): int
    {
        if (function_exists('frontend_cms_http_timeout')) {
            return frontend_cms_http_timeout();
        }

        return function_exists('frontend_remote_http_timeout')
            ? frontend_remote_http_timeout()
            : 12;
    }

    public static function cacheFreshTtl(): int
    {
        if (function_exists('frontend_cms_cache_ttl')) {
            return frontend_cms_cache_ttl();
        }

        return 120;
    }

    public static function cacheStaleMaxAge(): int
    {
        if (function_exists('frontend_cms_cache_stale_max_age')) {
            return frontend_cms_cache_stale_max_age();
        }

        return 86400;
    }

    /**
     * @return array<string, string>
     */
    public static function fetchLog(): array
    {
        return self::$fetchLog;
    }

    public static function recordFetch(string $cacheKey, string $source): void
    {
        $cacheKey = trim($cacheKey);
        if ($cacheKey === '') {
            return;
        }
        self::$fetchLog[$cacheKey] = $source;
    }

    public static function usingFallback(): bool
    {
        foreach (self::$fetchLog as $source) {
            if (in_array($source, ['default', 'failed', 'skipped'], true)) {
                return true;
            }
        }

        return false;
    }

    public static function defaultSiteLabel(): string
    {
        if (function_exists('frontend_env_string')) {
            $fromEnv = trim(frontend_env_string('SITE_NAME', ''));
            if ($fromEnv !== '') {
                return $fromEnv;
            }
        }

        $fromGetenv = trim((string) (getenv('SITE_NAME') ?: ''));
        if ($fromGetenv !== '') {
            return $fromGetenv;
        }

        if (defined('SITE_URL')) {
            $host = (string) (parse_url((string) SITE_URL, PHP_URL_HOST) ?: '');
            $host = preg_replace('#^(?:www|m)\.#i', '', $host);
            if ($host !== '') {
                $parts = explode('.', $host);

                return ucfirst((string) ($parts[0] ?? 'Site'));
            }
        }

        return 'Site';
    }

    /**
     * @param list<string> $paths
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>|null
     */
    public static function getMain(array $paths, array $query = [], ?int $timeoutBudget = null): ?array
    {
        if (function_exists('metropol_should_skip_remote_backend') && metropol_should_skip_remote_backend()) {
            return null;
        }

        self::ensureBackendClient();
        if (!class_exists('BackendApiClient', false)) {
            return null;
        }

        $paths = array_values(array_filter(array_map(static fn ($path): string => trim((string) $path), $paths), static fn (string $path): bool => $path !== ''));
        if ($paths === []) {
            return null;
        }

        if (function_exists('frontend_is_api_only') && frontend_is_api_only()) {
            $paths = [$paths[0]];
        }

        $budget = max(1, min(30, $timeoutBudget ?? self::remoteTimeout()));
        $deadline = microtime(true) + $budget;

        foreach ($paths as $path) {
            $remaining = (int) max(1, ceil($deadline - microtime(true)));
            if ($remaining <= 0) {
                break;
            }

            try {
                $res = BackendApiClient::request(
                    'GET',
                    BackendApiClient::SVC_MAIN,
                    $path,
                    $query,
                    null,
                    min($remaining, self::remoteTimeout())
                );
                $data = self::acceptMainResponse($res);
                if ($data !== null) {
                    return $data;
                }
            } catch (Throwable) {
            }
        }

        // Ana base host'a ulaşılamıyorsa (ör. api.* DNS kaydı yok / host ölü),
        // yedek base URL'ler ile aynı path'leri dene. Böylece CMS içerikleri
        // (footer, site ayarları vb.) süresiz stale cache'e saplanmaz.
        foreach (self::fallbackMainBaseUrls() as $base) {
            foreach ($paths as $path) {
                $remaining = (int) max(1, ceil($deadline - microtime(true)));
                if ($remaining <= 0) {
                    break 2;
                }

                try {
                    $res = BackendApiClient::requestWithBase(
                        'GET',
                        $base,
                        $path,
                        $query,
                        null,
                        min($remaining, self::remoteTimeout())
                    );
                    $data = self::acceptMainResponse($res);
                    if ($data !== null) {
                        return $data;
                    }
                } catch (Throwable) {
                }
            }
        }

        if (function_exists('metropol_cms_api_mark_failure')) {
            metropol_cms_api_mark_failure();
        }

        return null;
    }

    /**
     * Başarılı ana API yanıtını çözer; aksi halde null.
     *
     * @param array<string, mixed>|null $res
     * @return array<string, mixed>|null
     */
    private static function acceptMainResponse(?array $res): ?array
    {
        if (!is_array($res)) {
            return null;
        }
        $code = (int) ($res['code'] ?? 0);
        if (empty($res['success']) && $code !== 200) {
            return null;
        }
        $data = BackendApiClient::unwrap($res);
        if (!is_array($data)) {
            return null;
        }
        if (function_exists('metropol_cms_api_mark_success')) {
            metropol_cms_api_mark_success();
        }

        return $data;
    }

    /**
     * Ana base başarısız olduğunda denenecek yedek API base URL'leri
     * (etkin ana base hariç, sırayla).
     *
     * Not: API_BACKEND_FALLBACK_BASE_URL sabiti metropol_normalize_member_api_public_url()
     * tarafından api.* host'una normalize edildiği için burada HAM env değerleri
     * kullanılır (admin.* backend host'u genellikle canlı olan tek host'tur).
     *
     * @return list<string>
     */
    private static function fallbackMainBaseUrls(): array
    {
        $mainBase = '';
        if (class_exists('BackendApiClient', false)) {
            $mainBase = rtrim(BackendApiClient::effectiveOutboundMainBaseUrl(), '/');
        }

        $candidates = [];
        $add = static function (string $url) use (&$candidates, $mainBase): void {
            $url = rtrim(trim($url), '/');
            if ($url === '' || $url === $mainBase || in_array($url, $candidates, true)) {
                return;
            }
            $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
            if ($host === '' || $host === 'localhost' || $host === '127.0.0.1') {
                return;
            }
            $candidates[] = $url;
        };

        if (function_exists('frontend_env_string')) {
            // Ham env değerleri (normalize edilmemiş)
            $add(frontend_env_string('API_BACKEND_FALLBACK_BASE_URL', ''));
            $add(frontend_env_string('BACKEND_API_BASE_URL', ''));
            $backendUrl = rtrim(frontend_env_string('BACKEND_URL', ''), '/');
            if ($backendUrl !== '') {
                $add($backendUrl . '/api/v2');
            }
        }
        if (defined('BACKEND_URL')) {
            $add(rtrim((string) BACKEND_URL, '/') . '/api/v2');
        }
        if (function_exists('deploy_domain')) {
            $backendUrl = rtrim((string) deploy_domain('backend_url'), '/');
            if ($backendUrl !== '') {
                $add($backendUrl . '/api/v2');
            }
        }
        if (defined('API_BACKEND_FALLBACK_BASE_URL')) {
            $add((string) API_BACKEND_FALLBACK_BASE_URL);
        }

        // Ana host api.* ise ana domain'in backend host'unu da dene
        // (api.example.com -> admin.example.com kaydı yoksa DNS çözülmez).
        $mainHost = strtolower((string) (parse_url($mainBase, PHP_URL_HOST) ?: ''));
        if (str_starts_with($mainHost, 'api.')) {
            $bareDomain = substr($mainHost, 4);
            $add('https://admin.' . $bareDomain . '/api/v2');
        }

        return $candidates;
    }

    /**
     * Cached CMS read: fresh cache → remote → stale cache.
     *
     * @param list<string> $paths
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>|null
     */
    public static function getMainCached(
        string $cacheKey,
        array $paths,
        array $query = [],
        ?int $timeoutBudget = null
    ): ?array {
        $cacheKey = trim($cacheKey);
        if ($cacheKey === '') {
            return self::getMain($paths, $query, $timeoutBudget);
        }

        $freshTtl = self::cacheFreshTtl();
        $staleMax = self::cacheStaleMaxAge();

        $cached = self::readPayloadCache($cacheKey, $freshTtl, false);
        if ($cached !== null) {
            self::recordFetch($cacheKey, 'cache');

            return $cached;
        }

        if (function_exists('metropol_should_skip_remote_backend') && metropol_should_skip_remote_backend()) {
            $stale = self::readPayloadCache($cacheKey, $staleMax, true);
            if ($stale !== null) {
                self::recordFetch($cacheKey, 'stale');

                return $stale;
            }
            self::recordFetch($cacheKey, 'skipped');

            return null;
        }

        // Single-flight + stale-while-revalidate: when the fresh window expired
        // but a stale copy exists, only ONE worker performs the blocking remote
        // refresh; concurrent requests serve the stale copy immediately. This
        // prevents request pile-up (and PHP-FPM worker exhaustion) every time the
        // TTL lapses while the backend is slow.
        $stalePreview = self::readPayloadCache($cacheKey, $staleMax, true);
        if ($stalePreview !== null && !self::acquireRefreshLock($cacheKey)) {
            self::recordFetch($cacheKey, 'stale');

            return $stalePreview;
        }

        $remote = self::getMain($paths, $query, $timeoutBudget);
        if ($remote !== null) {
            self::writePayloadCache($cacheKey, $remote, $query);
            self::recordFetch($cacheKey, 'remote');

            return $remote;
        }

        $stale = $stalePreview ?? self::readPayloadCache($cacheKey, $staleMax, true);
        if ($stale !== null) {
            self::recordFetch($cacheKey, 'stale');

            return $stale;
        }

        self::recordFetch($cacheKey, 'failed');

        return null;
    }

    /**
     * Single-flight guard for stale-while-revalidate. Returns true if the caller
     * should perform the blocking remote refresh, false if another worker is
     * already refreshing within the lock window (caller should serve stale).
     */
    private static function acquireRefreshLock(string $cacheKey): bool
    {
        $path = self::cacheFilePath($cacheKey) . '.refresh.lock';
        $lockTtl = max(5, min(30, self::remoteTimeout() + 3));
        if (is_file($path)) {
            $age = time() - (int) @filemtime($path);
            if ($age >= 0 && $age < $lockTtl) {
                return false;
            }
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @touch($path);

        return true;
    }

    /**
     * @param array<string, scalar|null> $query
     */
    public static function cacheKey(string $prefix, array $query = []): string
    {
        $prefix = preg_replace('/[^a-z0-9_\-]/i', '_', trim($prefix)) ?: 'cms';
        if ($query === []) {
            return $prefix;
        }
        ksort($query);
        $encoded = json_encode($query, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $prefix . '_' . substr(md5(is_string($encoded) ? $encoded : ''), 0, 12);
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>|null
     */
    public static function readPayloadCache(string $cacheKey, int $maxAgeSeconds, bool $allowStale = false): ?array
    {
        $path = self::cacheFilePath($cacheKey);
        if (!is_readable($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload) || !is_array($payload['data'] ?? null)) {
            return null;
        }
        $savedAt = (int) ($payload['saved_at'] ?? 0);
        if ($savedAt <= 0) {
            return null;
        }
        $age = time() - $savedAt;
        if (!$allowStale && $age > max(30, $maxAgeSeconds)) {
            return null;
        }

        return $payload['data'];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, scalar|null> $query
     */
    public static function writePayloadCache(string $cacheKey, array $data, array $query = []): void
    {
        $path = self::cacheFilePath($cacheKey);
        if (!self::canWriteCacheFile($path)) {
            return;
        }
        $payload = json_encode([
            'saved_at' => time(),
            'query' => $query,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($payload)) {
            @file_put_contents($path, $payload, LOCK_EX);
        }
    }

    private static function canWriteCacheFile(string $path): bool
    {
        $dir = dirname($path);
        if (is_dir($dir)) {
            return is_writable($dir) && (!file_exists($path) || is_writable($path));
        }
        $parentDir = dirname($dir);
        if (!is_dir($parentDir) || !is_writable($parentDir)) {
            return false;
        }
        $created = @mkdir($dir, 0755, true);

        return $created && is_writable($dir);
    }

    private static function cacheFilePath(string $cacheKey): string
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__);
        $safe = preg_replace('/[^a-z0-9_\-]/i', '_', $cacheKey) ?: 'cms';

        return rtrim(str_replace('\\', '/', $base), '/') . '/storage/cache/cms/' . $safe . '.json';
    }

    /**
     * Clear CMS file cache (all keys or one prefix).
     */
    public static function purgeCache(?string $cacheKeyPrefix = null): int
    {
        $dir = (defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__)) . '/storage/cache/cms';
        if (!is_dir($dir)) {
            return 0;
        }
        $removed = 0;
        $prefixRaw = $cacheKeyPrefix !== null ? preg_replace('/[^a-z0-9_\-]/i', '_', trim($cacheKeyPrefix)) : '';
        $prefixVariants = [];
        if ($prefixRaw !== '') {
            $prefixVariants[] = $prefixRaw;
            $prefixVariants[] = str_replace('-', '_', $prefixRaw);
            $prefixVariants[] = str_replace('_', '-', $prefixRaw);
            $normalizedPrefix = str_replace('-', '_', $prefixRaw);
            if ($normalizedPrefix === 'footer_pages') {
                $prefixVariants[] = 'footer_page';
            } elseif ($normalizedPrefix === 'footer_page') {
                $prefixVariants[] = 'footer_pages';
            }
            $prefixVariants = array_values(array_unique(array_filter($prefixVariants, static fn ($v): bool => $v !== '')));
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (!is_string($file) || !is_file($file)) {
                continue;
            }
            $basename = basename($file);
            if (!str_ends_with($basename, '.json') && !str_ends_with($basename, '.json.refresh.lock')) {
                continue;
            }
            if ($prefixVariants !== []) {
                $matched = false;
                foreach ($prefixVariants as $prefix) {
                    if (str_starts_with($basename, $prefix)) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    continue;
                }
            }
            if (@unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    private static function ensureBackendClient(): void
    {
        if (class_exists('BackendApiClient', false)) {
            return;
        }

        $client = defined('BASE_PATH')
            ? BASE_PATH . '/services/BackendApiClient.php'
            : dirname(__DIR__) . '/services/BackendApiClient.php';
        if (is_readable($client)) {
            require_once $client;
        }
    }
}
