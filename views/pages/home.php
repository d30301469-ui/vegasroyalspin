<?php include VIEW_PATH . '/layouts/head.php'; ?>

<?php include VIEW_PATH . '/partials/header.php'; ?>

<div class="layout-content-holder-bc slider-full-sized">
<?php include VIEW_PATH . '/partials/slider.php'; ?>
<?php include VIEW_PATH . '/partials/main-content.php'; ?>
</div>

<?php $homeJsVer = (string) (is_file(BASE_PATH . '/assets/js/home.js') ? filemtime(BASE_PATH . '/assets/js/home.js') : time()); ?>
<?php $winnersJsVer = (string) (is_file(BASE_PATH . '/assets/js/winners.js') ? filemtime(BASE_PATH . '/assets/js/winners.js') : $homeJsVer); ?>
<script src="/assets/js/jackpot.js"></script>
<script src="/assets/js/winners.js?v=<?= htmlspecialchars($winnersJsVer, ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="/assets/js/home.js?v=<?= htmlspecialchars($homeJsVer, ENT_QUOTES, 'UTF-8') ?>"></script>
