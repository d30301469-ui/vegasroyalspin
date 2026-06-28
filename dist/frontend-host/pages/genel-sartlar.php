<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../views/layouts/head_full.php';
include __DIR__ . '/../views/partials/header.php';
?>
<section class="mainWrap legal-page py-5">
    <div class="container">
        <h1 class="mb-4">Genel Kurallar ve Şartlar</h1>
        <?php include __DIR__ . '/../views/partials/legal-terms-content.php'; ?>
        <p class="mt-4"><a href="/" class="btn btn-outline-light">Ana Sayfaya Dön</a></p>
    </div>
</section>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
