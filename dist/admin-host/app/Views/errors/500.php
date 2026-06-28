<div class="error-shell">
    <div class="error-card">
        <span class="error-eyebrow">Hata · Sunucu</span>
        <div class="error-code">500</div>
        <h1 class="error-title">Bir şeyler ters gitti</h1>
        <?php
        $serverErrorMessage = $errorMessage ?? $message ?? 'Admin panel isteği işlenirken hata oluştu.';
        if (!is_scalar($serverErrorMessage)) {
            $serverErrorMessage = 'Admin panel isteği işlenirken hata oluştu.';
        }
        ?>
        <p class="error-sub"><?= htmlspecialchars((string) $serverErrorMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <a class="btn btn--primary" href="<?= htmlspecialchars(AdminAuth::url('/dashboard'), ENT_QUOTES, 'UTF-8') ?>">Dashboard'a dön</a>
    </div>
</div>
