<?php
$plans = is_array($plans ?? null) ? $plans : [];
$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$flash = (string) ($flash ?? '');
$num = static fn (mixed $v): string => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') ?: '0';
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Ortaklık Sistemi</span>
        <h1 class="hero-title">Komisyon <span class="accent">planları</span></h1>
        <p class="hero-sub">RevShare, CPA ve Hybrid komisyon planlarını yönetin.</p>
    </div>
    <div class="hero-actions">
        <button type="button" class="btn btn--primary" data-admin-modal-inline="#modal-plan-create" data-admin-modal-title="Yeni Komisyon Planı">+ Plan Ekle</button>
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
                <?php if ($plans === []): ?>
                <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--t-light)">Henüz plan yok. Yeni plan ekleyin.</td></tr>
                <?php else: ?>
                <?php foreach ($plans as $p): ?>
                <tr>
                    <td><span class="data-cell-mono">#<?= (int) $p['id'] ?></span></td>
                    <td><strong><?= $text($p['name']) ?></strong></td>
                    <td><span class="badge primary"><?= $text($p['plan_type']) ?></span></td>
                    <td><?= $p['plan_type'] !== 'cpa' ? $text($num($p['revshare_rate'])) . '%' : '-' ?></td>
                    <td><?= $p['plan_type'] !== 'revshare' ? number_format((float) $p['cpa_amount'], 0, ',', '.') . ' ₺' : '-' ?></td>
                    <td><span class="data-cell-mono"><?= number_format((float) $p['min_deposit'], 0, ',', '.') ?> ₺</span></td>
                    <td><span class="badge <?= !empty($p['is_active']) ? 'success' : 'danger' ?> dot"><?= !empty($p['is_active']) ? 'Aktif' : 'Pasif' ?></span></td>
                    <td><?= !empty($p['is_default']) ? '<span class="badge warning">Varsayılan</span>' : '-' ?></td>
                    <td>
                        <button type="button" class="btn btn--ghost" style="font-size:11px;padding:4px 8px"
                                data-admin-modal-inline="#edit-plan-<?= (int) $p['id'] ?>"
                                data-admin-modal-title="Planı Düzenle">Düzenle</button>
                        <?php if (empty($p['is_default'])): ?>
                        <form method="post" action="<?= AdminAuth::url('/affiliate/plan-delete') ?>" style="display:inline" onsubmit="return confirm('Bu planı silmek istediğinize emin misiniz?')">
                            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button class="btn btn--ghost" type="submit" style="font-size:11px;padding:4px 8px;color:var(--danger)">Sil</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</section>

<!-- Create Modal Content -->
<div id="modal-plan-create" hidden>
<form method="post" action="<?= AdminAuth::url('/affiliate/plan-store') ?>" class="aff-plan-form" data-aff-plan-form>
    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
    <div style="padding:24px;display:grid;gap:12px">
        <div class="field">
            <label class="field-label">Plan Adı</label>
            <input class="input" name="name" required placeholder="RevShare %30">
        </div>
        <div class="field">
            <label class="field-label">Plan Türü</label>
            <select class="select" name="plan_type" data-aff-plan-type>
                <option value="revshare">RevShare</option>
                <option value="cpa">CPA</option>
                <option value="hybrid">Hybrid</option>
            </select>
        </div>
        <div class="field" data-aff-field="revshare">
            <label class="field-label">RevShare Oranı (%)</label>
            <input class="input" type="number" name="revshare_rate" step="0.01" min="0" max="100" value="30" inputmode="decimal">
        </div>
        <div class="field" data-aff-field="cpa" hidden>
            <label class="field-label">CPA Tutarı (₺)</label>
            <input class="input" type="number" name="cpa_amount" step="0.01" min="0" value="0" inputmode="decimal">
        </div>
        <div class="field">
            <label class="field-label">Min. Depozito (₺)</label>
            <input class="input" type="number" name="min_deposit" step="0.01" min="0" value="0" inputmode="decimal">
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
</div>

