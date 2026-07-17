<?php

declare(strict_types=1);

/**
 * Backend connectivity probes — same-server loopback, public HTTPS, internal .env URL.
 */
final class BackendConnectivityProbe
{
    public const API_PROBE_PATH = '/site_settings.php';

    /**
     * @param list<string> $extraHeaders
     * @return array{ok: bool, label: string, http: int, error: string, body: string}
     */
    public static function curl(string $url, array $extraHeaders = [], int $timeout = 8): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'label' => $url, 'http' => 0, 'error' => 'curl_missing', 'body' => ''];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $extraHeaders),
        ]);
        if (defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $bodyStr = is_string($body) ? $body : '';
        $ok = $http >= 200 && $http < 500;

        return [
            'ok' => $ok,
            'label' => $url,
            'http' => $http,
            'error' => $ok ? '' : ($err !== '' ? $err : 'http_' . $http),
            'body' => $bodyStr,
        ];
    }

    /**
     * Member API site_settings envelope (rejects frontend health.json false positives).
     *
     * @param array<string, mixed>|null $decoded
     */
    public static function isBackendSiteSettingsEnvelope(?array $decoded): bool
    {
        if (!is_array($decoded)) {
            return false;
        }
        if (isset($decoded['role']) && (string) $decoded['role'] === 'frontend') {
            return false;
        }
        if (isset($decoded['checks']) && is_array($decoded['checks'])) {
            return false;
        }
        $ok = !empty($decoded['success']) || (int) ($decoded['code'] ?? 0) === 200;
        if (!$ok) {
            return false;
        }
        $data = $decoded['data'] ?? null;
        if (!is_array($data)) {
            return false;
        }

        foreach (['site_name', 'site_title', 'logo', 'bakim_modu', 'meta_title', 'live_chat_enabled'] as $key) {
            if (array_key_exists($key, $data)) {
                return true;
            }
        }

        return $data !== [];
    }

    /**
     * @return list<string>
     */
    public static function loopbackOrigins(): array
    {
        $origins = ['http://127.0.0.1'];
        $custom = trim((string) (getenv('API_BACKEND_INTERNAL_LOOPBACK') ?: ''));
        if ($custom !== '' && !in_array($custom, $origins, true)) {
            array_unshift($origins, rtrim($custom, '/'));
        }

        return $origins;
    }

    /**
     * @return array{internal_base: string, internal_host: string}|null
     */
    public static function detectInternalConfig(string $backendHost = 'bo-backoffice.site'): ?array
    {
        $backendHost = trim($backendHost);
        if ($backendHost === '') {
            return null;
        }

        $hostHeader = ['Host: ' . $backendHost];
        foreach (self::loopbackOrigins() as $loopback) {
            $apiUrl = rtrim($loopback, '/') . '/api/v2' . self::API_PROBE_PATH;
            $probe = self::curl($apiUrl, $hostHeader, 6);
            if (!$probe['ok']) {
                continue;
            }
            $decoded = json_decode($probe['body'], true);
            if (!self::isBackendSiteSettingsEnvelope(is_array($decoded) ? $decoded : null)) {
                continue;
            }

            return [
                'internal_base' => rtrim($loopback, '/') . '/api/v2',
                'internal_host' => $backendHost,
            ];
        }

        return null;
    }

    /**
     * Install / runtime: backend API usable (not just ping.php).
     *
     * @return array{ok: bool, message: string, envelope?: array<string, mixed>, internal?: array{internal_base: string, internal_host: string}}
     */
    public static function verifyBackendForInstall(string $backendUrl): array
    {
        $backendUrl = rtrim(trim($backendUrl), '/');
        $backendHost = strtolower((string) (parse_url($backendUrl, PHP_URL_HOST) ?: ''));
        if ($backendHost === '') {
            return ['ok' => false, 'message' => 'Backend host çözümlenemedi.'];
        }

        // 1) Önce hızlı localhost loopback (aynı sunucu). Cloudflare hairpin'inden kaçınır:
        // public https://backend isteği aynı VM'de edge'e gidip döndüğü için yavaştır/timeout olur.
        $internal = self::detectInternalConfig($backendHost);
        // Split frontend kurulumunda internal URL yazılmaz — yalnızca erişilebilirlik mesajı.
        if ($internal !== null) {
            return [
                'ok' => true,
                'message' => 'Backend localhost üzerinden erişilebilir (aynı sunucu). Frontend .env loopback kullanmaz; api subdomain kullanılır.',
            ];
        }

        // 2) Üye API: api.* subdomain (split-deploy için doğru adres).
        require_once dirname(__DIR__) . '/app/Services/InstallEnvBuilder.php';
        $apiPublic = InstallEnvBuilder::resolveApiPublicBaseUrl($backendUrl);
        $apiProbe = self::curl($apiPublic . self::API_PROBE_PATH, [], 8);
        $apiDecoded = json_decode($apiProbe['body'], true);
        if ($apiProbe['ok'] && self::isBackendSiteSettingsEnvelope(is_array($apiDecoded) ? $apiDecoded : null)) {
            return [
                'ok' => true,
                'message' => 'Üye API OK (' . $apiPublic . ').',
            ];
        }

        // 3) Ayrı sunucu: public HTTPS ana backend host.
        $publicApi = $backendUrl . '/api/v2';
        $publicProbe = self::curl($publicApi . self::API_PROBE_PATH, [], 6);
        $publicDecoded = json_decode($publicProbe['body'], true);
        if ($publicProbe['ok'] && self::isBackendSiteSettingsEnvelope(is_array($publicDecoded) ? $publicDecoded : null)) {
            return [
                'ok' => true,
                'message' => 'Backend public HTTPS OK.',
            ];
        }

        // 4) Son çare: loopback ping (API henüz hazır değilse bile aynı sunucu doğrulanır).
        foreach (self::loopbackOrigins() as $loopback) {
            $ping = self::curl(rtrim($loopback, '/') . '/ping.php', ['Host: ' . $backendHost], 3);
            if ($ping['ok']) {
                return [
                    'ok' => true,
                    'message' => 'Backend ping OK — API henüz hazır olmayabilir. bo-backoffice.site/install kontrol edin.',
                ];
            }
        }

        return [
            'ok' => false,
            'message' => 'Backend erişilemiyor (public: '
                . ($publicProbe['error'] !== '' ? $publicProbe['error'] : 'fail')
                . '). Önce bo-backoffice.site/install tamamlayın.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function run(string $publicApiBase, string $backendHost = 'bo-backoffice.site'): array
    {
        $publicApiBase = rtrim($publicApiBase, '/');
        $hostHeader = ['Host: ' . $backendHost];
        $out = [
            'backend_host' => $backendHost,
            'backend_dns' => $backendHost !== '' ? @gethostbyname($backendHost) : '',
            'probes' => [],
        ];

        $publicProbe = self::curl($publicApiBase . self::API_PROBE_PATH, [], 5);
        $publicDecoded = json_decode($publicProbe['body'], true);
        $publicProbe['member_api'] = self::isBackendSiteSettingsEnvelope(is_array($publicDecoded) ? $publicDecoded : null) ? 'yes' : 'no';
        $out['probes']['public_https'] = $publicProbe;

        $internal = trim((string) (getenv('API_BACKEND_INTERNAL_BASE_URL') ?: ''));
        if ($internal !== '') {
            $internalHost = trim((string) (getenv('API_BACKEND_INTERNAL_HOST') ?: $backendHost));
            $headers = $internalHost !== '' ? ['Host: ' . $internalHost] : $hostHeader;
            $internalProbe = self::curl(rtrim($internal, '/') . self::API_PROBE_PATH, $headers);
            $internalDecoded = json_decode($internalProbe['body'], true);
            $internalProbe['member_api'] = self::isBackendSiteSettingsEnvelope(is_array($internalDecoded) ? $internalDecoded : null) ? 'yes' : 'no';
            $out['probes']['internal_env'] = $internalProbe;
        }

        foreach (self::loopbackOrigins() as $loopback) {
            $key = 'loopback_' . str_replace([':', '.', '/'], '_', $loopback);
            $apiUrl = rtrim($loopback, '/') . '/api/v2' . self::API_PROBE_PATH;
            $loopProbe = self::curl($apiUrl, $hostHeader, 6);
            $loopDecoded = json_decode($loopProbe['body'], true);
            $loopProbe['member_api'] = self::isBackendSiteSettingsEnvelope(is_array($loopDecoded) ? $loopDecoded : null) ? 'yes' : 'no';
            $out['probes'][$key] = $loopProbe;
            if ($loopProbe['ok'] && $loopProbe['member_api'] === 'yes') {
                $out['suggested_internal_base'] = rtrim($loopback, '/') . '/api/v2';
                break;
            }

            $ping = self::curl(rtrim($loopback, '/') . '/ping.php', $hostHeader, 4);
            $out['probes'][$key . '_ping'] = $ping;
        }

        $out['ok'] = self::isBackendUsable($out['probes']);

        if (!$out['ok'] && !empty($out['suggested_internal_base']) && empty($out['probes']['internal_env']['ok'])) {
            $out['hints'] = [
                'Cloudflare + ayni sunucu: .env dosyasina ekleyin (origin HTTP, public URL https):',
                'CLOUDFLARE_SSL=1',
                'ORIGIN_HTTP=1',
                'API_BACKEND_INTERNAL_BASE_URL=' . $out['suggested_internal_base'],
                'API_BACKEND_INTERNAL_HOST=' . $backendHost,
                'php deploy/aapanel/fix-cloudflare-env.php',
            ];
        } elseif (!$out['ok']) {
            $out['hints'] = [
                'Cloudflare SSL: origin HTTP (aaPanel Force HTTPS kapali), public .env https://',
                'php deploy/aapanel/fix-cloudflare-env.php',
                'Once bo-backoffice.site/install, sonra vegasroyalspin.com/install',
            ];
        }

        if ($out['backend_dns'] !== '' && (str_starts_with($out['backend_dns'], '104.') || str_starts_with($out['backend_dns'], '172.'))) {
            $out['hints'][] = 'backend_dns Cloudflare IP — internal URL kullanin veya DNS-only bekleyin';
        }

        return $out;
    }

    /**
     * @param array<string, array{ok?: bool, member_api?: string}> $probes
     */
    public static function isBackendUsable(array $probes): bool
    {
        foreach (['public_https', 'internal_env'] as $key) {
            if (!empty($probes[$key]['ok']) && ($probes[$key]['member_api'] ?? 'yes') === 'yes') {
                return true;
            }
        }
        foreach ($probes as $name => $probe) {
            if (!is_array($probe) || empty($probe['ok'])) {
                continue;
            }
            if (str_starts_with($name, 'loopback_') && !str_ends_with($name, '_ping') && ($probe['member_api'] ?? 'yes') === 'yes') {
                return true;
            }
        }

        return false;
    }
}
