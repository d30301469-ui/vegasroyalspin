<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/FrontendInstallGate.php';

final class FrontendInstaller
{
    public function __construct(private string $root)
    {
        $this->root = FrontendInstallGate::root($root);
    }

    /**
     * @return list<array{label: string, ok: bool, detail: string, critical?: bool}>
     */
    public function checkRequirements(): array
    {
        $checks = [];

        $checks[] = $this->check(
            'PHP sürümü (>= 8.0)',
            version_compare(PHP_VERSION, '8.0.0', '>='),
            PHP_VERSION
        );

        foreach (['json', 'mbstring', 'openssl', 'curl'] as $ext) {
            $checks[] = $this->check(
                'PHP eklentisi: ' . $ext,
                extension_loaded($ext),
                extension_loaded($ext) ? 'yüklü' : 'eksik'
            );
        }

        $checks[] = $this->check(
            'Composer vendor/ (zip ile gelir)',
            is_file($this->root . '/vendor/autoload.php'),
            is_file($this->root . '/vendor/autoload.php')
                ? 'vendor/autoload.php mevcut — sunucuda composer gerekmez'
                : 'Eksik: güncel deploy zip kullanın',
            false
        );

        foreach (['storage', 'storage/logs', 'storage/cache'] as $dir) {
            $path = $this->root . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            $writable = is_dir($path) && is_writable($path);
            $checks[] = $this->check(
                'Yazılabilir: ' . $dir,
                $writable,
                $writable ? 'ok' : 'chmod 755 veya 775 gerekli'
            );
        }

        $envWritable = is_file($this->root . '/.env')
            ? is_writable($this->root . '/.env')
            : is_writable($this->root);
        $checks[] = $this->check(
            '.env yazılabilir',
            $envWritable,
            $envWritable ? 'ok' : 'Site kökünde .env oluşturma izni gerekli'
        );

        $checks[] = $this->check(
            'admin/ klasörü yok (split frontend)',
            !is_dir($this->root . '/admin'),
            is_dir($this->root . '/admin')
                ? 'Uyarı: admin/ bulundu — production frontend paketinde olmamalı'
                : 'ok',
            false
        );

        return $checks;
    }

