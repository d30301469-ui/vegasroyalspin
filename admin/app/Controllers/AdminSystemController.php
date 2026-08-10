<?php

declare(strict_types=1);

final class AdminSystemController extends AdminController
{
    public function ui(): void
    {
        $this->requireAuth();
        $this->redirect(AdminAuth::url('/dashboard'));
    }

    public function buttons(): void
    {
        $this->requireAuth();
        $this->redirect(AdminAuth::url('/dashboard'));
    }

    public function forms(): void
    {
        $this->requireAuth();
        $this->redirect(AdminAuth::url('/site-settings'));
    }

    public function basicTable(): void
    {
        $this->requireAuth();
        $this->redirect(AdminAuth::url('/module?key=logs'));
    }

    public function blank(): void
    {
        $this->requireAuth();
        $this->redirect(AdminAuth::url('/dashboard'));
    }

    public function googleMaps(): void
    {
        $this->requirePermission('dashboard');

        $pdo = AdminDatabase::pdo();
        $countryData = [];
        $cityData = [];
        $recentVisitors = [];
        $mapPoints = [];
        $dailyTrend = ['labels' => [], 'data' => []];
        $totalVisitors = 0;
        $uniqueCountries = 0;
        $queryError = '';

        try {
            $countryData = VisitorCountryNormalizer::mergeCountryRows(
                $pdo->query(
                    "SELECT country_name, country_code, COUNT(*) AS visitors,
                            COALESCE(AVG(NULLIF(lat, 0)), 0) AS lat, COALESCE(AVG(NULLIF(lon, 0)), 0) AS lon
                     FROM visitor_logs
                     WHERE country_name IS NOT NULL AND country_name != ''
                     GROUP BY country_name, country_code
                     ORDER BY visitors DESC
                     LIMIT 200"
                )->fetchAll(PDO::FETCH_ASSOC) ?: [],
                'visitors'
            );
            $uniqueCountries = count($countryData);
            $countryData = array_slice($countryData, 0, 50);

            $cityRaw = $pdo->query(
                "SELECT COALESCE(NULLIF(city, ''), country_name, 'Bilinmiyor') AS city_label,
                        COALESCE(country_name, '') AS country_name,
                        COALESCE(country_code, '') AS country_code,
                        COUNT(*) AS visitors,
                        COALESCE(AVG(NULLIF(lat, 0)), 0) AS lat,
                        COALESCE(AVG(NULLIF(lon, 0)), 0) AS lon
                 FROM visitor_logs
                 WHERE country_name IS NOT NULL AND country_name != ''
                 GROUP BY COALESCE(NULLIF(city, ''), country_name, 'Bilinmiyor'),
                          COALESCE(country_name, ''),
                          COALESCE(country_code, '')
                 ORDER BY visitors DESC
                 LIMIT 40"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $cityMerged = [];
            foreach ($cityRaw as $row) {
                $countryLabel = VisitorCountryNormalizer::canonicalName(
                    (string) ($row['country_code'] ?? ''),
                    (string) ($row['country_name'] ?? '')
                );
                $cityLabel = (string) ($row['city_label'] ?? 'Bilinmiyor');
                // Şehir etiketi ham ülke adıysa kanonik ülkeye çek
                if ($cityLabel === (string) ($row['country_name'] ?? '')) {
                    $cityLabel = $countryLabel;
                }
                $key = mb_strtolower($cityLabel . '|' . $countryLabel, 'UTF-8');
                if (!isset($cityMerged[$key])) {
                    $cityMerged[$key] = [
                        'city_label' => $cityLabel,
                        'country_name' => $countryLabel,
                        'visitors' => 0,
                        'lat' => 0.0,
                        'lon' => 0.0,
                        '_w' => 0,
                    ];
                }
                $v = (int) ($row['visitors'] ?? 0);
                $cityMerged[$key]['visitors'] += $v;
                $lat = (float) ($row['lat'] ?? 0);
                $lon = (float) ($row['lon'] ?? 0);
                if (abs($lat) > 0.0001 || abs($lon) > 0.0001) {
                    $cityMerged[$key]['lat'] += $lat * max(1, $v);
                    $cityMerged[$key]['lon'] += $lon * max(1, $v);
                    $cityMerged[$key]['_w'] += max(1, $v);
                }
            }
            $cityData = [];
            foreach ($cityMerged as $row) {
                $w = (int) $row['_w'];
                $cityData[] = [
                    'city_label' => (string) $row['city_label'],
                    'country_name' => (string) $row['country_name'],
                    'visitors' => (int) $row['visitors'],
                    'lat' => $w > 0 ? ((float) $row['lat'] / $w) : 0.0,
                    'lon' => $w > 0 ? ((float) $row['lon'] / $w) : 0.0,
                ];
            }
            usort($cityData, static fn (array $a, array $b): int => ((int) $b['visitors']) <=> ((int) $a['visitors']));
            $cityData = array_slice($cityData, 0, 15);

            $recentVisitors = $pdo->query(
                "SELECT ip_address, country_name, country_code, city, region, lat, lon, user_agent, created_at
                 FROM visitor_logs
                 WHERE country_name IS NOT NULL AND country_name != ''
                 ORDER BY created_at DESC
                 LIMIT 100"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($recentVisitors as &$visitorRow) {
                $visitorRow['country_name'] = VisitorCountryNormalizer::canonicalName(
                    (string) ($visitorRow['country_code'] ?? ''),
                    (string) ($visitorRow['country_name'] ?? '')
                );
            }
            unset($visitorRow);

            $totalVisitors = (int) $pdo->query('SELECT COUNT(*) FROM visitor_logs')->fetchColumn();

            $days = [];
            for ($i = 13; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime('-' . $i . ' day'));
                $days[$d] = 0;
                $dailyTrend['labels'][] = date('d.m', strtotime($d));
            }
            foreach ($pdo->query(
                "SELECT DATE(created_at) AS d, COUNT(*) AS c
                 FROM visitor_logs
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
                 GROUP BY DATE(created_at)"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $d = (string) ($row['d'] ?? '');
                if (isset($days[$d])) {
                    $days[$d] = (int) ($row['c'] ?? 0);
                }
            }
            $dailyTrend['data'] = array_values($days);
        } catch (Throwable $exception) {
            error_log('[AdminSystemController] geomap query failed: ' . $exception->getMessage());
            $queryError = 'Ziyaretçi coğrafi verileri yüklenemedi. visitor_logs tablosunu ve GeoIP kayıtlarını kontrol edin.';
        }

        foreach ($countryData as $row) {
            $lat = (float) ($row['lat'] ?? 0);
            $lon = (float) ($row['lon'] ?? 0);
            if (abs($lat) < 0.0001 && abs($lon) < 0.0001) {
                continue;
            }
            $mapPoints[] = [
                'type' => 'country',
                'label' => (string) ($row['country_name'] ?? 'Ülke'),
                'visitors' => (int) ($row['visitors'] ?? 0),
                'lat' => $lat,
                'lon' => $lon,
            ];
        }
        foreach (array_slice($recentVisitors, 0, 40) as $row) {
            $lat = (float) ($row['lat'] ?? 0);
            $lon = (float) ($row['lon'] ?? 0);
            if (abs($lat) < 0.0001 && abs($lon) < 0.0001) {
                continue;
            }
            $city = trim((string) ($row['city'] ?? ''));
            $country = trim((string) ($row['country_name'] ?? ''));
            $mapPoints[] = [
                'type' => 'visit',
                'label' => $city !== '' ? ($city . ($country !== '' ? ' · ' . $country : '')) : ($country !== '' ? $country : 'Ziyaret'),
                'visitors' => 1,
                'lat' => $lat,
                'lon' => $lon,
                'ip' => (string) ($row['ip_address'] ?? ''),
                'at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        $palette = [
            '#3b82f6', '#8b5cf6', '#06b6d4', '#22c55e', '#f59e0b',
            '#ef4444', '#ec4899', '#14b8a6', '#a855f7', '#64748b',
            '#f97316', '#0ea5e9', '#84cc16', '#e11d48', '#6366f1',
        ];

        $topCountries = array_slice($countryData, 0, 12);
        $otherVisitors = 0;
        foreach (array_slice($countryData, 12) as $row) {
            $otherVisitors += (int) ($row['visitors'] ?? 0);
        }

        $donutLabels = array_map(static fn ($r) => (string) ($r['country_name'] ?? '—'), $topCountries);
        $donutData = array_map(static fn ($r) => (int) ($r['visitors'] ?? 0), $topCountries);
        $donutColors = array_slice($palette, 0, max(1, count($donutLabels)));
        if ($otherVisitors > 0) {
            $donutLabels[] = 'Diğer';
            $donutData[] = $otherVisitors;
            $donutColors[] = '#94a3b8';
        }

        $chartData = [
            'donut' => [
                'labels' => $donutLabels,
                'data' => $donutData,
                'colors' => $donutColors,
            ],
            'countries' => [
                'labels' => array_map(static fn ($r) => (string) ($r['country_name'] ?? '—'), array_slice($countryData, 0, 20)),
                'data' => array_map(static fn ($r) => (int) ($r['visitors'] ?? 0), array_slice($countryData, 0, 20)),
            ],
            'cities' => [
                'labels' => array_map(
                    static function ($r): string {
                        $city = (string) ($r['city_label'] ?? '—');
                        $country = (string) ($r['country_name'] ?? '');
                        return $country !== '' && $country !== $city ? ($city . ' · ' . $country) : $city;
                    },
                    $cityData
                ),
                'data' => array_map(static fn ($r) => (int) ($r['visitors'] ?? 0), $cityData),
            ],
            'trend' => $dailyTrend,
        ];

        $this->view('system/maps', [
            'title' => 'Oyuncu Haritası',
            'active' => 'maps',
            'crumbs' => 'Raporlar | Oyuncu Haritası',
            'countryData' => $countryData,
            'recentVisitors' => $recentVisitors,
            'mapPoints' => $mapPoints,
            'totalVisitors' => $totalVisitors,
            'uniqueCountries' => $uniqueCountries,
            'chartData' => $chartData,
            'queryError' => $queryError,
        ]);
    }

    public function vectorMaps(): void
    {
        $this->requireAuth();
        $this->redirect(AdminAuth::url('/reports/charts'));
    }

    public function signup(): void
    {
        $this->requirePermission('admins');
        $this->view('system/signup', [
            'title' => 'Yeni Admin',
            'active' => 'signup',
            'crumbs' => 'Admin | Yeni Admin',
            'flash' => $this->pullFlash(),
        ]);
    }

    public function storeAdmin(): void
    {
        $this->requirePermission('admins');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = trim((string) ($_POST['role'] ?? 'admin')) ?: 'admin';

        // Superadmin rolü yalnızca mevcut superadmin tarafından atanabilir.
        if (in_array($role, ['superadmin', 'super_admin', 'owner'], true) && !AdminAuth::isSuperAdmin()) {
            http_response_code(403);
            $this->flash('Superadmin rolü oluşturma yetkiniz yok.');
            $this->redirect(AdminAuth::url('/signup'));
        }

        if ($username === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < 6) {
            $this->flash('Kullanıcı adı, geçerli email ve en az 6 karakter şifre girilmelidir.');
            $this->redirect(AdminAuth::url('/signup'));
        }

        try {
            $columns = $this->tableColumns('admins');
            $data = [];
            $expressions = [];

            foreach ([
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'twofa_enabled' => '0',
                'is_active' => '1',
            ] as $column => $value) {
                if (in_array($column, $columns, true)) {
                    $data[$column] = $value;
                }
            }

            foreach (['created_at', 'updated_at'] as $column) {
                if (in_array($column, $columns, true)) {
                    $expressions[$column] = 'NOW()';
                }
            }

            if (!isset($data['password'])) {
                throw new RuntimeException('admins.password kolonu bulunamadı.');
            }

            $names = array_merge(array_keys($data), array_keys($expressions));
            $placeholders = array_merge(
                array_map(static fn (string $name): string => ':' . $name, array_keys($data)),
                array_values($expressions)
            );
            $sql = 'INSERT INTO admins (`' . implode('`, `', $names) . '`) VALUES (' . implode(', ', $placeholders) . ')';
            AdminDatabase::pdo()->prepare($sql)->execute($data);

            $this->flash('Admin hesabı oluşturuldu.');
            $this->redirect(AdminAuth::url('/module?key=admins'));
        } catch (Throwable $exception) {
            $this->flash('Admin oluşturulamadı: ' . $exception->getMessage());
            $this->redirect(AdminAuth::url('/signup'));
        }
    }

    public function notFound(): void
    {
        $this->requireAuth();
        http_response_code(404);
        $this->view('errors/404', [
            'title' => '404',
            'active' => '404',
            'crumbs' => 'Pages | 404',
        ]);
    }

    public function serverError(): void
    {
        $this->requireAuth();
        http_response_code(500);
        $this->view('errors/500', [
            'title' => '500',
            'active' => '500',
            'crumbs' => 'Pages | 500',
            'message' => 'Tema 500 hata sayfası yönetim paneline bağlandı.',
        ]);
    }

    private function logs(): array
    {
        try {
            $stmt = AdminDatabase::pdo()->query(
                'SELECT id, admin_username, action, entity_type, entity_id, status, ip_address, created_at FROM admin_logs ORDER BY created_at DESC LIMIT 20'
            );

            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function visitorLocations(): array
    {
        try {
            $rows = AdminDatabase::pdo()->query(
                "SELECT COALESCE(NULLIF(country_name, ''), 'Bilinmeyen') AS country_name,
                        COALESCE(country_code, '') AS country_code,
                        COALESCE(NULLIF(city, ''), '-') AS city,
                        COUNT(*) AS total,
                        AVG(lat) AS lat,
                        AVG(lon) AS lon
                 FROM visitor_logs
                 GROUP BY country_name, country_code, city
                 ORDER BY total DESC
                 LIMIT 60"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $merged = [];
            foreach ($rows as $row) {
                $country = VisitorCountryNormalizer::canonicalName(
                    (string) ($row['country_code'] ?? ''),
                    (string) ($row['country_name'] ?? '')
                );
                $city = (string) ($row['city'] ?? '-');
                $key = mb_strtolower($country . '|' . $city, 'UTF-8');
                if (!isset($merged[$key])) {
                    $merged[$key] = [
                        'country_name' => $country,
                        'city' => $city,
                        'total' => 0,
                        'lat' => 0.0,
                        'lon' => 0.0,
                        '_w' => 0,
                    ];
                }
                $t = (int) ($row['total'] ?? 0);
                $merged[$key]['total'] += $t;
                $lat = (float) ($row['lat'] ?? 0);
                $lon = (float) ($row['lon'] ?? 0);
                if (abs($lat) > 0.0001 || abs($lon) > 0.0001) {
                    $merged[$key]['lat'] += $lat * max(1, $t);
                    $merged[$key]['lon'] += $lon * max(1, $t);
                    $merged[$key]['_w'] += max(1, $t);
                }
            }

            $out = [];
            foreach ($merged as $row) {
                $w = (int) $row['_w'];
                $out[] = [
                    'country_name' => (string) $row['country_name'],
                    'city' => (string) $row['city'],
                    'total' => (int) $row['total'],
                    'lat' => $w > 0 ? ((float) $row['lat'] / $w) : 0.0,
                    'lon' => $w > 0 ? ((float) $row['lon'] / $w) : 0.0,
                ];
            }
            usort($out, static fn (array $a, array $b): int => ((int) $b['total']) <=> ((int) $a['total']));

            return array_slice($out, 0, 20);
        } catch (Throwable) {
            return [];
        }
    }

    private function tableColumns(string $table): array
    {
        $stmt = AdminDatabase::pdo()->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $stmt->execute(['table' => $table]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function flash(string $message): void
    {
        $_SESSION['admin_flash'] = $message;
    }

    private function pullFlash(): string
    {
        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        return $message;
    }
}
