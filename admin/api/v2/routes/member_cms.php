<?php
/**
 * Üye API modülü — index.php tarafından include edilir.
 *
 * Variables injected by the including kernel (member_api_kernel.php).
 * The ??= assignments are no-ops when the file is properly included.
 *
 * @var string $method
 * @var string $route
 * @var array{query: array<string,mixed>, body: array<string,mixed>} $payload
 * @var \Closure(int, array<string,mixed>): void $memberEnvelope
 * @var \Closure(array<string,mixed>): array<string,mixed> $memberInput
 * @var \Closure(int, string, array<string,mixed>): void $error
 * @var \Closure(string): void $requirePermission
 * @var \Closure(array<string,mixed>): void $validateCsrf
 */

$method ??= 'GET';
$route ??= '';
$payload ??= ['query' => [], 'body' => []];
$memberEnvelope ??= static function (int $s, array $b): void { http_response_code($s); echo json_encode($b); exit; };
$memberInput ??= static fn (array $p): array => $p['body'] ?? [];
$error ??= static function (int $s, string $m, array $t = []): void { http_response_code($s); echo json_encode(['success' => false, 'code' => $s, 'message' => $m, 'meta' => $t]); exit; };
$requirePermission ??= static function (string $k): void {};
$validateCsrf ??= static function (array $p): void {};

if ($method === 'GET' && in_array($route, ['site_settings.php', 'site-settings', 'site-settings.php', 'config'], true)) {
    $pdo = AdminDatabase::pdo();
    admin_require_project_file('api/bootstrap.php');
    if (class_exists('ApiSiteSettings')) {
        ApiSiteSettings::ensureStorage();
    }
    $stmt = $pdo->query('SELECT * FROM site_ayarlar ORDER BY id ASC LIMIT 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $settings = is_array($row) ? $row : [];
    $publicSettings = class_exists('ApiSiteSettings') ? ApiSiteSettings::normalizePublicSettings($settings) : $settings;
    admin_require_project_file('api/MediaUrl.php');
    if (class_exists('ApiMediaUrl', false)) {
        ApiMediaUrl::ensureLoaded();
        $rewritten = ApiMediaUrl::rewriteDeep($publicSettings);
        if (is_array($rewritten)) {
            $publicSettings = $rewritten;
        }
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Site ayarları',
        'data' => $publicSettings !== [] ? $publicSettings : new stdClass(),
    ]);
}

