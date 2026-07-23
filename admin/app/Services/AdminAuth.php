<?php

declare(strict_types=1);

final class AdminAuth
{
    private const PERSIST_COOKIE = 'vrs_admin_auth';

    private static function config(): array
    {
        return require ADMIN_APP_PATH . '/Config/admin.php';
    }

    private static function persistentCookieName(): string
    {
        $name = trim((string) (getenv('ADMIN_AUTH_PERSIST_COOKIE') ?: self::PERSIST_COOKIE));
        return $name !== '' ? $name : self::PERSIST_COOKIE;
    }

    private static function persistentCookieSecret(): string
    {
        $secret = trim((string) (getenv('ADMIN_AUTH_COOKIE_SECRET') ?: ''));
        if ($secret === '') {
            $secret = trim((string) (getenv('APP_KEY') ?: getenv('MEMBER_JWT_SECRET') ?: 'vegasroyalspin-admin-auth'));
        }
        return hash('sha256', $secret . '|admin-persist');
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $pad = strlen($value) % 4;
        if ($pad > 0) {
            $value .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return is_string($decoded) ? $decoded : null;
    }

    private static function setPersistentCookie(string $value, int $expiresAt): void
    {
        $params = session_get_cookie_params();
        setcookie(self::persistentCookieName(), $value, [
            'expires' => $expiresAt,
            'path' => (string) ($params['path'] ?? '/'),
            'domain' => (string) ($params['domain'] ?? ''),
            'secure' => (bool) ($params['secure'] ?? true),
            'httponly' => true,
            'samesite' => (string) ($params['samesite'] ?? 'Lax'),
        ]);
    }

    private static function clearPersistentCookie(): void
    {
        $params = session_get_cookie_params();
        setcookie(self::persistentCookieName(), '', [
            'expires' => time() - 3600,
            'path' => (string) ($params['path'] ?? '/'),
            'domain' => (string) ($params['domain'] ?? ''),
            'secure' => (bool) ($params['secure'] ?? true),
            'httponly' => true,
            'samesite' => (string) ($params['samesite'] ?? 'Lax'),
        ]);
    }

    private static function issuePersistentCookie(array $admin): void
    {
        $timeoutMinutes = (int) (getenv('ADMIN_SESSION_TIMEOUT_MINUTES') ?: self::SESSION_TIMEOUT_MINUTES);
        $expiresAt = time() + max(300, $timeoutMinutes * 60);
        $payload = json_encode([
            'id' => (int) ($admin['id'] ?? 0),
            'email' => (string) ($admin['email'] ?? ''),
            'exp' => $expiresAt,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payload) || $payload === '') {
            return;
        }
        $encoded = self::base64UrlEncode($payload);
        $sig = hash_hmac('sha256', $encoded, self::persistentCookieSecret());
        self::setPersistentCookie($encoded . '.' . $sig, $expiresAt);
    }

    public static function restorePersistentLogin(): bool
    {
        if (self::check()) {
            return true;
        }

        $raw = trim((string) ($_COOKIE[self::persistentCookieName()] ?? ''));
        if ($raw === '' || !str_contains($raw, '.')) {
            return false;
        }

        [$encoded, $sig] = explode('.', $raw, 2);
        $expected = hash_hmac('sha256', $encoded, self::persistentCookieSecret());
        if ($sig === '' || !hash_equals($expected, $sig)) {
            self::clearPersistentCookie();
            return false;
        }

        $json = self::base64UrlDecode($encoded);
        $payload = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($payload) || (int) ($payload['exp'] ?? 0) < time()) {
            self::clearPersistentCookie();
            return false;
        }

        $admin = self::findAdminByIdAndEmail((int) ($payload['id'] ?? 0), (string) ($payload['email'] ?? ''));
        if ($admin === null) {
            self::clearPersistentCookie();
            return false;
        }

        $config = self::config();
        $_SESSION[(string) $config['session_key']] = [
            'id' => (int) ($admin['id'] ?? 0),
            'username' => (string) ($admin['username'] ?? $payload['email'] ?? ''),
            'email' => (string) ($admin['email'] ?? ''),
            'role' => (string) ($admin['role'] ?? 'admin'),
            'login_at' => time(),
        ];
        $_SESSION['admin_last_activity'] = time();

        return true;
    }

