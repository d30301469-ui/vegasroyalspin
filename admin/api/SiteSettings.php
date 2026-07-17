<?php

/**
 * Public GET site_settings — backend yolları sırayla denenir (site_settings.php, site-settings, …).
 */
final class ApiSiteSettings
{
    /** @var list<string> */
    private const PATH_CANDIDATES = [
        '/site_settings.php',
        '/site-settings',
        '/site-settings.php',
    ];

    /**
     * Ham backend zarfı; tüm aday yollar başarısızsa null.
     *
     * @return array<string, mixed>|null
     */
    public static function fetchRawEnvelope(int $timeout = 12): ?array
    {
        if (function_exists('metropol_should_skip_remote_backend') && metropol_should_skip_remote_backend()) {
            return null;
        }

        $timeout = max(1, min(30, $timeout));
        if (function_exists('frontend_remote_http_timeout') && function_exists('frontend_is_api_only') && frontend_is_api_only()) {
            $timeout = min($timeout, frontend_remote_http_timeout());
        }

        $candidates = self::PATH_CANDIDATES;
        if (function_exists('frontend_is_api_only') && frontend_is_api_only()) {
            $candidates = [self::PATH_CANDIDATES[0]];
        }

        $deadline = microtime(true) + $timeout;
        $pathCount = max(1, count($candidates));

        foreach ($candidates as $path) {
            $remaining = (int) max(1, ceil($deadline - microtime(true)));
            if ($remaining <= 0) {
                break;
            }
            $pathTimeout = min($remaining, max(2, (int) ceil($timeout / $pathCount)));
            $res = BackendApiClient::request('GET', BackendApiClient::SVC_MAIN, $path, [], null, $pathTimeout);
            if (!is_array($res)) {
                continue;
            }
            $ok = !empty($res['success']);
            $code = isset($res['code']) ? (int) $res['code'] : 0;
            if ($ok || $code === 200) {
                if (function_exists('metropol_cms_api_mark_success')) {
                    metropol_cms_api_mark_success();
                }

                return $res;
            }
        }

        if (function_exists('metropol_cms_api_mark_failure')) {
            metropol_cms_api_mark_failure();
        }

        return null;
    }

    /**
     * Split frontend: cache envelope; backend unreachable ise stale cache kullan.
     *
     * @return array<string, mixed>|null
     */
    public static function fetchRawEnvelopeWithCache(int $timeout = 12, int $cacheTtl = 120): ?array
    {
        $useCache = function_exists('frontend_is_api_only') && frontend_is_api_only();
        if ($useCache) {
            $fresh = self::readCachedEnvelope($cacheTtl);
            if ($fresh !== null) {
                return $fresh;
            }

            // Single-flight: bu bootstrap'taki ilk bloklayan çağrı. TTL dolduğunda
            // her istek backend'i beklemesin; stale kopya varsa ve başka bir worker
            // zaten yeniliyorsa anında stale döndür (TTFB birikmesini önler).
            $stalePreview = self::readCachedEnvelope(86400, true);
            if ($stalePreview !== null && !self::acquireRefreshLock($timeout)) {
                return $stalePreview;
            }
        }

        $envelope = self::fetchRawEnvelope($timeout);
        if ($envelope !== null) {
            if ($useCache) {
                self::writeCachedEnvelope($envelope);
            }

            return $envelope;
        }

        if ($useCache) {
            return self::readCachedEnvelope(86400, true);
        }

        return null;
    }

