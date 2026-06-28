<?php
/**
 * Tam sayfa profil kabuğu — başlangıç. modal=1 iken çıktı üretmez (fragment yanıtı).
 *
 * Önceden: $profile_modal (bool)
 * İsteğe bağlı: $profile_include_toastr (bool) — tam sayfada toastr CDN ekler
 */
if (!empty($profile_modal)) {
    return;
}
require_once __DIR__ . '/../layouts/head_full.php';
include __DIR__ . '/header.php';
echo '<div class="centerWrap porfileWrap">';
if (!empty($profile_include_toastr)) {
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>';
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>';
}
