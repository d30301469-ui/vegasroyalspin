#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Backend DB: eski dev host URL'lerini (admin.metropolcasino.test vb.) production hostlarına çevirir.
 *
 * Usage:
 *   php deploy/aapanel/fix-stale-cms-urls.php [/path/to/bo-nexthub.site]
 */

$root = dirname(__DIR__, 2);
foreach (array_slice($argv, 1) as $arg) {
    if (trim($arg) !== '' && !str_starts_with($arg, '-')) {
        $root = rtrim(str_replace('\\', '/', $arg), '/');
    }
}

require_once $root . '/config/deploy_domains.php';

$envFile = $root . '/.env';
if (is_readable($envFile)) {
    require_once $root . '/config/env.php';
    frontend_load_dotenv($root);
}

if (!is_file($root . '/admin/app/Core/AdminDatabase.php')) {
    fwrite(STDERR, "AdminDatabase not found — run on backend host (bo-nexthub.site).\n");
    exit(1);
}

if (!defined('ADMIN_APP_PATH')) {
    define('ADMIN_APP_PATH', $root . '/admin/app');
}
require_once ADMIN_APP_PATH . '/Core/AdminDatabase.php';

$frontendOrigin = rtrim(deploy_domain('frontend_url'), '/');
$backendOrigin = rtrim(deploy_domain('backend_url'), '/');

$replacements = [
    'https://admin.metropolcasino.test' => $backendOrigin,
    'http://admin.metropolcasino.test' => $backendOrigin,
    'https://metropolcasino.test' => $frontendOrigin,
    'http://metropolcasino.test' => $frontendOrigin,
    'https://m.metropolcasino.test' => deploy_domain('mobile_url'),
    'http://m.metropolcasino.test' => deploy_domain('mobile_url'),
];

$pdo = AdminDatabase::pdo();
$columns = [
    'logo_url',
    'favicon_url',
    'manifest_url',
    'og_image_url',
    'frontend_url',
    'backend_url',
    'backend_api_base_url',
    'partnership_url',
    'live_support_url',
    'callback_url',
    'whatsapp_url',
    'telegram_url',
];

$updated = 0;
foreach ($columns as $column) {
    $stmt = $pdo->prepare(
        'SELECT id, `' . str_replace('`', '``', $column) . '` AS value FROM site_ayarlar'
    );
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = (int) ($row['id'] ?? 0);
        $value = (string) ($row['value'] ?? '');
        if ($id <= 0 || $value === '') {
            continue;
        }
        $newValue = $value;
        foreach ($replacements as $from => $to) {
            if (str_contains($newValue, $from)) {
                $newValue = str_replace($from, $to, $newValue);
            }
        }
        if (in_array($column, ['partnership_url', 'callback_url'], true) && preg_match('#^https?://#i', $newValue)) {
            $path = (string) (parse_url($newValue, PHP_URL_PATH) ?? '');
            if ($path !== '' && str_contains($newValue, (string) parse_url($backendOrigin, PHP_URL_HOST))) {
                $newValue = $path;
            }
        }
        if ($column === 'logo_url' && str_contains($newValue, '/assets/images/')) {
            $path = (string) (parse_url($newValue, PHP_URL_PATH) ?? '');
            if ($path !== '') {
                $newValue = $path;
            }
        }
        if ($newValue === $value) {
            continue;
        }
        $update = $pdo->prepare(
            'UPDATE site_ayarlar SET `' . str_replace('`', '``', $column) . '` = :value WHERE id = :id'
        );
        $update->execute(['value' => $newValue, 'id' => $id]);
        $updated++;
        echo "site_ayarlar#{$id}.{$column}: {$value} -> {$newValue}\n";
    }
}

echo $updated > 0
    ? "Updated {$updated} column value(s).\n"
    : "No stale URLs found in site_ayarlar.\n";
