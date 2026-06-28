<?php

declare(strict_types=1);

/**
 * Ensures allowlisted public routes are handled by admin/api/v2 route modules (not legacy runtime).
 *
 * Usage: php scripts/verify-route-module-coverage.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/Services/Api/PublicMemberApiDispatcher.php';

$kernelPath = $root . '/admin/api/v2/includes/member_api_kernel.php';
$routeFiles = glob($root . '/admin/api/v2/routes/member_*.php') ?: [];
$entryPath = $root . '/admin/api/v2/member_local.php';

foreach ([$kernelPath, $entryPath] as $path) {
    if (!is_readable($path)) {
        fwrite(STDERR, "Missing required file: {$path}\n");
        exit(1);
    }
}

$backendSrc = (string) file_get_contents($kernelPath);
foreach ($routeFiles as $file) {
    $backendSrc .= (string) file_get_contents($file);
}

$aliases = [];
if (preg_match('/\$routeAliases\s*=\s*\[(.*?)\];/s', (string) file_get_contents($kernelPath), $match)) {
    if (preg_match_all("/'([^']+)'\s*=>\s*'([^']+)'/", $match[1], $pairs, PREG_SET_ORDER)) {
        foreach ($pairs as $pair) {
            $aliases[$pair[1]] = $pair[2];
        }
    }
}

$ref = new ReflectionClass(\App\Services\Api\PublicMemberApiDispatcher::class);
$routesConst = $ref->getReflectionConstant('ALLOWED_ROUTES');
$patternsConst = $ref->getReflectionConstant('ALLOWED_ROUTE_PATTERNS');
$allowedRoutes = array_keys($routesConst->getValue());
$patterns = $patternsConst->getValue();

$missing = [];
$patternOnly = [];

foreach ($allowedRoutes as $route) {
    $candidates = array_unique([$route, $aliases[$route] ?? null]);
    $found = false;
    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || $candidate === '') {
            continue;
        }
        if (routeReferencedInBackend($backendSrc, $candidate)) {
            $found = true;
            break;
        }
    }
    if ($found) {
        continue;
    }

    foreach ($patterns as $pattern) {
        if (@preg_match($pattern, $route) === 1) {
            $patternOnly[] = $route;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $missing[] = $route;
    }
}

$dispatcherSrc = (string) file_get_contents($root . '/app/Services/Api/PublicMemberApiDispatcher.php');
if (str_contains($dispatcherSrc, 'PublicMemberApiRuntime.php')) {
    fwrite(STDERR, "FAIL: PublicMemberApiDispatcher still requires PublicMemberApiRuntime.php\n");
    exit(1);
}

$runtimePath = $root . '/app/Services/Api/PublicMemberApiRuntime.php';
if (is_file($runtimePath)) {
    fwrite(STDERR, "WARN: legacy runtime file still present at app/Services/Api/PublicMemberApiRuntime.php\n");
}

if ($missing !== []) {
    fwrite(STDERR, "Route module coverage gaps (" . count($missing) . "):\n");
    foreach ($missing as $route) {
        fwrite(STDERR, "  - {$route}\n");
    }
    exit(1);
}

echo 'Route module coverage OK (' . count($allowedRoutes) . ' static routes, '
    . count($patterns) . ' patterns, ' . count($patternOnly) . " pattern-only).\n";
exit(0);

function routeReferencedInBackend(string $source, string $route): bool
{
    $escaped = preg_quote($route, '/');
    $needles = [
        "'{$route}'",
        "\"{$route}\"",
        "'{$escaped}'",
    ];

    foreach ($needles as $needle) {
        if (str_contains($source, $needle)) {
            return true;
        }
    }

    if (str_ends_with($route, '.php')) {
        $bare = substr($route, 0, -4);
        if ($bare !== '' && str_contains($source, "'{$bare}'")) {
            return true;
        }
    }

    return false;
}