    public function requirementsPassed(): bool
    {
        foreach ($this->checkRequirements() as $check) {
            if (empty($check['ok']) && !empty($check['critical'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, envelope?: array<string, mixed>}
     */
    public function testBackendConnection(array $input): array
    {
        $backendUrl = rtrim(trim((string) ($input['backend_url'] ?? '')), '/');
        if ($backendUrl === '' || filter_var($backendUrl, FILTER_VALIDATE_URL) === false) {
            return ['ok' => false, 'message' => 'Geçerli bir backend URL girin (https://bo-nexthub.site).'];
        }

        $probeFile = $this->root . '/services/BackendConnectivityProbe.php';
        if (is_readable($probeFile)) {
            require_once $probeFile;
            $verified = BackendConnectivityProbe::verifyBackendForInstall($backendUrl);
            if ($verified['ok']) {
                $result = [
                    'ok' => true,
                    'message' => (string) ($verified['message'] ?? 'Backend bağlantısı başarılı.'),
                ];
                if (!empty($verified['internal'])) {
                    $result['internal'] = $verified['internal'];
                }

                return $result;
            }

            return [
                'ok' => false,
                'message' => (string) ($verified['message'] ?? 'Backend erişilemedi.'),
            ];
        }

        $deadline = microtime(true) + 6.0;
        $healthUrl = $backendUrl . '/health.php';
        $health = $this->httpGet($healthUrl, $this->remainingInstallTimeout($deadline, 3));
        if ($health['body'] === null) {
            return [
                'ok' => false,
                'message' => 'Backend health kontrolü başarısız: '
                    . $this->formatHttpFailure($healthUrl, $health)
                    . ' — Önce bo-nexthub.site üzerinde backend kurulumunu tamamlayın.',
            ];
        }

        $candidates = [
            $backendUrl . '/api/v2/site_settings.php',
        ];
        require_once $this->root . '/app/Services/InstallEnvBuilder.php';
        $candidates[] = InstallEnvBuilder::resolveApiPublicBaseUrl($backendUrl) . '/site_settings.php';
        $candidates = array_values(array_unique($candidates));

        $lastError = '';
        foreach ($candidates as $testUrl) {
            $remaining = $this->remainingInstallTimeout($deadline, 4);
            if ($remaining <= 0) {
                $lastError = 'Backend test süresi aşıldı (6 sn). Sunucu yanıt vermiyor olabilir.';
                break;
            }

            $http = $this->httpGet($testUrl, $remaining);
            if ($http['body'] === null) {
                $lastError = $this->formatHttpFailure($testUrl, $http);
                continue;
            }

            $decoded = $this->decodeJsonResponse($http['body']);
            if (!is_array($decoded)) {
                $snippet = trim(strip_tags(substr($http['body'], 0, 280)));
                $lastError = 'Backend geçersiz yanıt (HTTP ' . $http['code'] . '): '
                    . ($snippet !== '' ? $snippet : 'boş veya JSON değil');

                continue;
            }

            if (!empty($decoded['success']) || (int) ($decoded['code'] ?? 0) === 200) {
                return [
                    'ok' => true,
                    'message' => 'Backend bağlantısı başarılı (' . $testUrl . ').',
                    'envelope' => $decoded,
                ];
            }

            $msg = (string) ($decoded['message'] ?? 'Bilinmeyen hata');
            $metaRoute = is_array($decoded['meta'] ?? null) ? (string) ($decoded['meta']['route'] ?? '') : '';
            if ($metaRoute !== '') {
                $msg .= ' (route=' . $metaRoute . ')';
            }
            $lastError = 'Backend API hatası (HTTP ' . $http['code'] . '): ' . $msg;
        }

        return [
            'ok' => false,
            'message' => $lastError !== ''
                ? $lastError
                : 'Backend API yanıt vermedi. Önce bo-nexthub.site üzerinde /api/v2/site_settings.php adresini tarayıcıda test edin.',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: bool, message: string}
     */
    public function run(array $input): array
    {
        if (FrontendInstallGate::isInstalled($this->root)) {
            return ['success' => false, 'message' => 'Kurulum zaten tamamlanmış.'];
        }

        if (!$this->requirementsPassed()) {
            return ['success' => false, 'message' => 'Sunucu gereksinimleri karşılanmıyor.'];
        }

        $frontendUrl = self::normalizeSiteOrigin(rtrim(trim((string) ($input['frontend_url'] ?? '')), '/'));
        $backendUrl = self::normalizeSiteOrigin(rtrim(trim((string) ($input['backend_url'] ?? '')), '/'));
        if (is_readable($this->root . '/config/cloudflare.php')) {
            require_once $this->root . '/config/cloudflare.php';
        }
        if (function_exists('metropol_coerce_public_https_url')) {
            $frontendUrl = self::normalizeSiteOrigin(metropol_coerce_public_https_url($frontendUrl));
            $backendUrl = self::normalizeSiteOrigin(metropol_coerce_public_https_url($backendUrl));
        }
        $memberJwt = trim((string) ($input['member_jwt_secret'] ?? ''));
        $purgeSecret = trim((string) ($input['frontend_cms_purge_secret'] ?? ''));
        $appKey = trim((string) ($input['app_key'] ?? ''));
        $liveSupport = rtrim(trim((string) ($input['live_support_url'] ?? 'https://direct.lc.chat/19301899/')), '/') . '/';
        $telegramUrl = rtrim(trim((string) ($input['telegram_url'] ?? 'https://t.me')), '/');
        $whatsappUrl = trim((string) ($input['whatsapp_url'] ?? ''));

        if ($frontendUrl === '' || filter_var($frontendUrl, FILTER_VALIDATE_URL) === false) {
            return ['success' => false, 'message' => 'Geçerli bir frontend URL girin.'];
        }
        if ($backendUrl === '' || filter_var($backendUrl, FILTER_VALIDATE_URL) === false) {
            return ['success' => false, 'message' => 'Geçerli bir backend URL girin.'];
        }
        if (!FrontendInstallGate::isValidSecret($memberJwt)) {
            return ['success' => false, 'message' => 'MEMBER_JWT_SECRET en az 32 karakter olmalı ve backend .env ile birebir aynı olmalı.'];
        }
        if (!FrontendInstallGate::isValidSecret($purgeSecret)) {
            return ['success' => false, 'message' => 'FRONTEND_CMS_PURGE_SECRET en az 32 karakter olmalı ve backend .env ile birebir aynı olmalı.'];
        }
        if ($appKey === '') {
            $appKey = self::generateSecret(48);
        }
        if (!FrontendInstallGate::isValidSecret($appKey)) {
            return ['success' => false, 'message' => 'APP_KEY en az 32 karakter olmalı.'];
        }

        $backendTest = ['ok' => false, 'message' => ''];
        $skipBackendTest = !empty($input['skip_backend_test']);
        if (!$skipBackendTest) {
            $backendTest = $this->testBackendConnection(['backend_url' => $backendUrl]);
            if (!$backendTest['ok']) {
                return ['success' => false, 'message' => $backendTest['message']];
            }
        }

        $frontendHost = strtolower((string) (parse_url($frontendUrl, PHP_URL_HOST) ?: ''));
        $backendHost = strtolower((string) (parse_url($backendUrl, PHP_URL_HOST) ?: ''));
        if (str_starts_with($backendHost, 'api.')) {
            $backendHost = substr($backendHost, 4);
        }
        if ($frontendHost === '' || $backendHost === '') {
            return ['success' => false, 'message' => 'Frontend ve backend host adları çözümlenemedi.'];
        }

        $sessionCookie = self::sessionCookieDomain($frontendHost);

        try {
            require_once $this->root . '/app/Services/InstallEnvBuilder.php';
            $envValues = InstallEnvBuilder::buildFrontendEnv([
                'frontend_url' => $frontendUrl,
                'backend_url' => $backendUrl,
                'app_key' => $appKey,
                'member_jwt_secret' => $memberJwt,
                'frontend_cms_purge_secret' => $purgeSecret,
                'session_cookie_domain' => $sessionCookie,
                'live_support_url' => $liveSupport,
                'telegram_url' => $telegramUrl,
                'whatsapp_url' => $whatsappUrl,
            ]);

            $envErrors = InstallEnvBuilder::validateSplitFrontendEnv($envValues);
            if ($envErrors !== []) {
                return [
                    'success' => false,
                    'message' => 'Ortam doğrulaması başarısız: ' . implode('; ', $envErrors),
                ];
            }

            $this->writeEnv($envValues);
            $this->clearInstallCaches();

            FrontendInstallGate::loadEnv($this->root);
            $this->seedSiteSettingsCacheIfAvailable($backendTest['envelope'] ?? null);
            FrontendInstallGate::writeLock($this->root, [
                'frontend_url' => $frontendUrl,
                'backend_url' => $backendUrl,
                'backend_verified' => !$skipBackendTest,
            ]);
            FrontendInstallGate::clearCsrfToken($this->root);

            $message = 'Kurulum tamamlandı. .env oluşturuldu. Ana sayfaya yönlendiriliyorsunuz.';
            if ($skipBackendTest) {
                $message .= ' (Backend doğrulaması atlandı — backend hazır olunca site ayarları otomatik güncellenir.)';
            }

            return [
                'success' => true,
                'message' => $message,
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Kurulum hatası: ' . $exception->getMessage(),
            ];
        }
    }

    /** Public site URL must be origin only (no /api/v2 path — breaks JS apiUrl). */
    public static function normalizeSiteOrigin(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return $url;
        }
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        return $scheme . '://' . $host . $port;
    }

    /**
     * @return list<string>
     */
    public static function frontendHostVariants(string $frontendUrl): array
    {
        $host = strtolower((string) (parse_url($frontendUrl, PHP_URL_HOST) ?: ''));
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        if (str_starts_with($host, 'm.')) {
            $host = substr($host, 2);
        }
        if ($host === '') {
            return [];
        }

        return array_values(array_unique([$host, 'www.' . $host, 'm.' . $host]));
    }

    public static function sessionCookieDomain(string $frontendHost): string
    {
        $host = strtolower($frontendHost);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        if (str_starts_with($host, 'm.')) {
            $host = substr($host, 2);
        }
        if ($host === '' || !str_contains($host, '.')) {
            return '';
        }

        return '.' . $host;
    }

    public static function generateSecret(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    /**
     * @param array<string, string> $values
     */
    private function writeEnv(array $values): void
    {
        $lines = [
            '# Otomatik oluşturuldu — FrontendInstaller',
            '# ' . gmdate('Y-m-d H:i:s') . ' UTC',
            '# Split frontend (API-only) — MySQL credentials must NOT be added here.',
            '',
        ];

        foreach ($values as $key => $value) {
            $escaped = str_contains($value, ' ') || str_contains($value, '#') || str_contains($value, '"')
                ? '"' . str_replace('"', '\\"', $value) . '"'
                : $value;
            $lines[] = $key . '=' . $escaped;
        }

        $lines[] = '';

        $target = $this->root . '/.env';
        if (is_file($target) && !is_writable($target)) {
            throw new RuntimeException('.env dosyası yazılamıyor.');
        }

        if (file_put_contents($target, implode("\n", $lines)) === false) {
            throw new RuntimeException('.env dosyası oluşturulamadı.');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonResponse(string $body): ?array
    {
        // Çift BOM (UTF-8) veya FEFF — backend PHP dosyalarından gelebilir.
        $body = preg_replace('/^(?:\xEF\xBB\xBF|\x{FEFF})+/u', '', $body) ?? $body;
        $body = ltrim($body, "\xEF\xBB\xBF \t\n\r\0\x0B");
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // JSON gövdesi { veya [ ile başlıyorsa ara boşluk/BOM sonrası tekrar dene.
        $start = strcspn($body, "{[");
        if ($start > 0 && $start < strlen($body)) {
            $decoded = json_decode(substr($body, $start), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $envelope
     */
    private function seedSiteSettingsCacheIfAvailable(?array $envelope): void
    {
        if (!is_array($envelope) || $envelope === []) {
            return;
        }
        $settingsFile = $this->root . '/api/SiteSettings.php';
        if (!is_readable($settingsFile)) {
            return;
        }
        require_once $settingsFile;
        if (class_exists('ApiSiteSettings', false)) {
            ApiSiteSettings::seedInstallCache($envelope);
        }
    }

    private function remainingInstallTimeout(float $deadline, int $fallbackSeconds): int
    {
        $remaining = (int) ceil($deadline - microtime(true));

        return max(1, min($fallbackSeconds, $remaining));
    }

    private function clearInstallCaches(): void
    {
        foreach ([
            $this->root . '/storage/cache/backend_internal_base.json',
            $this->root . '/storage/cache/cms_api_circuit.json',
        ] as $cacheFile) {
            if (is_file($cacheFile)) {
                @unlink($cacheFile);
            }
        }
    }

    /**
     * @return array{body: ?string, code: int, error: string}
     */
    private function httpGet(string $url, int $timeoutSeconds = 4): array
    {
        $timeoutSeconds = max(1, min(8, $timeoutSeconds));
        $connectTimeout = min($timeoutSeconds, 2);
        if (!function_exists('curl_init')) {
            $ctx = stream_context_create([
                'http' => ['timeout' => $timeoutSeconds, 'ignore_errors' => true],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $body = @file_get_contents($url, false, $ctx);
            $code = 0;
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m)) {
                $code = (int) $m[1];
            }

            return [
                'body' => is_string($body) && $body !== '' ? $body : null,
                'code' => $code,
                'error' => is_string($body) && $body !== '' ? '' : 'Boş yanıt veya bağlantı kurulamadı',
            ];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['body' => null, 'code' => 0, 'error' => 'curl_init başarısız'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        if (defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!is_string($body) || $body === '') {
            return ['body' => null, 'code' => $code, 'error' => $error !== '' ? $error : 'Boş yanıt'];
        }

        if ($code >= 500) {
            return ['body' => null, 'code' => $code, 'error' => 'HTTP ' . $code];
        }

        return ['body' => $body, 'code' => $code, 'error' => ''];
    }

    /**
     * @param array{body: ?string, code: int, error: string} $http
     */
    private function formatHttpFailure(string $testUrl, array $http): string
    {
        $parts = ['Backend yanıt vermedi: ' . $testUrl];
        if ($http['code'] > 0) {
            $parts[] = 'HTTP ' . $http['code'];
        }
        if ($http['error'] !== '') {
            $parts[] = $http['error'];
        }

        return implode(' — ', $parts);
    }

    /**
     * @return array{label: string, ok: bool, detail: string, critical?: bool}
     */
    private function check(string $label, bool $ok, string $detail, bool $critical = true): array
    {
        return [
            'label' => $label,
            'ok' => $ok,
            'detail' => $detail,
            'critical' => $critical,
        ];
    }
}
