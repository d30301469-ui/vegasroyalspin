#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Admin backend CLI kurulum aracı.
 *
 * Usage:
 *   php bin/install.php --check
 *   php bin/install.php --migrate
 *   php bin/install.php \
 *     --db-host=127.0.0.1 --db-port=3306 --db-name=metropol_db \
 *     --db-user=metropol_user --db-pass=secret \
 *     --admin-email=admin@example.com --admin-user=admin --admin-pass=Secret123! \
 *     --backend-url=https://bo-nexthub.site --frontend-url=https://vegasroyalspin.com
 */

$root = dirname(__DIR__);
require_once $root . '/app/Core/AdminInstallGate.php';
require_once $root . '/app/Services/AdminInstaller.php';

$options = getopt('', [
    'check',
    'migrate',
    'db-host:',
    'db-port:',
    'db-name:',
    'db-user:',
    'db-pass:',
    'admin-email:',
    'admin-user:',
    'admin-pass:',
    'backend-url:',
    'frontend-url:',
    'site-name:',
    'help',
]);

if (isset($options['help'])) {
    fwrite(STDOUT, <<<HELP
Metropol Admin CLI Installer

  php bin/install.php --check
  php bin/install.php --migrate
  php bin/install.php --db-host=127.0.0.1 --db-name=... --db-user=... --db-pass=... \\
    --admin-email=admin@example.com --admin-pass=Secret123!

HELP);
    exit(0);
}

$installer = new AdminInstaller($root);

if (isset($options['check'])) {
    foreach ($installer->checkRequirements() as $check) {
        $status = $check['ok'] ? '[OK]' : '[FAIL]';
        fwrite(STDOUT, sprintf("%s %s — %s\n", $status, $check['label'], $check['detail']));
    }
    exit($installer->requirementsPassed() ? 0 : 1);
}

if (isset($options['migrate'])) {
    if (!is_readable($root . '/.env')) {
        fwrite(STDERR, "ERROR: .env not found. Run full install first.\n");
        exit(1);
    }
    try {
        AdminInstallGate::loadEnv($root);
        $pdo = AdminInstallGate::connectFromEnv($root);
        $installer->runMigrations($pdo);
        fwrite(STDOUT, "Migrations completed.\n");
        exit(0);
    } catch (Throwable $exception) {
        fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . "\n");
        exit(1);
    }
}

if (AdminInstallGate::isInstalled($root)) {
    fwrite(STDERR, "Already installed (storage/install.lock).\n");
    exit(1);
}

$required = ['db-name', 'db-user', 'admin-email', 'admin-pass'];
foreach ($required as $key) {
    if (empty($options[$key])) {
        fwrite(STDERR, "Missing required option: --{$key}\n");
        exit(1);
    }
}

$result = $installer->run([
    'db_host' => (string) ($options['db-host'] ?? '127.0.0.1'),
    'db_port' => (string) ($options['db-port'] ?? '3306'),
    'db_database' => (string) $options['db-name'],
    'db_username' => (string) $options['db-user'],
    'db_password' => (string) ($options['db-pass'] ?? ''),
    'admin_email' => (string) $options['admin-email'],
    'admin_username' => (string) ($options['admin-user'] ?? ''),
    'admin_password' => (string) $options['admin-pass'],
    'backend_url' => (string) ($options['backend-url'] ?? ''),
    'frontend_url' => (string) ($options['frontend-url'] ?? 'https://vegasroyalspin.com'),
    'site_name' => (string) ($options['site-name'] ?? 'Metropol Casino'),
]);

if (!$result['success']) {
    fwrite(STDERR, 'ERROR: ' . $result['message'] . "\n");
    exit(1);
}

fwrite(STDOUT, $result['message'] . "\n");
exit(0);