if ($method === 'GET' && ($route === 'announcements.php' || $route === 'announcements')) {
    $pdo = AdminDatabase::pdo();
    $now = date('Y-m-d H:i:s');
    try {
        $stmt = $pdo->prepare("SELECT id, title, description, type, icon_type, priority, created_at
                               FROM announcements
                               WHERE is_active = 1
                                 AND (start_date IS NULL OR start_date <= :now_start)
                                 AND (end_date IS NULL OR end_date >= :now_end)
                               ORDER BY priority DESC, id DESC
                               LIMIT 100");
        $stmt->execute(['now_start' => $now, 'now_end' => $now]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $rows = str_contains($e->getMessage(), '42S02') ? [] : throw $e;
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Duyurular',
        'data' => ['announcements' => $rows],
    ]);
}

if ($method === 'GET' && $route === 'member_inbox_messages.php') {
    $pdo = AdminDatabase::pdo();
    $now = date('Y-m-d H:i:s');
    $viewerUserId = (isset($memberJwtOptionalUserId) && is_callable($memberJwtOptionalUserId))
        ? (int) $memberJwtOptionalUserId($pdo)
        : 0;
    try {
        $columnStmt = $pdo->query('SHOW COLUMNS FROM member_inbox_messages');
        $columns = $columnStmt !== false ? array_map(static fn (array $row): string => (string) ($row['Field'] ?? ''), $columnStmt->fetchAll(PDO::FETCH_ASSOC)) : [];
        $hasColumn = static fn (string $column): bool => in_array($column, $columns, true);
        $select = [
            $hasColumn('id') ? 'id' : '0 AS id',
            $hasColumn('title') ? 'title' : "'' AS title",
            $hasColumn('body') ? 'body' : "'' AS body",
            $hasColumn('link_url') ? 'link_url' : 'NULL AS link_url',
            $hasColumn('priority') ? 'priority' : '0 AS priority',
            $hasColumn('created_at') ? 'created_at' : 'NOW() AS created_at',
            $hasColumn('updated_at') ? 'updated_at' : ($hasColumn('created_at') ? 'created_at AS updated_at' : 'NOW() AS updated_at'),
        ];
        $where = [];
        $params = [];
        if ($hasColumn('is_active')) {
            $where[] = 'is_active = 1';
        }
        if ($hasColumn('starts_at')) {
            $where[] = '(starts_at IS NULL OR starts_at <= :now_start)';
            $params['now_start'] = $now;
        }
        if ($hasColumn('ends_at')) {
            $where[] = '(ends_at IS NULL OR ends_at >= :now_end)';
            $params['now_end'] = $now;
        }
        if ($viewerUserId > 0 && $hasColumn('user_id')) {
            $where[] = '(user_id IS NULL OR user_id = :viewer_user_id)';
            $params['viewer_user_id'] = $viewerUserId;
        } elseif ($hasColumn('user_id')) {
            $where[] = 'user_id IS NULL';
        }
        $sql = 'SELECT ' . implode(', ', $select) . ' FROM member_inbox_messages';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $order = [];
        if ($hasColumn('priority')) $order[] = 'priority DESC';
        if ($hasColumn('id')) $order[] = 'id DESC';
        if ($order !== []) $sql .= ' ORDER BY ' . implode(', ', $order);
        $sql .= ' LIMIT 100';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $rows = [];
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Mesajlar',
        'data' => ['messages' => $rows],
    ]);
}
if (in_array($method, ['GET', 'POST'], true) && in_array($route, ['track_visit.php', 'track-visit'], true)) {
    $pdo = AdminDatabase::pdo();

    // Run schema DDL only when the table is missing. The ALTER attempts below
    // take metadata locks on every visitor hit; skipping them when the table
    // already exists removes the heaviest cost on this high-traffic endpoint.
    $visitorTableReady = false;
    try {
        $visitorTableReady = $pdo->query("SHOW TABLES LIKE 'visitor_logs'")->fetchColumn() !== false;
    } catch (Throwable) {
        $visitorTableReady = false;
    }

    if (!$visitorTableReady) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS visitor_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                ip_address VARCHAR(64) NULL,
                country_code VARCHAR(8) NULL,
                country_name VARCHAR(100) NULL,
                region VARCHAR(120) NULL,
                city VARCHAR(120) NULL,
                lat DECIMAL(10,7) NULL,
                lon DECIMAL(10,7) NULL,
                user_agent VARCHAR(500) NULL,
                referer VARCHAR(500) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_visitor_logs_created_at (created_at),
                KEY idx_visitor_logs_country (country_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        foreach ([
            'ip' => 'ALTER TABLE visitor_logs ADD COLUMN ip VARCHAR(64) NULL',
            'ip_address' => 'ALTER TABLE visitor_logs ADD COLUMN ip_address VARCHAR(64) NULL',
            'country_code' => 'ALTER TABLE visitor_logs ADD COLUMN country_code VARCHAR(8) NULL',
            'country_name' => 'ALTER TABLE visitor_logs ADD COLUMN country_name VARCHAR(100) NULL',
            'region' => 'ALTER TABLE visitor_logs ADD COLUMN region VARCHAR(120) NULL',
            'city' => 'ALTER TABLE visitor_logs ADD COLUMN city VARCHAR(120) NULL',
            'lat' => 'ALTER TABLE visitor_logs ADD COLUMN lat DECIMAL(10,7) NULL',
            'lon' => 'ALTER TABLE visitor_logs ADD COLUMN lon DECIMAL(10,7) NULL',
            'user_agent' => 'ALTER TABLE visitor_logs ADD COLUMN user_agent VARCHAR(500) NULL',
            'referer' => 'ALTER TABLE visitor_logs ADD COLUMN referer VARCHAR(500) NULL',
            'created_at' => 'ALTER TABLE visitor_logs ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP',
        ] as $alterSql) {
            try {
                $pdo->exec($alterSql);
            } catch (Throwable) {
            }
        }
    }

    $ip = (string) ($_SERVER['HTTP_CLIENT_IP'] ?? '');
    if ($ip === '' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim((string) ($parts[0] ?? ''));
    }
    if ($ip === '') {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
    $input = $memberInput($payload);
    $countryCode = trim((string) ($input['country_code'] ?? $input['countryCode'] ?? ''));
    $countryName = trim((string) ($input['country_name'] ?? $input['country'] ?? ''));
    $region = trim((string) ($input['region'] ?? ''));
    $city = trim((string) ($input['city'] ?? ''));
    $lat = isset($input['lat']) ? (float) $input['lat'] : null;
    $lon = isset($input['lon']) ? (float) $input['lon'] : null;
    $stmt = $pdo->prepare(
        'INSERT INTO visitor_logs
        (ip, ip_address, country_code, country_name, region, city, lat, lon, user_agent, referer, created_at)
        VALUES
        (:ip, :ip_address, :country_code, :country_name, :region, :city, :lat, :lon, :user_agent, :referer, NOW())'
    );
    $stmt->execute([
        'ip' => $ip !== '' ? $ip : '0.0.0.0',
        'ip_address' => $ip !== '' ? $ip : null,
        'country_code' => $countryCode !== '' ? $countryCode : null,
        'country_name' => $countryName !== '' ? $countryName : null,
        'region' => $region !== '' ? $region : null,
        'city' => $city !== '' ? $city : null,
        'lat' => $lat,
        'lon' => $lon,
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        'referer' => substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500),
    ]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Ziyaret kaydedildi.',
        'data' => [
            'id' => (string) $pdo->lastInsertId(),
            'country' => $countryName,
            'countryCode' => $countryCode,
        ],
    ]);
}
if (in_array($method, ['GET', 'POST', 'PUT'], true) && ($route === 'content/footer' || $route === 'footer.php')) {
    try {
        admin_require_project_file('api/bootstrap.php');

        if ($method === 'GET') {
            $footer = ApiFooter::fetch();
            $memberEnvelope(200, [
                'success' => true,
                'code' => 200,
                'message' => 'Footer ayarları',
                'data' => ['footer' => $footer],
            ]);
        }

        $requirePermission('footer-settings');
        $validateCsrf($payload);

        $input = is_array($payload['body'] ?? null) ? $payload['body'] : [];
        $incoming = $input['payload'] ?? $input['footer'] ?? $input;
        if (is_string($incoming)) {
            $decoded = json_decode($incoming, true);
            $incoming = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($incoming)) {
            $error(422, 'Footer payload JSON formatında olmalıdır.');
        }
        unset($incoming['_token']);
        unset($incoming['name'], $incoming['is_active']);

        $current = ApiFooter::fetch();
        $footer = ApiFooter::normalize(array_replace($current, $incoming));
        $name = trim((string) ($input['name'] ?? 'default')) ?: 'default';
        $isActive = (int) ($input['is_active'] ?? 1) === 1 ? 1 : 0;

        $pdo = AdminDatabase::pdo();
        if ($isActive === 1) {
            $pdo->exec('UPDATE footer_settings SET is_active = 0');
        }
        $encodedPayload = json_encode($footer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encodedPayload)) {
            $error(422, 'Footer payload JSON olarak kaydedilemedi.');
        }
        $existing = $pdo->prepare('SELECT id FROM footer_settings WHERE name = :name ORDER BY id DESC LIMIT 1');
        $existing->execute(['name' => $name]);
        $existingId = (int) $existing->fetchColumn();
        if ($existingId > 0) {
            $stmt = $pdo->prepare('UPDATE footer_settings SET payload = :payload, is_active = :is_active WHERE id = :id');
            $stmt->execute([
                'payload' => $encodedPayload,
                'is_active' => $isActive,
                'id' => $existingId,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO footer_settings (name, payload, is_active) VALUES (:name, :payload, :is_active)');
            $stmt->execute([
                'name' => $name,
                'payload' => $encodedPayload,
                'is_active' => $isActive,
            ]);
        }

        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Footer ayarları güncellendi',
            'data' => ['footer' => $footer],
        ]);
    } catch (Throwable $exception) {
        $error(500, 'Footer verisi işlenemedi.', ['reason' => $exception->getMessage()]);
    }
}

