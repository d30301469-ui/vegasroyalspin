<?php

declare(strict_types=1);

final class AdminRoutePermission
{
    /** @var array<string, string> */
    private static array $staticMap = [
        '/' => 'dashboard',
        '/dashboard' => 'dashboard',
        '/backoffice-suite' => 'dashboard',
        '/tables' => 'users',
        '/datatable' => 'users',
        '/forms' => 'site-settings',
        '/signup' => 'admins',
        '/signup/store' => 'admins',
        '/bgaming/settings' => 'bgaming-settings',
        '/bgaming/sync-games' => 'bgaming-settings',
        '/bgaming/campaigns' => 'bgaming-settings',
        '/bgaming/campaigns/assignments' => 'bgaming-settings',
        '/bgaming/campaigns/store' => 'bgaming-settings',
        '/bgaming/campaigns/assign' => 'bgaming-settings',
        '/bgaming/freespins' => 'bgaming-settings',
        '/bgaming/freespins/issue' => 'bgaming-settings',
        '/bgaming/freespins/sync' => 'bgaming-settings',
        '/bgaming/freespins/cancel' => 'bgaming-settings',
        '/drakon/settings' => 'drakon-settings',
        '/drakon/sync-providers' => 'drakon-settings',
        '/drakon/sync-games' => 'drakon-settings',
        '/sportsbook/settings' => 'sportsbook-settings',
        '/megapayz/settings' => 'payment-providers',
        '/megapayz/methods' => 'payment-methods',
        '/megapayz/withdraw/approve' => 'withdrawals',
        '/megapayz/withdraw/reject' => 'withdrawals',
        '/footer' => 'footer-settings',
        '/site-settings' => 'site-settings',
        '/mobile-menu' => 'mobile-menu-settings',
        '/homepage-sections' => 'homepage-sections',
        '/user' => 'users',
        '/user/create' => 'users',
        '/user/store' => 'users',
        '/user/edit' => 'users',
        '/user/update' => 'users',
        '/user/balance-adjust' => 'users',
        '/user/note/store' => 'users',
        '/promotions' => 'promotions',
        '/promotion/create' => 'promotions',
        '/promotion/store' => 'promotions',
        '/promotion/edit' => 'promotions',
        '/promotion/update' => 'promotions',
        '/promotion/delete' => 'promotions',
        '/promotion/claims' => 'promotions',
        '/bonus/assign' => 'promotions',
        '/bonus/revoke' => 'promotions',
        '/promocode-request/approve' => 'promocode-requests',
        '/promocode-request/reject' => 'promocode-requests',
        '/reports/financial' => 'deposits',
        '/compliance/audit-log' => 'logs',
        '/permissions' => 'permissions',
        '/email' => 'email',
        '/compose' => 'email',
        '/chat' => 'email',
        '/support/tickets' => 'support-tickets',
        '/support/ticket' => 'support-tickets',
        '/support/reply' => 'support-tickets',
        '/support/close' => 'support-tickets',
        '/notifications' => 'member-notifications',
        '/notifications/send' => 'member-notifications',
        '/kyc/review' => 'kyc',
        '/kyc/approve' => 'kyc',
        '/kyc/reject' => 'kyc',
        '/compliance/aml-alerts' => 'compliance-aml',
        '/compliance/risk-alerts' => 'compliance-risk',
        '/compliance/aml/resolve' => 'compliance-aml',
        '/compliance/risk/resolve' => 'compliance-risk',
        '/reports/calendar' => 'dashboard',
        '/reports/charts' => 'dashboard',
    ];

    /** @var array<string, string> */
    private static array $tableMap = [
        'member_inbox_messages' => 'email',
        'mail_outbound_log' => 'email',
        'mail_settings' => 'email',
        'call_me_requests' => 'call-requests',
    ];

    public static function resolve(string $path): ?string
    {
        if (isset(self::$staticMap[$path])) {
            return self::$staticMap[$path];
        }

        if ($path === '/module') {
            $key = trim((string) ($_GET['key'] ?? ''));

            return $key !== '' ? $key : null;
        }

        if (str_starts_with($path, '/table')) {
            $moduleKey = trim((string) ($_GET['module'] ?? $_POST['module'] ?? ''));
            if ($moduleKey !== '') {
                return $moduleKey;
            }

            $table = trim((string) ($_GET['name'] ?? $_POST['name'] ?? ''));
            if ($table !== '') {
                return self::moduleKeyForTable($table) ?? (self::$tableMap[$table] ?? null);
            }

            return 'users';
        }

        return null;
    }

    private static function moduleKeyForTable(string $table): ?string
    {
        $config = require ADMIN_APP_PATH . '/Config/admin.php';
        foreach ((array) ($config['modules'] ?? []) as $key => $module) {
            if (!is_array($module)) {
                continue;
            }
            if ((string) ($module['table'] ?? '') === $table) {
                return (string) $key;
            }
        }

        return null;
    }
}
