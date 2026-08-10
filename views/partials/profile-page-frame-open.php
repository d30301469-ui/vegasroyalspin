<?php
/**
 * Tam sayfa profil kabuğu — başlangıç. modal=1 iken çıktı üretmez (fragment yanıtı).
 *
 * Önceden: $profile_modal (bool)
 * İsteğe bağlı: $profile_include_toastr (bool) — tam sayfada toastr CDN ekler
 */
if (!empty($profile_modal)) {
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    /* Modal fragments have no <head>; declare UTF-8 for DOMParser/fetch consumers. */
    echo '<meta charset="utf-8">';
    return;
}
require_once __DIR__ . '/../layouts/head_full.php';
include __DIR__ . '/header.php';
echo '<div class="centerWrap porfileWrap">';
if (!empty($profile_include_toastr)) {
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>';
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>';
}
