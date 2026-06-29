<?php
// Hata raporlama modunu aç
$appDebug = filter_var((string) getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');
ini_set('log_errors', 1);
ini_set('error_log', (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/logs/error.log');
error_reporting(E_ALL);

// Oturum başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>


<?php require_once __DIR__ . '/../views/layouts/head_full.php'; ?>
<?php include __DIR__ . '/../views/partials/header.php' ?>
<br>
    require_once __DIR__ . '/../config/frontend_session.php';
    metropol_frontend_session_start();
<br>
<br>
<br>

<div class="responsive-iframe">
    <iframe src="https://spor.okkogaming.com/#/prematch/Soccer" frameborder="0" allowfullscreen></iframe>
</div>

<style>
    .responsive-iframe {
        position: relative;
        overflow: hidden;
        padding-top: 90.25%; /* 16:9 aspect ratio - daha geniş bir görünüm için */
        height: 1200px;
        width: 100%;
    }

    .responsive-iframe iframe {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border: 0;
    }
</style>