<?php
/**
 * Frontend monorepo: üye API v2 route modüllerini admin/api/v2 üzerinden çalıştırır.
 * PublicMemberApiRuntime.php yerine tek kaynak (route dosyaları) kullanılır.
 */

use App\Core\Database;

if (!defined('BASE_PATH')) {
    throw new RuntimeException('BASE_PATH is required for member local API bootstrap.');
}

require_once BASE_PATH . '/admin/app/Core/AdminPaths.php';
admin_paths_bootstrap();

// IMPORTANT: prefer the REAL AdminDatabase class (reads admin/app/Config/admin.php,
// ADMIN_DB_* → DB_* → DATABASE_* priority) so that mail_settings/mail_outbound_log and
// every other admin-owned table read/write the SAME database the admin panel manages —
// even when this dispatch runs in-process on the frontend host. Without this, the fake
// shim below would silently delegate to App\Core\Database (DATABASE_* → DB_* → ADMIN_DB_*
// priority), which can resolve to a DIFFERENT database if env vars diverge, causing admin
// panel settings (and outbound mail logs) to appear to "vanish" from the frontend's point
// of view.
if (!class_exists('AdminDatabase', false)) {
    $realAdminDatabaseClass = BASE_PATH . '/admin/app/Core/AdminDatabase.php';
    if (is_file($realAdminDatabaseClass)) {
        require_once $realAdminDatabaseClass;
    }
}

if (!class_exists('AdminDatabase', false)) {
    // Ultra-fallback only if the real class file is truly unavailable.
    final class AdminDatabase
    {
        public static function pdo(): PDO
        {
            return Database::pdo();
        }
    }
}

if (!class_exists('AdminAuth', false)) {
    final class AdminAuth
    {
        public static function check(): bool
        {
            return false;
        }

        public static function can(string $permissionKey): bool
        {
            return false;
        }

        public static function user(): array
        {
            return [];
        }

        public static function csrfToken(): string
        {
            return isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token'])
                ? $_SESSION['csrf_token']
                : '';
        }

        public static function verifyCsrf(?string $token): bool
        {
            $known = self::csrfToken();

            return $known !== ''
                && is_string($token)
                && $token !== ''
                && hash_equals($known, $token);
        }
    }
}

foreach ([
    'MemberJwtService.php',
    'MemberAccountService.php',
    'MemberKycService.php',
    'MemberNotificationService.php',
    'SupportTicketService.php',
    'ComplianceService.php',
    'ComplianceMonitorService.php',
    'MegaPayzService.php',
    'BgamingService.php',
    'DrakonService.php',
    'SportsbookService.php',
] as $serviceFile) {
    admin_require_project_file('services/' . $serviceFile);
}

if (!class_exists('AdminTableRepository', false)) {
    $repoPath = BASE_PATH . '/admin/app/Repositories/AdminTableRepository.php';
    if (is_file($repoPath)) {
        require_once $repoPath;
    } else {
        final class AdminTableRepository
        {
        }
    }
}

require_once BASE_PATH . '/api/bootstrap.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

require_once BASE_PATH . '/config/frontend_session.php';
metropol_frontend_session_start();

$csrfKey = 'vegasroyalspin_csrf_token';
if (empty($_SESSION[$csrfKey]) || !is_string($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token'])
        ? $_SESSION['csrf_token']
        : bin2hex(random_bytes(32));
}
$_SESSION['csrf_token'] = $_SESSION[$csrfKey];