if (in_array($method, ['GET', 'POST', 'PUT'], true) && ($route === 'content/mobile-menu' || $route === 'mobile-menu.php')) {
    try {
        admin_require_project_file('api/bootstrap.php');

        if ($method === 'GET') {
            $menu = ApiMobileMenu::fetch();
            $memberEnvelope(200, [
                'success' => true,
                'code' => 200,
                'message' => 'Mobil menü ayarları',
                'data' => ['mobile_menu' => $menu],
            ]);
        }

        $requirePermission('mobile-menu-settings');
        $validateCsrf($payload);

        $input = is_array($payload['body'] ?? null) ? $payload['body'] : [];
        $incoming = $input['payload'] ?? $input['mobile_menu'] ?? $input;
        if (is_string($incoming)) {
            $decoded = json_decode($incoming, true);
            $incoming = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($incoming)) {
            $error(422, 'Mobil menü payload JSON formatında olmalıdır.');
        }
        unset($incoming['_token']);
        unset($incoming['name'], $incoming['is_active']);

        $current = ApiMobileMenu::fetch();
        $menu = ApiMobileMenu::normalize(array_replace($current, $incoming));
        $name = trim((string) ($input['name'] ?? 'default')) ?: 'default';
        $isActive = (int) ($input['is_active'] ?? 1) === 1 ? 1 : 0;

        $pdo = AdminDatabase::pdo();
        if ($isActive === 1) {
            $pdo->exec('UPDATE mobile_menu_settings SET is_active = 0');
        }
        $encodedPayload = json_encode($menu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encodedPayload)) {
            $error(422, 'Mobil menü payload JSON olarak kaydedilemedi.');
        }
        $existing = $pdo->prepare('SELECT id FROM mobile_menu_settings WHERE name = :name ORDER BY id DESC LIMIT 1');
        $existing->execute(['name' => $name]);
        $existingId = (int) $existing->fetchColumn();
        if ($existingId > 0) {
            $stmt = $pdo->prepare('UPDATE mobile_menu_settings SET payload = :payload, is_active = :is_active WHERE id = :id');
            $stmt->execute([
                'payload' => $encodedPayload,
                'is_active' => $isActive,
                'id' => $existingId,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO mobile_menu_settings (name, payload, is_active) VALUES (:name, :payload, :is_active)');
            $stmt->execute([
                'name' => $name,
                'payload' => $encodedPayload,
                'is_active' => $isActive,
            ]);
        }

        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Mobil menü ayarları güncellendi',
            'data' => ['mobile_menu' => $menu],
        ]);
    } catch (Throwable $exception) {
        $error(500, 'Mobil menü verisi işlenemedi.', ['reason' => $exception->getMessage()]);
    }
}

