<?php
/**
 * Giriş sayfası: Site layout kullanır, login modalı otomatik açılır.
 * POST işleme ana sayfaya (/) gider; bu sayfa sadece modalı açar.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../views/layouts/head_full.php';
?>
<?php include __DIR__ . '/../views/partials/slider.php'; ?>
<?php include __DIR__ . '/../views/partials/header.php'; ?>
<?php require_once __DIR__ . '/../views/partials/main-content.php'; ?>

<script>
(function () {
    var el = document.getElementById('login2');
    var $ = window.jQuery || window.$;
    if (el && $) $(el).modal('show');
})();
</script>

<script src="/assets/js/home.js"></script>
