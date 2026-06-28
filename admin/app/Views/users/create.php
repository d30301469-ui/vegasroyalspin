<?php

$flash = trim((string) ($flash ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Üyeler · Ekle</span>
        <h1 class="hero-title">Yeni <span class="accent">oyuncu</span></h1>
        <p class="hero-sub">Oyuncu hesabını kontrollü form ile oluşturun. Bakiye işlemleri kayıt sonrası kullanıcı detay ekranından yönetilir.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/module?key=users'), ENT_QUOTES, 'UTF-8') ?>">Listeye dön</a>
        <button class="btn btn--primary" type="submit" form="userEditForm">Oyuncu Ekle</button>
    </div>
</div>

<?php require ADMIN_VIEW_PATH . '/users/_edit_form.php'; ?>
</section>
