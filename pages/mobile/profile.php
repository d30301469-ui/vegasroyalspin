<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/frontend_session.php';
    metropol_frontend_session_start();
}

require_once defined('BASE_PATH') ? BASE_PATH . '/core/bootstrap.php' : __DIR__ . '/../../core/bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /');
    exit;
}

$mobileHead = MOBILE_PATH . '/views/layouts/head.php';
if (is_file($mobileHead) && filesize($mobileHead) > 0) {
    include $mobileHead;
} else {
    include VIEW_PATH . '/layouts/head_full.php';
}

include MOBILE_PATH . '/views/partials/header.php';

echo '<div class="centerWrap porfileWrap">';

// Desktop modal kabuğunu değil, profile fragment içeriğini mobile sayfa gövdesine bas.
$_GET['modal'] = '1';
$profile_modal = true;
include BASE_PATH . '/pages/profile/details.php';

echo '</div>';

include MOBILE_PATH . '/views/partials/footer.php';
