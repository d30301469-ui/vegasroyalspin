<?php
/**
 * Kayıt sayfası: Site layout kullanır, kayıt modalı otomatik açılır.
 * Login sayfası gibi ayrı sayfa; modal header içinde views/partials/register.php ile yüklenir.
 */
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    metropol_frontend_session_start();
}

require_once __DIR__ . '/../views/layouts/head_full.php';
?>
<?php include __DIR__ . '/../views/partials/slider.php'; ?>
<?php include __DIR__ . '/../views/partials/header.php'; ?>
<?php require_once __DIR__ . '/../views/partials/main-content.php'; ?>

<script>
(function () {
    function openRegisterModal() {
        var el = document.getElementById('registerModal');
        var $ = window.jQuery || window.$;
        if (!el) return;
        if ($ && $.fn && typeof $.fn.modal === 'function') {
            $(el).modal('show');
        } else if (typeof window.showModalById === 'function') {
            window.showModalById('registerModal');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', openRegisterModal);
    } else {
        openRegisterModal();
    }
})();
</script>

<script src="<?= htmlspecialchars(asset_url('assets/js/home.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
