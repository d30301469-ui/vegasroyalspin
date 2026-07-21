<?php

declare(strict_types=1);

/**
 * TEMPORARY, token-protected Drakon diagnostic.
 *
 * Purpose: read-only ground-truth inspection of what Drakon's live /games/all
 * feed actually returns vs. what we have stored, so we can determine why some
 * Pragmatic Play titles (e.g. Sweet Bonanza) fail to launch. This file is meant
 * to be removed immediately after the investigation.
 *
 * Access: https://<host>/drakon_diag.php?token=<TOKEN>
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');

// Random per-investigation secret. Rotate/remove after use.
const DRAKON_DIAG_TOKEN = 'dg_2f9c7a1e5b8340d6ae13c0f4d972bb51';

$provided = (string) ($_GET['token'] ?? '');
if ($provided === '' || !hash_equals(DRAKON_DIAG_TOKEN, $provided)) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

$root = __DIR__;

// Bootstrap AdminPaths (same pattern as deploy/aapanel scripts).
$bootstrapped = false;
foreach ([
    $root . '/admin/app/Core/AdminPaths.php',
    $root . '/app/Core/AdminPaths.php',
] as $pathsFile) {
    if (is_readable($pathsFile)) {
        require_once $pathsFile;
        if (function_exists('admin_paths_bootstrap')) {
            admin_paths_bootstrap($root);
            $bootstrapped = true;
            break;
        }
    }
}

if (!$bootstrapped) {
    http_response_code(500);
    echo json_encode(['error' => 'paths_bootstrap_failed']);
    exit;
}

$requireFirstReadable = static function (string $class, array $candidates): bool {
    if (class_exists($class, false)) {
        return true;
    }
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_readable($candidate)) {
            require_once $candidate;
            if (class_exists($class, false)) {
                return true;
            }
        }
    }
    return false;
};

$okDb = $requireFirstReadable('AdminDatabase', [
    admin_project_path('app/Core/AdminDatabase.php'),
    admin_project_path('admin/app/Core/AdminDatabase.php'),
    $root . '/app/Core/AdminDatabase.php',
    $root . '/admin/app/Core/AdminDatabase.php',
]);
$okSvc = $requireFirstReadable('DrakonService', [
    admin_project_path('services/DrakonService.php'),
    admin_project_path('admin/services/DrakonService.php'),
    $root . '/services/DrakonService.php',
    $root . '/admin/services/DrakonService.php',
]);

if (!$okDb || !$okSvc) {
    http_response_code(500);
    echo json_encode(['error' => 'class_load_failed', 'db' => $okDb, 'svc' => $okSvc]);
    exit;
}

try {
    $pdo = AdminDatabase::pdo();
    $cfg = DrakonService::config($pdo);

    // 1) Fresh live feed via the same auth path the sync uses.
    $ref = new ReflectionMethod('DrakonService', 'getToken');
    $ref->setAccessible(true);
    $token = $ref->invoke(null, $pdo);

    $apiBaseRef = new ReflectionMethod('DrakonService', 'apiBase');
    $apiBaseRef->setAccessible(true);
    $apiBase = $apiBaseRef->invoke(null, $cfg);

    $httpRef = new ReflectionMethod('DrakonService', 'httpRequest');
    $httpRef->setAccessible(true);
    $feed = $httpRef->invoke(null, 'GET', $apiBase . '/games/all', [], [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ], 180);

    $games = is_array($feed['games'] ?? null) ? $feed['games'] : [];

    $feedSweet = [];
    $has51096InFeed = [];
    foreach ($games as $g) {
        $gid  = (string) ($g['game_id'] ?? '');
        $code = (string) ($g['game_code'] ?? '');
        $name = (string) ($g['game_name'] ?? '');
        if (stripos($name, 'sweet bonanza') !== false) {
            $feedSweet[] = [
                'game_id'       => $gid,
                'game_code'     => $code,
                'game_name'     => $name,
                'provider_game' => (string) ($g['provider_game'] ?? ''),
            ];
        }
        if ($gid === '51096' || $code === '51096') {
            $has51096InFeed[] = ['game_id' => $gid, 'game_code' => $code, 'game_name' => $name];
        }
    }

    // 2) What we have stored.
    $dbSweet = $pdo->query(
        "SELECT game_id, game_code, game_name, provider_name, is_active, synced_at
         FROM drakon_games WHERE game_name LIKE '%Sweet Bonanza%' ORDER BY game_name LIMIT 12"
    )->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'feed_total'        => count($games),
        'feed_status'       => $feed['status'] ?? null,
        'feed_sweet'        => $feedSweet,
        'feed_has_51096'    => $has51096InFeed,
        'db_sweet'          => $dbSweet,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'exception', 'message' => $e->getMessage()]);
}
