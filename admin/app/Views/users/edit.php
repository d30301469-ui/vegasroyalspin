<?php

$user = is_array($user ?? null) ? $user : [];
$flash = trim((string) ($flash ?? ''));
$userId = (string) ($user['id'] ?? '');
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Üyeler · Düzenle</span>
        <h1 class="hero-title"><?= $text($user['username'] ?? 'Kullanıcı') ?> <span class="accent">düzenle</span></h1>
        <p class="hero-sub">Kullanıcı profil bilgileri kontrollü form ile güncellenir. Bakiye işlemleri kullanıcı detay ekranındaki manuel bakiye panelinden yapılır.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/user?id=' . rawurlencode($userId)), ENT_QUOTES, 'UTF-8') ?>">Detaya dön</a>
        <button class="btn btn--primary" type="submit" form="userEditForm">Güncelle</button>
    </div>
</div>

<?php require ADMIN_VIEW_PATH . '/users/_edit_form.php'; ?>
</section>
