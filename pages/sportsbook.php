<?php
/**
 * Casino Spor Entegrasyonu - Başlatma Sayfası
 */

// Oturumu başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

// Kullanıcı bilgilerini oturumdan al
$userId   = $_SESSION['user_id'];
$username = $_SESSION['username'];
$balance  = $_SESSION['balance'] ?? $_SESSION['ana_bakiye'] ?? '0';

// Tip parametresi (genellikle GET'den alınır)
$type = isset($_GET['type']) ? $_GET['type'] : 'match';

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
$sporApiUrl = 'https://okkogaming.com/spor-launch';

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
    die("Spor sistemine bağlanırken hata oluştu");
}

if ($httpCode !== 200) {
    die("Spor sistemi HTTP hata kodu: " . $httpCode);
}

// Yanıtı işle
$responseData = json_decode($response, true);

if (!$responseData || !isset($responseData['success']) || $responseData['success'] !== true) {
    die("Spor sisteminden geçersiz yanıt alındı: " . $response);
}

if (!isset($responseData['iframe_url'])) {
    die("Spor sisteminden iframe URL alınamadı");
}

// iframe URL'i al
$iframeUrl = $responseData['iframe_url'];

// Sadece URL'i ekrana bas
echo $iframeUrl;