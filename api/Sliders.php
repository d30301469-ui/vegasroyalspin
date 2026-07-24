<?php

/**
 * Hero slider verisi — önce yerel DB, sonra HTTP API (MemberApiPaths::SLIDERS, ApiBases, ApiEnvelope).
 */
final class ApiSliders
{
    private const CATEGORIES = [
        'home' => 'Home slider',
        'slots' => 'Slot slider',
        'live_casino' => 'Live casino slider',
        'bgaming' => 'BGaming slider',
    ];

    private const CATEGORY_ALIASES = [
        'slot' => 'slots',
        'slots' => 'slots',
        'slot_slider' => 'slots',
        'slots_slider' => 'slots',
        'casino' => 'slots',
        'live' => 'live_casino',
        'live_slider' => 'live_casino',
        'livecasino' => 'live_casino',
        'live-casino' => 'live_casino',
        'live_casino' => 'live_casino',
        'live_casino_slider' => 'live_casino',
        'bgaming' => 'bgaming',
        'b-gaming' => 'bgaming',
        'bgaming_slider' => 'bgaming',
        'home' => 'home',
        'home_slider' => 'home',
        'homepage' => 'home',
        'main' => 'home',
    ];

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return self::CATEGORIES;
    }

    public static function normalizeCategory(string $category): string
    {
        $category = strtolower(trim($category));
        $category = str_replace([' ', '.'], ['_', ''], $category);

        return self::CATEGORY_ALIASES[$category] ?? $category;
    }

    public static function normalizeSurface(string $surface): string
    {
        $surface = strtolower(trim($surface));

        return in_array($surface, ['desktop', 'mobile'], true) ? $surface : 'all';
    }

    public static function ensureStorage(): void
    {
        $pdo = self::pdo();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sliders (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                subtitle VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                description TEXT COLLATE utf8mb4_unicode_ci NULL,
                desktop_path VARCHAR(700) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                mobile_path VARCHAR(700) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                button_link VARCHAR(700) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `order` INT NOT NULL DEFAULT 0,
                category VARCHAR(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'home',
                status TINYINT(1) NOT NULL DEFAULT 1,
                start_date DATETIME NULL,
                end_date DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sliders_lookup (category, status, `order`),
                KEY idx_sliders_dates (start_date, end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        foreach ([
            'subtitle' => "ALTER TABLE sliders ADD COLUMN subtitle VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER title",
            'description' => "ALTER TABLE sliders ADD COLUMN description TEXT COLLATE utf8mb4_unicode_ci NULL AFTER subtitle",
            'desktop_path' => "ALTER TABLE sliders ADD COLUMN desktop_path VARCHAR(700) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER description",
            'mobile_path' => "ALTER TABLE sliders ADD COLUMN mobile_path VARCHAR(700) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER desktop_path",
            'button_link' => "ALTER TABLE sliders ADD COLUMN button_link VARCHAR(700) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER mobile_path",
            'order' => "ALTER TABLE sliders ADD COLUMN `order` INT NOT NULL DEFAULT 0 AFTER button_link",
            'category' => "ALTER TABLE sliders ADD COLUMN category VARCHAR(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'home' AFTER `order`",
            'status' => "ALTER TABLE sliders ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 1 AFTER category",
            'start_date' => "ALTER TABLE sliders ADD COLUMN start_date DATETIME NULL AFTER status",
            'end_date' => "ALTER TABLE sliders ADD COLUMN end_date DATETIME NULL AFTER start_date",
            'created_at' => "ALTER TABLE sliders ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER end_date",
            'updated_at' => "ALTER TABLE sliders ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        ] as $column => $sql) {
            if (!self::columnExists($pdo, $column)) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable) {
                    // Keep reads available on partially legacy schemas.
                }
            }
        }

        try {
            $pdo->exec('CREATE INDEX idx_sliders_lookup ON sliders (category, status, `order`)');
        } catch (Throwable) {
        }
        try {
            $pdo->exec('CREATE INDEX idx_sliders_dates ON sliders (start_date, end_date)');
        } catch (Throwable) {
        }

        self::ensureCategoryColumnSupportsBgaming($pdo);
    }

    /**
     * Legacy ENUM('home','live_casino','slots') → VARCHAR(80) veya ENUM'a bgaming ekler.
     */
    public static function ensureCategoryColumnSupportsBgaming(?PDO $pdo = null): void
    {
        if ($pdo === null) {
            if (class_exists('AdminDatabase', false)) {
                $pdo = AdminDatabase::pdo();
            } elseif (class_exists('ApiCmsRemote', false) && ApiCmsRemote::canUseLocalDatabase()) {
                $pdo = self::pdo();
            } else {
                return;
            }
        }

        try {
            $stmt = $pdo->query(
                "SELECT DATA_TYPE AS data_type, COLUMN_TYPE AS column_type
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'sliders'
                   AND COLUMN_NAME = 'category'
                 LIMIT 1"
            );
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            if (!is_array($row)) {
                return;
            }
            if (strtolower((string) ($row['data_type'] ?? '')) !== 'enum') {
                return;
            }

            try {
                $pdo->exec(
                    "ALTER TABLE sliders
                     MODIFY COLUMN category VARCHAR(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'home'"
                );

                return;
            } catch (Throwable $varcharError) {
                error_log('[ApiSliders] category VARCHAR migrate: ' . $varcharError->getMessage());
            }

            $columnType = (string) ($row['column_type'] ?? '');
            if (preg_match("/^enum\\((.+)\\)$/i", $columnType, $matches) !== 1) {
                return;
            }

            $values = [];
            foreach (str_getcsv($matches[1], ',', "'") as $raw) {
                $value = trim((string) $raw, " '\"");
                if ($value !== '') {
                    $values[] = $value;
                }
            }
            if (in_array('bgaming', $values, true)) {
                return;
            }
            $values[] = 'bgaming';
            $enumSql = implode(',', array_map(
                static fn (string $v): string => "'" . str_replace("'", "''", $v) . "'",
                $values
            ));
            $pdo->exec(
                "ALTER TABLE sliders
                 MODIFY COLUMN category ENUM({$enumSql}) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'home'"
            );
        } catch (Throwable $e) {
            error_log('[ApiSliders] ensureCategoryColumnSupportsBgaming: ' . $e->getMessage());
        }
    }

    /**
     * @return list<string>
     */
    private static function candidateBases(): array
    {
        $bases = ApiBases::forMemberApi();
        $apiOnly = function_exists('frontend_is_api_only') && frontend_is_api_only();
        if ($apiOnly && $bases !== []) {
            return [$bases[0]];
        }

        if (!$apiOnly && defined('SITE_URL') && SITE_URL !== '') {
            $siteBase = rtrim((string) SITE_URL, '/') . '/api/v2';
            if (!in_array($siteBase, $bases, true)) {
                array_unshift($bases, $siteBase);
            }
        }

        return $bases;
    }

    /**
     * Yerel maltabet.sliders tablosundan yayında olan kayıtları okur.
     *
     * @param array<string, string|int|float|bool|null> $query Örn. ['category' => 'home']
     * @return list<array<string, mixed>>
     */
    public static function fetchFromDatabase(array $query = []): array
    {
        if (!ApiCmsRemote::canUseLocalDatabase()) {
            return [];
        }

        try {
            $pdo = self::pdo();
            $columns = self::tableColumns($pdo);
            if ($columns === []) {
                return [];
            }
            $category = self::normalizeCategory((string) ($query['category'] ?? $query['page'] ?? ''));
            $surface = self::normalizeSurface((string) ($query['surface'] ?? 'all'));
            $today = date('Y-m-d');

            $where = [];
            if (isset($columns['status']) && isset($columns['is_active'])) {
                $where[] = "(LOWER(TRIM(CAST(status AS CHAR))) IN ('1', 'active', 'published', 'on', 'true') OR LOWER(TRIM(CAST(is_active AS CHAR))) IN ('1', 'active', 'published', 'on', 'true'))";
            } elseif (isset($columns['status'])) {
                $where[] = "LOWER(TRIM(CAST(status AS CHAR))) IN ('1', 'active', 'published', 'on', 'true')";
            } elseif (isset($columns['is_active'])) {
                $where[] = "LOWER(TRIM(CAST(is_active AS CHAR))) IN ('1', 'active', 'published', 'on', 'true')";
            }

            $params = [
                'today_start' => $today,
                'today_end' => $today,
            ];

            // Tarih aralığı: gün bazında (admin’de saat seçilse bile o gün boyunca yayında)
            if (isset($columns['start_date']) && isset($columns['starts_at'])) {
                $where[] = "(NULLIF(DATE(COALESCE(start_date, starts_at)), '0000-00-00') IS NULL OR DATE(COALESCE(start_date, starts_at)) <= :today_start)";
            } elseif (isset($columns['start_date'])) {
                $where[] = "(NULLIF(DATE(start_date), '0000-00-00') IS NULL OR DATE(start_date) <= :today_start)";
            } elseif (isset($columns['starts_at'])) {
                $where[] = "(NULLIF(DATE(starts_at), '0000-00-00') IS NULL OR DATE(starts_at) <= :today_start)";
            }
            if (isset($columns['end_date']) && isset($columns['ends_at'])) {
                $where[] = "(NULLIF(DATE(COALESCE(end_date, ends_at)), '0000-00-00') IS NULL OR DATE(COALESCE(end_date, ends_at)) >= :today_end)";
            } elseif (isset($columns['end_date'])) {
                $where[] = "(NULLIF(DATE(end_date), '0000-00-00') IS NULL OR DATE(end_date) >= :today_end)";
            } elseif (isset($columns['ends_at'])) {
                $where[] = "(NULLIF(DATE(ends_at), '0000-00-00') IS NULL OR DATE(ends_at) >= :today_end)";
            }

            $select = [
                isset($columns['id']) ? 'id' : '0 AS id',
                isset($columns['title']) ? 'title' : "'' AS title",
                isset($columns['subtitle']) ? 'subtitle' : "'' AS subtitle",
                isset($columns['description']) ? 'description' : "'' AS description",
                isset($columns['image_url']) ? 'image_url' : "'' AS image_url",
                isset($columns['desktop_path'])
                    ? 'desktop_path'
                    : (isset($columns['desktop_image_url'])
                        ? 'desktop_image_url AS desktop_path'
                        : "'' AS desktop_path"),
                isset($columns['mobile_image_url']) ? 'mobile_image_url' : "'' AS mobile_image_url",
                isset($columns['mobile_path'])
                    ? 'mobile_path'
                    : (isset($columns['mobile_image_url'])
                        ? 'mobile_image_url AS mobile_path'
                        : "'' AS mobile_path"),
                isset($columns['surface']) ? 'surface' : "'all' AS surface",
                isset($columns['link_url']) ? 'link_url' : "'' AS link_url",
                isset($columns['button_link'])
                    ? 'button_link'
                    : (isset($columns['slider_link'])
                        ? 'slider_link AS button_link'
                        : (isset($columns['link'])
                            ? '`link` AS button_link'
                            : "'' AS button_link")),
                isset($columns['sort_order']) ? 'sort_order' : '0 AS sort_order',
                isset($columns['order']) ? '`order`' : '0 AS `order`',
                isset($columns['category']) ? 'category' : "'' AS category",
            ];

            $whereSql = $where !== [] ? implode(' AND ', $where) : '1=1';

            $sql = 'SELECT ' . implode(', ', $select) . '
                    FROM sliders
                    WHERE ' . $whereSql . '
                    ORDER BY `order` ASC, id DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $list = self::mapRows(is_array($rows) ? $rows : [], $surface);
            if ($category === '') {
                return $list;
            }

            return array_values(array_filter(
                $list,
                static function (array $slider) use ($category): bool {
                    $sliderCategory = self::normalizeCategory((string) ($slider['category'] ?? ''));
                    if ($sliderCategory === '') {
                        return true;
                    }

                    return $sliderCategory === $category;
                }
            ));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function mapRows(array $rows, string $surface = 'all'): array
    {
        $sliders = [];
        $surface = self::normalizeSurface($surface);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $desktop = self::pickFirstNonEmptyString([
                $row['desktop_path'] ?? '',
                $row['desktop_image_url'] ?? '',
            ]);
            $mobile = self::pickFirstNonEmptyString([
                $row['mobile_path'] ?? '',
                $row['mobile_image_url'] ?? '',
            ]);
            $singleImage = trim((string) ($row['image_url'] ?? ''));
            $rowSurface = self::normalizeSurface((string) ($row['surface'] ?? 'all'));

            if ($singleImage !== '') {
                if ($rowSurface === 'mobile' && $mobile === '') {
                    $mobile = $singleImage;
                } elseif ($rowSurface === 'desktop' && $desktop === '') {
                    $desktop = $singleImage;
                } elseif ($rowSurface === 'all') {
                    if ($desktop === '') {
                        $desktop = $singleImage;
                    }
                    if ($mobile === '') {
                        $mobile = $singleImage;
                    }
                }
            }

            if ($desktop === '' && $mobile === '') {
                continue;
            }
            $desktop = ApiMediaUrl::resolve($desktop);
            $mobile = ApiMediaUrl::resolve($mobile);
            $selected = $surface === 'mobile'
                ? ($mobile !== '' ? $mobile : $desktop)
                : ($desktop !== '' ? $desktop : $mobile);
            $sliders[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'subtitle' => (string) ($row['subtitle'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'category' => self::normalizeCategory((string) ($row['category'] ?? '')),
                'order' => (int) ($row['sort_order'] ?? $row['order'] ?? 0),
                'desktopImageUrl' => $desktop,
                'mobileImageUrl' => $mobile,
                'imageUrl' => $selected,
                'surface' => $surface,
                'sliderLink' => (string) ($row['link_url'] ?? $row['button_link'] ?? $row['slider_link'] ?? $row['link'] ?? ''),
            ];
        }

        return $sliders;
    }

    /**
     * @param array<string, string|int|float|bool|null> $query Örn. ['category' => 'home']
     * @return list<array<string, mixed>>
     */
    public static function fetch(array $query): array
    {
        $surface = self::normalizeSurface((string) ($query['surface'] ?? 'all'));
        $list = self::fetchRaw($query);

        if ($surface === 'all') {
            return $list;
        }

        $surfaceAdjusted = self::alignSurfacePayload($list, $surface);
        if (self::hasUsableMediaForSurface($surfaceAdjusted, $surface)) {
            return $surfaceAdjusted;
        }

        $fallbackQuery = $query;
        unset($fallbackQuery['surface']);
        $fallback = self::fetchRaw($fallbackQuery);
        $fallbackAdjusted = self::alignSurfacePayload($fallback, $surface);
        if (self::hasUsableMediaForSurface($fallbackAdjusted, $surface)) {
            return $fallbackAdjusted;
        }

        return $surfaceAdjusted;
    }

    /**
     * @param array<string, string|int|float|bool|null> $query
     * @return list<array<string, mixed>>
     */
    private static function fetchRaw(array $query): array
    {
        $apiOnly = function_exists('frontend_is_api_only') && frontend_is_api_only();
        $local = $apiOnly ? [] : self::fetchFromDatabase($query);
        if (!$apiOnly && (defined('METROPOL_ADMIN_PANEL') && METROPOL_ADMIN_PANEL)) {
            // Admin/backend host: slider kaynagi her zaman lokal DB olsun.
            // Remote fallback yeni admin kayitlarini stale upstream ile ezmesin.
            return $local;
        }
        if (!$apiOnly && $local !== []) {
            return $local;
        }

        $cacheKey = ApiCmsRemote::cacheKey('sliders', $query);
        $timeout = function_exists('frontend_cms_http_timeout')
            ? frontend_cms_http_timeout()
            : (function_exists('frontend_remote_http_timeout') ? frontend_remote_http_timeout() : 12);

        if ($apiOnly) {
            $cached = ApiCmsRemote::readPayloadCache($cacheKey, ApiCmsRemote::cacheFreshTtl(), false);
            if (is_array($cached['sliders'] ?? null)) {
                ApiCmsRemote::recordFetch($cacheKey, 'cache');

                return self::resolveSliderList($cached['sliders']);
            }
        }

        if (function_exists('metropol_should_skip_remote_backend') && metropol_should_skip_remote_backend()) {
            if ($apiOnly) {
                $stale = ApiCmsRemote::readPayloadCache($cacheKey, ApiCmsRemote::cacheStaleMaxAge(), true);
                if (is_array($stale['sliders'] ?? null)) {
                    ApiCmsRemote::recordFetch($cacheKey, 'stale');

                    return self::resolveSliderList($stale['sliders']);
                }
                ApiCmsRemote::recordFetch($cacheKey, 'skipped');
            }

            return $local;
        }

        $paths = ['/content/sliders', '/sliders.php'];
        foreach (self::candidateBases() as $base) {
            foreach ($paths as $path) {
                $api = ApiClient::getWithBase($base, $path, $query, $timeout);
                $parsed = ApiEnvelope::listFromData($api, 'sliders');
                if ($parsed !== null) {
                    if ($apiOnly) {
                        ApiCmsRemote::writePayloadCache($cacheKey, ['sliders' => $parsed], $query);
                        ApiCmsRemote::recordFetch($cacheKey, 'remote');
                    }

                    return self::resolveSliderList($parsed);
                }
            }
        }

        if ($apiOnly) {
            $stale = ApiCmsRemote::readPayloadCache($cacheKey, ApiCmsRemote::cacheStaleMaxAge(), true);
            if (is_array($stale['sliders'] ?? null)) {
                ApiCmsRemote::recordFetch($cacheKey, 'stale');

                return self::resolveSliderList($stale['sliders']);
            }

            $loopback = self::fetchViaFrontendLoopback($query);
            if (is_array($loopback) && $loopback !== []) {
                ApiCmsRemote::writePayloadCache($cacheKey, ['sliders' => $loopback], $query);
                ApiCmsRemote::recordFetch($cacheKey, 'loopback');
                if (function_exists('metropol_cms_api_mark_success')) {
                    metropol_cms_api_mark_success();
                }

                return self::resolveSliderList($loopback);
            }

            ApiCmsRemote::recordFetch($cacheKey, 'failed');
            if (function_exists('metropol_cms_api_mark_failure')) {
                metropol_cms_api_mark_failure();
            }

            return $local;
        }

        $fromMember = ApiMemberApi::firstSuccessfulMemberPath(
            self::candidateBases(),
            MemberApiPaths::SLIDERS,
            static function (string $base, string $path) use ($query): ?array {
                $api    = ApiClient::getWithBase($base, $path, $query, 5);
                $parsed = ApiEnvelope::listFromData($api, 'sliders');

                return $parsed;
            },
            static fn (mixed $r): bool => is_array($r) && $r !== []
        );
        if (is_array($fromMember) && $fromMember !== []) {
            return array_map(
                static fn (array $row): array => ApiMediaUrl::resolveSliderRow($row),
                $fromMember
            );
        }

        if (defined('API_BACKEND_MAIN_BASE_URL') && API_BACKEND_MAIN_BASE_URL !== '') {
            $legacy = ApiClient::mainGet('/content/sliders', $query, 5);
            $parsed = ApiEnvelope::listFromData($legacy, 'sliders');
            if ($parsed !== null) {
                return array_map(
                    static fn (array $row): array => ApiMediaUrl::resolveSliderRow($row),
                    $parsed
                );
            }
        }

        return $local;
    }

    /**
     * API-only frontend: SSR slider fetch via same-host Apache loopback + frontend proxy.
     *
     * @param array<string, string|int|float|bool|null> $query
     * @return list<array<string, mixed>>|null
     */
    private static function fetchViaFrontendLoopback(array $query): ?array
    {
        if (!function_exists('frontend_is_api_only') || !frontend_is_api_only()) {
            return null;
        }
        if (!function_exists('curl_init')) {
            return null;
        }

        $host = strtolower((string) (parse_url(defined('SITE_URL') ? (string) SITE_URL : '', PHP_URL_HOST) ?: ''));
        if ($host === '' && function_exists('deploy_domain')) {
            $host = strtolower((string) (parse_url(deploy_domain('frontend_url'), PHP_URL_HOST) ?: ''));
        }
        if ($host === '') {
            return null;
        }

        $qs = $query !== [] ? '?' . http_build_query($query) : '';
        foreach (['/api/v2/content/sliders', '/api/v2/sliders.php', '/api/sliders'] as $loopbackPath) {
            $ch = curl_init('http://127.0.0.1' . $loopbackPath . $qs);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_HTTPHEADER => ['Host: ' . $host, 'Accept: application/json'],
            ]);
            if (defined('CURL_IPRESOLVE_V4')) {
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            }
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!is_string($body) || $body === '' || $code !== 200) {
                continue;
            }

            $decoded = json_decode($body, true);
            $parsed = ApiEnvelope::listFromData(is_array($decoded) ? $decoded : null, 'sliders');
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>>|mixed $list
     * @return list<array<string, mixed>>
     */
    private static function resolveSliderList(mixed $list): array
    {
        if (!is_array($list)) {
            return [];
        }

        return array_map(
            static fn (array $row): array => ApiMediaUrl::resolveSliderRow($row),
            array_values(array_filter($list, 'is_array'))
        );
    }

    /**
     * Yalnızca belirtilen kategorideki yayında slider'ları döndürür (fallback yok).
     *
     * @return list<array<string, mixed>>
     */
    public static function fetchForCategory(string $category): array
    {
        $category = self::normalizeCategory($category);
        if ($category === '') {
            return [];
        }

        $surface = 'desktop';
        if ((defined('SURFACE') && SURFACE === 'mobile') || (isset($_SERVER['HTTP_HOST']) && strpos(strtolower((string) $_SERVER['HTTP_HOST']), 'm.') === 0)) {
            $surface = 'mobile';
        }

        $list = self::fetch(['category' => $category, 'surface' => $surface]);

        return array_values(array_filter(
            $list,
            static function (array $slider) use ($category): bool {
                $sliderCategory = self::normalizeCategory((string) ($slider['category'] ?? ''));
                if ($sliderCategory === '') {
                    return true;
                }

                return $sliderCategory === $category;
            }
        ));
    }

    /**
     * @deprecated Kategori fallback kaldırıldı — fetchForCategory() kullanın.
     * @return list<array<string, mixed>>
     */
    public static function fetchWithCategoryFallback(string $category = 'home'): array
    {
        return self::fetchForCategory($category);
    }

    private static function pdo(): PDO
    {
        return ApiCmsRemote::pdo();
    }

    /**
     * @param list<mixed> $values
     */
    private static function pickFirstNonEmptyString(array $values): string
    {
        foreach ($values as $value) {
            $candidate = trim((string) $value);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private static function hasUsableMediaForSurface(array $items, string $surface): bool
    {
        $surface = self::normalizeSurface($surface);
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $desktop = self::pickFirstNonEmptyString([
                $item['desktopImageUrl'] ?? '',
                $item['desktop_image_url'] ?? '',
                $item['desktop_path'] ?? '',
            ]);
            $mobile = self::pickFirstNonEmptyString([
                $item['mobileImageUrl'] ?? '',
                $item['mobile_image_url'] ?? '',
                $item['mobile_path'] ?? '',
            ]);
            $selected = self::pickFirstNonEmptyString([
                $item['imageUrl'] ?? '',
                $item['image_url'] ?? '',
            ]);

            if ($surface === 'mobile' && ($mobile !== '' || $selected !== '')) {
                return true;
            }
            if ($surface === 'desktop' && ($desktop !== '' || $selected !== '')) {
                return true;
            }
            if ($surface === 'all' && ($desktop !== '' || $mobile !== '' || $selected !== '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private static function alignSurfacePayload(array $items, string $surface): array
    {
        $surface = self::normalizeSurface($surface);
        if ($surface === 'all') {
            return $items;
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $desktop = self::pickFirstNonEmptyString([
                $item['desktopImageUrl'] ?? '',
                $item['desktop_image_url'] ?? '',
                $item['desktop_path'] ?? '',
            ]);
            $mobile = self::pickFirstNonEmptyString([
                $item['mobileImageUrl'] ?? '',
                $item['mobile_image_url'] ?? '',
                $item['mobile_path'] ?? '',
            ]);
            $selected = self::pickFirstNonEmptyString([
                $item['imageUrl'] ?? '',
                $item['image_url'] ?? '',
            ]);

            if ($surface === 'mobile') {
                if ($mobile === '') {
                    $mobile = $selected;
                }
                if ($selected === '') {
                    $selected = $mobile !== '' ? $mobile : $desktop;
                }
            } else {
                if ($desktop === '') {
                    $desktop = $selected;
                }
                if ($selected === '') {
                    $selected = $desktop !== '' ? $desktop : $mobile;
                }
            }

            if ($desktop === '' && $mobile === '' && $selected === '') {
                continue;
            }

            $item['desktopImageUrl'] = ApiMediaUrl::resolve($desktop);
            $item['mobileImageUrl'] = ApiMediaUrl::resolve($mobile);
            $item['imageUrl'] = ApiMediaUrl::resolve($selected);
            $item['surface'] = $surface;
            $normalized[] = $item;
        }

        return $normalized;
    }

    private static function columnExists(PDO $pdo, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $stmt->execute([
            'table' => 'sliders',
            'column' => $column,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return array<string, bool>
     */
    private static function tableColumns(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM sliders');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $columns = [];
            foreach ($rows as $row) {
                $name = strtolower((string) ($row['Field'] ?? ''));
                if ($name !== '') {
                    $columns[$name] = true;
                }
            }

            return $columns;
        } catch (Throwable) {
            return [];
        }
    }
}
