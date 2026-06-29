<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    metropol_frontend_session_start();
}
require_once __DIR__ . '/../views/layouts/head_full.php';
include __DIR__ . '/../views/partials/header.php';
?>
<section class="mainWrap py-5">
    <div class="container">
        <div class="text-center py-5">
            <h1 class="mb-3">Turnuvalar</h1>
            <p class="lead text-muted">Bu sayfa yakında eklenecektir.</p>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
