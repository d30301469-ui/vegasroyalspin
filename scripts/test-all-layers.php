<?php

declare(strict_types=1);

/**
 * Split-deploy katman testi — statik + yerel simülasyon (+ isteğe bağlı canlı probe).
 *
 * Usage:
 *   php scripts/test-all-layers.php
 *   RUN_LIVE_PROBES=1 php scripts/test-all-layers.php
 */

$root = dirname(__DIR__);
$runLive = in_array('--live', $argv ?? [], true)
    || getenv('RUN_LIVE_PROBES') === '1';

/** @var list<array{layer: string, name: string, status: string, detail: string}> */
$results = [];
$failCount = 0;
$warnCount = 0;

function layer_record(string $layer, string $name, string $status, string $detail = ''): void
{
    global $results, $failCount, $warnCount;
    $results[] = ['layer' => $layer, 'name' => $name, 'status' => $status, 'detail' => $detail];
    if ($status === 'FAIL') {
        $failCount++;
    } elseif ($status === 'WARN') {
        $warnCount++;
    }
}

function layer_pass(string $layer, string $name, string $detail = ''): void
{
    layer_record($layer, $name, 'OK', $detail);
}

function layer_fail(string $layer, string $name, string $detail): void
{
    layer_record($layer, $name, 'FAIL', $detail);
}

function layer_warn(string $layer, string $name, string $detail): void
{
    layer_record($layer, $name, 'WARN', $detail);
}

// ── Layer 1: PHP syntax ──────────────────────────────────────────────────────
$syntaxFiles = [
    'index.php',
    'health.php',
    'diagnose.php',
    'config/env.php',
    'config/bootstrap_api.php',
    'config/backend_api.php',
    'services/frontend_api_dispatch.php',
    'services/PublicApiV2Dispatcher.php',
    'services/BackendMemberApiProxy.php',
    'services/BackendApiClient.php',
    'services/BackendConnectivityProbe.php',
    'services/SplitDeployDiagnostics.php',
    'api/CmsRemote.php',
    'api/MediaUrl.php',
    'app/Services/Api/PublicMemberApiDispatcher.php',
    'admin/api/v2/includes/member_api_kernel.php',
];

foreach ($syntaxFiles as $rel) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        layer_fail('syntax', $rel, 'file missing');
        continue;
    }
    $out = [];
    $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        layer_fail('syntax', $rel, implode(' ', $out));
    } else {
        layer_pass('syntax', $rel);
    }
}

// ── Layer 2: Verify scripts ──────────────────────────────────────────────────
foreach (['verify-api-sync.php', 'verify-split-deploy.php', 'verify-services-sync.php', 'verify-route-module-coverage.php', 'audit-public-apis.php'] as $script) {
    $path = $root . '/scripts/' . $script;
    if (!is_file($path)) {
        layer_warn('verify', $script, 'missing');
        continue;
    }
    $out = [];
    $code = 0;
    exec(PHP_BINARY . ' ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        layer_fail('verify', $script, implode(' | ', $out));
    } else {
        layer_pass('verify', $script, trim(implode(' ', $out)));
    }
}

// ── Layer 3: Legacy route → allowlist parity ─────────────────────────────────
require_once $root . '/services/frontend_api_dispatch.php';
require_once $root . '/app/Services/Api/PublicMemberApiDispatcher.php';

$legacyPaths = [
    '/api/sliders' => 'content/sliders',
    '/api/footer' => 'content/footer',
    '/api/mobile-menu' => 'content/mobile-menu',
    '/api/homepage-sections' => 'content/homepage-sections',
    '/api/promotions' => 'content/promotions',
    '/api/site_settings' => 'site_settings.php',
    '/api/winners' => 'winners.php',
    '/api/sports' => 'sports',
    '/api/v2/content/sliders' => 'content/sliders',
    '/api/content/footer' => 'content/footer',
];

