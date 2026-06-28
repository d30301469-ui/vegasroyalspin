<?php
$appDebug = filter_var((string) getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
error_reporting(E_ALL);
ini_set('display_errors', $appDebug ? '1' : '0');

// Hata loglama dosyası (proje kökü logs/error.log)
ini_set('log_errors', 1);
ini_set('error_log', (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    if (function_exists('maltabet_configure_session_security')) {
        maltabet_configure_session_security();
    }
    session_start();
}

$csrfKey = 'vegasroyalspin_csrf_token';
if (empty($_SESSION[$csrfKey]) || !is_string($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token'])
        ? $_SESSION['csrf_token']
        : bin2hex(random_bytes(32));
}
$_SESSION['csrf_token'] = $_SESSION[$csrfKey];

// Frontend ödeme sayfaları gateway/DB/servis bilgisi bilmez.
// Tüm yöntem, limit, yatırım ve çekim işlemleri yalnızca /api/v2 endpointleri ile alınır.

// ========== KULLANICI KONTROLÜ ==========
$isLoggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$username = $isLoggedIn ? $_SESSION['username'] : '';

if (!$isLoggedIn) {
    header('Location: /');
    exit();
}

$paymentLimits = [];

$user_info = [
    'id' => $_SESSION['user_id'] ?? null,
    'username' => $username,
    'first_name' => $_SESSION['first_name'] ?? '',
    'surname' => $_SESSION['surname'] ?? '',
];
$initial = strtoupper(substr($username, 0, 2));
