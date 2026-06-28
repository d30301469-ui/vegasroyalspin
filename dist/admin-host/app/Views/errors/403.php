<div class="error-shell">
    <div class="error-card">
        <span class="error-eyebrow">Erişim · Yetkisiz</span>
        <div class="error-code">403</div>
        <h1 class="error-title">Bu alana erişiminiz yok</h1>
        <?php
        $forbiddenMessage = $errorMessage ?? 'Bu işlem için gerekli yetkiye sahip değilsiniz.';
        if (!is_scalar($forbiddenMessage)) {
            $forbiddenMessage = 'Bu işlem için gerekli yetkiye sahip değilsiniz.';
        }
        ?>
        <p class="error-sub"><?= htmlspecialchars((string) $forbiddenMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <a class="btn btn--primary" href="<?= htmlspecialchars(AdminAuth::url('/dashboard'), ENT_QUOTES, 'UTF-8') ?>">Dashboard'a dön</a>
    </div>
</div>