    /**
     * Single-flight guard (stale-while-revalidate). True → bu istek yenilemeli;
     * false → başka worker yeniliyor, çağıran stale döndürmeli.
     */
    private static function acquireRefreshLock(int $timeout): bool
    {
        $path = self::cacheFilePath() . '.refresh.lock';
        $lockTtl = max(5, min(30, $timeout + 3));
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
     * @return array<string, mixed>|null
     */
    private static function readCachedEnvelope(int $maxAgeSeconds, bool $allowStale = false): ?array
    {
        $path = self::cacheFilePath();
        if (!is_readable($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload) || !is_array($payload['envelope'] ?? null)) {
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

        return $payload['envelope'];
    }

    /**
     * @param array<string, mixed> $envelope
     */
    private static function writeCachedEnvelope(array $envelope): void
    {
        $path = self::cacheFilePath();
        if (!self::canWriteCacheFile($path)) {
            return;
        }
        $payload = json_encode([
            'saved_at' => time(),
            'envelope' => $envelope,
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

    /**
     * Kurulum sonrası ilk sayfa yüklemesinde backend beklemesini önlemek için önbellek doldur.
     *
     * @param array<string, mixed> $envelope
     */
    public static function seedInstallCache(array $envelope): void
    {
        if ($envelope === []) {
            return;
        }
        self::writeCachedEnvelope($envelope);
    }

    /**
     * Ayar zarfı önbelleğini (ve tazeleme kilidini) siler. Admin logo/branding
     * güncellemesi sonrası frontend'in bir sonraki istekte API'den taze veri
     * çekmesini garanti eder; aksi halde stale zarf servis edilmeye devam eder.
     */
    public static function purgeCache(): void
    {
        foreach ([self::cacheFilePath(), self::cacheFilePath() . '.refresh.lock'] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private static function cacheFilePath(): string
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__);

        return rtrim(str_replace('\\', '/', $base), '/') . '/storage/cache/site_settings_envelope.json';
    }

    /**
     * Bootstrap / meta için düz dizi: data.site_settings veya düz data.
     *
     * @return array<string, mixed>
     */
    public static function normalizeAyarFromEnvelope(?array $envelope): array
    {
        if ($envelope === null) {
            return [];
        }
        $data = BackendApiClient::unwrap($envelope);
        if (!is_array($data)) {
            return [];
        }
        if (isset($data['site_settings']) && is_array($data['site_settings'])) {
            $payload = $data['site_settings'];
        } else {
            $payload = $data;
        }

        // Farkli backend surumlerinde ayarlar branding/meta altinda gelebilir.
        // Duz contract'a mapleyip title/logo'nun defaulta dusmesini engeller.
        $branding = is_array($data['branding'] ?? null) ? $data['branding'] : [];
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        if (!isset($payload['site_adi']) || trim((string) $payload['site_adi']) === '') {
            $payload['site_adi'] = (string) ($payload['site_name'] ?? $branding['site_name'] ?? '');
        }
        if (!isset($payload['site_aciklama']) || trim((string) $payload['site_aciklama']) === '') {
            $payload['site_aciklama'] = (string) ($payload['description'] ?? $branding['description'] ?? $meta['description'] ?? '');
        }
        if (!isset($payload['logo_url']) || trim((string) $payload['logo_url']) === '') {
            $payload['logo_url'] = (string) ($payload['site_logo'] ?? $branding['logo_url'] ?? '');
        }
        if (!isset($payload['favicon_url']) || trim((string) $payload['favicon_url']) === '') {
            $payload['favicon_url'] = (string) ($branding['favicon_url'] ?? '');
        }
        if (!isset($payload['manifest_url']) || trim((string) $payload['manifest_url']) === '') {
            $payload['manifest_url'] = (string) ($branding['manifest_url'] ?? '');
        }
        if (!isset($payload['og_image_url']) || trim((string) $payload['og_image_url']) === '') {
            $payload['og_image_url'] = (string) ($branding['og_image_url'] ?? '');
        }
        if (!isset($payload['meta_title']) || trim((string) $payload['meta_title']) === '') {
            $payload['meta_title'] = (string) ($meta['title'] ?? $payload['site_title'] ?? '');
        }
        if (!isset($payload['site_keywords']) || trim((string) $payload['site_keywords']) === '') {
            $payload['site_keywords'] = (string) ($meta['keywords'] ?? '');
        }
        if (!isset($payload['robots']) || trim((string) $payload['robots']) === '') {
            $payload['robots'] = (string) ($meta['robots'] ?? '');
        }
        if (!isset($payload['language']) || trim((string) $payload['language']) === '') {
            $payload['language'] = (string) ($meta['language'] ?? '');
        }
        if (!isset($payload['theme_color']) || trim((string) $payload['theme_color']) === '') {
            $payload['theme_color'] = (string) ($meta['theme_color'] ?? '');
        }

        $turnstileEnabled = !in_array(strtolower(trim((string) ($payload['turnstile_enabled'] ?? '0'))), ['0', '', 'false', 'off', 'no'], true);
        $turnstileSiteKey = trim((string) ($payload['turnstile_site_key'] ?? ''));
        unset($payload['turnstile_secret_key']);
        $payload['turnstile_enabled'] = $turnstileEnabled ? 1 : 0;
        $payload['turnstile_site_key'] = $turnstileSiteKey;

        if (class_exists('ApiMediaUrl', false)) {
            $rewritten = ApiMediaUrl::rewriteDeep($payload);
            return is_array($rewritten) ? $rewritten : $payload;
        }

        return $payload;
    }

    public static function ensureStorage(): void
    {
        if (!class_exists('AdminDatabase', false)) {
            return;
        }

        $pdo = AdminDatabase::pdo();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `site_ayarlar` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `site_adi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'VegasRoyalSpin',
                `site_aciklama` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT 'Güvenilir casino ve bahis',
                `bakim_modu` tinyint(1) NOT NULL DEFAULT 0,
                `logo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `favicon_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        foreach (self::managedColumns() as $name => $definition) {
            if (!self::columnExists($pdo, $name)) {
                $pdo->exec('ALTER TABLE `site_ayarlar` ADD COLUMN `' . str_replace('`', '``', $name) . '` ' . $definition);
            }
        }

        $count = (int) $pdo->query('SELECT COUNT(*) FROM `site_ayarlar`')->fetchColumn();
        if ($count === 0) {
            $pdo->exec("INSERT INTO `site_ayarlar` (`site_adi`, `site_aciklama`) VALUES ('VegasRoyalSpin', 'Güvenilir casino ve bahis')");
        }
    }

    /**
     * @return array<string, string>
     */
    public static function normalizeContactLinks(array $settings): array
    {
        $defaults = [
            'partnership_label' => 'ORTAKLIK',
            'partnership_title' => 'Ortaklık',
            'partnership_url' => '/ortaklik',
            'live_support_title' => 'Canlı Destek',
            'live_support_url' => self::frontendUrlEnv('LIVE_SUPPORT_URL', 'https://direct.lc.chat/19769276/'),
            'callback_url' => '/beni-ara',
            'callback_widget_text' => 'Dolandırıcılara geçit verme! Size ulaşan numara bize mi ait tıkla!',
            'contact_phone' => '',
            'whatsapp_url' => self::frontendUrlEnv('WHATSAPP_URL', ''),
            'telegram_url' => self::frontendUrlEnv('TELEGRAM_URL', 'https://t.me'),
        ];

        foreach ($defaults as $key => $default) {
            $value = trim((string) ($settings[$key] ?? ''));
            $defaults[$key] = $value !== '' ? $value : $default;
        }

        // Kullanıcıya yönelik navigasyon linkleri yanlışlıkla backend/admin host'una
        // kaydedilmişse frontend host'una çevir (örn. callback_url -> /beni-ara sayfası
        // frontend'de açılmalı, admin panelinde değil). Harici/3. taraf hostlara dokunmaz.
        foreach (['callback_url', 'partnership_url'] as $linkKey) {
            $defaults[$linkKey] = self::rewriteToFrontendHost((string) $defaults[$linkKey], $settings);
        }

        return $defaults;
    }

    /**
     * Verilen mutlak URL'in host'u, ayarlardaki backend/admin host'una eşitse
     * origin'i frontend host'una çevirir (path/query korunur). Boş veya göreli
     * değerler ile farklı (harici) host'lar olduğu gibi bırakılır.
     */
    private static function rewriteToFrontendHost(string $value, array $settings): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('#^https?://#i', $value) !== 1) {
            return $value;
        }

        $frontend = trim((string) ($settings['frontend_url'] ?? ''));
        if ($frontend === '' || preg_match('#^https?://#i', $frontend) !== 1) {
            return $value;
        }

        $valueHost = parse_url($value, PHP_URL_HOST);
        if (!is_string($valueHost) || $valueHost === '') {
            return $value;
        }

        $backendHosts = [];
        foreach (['backend_url', 'backend_api_base_url'] as $key) {
            $host = parse_url((string) ($settings[$key] ?? ''), PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $backendHosts[$host] = true;
            }
        }

        if (!isset($backendHosts[$valueHost]) && !self::isStaleDevHost($valueHost)) {
            return $value;
        }

        $path = (string) (parse_url($value, PHP_URL_PATH) ?? '');
        $query = parse_url($value, PHP_URL_QUERY);
        $fragment = parse_url($value, PHP_URL_FRAGMENT);

        return rtrim($frontend, '/')
            . ($path !== '' ? '/' . ltrim($path, '/') : '')
            . (is_string($query) && $query !== '' ? '?' . $query : '')
            . (is_string($fragment) && $fragment !== '' ? '#' . $fragment : '');
    }

    private static function isStaleDevHost(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return $host !== '';
        }
        if (str_ends_with($host, '.test') || str_ends_with($host, '.local')) {
            return true;
        }

        return in_array($host, function_exists('deploy_stale_url_hosts') ? deploy_stale_url_hosts() : [], true);
    }

