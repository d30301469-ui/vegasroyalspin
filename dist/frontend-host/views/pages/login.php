<?php include VIEW_PATH . '/layouts/head.php'; ?>
<?php include VIEW_PATH . '/partials/slider.php'; ?>
<?php include VIEW_PATH . '/partials/header.php'; ?>
<?php include VIEW_PATH . '/partials/main-content.php'; ?>

<script>
(function () {
    var el = document.getElementById('login2');
    var $ = window.jQuery || window.$;
    if (el && $) $(el).modal('show');
})();
</script>

<script src="/assets/js/home.js"></script>