foreach ($legacyPaths as $path => $expected) {
    $mapped = metropol_public_api_route_from_path($path);
    if ($mapped !== $expected) {
        layer_fail('routes', $path, "mapped={$mapped}, expected={$expected}");
        continue;
    }
    if (!\App\Services\Api\PublicMemberApiDispatcher::isAllowed($mapped)) {
        layer_fail('routes', $path, "allowlist rejects {$mapped}");
        continue;
    }
    layer_pass('routes', $path, $mapped);
}

if (!metropol_request_is_fast_public_api('/api/v2/content/sliders')) {
    layer_fail('routes', 'fast-path', '/api/v2/* must use fast path');
} else {
    layer_pass('routes', 'fast-path', 'all /api/*');
}

$dispatchSrc = (string) file_get_contents($root . '/services/frontend_api_dispatch.php');
if (!str_contains($dispatchSrc, 'frontend_session.php')) {
    layer_fail('routes', 'frontend_session', 'fast API path must load frontend_session.php for login proxy');
} else {
    layer_pass('routes', 'frontend_session', 'loaded in metropol_dispatch_frontend_public_api');
}

$proxySrc = (string) file_get_contents($root . '/services/BackendMemberApiProxy.php');
if (!str_contains($proxySrc, 'ensureFrontendSession')) {
    layer_fail('routes', 'proxy-session', 'BackendMemberApiProxy must ensure frontend session');
} else {
    layer_pass('routes', 'proxy-session', 'ensureFrontendSession()');
}

// ── Layer 4: CMS routes on backend kernel ────────────────────────────────────
$kernelSrc = is_file($root . '/admin/api/v2/includes/member_api_kernel.php')
    ? (string) file_get_contents($root . '/admin/api/v2/includes/member_api_kernel.php')
    : '';
$cmsRoutesGlob = glob($root . '/admin/api/v2/routes/member_*.php') ?: [];
$cmsSrc = $kernelSrc;
foreach ($cmsRoutesGlob as $f) {
    $cmsSrc .= (string) file_get_contents($f);
}

foreach ([
    'content/sliders',
    'content/footer',
    'content/mobile-menu',
    'content/homepage-sections',
    'content/promotions',
    'content/auth-sliders',
    'site_settings.php',
] as $needle) {
    if (!str_contains($cmsSrc, $needle)) {
        layer_fail('backend-cms', $needle, 'not referenced in member route modules');
    } else {
        layer_pass('backend-cms', $needle);
    }
}

// ── Layer 5: BackendConnectivityProbe envelope ───────────────────────────────
require_once $root . '/services/BackendConnectivityProbe.php';

$frontendHealth = [
    'ok' => true,
    'role' => 'frontend',
    'checks' => ['env_file' => 'ok'],
];
if (BackendConnectivityProbe::isBackendSiteSettingsEnvelope($frontendHealth)) {
    layer_fail('probe', 'envelope-reject-frontend-health', 'health.json false positive');
} else {
    layer_pass('probe', 'envelope-reject-frontend-health');
}

$validSettings = [
    'success' => true,
    'code' => 200,
    'data' => ['site_name' => 'Test', 'logo' => '/uploads/logo.png'],
];
if (!BackendConnectivityProbe::isBackendSiteSettingsEnvelope($validSettings)) {
    layer_fail('probe', 'envelope-accept-site-settings', 'valid envelope rejected');
} else {
    layer_pass('probe', 'envelope-accept-site-settings');
}

$probes = [
    'public_https' => ['ok' => false],
    'loopback_127_0_0_1' => ['ok' => true, 'member_api' => 'yes'],
];
if (!BackendConnectivityProbe::isBackendUsable($probes)) {
    layer_fail('probe', 'isBackendUsable-loopback', 'loopback with member_api should be usable');
} else {
    layer_pass('probe', 'isBackendUsable-loopback');
}

$probesPingOnly = [
    'loopback_127_0_0_1' => ['ok' => true, 'member_api' => 'no'],
    'loopback_127_0_0_1_ping' => ['ok' => true],
];
if (BackendConnectivityProbe::isBackendUsable($probesPingOnly)) {
    layer_fail('probe', 'isBackendUsable-ping-only', 'ping-only must not count as usable');
} else {
    layer_pass('probe', 'isBackendUsable-ping-only');
}

