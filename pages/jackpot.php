<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    metropol_frontend_session_start();
}
require_once __DIR__ . '/../views/layouts/head_full.php';
include __DIR__ . '/../views/partials/header.php';
?>
<section class="mainWrap jackpot-page py-4">
    <?php include __DIR__ . '/../views/partials/jackpot.php'; ?>
</section>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
<?php
$jackpotJsVer = (string) (file_exists(__DIR__ . '/../assets/js/jackpot.js') ? filemtime(__DIR__ . '/../assets/js/jackpot.js') : 1);
?>
<script src="/assets/js/jackpot.js?v=<?= $jackpotJsVer ?>"></script>