<!-- Edit Modal Contents (top-level, not nested) -->
<?php foreach ($plans as $p): ?>
<?php
$planType = (string) ($p['plan_type'] ?? 'revshare');
$showRev = $planType === 'revshare' || $planType === 'hybrid';
$showCpa = $planType === 'cpa' || $planType === 'hybrid';
?>
<div id="edit-plan-<?= (int) $p['id'] ?>" hidden>
<form method="post" action="<?= AdminAuth::url('/affiliate/plan-update') ?>" class="aff-plan-form" data-aff-plan-form>
    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
    <div style="padding:24px;display:grid;gap:12px">
        <div class="field">
            <label class="field-label">Plan Adı</label>
            <input class="input" name="name" value="<?= $text($p['name']) ?>" required>
        </div>
        <div class="field">
            <label class="field-label">Plan Türü</label>
            <select class="select" name="plan_type" data-aff-plan-type>
                <option value="revshare" <?= $planType === 'revshare' ? 'selected' : '' ?>>RevShare</option>
                <option value="cpa" <?= $planType === 'cpa' ? 'selected' : '' ?>>CPA</option>
                <option value="hybrid" <?= $planType === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
            </select>
        </div>
        <div class="field" data-aff-field="revshare" <?= $showRev ? '' : 'hidden' ?>>
            <label class="field-label">RevShare Oranı (%)</label>
            <input class="input" type="number" name="revshare_rate" step="0.01" min="0" max="100" value="<?= $text($num($p['revshare_rate'])) ?>" inputmode="decimal">
        </div>
        <div class="field" data-aff-field="cpa" <?= $showCpa ? '' : 'hidden' ?>>
            <label class="field-label">CPA Tutarı (₺)</label>
            <input class="input" type="number" name="cpa_amount" step="0.01" min="0" value="<?= $text($num($p['cpa_amount'])) ?>" inputmode="decimal">
        </div>
        <div class="field">
            <label class="field-label">Min. Depozito (₺)</label>
            <input class="input" type="number" name="min_deposit" step="0.01" min="0" value="<?= $text($num($p['min_deposit'])) ?>" inputmode="decimal">
        </div>
        <div class="field">
            <label class="field-label">Açıklama</label>
            <input class="input" name="description" value="<?= $text($p['description'] ?? '') ?>">
        </div>
        <div class="field" style="display:flex;gap:18px;flex-wrap:wrap">
            <label><input type="checkbox" name="is_active" value="1" <?= !empty($p['is_active']) ? 'checked' : '' ?>> Aktif</label>
            <label><input type="checkbox" name="is_default" value="1" <?= !empty($p['is_default']) ? 'checked' : '' ?>> Varsayılan yap</label>
        </div>
    </div>
    <div class="form-actions">
        <button class="btn btn--primary" type="submit">Güncelle</button>
    </div>
</form>
</div>
<?php endforeach; ?>

<script>
(function () {
    function syncPlanFields(form) {
        if (!form) return;
        var typeSelect = form.querySelector('[data-aff-plan-type]');
        if (!typeSelect) return;
        var val = typeSelect.value;
        var rev = form.querySelector('[data-aff-field="revshare"]');
        var cpa = form.querySelector('[data-aff-field="cpa"]');
        if (rev) rev.hidden = !(val === 'revshare' || val === 'hybrid');
        if (cpa) cpa.hidden = !(val === 'cpa' || val === 'hybrid');
    }

    document.addEventListener('change', function (event) {
        var target = event.target;
        if (!target || !target.matches || !target.matches('[data-aff-plan-type]')) return;
        syncPlanFields(target.closest('[data-aff-plan-form]'));
    });

    // When admin modal clones form HTML, re-sync fields inside the live modal.
    document.addEventListener('click', function (event) {
        var trigger = event.target && event.target.closest ? event.target.closest('[data-admin-modal-inline]') : null;
        if (!trigger) return;
        setTimeout(function () {
            var modal = document.querySelector('.admin-modal-body [data-aff-plan-form]');
            syncPlanFields(modal);
        }, 0);
    });
})();
</script>
