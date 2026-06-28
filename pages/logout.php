<?php
session_start(); // Oturumu başlat

// Oturumdaki tüm değişkenleri temizle
$_SESSION = [];

// Oturumu yok et
session_destroy();

// Kullanıcıyı giriş sayfasına veya ana sayfaya yönlendir
header("Location: /"); // veya istediğiniz sayfa
exit();
?>