    /** Admin oturum hareketsizlik zaman aşımı (dakika). Değiştirmek için env: ADMIN_SESSION_TIMEOUT_MINUTES */
    private const SESSION_TIMEOUT_MINUTES = 120;

    public static function check(): bool
    {
        $config = self::config();
        $key = (string) $config['session_key'];

        $user = isset($_SESSION[$key]) && is_array($_SESSION[$key]) ? $_SESSION[$key] : [];

        if (empty($user['id']) || empty($user['username'])) {
            return false;
        }

        // Hareketsizlik zaman aşımı kontrolü.
        $timeoutMinutes = (int) (getenv('ADMIN_SESSION_TIMEOUT_MINUTES') ?: self::SESSION_TIMEOUT_MINUTES);
        $timeoutSeconds = max(300, $timeoutMinutes * 60); // En az 5 dakika
        $lastActivity = (int) ($_SESSION['admin_last_activity'] ?? 0);

        if ($lastActivity > 0 && (time() - $lastActivity) > $timeoutSeconds) {
            // Oturum süresi doldu — temizle.
            $_SESSION[$key] = [];
            unset($_SESSION['admin_last_activity']);
            return false;
        }

        $_SESSION['admin_last_activity'] = time();

        self::ensureSuperAdminHasAllPermissions();

        return true;
    }

