<?php

$table = (string) ($table ?? '');
$mode = (string) ($mode ?? 'create');
$moduleKey = isset($moduleKey) ? (string) $moduleKey : '';
$flash = trim((string) ($flash ?? ''));
$isEdit = $mode === 'edit';
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Backoffice · Form</span>
        <h1 class="hero-title"><?= htmlspecialchars($table, ENT_QUOTES, 'UTF-8') ?> <span class="accent"><?= $isEdit ? 'düzenle' : 'ekle' ?></span></h1>
        <p class="hero-sub">Kayıt alanları tablo şemasına göre otomatik hazırlanır. Değişiklikleri kaydetmeden önce kritik alanları kontrol edin.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn--primary" type="submit" form="adminRecordForm">Kaydet</button>
        <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url($moduleKey !== '' ? '/module?key=' . rawurlencode($moduleKey) : '/table?name=' . rawurlencode($table)), ENT_QUOTES, 'UTF-8') ?>">Listeye dön</a>
    </div>
</div>

<div class="grid">
<section class="col-12 card admin-compact-card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Kayıt</span>
            <h2 class="card-title"><?= htmlspecialchars($isEdit ? 'Kayıt düzenle' : 'Yeni kayıt', ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
        <span class="badge solid"><?= htmlspecialchars($table, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php require ADMIN_VIEW_PATH . '/tables/_form.php'; ?>
</section>

<section class="col-6 card admin-compact-card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Bilgi</span>
            <h2 class="card-title">Form Özeti</h2>
        </div>
    </div>
    <div class="admin-stack is-relaxed">
        <div class="field"><label class="field-label">Tablo</label><input class="input" value="<?= htmlspecialchars($table, ENT_QUOTES, 'UTF-8') ?>" disabled></div>
        <div class="field"><label class="field-label">Mod</label><input class="input" value="<?= htmlspecialchars($isEdit ? 'edit' : 'create', ENT_QUOTES, 'UTF-8') ?>" disabled></div>
        <div class="alert info"><span class="ico"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg></span><div class="body">Form alanları `information_schema.COLUMNS` üzerinden üretilir.</div></div>
    </div>
</section>

<section class="col-6 card admin-compact-card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Alan Tipleri</span>
            <h2 class="card-title">Otomatik Bileşenler</h2>
        </div>
    </div>
    <div class="admin-stack">
        <label class="switch"><input type="checkbox" checked disabled><span class="track"></span> tinyint(1) alanları switch olarak gösterilir</label>
        <label class="switch"><input type="checkbox" disabled><span class="track"></span> enum alanları select olarak gösterilir</label>
        <label class="switch"><input type="checkbox" checked disabled><span class="track"></span> Veri alanları işlenmiş satır editörü olarak gösterilir</label>
    </div>
</section>
</div>
</section>
