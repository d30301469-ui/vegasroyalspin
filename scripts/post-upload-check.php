<?php

declare(strict_types=1);

/**
 * Sunucuya yükleme sonrası CLI kontrol listesi.
 *
 * Usage:
 *   cd /www/wwwroot/vegasroyalspin.com && php scripts/post-upload-check.php
 *   cd /www/wwwroot/bo-nexthub.site && php scripts/post-upload-check.php
 */

$root = dirname(__DIR__);
$errors = [];
$warnings = [];

$isBackend = is_readable($root . '/services/MegaPayzService.php');
$role = $isBackend ? 'backend (bo-nexthub.site)' : 'frontend (vegasroyalspin.com)';

echo "=== Post-upload check: {$role} ===\n";
echo "Root: {$root}\n\n";

foreach ([
    'index.php' => 'Ana giriş',
    'install.php' => 'Kurulum sihirbazı',
    '.htaccess' => 'Apache rewrite',
    'ping.php' => 'Canlılık testi',
] as $file => $label) {
    if (!is_file($root . '/' . $file)) {
        $errors[] = "Eksik: {$file} ({$label})";
    } else {
        echo "[OK] {$file}\n";
    }
}

$lock = $root . '/storage/install.lock';
$env = $root . '/.env';

if (is_file($lock)) {
    $warnings[] = 'storage/install.lock VAR — /install sihirbazı gösterilmez. Yeni kurulum için silin: rm storage/install.lock';
    echo "[WARN] storage/install.lock mevcut\n";
} else {
    echo "[OK] storage/install.lock yok (kurulum açılabilir)\n";
}

if (is_readable($env)) {
    echo "[INFO] .env mevcut\n";
} else {
    echo "[INFO] .env yok (normal — /install ile oluşturulur)\n";
}

if (!is_file($root . '/vendor/autoload.php')) {
    $errors[] = 'vendor/autoload.php yok — güncel zip kullanın (vendor pakete dahil). Sunucuda composer gerekmez.';
    echo "[FAIL] vendor/autoload.php yok\n";
} else {
    echo "[OK] vendor/autoload.php (composer sunucuda gerekmez)\n";
}

$composerJson = $root . '/composer.json';
if (is_readable($composerJson)) {
    $composerRaw = (string) file_get_contents($composerJson);
    if (str_contains($composerRaw, '"admin/"') || str_contains($composerRaw, '"admin\\\\"')) {
        $errors[] = 'composer.json monorepo sürümü — frontend zip deploy/composer.frontend.json kullanmalı';
        echo "[FAIL] composer.json admin/ referansı içeriyor\n";
    } else {
        echo "[OK] composer.json (frontend deploy sürümü)\n";
    }
}

if (!is_dir($root . '/storage') || !is_writable($root . '/storage')) {
    $errors[] = 'storage/ yazılamıyor — chown www:www ve chmod 775';
    echo "[FAIL] storage/ yazılabilir değil\n";
} else {
    echo "[OK] storage/ yazılabilir\n";
}

if (is_file($root . '/install-status.php')) {
    echo "\n--- install-status.php ---\n";
    passthru('php ' . escapeshellarg($root . '/install-status.php'), $code);
    echo "\n";
}

echo "\n--- Loopback ping (Apache aynı sunucuda) ---\n";
$host = $isBackend ? 'bo-nexthub.site' : 'vegasroyalspin.com';
if (function_exists('curl_init')) {
    $ch = curl_init('http://127.0.0.1/ping.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER => ['Host: ' . $host],
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($http === 200 && is_string($body) && str_contains($body, '"pong"')) {
        echo "[OK] http://127.0.0.1/ping.php Host:{$host} → HTTP 200\n";
    } else {
        $errors[] = "Loopback ping başarısız (HTTP {$http}, {$err}) — Apache restart: systemctl restart httpd";
        echo "[FAIL] loopback ping HTTP {$http}" . ($err !== '' ? " ({$err})" : '') . "\n";
    }
} else {
    $warnings[] = 'curl eklentisi yok — loopback test atlandı';
}

if ($warnings !== []) {
    echo "\nUyarılar:\n- " . implode("\n- ", $warnings) . "\n";
}
if ($errors !== []) {
    echo "\nHatalar:\n- " . implode("\n- ", $errors) . "\n";
    exit(1);
}

echo "\nSonraki adım: tarayıcıda https://{$host}/install\n";
echo "Ping: https://{$host}/ping.php\n";
echo "Durum: https://{$host}/install-status.php\n";
if (is_file($root . '/deploy/aapanel/fix-apache-deploy.php')) {
    echo "Apache düzeltme: php deploy/aapanel/fix-apache-deploy.php\n";
}
exit(0);