    /**
     * Süperadmin hesapları kod tarafında (isSuperAdmin()) her zaman tam yetkiye
     * sahiptir; ancak admin_permissions tablosunda buna karşılık gelen satırlar
     * olmayabilir (ör. yeni eklenen bir modül, ya da /permissions ekranı hiç
     * açılmadıysa). Bu durum hem "Admin Yetkileri" ekranında yanıltıcı şekilde
     * boş/kapalı görünmesine hem de kod dışı bir kontrol noktasının yanlışlıkla
     * bu tabloyu tek kaynak sayması hâlinde erişimin reddedilmesine yol açar.
     * Süperadmin oturumu doğrulandığında (oturum başına bir kez) tüm mevcut
     * sayfa anahtarlarını granted=1 olarak yazarak bu tabloyu koddaki bypass ile
     * senkron tutar. Hata durumunda sessizce yutulur (sayfa yüklemesini bozmaz).
     */
    private static function ensureSuperAdminHasAllPermissions(): void
    {
        if (!self::isSuperAdmin()) {
            return;
        }
        if (!empty($_SESSION['admin_superadmin_perms_synced'])) {
            return;
        }
        $adminId = (int) (self::user()['id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        try {
            $pdo = AdminDatabase::pdo();
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS admin_permissions (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    admin_id INT UNSIGNED NOT NULL,
                    page_key VARCHAR(120) NOT NULL,
                    granted TINYINT(1) NOT NULL DEFAULT 0,
                    granted_by INT UNSIGNED NULL,
                    granted_at DATETIME NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_admin_permissions_admin_page (admin_id, page_key),
                    KEY idx_admin_permissions_admin (admin_id),
                    KEY idx_admin_permissions_page (page_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $stmt = $pdo->prepare(
                'INSERT INTO admin_permissions (admin_id, page_key, granted, granted_by, granted_at)
                 VALUES (:admin_id, :page_key, 1, :granted_by, NOW())
                 ON DUPLICATE KEY UPDATE granted = 1, granted_by = VALUES(granted_by), granted_at = VALUES(granted_at)'
            );
            foreach (self::allCanonicalPermissionKeys() as $pageKey) {
                $stmt->execute([
                    'admin_id' => $adminId,
                    'page_key' => $pageKey,
                    'granted_by' => $adminId,
                ]);
            }

            $_SESSION['admin_superadmin_perms_synced'] = true;
        } catch (Throwable) {
            // DB henüz hazır değilse ya da geçici bir hata olursa panel açılmaya devam etsin;
            // isSuperAdmin() bypass'ı zaten erişimi garanti eder.
        }
    }

    /** Navigasyon konfigürasyonundaki tüm benzersiz canonical yetki anahtarları. @return list<string> */
    private static function allCanonicalPermissionKeys(): array
    {
        $config = self::config();
        $navigation = is_array($config['navigation'] ?? null) ? $config['navigation'] : [];
        $keys = [];
        foreach ($navigation as $group) {
            if (!is_array($group)) {
                continue;
            }
            foreach ((array) ($group['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $key = self::navPermissionKey($item);
                if ($key !== '') {
                    $keys[$key] = true;
                }
            }
        }

        return array_keys($keys);
    }

    public static function userName(): string
    {
        $config = self::config();
        $key = (string) $config['session_key'];
        $user = isset($_SESSION[$key]) && is_array($_SESSION[$key]) ? $_SESSION[$key] : [];

        return (string) ($user['username'] ?? 'Admin');
    }

    public static function user(): array
    {
        $config = self::config();
        $key = (string) $config['session_key'];
        $user = isset($_SESSION[$key]) && is_array($_SESSION[$key]) ? $_SESSION[$key] : [];

        return $user;
    }

    public static function isSuperAdmin(): bool
    {
        $role = strtolower(trim((string) (self::user()['role'] ?? '')));

        return in_array($role, ['superadmin', 'super_admin', 'owner'], true);
    }

    public static function canonicalPermissionKey(string $permissionKey): string
    {
        $permissionKey = trim($permissionKey);
        if ($permissionKey === '') {
            return '';
        }

        return match ($permissionKey) {
            'kyc-review' => 'kyc',
            'admin-signup' => 'admins',
            'compliance-audit' => 'logs',
            'chat', 'compose' => 'email',
            'reports-financial' => 'deposits',
            'reports-charts', 'reports-calendar', 'backoffice-suite' => 'dashboard',
            default => $permissionKey,
        };
    }

    /**
     * Nav item için canonical yetki anahtarı: permission → module → key.
     */
    public static function navPermissionKey(array $item): string
    {
        $raw = trim((string) ($item['permission'] ?? $item['module'] ?? $item['key'] ?? ''));

        return self::canonicalPermissionKey($raw);
    }

    /** @return list<string> */
    public static function permissionKeysToCheck(string $permissionKey): array
    {
        $canonical = self::canonicalPermissionKey($permissionKey);
        if ($canonical === '') {
            return [];
        }

        $keys = [$canonical];
        foreach (self::legacyPermissionAliases() as $legacy => $target) {
            if ($target === $canonical) {
                $keys[] = $legacy;
            }
        }

        return array_values(array_unique($keys));
    }

    /** @return array<string, string> */
    private static function legacyPermissionAliases(): array
    {
        return [
            'kyc-review' => 'kyc',
            'admin-signup' => 'admins',
            'compliance-audit' => 'logs',
            'chat' => 'email',
            'compose' => 'email',
            'reports-financial' => 'deposits',
            'reports-charts' => 'dashboard',
            'reports-calendar' => 'dashboard',
            'backoffice-suite' => 'dashboard',
            // Eski "be_pages_*" / önceki panel şeması altında kaydedilmiş izinler.
            // Bu admin_permissions satırları hiç güncellenmediyse (yeni sayfa
            // adlarıyla tekrar kaydedilmediyse) aşağıdaki eşlemeler olmadan
            // ilgili admin, panel yeniden adlandırıldığından beri o bölümlere
            // erişimini sessizce kaybeder.
            'admin_management' => 'admins',
            'admin_activity_logs' => 'logs',
            'visitor_logs' => 'logs',
            'admin_sessions' => 'sessions',
            'kyc_requests' => 'kyc',
            'mail_settings' => 'email',
            'slider' => 'sliders',
            'finance_deposits' => 'deposits',
            'finance_withdraws' => 'withdrawals',
            'be_pages_admin_access_control' => 'permissions',
            'be_pages_bonus_claims' => 'bonus-claims',
            'be_pages_call_me_requests' => 'call-requests',
        ];
    }

    public static function can(string $permissionKey): bool
    {
        $permissionKey = trim($permissionKey);
        if ($permissionKey === '' || !self::check()) {
            return false;
        }
        if (self::isSuperAdmin()) {
            return true;
        }

        $adminId = (int) (self::user()['id'] ?? 0);
        if ($adminId <= 0) {
            return false;
        }

        try {
            $stmt = AdminDatabase::pdo()->prepare(
                'SELECT granted FROM admin_permissions WHERE admin_id = :admin_id AND page_key = :page_key LIMIT 1'
            );
            foreach (self::permissionKeysToCheck($permissionKey) as $pageKey) {
                $stmt->execute([
                    'admin_id' => $adminId,
                    'page_key' => $pageKey,
                ]);
                if ((int) $stmt->fetchColumn() === 1) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    public static function attempt(string $email, string $password): bool
    {
        $config = self::config();
        $email = trim($email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $admin = self::findAdminByEmail($email);
        if ($admin === null) {
            self::writeLog('', 'login_failed', 'auth', 'failed', $email);
            return false;
        }

        $hash = (string) ($admin['password'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            self::writeLog('', 'login_failed', 'auth', 'failed', $email);
            return false;
        }

        session_regenerate_id(true);
        $_SESSION[(string) $config['session_key']] = [
            'id' => (int) ($admin['id'] ?? 0),
            'username' => (string) ($admin['username'] ?? $email),
            'email' => (string) ($admin['email'] ?? ''),
            'role' => (string) ($admin['role'] ?? 'admin'),
            'login_at' => time(),
        ];
        self::issuePersistentCookie($admin);
        self::ensureAdminTables();
        self::recordSession();
        self::writeLog((string) ($admin['username'] ?? $email), 'login', 'auth', 'success');

        return true;
    }

    public static function logout(): void
    {
        $config = self::config();
        $user = self::user();
        self::writeLog((string) ($user['username'] ?? ''), 'logout', 'auth', 'success');
        self::deactivateSession();
        unset($_SESSION[(string) $config['session_key']]);
        self::clearPersistentCookie();
        session_regenerate_id(true);
    }

    public static function csrfToken(): string
    {
        $config = self::config();
        $key = (string) $config['csrf_key'];
        if (empty($_SESSION[$key]) || !is_string($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$key];
    }

    public static function verifyCsrf(?string $token): bool
    {
        $config = self::config();
        $key = (string) $config['csrf_key'];
        $known = isset($_SESSION[$key]) && is_string($_SESSION[$key]) ? $_SESSION[$key] : '';

        return $known !== '' && is_string($token) && hash_equals($known, $token);
    }

    public static function url(string $path = '/'): string
    {
        $path = '/' . ltrim($path, '/');
        $adminBase = function_exists('admin_url_prefix') ? admin_url_prefix() : '';

        if ($adminBase === '') {
            return $path === '/' ? '/' : $path;
        }

        return $adminBase . ($path === '/' ? '' : $path);
    }

    /** İlk erişilebilir panel sayfası (dashboard yetkisi yoksa 403 yerine). */
    public static function postLoginPath(): string
    {
        if (self::can('dashboard')) {
            return '/dashboard';
        }

        $config = self::config();
        $navigation = is_array($config['navigation'] ?? null) ? $config['navigation'] : [];
        foreach ($navigation as $group) {
            if (!is_array($group)) {
                continue;
            }
            foreach ((array) ($group['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $permission = self::navPermissionKey($item);
                if ($permission === '' || !self::can($permission)) {
                    continue;
                }
                $url = trim((string) ($item['url'] ?? ''));
                if ($url !== '') {
                    return $url;
                }
            }
        }

        return '/dashboard';
    }

    private static function findAdminByEmail(string $email): ?array
    {
        try {
            $pdo = AdminDatabase::pdo();
            $hasActiveColumn = false;
            try {
                $check = $pdo->query("SHOW COLUMNS FROM admins LIKE 'is_active'");
                $hasActiveColumn = $check !== false && $check->fetchColumn() !== false;
            } catch (Throwable) {
                $hasActiveColumn = false;
            }

            $sql = $hasActiveColumn
                ? 'SELECT * FROM admins WHERE LOWER(email) = LOWER(:email) AND is_active = 1 LIMIT 1'
                : 'SELECT * FROM admins WHERE LOWER(email) = LOWER(:email) LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'email' => $email,
            ]);
            $admin = $stmt->fetch();

            return is_array($admin) ? $admin : null;
        } catch (Throwable $exception) {
            error_log('Admin login DB lookup failed: ' . $exception->getMessage());
            $_SESSION['admin_login_error'] = 'Veritabanı bağlantısı kurulamadı. .env içindeki DB_* ayarlarını kontrol edin.';

            return null;
        }
    }

    private static function findAdminByIdAndEmail(int $id, string $email): ?array
    {
        if ($id <= 0 || trim($email) === '') {
            return null;
        }

        try {
            $pdo = AdminDatabase::pdo();
            $hasActiveColumn = false;
            try {
                $check = $pdo->query("SHOW COLUMNS FROM admins LIKE 'is_active'");
                $hasActiveColumn = $check !== false && $check->fetchColumn() !== false;
            } catch (Throwable) {
                $hasActiveColumn = false;
            }

            $sql = $hasActiveColumn
                ? 'SELECT * FROM admins WHERE id = :id AND LOWER(email) = LOWER(:email) AND is_active = 1 LIMIT 1'
                : 'SELECT * FROM admins WHERE id = :id AND LOWER(email) = LOWER(:email) LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'email' => $email,
            ]);
            $admin = $stmt->fetch();

            return is_array($admin) ? $admin : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function ensureAdminTables(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $pdo = AdminDatabase::pdo();
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS admin_sessions (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    session_id VARCHAR(128) NOT NULL,
                    admin_id INT UNSIGNED NOT NULL,
                    username VARCHAR(100) NOT NULL,
                    email VARCHAR(190) NULL,
                    role VARCHAR(40) NOT NULL DEFAULT 'admin',
                    ip_address VARCHAR(64) NULL,
                    user_agent VARCHAR(255) NULL,
                    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                    last_activity DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                    expired_at DATETIME NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    is_2fa_verified TINYINT(1) NOT NULL DEFAULT 0,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_admin_sessions_session (session_id),
                    KEY idx_admin_sessions_admin (admin_id, is_active),
                    KEY idx_admin_sessions_activity (last_activity)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS admin_logs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    admin_id INT UNSIGNED NULL,
                    admin_username VARCHAR(100) NULL,
                    action VARCHAR(120) NOT NULL,
                    entity_type VARCHAR(80) NULL,
                    entity_id VARCHAR(80) NULL,
                    status VARCHAR(40) NOT NULL DEFAULT 'success',
                    ip_address VARCHAR(64) NULL,
                    user_agent VARCHAR(255) NULL,
                    payload LONGTEXT NULL,
                    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_admin_logs_created (created_at),
                    KEY idx_admin_logs_admin (admin_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable) {}
    }

    public static function writeLog(string $username, string $action, string $entityType, string $status, string $entityId = ''): void
    {
        try {
            $pdo = AdminDatabase::pdo();
            $user = self::user();
            $adminId = (int) ($user['id'] ?? 0);
            $pdo->prepare(
                "INSERT INTO admin_logs (admin_id, admin_username, action, entity_type, entity_id, status, ip_address, user_agent, created_at)
                 VALUES (:admin_id, :admin_username, :action, :entity_type, :entity_id, :status, :ip, :ua, NOW())"
            )->execute([
                'admin_id'       => $adminId > 0 ? $adminId : null,
                'admin_username' => $username !== '' ? $username : null,
                'action'         => $action,
                'entity_type'    => $entityType,
                'entity_id'      => $entityId !== '' ? $entityId : null,
                'status'         => $status,
                'ip'             => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'ua'             => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (Throwable) {}
    }

    private static function recordSession(): void
    {
        $user = self::user();
        if (empty($user['id'])) {
            return;
        }

        try {
            $stmt = AdminDatabase::pdo()->prepare(
                "INSERT INTO admin_sessions
                    (session_id, admin_id, username, email, role, ip_address, user_agent, created_at, last_activity, is_active, is_2fa_verified)
                 VALUES
                    (:session_id, :admin_id, :username, :email, :role, :ip_address, :user_agent, NOW(), NOW(), 1, 1)"
            );
            $stmt->execute([
                'session_id' => session_id(),
                'admin_id' => (int) $user['id'],
                'username' => (string) $user['username'],
                'email' => (string) ($user['email'] ?? ''),
                'role' => (string) ($user['role'] ?? 'admin'),
                'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (Throwable) {
        }
    }

    private static function deactivateSession(): void
    {
        try {
            $stmt = AdminDatabase::pdo()->prepare(
                'UPDATE admin_sessions SET is_active = 0, expired_at = NOW() WHERE session_id = :session_id'
            );
            $stmt->execute(['session_id' => session_id()]);
        } catch (Throwable) {
        }
    }
}
