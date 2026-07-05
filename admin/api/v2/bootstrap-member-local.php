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

if (!class_exists('AdminDatabase', false)) {
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
