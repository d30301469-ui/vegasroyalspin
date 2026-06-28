<?php

$table = (string) ($table ?? '');
$moduleKey = isset($moduleKey) ? (string) $moduleKey : '';
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Backoffice · Detay</span>
        <h1 class="hero-title"><?= htmlspecialchars($table, ENT_QUOTES, 'UTF-8') ?> <span class="accent">görüntüle</span></h1>
        <p class="hero-sub">Bu ekran sadece kayıt detaylarını gösterir; düzenleme ve silme işlemi yapılmaz.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url($moduleKey !== '' ? '/module?key=' . rawurlencode($moduleKey) : '/table?name=' . rawurlencode($table)), ENT_QUOTES, 'UTF-8') ?>">Listeye dön</a>
    </div>
</div>

<div class="grid">
    <section class="col-12 card admin-compact-card">
        <?php require ADMIN_VIEW_PATH . '/tables/_view.php'; ?>
    </section>
</div>
</section>
