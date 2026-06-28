#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI frontend kurulum — .env oluşturur (API-only split deploy).
 *
 * Usage:
 *   php bin/install-frontend.php --check
 *   php bin/install-frontend.php \
 *     --frontend-url=https://vegasroyalspin.com \
 *     --backend-url=https://bo-nexthub.site \
 *     --member-jwt-secret=YOUR-32-CHAR-SECRET-SAME-AS-BACKEND \
 *     --frontend-cms-purge-secret=YOUR-32-CHAR-PURGE-SECRET-SAME-AS-BACKEND
 */

$root = dirname(__DIR__);

require_once $root . '/app/Core/FrontendInstallGate.php';
require_once $root . '/app/Services/FrontendInstaller.php';

$options = getopt('', [
    'check',
    'frontend-url:',
    'backend-url:',
    'member-jwt-secret:',
    'frontend-cms-purge-secret:',
    'app-key:',
    'live-support-url:',
    'telegram-url:',
    'whatsapp-url:',
    'help',
]);

if (isset($options['help'])) {
    echo <<<HELP
Frontend CLI kurulum

  php bin/install-frontend.php --check
  php bin/install-frontend.php --frontend-url=https://vegasroyalspin.com \\
    --backend-url=https://bo-nexthub.site --member-jwt-secret=... \\
    --frontend-cms-purge-secret=...

HELP;
    exit(0);
}

$installer = new FrontendInstaller($root);

if (isset($options['check'])) {
    foreach ($installer->checkRequirements() as $check) {
        $flag = !empty($check['ok']) ? 'OK' : 'FAIL';
        echo "[{$flag}] {$check['label']} — {$check['detail']}\n";
    }
    exit($installer->requirementsPassed() ? 0 : 1);
}

if (FrontendInstallGate::isInstalled($root)) {
    fwrite(STDERR, "ERROR: Kurulum zaten tamamlanmış.\n");
    exit(1);
}

$input = [
    'frontend_url' => (string) ($options['frontend-url'] ?? ''),
    'backend_url' => (string) ($options['backend-url'] ?? 'https://bo-nexthub.site'),
    'member_jwt_secret' => (string) ($options['member-jwt-secret'] ?? ''),
    'frontend_cms_purge_secret' => (string) ($options['frontend-cms-purge-secret'] ?? ''),
    'app_key' => (string) ($options['app-key'] ?? ''),
    'live_support_url' => (string) ($options['live-support-url'] ?? 'https://direct.lc.chat/19301899/'),
    'telegram_url' => (string) ($options['telegram-url'] ?? 'https://t.me'),
    'whatsapp_url' => (string) ($options['whatsapp-url'] ?? ''),
];

$result = $installer->run($input);
if (!$result['success']) {
    fwrite(STDERR, 'ERROR: ' . $result['message'] . "\n");
    exit(1);
}

echo $result['message'] . "\n";
echo ".env → {$root}/.env\n";
exit(0);