if ($method === 'GET' && ($route === 'content/footer-pages' || $route === 'footer_pages.php')) {
    try {
        admin_require_project_file('api/bootstrap.php');
        $slug = trim((string) ($_GET['slug'] ?? ''));
        if ($slug !== '') {
            $page = ApiFooterPages::findBySlug($slug);
            if ($page === null) {
                $memberEnvelope(404, [
                    'success' => false,
                    'code' => 404,
                    'message' => 'Footer sayfası bulunamadı',
                ]);
            }
            $memberEnvelope(200, [
                'success' => true,
                'code' => 200,
                'message' => 'Footer sayfası',
                'data' => ['page' => $page],
            ]);
        }

        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Footer sayfaları',
            'data' => ['pages' => ApiFooterPages::allActive()],
        ]);
    } catch (Throwable $exception) {
        $error(500, 'Footer sayfaları alınamadı.', ['reason' => $exception->getMessage()]);
    }
}

if ($method === 'GET' && ($route === 'content/homepage-sections' || $route === 'homepage_sections.php')) {
    try {
        admin_require_project_file('api/bootstrap.php');
        $surface = trim((string) ($_GET['surface'] ?? 'all'));
        $sectionKey = trim((string) ($_GET['section_key'] ?? ''));
        $sections = ApiHomepageSections::fetch([
            'surface' => $surface,
            'section_key' => $sectionKey,
        ]);

        $memberEnvelope(200, [
            'success' => true,
            'ok' => true,
            'code' => 200,
            'message' => 'Ana sayfa vitrin alanları',
            'data' => [
                'surface' => $surface !== '' ? $surface : 'all',
                'section_key' => $sectionKey !== '' ? $sectionKey : null,
                'total' => count($sections),
                'sections' => $sections,
            ],
        ]);
    } catch (Throwable $exception) {
        $error(500, 'Ana sayfa vitrin verisi alınamadı.', ['reason' => $exception->getMessage()]);
    }
}

