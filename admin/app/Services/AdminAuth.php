<?php

declare(strict_types=1);

final class AdminAuth
{
    private static function config(): array
    {
        return require ADMIN_APP_PATH . '/Config/admin.php';
    }

    public static function check(): bool
    {
        $config = self::config();
        $key = (string) $config['session_key'];

        $user = isset($_SESSION[$key]) && is_array($_SESSION[$key]) ? $_SESSION[$key] : [];

        return !empty($user['id']) && !empty($user['username']);
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
            $stmt->execute(['email' => $email]);
            $admin = $stmt->fetch();

            return is_array($admin) ? $admin : null;
        } catch (Throwable $exception) {
            error_log('Admin login DB lookup failed: ' . $exception->getMessage());
            $_SESSION['admin_login_error'] = 'Veritabanı bağlantısı kurulamadı. .env içindeki DB_* ayarlarını kontrol edin.';

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
