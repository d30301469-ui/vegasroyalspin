<div class="error-shell">
    <div class="error-card">
        <span class="error-eyebrow">Hata · Bulunamadı</span>
        <div class="error-code">404</div>
        <h1 class="error-title">Sayfa bulunamadı</h1>
        <?php
        $notFoundMessage = $errorMessage ?? "İstenen admin route'u henüz tanımlı değil.";
        if (!is_scalar($notFoundMessage)) {
            $notFoundMessage = "İstenen admin route'u henüz tanımlı değil.";
        }
        ?>
        <p class="error-sub"><?= htmlspecialchars((string) $notFoundMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <a class="btn btn--primary" href="<?= htmlspecialchars(AdminAuth::url('/dashboard'), ENT_QUOTES, 'UTF-8') ?>">Dashboard'a dön</a>
    </div>
</div>
