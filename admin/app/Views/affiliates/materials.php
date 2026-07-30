<?php
$materials = is_array($materials ?? null) ? $materials : [];
$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$flash = (string) ($flash ?? '');
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Ortaklık Sistemi</span>
        <h1 class="hero-title">Pazarlama <span class="accent">materyalleri</span></h1>
        <p class="hero-sub">Ortaklarınızın kullanması için banner ve link materyalleri ekleyin.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn--primary" data-admin-modal-inline="#modal-material-create" data-admin-modal-title="Yeni Materyal">+ Materyal Ekle</button>
    </div>
</div>
<?php if ($flash !== ''): ?>
    <div class="alert alert--success"><?= $text($flash) ?></div>
<?php endif; ?>
<section class="card admin-compact-card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Materyaller</span>
            <h2 class="card-title">Tüm materyaller</h2>
        </div>
    </div>
    <div class="admin-compact-table-wrap">
        <table class="admin-compact-table">
            <thead>
                <tr><th>ID</th><th>Başlık</th><th>Tür</th><th>Boyut</th><th>Hedef URL</th><th>Dosya</th><th>Aktif</th><th>Sıra</th><th>İşlem</th></tr>
            </thead>
            <tbody>
                <?php if (empty($materials)): ?>
                    <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--t-light)">Henüz materyal eklenmemiş.</td></tr>
                <?php else: ?>
                    <?php foreach ($materials as $m): ?>
                    <tr>
                        <td><span class="data-cell-mono">#<?= $m['id'] ?></span></td>
                        <td><strong><?= $text($m['title']) ?></strong></td>
                        <td><span class="badge primary"><?= $text($m['material_type']) ?></span></td>
                        <td><?= $m['width'] > 0 ? $m['width'] . '×' . $m['height'] : '-' ?></td>
                        <td><small style="word-break:break-all"><?= $text(mb_substr($m['target_url'], 0, 50)) ?></small></td>
                        <td><small style="word-break:break-all"><?= $text(mb_substr($m['file_url'], 0, 40)) ?></small></td>
                        <td><span class="badge <?= $m['is_active'] ? 'success' : 'danger' ?> dot"><?= $m['is_active'] ? 'Evet' : 'Hayır' ?></span></td>
                        <td><?= (int) $m['sort_order'] ?></td>
                        <td>
                            <form method="post" action="<?= AdminAuth::url('/affiliate/material-delete') ?>" style="display:inline" onsubmit="return confirm('Silmek istediğinize emin misiniz?')">
                                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <button class="btn btn--ghost" style="font-size:11px;padding:4px 8px;color:var(--danger)">Sil</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</section>

<!-- Create Material Modal -->
<div id="modal-material-create" style="display:none">
<form method="post" action="<?= AdminAuth::url('/affiliate/material-store') ?>">
    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
    <div style="padding:24px;display:grid;gap:12px">
        <div class="field">
            <label class="field-label">Başlık</label>
            <input class="input" name="title" required placeholder="Ana Sayfa Banner 728x90">
        </div>
        <div class="field">
            <label class="field-label">Tür</label>
            <select class="select" name="material_type">
                <option value="banner">Banner</option>
                <option value="text_link">Metin Linki</option>
                <option value="landing_page">Açılış Sayfası</option>
                <option value="promo_code">Promosyon Kodu</option>
            </select>
        </div>
        <div class="field">
            <label class="field-label">Dosya URL'i (banner resmi)</label>
            <input class="input" name="file_url" placeholder="https://...">
        </div>
        <div class="field">
            <label class="field-label">Hedef URL</label>
            <input class="input" name="target_url" placeholder="https://vegasroyalspin.com/?ref={kod}">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="field">
                <label class="field-label">Genişlik (px)</label>
                <input class="input" type="number" name="width" min="0" value="0">
            </div>
            <div class="field">
                <label class="field-label">Yükseklik (px)</label>
                <input class="input" type="number" name="height" min="0" value="0">
            </div>
        </div>
    </div>
    <div class="form-actions">
        <button class="btn btn--primary" type="submit">Ekle</button>
    </div>
</form>
</div>
