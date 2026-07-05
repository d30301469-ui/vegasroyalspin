#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Apache / zip / API bağlantı teşhisi ve otomatik .htaccess düzeltmesi.
 *
 * Usage:
 *   cd /www/wwwroot/vegasroyalspin.com && php deploy/aapanel/fix-apache-deploy.php
 *   cd /www/wwwroot/admin.vegasroyalspin.com && php deploy/aapanel/fix-apache-deploy.php
 */

$root = dirname(__DIR__, 2);
foreach (array_slice($argv, 1) as $arg) {
    if (trim($arg) !== '' && !str_starts_with($arg, '-')) {
        $root = rtrim(str_replace('\\', '/', $arg), '/');
    }
}

$isBackend = is_readable($root . '/services/MegaPayzService.php')
    || is_readable($root . '/services/PaymentCallbackService.php');
$role = $isBackend ? 'backend' : 'frontend';
$host = $isBackend ? 'admin.vegasroyalspin.com' : 'vegasroyalspin.com';

echo "=== fix-apache-deploy ({$role}: {$host}) ===\n";
echo "Root: {$root}\n\n";

$errors = [];
$fixes = [];

// ── 1. Kritik dosyalar ───────────────────────────────────────────────────────
foreach (['index.php' => 'Ana giriş', '.htaccess' => 'Apache rewrite'] as $file => $label) {
    if (!is_file($root . '/' . $file)) {
        $errors[] = "Eksik: {$file} ({$label})";
        echo "[FAIL] {$file}\n";
    } else {
        echo "[OK] {$file}\n";
    }
}

if ($role === 'frontend' && !is_file($root . '/index.php')) {
    $nested = $root . '/frontend-host/index.php';
    if (is_file($nested)) {
        $errors[] = 'Zip yanlış açılmış: dosyalar frontend-host/ altında. İçeriği bir üst dizine taşıyın.';
        echo "[FAIL] frontend-host/ alt klasörü algılandı — zip köküne taşıyın\n";
    }
}

// ── 2. .htaccess düzelt ─────────────────────────────────────────────────────
$htSource = $role === 'frontend'
    ? $root . '/deploy/apache/vegasroyalspin.com.htaccess'
    : $root . '/deploy/apache/admin.vegasroyalspin.com.htaccess';
$htTarget = $root . '/.htaccess';

if (is_readable($htSource)) {
    $canonical = (string) file_get_contents($htSource);
    $current = is_readable($htTarget) ? (string) file_get_contents($htTarget) : '';
    if ($current !== $canonical) {
        if (is_file($htTarget)) {
            copy($htTarget, $htTarget . '.bak.' . date('YmdHis'));
        }
        file_put_contents($htTarget, $canonical);
        $fixes[] = 'Güncel deploy .htaccess kopyalandı';
        echo "[FIX] .htaccess güncellendi\n";
    } else {
        echo "[OK] .htaccess güncel\n";
    }
} else {
    echo "[WARN] deploy/apache/*.htaccess pakette yok\n";
}

if ($role === 'frontend') {
    $apiHt = $root . '/deploy/apache/frontend-api.htaccess';
    if (is_readable($apiHt) && is_dir($root . '/api')) {
        $apiTarget = $root . '/api/.htaccess';
        $apiCanonical = (string) file_get_contents($apiHt);
        $apiCurrent = is_readable($apiTarget) ? (string) file_get_contents($apiTarget) : '';
        if ($apiCurrent !== $apiCanonical) {
            file_put_contents($apiTarget, $apiCanonical);
            $fixes[] = 'api/.htaccess frontend sürümüne düzeltildi';
            echo "[FIX] api/.htaccess (frontend-safe)\n";
        }
    }
    $fallback = $root . '/deploy/apache/fallback-index.html';
    if (is_readable($fallback) && !is_file($root . '/index.html')) {
        copy($fallback, $root . '/index.html');
        $fixes[] = 'index.html yedek sayfa eklendi';
        echo "[FIX] index.html fallback eklendi\n";
    }
}

// ── 3. Loopback + redirect döngüsü ───────────────────────────────────────────
if (function_exists('curl_init')) {
    $ch = curl_init('http://127.0.0.1/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ['Host: ' . $host],
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);

    if ($http >= 300 && $http < 400) {
        $errors[] = "HTTP {$http} yönlendirme döngüsü riski → {$redirect}. aaPanel Force HTTPS KAPAT, Cloudflare Flexible kullanın.";
        echo "[FAIL] Loopback / → HTTP {$http} Location: {$redirect}\n";
    } elseif ($http === 200) {
        echo "[OK] Loopback / → HTTP 200\n";
    } elseif ($http === 503) {
        echo "[WARN] Loopback / → HTTP 503 (index.php eksik)\n";
    } else {
        echo "[WARN] Loopback / → HTTP {$http}\n";
    }

    // API test (frontend → backend veya backend doğrudan)
    if ($role === 'backend') {
        $apiUrl = 'http://127.0.0.1/api/v2/site_settings.php';
        $apiHost = 'api.vegasroyalspin.com';
    } else {
        $apiUrl = 'http://127.0.0.1/api/v2/content/sliders?category=home';
        $apiHost = $host;
    }
    $ch2 = curl_init($apiUrl);
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER => [
            'Host: ' . $apiHost,
            'Accept: application/json',
            'Origin: https://vegasroyalspin.com',
        ],
    ]);
    $apiBody = curl_exec($ch2);
    $apiHttp = (int) curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    if ($apiHttp === 200 && is_string($apiBody) && str_contains($apiBody, '"success"')) {
        echo "[OK] API loopback {$apiUrl} Host:{$apiHost} → HTTP 200 JSON\n";
    } else {
        $errors[] = "API loopback başarısız (HTTP {$apiHttp}). Backend .env ve api.vegasroyalspin.com DNS/SSL kontrol edin.";
        echo "[FAIL] API loopback HTTP {$apiHttp}\n";
    }
}

// ── 4. SSL / api subdomain notu ─────────────────────────────────────────────
if ($role === 'backend') {
    echo "\n--- api.vegasroyalspin.com SSL (AH01909) ---\n";
    echo "Uyarı normaldir aaPanel'de yanlış origin sertifikası varsa.\n";
    echo "Çözüm: Cloudflare'de api A kaydını proxied (turuncu bulut) yapın.\n";
    echo "       aaPanel'de api için ayrı 443 site AÇMAYIN — admin.vegasroyalspin.com ile aynı docroot.\n";
    echo "       Cloudflare SSL = Flexible, aaPanel Force HTTPS = KAPAT.\n";
    echo "Sonra: php deploy/aapanel/fix-backend-env.php\n";
} else {
    echo "\n--- Sonraki adımlar ---\n";
    echo "php deploy/aapanel/fix-frontend-env.php\n";
    echo "https://{$host}/install-status.php\n";
}

echo "\n";
if ($fixes !== []) {
    echo "Uygulanan düzeltmeler:\n- " . implode("\n- ", $fixes) . "\n\n";
}
if ($errors !== []) {
    fwrite(STDERR, "Kalan sorunlar:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "Tamam.\n";
