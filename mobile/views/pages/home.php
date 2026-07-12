<?php
$mobileHead = MOBILE_PATH . '/views/layouts/head.php';
if (is_file($mobileHead) && filesize($mobileHead) > 0) {
	include $mobileHead;
} else {
	include VIEW_PATH . '/layouts/head_full.php';
}
?>
<?php include MOBILE_PATH . '/views/partials/header.php'; ?>

<?php
$sliderMobileBc = true;
$sliderApiCategory = 'home';
include VIEW_PATH . '/partials/slider.php';
?>
<?php include VIEW_PATH . '/partials/main-content.php'; ?>

<?php $homeJsVer = (string) (is_file(BASE_PATH . '/assets/js/home.js') ? filemtime(BASE_PATH . '/assets/js/home.js') : time()); ?>
<?php $winnersJsVer = (string) (is_file(BASE_PATH . '/assets/js/winners.js') ? filemtime(BASE_PATH . '/assets/js/winners.js') : $homeJsVer); ?>
<?php $jackpotJsVer = (string) (is_file(BASE_PATH . '/assets/js/jackpot.js') ? filemtime(BASE_PATH . '/assets/js/jackpot.js') : $homeJsVer); ?>
<script src="/assets/js/jackpot.js?v=<?= htmlspecialchars($jackpotJsVer, ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="/assets/js/winners.js?v=<?= htmlspecialchars($winnersJsVer, ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="/assets/js/home.js?v=<?= htmlspecialchars($homeJsVer, ENT_QUOTES, 'UTF-8') ?>"></script>
