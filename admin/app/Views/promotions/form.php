<?php

$promotion = is_array($promotion ?? null) ? $promotion : [];
$mode = (string) ($mode ?? 'create');
$isEdit = $mode === 'edit';
$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Marketing · <?= $isEdit ? 'Düzenle' : 'Ekle' ?></span>
        <h1 class="hero-title"><?= $isEdit ? 'Promosyon <span class="accent">düzenle</span>' : 'Promosyon <span class="accent">ekle</span>' ?></h1>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/promotions')) ?>">Geri dön</a>
    </div>
</div>

<section class="card admin-compact-card" style="max-width:720px">
    <div class="card-head">
        <div class="card-title-wrap">
            <h2 class="card-title"><?= $isEdit ? 'Promosyon bilgilerini düzenle' : 'Yeni promosyon ekle' ?></h2>
        </div>
    </div>
    <?php require __DIR__ . '/_form.php'; ?>
</section>
</section>
