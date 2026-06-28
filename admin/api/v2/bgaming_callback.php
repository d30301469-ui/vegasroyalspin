<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
admin_require_project_file('controllers/Api/ApiBgamingWalletController.php');

$endpoint = trim((string) ($_GET['endpoint'] ?? $_GET['route'] ?? ''), '/');
$endpoint = preg_replace('#^bgaming-wallet/#', '', $endpoint) ?? $endpoint;
$endpoint = preg_replace('#^bgaming/#', '', $endpoint) ?? $endpoint;

$map = [
    '' => 'health',
    'balance' => 'balance',
    'play' => 'play',
    'rollback' => 'rollback',
    'freespins/finish' => 'freespinsFinish',
    'promo/bet' => 'promoBet',
    'promo/win' => 'promoWin',
    'promo/rollback' => 'promoRollback',
    'auth/token_rotation' => 'tokenRotation',
];

$method = $map[$endpoint] ?? null;
if ($method === null) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'NOT_FOUND'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

(new ApiBgamingWalletController())->$method();