if ($method === 'GET' && ($route === 'content/auth-sliders' || $route === 'auth_sliders.php')) {
    try {
        admin_require_project_file('api/bootstrap.php');
        ApiAuthSliders::ensureStorage();
        $screen = trim((string) ($_GET['screen'] ?? 'login'));
        $surface = trim((string) ($_GET['surface'] ?? 'desktop'));
        $sliders = ApiAuthSliders::fetchFor($screen, $surface);
        $memberEnvelope(200, [
            'success' => true,
            'ok' => true,
            'code' => 200,
            'message' => 'Auth slider listesi',
            'data' => [
                'screen' => in_array($screen, ['login', 'register'], true) ? $screen : 'login',
                'surface' => in_array($surface, ['desktop', 'mobile'], true) ? $surface : 'desktop',
                'total' => count($sliders),
                'sliders' => $sliders,
            ],
        ]);
    } catch (Throwable $exception) {
        $error(500, 'Auth slider verisi alınamadı.', ['reason' => $exception->getMessage()]);
    }
}

if ($method === 'GET' && ($route === 'content/sliders' || $route === 'sliders.php')) {
    try {
        admin_require_project_file('api/bootstrap.php');
        
        // Ensure AdminDatabase is loaded
        $dbFile = (defined('ADMIN_APP_PATH') ? rtrim((string) ADMIN_APP_PATH, '/\\') : '') . '/Core/AdminDatabase.php';
        if (!class_exists('AdminDatabase', false) && is_file($dbFile)) {
            require_once $dbFile;
        }
        
        $category = ApiSliders::normalizeCategory((string) ($_GET['category'] ?? $_GET['page'] ?? ''));
        $surface = ApiSliders::normalizeSurface((string) ($_GET['surface'] ?? 'all'));

        // Direct DB query — bypass ApiSliders::fetch() which may use stale/wrong code
        $sliders = [];
        if (class_exists('AdminDatabase', false)) {
            $pdo = AdminDatabase::pdo();
            $columns = [];
            try { $cStmt = $pdo->query('SHOW COLUMNS FROM sliders'); $columns = $cStmt ? array_map('strtolower', $cStmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : []; } catch (Throwable) {}

            $where = [];
            $params = [];
            if ($category !== '') {
                $where[] = 'LOWER(TRIM(CAST(category AS CHAR))) = :category';
                $params['category'] = $category;
            }

            $sql = 'SELECT * FROM sliders';
            if ($where !== []) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY `order` ASC, id DESC';
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $desktop = trim((string) ($row['desktop_path'] ?? $row['desktop_image_url'] ?? ''));
                $mobile  = trim((string) ($row['mobile_path'] ?? $row['mobile_image_url'] ?? ''));
                if ($desktop === '' && $mobile === '') continue;
                if (class_exists('ApiMediaUrl', false)) {
                    $desktop = ApiMediaUrl::resolve($desktop);
                    $mobile  = ApiMediaUrl::resolve($mobile);
                }
                $sliders[] = [
                    'id'             => (int) ($row['id'] ?? 0),
                    'title'          => (string) ($row['title'] ?? ''),
                    'subtitle'       => (string) ($row['subtitle'] ?? ''),
                    'description'    => (string) ($row['description'] ?? ''),
                    'category'       => ApiSliders::normalizeCategory((string) ($row['category'] ?? '')),
                    'order'          => (int) ($row['order'] ?? $row['sort_order'] ?? 0),
                    'desktopImageUrl' => $desktop,
                    'mobileImageUrl' => $mobile,
                    'imageUrl'       => $surface === 'mobile' ? ($mobile !== '' ? $mobile : $desktop) : ($desktop !== '' ? $desktop : $mobile),
                    'surface'        => $surface,
                    'sliderLink'     => (string) ($row['link_url'] ?? $row['button_link'] ?? $row['slider_link'] ?? $row['link'] ?? ''),
                ];
            }
        }

        http_response_code(200);
        $dbDiag = null;
        try {
            if (class_exists('AdminDatabase', false)) {
                $diagPdo = AdminDatabase::pdo();
                $dbName = (string) $diagPdo->query('SELECT DATABASE()')->fetchColumn();
                $rawCount = (int) $diagPdo->query('SELECT COUNT(*) FROM sliders')->fetchColumn();
                $cols = [];
                try { $cStmt = $diagPdo->query('SHOW COLUMNS FROM sliders'); $cols = $cStmt ? $cStmt->fetchAll(PDO::FETCH_COLUMN) : []; } catch (Throwable) {}
                $rawRows = [];
                try {
                    $rawRows = $diagPdo->query('SELECT id, title, category, status, start_date, end_date FROM sliders ORDER BY id DESC LIMIT 15')->fetchAll(PDO::FETCH_ASSOC);
                } catch (Throwable $re) {
                    $rawRows = ['error' => $re->getMessage()];
                }
                $dbDiag = ['db' => $dbName, 'raw_count' => $rawCount, 'columns' => $cols, 'raw_rows' => $rawRows];
            } else {
                $dbDiag = ['error' => 'AdminDatabase class could not be loaded', 'dbFile' => $dbFile];
            }
        } catch (Throwable $e) {
            $dbDiag = ['error' => $e->getMessage()];
        }
        if (isset($GLOBALS['__slider_diag_fdb']) && is_array($GLOBALS['__slider_diag_fdb'])) {
            $dbDiag = is_array($dbDiag) ? array_merge($dbDiag, ['fdb' => $GLOBALS['__slider_diag_fdb']]) : $dbDiag;
        }

        echo json_encode([
            'success' => true,
            'code' => 200,
            'message' => 'Slider listesi v942',
            'data' => [
                'category' => $category !== '' ? $category : null,
                'surface' => $surface,
                'categories' => ApiSliders::categories(),
                'total' => count($sliders),
                'sliders' => $sliders,
            ],
            '_db' => $dbDiag,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    } catch (Throwable $exception) {
        $error(500, 'Slider verisi alınamadı.', ['reason' => $exception->getMessage()]);
    }
}

if ($method === 'GET' && in_array($route, ['currencies', 'countries', 'languages', 'maintenance-status'], true)) {
    $reference = match ($route) {
        'currencies' => [
            'items' => [
                ['code' => 'TRY', 'symbol' => '₺', 'name' => 'Turkish Lira', 'default' => true],
                ['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'default' => false],
                ['code' => 'EUR', 'symbol' => '€', 'name' => 'Euro', 'default' => false],
            ],
            'default' => 'TRY',
        ],
        'countries' => [
            'items' => [['code' => 'TR', 'name' => 'Türkiye', 'default' => true]],
            'default' => 'TR',
        ],
        'languages' => [
            'items' => [['code' => 'tr', 'name' => 'Türkçe', 'default' => true]],
            'default' => 'tr',
        ],
        default => [
            'maintenance' => false,
            'status' => 'online',
        ],
    };
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'data' => $reference,
        'meta' => ['resource' => $route],
    ]);
}
