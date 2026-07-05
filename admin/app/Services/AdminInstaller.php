<?php

declare(strict_types=1);

final class AdminInstaller
{
    public function __construct(private string $root)
    {
        $this->root = AdminInstallGate::root($root);
    }

    /**
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    public function checkRequirements(): array
    {
        $checks = [];

        $checks[] = $this->check(
            'PHP sürümü (>= 8.0)',
            version_compare(PHP_VERSION, '8.0.0', '>='),
            PHP_VERSION
        );

        foreach (['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'curl'] as $ext) {
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

        $envPath = $this->root . '/.env';
        $envWritable = is_file($envPath) ? is_writable($envPath) : is_writable($this->root);
        $checks[] = $this->check(
            '.env yazılabilir',
            $envWritable,
            $envWritable ? 'ok' : 'Kök dizinde .env oluşturma izni gerekli'
        );

        $seedOk = SqlSeedImporter::isAvailable($this->root);
        $checks[] = $this->check(
            'Kurulum seed SQL (metropolcasino.sql)',
            $seedOk,
            $seedOk
                ? 'database/seed/' . SqlSeedImporter::SEED_FILENAME . ' (' . SqlSeedImporter::humanSize($this->root) . ')'
                : 'Eksik: database/seed/' . SqlSeedImporter::SEED_FILENAME,
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
     * @param array<string, string> $db
     */
    public function testDatabase(array $db): void
    {
        $pdo = $this->connectDatabase($db);
        $pdo->query('SELECT 1');
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: bool, message: string}
     */
    public function run(array $input): array
    {
        if (AdminInstallGate::isInstalled($this->root)) {
            return ['success' => false, 'message' => 'Kurulum zaten tamamlanmış.'];
        }

        if (!$this->requirementsPassed()) {
            return ['success' => false, 'message' => 'Sunucu gereksinimleri karşılanmıyor.'];
        }

        $db = [
            'host' => trim((string) ($input['db_host'] ?? '127.0.0.1')),
            'port' => trim((string) ($input['db_port'] ?? '3306')),
            'database' => trim((string) ($input['db_database'] ?? '')),
            'username' => trim((string) ($input['db_username'] ?? '')),
            'password' => (string) ($input['db_password'] ?? ''),
        ];

        $useExistingDatabase = !empty($input['use_existing_database']);
        $importSeedDatabase = !isset($input['import_seed_database'])
            || in_array(strtolower(trim((string) $input['import_seed_database'])), ['1', 'true', 'yes', 'on'], true);
        $preserveIntegrations = !isset($input['preserve_integrations'])
            || in_array(strtolower(trim((string) $input['preserve_integrations'])), ['1', 'true', 'yes', 'on'], true);
        $existingEnv = $this->readEnvValues();

        $adminEmail = strtolower(trim((string) ($input['admin_email'] ?? '')));
        $adminUsername = trim((string) ($input['admin_username'] ?? ''));
        $adminPassword = (string) ($input['admin_password'] ?? '');

        if ($db['database'] === '' || $db['username'] === '') {
            return ['success' => false, 'message' => 'Veritabanı adı ve kullanıcı zorunludur.'];
        }

        if (!$useExistingDatabase) {
            if ($adminEmail === '' || filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
                return ['success' => false, 'message' => 'Geçerli bir admin e-posta adresi girin. Giriş yalnızca e-posta ile yapılır.'];
            }
            if ($adminUsername === '') {
                $adminUsername = strstr($adminEmail, '@', true) ?: 'admin';
            }
            if (strlen($adminPassword) < 8) {
                return ['success' => false, 'message' => 'Admin şifresi en az 8 karakter olmalıdır.'];
            }
        }

        $backendHost = trim((string) ($input['backend_host'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost'));
        $backendHost = preg_replace('/:\d+$/', '', $backendHost) ?: 'localhost';
        if (is_readable($this->root . '/config/cloudflare.php')) {
            require_once $this->root . '/config/cloudflare.php';
        }
        $backendUrl = rtrim(trim((string) ($input['backend_url'] ?? '')), '/');
        if ($backendUrl === '' && function_exists('metropol_build_public_origin_url')) {
            $backendUrl = metropol_build_public_origin_url($backendHost);
        } elseif ($backendUrl === '') {
            $backendScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
                ? 'https'
                : 'http';
            $backendUrl = rtrim($backendScheme . '://' . $backendHost, '/');
        }
        if (function_exists('metropol_coerce_public_https_url')) {
            $backendUrl = metropol_coerce_public_https_url($backendUrl);
        }
        $frontendUrl = rtrim(trim((string) ($input['frontend_url'] ?? 'https://vegasroyalspin.com')), '/');
        if (function_exists('metropol_coerce_public_https_url')) {
            $frontendUrl = metropol_coerce_public_https_url($frontendUrl);
        }
        $siteName = trim((string) ($input['site_name'] ?? 'Vegas Royal Spin'));

        $appKey = $preserveIntegrations
            ? $this->envSecretOrFallback($existingEnv, 'APP_KEY', self::generateSecret(48))
            : self::generateSecret(48);
        $jwtSecret = $preserveIntegrations
            ? $this->envSecretOrFallback($existingEnv, 'MEMBER_JWT_SECRET', self::generateSecret(48))
            : self::generateSecret(48);
        $purgeSecret = $preserveIntegrations
            ? $this->envSecretOrFallback($existingEnv, 'FRONTEND_CMS_PURGE_SECRET', self::generateSecret(48))
            : self::generateSecret(48);

        self::ensureInstallEnvBuilderLoaded();
        $apiPublicBase = InstallEnvBuilder::resolveApiPublicBaseUrl($backendUrl);

        try {
            self::ensureInstallEnvBuilderLoaded();
            $backendEnv = InstallEnvBuilder::finalizeBackendEnv(InstallEnvBuilder::buildBackendEnv([
                'root' => $this->root,
                'backend_host' => $backendHost,
                'backend_url' => $backendUrl,
                'frontend_url' => $frontendUrl,
                'app_key' => $appKey,
                'member_jwt_secret' => $jwtSecret,
                'frontend_cms_purge_secret' => $purgeSecret,
                'db_host' => $db['host'],
                'db_port' => $db['port'],
                'db_database' => $db['database'],
                'db_username' => $db['username'],
                'db_password' => $db['password'],
                'app_env' => trim((string) ($input['app_env'] ?? 'production')),
            ]));

            $envErrors = InstallEnvBuilder::validateBackendEnv($backendEnv);
            if ($envErrors !== []) {
                return [
                    'success' => false,
                    'message' => 'Ortam doğrulaması başarısız: ' . implode('; ', $envErrors),
                ];
            }

            $this->writeEnv($backendEnv, $preserveIntegrations);

            AdminInstallGate::loadEnv($this->root);
            $pdo = $this->connectDatabase($db);

            if (!$useExistingDatabase && $importSeedDatabase) {
                if (!SqlSeedImporter::isAvailable($this->root)) {
                    return [
                        'success' => false,
                        'message' => 'Seed SQL dosyası bulunamadı veya bozuk (database/seed/metropolcasino.sql). '
                            . 'Tam dosya ~22 MB olmalıdır; FTP ile yarım kalmış olabilir.',
                    ];
                }
                SqlSeedImporter::assertDatabaseEmpty($pdo);
                SqlSeedImporter::import($pdo, SqlSeedImporter::seedPath($this->root));
            }

            $this->runMigrations($pdo);
            $this->applySiteSettings($pdo, $siteName, $frontendUrl, $backendUrl, $apiPublicBase);

            if (!$useExistingDatabase) {
                $this->applyInstallAdmin($pdo, $adminUsername, $adminEmail, $adminPassword);
            } elseif (!$this->hasAnyAdmin($pdo)) {
                if ($adminEmail === '' || filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
                    return ['success' => false, 'message' => 'Veritabanında admin yok. Geçerli bir admin e-posta girin.'];
                }
                if ($adminUsername === '') {
                    $adminUsername = strstr($adminEmail, '@', true) ?: 'admin';
                }
                if (strlen($adminPassword) < 8) {
                    return ['success' => false, 'message' => 'Veritabanında admin yok. Şifre en az 8 karakter olmalı.'];
                }
                $this->applyInstallAdmin($pdo, $adminUsername, $adminEmail, $adminPassword);
            } elseif (
                $adminEmail !== ''
                && filter_var($adminEmail, FILTER_VALIDATE_EMAIL) !== false
                && strlen($adminPassword) >= 8
            ) {
                $this->applyInstallAdmin($pdo, $adminUsername, $adminEmail, $adminPassword);
            }

            AdminInstallGate::writeLock($this->root, [
                'admin_email' => $adminEmail,
                'backend_url' => rtrim($backendUrl, '/'),
            ]);
            AdminInstallGate::clearCsrfToken($this->root);

            return [
                'success' => true,
                'message' => 'Kurulum tamamlandı.',
                'admin_email' => $adminEmail,
                'admin_username' => $adminUsername,
                'member_jwt_secret' => $jwtSecret,
                'frontend_cms_purge_secret' => $purgeSecret,
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Kurulum hatası: ' . $exception->getMessage(),
            ];
        }
    }

    public function runMigrations(\PDO $pdo): void
    {
        require_once $this->root . '/app/Core/Migrator.php';
        $migrator = new \App\Core\Migrator($pdo);
        $migrator->run($this->root . '/database/migrations');
    }

    /**
     * @param array<string, string> $db
     */
    private function connectDatabase(array $db): \PDO
    {
        $host = $db['host'] !== '' ? $db['host'] : '127.0.0.1';
        $port = (int) ($db['port'] !== '' ? $db['port'] : 3306);
        $database = $db['database'];
        $username = $db['username'];
        $password = $db['password'];

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

        $options = function_exists('metropol_pdo_options')
            ? metropol_pdo_options()
            : [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];

        return new \PDO($dsn, $username, $password, $options);
    }

    /**
     * @param array<string, string> $values
     */
    private function writeEnv(array $values, bool $preserveExisting = true): void
    {
        $existing = $preserveExisting ? $this->readEnvValues() : [];
        if ($existing !== []) {
            $values = array_merge($existing, $values);
        }

        $lines = [
            '# Otomatik oluşturuldu — AdminInstaller',
            '# ' . gmdate('Y-m-d H:i:s') . ' UTC',
            '',
        ];

        foreach ($values as $key => $value) {
            $escaped = str_contains($value, ' ') || str_contains($value, '#')
                ? '"' . str_replace('"', '\\"', $value) . '"'
                : $value;
            $lines[] = $key . '=' . $escaped;
        }

        $lines[] = '';
        if (!isset($values['BGAMING_WALLET_SECRET'])) {
            $lines[] = '# Provider secrets (panelden veya buradan yapılandırın)';
            $lines[] = 'BGAMING_WALLET_SECRET=';
            $lines[] = 'MEGAPAYZ_PRIVATE_KEY=';
            $lines[] = 'MEGAPAYZ_CALLBACK_TOKEN=';
            $lines[] = '';
        }

        $target = $this->root . '/.env';
        if (is_file($target) && !is_writable($target)) {
            throw new RuntimeException('.env dosyası yazılamıyor.');
        }

        file_put_contents($target, implode("\n", $lines));
    }

    /**
     * @return array<string, string>
     */
    private function readEnvValues(): array
    {
        $path = $this->root . '/.env';
        if (!is_readable($path)) {
            return [];
        }
        $values = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return [];
        }
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            $value = trim((string) $value);
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }
            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * @param array<string, string> $env
     */
    private function envSecretOrFallback(array $env, string $key, string $fallback): string
    {
        $value = trim((string) ($env[$key] ?? ''));
        if (strlen($value) >= 32) {
            return $value;
        }

        return $fallback;
    }

    private function applySiteSettings(\PDO $pdo, string $siteName, string $frontendUrl, string $backendUrl, string $apiPublicBase): void
    {
        if (!$this->tableExists($pdo, 'site_ayarlar')) {
            return;
        }

        self::ensureInstallEnvBuilderLoaded();
        $frontendHost = parse_url($frontendUrl, PHP_URL_HOST) ?: '';
        $backendHost = parse_url($backendUrl, PHP_URL_HOST) ?: '';
        $apiHost = parse_url($apiPublicBase, PHP_URL_HOST) ?: InstallEnvBuilder::resolveApiHost($backendHost);
        $baseHost = $frontendHost;
        if (str_starts_with($baseHost, 'www.')) {
            $baseHost = substr($baseHost, 4);
        }
        if (str_starts_with($baseHost, 'm.')) {
            $baseHost = substr($baseHost, 2);
        }
        $allowed = implode(',', array_filter(array_unique([
            $frontendHost,
            $backendHost,
            $apiHost,
            $baseHost !== '' ? 'www.' . $baseHost : '',
            $baseHost !== '' ? 'm.' . $baseHost : '',
        ])));

        $count = (int) $pdo->query('SELECT COUNT(*) FROM site_ayarlar')->fetchColumn();
        if ($count === 0) {
            $stmt = $pdo->prepare(
                'INSERT INTO site_ayarlar (site_adi, site_aciklama, frontend_url, backend_url, backend_api_base_url, allowed_url_hosts)
                 VALUES (:site_adi, :site_aciklama, :frontend_url, :backend_url, :backend_api_base_url, :allowed_url_hosts)'
            );
            $stmt->execute([
                'site_adi' => $siteName,
                'site_aciklama' => 'Güvenilir casino ve bahis',
                'frontend_url' => rtrim($frontendUrl, '/'),
                'backend_url' => rtrim($backendUrl, '/'),
                'backend_api_base_url' => rtrim($apiPublicBase, '/'),
                'allowed_url_hosts' => $allowed,
            ]);

            return;
        }

        $rowId = (int) $pdo->query('SELECT MIN(id) FROM site_ayarlar')->fetchColumn();
        if ($rowId <= 0) {
            return;
        }

        $stmt = $pdo->prepare(
            'UPDATE site_ayarlar
             SET site_adi = :site_adi,
                 frontend_url = :frontend_url,
                 backend_url = :backend_url,
                 backend_api_base_url = :backend_api_base_url,
                 allowed_url_hosts = :allowed_url_hosts
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $rowId,
            'site_adi' => $siteName,
            'frontend_url' => rtrim($frontendUrl, '/'),
            'backend_url' => rtrim($backendUrl, '/'),
            'backend_api_base_url' => rtrim($apiPublicBase, '/'),
            'allowed_url_hosts' => $allowed,
        ]);
    }

    private function applyInstallAdmin(\PDO $pdo, string $username, string $email, string $password): void
    {
        $email = strtolower(trim($email));
        $username = trim($username);
        if ($email === '' || $username === '' || $password === '') {
            return;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $hasActiveColumn = $this->columnExists($pdo, 'admins', 'is_active');

        $find = $pdo->prepare('SELECT id, username FROM admins WHERE LOWER(email) = :email LIMIT 1');
        $find->execute(['email' => $email]);
        $existing = $find->fetch();
        if (is_array($existing)) {
            $sql = $hasActiveColumn
                ? 'UPDATE admins SET username = :username, password = :password, role = :role, is_active = 1, updated_at = NOW() WHERE id = :id'
                : 'UPDATE admins SET username = :username, password = :password, role = :role, updated_at = NOW() WHERE id = :id';
            $pdo->prepare($sql)->execute([
                'id' => (int) $existing['id'],
                'username' => $username,
                'password' => $hash,
                'role' => 'superadmin',
            ]);

            return;
        }

        $username = $this->uniqueAdminUsername($pdo, $username);
        $columns = ['username', 'email', 'password', 'role', 'twofa_enabled', 'created_at', 'updated_at'];
        $values = [':username', ':email', ':password', ':role', '0', 'NOW()', 'NOW()'];
        $params = [
            'username' => $username,
            'email' => $email,
            'password' => $hash,
            'role' => 'superadmin',
        ];
        if ($hasActiveColumn) {
            $columns[] = 'is_active';
            $values[] = '1';
        }

        $pdo->prepare(
            'INSERT INTO admins (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')'
        )->execute($params);
    }

    private function uniqueAdminUsername(\PDO $pdo, string $username): string
    {
        $base = $username !== '' ? $username : 'admin';
        $candidate = $base;
        $suffix = 1;
        $check = $pdo->prepare('SELECT 1 FROM admins WHERE username = :username LIMIT 1');
        while (true) {
            $check->execute(['username' => $candidate]);
            if ($check->fetchColumn() === false) {
                return $candidate;
            }
            $suffix++;
            $candidate = $base . $suffix;
            if ($suffix > 99) {
                return $base . bin2hex(random_bytes(3));
            }
        }
    }

    private function columnExists(\PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1'
            );
            $stmt->execute(['table' => $table, 'column' => $column]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private function createSuperAdmin(\PDO $pdo, string $username, string $email, string $password): void
    {
        $this->applyInstallAdmin($pdo, $username, $email, $password);
    }

    private function hasAnyAdmin(\PDO $pdo): bool
    {
        if (!$this->tableExists($pdo, 'admins')) {
            return false;
        }

        return (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
    }

    private function tableExists(\PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1'
        );
        $stmt->execute(['table' => $table]);

        return (bool) $stmt->fetchColumn();
    }

    public static function generateSecret(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    /**
     * @return array{0: string, 1: string} [public_hosts, allowed_hosts]
     */
    public static function hostListsForUrls(string $frontendUrl, string $backendHost): array
    {
        self::ensureInstallEnvBuilderLoaded();

        return InstallEnvBuilder::hostLists($frontendUrl, $backendHost);
    }

    private static function ensureInstallEnvBuilderLoaded(): void
    {
        if (class_exists(InstallEnvBuilder::class, false)) {
            return;
        }

        foreach ([
            dirname(__DIR__) . '/Services/InstallEnvBuilder.php',
            dirname(__DIR__, 3) . '/app/Services/InstallEnvBuilder.php',
        ] as $file) {
            if (is_readable($file)) {
                require_once $file;

                return;
            }
        }

        throw new RuntimeException('InstallEnvBuilder.php bulunamadı.');
    }

    /**
     * @return array{label: string, ok: bool, detail: string, critical: bool}
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
