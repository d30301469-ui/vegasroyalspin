<?php

$emailSection = 'inbox';
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">E-posta</span>
        <h1 class="hero-title">E-posta <span class="accent">oku</span></h1>
        <p class="hero-sub">Mesaj detayı</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/email/inbox'), ENT_QUOTES, 'UTF-8') ?>">Gelen kutusuna dön</a>
    </div>
</section>

<?php include __DIR__ . '/_nav.php'; ?>

<section class="card">
    <?php include __DIR__ . '/_email_read.php'; ?>
</section>