    /**
     * Gömülü LiveChat widget yapılandırması.
     * Lisans önce açık alandan (live_chat_license), yoksa live_support_url
     * içindeki direct.lc.chat/<id> veya chat-with/<id> kalıbından çıkarılır.
     *
     * @return array{enabled: bool, license: string}
     */
    public static function liveChatConfig(array $settings): array
    {
        $enabled = !array_key_exists('live_chat_enabled', $settings)
            || !in_array((string) $settings['live_chat_enabled'], ['0', '', 'false', 'off', 'no'], true);

        $license = preg_replace('/\D+/', '', (string) ($settings['live_chat_license'] ?? '')) ?? '';
        if ($license === '') {
            $url = trim((string) ($settings['live_support_url'] ?? ''));
            if ($url !== '' && preg_match('~(?:direct\.lc\.chat|chat-with)/(\d+)~', $url, $m) === 1) {
                $license = $m[1];
            }
        }

        return [
            'enabled' => $enabled && $license !== '',
            'license' => $license,
        ];
    }

    /**
     * Frontend tarafına verilecek tekil, public site ayarları sözleşmesi.
     *
     * @return array<string, mixed>
     */
    public static function normalizePublicSettings(array $settings): array
    {
        $siteName = trim((string) ($settings['site_adi'] ?? ''));
        $description = trim((string) ($settings['site_aciklama'] ?? ''));
        $logoUrl         = self::publicAssetUrl((string) ($settings['logo_url'] ?? ''), '');
        $logoAnimatedUrl = self::publicAssetUrl((string) ($settings['logo_animated_url'] ?? ''), '');
        $logoMobileUrl   = self::publicAssetUrl((string) ($settings['logo_mobile_url'] ?? ''), '');
        $logoDarkUrl     = self::publicAssetUrl((string) ($settings['logo_dark_url'] ?? ''), '');
        $logoFooterUrl   = self::publicAssetUrl((string) ($settings['logo_footer_url'] ?? ''), '');
        $faviconUrl = self::publicAssetUrl((string) ($settings['favicon_url'] ?? ''), '/assets/images/favicons/favicon.svg');
        $manifestUrl = self::publicAssetUrl((string) ($settings['manifest_url'] ?? ''), '/assets/images/favicons/site.webmanifest');
        $ogRaw = trim((string) ($settings['og_image_url'] ?? ''));
        $ogPath = $ogRaw !== '' ? (string) (parse_url($ogRaw, PHP_URL_PATH) ?? '') : '';
        // Host kökü ('/') veya boş değer gerçek bir görsel değildir; logoya düş.
        $ogImageUrl = ($ogRaw === '' || $ogPath === '' || $ogPath === '/')
            ? $logoUrl
            : self::publicAssetUrl($ogRaw, $logoUrl);

        $siteName = $siteName !== '' ? $siteName : 'VegasRoyalSpin';
        $description = $description !== '' ? $description : 'Güvenilir casino ve bahis';

        $resetHeroImageUrl = self::publicAssetUrl((string) ($settings['reset_password_hero_image_url'] ?? ''), '/assets/images/login-bg.png');
        $resetBrandText = trim((string) ($settings['reset_password_brand_text'] ?? ''));
        $resetTitleRequest = trim((string) ($settings['reset_password_title_request'] ?? ''));
        $resetTitleConfirm = trim((string) ($settings['reset_password_title_confirm'] ?? ''));
        $resetButtonText = trim((string) ($settings['reset_password_button_text'] ?? ''));
        $resetLeadText = trim((string) ($settings['reset_password_lead_text'] ?? ''));
        $resetInfoText = trim((string) ($settings['reset_password_info_text'] ?? ''));
        $resetModalBg = trim((string) ($settings['reset_password_modal_bg'] ?? ''));
        $resetHeroTopBorder = trim((string) ($settings['reset_password_hero_top_border_color'] ?? ''));
        $resetHeroBottomBorder = trim((string) ($settings['reset_password_hero_bottom_border_color'] ?? ''));
        $resetInputBorder = trim((string) ($settings['reset_password_input_border_color'] ?? ''));
        $resetButtonTextColor = trim((string) ($settings['reset_password_button_text_color'] ?? ''));

        $normalizedSettings = array_merge($settings, [
            'site_adi' => $siteName,
            'site_name' => $siteName,
            'site_aciklama' => $description,
            'logo_url' => $logoUrl,
            'site_logo' => $logoUrl,
            'favicon_url' => $faviconUrl,
            'manifest_url' => $manifestUrl,
            'og_image_url' => $ogImageUrl,
            'reset_password_hero_image_url' => $resetHeroImageUrl,
            'reset_password_brand_text' => $resetBrandText,
            'reset_password_title_request' => $resetTitleRequest,
            'reset_password_title_confirm' => $resetTitleConfirm,
            'reset_password_button_text' => $resetButtonText,
            'reset_password_lead_text' => $resetLeadText,
            'reset_password_info_text' => $resetInfoText,
            'reset_password_modal_bg' => $resetModalBg,
            'reset_password_hero_top_border_color' => $resetHeroTopBorder,
            'reset_password_hero_bottom_border_color' => $resetHeroBottomBorder,
            'reset_password_input_border_color' => $resetInputBorder,
            'reset_password_button_text_color' => $resetButtonTextColor,
        ]);

        $payload = array_merge($normalizedSettings, [
            'site_settings' => $normalizedSettings,
            'site_name' => $siteName,
            'site_logo' => $logoUrl,
            'branding' => [
                'site_name'         => $siteName,
                'description'       => $description,
                'logo_url'          => $logoUrl,
                'logo_animated_url' => $logoAnimatedUrl,
                'logo_mobile_url'   => $logoMobileUrl,
                'logo_dark_url'     => $logoDarkUrl,
                'logo_footer_url'   => $logoFooterUrl,
                'favicon_url'       => self::applyFaviconCacheBusting($faviconUrl),
                'manifest_url'      => $manifestUrl,
                'og_image_url'      => $ogImageUrl,
            ],
            'meta' => [
                'title' => trim((string) ($settings['meta_title'] ?? '')) !== ''
                    ? trim((string) ($settings['meta_title'] ?? ''))
                    : $siteName . ' - ' . $description,
                'description' => $description,
                'keywords' => trim((string) ($settings['site_keywords'] ?? '')),
                'robots' => trim((string) ($settings['robots'] ?? '')) !== '' ? trim((string) $settings['robots']) : 'index, follow',
                'language' => trim((string) ($settings['language'] ?? '')) !== '' ? trim((string) $settings['language']) : 'tr',
                'theme_color' => trim((string) ($settings['theme_color'] ?? '')) !== '' ? trim((string) $settings['theme_color']) : '#120023',
            ],
            'contact' => self::normalizeContactLinks($settings),
            'reset_password' => [
                'hero_image_url' => $resetHeroImageUrl,
                'brand_text' => $resetBrandText,
                'title_request' => $resetTitleRequest,
                'title_confirm' => $resetTitleConfirm,
                'button_text' => $resetButtonText,
                'lead_text' => $resetLeadText,
                'info_text' => $resetInfoText,
                'modal_bg' => $resetModalBg,
                'hero_top_border_color' => $resetHeroTopBorder,
                'hero_bottom_border_color' => $resetHeroBottomBorder,
                'input_border_color' => $resetInputBorder,
                'button_text_color' => $resetButtonTextColor,
            ],
            'live_chat' => self::liveChatConfig($settings),
            'flags' => [
                'maintenance_mode' => !empty($settings['bakim_modu']),
            ],
            'updated_at' => (string) ($settings['updated_at'] ?? ''),
        ]);
        $payload['site_title'] = (string) (($payload['meta']['title'] ?? '') ?: ($normalizedSettings['meta_title'] ?? ''));
        $payload['site_settings']['site_title'] = $payload['site_title'];
        self::ensureMediaUrl();
        if (class_exists('ApiMediaUrl', false)) {
            $rewritten = ApiMediaUrl::rewriteDeep($payload);
            if (is_array($rewritten)) {
                return $rewritten;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private static function managedColumns(): array
    {
        return [
            'partnership_label' => "varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT 'ORTAKLIK'",
            'partnership_title' => "varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT 'Ortaklık'",
            'partnership_url' => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '/ortaklik'",
            'live_support_title' => "varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT 'Canlı Destek'",
            'live_support_url' => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '" . self::sqlDefault(self::frontendUrlEnv('LIVE_SUPPORT_URL', 'https://direct.lc.chat/19769276/')) . "'",
            'live_chat_enabled' => "tinyint(1) NOT NULL DEFAULT 1",
            'live_chat_license' => "varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT '" . self::sqlDefault(self::frontendUrlEnv('LIVE_CHAT_LICENSE', '19769276')) . "'",
            'callback_url' => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '/beni-ara'",
            'callback_widget_text' => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT 'Dolandırıcılara geçit verme! Size ulaşan numara bize mi ait tıkla!'",
            'contact_phone' => "varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL",
            'whatsapp_url' => self::frontendUrlEnv('WHATSAPP_URL', '') !== ''
                ? "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '" . self::sqlDefault(self::frontendUrlEnv('WHATSAPP_URL', '')) . "'"
                : "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL",
            'telegram_url' => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '" . self::sqlDefault(self::frontendUrlEnv('TELEGRAM_URL', 'https://t.me')) . "'",
            'manifest_url' => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '/assets/images/favicons/site.webmanifest'",
            'og_image_url'      => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL",
            'logo_animated_url' => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL",
            'logo_mobile_url'   => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL",
            'logo_dark_url'     => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL",
            'logo_footer_url'   => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL",
            'meta_title' => "varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL",
            'site_keywords' => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL",
            'robots' => "varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT 'index, follow'",
            'language' => "varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'tr'",
            'theme_color' => "varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT '#120023'",
            'frontend_url' => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '" . self::sqlDefault(self::frontendUrlEnv('FRONTEND_FALLBACK_URL', self::deployDefault('frontend_fallback_url'))) . "'",
            'backend_url' => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '" . self::sqlDefault(self::frontendUrlEnv('BACKEND_FALLBACK_URL', self::deployDefault('backend_url'))) . "'",
            'backend_api_base_url' => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '" . self::sqlDefault(self::frontendUrlEnv('API_BACKEND_FALLBACK_BASE_URL', self::deployDefault('backend_api_base_url'))) . "'",
            'allowed_url_hosts' => "varchar(700) COLLATE utf8mb4_unicode_ci DEFAULT '" . self::sqlDefault(self::frontendUrlEnv('DEFAULT_ALLOWED_URL_HOSTS', self::deployDefault('default_allowed_url_hosts'))) . "'",
            'reset_password_hero_image_url' => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '/assets/images/login-bg.png'",
            'reset_password_brand_text' => "varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT 'Vegasroyalspin'",
            'reset_password_title_request' => "varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT 'ŞİFRE SIFIRLA'",
            'reset_password_title_confirm' => "varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT 'YENİ ŞİFRE'",
            'reset_password_button_text' => "varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT 'SIFIRLA'",
            'reset_password_lead_text' => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT 'Şifrenizi sıfırlamak için kayıtlı e-posta adresinizi giriniz.'",
            'reset_password_info_text' => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT 'Şifrenizi sıfırlamak için kayıtlı e-posta adresinizi giriniz.'",
            'reset_password_modal_bg' => "varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT 'linear-gradient(145deg, #1b0c49 0%, #0a0f3c 60%, #09123f 100%)'",
            'reset_password_hero_top_border_color' => "varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT '#7d1c7a'",
            'reset_password_hero_bottom_border_color' => "varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT '#ff00ff'",
            'reset_password_input_border_color' => "varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT '#ec46aa'",
            'reset_password_button_text_color' => "varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT '#d2d6eb'",
            'turnstile_enabled' => "tinyint(1) NOT NULL DEFAULT 0",
            'turnstile_site_key' => "varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL",
            'turnstile_secret_key' => "varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL",
        ];
    }

    private static function deployDefault(string $key): string
    {
        if (!function_exists('deploy_domain')) {
            $path = dirname(__DIR__) . '/config/deploy_domains.php';
            if (is_file($path)) {
                require_once $path;
            }
        }

        return function_exists('deploy_domain') ? deploy_domain($key) : '';
    }

    private static function frontendUrlEnv(string $key, string $default): string
    {
        if (defined($key)) {
            return trim((string) constant($key));
        }

        $value = getenv($key);
        if ($value === false || trim((string) $value) === '') {
            return $default;
        }

        return trim((string) $value);
    }

    private static function sqlDefault(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    private static function ensureMediaUrl(): void
    {
        if (class_exists('ApiMediaUrl', false)) {
            return;
        }
        if (is_readable(__DIR__ . '/MediaUrl.php')) {
            require_once __DIR__ . '/MediaUrl.php';
        }
    }

    private static function publicAssetUrl(string $value, string $default): string
    {
        self::ensureMediaUrl();
        $value = trim($value);
        if ($value === '') {
            return class_exists('ApiMediaUrl', false) ? ApiMediaUrl::resolve($default) : self::legacyPublicAssetUrl($default);
        }
        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $value)) {
            return class_exists('ApiMediaUrl', false) ? ApiMediaUrl::resolve($value) : self::rewriteStaleAbsoluteUrl($value);
        }

        return class_exists('ApiMediaUrl', false)
            ? ApiMediaUrl::resolve($value)
            : self::legacyPublicAssetUrl($value);
    }

    private static function rewriteStaleAbsoluteUrl(string $url): string
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host === '' || (!str_ends_with($host, '.test') && !str_ends_with($host, '.local') && !in_array($host, ['localhost', '127.0.0.1'], true))) {
            return $url;
        }
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        if ($path === '') {
            return '';
        }

        return $path;
    }

    private static function legacyPublicAssetUrl(string $value): string
    {
        return '/' . ltrim($value, '/');
    }

    private static function applyFaviconCacheBusting(string $url): string
    {
        // Only apply cache busting to local relative paths
        if (strpos($url, '://') !== false) {
            // Full URL - return as-is
            return $url;
        }
        
        // Relative path - apply cache busting
        $realPath = ltrim($url, '/');
        $fullPath = ADMIN_BASE_PATH . '/' . $realPath;
        
        if (file_exists($fullPath)) {
            $mtime = @filemtime($fullPath);
            if ($mtime !== false) {
                return $url . '?v=' . $mtime;
            }
        }
        
        return $url;
    }

    private static function columnExists(PDO $pdo, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $stmt->execute(['table' => 'site_ayarlar', 'column' => $column]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
