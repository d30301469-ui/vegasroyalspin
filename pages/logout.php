<?php
require_once __DIR__ . '/../config/frontend_session.php';
if (session_status() === PHP_SESSION_NONE) {
	metropol_frontend_session_start();
}

// Oturumdaki tüm değişkenleri temizle
$_SESSION = [];

// Oturumu yok et
session_destroy();

// Kullanıcıyı giriş sayfasına veya ana sayfaya yönlendir
header("Location: /"); // veya istediğiniz sayfa
exit();
?>