// ── Layer 6: ApiMediaUrl ─────────────────────────────────────────────────────
require_once $root . '/api/MediaUrl.php';

putenv('BACKEND_URL=https://bo-nexthub.site');
$_ENV['BACKEND_URL'] = 'https://bo-nexthub.site';
$_SERVER['BACKEND_URL'] = 'https://bo-nexthub.site';

$uploadResolved = ApiMediaUrl::resolve('/uploads/slider/test.webp');
if ($uploadResolved !== 'https://bo-nexthub.site/uploads/slider/test.webp') {
    layer_fail('media', 'uploads-resolve', "got {$uploadResolved}");
} else {
    layer_pass('media', 'uploads-resolve');
}

$assetResolved = ApiMediaUrl::resolve('/assets/images/logo.png');
if ($assetResolved !== '/assets/images/logo.png') {
    layer_fail('media', 'assets-local', "got {$assetResolved}");
} else {
    layer_pass('media', 'assets-local');
}

$absResolved = ApiMediaUrl::resolve('https://cdn.example/x.png');
if ($absResolved !== 'https://cdn.example/x.png') {
    layer_fail('media', 'absolute-url', "got {$absResolved}");
} else {
    layer_pass('media', 'absolute-url');
}

// ── Layer 7: Circuit breaker (env helpers) ───────────────────────────────────
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $root);
}
require_once $root . '/config/env.php';

putenv('FRONTEND_API_ONLY=1');
$_ENV['FRONTEND_API_ONLY'] = '1';
putenv('FRONTEND_CMS_CIRCUIT_SECONDS=8');
$_ENV['FRONTEND_CMS_CIRCUIT_SECONDS'] = '8';

$circuitPath = metropol_cms_api_circuit_cache_path();
$circuitDir = dirname($circuitPath);
if (!is_dir($circuitDir)) {
    @mkdir($circuitDir, 0755, true);
}
@unlink($circuitPath);

if (metropol_cms_api_circuit_is_open()) {
    layer_fail('circuit', 'initial-closed', 'circuit should start closed');
} else {
    layer_pass('circuit', 'initial-closed');
}

metropol_cms_api_mark_failure();
if (!metropol_cms_api_circuit_is_open()) {
    layer_fail('circuit', 'open-after-failure', 'circuit should open after failure');
} else {
    layer_pass('circuit', 'open-after-failure');
}

if (!metropol_should_skip_remote_backend()) {
    layer_fail('circuit', 'skip-remote-when-open', 'skip_remote should follow circuit when internal URL set');
} else {
    layer_pass('circuit', 'skip-remote-when-open');
}

putenv('API_BACKEND_INTERNAL_BASE_URL=http://127.0.0.1/api/v2');
$_ENV['API_BACKEND_INTERNAL_BASE_URL'] = 'http://127.0.0.1/api/v2';

metropol_cms_api_mark_success();
if (metropol_backend_api_circuit_is_open()) {
    layer_fail('circuit', 'backend-alias-closed', 'backend circuit alias should match cms circuit');
} else {
    layer_pass('circuit', 'backend-alias-closed');
}
@unlink($circuitPath);

// ── Layer 8: ApiCmsRemote API-only guard ─────────────────────────────────────
require_once $root . '/api/CmsRemote.php';

putenv('FRONTEND_API_ONLY=1');
if (ApiCmsRemote::canUseLocalDatabase()) {
    layer_fail('cms', 'api-only-no-db', 'canUseLocalDatabase must be false on API-only');
} else {
    layer_pass('cms', 'api-only-no-db');
}

// ── Layer 8b: Product banners CMS + fallback ───────────────────────────────────
require_once $root . '/api/ProductBanners.php';

$guestBanners = ApiProductBanners::fetch(false);
$memberBanners = ApiProductBanners::fetch(true);
if (count($guestBanners['items'] ?? []) < 1) {
    layer_fail('cms', 'product-banners-default', 'default banner list empty');
} else {
    layer_pass('cms', 'product-banners-default');
}

