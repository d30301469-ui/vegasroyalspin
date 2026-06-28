<?php

declare(strict_types=1);

/**
 * Public API route parity audit for split deploy.
 *
 * Usage: php scripts/audit-public-apis.php
 */

$root = dirname(__DIR__);
$errors = [];
$warnings = [];

require_once $root . '/app/Services/Api/PublicMemberApiDispatcher.php';

$homepageRoutes = [
    'content/sliders',
    'content/footer',
    'content/mobile-menu',
    'content/homepage-sections',
    'content/promotions',
    'content/auth-sliders',
    'site-settings',
    'winners',
    'announcements',
    'track-visit',
    'auth/register',
    'auth/login',
    'auth/session',
    'auth/password-reset',
    'member-inbox-messages',
];

$profileRoutes = [
    'balance',
    'loyalty',
    'profile/detail',
    'profile/update',
    'profile/casino-game-history',
    'profile/spor-bet-detail',
    'profile/game-history-detail',
    'deposit-history',
    'withdraw-history',
    'payment-methods',
    'game-launch',
    'referrals',
    'bonus/use-code',
];

$metadataRoutes = [
    'config',
    'maintenance-status',
    'countries',
    'currencies',
    'languages',
    'auth/verify-phone',
];

$casinoRoutes = [
    'games',
    'games.php',
    'game-launch',
    'game_launch.php',
    'games-provider',
    'games_provider.php',
    'favorite-slots',
    'favorite-live-casino',
    'games/search',
];

$kernelSrc = (string) file_get_contents($root . '/admin/api/v2/includes/member_api_kernel.php');
$routesGlob = glob($root . '/admin/api/v2/routes/member_*.php') ?: [];
$backendSrc = $kernelSrc;
foreach ($routesGlob as $file) {
    $backendSrc .= (string) file_get_contents($file);
}

$requiredKernelAliases = [
    'content/sliders' => 'content/sliders',
    'content/footer' => 'content/footer',
    'content/mobile-menu' => 'content/mobile-menu',
    'content/promotions' => 'content/promotions',
    'content/auth-sliders' => 'content/auth-sliders',
    'content/homepage-sections' => 'content/homepage-sections',
    'site-settings' => 'site_settings.php',
    'config' => 'site_settings.php',
    'track-visit' => 'track_visit.php',
    'profile/spor-bet-detail' => 'profile/spor_bet_detail.php',
    'profile/game-history-detail' => 'profile/game_history_detail.php',
    'profile/casino-game-history' => 'casino_game_history.php',
    'bonus-claim' => 'bonus_claim.php',
    'payments/methods' => 'payment_methods.php',
    'game-launch' => 'game_launch.php',
    'games' => 'games.php',
    'games-provider' => 'games_provider.php',
    'favorite-live-casino' => 'favorite_live_casino.php',
];

foreach ($requiredKernelAliases as $from => $to) {
    $needle = "'{$from}' => '{$to}'";
    if (!str_contains($kernelSrc, $needle)) {
        $errors[] = "member_api_kernel missing alias: {$from} => {$to}";
    }
}

foreach (array_merge($homepageRoutes, $profileRoutes, $metadataRoutes, $casinoRoutes) as $route) {
    if (!\App\Services\Api\PublicMemberApiDispatcher::isAllowed($route)) {
        $errors[] = "Allowlist missing route: {$route}";
    }
}

$backendMustHandle = [
    'profile/spor_bet_detail.php' => 'profile_detail_html.php',
    'profile/game_history_detail.php' => 'profile_detail_html.php',
    'maintenance-status' => 'maintenance-status',
    'auth/verify-phone' => 'auth/verify-phone',
    'games.php' => 'member_games.php',
    'game-launch' => 'game_launch.php',
    'favorite-live-casino' => 'favorite_live_casino.php',
];

foreach ($backendMustHandle as $needle => $fileHint) {
    if (!str_contains($backendSrc, $needle)) {
        $errors[] = "Backend handler missing reference: {$needle} (expected in {$fileHint})";
    }
}

$frontendFiles = [
    'services/frontend_api_dispatch.php',
    'services/PublicApiV2Dispatcher.php',
    'services/BackendMemberApiProxy.php',
    'deploy/apache/vegasroyalspin.com.htaccess',
    'deploy/apache/bo-nexthub.site.htaccess',
    'ping.php',
    'diagnose.php',
    'health.php',
];

foreach ($frontendFiles as $rel) {
    if (!is_file($root . '/' . $rel)) {
        $errors[] = "Missing deploy file: {$rel}";
    }
}

$htaccess = (string) file_get_contents($root . '/deploy/apache/vegasroyalspin.com.htaccess');
if (!str_contains($htaccess, 'HTTP_AUTHORIZATION')) {
    $warnings[] = 'Frontend htaccess should pass HTTP Authorization header';
}

$indexSrc = (string) file_get_contents($root . '/index.php');
if (!str_contains($indexSrc, 'metropol_handle_public_api_request')) {
    $errors[] = 'index.php must use metropol_handle_public_api_request() for /api/*';
}
if (str_contains($indexSrc, 'ApiMemberV2BridgeController')) {
    $errors[] = 'index.php must not register ApiMemberV2BridgeController routes (use PublicApiV2Dispatcher)';
}

$controllerSrc = (string) file_get_contents($root . '/app/Http/Controllers/Api/PublicMemberApiController.php');
if (!str_contains($controllerSrc, 'PublicApiV2Dispatcher::dispatch')) {
    $errors[] = 'PublicMemberApiController must delegate to PublicApiV2Dispatcher';
}

$dispatcherSrc = (string) file_get_contents($root . '/services/PublicApiV2Dispatcher.php');
if (str_contains($dispatcherSrc, 'tryDispatchApiOnlyAuth')) {
    $errors[] = 'PublicApiV2Dispatcher must not use separate auth shortcut (use transparent proxy)';
}

$memberDispatcherSrc = (string) file_get_contents($root . '/app/Services/Api/PublicMemberApiDispatcher.php');
if (str_contains($memberDispatcherSrc, 'PublicMemberApiRuntime.php')) {
    $errors[] = 'PublicMemberApiDispatcher must not require PublicMemberApiRuntime (use route modules)';
}

$dispatchSrc = (string) file_get_contents($root . '/services/frontend_api_dispatch.php');
if (!str_contains($dispatchSrc, 'function metropol_handle_public_api_request')
    || !str_contains($dispatchSrc, 'exit;')) {
    $errors[] = 'frontend_api_dispatch.php must exit after public API dispatch';
}

if ($errors !== []) {
    fwrite(STDERR, "FAIL (" . count($errors) . "):\n- " . implode("\n- ", $errors) . "\n");
    if ($warnings !== []) {
        fwrite(STDERR, "Warnings:\n- " . implode("\n- ", $warnings) . "\n");
    }
    exit(1);
}

echo "OK: " . count($homepageRoutes) . " homepage + " . count($profileRoutes) . " profile + "
    . count($metadataRoutes) . " metadata + " . count($casinoRoutes) . " casino routes audited.\n";
if ($warnings !== []) {
    echo "Warnings:\n- " . implode("\n- ", $warnings) . "\n";
}

exit(0);
