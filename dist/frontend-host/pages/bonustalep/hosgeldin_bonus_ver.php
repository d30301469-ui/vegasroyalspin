<?php
$appDebug = filter_var((string) getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $appDebug ? '1' : '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once defined('BASE_PATH') ? BASE_PATH . '/core/bootstrap.php' : __DIR__ . '/../../core/bootstrap.php';
require_once (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/services/BackendApiClient.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum bulunamadı.']);
    exit;
}

$res = BackendApiClient::request('POST', BackendApiClient::SVC_MAIN, '/bonuses/claim-welcome', [], [
    'username' => $_SESSION['username'],
]);

echo json_encode($res ?? ['success' => false, 'message' => 'Backend API yanıt vermedi.'], JSON_UNESCAPED_UNICODE);
