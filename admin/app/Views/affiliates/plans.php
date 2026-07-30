<?php
$plans = is_array($plans ?? null) ? $plans : [];
$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$flash = (string) ($flash ?? '');
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Ortaklık Sistemi</span>
        <h1 class="hero-title">Komisyon <span class="accent">planları</span></h1>
        <p class="hero-sub">RevShare, CPA ve Hybrid komisyon planlarını yönetin.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn--primary" data-admin-modal-inline="#modal-plan-create" data-admin-modal-title="Yeni Komisyon Planı">+ Plan Ekle</button>
    </div>
</div>
<?php if ($flash !== ''): ?>
    <div class="alert alert--success"><?= $text($flash) ?></div>
<?php endif; ?>
<section class="card admin-compact-card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Planlar</span>
            <h2 class="card-title">Tüm komisyon planları</h2>
        </div>
    </div>
    <div class="admin-compact-table-wrap">
        <table class="admin-compact-table">
            <thead>
                <tr><th>ID</th><th>Plan Adı</th><th>Tür</th><th>RevShare</th><th>CPA</th><th>Min. Depozito</th><th>Durum</th><th>Varsayılan</th><th>İşlem</th></tr>
            </thead>
            <tbody>
                <?php foreach ($plans as $p): ?>
                <tr>
                    <td><span class="data-cell-mono">#<?= $p['id'] ?></span></td>
                    <td><strong><?= $text($p['name']) ?></strong></td>
                    <td><span class="badge primary"><?= $text($p['plan_type']) ?></span></td>
                    <td><?= $p['plan_type'] !== 'cpa' ? $text($p['revshare_rate']) . '%' : '-' ?></td>
                    <td><?= $p['plan_type'] !== 'revshare' ? number_format((float) $p['cpa_amount'], 0, ',', '.') . ' ₺' : '-' ?></td>
                    <td><span class="data-cell-mono"><?= number_format((float) $p['min_deposit'], 0, ',', '.') ?> ₺</span></td>
                    <td><span class="badge <?= $p['is_active'] ? 'success' : 'danger' ?> dot"><?= $p['is_active'] ? 'Aktif' : 'Pasif' ?></span></td>
                    <td><?= $p['is_default'] ? '<span class="badge warning">Varsayılan</span>' : '-' ?></td>
                    <td>
                        <button class="btn btn--ghost" style="font-size:11px;padding:4px 8px"
                                data-admin-modal-inline="#edit-<?= (int) $p['id'] ?>"
                                data-admin-modal-title="Planı Düzenle">Düzenle</button>
                        <?php if (!$p['is_default']): ?>
                        <form method="post" action="<?= AdminAuth::url('/affiliate/plan-delete') ?>" style="display:inline" onsubmit="return confirm('Emin misiniz?')">
                            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button class="btn btn--ghost" style="font-size:11px;padding:4px 8px;color:var(--danger)">Sil</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</section>

<!-- Create Modal Content -->
<div id="modal-plan-create" style="display:none">
<form method="post" action="<?= AdminAuth::url('/affiliate/plan-store') ?>">
    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
    <div style="padding:24px;display:grid;gap:12px">
        <div class="field">
            <label class="field-label">Plan Adı</label>
            <input class="input" name="name" required placeholder="RevShare %30">
        </div>
        <div class="field">
            <label class="field-label">Plan Türü</label>
            <select class="select" name="plan_type" onchange="togglePlanType(this.value)">
                <option value="revshare">RevShare</option>
                <option value="cpa">CPA</option>
                <option value="hybrid">Hybrid</option>
            </select>
        </div>
        <div class="field" id="field-revshare">
            <label class="field-label">RevShare Oranı (%)</label>
            <input class="input" type="number" name="revshare_rate" step="0.01" min="0" value="0">
        </div>
        <div class="field" id="field-cpa">
            <label class="field-label">CPA Tutarı (₺)</label>
            <input class="input" type="number" name="cpa_amount" step="0.01" min="0" value="0">
        </div>
        <div class="field">
            <label class="field-label">Min. Depozito (₺)</label>
            <input class="input" type="number" name="min_deposit" step="0.01" min="0" value="0">
        </div>
        <div class="field">
            <label class="field-label">Açıklama</label>
            <input class="input" name="description" placeholder="Plan açıklaması">
        </div>
        <div class="field">
            <label><input type="checkbox" name="is_default" value="1"> Varsayılan plan yap</label>
        </div>
    </div>
    <div class="form-actions">
        <button class="btn btn--primary" type="submit">Plan Oluştur</button>
    </div>
</form>
<script>
function togglePlanType(val) {
    document.getElementById('field-revshare').style.display = (val === 'revshare' || val === 'hybrid') ? '' : 'none';
    document.getElementById('field-cpa').style.display = (val === 'cpa' || val === 'hybrid') ? '' : 'none';
}
togglePlanType('revshare');
</script>
</div>

<!-- Edit Modal Content -->
<div id="modal-plan-edit" style="display:none">
<?php foreach ($plans as $p): ?>
<div id="edit-<?= $p['id'] ?>" style="display:none">
<form method="post" action="<?= AdminAuth::url('/affiliate/plan-update') ?>">
    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= $p['id'] ?>">
    <div style="padding:24px;display:grid;gap:12px">
        <div class="field">
            <label class="field-label">Plan Adı</label>
            <input class="input" name="name" value="<?= $text($p['name']) ?>" required>
        </div>
        <div class="field">
            <label class="field-label">Plan Türü</label>
            <select class="select" name="plan_type">
                <option value="revshare" <?= $p['plan_type'] === 'revshare' ? 'selected' : '' ?>>RevShare</option>
                <option value="cpa" <?= $p['plan_type'] === 'cpa' ? 'selected' : '' ?>>CPA</option>
                <option value="hybrid" <?= $p['plan_type'] === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
            </select>
        </div>
        <div class="field">
            <label class="field-label">RevShare Oranı (%)</label>
            <input class="input" type="number" name="revshare_rate" step="0.01" min="0" value="<?= $p['revshare_rate'] ?>">
        </div>
        <div class="field">
            <label class="field-label">CPA Tutarı (₺)</label>
            <input class="input" type="number" name="cpa_amount" step="0.01" min="0" value="<?= $p['cpa_amount'] ?>">
        </div>
        <div class="field">
            <label class="field-label">Min. Depozito (₺)</label>
            <input class="input" type="number" name="min_deposit" step="0.01" min="0" value="<?= $p['min_deposit'] ?>">
        </div>
        <div class="field">
            <label class="field-label">Açıklama</label>
            <input class="input" name="description" value="<?= $text($p['description']) ?>">
        </div>
        <div class="field">
            <label><input type="checkbox" name="is_active" value="1" <?= $p['is_active'] ? 'checked' : '' ?>> Aktif</label>
            <label><input type="checkbox" name="is_default" value="1" <?= $p['is_default'] ? 'checked' : '' ?>> Varsayılan yap</label>
        </div>
    </div>
    <div class="form-actions">
        <button class="btn btn--primary" type="submit">Güncelle</button>
    </div>
</form>
</div>
<?php endforeach; ?>
</div>
