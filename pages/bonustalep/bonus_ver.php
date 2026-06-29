<?php
$appDebug = filter_var((string) getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once defined('BASE_PATH') ? BASE_PATH . '/core/bootstrap.php' : __DIR__ . '/../../core/bootstrap.php';
require_once (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/services/BackendApiClient.php';

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/frontend_session.php';
    metropol_frontend_session_start();
}
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum açılmamış. Lütfen giriş yapın.']);
    exit;
}

$aktif_kullanici_username = $_SESSION['username'];

$request = json_decode(file_get_contents('php://input'), true);
if (!$request || !isset($request['action']) || $request['action'] !== 'ver_bonusu') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek veya eksik parametre.']);
    exit;
}

$body = array_merge($request, ['username' => $aktif_kullanici_username]);

$res = BackendApiClient::request('POST', BackendApiClient::SVC_MAIN, '/bonuses/claim-daily', [], $body);

if ($res === null) {
    echo json_encode(['success' => false, 'message' => 'Backend API yanıt vermedi veya tanımlı değil.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($res, JSON_UNESCAPED_UNICODE);
