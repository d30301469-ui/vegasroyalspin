#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Drakon game_launch teşhisi (backend SSH).
 *
 * Usage:
 *   php deploy/aapanel/probe-drakon-launch.php --game-id=23846 [--user-id=1] [--demo]
 *   php deploy/aapanel/probe-drakon-launch.php --game-id=23846 --user-id=5 [/path/to/backend]
 */

$root = dirname(__DIR__, 2);
$gameId = '23846';
$userId = 1;
$demo = in_array('--demo', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--game-id=')) {
        $gameId = substr($arg, 10);
        continue;
    }
    if (str_starts_with($arg, '--user-id=')) {
        $userId = (int) substr($arg, 10);
        continue;
    }
    if (trim($arg) !== '' && !str_starts_with($arg, '-')) {
        $root = rtrim(str_replace('\\', '/', $arg), '/');
    }
}

$bootstrapCandidates = [
    $root . '/app/Core/AdminPaths.php',
    $root . '/admin/app/Core/AdminPaths.php',
];
$bootstrapped = false;
foreach ($bootstrapCandidates as $candidate) {
    if (!is_readable($candidate)) {
        continue;
    }
    require_once $candidate;
    admin_paths_bootstrap();
    $bootstrapped = true;
    break;
}
if (!$bootstrapped) {
    fwrite(STDERR, "AdminPaths bootstrap not found under {$root}\n");
    exit(1);
}

require_once admin_project_path('app/Core/AdminDatabase.php');
require_once admin_project_path('services/DrakonService.php');

$pdo = AdminDatabase::pdo();
DrakonService::bootstrap($pdo);

echo "Drakon launch probe\n";
echo "Root: {$root}\n";
echo "game_id: {$gameId}\n";
echo "user_id: {$userId}\n";
echo "mode: " . ($demo ? 'fun' : 'real') . "\n\n";

$diag = DrakonService::integrationDiagnostics($pdo);
echo "site_endpoint: " . ($diag['site_endpoint'] ?? '?') . "\n";
echo "webhook_url: " . ($diag['drakon_webhook_url'] ?? '?') . "\n";
echo "webhook_handler: " . (($diag['webhook_handler']['ok'] ?? false) ? 'OK' : 'FAIL') . "\n";
if (!empty($diag['webhook_handler']['message'])) {
    echo "  " . $diag['webhook_handler']['message'] . "\n";
}

$resolved = DrakonService::resolveLaunchGameId($pdo, $gameId);
if ($resolved !== $gameId) {
    echo "\nResolved game_id: {$gameId} → {$resolved}\n";
}

$user = null;
if (!$demo) {
    $stmt = $pdo->prepare('SELECT id, username, name, surname FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!is_array($user)) {
        fwrite(STDERR, "\nFAIL: user_id={$userId} not found\n");
        exit(1);
    }
}

$input = [
    'game_id' => $resolved,
    'mode' => $demo ? 'fun' : 'real',
];
if ($demo) {
    $input['demo'] = true;
}

echo "\nLaunching...\n";
$result = DrakonService::launch($pdo, $user, $input);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

exit(!empty($result['success']) ? 0 : 1);