$depositGuest = null;
$depositMember = null;
foreach ($guestBanners['items'] as $item) {
    if (($item['aria'] ?? '') === 'Yatırım') {
        $depositGuest = $item;
        break;
    }
}
foreach ($memberBanners['items'] as $item) {
    if (($item['aria'] ?? '') === 'Yatırım') {
        $depositMember = $item;
        break;
    }
}
if (!is_array($depositGuest) || !is_array($depositMember)) {
    layer_fail('cms', 'product-banners-deposit', 'deposit banner missing');
} elseif (($depositGuest['onclick'] ?? '') === '' || !str_contains((string) $depositMember['href'], 'deposit')) {
    layer_fail('cms', 'product-banners-login-gate', 'login gate href/onclick mismatch');
} else {
    layer_pass('cms', 'product-banners-login-gate');
}

// ── Layer 9: Apache / deploy htaccess ──────────────────────────────────────────
$feHtaccess = $root . '/deploy/apache/vegasroyalspin.com.htaccess';
if (is_file($feHtaccess)) {
    $ht = (string) file_get_contents($feHtaccess);
    foreach (['RewriteEngine On', 'HTTP_AUTHORIZATION', 'index.php', 'index.php !-f'] as $needle) {
        if (!str_contains($ht, $needle)) {
            layer_fail('apache', 'frontend-' . $needle, 'missing in vegasroyalspin.com.htaccess');
        } else {
            layer_pass('apache', 'frontend-' . $needle);
        }
    }
} else {
    layer_fail('apache', 'frontend-htaccess', 'file missing');
}

$beHtaccess = $root . '/deploy/apache/bo-nexthub.site.htaccess';
if (!is_file($beHtaccess)) {
    layer_fail('apache', 'backend-htaccess', 'file missing');
} else {
    layer_pass('apache', 'backend-htaccess');
}

$feApiHt = $root . '/deploy/apache/frontend-api.htaccess';
if (!is_file($feApiHt)) {
    layer_fail('apache', 'frontend-api-htaccess', 'deploy/apache/frontend-api.htaccess missing');
} elseif (str_contains((string) file_get_contents($feApiHt), 'RewriteRule ^v2')) {
    layer_fail('apache', 'frontend-api-htaccess', 'frontend api/.htaccess must not route v2 locally');
} else {
    layer_pass('apache', 'frontend-api-htaccess');
}

// ── Layer 10: PublicApiV2Dispatcher CMS purge route (static) ─────────────────
$dispatcherSrc = (string) file_get_contents($root . '/services/PublicApiV2Dispatcher.php');
if (!str_contains($dispatcherSrc, 'tryDispatchCmsCachePurge')) {
    layer_fail('dispatcher', 'cms-cache-purge', 'purge handler missing');
} elseif (!str_contains($dispatcherSrc, 'FRONTEND_CMS_PURGE_SECRET')) {
    layer_fail('dispatcher', 'cms-purge-secret', 'secret check missing');
} else {
    layer_pass('dispatcher', 'cms-cache-purge');
}

// ── Layer 11: BackendMemberApiProxy game-launch timeout ──────────────────────
$proxySrc = (string) file_get_contents($root . '/services/BackendMemberApiProxy.php');
if (!preg_match('#game[-_/]?launch.*max\(\$timeout,\s*(45|60|90)\)#s', $proxySrc)) {
    layer_fail('proxy', 'game-launch-timeout', 'min 45s game-launch timeout not found');
} else {
    layer_pass('proxy', 'game-launch-timeout');
}

