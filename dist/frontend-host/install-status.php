<?php

declare(strict_types=1);

/**
 * Kurulum durumu — Apache/PHP çalışıyorsa /install gelmeden önce buradan teşhis.
 * Kurulum sihirbazı vendor/ gerektirmez; bu dosya da gerektirmez.
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$root = __DIR__;
$started = microtime(true);

$result = [
    'ok' => true,
    'role' => 'unknown',
    'time' => gmdate('c'),
    'php' => PHP_VERSION,
    'checks' => [],
    'hints' => [],
    'install_url' => '/install',
];

$isBackend = is_readable($root . '/services/MegaPayzService.php')
    || is_readable($root . '/services/PaymentCallbackService.php');
$result['role'] = $isBackend ? 'backend' : 'frontend';

$result['checks']['htaccess'] = is_file($root . '/.htaccess') ? 'ok' : 'missing';
$result['checks']['index_php'] = is_file($root . '/index.php') ? 'ok' : 'missing';
$result['checks']['install_php'] = is_file($root . '/install.php') ? 'ok' : 'missing';
$result['checks']['vendor'] = is_file($root . '/vendor/autoload.php') ? 'ok' : 'missing';

$lockPath = $root . '/storage/install.lock';
$envPath = $root . '/.env';
$result['checks']['env_file'] = is_readable($envPath) ? 'present' : 'missing';
$result['checks']['install_lock'] = is_file($lockPath) ? 'present' : 'missing';

$storageWritable = is_dir($root . '/storage') && is_writable($root . '/storage');
$result['checks']['storage_writable'] = $storageWritable ? 'ok' : 'fail';

if (!$storageWritable) {
    $result['ok'] = false;
    $result['hints'][] = 'storage/ yazılabilir olmalı (chmod 755 veya 775, sahip: www).';
}

$installed = false;
$installReason = '';

try {
    if ($isBackend && is_readable($root . '/app/Core/AdminInstallGate.php')) {
        require_once $root . '/app/Core/AdminInstallGate.php';
        $installed = AdminInstallGate::isInstalled($root);
        if ($installed) {
            $installReason = is_file($lockPath) ? 'install.lock mevcut' : 'legacy .env + veritabanı algılandı';
        }
    } elseif (is_readable($root . '/app/Core/FrontendInstallGate.php')) {
        require_once $root . '/app/Core/FrontendInstallGate.php';
        $installed = FrontendInstallGate::isInstalled($root);
        if ($installed) {
            $installReason = is_file($lockPath) ? 'install.lock mevcut' : 'geçerli .env algılandı (otomatik kilit)';
        }
    }
} catch (Throwable $exception) {
    $result['checks']['install_gate_error'] = $exception->getMessage();
}

$result['checks']['is_installed'] = $installed ? 'yes' : 'no';
if ($installReason !== '') {
    $result['checks']['installed_reason'] = $installReason;
}

if ($installed) {
    $result['hints'][] = '/install sihirbazı atlanır (site kurulu sayılıyor). Yeniden kurulum: storage/install.lock silin, gerekirse .env silin, sonra /install açın.';
} elseif ($result['checks']['env_file'] === 'present' && $result['checks']['install_lock'] === 'missing') {
    $result['hints'][] = '.env var ama kurulum tamamlanmamış olabilir — /install adresini doğrudan açın.';
} else {
    $result['hints'][] = 'Tarayıcıda /install açın (önce backend bo-nexthub.site, sonra frontend).';
}

if ($result['checks']['vendor'] === 'missing') {
    $result['ok'] = false;
    $result['hints'][] = 'vendor/ eksik — güncel zip yükleyin (sunucuda composer gerekmez).';
}

if ($result['checks']['htaccess'] === 'missing') {
    $result['ok'] = false;
    $result['hints'][] = '.htaccess eksik — zip kökündeki .htaccess dosyasını yükleyin.';
}

if ($result['checks']['env_file'] === 'present' && is_readable($root . '/config/env.php')) {
    require_once $root . '/config/env.php';
    frontend_load_dotenv($root);
    if (is_readable($root . '/app/Services/InstallEnvBuilder.php')) {
        require_once $root . '/app/Services/InstallEnvBuilder.php';
        $envSnapshot = [];
        foreach ([
            'SITE_URL', 'FRONTEND_URL', 'BACKEND_URL', 'API_PUBLIC_BASE_URL', 'API_BACKEND_MAIN_BASE_URL',
            'FRONTEND_API_ONLY', 'FRONTEND_DIRECT_MEMBER_API', 'MEMBER_JWT_SECRET', 'FRONTEND_CMS_PURGE_SECRET',
            'SESSION_COOKIE_DOMAIN', 'API_BACKEND_INTERNAL_BASE_URL',
        ] as $envKey) {
            $val = trim(frontend_env_string($envKey, ''));
            if ($val !== '') {
                $envSnapshot[$envKey] = in_array($envKey, ['MEMBER_JWT_SECRET', 'FRONTEND_CMS_PURGE_SECRET', 'APP_KEY'], true)
                    ? '(set, ' . strlen($val) . ' chars)'
                    : $val;
            }
        }
        if ($envSnapshot !== []) {
            $result['env'] = $envSnapshot;
        }
        $envForValidation = [];
        $validationKeys = $isBackend
            ? [
                'BACKEND_URL', 'API_PUBLIC_BASE_URL', 'API_BACKEND_MAIN_BASE_URL',
                'MEMBER_JWT_SECRET', 'FRONTEND_CMS_PURGE_SECRET', 'APP_KEY',
                'DB_HOST', 'DB_DATABASE', 'DB_USERNAME',
            ]
            : [
                'SITE_URL', 'FRONTEND_URL', 'BACKEND_URL', 'API_PUBLIC_BASE_URL', 'API_BACKEND_MAIN_BASE_URL',
                'MEMBER_JWT_SECRET', 'FRONTEND_CMS_PURGE_SECRET', 'APP_KEY', 'SESSION_COOKIE_DOMAIN',
                'FRONTEND_API_ONLY', 'FRONTEND_DIRECT_MEMBER_API', 'API_BACKEND_INTERNAL_BASE_URL',
            ];
        foreach ($validationKeys as $envKey) {
            $envForValidation[$envKey] = trim(frontend_env_string($envKey, ''));
        }
        $envErrors = $isBackend
            ? InstallEnvBuilder::validateBackendEnv($envForValidation)
            : InstallEnvBuilder::validateSplitFrontendEnv($envForValidation);
        if ($envErrors !== []) {
            $result['env_errors'] = $envErrors;
            $result['ok'] = false;
            $result['hints'][] = 'Ortam hatası: ' . implode('; ', array_slice($envErrors, 0, 3));
            if (!$isBackend) {
                $result['hints'][] = 'Onar: php deploy/aapanel/fix-frontend-env.php';
            } else {
                $result['hints'][] = 'Onar: php deploy/aapanel/fix-backend-env.php';
            }
        } else {
            $result['checks']['env_valid'] = 'ok';
        }
    }
}

$result['ms'] = (int) round((microtime(true) - $started) * 1000);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
