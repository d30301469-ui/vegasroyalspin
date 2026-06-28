<?php

header('Content-Type: application/json');

require_once defined('BASE_PATH') ? BASE_PATH . '/core/bootstrap.php' : __DIR__ . '/../../core/bootstrap.php';
require_once (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/services/BackendApiClient.php';

session_start();
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

$request = json_decode(file_get_contents('php://input'), true);
if (!$request || ($request['action'] ?? '') !== 'ver_bonusu') {
    exit;
}

$res = BackendApiClient::request('POST', BackendApiClient::SVC_MAIN, '/bonuses/claim-loss', [], array_merge($request, [
    'username' => $_SESSION['username'],
]));

echo json_encode($res ?? ['success' => false, 'message' => 'Backend API yanıt vermedi.'], JSON_UNESCAPED_UNICODE);
