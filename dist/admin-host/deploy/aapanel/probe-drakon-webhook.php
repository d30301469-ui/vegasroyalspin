#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Drakon webhook public erişim testi (backend SSH).
 * Usage: php deploy/aapanel/probe-drakon-webhook.php [user_id] [/path/to/backend]
 */

$root = dirname(__DIR__, 2);
$userId = '1';
foreach (array_slice($argv, 1) as $arg) {
    if (ctype_digit(trim($arg))) {
        $userId = trim($arg);
        continue;
    }
    if (trim($arg) !== '' && !str_starts_with($arg, '-')) {
        $root = rtrim(str_replace('\\', '/', $arg), '/');
    }
}

require_once $root . '/app/Core/AdminPaths.php';
admin_paths_bootstrap();
require_once admin_project_path('app/Core/AdminDatabase.php');
require_once admin_project_path('services/DrakonService.php');

$pdo = AdminDatabase::pdo();
DrakonService::bootstrap($pdo);
$config = DrakonService::config($pdo);

echo "Drakon public webhook probe\n";
echo "Root: {$root}\n";
echo "user_id: {$userId}\n";
echo 'is_active: ' . ((int) ($config['is_active'] ?? 0) === 1 ? 'yes' : 'NO') . "\n";
echo 'site_endpoint: ' . ($config['site_endpoint'] ?? '') . "\n";
echo 'callback_allowed_ips: ' . (trim((string) ($config['callback_allowed_ips'] ?? '')) ?: '(empty — OK)') . "\n\n";

$result = DrakonService::probePublicWebhook($pdo, $userId);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

if (!empty($result['ok'])) {
    echo "\nOK — Drakon webhook public erişilebilir.\n";
    exit(0);
}

echo "\nFAIL — Drakon sunucuları bu webhook'a ulaşamaz; game_launch altyapı URL döner.\n";
echo "Kontrol:\n";
echo "  1. Drakon agent panel Site URL = https://bo-nexthub.site\n";
echo "  2. Admin → Drakon → Callback Allowed IPs = BOŞ\n";
echo "  3. Cloudflare proxy KAPALI (gri bulut) veya Drakon IP whitelist\n";
echo "  4. curl -X POST \"https://bo-nexthub.site/drakon_api\" -H \"Content-Type: application/json\" -d '{\"method\":\"user_balance\",\"user_id\":\"1\"}'\n";
exit(1);
