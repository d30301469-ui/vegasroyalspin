<?php include VIEW_PATH . '/layouts/head.php'; ?>
<?php include VIEW_PATH . '/partials/slider.php'; ?>
<?php include VIEW_PATH . '/partials/header.php'; ?>
<?php include VIEW_PATH . '/partials/main-content.php'; ?>

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

<script src="/assets/js/home.js"></script>
