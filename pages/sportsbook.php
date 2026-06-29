<?php
/**
 * Casino Spor Entegrasyonu - Başlatma Sayfası
 */

// Oturumu başlat
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    metropol_frontend_session_start();
}

// Hata gösterimi
$appDebug = filter_var((string) getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');
error_reporting(E_ALL);

if (empty($_SESSION['user_id']) || empty($_SESSION['username'])) {
    http_response_code(401);
    exit('Oturum bulunamadı.');
}

$API_KEY = trim((string) getenv('OKKO_SPORTS_API_KEY'));
$API_SECRET = trim((string) getenv('OKKO_SPORTS_API_SECRET'));
if ($API_KEY === '' || $API_SECRET === '') {
    http_response_code(503);
    exit('Spor servisi yapılandırması eksik.');
}

// Tip parametresi — beyaz liste doğrulaması
$allowedTypes = ['match', 'live', 'esports', 'virtual', 'prematch'];
$type = isset($_GET['type']) && in_array($_GET['type'], $allowedTypes, true) ? $_GET['type'] : 'match';
$balance  = $_SESSION['balance'] ?? $_SESSION['ana_bakiye'] ?? '0';

// Spor API'sine gönderilecek veri
$postData = [
    'api_key'    => $API_KEY,
    'api_secret' => $API_SECRET,
    'user_id'    => $userId,
    'username'   => $username,
    'balance'    => $balance,
    'type'       => $type
];

// Spor API URL'i
$sporApiUrl = trim((string) (getenv('OKKO_SPORTS_LAUNCH_URL') ?: (defined('OKKO_SPORTS_LAUNCH_URL') ? OKKO_SPORTS_LAUNCH_URL : 'https://okkogaming.com/spor-launch')));

// cURL ile istek gönder
$ch = curl_init($sporApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Hata kontrolü
if ($response === false) {
    http_response_code(502);
    exit('Spor sistemine bağlanirken hata oluştu');
}

if ($httpCode !== 200) {
    http_response_code(502);
    exit('Spor sistemi HTTP hata kodu: ' . $httpCode);
}

// Yanıtı işle
$responseData = json_decode($response, true);

if (!$responseData || !isset($responseData['success']) || $responseData['success'] !== true) {
    http_response_code(502);
    exit('Spor sisteminden geçersiz yanıt alındı');
}

if (!isset($responseData['iframe_url'])) {
    http_response_code(502);
    exit('Spor sisteminden iframe URL alınamadı');
}

// iframe URL'i al
$iframeUrl = $responseData['iframe_url'];

// Sadece URL'i ekrana bas
echo $iframeUrl;