// ── Layer 12: Live probes (optional) ─────────────────────────────────────────
if ($runLive) {
    $liveTargets = [
        'backend-ping' => 'https://bo-nexthub.site/ping.php',
        'backend-site-settings' => 'https://bo-nexthub.site/api/v2/site_settings.php',
        'frontend-health' => 'https://vegasroyalspin.com/health.php',
        'frontend-sliders' => 'https://vegasroyalspin.com/api/v2/content/sliders?category=home',
    ];

    foreach ($liveTargets as $label => $url) {
        $probe = BackendConnectivityProbe::curl($url, [], 15);
        $body = is_string($probe['body'] ?? null) ? $probe['body'] : '';
        $decoded = json_decode($body, true);
        $decoded = is_array($decoded) ? $decoded : null;
        $memberApi = is_array($decoded)
            && BackendConnectivityProbe::isBackendSiteSettingsEnvelope($decoded);

        if (!$probe['ok']) {
            layer_fail('live', $label, ($probe['error'] !== '' ? $probe['error'] : 'http_' . $probe['http']));
            continue;
        }

        if (str_contains($label, 'site-settings') && !$memberApi) {
            layer_fail('live', $label, 'response is not valid site_settings envelope');
            continue;
        }

        if (str_contains($label, 'health') && is_array($decoded) && ($decoded['role'] ?? '') !== 'frontend') {
            layer_warn('live', $label, 'unexpected role in health response');
            continue;
        }

        layer_pass('live', $label, 'http=' . $probe['http']);
    }
} else {
    layer_warn('live', 'skipped', 'Set RUN_LIVE_PROBES=1 or pass --live for production curl tests');
}

// ── Layer: Member API CORS ───────────────────────────────────────────────────
require_once $root . '/config/deploy_domains.php';
$corsFile = $root . '/admin/api/v2/includes/member_api_cors.php';
if (!is_readable($corsFile)) {
    layer_fail('cors', 'member_api_cors-file', 'missing admin/api/v2/includes/member_api_cors.php');
} else {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    unset($_SERVER['HTTP_ORIGIN']);
    require_once $corsFile;
    $corsOrigins = member_api_allowed_origins();
    if (!in_array('https://vegasroyalspin.com', $corsOrigins, true)) {
        layer_fail('cors', 'allowed-origin-frontend', 'https://vegasroyalspin.com not in allowed origins');
    } else {
        layer_pass('cors', 'allowed-origin-frontend');
    }
    if (!in_array('https://www.vegasroyalspin.com', $corsOrigins, true)) {
        layer_fail('cors', 'allowed-origin-www', 'www variant missing');
    } else {
        layer_pass('cors', 'allowed-origin-www');
    }
}

$apiHtaccess = (string) file_get_contents($root . '/admin/api/.htaccess');
if (str_contains($apiHtaccess, 'RewriteRule ^.*$ ../admin/index.php')) {
    layer_fail('cors', 'api-htaccess-v2-hijack', 'api/.htaccess still hijacks all routes to ../admin/index.php');
} elseif (!str_contains($apiHtaccess, 'v2/index.php')) {
    layer_fail('cors', 'api-htaccess-v2-route', 'api/.htaccess missing v2/index.php route');
} else {
    layer_pass('cors', 'api-htaccess-v2-route');
}

// ── Report ───────────────────────────────────────────────────────────────────
$byLayer = [];
foreach ($results as $row) {
    $byLayer[$row['layer']][] = $row;
}

echo "=== Split-deploy layer test ===\n";
foreach ($byLayer as $layer => $rows) {
    $ok = count(array_filter($rows, static fn ($r) => $r['status'] === 'OK'));
    $fail = count(array_filter($rows, static fn ($r) => $r['status'] === 'FAIL'));
    $warn = count(array_filter($rows, static fn ($r) => $r['status'] === 'WARN'));
    echo sprintf("\n[%s] %d ok, %d fail, %d warn\n", strtoupper($layer), $ok, $fail, $warn);
    foreach ($rows as $row) {
        if ($row['status'] === 'OK') {
            continue;
        }
        $suffix = $row['detail'] !== '' ? ' — ' . $row['detail'] : '';
        echo '  ' . $row['status'] . ' ' . $row['name'] . $suffix . "\n";
    }
}

echo sprintf(
    "\nTOTAL: %d checks, %d FAIL, %d WARN\n",
    count($results),
    $failCount,
    $warnCount
);

exit($failCount > 0 ? 1 : 0);
